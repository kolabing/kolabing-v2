<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Jobs\SyncInstagramAccount;
use App\Models\BusinessProfile;
use App\Models\CommunityProfile;
use App\Models\InstagramAccount;
use App\Models\InstagramDataDeletionRequest;
use App\Models\Kolab;
use App\Models\Profile;
use App\Models\ProfileGalleryPhoto;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Instagram Connect (BE-NF-75). Every Instagram / Meta call is faked with
 * Http::fake; shapes follow the Meta docs for "Instagram API with Instagram
 * Login" (authorize → code → short-lived → long-lived → refresh, /me, /me/media,
 * IG Media with children{…}, signed_request callbacks).
 */
class InstagramConnectTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const SECRET = 'test-instagram-secret';

    private const IG_USER_ID = '17841400000000001';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'filesystems.uploads_disk' => 'public',
            'services.instagram.enabled' => true,
            'services.instagram.app_id' => '990000000000001',
            'services.instagram.app_secret' => self::SECRET,
            'services.instagram.redirect_uri' => 'https://kolabing.com/instagram/callback',
            'services.instagram.tester_profile_ids' => [],
            'services.instagram.max_video_mb' => 1,
        ]);
        Storage::fake('public');
    }

    /*
    |--------------------------------------------------------------------------
    | Fixtures
    |--------------------------------------------------------------------------
    */

    private function jpeg(): string
    {
        return "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00".str_repeat("\x00", 64)."\xFF\xD9";
    }

    private function mp4(int $padding = 256): string
    {
        return "\x00\x00\x00\x18ftypmp42\x00\x00\x00\x00mp42isom".str_repeat("\x00", $padding);
    }

    private function business(array $attributes = []): Profile
    {
        $profile = Profile::factory()->business()->create(['avatar_url' => null] + $attributes);
        BusinessProfile::factory()->create(['profile_id' => $profile->id, 'profile_photo' => null]);

        return $profile->fresh();
    }

    private function connected(?Profile $profile = null, array $attributes = []): InstagramAccount
    {
        $profile ??= $this->business();

        return InstagramAccount::factory()->forProfile($profile)->create([
            'ig_user_id' => self::IG_USER_ID,
            'ig_app_scoped_id' => '26000000000000001',
            'username' => 'cafe.rosa',
            'access_token' => 'LONG-TOKEN',
        ] + $attributes);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function me(array $overrides = []): array
    {
        return $overrides + [
            'id' => '26000000000000001',
            'user_id' => self::IG_USER_ID,
            'username' => 'cafe.rosa',
            'name' => 'Café Rosa',
            'account_type' => 'BUSINESS',
            'profile_picture_url' => 'https://scontent.cdninstagram.com/pic.jpg',
        ];
    }

    /**
     * Fakes for the OAuth exchange; `me` / `short` override the Graph answers.
     *
     * @param  array<string, mixed>|null  $me
     * @param  array<string, mixed>|null  $short
     */
    private function fakeOauth(?array $me = null, ?array $short = null, int $shortStatus = 200): void
    {
        Http::fake([
            'api.instagram.com/oauth/access_token' => Http::response($short ?? [
                'access_token' => 'SHORT-TOKEN',
                'user_id' => (int) self::IG_USER_ID,
                'permissions' => ['instagram_business_basic'],
            ], $shortStatus),
            'graph.instagram.com/access_token*' => Http::response([
                'access_token' => 'LONG-TOKEN',
                'token_type' => 'bearer',
                'expires_in' => 5183944,
            ]),
            'graph.instagram.com/v25.0/me*' => Http::response($me ?? $this->me()),
        ]);
    }

    private function stateFor(Profile $profile, string $return = 'app'): string
    {
        $url = $this->actingAs($profile)
            ->postJson('/api/v1/me/instagram/connect-url', ['return' => $return])
            ->assertOk()
            ->json('data.url');

        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        return $query['state'];
    }

    private function signedRequest(array $payload, string $secret = self::SECRET): string
    {
        $enc = fn (string $v): string => rtrim(strtr(base64_encode($v), '+/', '-_'), '=');
        $body = $enc((string) json_encode($payload));

        return $enc(hash_hmac('sha256', $body, $secret, true)).'.'.$body;
    }

    /*
    |--------------------------------------------------------------------------
    | Status + availability
    |--------------------------------------------------------------------------
    */

    public function test_status_requires_authentication(): void
    {
        $this->getJson('/api/v1/me/instagram')->assertStatus(401);
    }

    public function test_status_when_not_connected(): void
    {
        $this->actingAs($this->business())
            ->getJson('/api/v1/me/instagram')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.connected', false)
            ->assertJsonPath('data.username', null)
            ->assertJsonPath('data.enabled', true)
            ->assertJsonStructure(['data' => ['connected', 'username', 'account_type', 'profile_picture_url', 'connected_at', 'last_synced_at', 'enabled']]);
    }

    public function test_status_when_connected(): void
    {
        $account = $this->connected();

        $this->actingAs($account->profile)
            ->getJson('/api/v1/me/instagram')
            ->assertOk()
            ->assertJsonPath('data.connected', true)
            ->assertJsonPath('data.username', 'cafe.rosa')
            ->assertJsonPath('data.account_type', 'BUSINESS')
            ->assertJsonPath('data.avatar_suggested', true)
            ->assertJsonMissingPath('data.access_token');
    }

    public function test_feature_off_hides_everything_but_status(): void
    {
        config(['services.instagram.enabled' => false]);
        $profile = $this->business();

        $this->actingAs($profile)->getJson('/api/v1/me/instagram')->assertOk()->assertJsonPath('data.enabled', false);
        $this->actingAs($profile)->postJson('/api/v1/me/instagram/connect-url')
            ->assertStatus(404)->assertJsonPath('code', 'INSTAGRAM_UNAVAILABLE');
        $this->actingAs($profile)->getJson('/api/v1/me/instagram/media')->assertStatus(404);
    }

    public function test_listed_tester_can_connect_while_feature_is_off(): void
    {
        $profile = $this->business();
        config(['services.instagram.enabled' => false, 'services.instagram.tester_profile_ids' => [$profile->id]]);

        $this->actingAs($profile)->getJson('/api/v1/me/instagram')->assertJsonPath('data.enabled', true);
        $this->actingAs($profile)->postJson('/api/v1/me/instagram/connect-url')->assertOk();
    }

    public function test_missing_app_credentials_disable_the_feature(): void
    {
        config(['services.instagram.app_secret' => null]);

        $this->actingAs($this->business())->postJson('/api/v1/me/instagram/connect-url')->assertStatus(404);
    }

    public function test_attendees_cannot_connect(): void
    {
        $attendee = Profile::factory()->attendee()->create();

        $this->actingAs($attendee)->getJson('/api/v1/me/instagram')->assertJsonPath('data.enabled', false);
        $this->actingAs($attendee)->postJson('/api/v1/me/instagram/connect-url')->assertStatus(404);
    }

    public function test_community_can_get_a_connect_url(): void
    {
        $community = Profile::factory()->community()->create();

        $url = $this->actingAs($community)->postJson('/api/v1/me/instagram/connect-url')
            ->assertOk()
            ->json('data.url');

        $this->assertStringStartsWith('https://www.instagram.com/oauth/authorize?', $url);
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $this->assertSame('990000000000001', $query['client_id']);
        $this->assertSame('https://kolabing.com/instagram/callback', $query['redirect_uri']);
        $this->assertSame('code', $query['response_type']);
        $this->assertSame('instagram_business_basic', $query['scope']);
        $this->assertNotEmpty($query['state']);
        $this->assertStringNotContainsString($community->id, $query['state']);
    }

    /*
    |--------------------------------------------------------------------------
    | OAuth callback
    |--------------------------------------------------------------------------
    */

    public function test_callback_connects_and_redirects_to_the_app(): void
    {
        $profile = $this->business();
        $state = $this->stateFor($profile);
        $this->fakeOauth();

        $this->get('/instagram/callback?'.http_build_query(['code' => 'AQD-code#_', 'state' => $state]))
            ->assertRedirect('kolabing://instagram/connected?status=ok');

        $account = InstagramAccount::query()->where('profile_id', $profile->id)->firstOrFail();
        $this->assertSame('cafe.rosa', $account->username);
        $this->assertSame(self::IG_USER_ID, $account->ig_user_id);
        $this->assertSame('BUSINESS', $account->account_type);
        $this->assertSame('LONG-TOKEN', $account->access_token);
        $this->assertTrue($account->token_expires_at->between(now()->addDays(59), now()->addDays(61)));

        // Encrypted at rest.
        $raw = DB::table('instagram_accounts')->where('id', $account->id)->value('access_token');
        $this->assertNotSame('LONG-TOKEN', $raw);
        $this->assertStringNotContainsString('LONG-TOKEN', $raw);

        Http::assertSent(fn (HttpRequest $r): bool => str_contains($r->url(), 'api.instagram.com/oauth/access_token')
            && $r['code'] === 'AQD-code'
            && $r['grant_type'] === 'authorization_code'
            && $r['client_secret'] === self::SECRET);
        Http::assertSent(fn (HttpRequest $r): bool => str_contains($r->url(), 'grant_type=ig_exchange_token'));
    }

    public function test_callback_accepts_the_documented_data_wrapped_token_response(): void
    {
        $profile = $this->business();
        $state = $this->stateFor($profile);
        $this->fakeOauth(short: ['data' => [[
            'access_token' => 'SHORT-TOKEN',
            'user_id' => self::IG_USER_ID,
            'permissions' => 'instagram_business_basic,instagram_business_manage_messages',
        ]]]);

        $this->get('/instagram/callback?'.http_build_query(['code' => 'c', 'state' => $state]))
            ->assertRedirect('kolabing://instagram/connected?status=ok');
    }

    public function test_callback_redirects_to_the_web_app_when_asked(): void
    {
        $profile = $this->business();
        $state = $this->stateFor($profile, 'web');
        $this->fakeOauth();

        $this->get('/instagram/callback?'.http_build_query(['code' => 'c', 'state' => $state]))
            ->assertRedirect('https://app.kolabing.com/settings?instagram=ok');
    }

    public function test_callback_reconnect_updates_the_same_row(): void
    {
        $account = $this->connected(null, ['username' => 'old.name', 'access_token' => null, 'disconnected_at' => now()]);
        $state = $this->stateFor($account->profile);
        $this->fakeOauth();

        $this->get('/instagram/callback?'.http_build_query(['code' => 'c', 'state' => $state]))->assertRedirect();

        $this->assertSame(1, InstagramAccount::query()->where('profile_id', $account->profile_id)->count());
        $fresh = $account->fresh();
        $this->assertSame('cafe.rosa', $fresh->username);
        $this->assertTrue($fresh->isConnected());
        $this->assertNull($fresh->disconnected_at);
    }

    public function test_callback_rejects_an_invalid_state(): void
    {
        Http::fake();

        $this->get('/instagram/callback?'.http_build_query(['code' => 'c', 'state' => 'forged']))
            ->assertRedirect('kolabing://instagram/connected?status=error&reason=invalid_state');

        Http::assertNothingSent();
        $this->assertSame(0, InstagramAccount::query()->count());
    }

    public function test_callback_state_is_single_use(): void
    {
        $profile = $this->business();
        $state = $this->stateFor($profile);
        $this->fakeOauth();

        $this->get('/instagram/callback?'.http_build_query(['code' => 'c', 'state' => $state]))
            ->assertRedirect('kolabing://instagram/connected?status=ok');
        $this->get('/instagram/callback?'.http_build_query(['code' => 'c', 'state' => $state]))
            ->assertRedirect('kolabing://instagram/connected?status=error&reason=invalid_state');
    }

    public function test_callback_rejects_an_expired_state(): void
    {
        $profile = $this->business();
        $state = $this->stateFor($profile, 'web');
        $this->fakeOauth();

        $this->travel(16)->minutes();

        $this->get('/instagram/callback?'.http_build_query(['code' => 'c', 'state' => $state]))
            ->assertRedirect('https://app.kolabing.com/settings?instagram=error&reason=invalid_state');
    }

    public function test_callback_when_the_user_denies_permission(): void
    {
        $profile = $this->business();
        $state = $this->stateFor($profile);
        Http::fake();

        $this->get('/instagram/callback?'.http_build_query([
            'error' => 'access_denied',
            'error_reason' => 'user_denied',
            'error_description' => 'The user denied your request',
            'state' => $state,
        ]))->assertRedirect('kolabing://instagram/connected?status=error&reason=denied');

        Http::assertNothingSent();
    }

    public function test_callback_refuses_a_personal_account(): void
    {
        $profile = $this->business();
        $state = $this->stateFor($profile);
        $this->fakeOauth(me: $this->me(['account_type' => 'PERSONAL']));

        $this->get('/instagram/callback?'.http_build_query(['code' => 'c', 'state' => $state]))
            ->assertRedirect('kolabing://instagram/connected?status=error&reason=personal_account');

        $this->assertSame(0, InstagramAccount::query()->count());
    }

    public function test_callback_when_the_basic_permission_was_not_granted(): void
    {
        $profile = $this->business();
        $state = $this->stateFor($profile);
        $this->fakeOauth(short: ['access_token' => 'SHORT', 'user_id' => 1, 'permissions' => []]);

        $this->get('/instagram/callback?'.http_build_query(['code' => 'c', 'state' => $state]))
            ->assertRedirect('kolabing://instagram/connected?status=error&reason=permission_denied');

        $this->assertSame(0, InstagramAccount::query()->count());
    }

    public function test_callback_when_the_code_exchange_fails(): void
    {
        $profile = $this->business();
        $state = $this->stateFor($profile);
        $this->fakeOauth(short: ['error_type' => 'OAuthException', 'code' => 400, 'error_message' => 'Invalid authorization code'], shortStatus: 400);

        $this->get('/instagram/callback?'.http_build_query(['code' => 'c', 'state' => $state]))
            ->assertRedirect('kolabing://instagram/connected?status=error&reason=error');
    }

    public function test_callback_when_the_feature_was_switched_off_meanwhile(): void
    {
        $profile = $this->business();
        $state = $this->stateFor($profile);
        config(['services.instagram.enabled' => false]);
        Http::fake();

        $this->get('/instagram/callback?'.http_build_query(['code' => 'c', 'state' => $state]))
            ->assertRedirect('kolabing://instagram/connected?status=error&reason=disabled');
    }

    /*
    |--------------------------------------------------------------------------
    | Media list
    |--------------------------------------------------------------------------
    */

    public function test_media_lists_items_with_imported_flag_and_cursor(): void
    {
        $account = $this->connected();
        ProfileGalleryPhoto::factory()->forProfile($account->profile)->create(['instagram_source_id' => '1002', 'instagram_media_id' => '1002']);

        Http::fake([
            'graph.instagram.com/v25.0/me/media*' => Http::response([
                'data' => [
                    ['id' => '1001', 'media_type' => 'IMAGE', 'media_url' => 'https://scontent.cdninstagram.com/1001.jpg', 'permalink' => 'https://www.instagram.com/p/A/', 'caption' => 'Brunch', 'timestamp' => '2026-09-20T10:00:00+0000'],
                    ['id' => '1002', 'media_type' => 'VIDEO', 'media_url' => 'https://scontent.cdninstagram.com/1002.mp4', 'thumbnail_url' => 'https://scontent.cdninstagram.com/1002.jpg', 'permalink' => 'https://www.instagram.com/reel/B/', 'timestamp' => '2026-09-19T10:00:00+0000'],
                    ['id' => '1003', 'media_type' => 'CAROUSEL_ALBUM', 'media_url' => 'https://scontent.cdninstagram.com/1003.jpg', 'permalink' => 'https://www.instagram.com/p/C/', 'timestamp' => '2026-09-18T10:00:00+0000'],
                ],
                'paging' => ['cursors' => ['before' => 'B', 'after' => 'NEXT-CURSOR'], 'next' => 'https://graph.instagram.com/v25.0/me/media?after=NEXT-CURSOR'],
            ]),
        ]);

        $response = $this->actingAs($account->profile)->getJson('/api/v1/me/instagram/media')->assertOk();

        $response->assertJsonPath('data.next_cursor', 'NEXT-CURSOR')
            ->assertJsonCount(3, 'data.items')
            ->assertJsonPath('data.items.0.id', '1001')
            ->assertJsonPath('data.items.0.media_type', 'IMAGE')
            ->assertJsonPath('data.items.0.imported', false)
            ->assertJsonPath('data.items.1.imported', true)
            ->assertJsonPath('data.items.1.thumbnail_url', 'https://scontent.cdninstagram.com/1002.jpg')
            ->assertJsonPath('data.items.2.media_type', 'CAROUSEL_ALBUM')
            ->assertJsonStructure(['data' => ['items' => ['*' => ['id', 'media_type', 'media_url', 'thumbnail_url', 'permalink', 'caption', 'timestamp', 'imported']], 'next_cursor']]);

        Http::assertSent(fn (HttpRequest $r): bool => str_contains($r->url(), 'access_token=LONG-TOKEN'));
    }

    public function test_media_passes_the_cursor_and_ends_without_next(): void
    {
        $account = $this->connected();
        Http::fake(['graph.instagram.com/v25.0/me/media*' => Http::response(['data' => [], 'paging' => ['cursors' => ['after' => 'X']]])]);

        $this->actingAs($account->profile)->getJson('/api/v1/me/instagram/media?cursor=NEXT-CURSOR')
            ->assertOk()
            ->assertJsonPath('data.next_cursor', null)
            ->assertJsonCount(0, 'data.items');

        Http::assertSent(fn (HttpRequest $r): bool => str_contains($r->url(), 'after=NEXT-CURSOR'));
    }

    public function test_media_requires_a_connection(): void
    {
        $this->actingAs($this->business())->getJson('/api/v1/me/instagram/media')
            ->assertStatus(409)
            ->assertJsonPath('code', 'INSTAGRAM_NOT_CONNECTED');
    }

    public function test_expired_token_disconnects_and_answers_409(): void
    {
        $account = $this->connected();
        Http::fake(['graph.instagram.com/*' => Http::response([
            'error' => ['message' => 'Error validating access token: Session has expired', 'type' => 'OAuthException', 'code' => 190, 'error_subcode' => 463],
        ], 400)]);

        $this->actingAs($account->profile)->getJson('/api/v1/me/instagram/media')
            ->assertStatus(409)
            ->assertJsonPath('code', 'INSTAGRAM_TOKEN_EXPIRED');

        $this->assertNull($account->fresh()->access_token);
        $this->actingAs($account->profile)->getJson('/api/v1/me/instagram')->assertJsonPath('data.connected', false);
    }

    public function test_instagram_outage_answers_502(): void
    {
        $account = $this->connected();
        Http::fake(['graph.instagram.com/*' => Http::response(['error' => ['message' => 'Please retry', 'type' => 'OAuthException', 'code' => 2]], 500)]);

        $this->actingAs($account->profile)->getJson('/api/v1/me/instagram/media')
            ->assertStatus(502)
            ->assertJsonPath('code', 'INSTAGRAM_API_ERROR');

        $this->assertNotNull($account->fresh()->access_token);
    }

    /*
    |--------------------------------------------------------------------------
    | Import
    |--------------------------------------------------------------------------
    */

    public function test_import_an_image_into_the_gallery(): void
    {
        $account = $this->connected();
        Http::fake([
            'graph.instagram.com/v25.0/2001*' => Http::response(['id' => '2001', 'media_type' => 'IMAGE', 'media_url' => 'https://scontent.cdninstagram.com/2001.jpg', 'caption' => 'Sunday brunch', 'username' => 'cafe.rosa']),
            'scontent.cdninstagram.com/*' => Http::response($this->jpeg(), 200, ['Content-Type' => 'image/jpeg']),
        ]);

        $response = $this->actingAs($account->profile)
            ->postJson('/api/v1/me/instagram/media/import', ['ids' => ['2001'], 'target' => 'gallery'])
            ->assertStatus(201)
            ->assertJsonPath('data.target', 'gallery')
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.media_type', 'image')
            ->assertJsonPath('data.items.0.video_url', null)
            ->assertJsonPath('data.items.0.source', 'instagram')
            ->assertJsonPath('data.items.0.caption', 'Sunday brunch')
            ->assertJsonCount(0, 'data.skipped');

        $photo = ProfileGalleryPhoto::query()->where('profile_id', $account->profile_id)->firstOrFail();
        $this->assertSame('2001', $photo->instagram_source_id);
        $this->assertStringContainsString('/gallery/'.$account->profile_id.'/', $photo->url);
        $this->assertCount(1, Storage::disk('public')->files('gallery/'.$account->profile_id));
        $this->assertSame($photo->id, $response->json('data.items.0.id'));
    }

    public function test_import_expands_a_carousel_and_stores_videos_with_a_thumbnail(): void
    {
        $account = $this->connected();
        Http::fake([
            'graph.instagram.com/v25.0/3000*' => Http::response([
                'id' => '3000', 'media_type' => 'CAROUSEL_ALBUM', 'username' => 'cafe.rosa', 'caption' => 'Our week',
                'children' => ['data' => [
                    ['id' => '3001', 'media_type' => 'IMAGE', 'media_url' => 'https://scontent.cdninstagram.com/3001.jpg'],
                    ['id' => '3002', 'media_type' => 'VIDEO', 'media_url' => 'https://scontent.cdninstagram.com/3002.mp4', 'thumbnail_url' => 'https://scontent.cdninstagram.com/3002.jpg'],
                ]],
            ]),
            'scontent.cdninstagram.com/*.mp4' => Http::response($this->mp4(), 200, ['Content-Type' => 'video/mp4']),
            'scontent.cdninstagram.com/*' => Http::response($this->jpeg(), 200, ['Content-Type' => 'image/jpeg']),
        ]);

        $this->actingAs($account->profile)
            ->postJson('/api/v1/me/instagram/media/import', ['ids' => ['3000']])
            ->assertStatus(201)
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.items.0.media_type', 'image')
            ->assertJsonPath('data.items.1.media_type', 'video');

        $video = ProfileGalleryPhoto::query()->where('instagram_media_id', '3002')->firstOrFail();
        $this->assertSame('video', $video->media_type);
        $this->assertStringEndsWith('.mp4', (string) $video->video_url);
        $this->assertStringEndsWith('.jpg', $video->url);
        $this->assertSame('3000', $video->instagram_source_id);
        // image + video + poster, all under the gallery path.
        $this->assertCount(3, Storage::disk('public')->files('gallery/'.$account->profile_id));

        Http::assertSent(fn (HttpRequest $r): bool => str_contains(urldecode($r->url()), 'children{id,media_type,media_url,thumbnail_url}'));

        // The listing now flags the carousel as imported.
        Http::fake(['graph.instagram.com/v25.0/me/media*' => Http::response(['data' => [['id' => '3000', 'media_type' => 'CAROUSEL_ALBUM']]])]);
        $this->actingAs($account->profile)->getJson('/api/v1/me/instagram/media')->assertJsonPath('data.items.0.imported', true);
    }

    public function test_import_skips_what_cannot_be_imported(): void
    {
        $account = $this->connected();
        ProfileGalleryPhoto::factory()->forProfile($account->profile)->create(['instagram_source_id' => '4000']);

        Http::fake([
            // Copyrighted audio: Instagram omits media_url.
            'graph.instagram.com/v25.0/4001*' => Http::response(['id' => '4001', 'media_type' => 'VIDEO', 'thumbnail_url' => 'https://scontent.cdninstagram.com/4001.jpg', 'username' => 'cafe.rosa']),
            // Someone else's media.
            'graph.instagram.com/v25.0/4002*' => Http::response(['id' => '4002', 'media_type' => 'IMAGE', 'media_url' => 'https://scontent.cdninstagram.com/4002.jpg', 'username' => 'someone.else']),
            // Bigger than INSTAGRAM_MAX_VIDEO_MB (1 MB in this test).
            'graph.instagram.com/v25.0/4003*' => Http::response(['id' => '4003', 'media_type' => 'VIDEO', 'media_url' => 'https://scontent.cdninstagram.com/4003.mp4', 'thumbnail_url' => 'https://scontent.cdninstagram.com/4003.jpg', 'username' => 'cafe.rosa']),
            // Not found / not ours.
            'graph.instagram.com/v25.0/4004*' => Http::response(['error' => ['message' => 'Unsupported get request', 'type' => 'GraphMethodException', 'code' => 100]], 400),
            'scontent.cdninstagram.com/4003.mp4' => Http::response($this->mp4(1024 * 1024 + 10), 200, ['Content-Type' => 'video/mp4']),
            'scontent.cdninstagram.com/*' => Http::response($this->jpeg(), 200),
        ]);

        $this->actingAs($account->profile)
            ->postJson('/api/v1/me/instagram/media/import', ['ids' => ['4000', '4001', '4002', '4003', '4004']])
            ->assertOk()
            ->assertJsonCount(0, 'data.items')
            ->assertJsonPath('data.skipped', [
                ['id' => '4000', 'reason' => 'already_imported'],
                ['id' => '4001', 'reason' => 'no_media_url'],
                ['id' => '4002', 'reason' => 'not_owner'],
                ['id' => '4003', 'reason' => 'too_large'],
                ['id' => '4004', 'reason' => 'not_found'],
            ]);

        $this->assertSame(1, ProfileGalleryPhoto::query()->where('profile_id', $account->profile_id)->count());
    }

    public function test_import_respects_the_gallery_limit(): void
    {
        $account = $this->connected();
        ProfileGalleryPhoto::factory()->count(19)->forProfile($account->profile)->create();
        Http::fake([
            'graph.instagram.com/v25.0/5000*' => Http::response([
                'id' => '5000', 'media_type' => 'CAROUSEL_ALBUM', 'username' => 'cafe.rosa',
                'children' => ['data' => [
                    ['id' => '5001', 'media_type' => 'IMAGE', 'media_url' => 'https://scontent.cdninstagram.com/5001.jpg'],
                    ['id' => '5002', 'media_type' => 'IMAGE', 'media_url' => 'https://scontent.cdninstagram.com/5002.jpg'],
                ]],
            ]),
            'scontent.cdninstagram.com/*' => Http::response($this->jpeg()),
        ]);

        $this->actingAs($account->profile)
            ->postJson('/api/v1/me/instagram/media/import', ['ids' => ['5000']])
            ->assertStatus(201)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.skipped.0', ['id' => '5002', 'reason' => 'limit_reached']);

        // Now full: the next import is refused outright.
        $this->actingAs($account->profile)
            ->postJson('/api/v1/me/instagram/media/import', ['ids' => ['5000']])
            ->assertStatus(422)
            ->assertJsonPath('code', 'INSTAGRAM_GALLERY_FULL');
    }

    public function test_import_validates_the_payload(): void
    {
        $account = $this->connected();

        $this->actingAs($account->profile)->postJson('/api/v1/me/instagram/media/import', [])->assertStatus(422);
        $this->actingAs($account->profile)->postJson('/api/v1/me/instagram/media/import', ['ids' => ['../etc']])->assertStatus(422);
        $this->actingAs($account->profile)->postJson('/api/v1/me/instagram/media/import', ['ids' => ['1'], 'target' => 'kolab'])->assertStatus(422);
        $this->actingAs($account->profile)->postJson('/api/v1/me/instagram/media/import', ['ids' => array_map('strval', range(1, 11))])->assertStatus(422);
    }

    public function test_import_into_an_own_kolab(): void
    {
        $account = $this->connected();
        $kolab = Kolab::factory()->forCreator($account->profile)->create(['media' => [
            ['url' => 'https://cdn.kolabing.com/existing.jpg', 'type' => 'image', 'thumbnail_url' => null, 'sort_order' => 0],
        ]]);
        Http::fake([
            'graph.instagram.com/v25.0/6001*' => Http::response(['id' => '6001', 'media_type' => 'VIDEO', 'media_url' => 'https://scontent.cdninstagram.com/6001.mp4', 'thumbnail_url' => 'https://scontent.cdninstagram.com/6001.jpg', 'username' => 'cafe.rosa']),
            'scontent.cdninstagram.com/*.mp4' => Http::response($this->mp4()),
            'scontent.cdninstagram.com/*' => Http::response($this->jpeg()),
        ]);

        $this->actingAs($account->profile)
            ->postJson('/api/v1/me/instagram/media/import', ['ids' => ['6001'], 'target' => 'kolab', 'kolab_id' => $kolab->id])
            ->assertStatus(201)
            ->assertJsonPath('data.target', 'kolab')
            ->assertJsonPath('data.items.0.type', 'video')
            ->assertJsonPath('data.items.0.sort_order', 1);

        $media = $kolab->fresh()->media;
        $this->assertCount(2, $media);
        $this->assertStringEndsWith('.mp4', $media[1]['url']);
        $this->assertStringEndsWith('.jpg', $media[1]['thumbnail_url']);
        $this->assertSame('instagram', $media[1]['source']);
        $this->assertSame(0, ProfileGalleryPhoto::query()->where('profile_id', $account->profile_id)->count());
    }

    public function test_import_into_someone_elses_kolab_is_forbidden(): void
    {
        $account = $this->connected();
        $kolab = Kolab::factory()->create();
        Http::fake();

        $this->actingAs($account->profile)
            ->postJson('/api/v1/me/instagram/media/import', ['ids' => ['6001'], 'target' => 'kolab', 'kolab_id' => $kolab->id])
            ->assertStatus(403);

        Http::assertNothingSent();
    }

    public function test_gallery_delete_removes_the_video_file_too(): void
    {
        $account = $this->connected();
        Storage::disk('public')->put('gallery/'.$account->profile_id.'/v.mp4', 'x');
        Storage::disk('public')->put('gallery/'.$account->profile_id.'/p.jpg', 'x');
        $photo = ProfileGalleryPhoto::factory()->forProfile($account->profile)->create([
            'url' => url('/storage/gallery/'.$account->profile_id.'/p.jpg'),
            'video_url' => url('/storage/gallery/'.$account->profile_id.'/v.mp4'),
            'media_type' => 'video',
        ]);

        $this->actingAs($account->profile)->deleteJson('/api/v1/me/gallery/'.$photo->id)->assertOk();

        $this->assertSame([], Storage::disk('public')->files('gallery/'.$account->profile_id));
    }

    /*
    |--------------------------------------------------------------------------
    | Sync, avatar, disconnect
    |--------------------------------------------------------------------------
    */

    public function test_sync_is_queued(): void
    {
        Queue::fake();
        $account = $this->connected();

        $this->actingAs($account->profile)->postJson('/api/v1/me/instagram/sync')
            ->assertStatus(202)
            ->assertJsonPath('data.queued', true);

        Queue::assertPushed(SyncInstagramAccount::class, fn (SyncInstagramAccount $job): bool => $job->accountId === $account->id);
    }

    public function test_sync_job_refreshes_profile_and_media(): void
    {
        $account = $this->connected();
        Http::fake([
            'graph.instagram.com/v25.0/me/media*' => Http::response(['data' => [['id' => '7001', 'media_type' => 'IMAGE', 'media_url' => 'https://scontent.cdninstagram.com/7001.jpg']]]),
            'graph.instagram.com/v25.0/me*' => Http::response($this->me(['username' => 'cafe.rosa.bcn', 'account_type' => 'MEDIA_CREATOR'])),
        ]);

        (new SyncInstagramAccount($account->id))->handle(app(\App\Services\InstagramService::class));

        $fresh = $account->fresh();
        $this->assertSame('cafe.rosa.bcn', $fresh->username);
        $this->assertSame('MEDIA_CREATOR', $fresh->account_type);
        $this->assertNotNull($fresh->last_synced_at);

        // The first page is served from the warmed cache.
        Http::fake(fn () => Http::response([], 500));
        $this->actingAs($account->profile)->getJson('/api/v1/me/instagram/media')
            ->assertOk()
            ->assertJsonPath('data.items.0.id', '7001');
    }

    public function test_sync_job_with_an_expired_token_disconnects_quietly(): void
    {
        $account = $this->connected();
        Http::fake(['graph.instagram.com/*' => Http::response(['error' => ['type' => 'OAuthException', 'code' => 190]], 400)]);

        (new SyncInstagramAccount($account->id))->handle(app(\App\Services\InstagramService::class));

        $this->assertFalse($account->fresh()->isConnected());
    }

    public function test_avatar_is_offered_and_applied_only_when_empty(): void
    {
        $account = $this->connected();
        Http::fake(['scontent.cdninstagram.com/*' => Http::response($this->jpeg())]);

        $url = $this->actingAs($account->profile)->postJson('/api/v1/me/instagram/avatar')
            ->assertOk()
            ->json('data.avatar_url');

        $profile = $account->profile->fresh();
        $this->assertSame($url, $profile->avatar_url);
        $this->assertSame($url, $profile->businessProfile->profile_photo);

        // Now set: never overwritten, and no longer suggested.
        $this->actingAs($profile)->postJson('/api/v1/me/instagram/avatar')
            ->assertStatus(409)
            ->assertJsonPath('code', 'INSTAGRAM_AVATAR_ALREADY_SET');
        $this->actingAs($profile)->getJson('/api/v1/me/instagram')->assertJsonPath('data.avatar_suggested', false);
    }

    public function test_avatar_never_overwrites_an_uploaded_logo(): void
    {
        $community = Profile::factory()->community()->create(['avatar_url' => null]);
        CommunityProfile::factory()->create(['profile_id' => $community->id, 'profile_photo' => 'https://cdn.kolabing.com/logo.png']);
        $account = $this->connected($community->fresh());
        // The mirror hook copied the logo to avatar_url; clear it to test the logo check alone.
        Profile::query()->whereKey($community->id)->update(['avatar_url' => null]);
        Http::fake();

        $this->actingAs($community->fresh())->postJson('/api/v1/me/instagram/avatar')->assertStatus(409);

        $this->assertSame('https://cdn.kolabing.com/logo.png', $community->fresh()->communityProfile->profile_photo);
        Http::assertNothingSent();
        $this->assertNotNull($account);
    }

    public function test_disconnect_forgets_the_token_and_keeps_imported_media(): void
    {
        $account = $this->connected();
        ProfileGalleryPhoto::factory()->forProfile($account->profile)->create(['instagram_source_id' => '8001']);

        $this->actingAs($account->profile)->deleteJson('/api/v1/me/instagram')
            ->assertOk()
            ->assertJsonPath('data.connected', false);

        $fresh = $account->fresh();
        $this->assertNull($fresh->access_token);
        $this->assertNotNull($fresh->disconnected_at);
        $this->assertSame(1, ProfileGalleryPhoto::query()->where('profile_id', $account->profile_id)->count());
    }

    /*
    |--------------------------------------------------------------------------
    | Meta callbacks (signed_request)
    |--------------------------------------------------------------------------
    */

    public function test_deauthorize_with_a_valid_signed_request(): void
    {
        $account = $this->connected();

        $this->post('/instagram/deauthorize', ['signed_request' => $this->signedRequest([
            'algorithm' => 'HMAC-SHA256', 'issued_at' => time(), 'user_id' => self::IG_USER_ID,
        ])])->assertOk();

        $this->assertNull($account->fresh()->access_token);
    }

    public function test_deauthorize_matches_the_app_scoped_id_too(): void
    {
        $account = $this->connected();

        $this->post('/instagram/deauthorize', ['signed_request' => $this->signedRequest([
            'algorithm' => 'HMAC-SHA256', 'issued_at' => time(), 'user_id' => '26000000000000001',
        ])])->assertOk();

        $this->assertNull($account->fresh()->access_token);
    }

    public function test_callbacks_reject_a_bad_signature(): void
    {
        $account = $this->connected();
        $payload = ['algorithm' => 'HMAC-SHA256', 'issued_at' => time(), 'user_id' => self::IG_USER_ID];

        $this->post('/instagram/deauthorize', ['signed_request' => $this->signedRequest($payload, 'wrong-secret')])->assertStatus(400);
        $this->post('/instagram/data-deletion', ['signed_request' => $this->signedRequest($payload, 'wrong-secret')])->assertStatus(400);
        $this->post('/instagram/deauthorize', ['signed_request' => 'garbage'])->assertStatus(400);
        $this->post('/instagram/deauthorize', [])->assertStatus(400);
        $this->post('/instagram/deauthorize', ['signed_request' => $this->signedRequest(['algorithm' => 'none', 'user_id' => self::IG_USER_ID])])->assertStatus(400);

        $this->assertNotNull($account->fresh()->access_token);
    }

    public function test_an_instagram_import_never_becomes_the_profile_photo(): void
    {
        // The "use your Instagram picture" offer must stay available: an
        // imported post (maybe a product shot) is not silently the avatar.
        $account = $this->connected();
        Http::fake([
            'graph.instagram.com/v25.0/2001*' => Http::response(['id' => '2001', 'media_type' => 'IMAGE', 'media_url' => 'https://scontent.cdninstagram.com/2001.jpg', 'username' => 'cafe.rosa']),
            'scontent.cdninstagram.com/*' => Http::response($this->jpeg(), 200, ['Content-Type' => 'image/jpeg']),
        ]);

        $this->actingAs($account->profile)
            ->postJson('/api/v1/me/instagram/media/import', ['ids' => ['2001'], 'target' => 'gallery'])
            ->assertStatus(201);

        $profile = $account->profile->fresh();
        $this->assertNull($profile->avatar_url);
        $this->assertNull($profile->businessProfile->profile_photo);
    }

    public function test_data_deletion_clears_a_profile_photo_that_was_an_imported_photo(): void
    {
        $account = $this->connected();
        $imported = ProfileGalleryPhoto::factory()->forProfile($account->profile)->create([
            'instagram_source_id' => '9101',
            'url' => 'https://cdn.kolabing.com/gallery/ig-9101.jpg',
        ]);
        $account->profile->businessProfile->forceFill(['profile_photo' => $imported->url])->save();
        $this->assertSame($imported->url, $account->profile->fresh()->avatar_url);

        $this->post('/instagram/data-deletion', ['signed_request' => $this->signedRequest([
            'algorithm' => 'HMAC-SHA256', 'issued_at' => time(), 'user_id' => self::IG_USER_ID,
        ])])->assertOk();

        // The file is gone, so nothing may still point at it.
        $profile = Profile::query()->findOrFail($account->profile_id);
        $this->assertNull($profile->avatar_url);
        $this->assertNull($profile->businessProfile->profile_photo);
    }

    public function test_data_deletion_removes_the_connection_and_imported_media(): void
    {
        $account = $this->connected();
        $profileId = $account->profile_id;
        ProfileGalleryPhoto::factory()->count(2)->forProfile($account->profile)->create(['instagram_source_id' => '9001']);
        $uploaded = ProfileGalleryPhoto::factory()->forProfile($account->profile)->create();
        $kolab = Kolab::factory()->forCreator($account->profile)->create(['media' => [
            ['url' => 'https://cdn.kolabing.com/own.jpg', 'type' => 'image', 'thumbnail_url' => null, 'sort_order' => 0],
            ['url' => 'https://cdn.kolabing.com/ig.jpg', 'type' => 'image', 'thumbnail_url' => null, 'sort_order' => 1, 'source' => 'instagram', 'instagram_media_id' => '9002'],
        ]]);

        $response = $this->post('/instagram/data-deletion', ['signed_request' => $this->signedRequest([
            'algorithm' => 'HMAC-SHA256', 'issued_at' => time(), 'user_id' => self::IG_USER_ID,
        ])])->assertOk()->assertJsonStructure(['url', 'confirmation_code']);

        $code = $response->json('confirmation_code');
        $this->assertStringEndsWith('/instagram/data-deletion/'.$code, $response->json('url'));

        $this->assertSame(0, InstagramAccount::query()->where('profile_id', $profileId)->count());
        $this->assertSame([$uploaded->id], ProfileGalleryPhoto::query()->where('profile_id', $profileId)->pluck('id')->all());
        $this->assertCount(1, $kolab->fresh()->media);
        $this->assertSame(3, InstagramDataDeletionRequest::query()->where('confirmation_code', $code)->value('media_deleted'));

        $this->get('/instagram/data-deletion/'.$code)->assertOk()->assertSee($code)->assertSee('Completed');
        $this->get('/instagram/data-deletion/NOTACODE00')->assertNotFound();
    }

    /*
    |--------------------------------------------------------------------------
    | Scheduled token refresh
    |--------------------------------------------------------------------------
    */

    public function test_refresh_command_refreshes_old_tokens_and_disconnects_expired_ones(): void
    {
        $old = $this->connected(null, ['token_refreshed_at' => now()->subDays(51), 'token_expires_at' => now()->addDays(9)]);
        $recent = InstagramAccount::factory()->create(['token_refreshed_at' => now()->subDays(10), 'access_token' => 'RECENT']);
        $expired = InstagramAccount::factory()->create(['token_expires_at' => now()->subDay(), 'access_token' => 'DEAD']);

        Http::fake(['graph.instagram.com/refresh_access_token*' => Http::response([
            'access_token' => 'REFRESHED-TOKEN', 'token_type' => 'bearer', 'expires_in' => 5183944,
        ])]);

        $this->artisan('instagram:refresh-tokens')->assertSuccessful();

        $this->assertSame('REFRESHED-TOKEN', $old->fresh()->access_token);
        $this->assertTrue($old->fresh()->token_expires_at->greaterThan(now()->addDays(59)));
        $this->assertSame('RECENT', $recent->fresh()->access_token);
        $this->assertNull($expired->fresh()->access_token);

        Http::assertSentCount(1);
        Http::assertSent(fn (HttpRequest $r): bool => str_contains($r->url(), 'grant_type=ig_refresh_token'));
    }

    public function test_refresh_command_disconnects_a_revoked_token(): void
    {
        $account = $this->connected(null, ['token_refreshed_at' => now()->subDays(55)]);
        Http::fake(['graph.instagram.com/*' => Http::response(['error' => ['type' => 'OAuthException', 'code' => 190]], 400)]);

        $this->artisan('instagram:refresh-tokens')->assertSuccessful();

        $this->assertNull($account->fresh()->access_token);
    }

    public function test_plain_get_on_meta_callback_urls_returns_a_page_not_405(): void
    {
        $this->get('/instagram/data-deletion')
            ->assertOk()
            ->assertSee('Delete your Instagram data from Kolabing');

        $this->get('/instagram/deauthorize')->assertOk();
    }
}
