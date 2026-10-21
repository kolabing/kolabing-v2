<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\KolabStatus;
use App\Enums\UserType;
use App\Enums\VerificationStatus;
use App\Http\Controllers\Controller;
use App\Models\Profile;
use App\Services\ModerationService;
use App\Support\CommunityIdentityMask;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Every community on Kolabing, for a business to scroll (ROLES §2.24, SERR-201).
 *
 * Explore lists Kolabs, so a community with no open Kolab never reached a venue.
 * This is the whole network instead. A free business gets the same blur as on a
 * profile: the identity is withheld here, on the server, by the one rule
 * {@see CommunityIdentityMask}; the app only draws what it is sent.
 *
 * GET /api/v1/discovery/communities?city_id=&community_type=&per_page=&page=
 */
class BusinessCommunityDirectoryController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var Profile $viewer */
        $viewer = $request->user();

        if (! $viewer->isBusiness()) {
            return response()->json([
                'success' => false,
                'message' => 'Only businesses can browse the community directory.',
            ], 403);
        }

        $validated = $request->validate([
            'city_id' => ['nullable', 'uuid'],
            'community_type' => ['nullable', 'string', 'max:64'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $query = Profile::query()
            ->select('profiles.*')
            ->join('community_profiles', 'community_profiles.profile_id', '=', 'profiles.id')
            ->where('profiles.user_type', UserType::Community)
            ->active()
            ->with('communityProfile.city')
            ->withCount(['kolabs as published_kolabs_count' => function ($q): void {
                $q->where('status', KolabStatus::Published);
            }]);

        // Half-onboarded rows carry nothing to judge a fit on: same completeness
        // rule as suggestions (PairCandidateFinder), plus a name to show a subscriber.
        $query->whereNotNull('community_profiles.community_type')
            ->whereNotNull('community_profiles.name');

        // Blocks cut both ways, as on Explore and suggestions (App Review 1.2).
        $blockedIds = app(ModerationService::class)->blockedIds($viewer);
        if ($blockedIds !== []) {
            $query->whereNotIn('profiles.id', $blockedIds);
        }

        // Test accounts see each other (App Review); real venues never see them.
        if (! $viewer->is_test_user) {
            $query->where('profiles.is_test_user', false);
        }

        if (! empty($validated['city_id'])) {
            $query->where('community_profiles.city_id', $validated['city_id']);
        }

        if (! empty($validated['community_type'])) {
            $query->where('community_profiles.community_type', $validated['community_type']);
        }

        // Nothing here orders by name: the order itself must not hint at an
        // identity the viewer has not paid for.
        $paginator = $query
            ->orderByDesc('community_profiles.is_featured')
            ->orderByRaw('CASE WHEN community_profiles.verification_status = ? THEN 0 ELSE 1 END', [VerificationStatus::Verified->value])
            ->orderByRaw('community_profiles.community_size DESC NULLS LAST')
            ->orderBy('profiles.id')
            ->paginate((int) ($validated['per_page'] ?? 20));

        $items = $paginator->getCollection()->map(function (Profile $community) use ($viewer): array {
            $extended = $community->communityProfile;
            $masked = CommunityIdentityMask::applies($viewer, $community);

            return [
                // Withheld under the mask too: older endpoints still resolve a
                // profile id to a name (BE-FX-21 and the follow-up), and a masked
                // row only ever opens the paywall, so it needs no id.
                'id' => $masked ? null : $community->id,
                'identity_masked' => $masked,
                'display_name' => $masked ? null : $extended?->name,
                'avatar_url' => $masked ? null : ($community->avatar_url ?: $extended?->profile_photo),
                'community_type' => $extended?->community_type,
                'community_size' => $extended?->community_size,
                'city_name' => $extended?->city?->name,
                'is_verified' => $extended?->verification_status === VerificationStatus::Verified->value,
                'published_kolabs_count' => (int) $community->published_kolabs_count,
            ];
        })->values();

        $meta = [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ];

        return response()->json([
            'success' => true,
            'data' => $items,
            'meta' => $meta,
        ]);
    }
}
