<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\FileUploadType;
use App\Exceptions\InstagramException;
use App\Http\Controllers\Api\V1\GalleryController;
use App\Models\InstagramAccount;
use App\Models\InstagramDataDeletionRequest;
use App\Models\Kolab;
use App\Models\Profile;
use App\Models\ProfileGalleryPhoto;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Instagram Connect (BE-NF-75): Instagram API with Instagram Login, permission
 * `instagram_business_basic`, Business and Creator accounts only.
 *
 * Flow (Meta docs, "Business Login for Instagram"):
 *   authorize URL → `code` (valid 1h, one use, trailing `#_` stripped)
 *   → POST api.instagram.com/oauth/access_token (short-lived, 1h)
 *   → GET graph.instagram.com/access_token grant_type=ig_exchange_token (60 days)
 *   → GET graph.instagram.com/refresh_access_token grant_type=ig_refresh_token
 *     (token ≥ 24h old and unexpired; the nightly job refreshes from day 50).
 *
 * Only the account owner's own media is read, and we copy only the items the
 * owner picks (the import action is their consent). Instagram CDN URLs expire,
 * so nothing is ever hotlinked: every imported file is stored in our storage via
 * FileUploadService, in the same `gallery/{profile}` path and limits as uploads.
 */
class InstagramService
{
    public const RETURN_APP = 'app';

    public const RETURN_WEB = 'web';

    /** `account_type` values /me returns for a professional account. */
    private const PROFESSIONAL_TYPES = ['BUSINESS', 'MEDIA_CREATOR', 'CREATOR'];

    private const MEDIA_FIELDS = 'id,media_type,media_url,thumbnail_url,permalink,caption,timestamp';

    private const ME_FIELDS = 'id,user_id,username,name,account_type,profile_picture_url';

