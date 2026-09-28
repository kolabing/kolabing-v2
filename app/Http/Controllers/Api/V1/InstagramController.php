<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ImportInstagramMediaRequest;
use App\Http\Resources\Api\V1\GalleryPhotoResource;
use App\Jobs\SyncInstagramAccount;
use App\Models\Kolab;
use App\Models\Profile;
use App\Services\InstagramService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Instagram Connect for businesses and communities (BE-NF-75,
 * docs/instagram-connect.md). Everything but GET /me/instagram answers 404
 * INSTAGRAM_UNAVAILABLE unless `services.instagram.enabled` is on or the
 * profile is a listed tester (pre App Review).
 */
class InstagramController extends Controller
{
    public function __construct(
        private readonly InstagramService $instagram,
    ) {}

    /**
     * GET /api/v1/me/instagram
     */
    public function show(Request $request): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        return response()->json([
            'success' => true,
            'data' => $this->instagram->status($profile),
        ]);
    }

    /**
     * POST /api/v1/me/instagram/connect-url  {return?: "app"|"web"}
     */
    public function connectUrl(Request $request): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();
        $this->instagram->ensureAvailable($profile);

        $validated = $request->validate([
            'return' => ['sometimes', 'string', 'in:app,web'],
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'url' => $this->instagram->authorizeUrl($profile, $validated['return'] ?? InstagramService::RETURN_APP),
            ],
        ]);
    }

    /**
     * GET /api/v1/me/instagram/media?cursor=
     */
    public function media(Request $request): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();
        $this->instagram->ensureAvailable($profile);

        $validated = $request->validate([
            'cursor' => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);

        $account = $this->instagram->connectedAccount($profile);

        return response()->json([
            'success' => true,
            'data' => $this->instagram->listMedia($profile, $account, $validated['cursor'] ?? null),
        ]);
    }

    /**
     * POST /api/v1/me/instagram/media/import  {ids: [...], target: "gallery"|"kolab", kolab_id?}
     */
    public function import(ImportInstagramMediaRequest $request): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();
        $this->instagram->ensureAvailable($profile);
        $account = $this->instagram->connectedAccount($profile);

        $target = $request->validated('target') ?? 'gallery';
        $kolab = null;

        if ($target === 'kolab') {
            $kolab = Kolab::query()->find($request->validated('kolab_id'));

            if ($kolab === null || $kolab->creator_profile_id !== $profile->id) {
                return response()->json([
                    'success' => false,
                    'message' => __('You are not authorized to edit this Kolab.'),
                ], 403);
            }
        }

        $result = $this->instagram->import($profile, $account, array_map('strval', $request->validated('ids')), $target, $kolab);

        return response()->json([
            'success' => true,
            'data' => [
                'target' => $target,
                // gallery: GalleryPhotoResource items; kolab: Kolab media items.
                'items' => $target === 'gallery'
                    ? GalleryPhotoResource::collection($result['items'])
                    : $result['items'],
                'skipped' => $result['skipped'],
            ],
        ], $result['items'] === [] ? 200 : 201);
    }

    /**
     * POST /api/v1/me/instagram/sync — queued; answers 202.
     */
    public function sync(Request $request): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();
        $this->instagram->ensureAvailable($profile);
        $account = $this->instagram->connectedAccount($profile);

        SyncInstagramAccount::dispatch($account->id);

        return response()->json([
            'success' => true,
            'data' => ['queued' => true],
        ], 202);
    }

    /**
     * POST /api/v1/me/instagram/avatar — use the Instagram picture as the
     * profile photo, only when the profile has none (never overwrites).
     */
    public function avatar(Request $request): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();
        $this->instagram->ensureAvailable($profile);
        $account = $this->instagram->connectedAccount($profile);

        return response()->json([
            'success' => true,
            'data' => ['avatar_url' => $this->instagram->applyAvatar($profile, $account)],
        ]);
    }

    /**
     * DELETE /api/v1/me/instagram — forget the token; imported media stays.
     * Allowed even when the feature is switched off, so nobody is stuck connected.
     */
    public function destroy(Request $request): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();
        $account = $profile->instagramAccount()->first();

        if ($account !== null && $account->access_token !== null) {
            $this->instagram->disconnect($account);
        }

        return response()->json([
            'success' => true,
            'data' => $this->instagram->status($profile),
        ]);
    }
}
