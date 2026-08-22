<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Community;
use App\Models\CommunityFollower;
use App\Models\Profile;
use Illuminate\Support\Carbon;

/**
 * Following and unfollowing a community (kolabing-app#138).
 *
 * Following is the low-commitment half of the split: one tap, no approval, no
 * notification to the leader — a follow is interest, not a request. It grants
 * nothing that membership grants; it exists so a person can keep an eye on a
 * community, and turn up to its public events, without being handed the keys to
 * the chat, the member-only events and the community's whole reward economy.
 *
 * Deliberately has no bearing on `community_members`: nothing here writes to
 * that table, which is what keeps every member-gated query in the app honest.
 */
class CommunityFollowService
{
    /**
     * Follow [$community], idempotently.
     *
     * Returns the follow row and whether it was created now — the controller
     * turns that into 201 vs 200. Idempotent because a double tap (or a retry
     * on a flaky connection) must not surface the unique constraint as a 500.
     *
     * @return array{follower: CommunityFollower, created: bool}
     */
    public function follow(Community $community, Profile $profile): array
    {
        $existing = CommunityFollower::query()
            ->where('community_id', $community->id)
            ->where('profile_id', $profile->id)
            ->first();

        if ($existing !== null) {
            return ['follower' => $existing, 'created' => false];
        }

        $follower = CommunityFollower::query()->create([
            'community_id' => $community->id,
            'profile_id' => $profile->id,
            'followed_at' => Carbon::now(),
        ]);

        return ['follower' => $follower, 'created' => true];
    }

    /**
     * Unfollow, idempotently. Unfollowing something you do not follow is a
     * no-op rather than a 404: the caller's intent is already satisfied.
     */
    public function unfollow(Community $community, Profile $profile): void
    {
        CommunityFollower::query()
            ->where('community_id', $community->id)
            ->where('profile_id', $profile->id)
            ->delete();
    }

    public function isFollowing(Community $community, Profile $profile): bool
    {
        return CommunityFollower::query()
            ->where('community_id', $community->id)
            ->where('profile_id', $profile->id)
            ->exists();
    }
}
