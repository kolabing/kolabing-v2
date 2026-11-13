<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\KolabingTeamOfferKind;
use App\Enums\KolabingTeamOfferStatus;
use App\Enums\UserType;
use App\Jobs\SendTransactionalEmail;
use App\Models\Application;
use App\Models\BusinessProfile;
use App\Models\ChatMessage;
use App\Models\City;
use App\Models\Community;
use App\Models\CommunityProfile;
use App\Models\Kolab;
use App\Models\KolabingTeamOffer;
use App\Models\Profile;
use App\Models\User;
use App\Services\Admin\ManagedProfileService;
use App\Services\GoogleAuthService;
use App\Services\KolabingTeamService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Kolabing team sender, second pass (SERR-1344): the deeplink in the message,
 * the 7-day expiry with one resend, and the unclaimed-profile claim flow that
 * stays off for sending until Daniel turns it on.
 */
class KolabingTeamClaimTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    private function city(): City
    {
        return City::query()->where('name', 'Barcelona')->first()
            ?? City::factory()->create(['name' => 'Barcelona', 'country' => 'Spain', 'is_active' => true]);
    }

    private function maintainer(): User
    {
        return User::factory()->create(['is_maintainer' => true]);
    }

    private function business(array $profile = []): Profile
    {
        $business = Profile::factory()->business()->create([
            'preferred_locale' => 'en',
            'last_active_at' => now()->subDay(),
            ...$profile,
        ]);
        BusinessProfile::factory()->create([
            'profile_id' => $business->id,
            'name' => 'Cafe Mallorca',
            'city_id' => $this->city()->id,
            'city_name' => 'Barcelona',
        ]);

        return $business;
    }

    private function community(array $profile = []): Profile
    {
        $community = Profile::factory()->community()->create([
            'preferred_locale' => 'en',
            'last_active_at' => now()->subDay(),
            ...$profile,
        ]);
        CommunityProfile::factory()->create([
            'profile_id' => $community->id,
            'name' => 'Sunrise Run Club',
            'city_id' => $this->city()->id,
            'community_type' => 'run_club',
            'community_size' => 120,
        ]);

        return $community;
    }

    private function openKolab(): Kolab
    {
        return Kolab::factory()->venuePromotion()->published()->create([
            'creator_profile_id' => $this->business()->id,
            'title' => 'Host your run club at Cafe Mallorca',
            'preferred_city' => 'Barcelona',
            'availability_mode' => 'flexible',
            'availability_start' => null,
            'availability_end' => null,
        ]);
    }

    private function service(): KolabingTeamService
    {
        return app(KolabingTeamService::class);
    }

    private function communityOffer(Profile $community, array $extra = []): KolabingTeamOffer
    {
        return $this->service()->draft([
            'kind' => KolabingTeamOfferKind::CommunityApply->value,
            'recipient' => $community->id,
            'kolab' => $this->openKolab()->id,
            'message' => 'We think your club fits this venue.',
            ...$extra,
        ], $this->maintainer());
    }

    /** An unclaimed community, created the way the admin onboard form creates it. */
    private function unclaimedCommunity(): Profile
    {
        $profile = Profile::factory()->community()->create([
            'email' => 'unclaimed-x@unclaimed.kolabing.invalid',
            'is_active' => false,
            'is_unclaimed' => true,
            'last_active_at' => null,
        ]);
        CommunityProfile::factory()->create([
            'profile_id' => $profile->id,
            'name' => 'Poblenou Padel Crew',
            'city_id' => $this->city()->id,
            'community_type' => 'sports_club',
            'community_size' => 80,
        ]);

        return $profile;
    }

    private function enableUnclaimedSend(): void
    {
        config(['kolabing_team.allow_unclaimed_send' => true]);
    }

    private function googleIdentity(string $email = 'owner@example.com', string $id = 'google-owner-1'): void
    {
        $this->mock(GoogleAuthService::class)
            ->shouldReceive('verifyIdToken')->andReturn([
                'google_id' => $id, 'email' => $email, 'avatar_url' => null, 'email_verified' => true,
            ]);
    }

    // ── 1. Deeplink ──────────────────────────────────────────────────────

    public function test_the_message_links_to_the_offer_card_not_to_the_play_store(): void
    {
        config(['webapp.url' => 'https://app.kolabing.com']);
        $community = $this->community(['last_active_at' => null, 'device_token' => null, 'phone_number' => '+34 600 111 222']);
        $offer = $this->communityOffer($community);
        $this->service()->send($offer, $this->maintainer());

        $text = $this->service()->fallbackText($offer->fresh());

        $this->assertStringContainsString('https://app.kolabing.com/o/'.$offer->id, $text);
        $this->assertStringNotContainsString('play.google.com', $text);
        $this->assertStringContainsString(
            rawurlencode('https://app.kolabing.com/o/'.$offer->id),
            (string) $this->service()->whatsappLink($offer->fresh()),
        );
    }

    public function test_the_offer_link_is_an_app_link_and_the_web_page_points_to_the_right_store(): void
    {
        config([
            'webapp.app_store_url' => 'https://apps.apple.com/app/kolabing/id1',
            'webapp.play_store_url' => 'https://play.google.com/store/apps/details?id=com.kolabing.kolabingApp',
        ]);
        $this->assertContains('/o/????????-????-????-????-????????????', config('webapp.app_links.paths'));
        $this->assertContains('/claim/*', config('webapp.app_links.paths'));

        $host = 'http://'.config('webapp.host');
        $id = '0191a2b3-c4d5-7e6f-8a9b-0c1d2e3f4a5b';

        $this->withHeader('User-Agent', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X)')
            ->get($host.'/o/'.$id)
            ->assertOk()
            ->assertSee('apps.apple.com', false)
            ->assertDontSee('play.google.com', false)
            ->assertSee('content="no-referrer"', false);

        $this->withHeader('User-Agent', 'Mozilla/5.0 (Linux; Android 14)')
            ->get($host.'/claim/sometoken')
            ->assertOk()
            ->assertSee('play.google.com', false)
            ->assertSee('delete the page', false);
    }

    public function test_a_tapped_offer_link_resolves_to_the_thread_for_the_recipient_only(): void
    {
        $community = $this->community();
        $offer = $this->communityOffer($community);
        $this->service()->send($offer, $this->maintainer());
        $threadId = $community->fresh()->teamThread?->id
            ?? \App\Models\ChatThread::query()->where('team_profile_id', $community->id)->value('id');

        $this->actingAs($community)->getJson('/api/v1/kolabing-team/offers/'.$offer->id)
            ->assertOk()
            ->assertJsonPath('data.offer.thread_id', $threadId)
            ->assertJsonPath('data.offer.can_respond', true);

        $this->actingAs($this->community())->getJson('/api/v1/kolabing-team/offers/'.$offer->id)->assertForbidden();
    }

    // ── 2. 7-day expiry and one resend ───────────────────────────────────

    public function test_an_offer_expires_seven_days_after_it_was_sent(): void
    {
        $community = $this->community();
        $offer = $this->communityOffer($community);
        $this->service()->send($offer, $this->maintainer());

        $this->assertEqualsWithDelta(now()->addDays(7)->timestamp, $offer->fresh()->expires_at->timestamp, 5);

        $this->travel(6)->days();
        $this->assertTrue($offer->fresh()->isOpenForResponse());

        $this->travel(2)->days();
        $this->assertTrue($offer->fresh()->isExpired());
    }

    public function test_accept_and_not_now_on_an_expired_offer_are_refused_with_a_clear_message(): void
    {
        $community = $this->community();
        $offer = $this->communityOffer($community);
        $this->service()->send($offer, $this->maintainer());

        $this->travel(8)->days();

        $this->actingAs($community)->postJson('/api/v1/kolabing-team/offers/'.$offer->id.'/accept')
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonFragment(['message' => 'This offer expired on '.$offer->fresh()->expires_at->format('j M Y').'. Ask the Kolabing team to send it again.']);
        $this->actingAs($community)->postJson('/api/v1/kolabing-team/offers/'.$offer->id.'/decline')->assertStatus(422);

        $this->assertSame(0, Application::query()->count());
        $this->assertSame(KolabingTeamOfferStatus::Sent, $offer->fresh()->status);
        $this->actingAs($community)->getJson('/api/v1/kolabing-team/offers/'.$offer->id)
            ->assertJsonPath('data.offer.expired', true)
            ->assertJsonPath('data.offer.can_respond', false);
    }

    public function test_an_expired_offer_can_be_resent_once_and_then_accepted(): void
    {
        $community = $this->community();
        $admin = $this->maintainer();
        $offer = $this->communityOffer($community);
        $this->service()->send($offer, $admin);

        $this->travel(8)->days();
        $this->service()->resend($offer->fresh(), $admin);

        $fresh = $offer->fresh();
        $this->assertSame(1, $fresh->resend_count);
        $this->assertTrue($fresh->isOpenForResponse());
        $this->assertEqualsWithDelta(now()->addDays(7)->timestamp, $fresh->expires_at->timestamp, 5);
        $this->assertSame(1, ChatMessage::query()->where('team_offer_id', $offer->id)->count(), 'the card moves, it does not double up');

        $this->actingAs($community)->postJson('/api/v1/kolabing-team/offers/'.$offer->id.'/accept')->assertOk();
        $this->assertSame(1, Application::query()->count());

        try {
            $this->service()->resend($offer->fresh(), $admin);
            $this->fail('An answered offer cannot be resent.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('sent and not answered', $e->getMessage());
        }
    }

    public function test_a_second_resend_is_refused(): void
    {
        $admin = $this->maintainer();
        $offer = $this->communityOffer($this->community());
        $this->service()->send($offer, $admin);
        $this->service()->resend($offer->fresh(), $admin);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('already resent once');
        $this->service()->resend($offer->fresh(), $admin);
    }

    public function test_a_resend_counts_toward_the_weekly_cap(): void
    {
        $community = $this->community();
        $admin = $this->maintainer();
        $first = $this->communityOffer($community);
        $this->service()->send($first, $admin);
        $this->service()->resend($first->fresh(), $admin); // send 1 + resend = 2 = cap

        $second = $this->communityOffer($community);
        $this->assertStringContainsString('cap', implode(' ', $this->service()->problems($second)));

        $this->expectException(InvalidArgumentException::class);
        $this->service()->send($second, $admin);
    }

    public function test_a_resend_is_refused_when_it_would_pass_the_cap(): void
    {
        $community = $this->community();
        $admin = $this->maintainer();
        $a = $this->communityOffer($community);
        $b = $this->communityOffer($community);
        $this->service()->send($a, $admin);
        $this->service()->send($b, $admin); // 2 sends = cap

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('cap');
        $this->service()->resend($a->fresh(), $admin);
    }

    public function test_admin_can_resend_from_the_panel_once(): void
    {
        $admin = $this->maintainer();
        $offer = $this->communityOffer($this->community());
        $this->service()->send($offer, $admin);

        $this->actingAs($admin, 'admin')->post(route('admin.kolabing-team.resend', $offer))
            ->assertRedirect()->assertSessionHas('status');
        $this->actingAs($admin, 'admin')->post(route('admin.kolabing-team.resend', $offer))
            ->assertSessionHasErrors('send');
        $this->actingAs($admin, 'admin')->get(route('admin.kolabing-team.index'))->assertOk()->assertSee('resent once');
    }

    // ── 3. Unclaimed profile and claim flow ──────────────────────────────

    public function test_sending_to_an_unclaimed_profile_is_off_by_default(): void
    {
        $unclaimed = $this->unclaimedCommunity();
        $offer = $this->communityOffer($unclaimed, ['contact_email' => 'owner@example.com']);

        $this->assertStringContainsString('unclaimed', strtolower(implode(' ', $this->service()->problems($offer))));

        try {
            $this->service()->send($offer, $this->maintainer());
            $this->fail('The switch is off.');
        } catch (InvalidArgumentException) {
        }

        $this->assertSame(KolabingTeamOfferStatus::Draft, $offer->fresh()->status);
        $this->assertNull($offer->fresh()->claim_token_hash);
        Queue::assertNothingPushed();
    }

    public function test_with_the_switch_on_one_message_carries_the_claim_link_and_the_delete_line(): void
    {
        $this->enableUnclaimedSend();
        config(['webapp.url' => 'https://app.kolabing.com']);
        $unclaimed = $this->unclaimedCommunity();
        $offer = $this->communityOffer($unclaimed, ['contact_email' => 'owner@example.com']);

        $this->assertSame([], $this->service()->problems($offer));
        $this->service()->send($offer, $this->maintainer());

        $offer = $offer->fresh();
        $token = $this->service()->claimTokenFor($offer);
        $this->assertNotNull($token);
        $this->assertSame(hash('sha256', $token), $offer->claim_token_hash);
        $this->assertSame('claim', $offer->channel);

        $text = $this->service()->fallbackText($offer);
        $this->assertStringContainsString('https://app.kolabing.com/claim/'.$token, $text);
        $this->assertStringContainsString('You can delete this page whenever you like after you claim it.', $text);

        $this->assertSame(0, ChatMessage::query()->count(), 'nobody owns a thread yet');
        $this->assertFalse($unclaimed->fresh()->is_active);
    }

    public function test_the_claim_preview_shows_the_delete_line_and_names_nothing_for_a_bad_token(): void
    {
        $this->enableUnclaimedSend();
        $offer = $this->communityOffer($this->unclaimedCommunity());
        $this->service()->send($offer, $this->maintainer());
        $token = $this->service()->claimTokenFor($offer->fresh());

        $this->getJson('/api/v1/kolabing-team/claim/'.$token)
            ->assertOk()
            ->assertJsonPath('data.profile_name', 'Poblenou Padel Crew')
            ->assertJsonPath('data.disclaimer', 'You can delete this page whenever you like after you claim it.')
            ->assertJsonPath('data.claimable', true);

        $this->getJson('/api/v1/kolabing-team/claim/not-a-token')->assertNotFound();
    }

    public function test_claiming_makes_the_owner_the_profile_holder_and_accepts_the_offer(): void
    {
        $this->enableUnclaimedSend();
        $unclaimed = $this->unclaimedCommunity();
        $offer = $this->communityOffer($unclaimed);
        $this->service()->send($offer, $this->maintainer());
        $token = $this->service()->claimTokenFor($offer->fresh());
        $this->googleIdentity();

        $response = $this->postJson('/api/v1/kolabing-team/claim/'.$token, ['provider' => 'google', 'id_token' => 'x'])
            ->assertOk()
            ->assertJsonPath('data.offer.status', 'accepted')
            ->assertJsonStructure(['data' => ['token', 'refresh_token', 'user']]);

        $profile = $unclaimed->fresh();
        $this->assertTrue($profile->is_active);
        $this->assertFalse($profile->is_unclaimed);
        $this->assertNotNull($profile->claimed_at);
        $this->assertSame('google-owner-1', $profile->google_id);
        $this->assertSame('owner@example.com', $profile->email);
        $this->assertSame($profile->id, $response->json('data.user.id'));
        $this->assertSame(1, Application::query()->where('applicant_profile_id', $profile->id)->count());
        $this->assertNull($offer->fresh()->claim_token_hash, 'the link works once');

        // The new session is the owner's.
        $this->withToken($response->json('data.token'))->getJson('/api/v1/auth/me')->assertOk();

        $this->postJson('/api/v1/kolabing-team/claim/'.$token, ['provider' => 'google', 'id_token' => 'x'])->assertNotFound();
    }

    public function test_claiming_an_unclaimed_business_creates_the_kolab(): void
    {
        $this->enableUnclaimedSend();
        $community = $this->community();
        $unclaimed = Profile::factory()->business()->create([
            'email' => 'unclaimed-b@unclaimed.kolabing.invalid', 'is_active' => false, 'is_unclaimed' => true,
        ]);
        BusinessProfile::factory()->create([
            'profile_id' => $unclaimed->id, 'name' => 'Bar Poblenou', 'city_id' => $this->city()->id, 'city_name' => 'Barcelona',
        ]);

        $offer = $this->service()->draft([
            'kind' => KolabingTeamOfferKind::BusinessPropose->value,
            'recipient' => $unclaimed->id,
            'community' => $community->id,
            'title' => 'A run-club night at Bar Poblenou',
            'message' => 'A local run club would like to host a night with you.',
        ], $this->maintainer());
        $this->service()->send($offer, $this->maintainer());
        $token = $this->service()->claimTokenFor($offer->fresh());
        $this->googleIdentity('biz@example.com', 'google-biz-1');

        $this->postJson('/api/v1/kolabing-team/claim/'.$token, ['provider' => 'google', 'id_token' => 'x'])
            ->assertOk()
            ->assertJsonPath('data.offer.status', 'accepted');

        $kolab = Kolab::query()->where('recipient_community_id', $community->id)->where('creator_profile_id', $unclaimed->id)->first();
        $this->assertNotNull($kolab);
        $this->assertSame($kolab->id, $offer->fresh()->result_kolab_id);
        $this->assertTrue($unclaimed->fresh()->is_active);
    }

    public function test_a_claim_with_an_identity_that_already_has_a_profile_is_refused_and_changes_nothing(): void
    {
        $this->enableUnclaimedSend();
        Profile::factory()->community()->create(['email' => 'taken@example.com', 'google_id' => 'google-taken']);
        $unclaimed = $this->unclaimedCommunity();
        $offer = $this->communityOffer($unclaimed);
        $this->service()->send($offer, $this->maintainer());
        $token = $this->service()->claimTokenFor($offer->fresh());
        $this->googleIdentity('taken@example.com', 'google-taken');

        $this->postJson('/api/v1/kolabing-team/claim/'.$token, ['provider' => 'google', 'id_token' => 'x'])->assertStatus(409);

        $this->assertTrue($unclaimed->fresh()->is_unclaimed);
        $this->assertFalse($unclaimed->fresh()->is_active);
        $this->assertSame(KolabingTeamOfferStatus::Sent, $offer->fresh()->status);
        $this->assertNotNull($offer->fresh()->claim_token_hash);
    }

    public function test_a_claim_after_the_seven_days_is_refused_and_a_resend_reopens_it(): void
    {
        $this->enableUnclaimedSend();
        $unclaimed = $this->unclaimedCommunity();
        $admin = $this->maintainer();
        $offer = $this->communityOffer($unclaimed);
        $this->service()->send($offer, $admin);
        $token = $this->service()->claimTokenFor($offer->fresh());
        $this->googleIdentity();

        $this->travel(8)->days();
        $this->getJson('/api/v1/kolabing-team/claim/'.$token)->assertOk()->assertJsonPath('data.claimable', false)->assertJsonPath('data.expired', true);
        $this->postJson('/api/v1/kolabing-team/claim/'.$token, ['provider' => 'google', 'id_token' => 'x'])->assertStatus(410);
        $this->assertTrue($unclaimed->fresh()->is_unclaimed);

        $this->service()->resend($offer->fresh(), $admin);
        $this->postJson('/api/v1/kolabing-team/claim/'.$token, ['provider' => 'google', 'id_token' => 'x'])->assertOk();
    }

    public function test_an_invalid_sign_in_token_claims_nothing(): void
    {
        $this->enableUnclaimedSend();
        $unclaimed = $this->unclaimedCommunity();
        $offer = $this->communityOffer($unclaimed);
        $this->service()->send($offer, $this->maintainer());
        $token = $this->service()->claimTokenFor($offer->fresh());
        $this->mock(GoogleAuthService::class)->shouldReceive('verifyIdToken')->andReturn(null);

        $this->postJson('/api/v1/kolabing-team/claim/'.$token, ['provider' => 'google', 'id_token' => 'bad'])->assertStatus(400);

        $this->assertTrue($unclaimed->fresh()->is_unclaimed);
    }

    public function test_an_unclaimed_profile_cannot_sign_in_or_be_switched_on_by_hand(): void
    {
        $unclaimed = $this->unclaimedCommunity();
        $unclaimed->forceFill(['google_id' => 'google-pre'])->save();

        $this->mock(GoogleAuthService::class)->shouldReceive('verifyIdToken')->andReturn([
            'google_id' => 'google-pre', 'email' => 'unclaimed-x@unclaimed.kolabing.invalid', 'avatar_url' => null, 'email_verified' => true,
        ]);
        $this->postJson('/api/v1/auth/google', ['id_token' => 'x', 'user_type' => 'community'])->assertStatus(403);

        $this->expectException(InvalidArgumentException::class);
        app(ManagedProfileService::class)->activate($unclaimed);
    }

    public function test_an_unclaimed_profile_is_invisible_in_the_app_until_it_is_claimed(): void
    {
        $viewer = $this->business();
        $biz = Profile::factory()->business()->create(['email' => 'ub@unclaimed.kolabing.invalid', 'is_active' => false, 'is_unclaimed' => true]);
        BusinessProfile::factory()->create(['profile_id' => $biz->id, 'name' => 'ZBIZMARK', 'city_id' => $this->city()->id]);
        $kolab = Kolab::factory()->create(['creator_profile_id' => $biz->id, 'title' => 'ZKOLABMARK']);

        $com = $this->unclaimedCommunity();
        CommunityProfile::withoutGlobalScopes()->where('profile_id', $com->id)->update(['name' => 'ZCOMMARK']);
        $community = Community::factory()->create(['owner_profile_id' => $com->id, 'name' => 'ZCOMMUNITYMARK', 'join_policy' => 'open']);

        $markers = ['ZBIZMARK', 'ZKOLABMARK', 'ZCOMMARK', 'ZCOMMUNITYMARK'];
        $paths = [
            'kolabs', 'opportunities', 'discovery/opportunities', 'discovery/communities', 'communities/discover',
            'events', 'events/discover', 'suggestions', 'leaderboard/global', 'profiles/lookup?q=Z',
            'profiles/'.$biz->id, 'profiles/'.$com->id, 'communities/'.$community->id, 'profiles/'.$biz->id.'/public-profile',
        ];

        $leaks = [];
        foreach ($paths as $path) {
            $body = (string) $this->actingAs($viewer, 'sanctum')->getJson('/api/v1/'.$path)->getContent();
            foreach ($markers as $marker) {
                if (str_contains($body, $marker)) {
                    $leaks[] = "{$path}: {$marker}";
                }
            }
        }

        $this->assertSame([], $leaks, 'An unclaimed profile leaked: '.implode('; ', $leaks));
        $this->assertSame(0, \App\Models\BusinessProfile::query()->where('profile_id', $biz->id)->count(), 'default scope hides it from stats');
    }

    public function test_the_admin_onboard_form_can_create_an_unclaimed_profile_without_an_email(): void
    {
        $city = $this->city();
        \App\Models\CommunityType::query()->firstOrCreate(['slug' => 'social_club'], ['name' => 'Social club', 'is_active' => true]);

        $this->actingAs($this->maintainer(), 'admin')
            ->post(route('admin.users.onboard.community'), [
                'unclaimed' => '1',
                'name' => 'Raval Book Club',
                'about' => 'A book club that meets in cafes.',
                'community_type' => 'social_club',
                'community_size' => 40,
                'city_id' => $city->id,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.kolabing-team.index'));

        $profile = Profile::query()->where('is_unclaimed', true)->sole();
        $this->assertFalse($profile->is_active);
        $this->assertSame(UserType::Community, $profile->user_type);
        $this->assertStringEndsWith('@unclaimed.kolabing.invalid', $profile->email);
        $this->assertSame('Raval Book Club', $profile->communityProfile()->withoutGlobalScopes()->first()->name);
        Queue::assertNotPushed(SendTransactionalEmail::class);
    }
}
