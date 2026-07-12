<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CommunityMemberStatus;
use App\Enums\JoinRequestStatus;
use App\Models\CommunityJoinRequest;
use App\Models\CommunityMember;
use App\Models\CommunityPoints;
use App\Models\Profile;
use Illuminate\Support\Collection;

class CommunityMembershipHydrator
{
    /**
     * Bulk-resolve the viewer-scoped fields CommunityResource would otherwise
     * query once per community (member count, points, tier, join-request status,
     * is-member) and stash them as transient attributes on each community model.
     * This turns membership-backed lists from ~5 queries per community into a
     * constant handful (PHP-LARAVEL-7).
     *
     * Each membership must have its `community` (and `tier`, when set) loaded.
     * Returns the per-community points map (community_id => points) so callers
     * that also need the raw balances (e.g. reward affordability) can reuse it
     * without a second query.
     *
     * @param  Collection<int, CommunityMember>  $memberships
     * @return array<string, int>
     */
    public function hydrate(Profile $profile, Collection $memberships): array
    {
        $communityIds = $memberships->pluck('community')->filter()->pluck('id')->all();

        if ($communityIds === []) {
            return [];
        }

        $memberCounts = CommunityMember::query()
            ->whereIn('community_id', $communityIds)
            ->where('status', CommunityMemberStatus::Active->value)
            ->selectRaw('community_id, count(*) as aggregate')
            ->groupBy('community_id')
            ->pluck('aggregate', 'community_id');

        $points = CommunityPoints::query()
            ->whereIn('community_id', $communityIds)
            ->where('profile_id', $profile->id)
            ->pluck('points', 'community_id');

        $joinStatuses = CommunityJoinRequest::query()
            ->whereIn('community_id', $communityIds)
            ->where('profile_id', $profile->id)
            ->orderByDesc('created_at')
            ->get(['community_id', 'status'])
            ->groupBy('community_id')
            ->map(static function ($requests): ?string {
                $status = $requests->first()->status;

                if ($status === null) {
                    return null;
                }

                return $status instanceof JoinRequestStatus ? $status->value : (string) $status;
            });

        foreach ($memberships as $member) {
            $community = $member->community;
            if ($community === null) {
                continue;
            }

            $community->setAttribute('member_count', (int) ($memberCounts[$community->id] ?? 0));
            // Every row in a membership list is the viewer's own ACTIVE membership.
            $community->setAttribute('viewer_is_member', true);
            $community->setAttribute('viewer_join_request_status', $joinStatuses[$community->id] ?? null);
            $community->setAttribute('viewer_points', (int) ($points[$community->id] ?? 0));
            $community->setAttribute('viewer_tier', $member->tier ? [
                'id' => $member->tier->id,
                'name' => $member->tier->name,
                'color' => $member->tier->color,
                'rank' => $member->tier->rank,
            ] : null);
        }

        return $points->mapWithKeys(static fn ($value, $communityId): array => [
            (string) $communityId => (int) $value,
        ])->all();
    }
}