    public function __construct(
        private readonly FileUploadService $files,
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Availability
    |--------------------------------------------------------------------------
    */

    public function isConfigured(): bool
    {
        return filled(config('services.instagram.app_id')) && filled(config('services.instagram.app_secret'));
    }

    /**
     * Whether this profile may use Instagram Connect right now: the app is
     * configured, the profile is a business or community, and the feature is on
     * for everyone or this profile is a listed tester (pre App Review).
     */
    public function isAvailableFor(Profile $profile): bool
    {
        if (! $this->isConfigured() || ! ($profile->isBusiness() || $profile->isCommunity())) {
            return false;
        }

        if ((bool) config('services.instagram.enabled')) {
            return true;
        }

        return in_array($profile->id, (array) config('services.instagram.tester_profile_ids', []), true);
    }

    public function ensureAvailable(Profile $profile): void
    {
        if (! $this->isAvailableFor($profile)) {
            throw new InstagramException(InstagramException::UNAVAILABLE);
        }
    }

    /**
     * The profile's live connection, or 409 NOT_CONNECTED.
     */
    public function connectedAccount(Profile $profile): InstagramAccount
    {
        $account = $profile->instagramAccount()->first();

        if ($account === null || ! $account->isConnected()) {
            throw new InstagramException(InstagramException::NOT_CONNECTED);
        }

        return $account;
    }

    /**
     * GET /api/v1/me/instagram payload.
     *
     * @return array<string, mixed>
     */
    public function status(Profile $profile): array
    {
        $account = $profile->instagramAccount()->first();
        $connected = $account !== null && $account->isConnected();

        return [
            'connected' => $connected,
            'username' => $connected ? $account->username : null,
            'account_type' => $connected ? $account->account_type : null,
            'profile_picture_url' => $connected ? $account->profile_picture_url : null,
            'connected_at' => $connected ? $account->connected_at?->toIso8601String() : null,
            'last_synced_at' => $connected ? $account->last_synced_at?->toIso8601String() : null,
            'enabled' => $this->isAvailableFor($profile),
            // True when the profile has no photo and Instagram has one: the app
            // offers it (POST /me/instagram/avatar). Never overwrites a photo.
            'avatar_suggested' => $connected && $this->canUseAsAvatar($profile, $account),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | OAuth
    |--------------------------------------------------------------------------
    */

    /**
     * The Instagram authorize URL. `state` is encrypted + MAC'd with APP_KEY,
     * bound to the profile, expires after `state_ttl_minutes`, and its nonce is
     * single-use (pulled from the cache by the callback).
     */
    public function authorizeUrl(Profile $profile, string $return = self::RETURN_APP): string
    {
        $nonce = Str::random(32);
        $ttl = max(1, (int) config('services.instagram.state_ttl_minutes', 15));

        Cache::put($this->stateCacheKey($nonce), $profile->id, now()->addMinutes($ttl));

        $state = Crypt::encryptString((string) json_encode([
            'p' => $profile->id,
            'r' => $return === self::RETURN_WEB ? self::RETURN_WEB : self::RETURN_APP,
            'n' => $nonce,
            'exp' => now()->addMinutes($ttl)->getTimestamp(),
        ]));

        return config('services.instagram.authorize_url').'?'.http_build_query([
            'client_id' => config('services.instagram.app_id'),
            'redirect_uri' => config('services.instagram.redirect_uri'),
            'response_type' => 'code',
            'scope' => config('services.instagram.scope', 'instagram_business_basic'),
            'state' => $state,
            // Instagram-only login screen; Kolabing does not use Facebook Login here.
            'enable_fb_login' => 'false',
        ]);
    }

    /**
     * Decode a callback `state`. Returns the return target even when the state
     * is expired or replayed (so the error lands in the right place), with
     * `valid` false. Never trusts anything that fails decryption.
     *
     * @return array{valid: bool, profile_id: string|null, return: string}
     */
    public function parseState(?string $state): array
    {
        $invalid = ['valid' => false, 'profile_id' => null, 'return' => self::RETURN_APP];

        if (! is_string($state) || $state === '') {
            return $invalid;
        }

        try {
            $payload = json_decode(Crypt::decryptString($state), true, 4, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return $invalid;
        }

        if (! is_array($payload) || ! is_string($payload['p'] ?? null) || ! is_string($payload['n'] ?? null)) {
            return $invalid;
        }

        $return = ($payload['r'] ?? null) === self::RETURN_WEB ? self::RETURN_WEB : self::RETURN_APP;
        $fresh = (int) ($payload['exp'] ?? 0) >= now()->getTimestamp();
        // Single use: the nonce is consumed whether or not the rest succeeds.
        $owner = Cache::pull($this->stateCacheKey($payload['n']));

        return [
            'valid' => $fresh && $owner === $payload['p'],
            'profile_id' => $payload['p'],
            'return' => $return,
        ];
    }

    /**
     * Where the callback sends the user back to.
     */
    public function returnUrl(string $return, string $status, ?string $reason = null): string
    {
        $query = array_filter(['status' => $status, 'reason' => $reason]);

        if ($return === self::RETURN_WEB) {
            $base = (string) config('services.instagram.web_return_url');

            return $base.(str_contains($base, '?') ? '&' : '?').http_build_query(['instagram' => $status] + array_filter(['reason' => $reason]));
        }

        return config('services.instagram.app_return_url').'?'.http_build_query($query);
    }

    /**
     * Swap the authorization code for a long-lived token, read the account and
     * store the connection. Throws InstagramException on every failure.
     */
    public function connect(Profile $profile, string $code): InstagramAccount
    {
        // Meta appends `#_` to the code in the redirect; it is not part of it.
        $code = preg_replace('/#_$/', '', trim($code)) ?? '';

        $short = $this->send(fn () => Http::asForm()
            ->timeout($this->timeout())
            ->post((string) config('services.instagram.token_url'), [
                'client_id' => config('services.instagram.app_id'),
                'client_secret' => config('services.instagram.app_secret'),
                'grant_type' => 'authorization_code',
                'redirect_uri' => config('services.instagram.redirect_uri'),
                'code' => $code,
            ]));

        // Documented as {"data":[{…}]}; the live endpoint answers flat. Accept both.
        $body = $short->json();
        $grant = is_array($body['data'][0] ?? null) ? $body['data'][0] : (is_array($body) ? $body : []);
        $shortToken = $grant['access_token'] ?? null;

        if (! is_string($shortToken) || $shortToken === '') {
            Log::warning('Instagram code exchange returned no token', ['profile_id' => $profile->id]);

            throw new InstagramException(InstagramException::API_ERROR);
        }

        if (array_key_exists('permissions', $grant) && ! in_array('instagram_business_basic', $this->permissionList($grant['permissions']), true)) {
            throw new InstagramException(InstagramException::PERMISSION_DENIED);
        }

        $long = $this->send(fn () => Http::timeout($this->timeout())
            ->get($this->graphBase().'/access_token', [
                'grant_type' => 'ig_exchange_token',
                'client_secret' => config('services.instagram.app_secret'),
                'access_token' => $shortToken,
            ]))->json();

        $token = is_string($long['access_token'] ?? null) ? $long['access_token'] : null;

        if ($token === null) {
            throw new InstagramException(InstagramException::API_ERROR);
        }

        $me = $this->fetchMe($token);

        $account = InstagramAccount::query()->updateOrCreate(
            ['profile_id' => $profile->id],
            [
                'ig_user_id' => (string) ($me['user_id'] ?? $me['id'] ?? $grant['user_id'] ?? ''),
                'ig_app_scoped_id' => isset($me['id']) ? (string) $me['id'] : null,
                'username' => (string) $me['username'],
                'name' => $me['name'] ?? null,
                'account_type' => $me['account_type'] ?? null,
                'profile_picture_url' => $me['profile_picture_url'] ?? null,
                'access_token' => $token,
                'token_expires_at' => now()->addSeconds((int) ($long['expires_in'] ?? 60 * 24 * 3600)),
                'token_refreshed_at' => now(),
                'connected_at' => now(),
                'disconnected_at' => null,
                'last_synced_at' => now(),
            ]
        );

        Log::info('Instagram connected', ['profile_id' => $profile->id, 'username' => $account->username]);

        return $account;
    }

    /**
     * Disconnect: forget the token, keep already-imported media. The row stays
     * (token NULL) so a later Meta data-deletion request can still find it.
     */
    public function disconnect(InstagramAccount $account): void
    {
        $account->forceFill([
            'access_token' => null,
            'token_expires_at' => null,
            'disconnected_at' => now(),
        ])->save();

        Cache::forget($this->mediaCacheKey($account));
    }

    /*
    |--------------------------------------------------------------------------
    | Profile + media
    |--------------------------------------------------------------------------
    */

    /**
     * Refresh username / type / picture and warm the first media page
     * (POST /me/instagram/sync, run by SyncInstagramAccount on the queue).
     */
    public function sync(InstagramAccount $account): void
    {
        $me = $this->withToken($account, fn (string $token) => $this->fetchMe($token));

        $account->forceFill([
            'username' => (string) $me['username'],
            'name' => $me['name'] ?? $account->name,
            'account_type' => $me['account_type'] ?? $account->account_type,
            'profile_picture_url' => $me['profile_picture_url'] ?? null,
            'last_synced_at' => now(),
        ])->save();

        Cache::put($this->mediaCacheKey($account), $this->fetchMediaPage($account, null), now()->addMinutes(10));
    }

    /**
     * One page of the account's media, newest first, with `imported` set for
     * items this profile already has in its gallery.
     *
     * @return array{items: list<array<string, mixed>>, next_cursor: string|null}
     */
    public function listMedia(Profile $profile, InstagramAccount $account, ?string $cursor = null): array
    {
        $page = $cursor === null ? Cache::get($this->mediaCacheKey($account)) : null;

        if (! is_array($page)) {
            $page = $this->fetchMediaPage($account, $cursor);
        }

        $ids = array_column($page['items'], 'id');
        $imported = $ids === [] ? [] : ProfileGalleryPhoto::query()
            ->where('profile_id', $profile->id)
            ->whereIn('instagram_source_id', $ids)
            ->pluck('instagram_source_id')
            ->all();

        return [
            'items' => array_map(fn (array $item): array => $item + [
                'imported' => in_array($item['id'], $imported, true),
            ], $page['items']),
            'next_cursor' => $page['next_cursor'],
        ];
    }

    /**
     * Copy the selected media into our storage. Carousels expand into their
     * children. Items that cannot be imported are reported in `skipped` with a
     * reason instead of failing the whole request; an expired token aborts.
     *
     * @param  list<string>  $ids
     * @return array{items: list<mixed>, skipped: list<array{id: string, reason: string}>}
     */
    public function import(Profile $profile, InstagramAccount $account, array $ids, string $target, ?Kolab $kolab = null): array
    {
        $isGallery = $target === 'gallery';
        $capacity = $isGallery
            ? GalleryController::MAX_GALLERY_PHOTOS - $profile->galleryPhotos()->count()
            : (int) config('services.instagram.kolab_max_media', 10) - count((array) ($kolab?->media ?? []));

        if ($capacity <= 0) {
            throw new InstagramException(InstagramException::GALLERY_FULL, $isGallery
                ? __('You can upload a maximum of :max gallery photos.', ['max' => GalleryController::MAX_GALLERY_PHOTOS])
                : __('This Kolab already has the maximum number of photos and videos.'));
        }

        $alreadyImported = $isGallery ? ProfileGalleryPhoto::query()
            ->where('profile_id', $profile->id)
            ->whereIn('instagram_source_id', $ids)
            ->pluck('instagram_source_id')
            ->all() : [];

        $created = [];
        $skipped = [];
        $sortOrder = $isGallery
            ? (int) $profile->galleryPhotos()->max('sort_order') + 1
            : count((array) ($kolab?->media ?? []));
        $kolabMedia = array_values((array) ($kolab?->media ?? []));

        foreach (array_values(array_unique($ids)) as $id) {
            if (in_array($id, $alreadyImported, true)) {
                $skipped[] = ['id' => $id, 'reason' => 'already_imported'];

                continue;
            }

            $media = $this->fetchOwnMedia($account, $id);

            if (is_string($media)) {
                $skipped[] = ['id' => $id, 'reason' => $media];

                continue;
            }

            $leaves = ($media['media_type'] ?? null) === 'CAROUSEL_ALBUM'
                ? array_values((array) ($media['children']['data'] ?? []))
                : [$media];

            foreach ($leaves as $leaf) {
                $leafId = (string) ($leaf['id'] ?? $id);

                if ($capacity <= 0) {
                    $skipped[] = ['id' => $leafId, 'reason' => 'limit_reached'];

                    continue;
                }

                $stored = $this->storeLeaf($profile, $leaf, $isGallery);

                if (is_string($stored)) {
                    $skipped[] = ['id' => $leafId, 'reason' => $stored];

                    continue;
                }

                $capacity--;

                if ($isGallery) {
                    $created[] = ProfileGalleryPhoto::query()->create([
                        'profile_id' => $profile->id,
                        'url' => $stored['image_url'],
                        'media_type' => $stored['type'],
                        'video_url' => $stored['video_url'],
                        'caption' => $this->caption($media['caption'] ?? null),
                        'sort_order' => $sortOrder++,
                        'instagram_media_id' => $leafId,
                        'instagram_source_id' => $id,
                    ]);
                } else {
                    $item = [
                        'url' => $stored['type'] === 'video' ? $stored['video_url'] : $stored['image_url'],
                        'type' => $stored['type'],
                        'thumbnail_url' => $stored['type'] === 'video' ? $stored['image_url'] : null,
                        'sort_order' => $sortOrder++,
                        'source' => 'instagram',
                        'instagram_media_id' => $leafId,
                    ];
                    $kolabMedia[] = $item;
                    $created[] = $item;
                }
            }
        }

        if (! $isGallery && $kolab !== null && $created !== []) {
            $kolab->forceFill(['media' => $kolabMedia])->save();
        }

        return ['items' => $created, 'skipped' => $skipped];
    }

    /*
    |--------------------------------------------------------------------------
    | Avatar offer
    |--------------------------------------------------------------------------
    */

    public function canUseAsAvatar(Profile $profile, InstagramAccount $account): bool
    {
        return filled($account->profile_picture_url) && ! $this->hasPhoto($profile);
    }

    /**
     * Use the Instagram profile picture as the profile photo, only when the
     * profile has none. Never overwrites an avatar or an uploaded logo.
     */
    public function applyAvatar(Profile $profile, InstagramAccount $account): string
    {
        if ($this->hasPhoto($profile)) {
            throw new InstagramException(InstagramException::AVATAR_ALREADY_SET);
        }

        if (blank($account->profile_picture_url)) {
            throw new InstagramException(InstagramException::API_ERROR);
        }

        $bytes = $this->download((string) $account->profile_picture_url, FileUploadType::ProfilePhoto->getMaxFileSize());

        if ($bytes === null) {
            throw new InstagramException(InstagramException::API_ERROR);
        }

        try {
            $url = $this->files->uploadFromContents($bytes, FileUploadType::ProfilePhoto, $profile->id);
        } catch (RuntimeException) {
            throw new InstagramException(InstagramException::API_ERROR);
        }

        $extended = $profile->getExtendedProfile();

        if ($extended !== null && blank($extended->profile_photo)) {
            // The model's saved hook mirrors profile_photo onto avatar_url.
            $extended->forceFill(['profile_photo' => $url])->save();
        }

        $profile->forceFill(['avatar_url' => $url])->save();

        return $url;
    }

    /*
    |--------------------------------------------------------------------------
    | Token lifecycle
    |--------------------------------------------------------------------------
    */

    /**
     * Refresh a long-lived token for another 60 days. An expired or revoked
     * token disconnects the account. Returns true when refreshed.
     */
    public function refreshToken(InstagramAccount $account): bool
    {
        if ($account->access_token === null) {
            return false;
        }

        try {
            $response = $this->send(fn () => Http::timeout($this->timeout())
                ->get($this->graphBase().'/refresh_access_token', [
                    'grant_type' => 'ig_refresh_token',
                    'access_token' => $account->access_token,
                ]));
        } catch (InstagramException $e) {
            if ($e->errorCode === InstagramException::TOKEN_EXPIRED || $e->errorCode === InstagramException::PERMISSION_DENIED) {
                $this->disconnect($account);
            }

            return false;
        }

        $token = $response->json('access_token');

        if (! is_string($token) || $token === '') {
            return false;
        }

        $account->forceFill([
            'access_token' => $token,
            'token_expires_at' => now()->addSeconds((int) ($response->json('expires_in') ?? 60 * 24 * 3600)),
            'token_refreshed_at' => now(),
        ])->save();

        return true;
    }

    /*
    |--------------------------------------------------------------------------
    | Meta callbacks (deauthorize + data deletion)
    |--------------------------------------------------------------------------
    */

    /**
     * Verify a Meta `signed_request` ("<base64url sig>.<base64url payload>",
     * HMAC-SHA256 of the payload with the app secret). Returns the payload, or
     * null when the signature, algorithm or user id is wrong.
     *
     * @return array<string, mixed>|null
     */
    public function parseSignedRequest(?string $signedRequest): ?array
    {
        $secret = (string) config('services.instagram.app_secret');

        if (! is_string($signedRequest) || $secret === '' || substr_count($signedRequest, '.') !== 1) {
            return null;
        }

        [$encodedSig, $encodedPayload] = explode('.', $signedRequest, 2);

        $sig = $this->base64UrlDecode($encodedSig);
        $expected = hash_hmac('sha256', $encodedPayload, $secret, true);

        if ($sig === null || ! hash_equals($expected, $sig)) {
            return null;
        }

        $json = $this->base64UrlDecode($encodedPayload);
        $payload = $json === null ? null : json_decode($json, true);

        if (! is_array($payload)
            || strtoupper((string) ($payload['algorithm'] ?? '')) !== 'HMAC-SHA256'
            || blank($payload['user_id'] ?? null)) {
            return null;
        }

        return $payload;
    }

    /**
     * The user removed Kolabing from their Instagram apps: forget the token.
     * Imported media stays (the same as a disconnect in the app).
     */
    public function deauthorize(string $igUserId): int
    {
        $accounts = $this->accountsFor($igUserId)->get();

        foreach ($accounts as $account) {
            $this->disconnect($account);
        }

        return $accounts->count();
    }

    /**
     * The user asked Meta to delete the data we received: delete the connection
     * AND every item imported from Instagram (gallery + Kolab media), files too.
     */
    public function deleteData(string $igUserId): InstagramDataDeletionRequest
    {
        $accounts = $this->accountsFor($igUserId)->get();
        $mediaDeleted = 0;

        foreach ($accounts as $account) {
            $mediaDeleted += $this->deleteImportedMedia($account->profile_id);
            Cache::forget($this->mediaCacheKey($account));
            $account->delete();
        }

        return InstagramDataDeletionRequest::query()->create([
            'confirmation_code' => strtoupper(Str::random(20)),
            'ig_user_id' => $igUserId,
            'status' => InstagramDataDeletionRequest::STATUS_COMPLETED,
            'accounts_deleted' => $accounts->count(),
            'media_deleted' => $mediaDeleted,
            'completed_at' => now(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Internals
    |--------------------------------------------------------------------------
    */

    /**
     * @return \Illuminate\Database\Eloquent\Builder<InstagramAccount>
     */
    private function accountsFor(string $igUserId)
    {
        return InstagramAccount::query()->where(function ($q) use ($igUserId): void {
            $q->where('ig_user_id', $igUserId)->orWhere('ig_app_scoped_id', $igUserId);
        });
    }

    private function deleteImportedMedia(string $profileId): int
    {
        $deleted = 0;

        ProfileGalleryPhoto::query()
            ->where('profile_id', $profileId)
            ->whereNotNull('instagram_source_id')
            ->get()
            ->each(function (ProfileGalleryPhoto $photo) use (&$deleted): void {
                $this->clearProfilePhotoIfItIs($photo->profile_id, $photo->url);
                $this->files->delete($photo->url);
                if ($photo->video_url !== null) {
                    $this->files->delete($photo->video_url);
                }
                $photo->delete();
                $deleted++;
            });

        Kolab::query()->where('creator_profile_id', $profileId)->get()->each(function (Kolab $kolab) use (&$deleted): void {
            $media = array_values((array) ($kolab->media ?? []));
            $kept = array_values(array_filter($media, fn ($m): bool => ! (is_array($m) && ($m['source'] ?? null) === 'instagram')));

            if (count($kept) === count($media)) {
                return;
            }

            foreach ($media as $m) {
                if (is_array($m) && ($m['source'] ?? null) === 'instagram') {
                    foreach (['url', 'thumbnail_url'] as $key) {
                        if (is_string($m[$key] ?? null)) {
                            $this->files->delete($m[$key]);
                        }
                    }
                    $deleted++;
                }
            }

            DB::transaction(fn () => $kolab->forceFill(['media' => $kept])->save());
        });

        return $deleted;
    }

    /**
     * A Meta data-deletion request removes the file, so a profile photo that
     * points at it must go too, or the avatar is left a broken link.
     */
    private function clearProfilePhotoIfItIs(string $profileId, string $url): void
    {
        $profile = Profile::query()->find($profileId);

        if ($profile === null || ! $profile->isProfilePhoto($url)) {
            return;
        }

        $extended = $profile->isAttendee() ? null : $profile->getExtendedProfile();

        if ($extended !== null && $extended->profile_photo === $url) {
            $extended->forceFill(['profile_photo' => null])->save();
        }

        $profile->forceFill(['avatar_url' => null])->save();
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchMe(string $token): array
    {
        $me = $this->send(fn () => Http::timeout($this->timeout())
            ->get($this->graphBase(true).'/me', [
                'fields' => self::ME_FIELDS,
                'access_token' => $token,
            ]))->json();

        if (! is_array($me) || blank($me['username'] ?? null)) {
            throw new InstagramException(InstagramException::API_ERROR);
        }

        $type = strtoupper((string) ($me['account_type'] ?? ''));

        if ($type !== '' && ! in_array($type, self::PROFESSIONAL_TYPES, true)) {
            throw new InstagramException(InstagramException::PERSONAL_ACCOUNT);
        }

        $me['account_type'] = $type !== '' ? $type : null;

        return $me;
    }

    /**
     * @return array{items: list<array<string, mixed>>, next_cursor: string|null}
     */
    private function fetchMediaPage(InstagramAccount $account, ?string $cursor): array
    {
        $query = [
            'fields' => self::MEDIA_FIELDS,
            'limit' => (int) config('services.instagram.media_page_size', 24),
        ];

        if ($cursor !== null && $cursor !== '') {
            $query['after'] = $cursor;
        }

        $body = $this->withToken($account, fn (string $token) => $this->send(fn () => Http::timeout($this->timeout())
            ->get($this->graphBase(true).'/me/media', $query + ['access_token' => $token]))->json());

        $items = [];

        foreach ((array) ($body['data'] ?? []) as $raw) {
            if (! is_array($raw) || blank($raw['id'] ?? null)) {
                continue;
            }

            $items[] = [
                'id' => (string) $raw['id'],
                'media_type' => (string) ($raw['media_type'] ?? 'IMAGE'),
                // Omitted by Instagram for media with copyrighted content (e.g.
                // licensed music): such an item is listed but cannot be imported.
                'media_url' => $raw['media_url'] ?? null,
                'thumbnail_url' => $raw['thumbnail_url'] ?? null,
                'permalink' => $raw['permalink'] ?? null,
                'caption' => $raw['caption'] ?? null,
                'timestamp' => $raw['timestamp'] ?? null,
            ];
        }

        $next = filled($body['paging']['next'] ?? null) ? ($body['paging']['cursors']['after'] ?? null) : null;

        return ['items' => $items, 'next_cursor' => is_string($next) && $next !== '' ? $next : null];
    }

    /**
     * One media item of the connected account, or a skip reason. Instagram only
     * answers a user token for its own media; the username check is belt and braces.
     *
     * @return array<string, mixed>|string
     */
    private function fetchOwnMedia(InstagramAccount $account, string $id): array|string
    {
        if (! preg_match('/^\d{1,30}$/', $id)) {
            return 'not_found';
        }

        try {
            $media = $this->withToken($account, fn (string $token) => $this->send(fn () => Http::timeout($this->timeout())
                ->get($this->graphBase(true).'/'.$id, [
                    'fields' => self::MEDIA_FIELDS.',username,children{id,media_type,media_url,thumbnail_url}',
                    'access_token' => $token,
                ]))->json());
        } catch (InstagramException $e) {
            if ($e->errorCode === InstagramException::TOKEN_EXPIRED) {
                throw $e;
            }

            return 'not_found';
        }

        if (! is_array($media) || ($media['id'] ?? null) === null) {
            return 'not_found';
        }

        if (isset($media['username']) && strcasecmp((string) $media['username'], $account->username) !== 0) {
            return 'not_owner';
        }

        return $media;
    }

    /**
     * Download + store one image or video (with its poster frame).
     *
     * @param  array<string, mixed>  $leaf
     * @return array{type: string, image_url: string, video_url: string|null}|string
     */
    private function storeLeaf(Profile $profile, array $leaf, bool $isGallery): array|string
    {
        $type = (string) ($leaf['media_type'] ?? 'IMAGE');
        $mediaUrl = $leaf['media_url'] ?? null;

        if (! is_string($mediaUrl) || $mediaUrl === '') {
            return 'no_media_url';
        }

        $imageType = $isGallery ? FileUploadType::GalleryPhoto : FileUploadType::KolabMedia;

        if ($type === 'VIDEO') {
            $thumb = $leaf['thumbnail_url'] ?? null;
            if (! is_string($thumb) || $thumb === '') {
                return 'no_thumbnail';
            }

            $videoType = $isGallery ? FileUploadType::GalleryVideo : FileUploadType::KolabMedia;
            $cap = min((int) config('services.instagram.max_video_mb', 100) * 1024 * 1024, $videoType->getMaxFileSize());

            $video = $this->download($mediaUrl, $cap, $reason);
            if ($video === null) {
                return $reason;
            }

            $poster = $this->download($thumb, $imageType->getMaxFileSize(), $reason);
            if ($poster === null) {
                return $reason;
            }

            try {
                $videoUrl = $this->files->uploadFromContents($video, $videoType, $profile->id);
                $posterUrl = $this->files->uploadFromContents($poster, $imageType, $profile->id);
            } catch (RuntimeException) {
                return 'invalid_file';
            }

            return ['type' => 'video', 'image_url' => $posterUrl, 'video_url' => $videoUrl];
        }

        if ($type !== 'IMAGE') {
            return 'unsupported_type';
        }

        $image = $this->download($mediaUrl, $imageType->getMaxFileSize(), $reason);
        if ($image === null) {
            return $reason;
        }

        try {
            $url = $this->files->uploadFromContents($image, $imageType, $profile->id);
        } catch (RuntimeException) {
            return 'invalid_file';
        }

        return ['type' => 'image', 'image_url' => $url, 'video_url' => null];
    }

    /**
     * Download a CDN file. Returns the bytes, or null with `$reason` set to
     * `too_large` or `download_failed`.
     */
    private function download(string $url, int $maxBytes, ?string &$reason = null): ?string
    {
        $reason = 'download_failed';

        if (! str_starts_with($url, 'https://')) {
            return null;
        }

        try {
            $response = Http::timeout(max(30, $this->timeout()))->get($url);
        } catch (ConnectionException) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $body = $response->body();

        if ((int) $response->header('Content-Length') > $maxBytes || strlen($body) > $maxBytes) {
            $reason = 'too_large';

            return null;
        }

        $reason = null;

        return $body;
    }

    /**
     * Run a Graph call with the account's token; an expired / revoked token
     * disconnects the account before the exception reaches the caller.
     *
     * @template T
     *
     * @param  callable(string): T  $call
     * @return T
     */
    private function withToken(InstagramAccount $account, callable $call): mixed
    {
        if ($account->access_token === null) {
            throw new InstagramException(InstagramException::NOT_CONNECTED);
        }

        try {
            return $call($account->access_token);
        } catch (InstagramException $e) {
            if ($e->errorCode === InstagramException::TOKEN_EXPIRED) {
                $this->disconnect($account);
            }

            throw $e;
        }
    }

    /**
     * Send a request and map Meta errors onto InstagramException.
     *
     * @param  callable(): Response  $request
     */
    private function send(callable $request): Response
    {
        try {
            $response = $request();
        } catch (ConnectionException $e) {
            Log::warning('Instagram API unreachable', ['error' => $e->getMessage()]);

            throw new InstagramException(InstagramException::API_ERROR);
        }

        if ($response->successful()) {
            return $response;
        }

        $error = $response->json('error');
        $code = is_array($error) ? (int) ($error['code'] ?? 0) : (int) $response->json('code', 0);
        $type = is_array($error) ? (string) ($error['type'] ?? '') : (string) $response->json('error_type', '');

        Log::warning('Instagram API error', [
            'status' => $response->status(),
            'code' => $code,
            'type' => $type,
            'message' => is_array($error) ? ($error['message'] ?? null) : $response->json('error_message'),
        ]);

        // 190 = invalid / expired / revoked access token (Graph API error codes).
        if ($code === 190 || $type === 'OAuthException' && $response->status() === 401) {
            throw new InstagramException(InstagramException::TOKEN_EXPIRED);
        }

        // 10 and 200-299 = permission not granted.
        if ($code === 10 || ($code >= 200 && $code <= 299)) {
            throw new InstagramException(InstagramException::PERMISSION_DENIED);
        }

        throw new InstagramException(InstagramException::API_ERROR);
    }

    /**
     * @return list<string>
     */
    private function permissionList(mixed $permissions): array
    {
        if (is_string($permissions)) {
            $permissions = explode(',', $permissions);
        }

        return array_values(array_map('trim', array_filter((array) $permissions, 'is_string')));
    }

    private function hasPhoto(Profile $profile): bool
    {
        return $profile->hasProfilePhoto();
    }

    private function caption(mixed $caption): ?string
    {
        if (! is_string($caption) || trim($caption) === '') {
            return null;
        }

        return Str::limit(trim($caption), 497);
    }

    private function graphBase(bool $versioned = false): string
    {
        $base = rtrim((string) config('services.instagram.graph_url', 'https://graph.instagram.com'), '/');

        return $versioned ? $base.'/'.config('services.instagram.graph_version', 'v25.0') : $base;
    }

    private function timeout(): int
    {
        return (int) config('services.instagram.timeout', 20);
    }

    private function stateCacheKey(string $nonce): string
    {
        return 'instagram:state:'.$nonce;
    }

    private function mediaCacheKey(InstagramAccount $account): string
    {
        return 'instagram:media:'.$account->id;
    }

    private function base64UrlDecode(string $value): ?string
    {
        $decoded = base64_decode(strtr($value, '-_', '+/').str_repeat('=', (4 - strlen($value) % 4) % 4), true);

        return $decoded === false ? null : $decoded;
    }
}
