<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\CommunityMemberStatus;
use App\Models\Community;
use App\Models\Profile;

/**
 * Authorization for the community-members & tiers surface (NF-6).
 *
 * Mutating a community, its tiers, or its roster requires the auth profile to
 * be the owner OR an active member with can_manage = true. This is NEVER gated
 * on a business subscription; communities and members are free of the paywall.
 */
class CommunityPolicy
{
    /**
     * Any authenticated user may view a community and its public roster.
     */
    public function view(Profile $user, Community $community): bool
    {
        return true;
    }

    /**
     * Owner, or an active member with can_manage, may administer the community.
     */
    public function manage(Profile $user, Community $community): bool
    {
        if ($user->id === $community->owner_profile_id) {
            return true;
        }

        return $community->members()
            ->where('profile_id', $user->id)
            ->where('can_manage', true)
            ->where('status', CommunityMemberStatus::Active->value)
            ->exists();
    }

    /**
     * The per-community points leaderboard lists every member with their
     * points, tier and badges, so only people inside the community may read
     * it: an active member, or someone who may manage it (owner / can_manage).
     */
    public function viewLeaderboard(Profile $user, Community $community): bool
    {
        if ($this->manage($user, $community)) {
            return true;
        }

        return $community->members()
            ->where('profile_id', $user->id)
            ->where('status', CommunityMemberStatus::Active->value)
            ->exists();
    }
}
