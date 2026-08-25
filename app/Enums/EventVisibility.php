<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Who may SEE an event.
 *
 * - Public  — anyone; surfaces in city discover.
 * - Members — host community members (the historical default).
 * - Tier    — restricted to the tiers listed in `events.tier_gate`.
 */
enum EventVisibility: string
{
    case Public = 'public';

    /**
     * Anyone who follows the community (kolabing-app#157).
     *
     * The weakest community-scoped audience, and the one that makes following
     * worth doing: it is something a stranger can opt into with one tap, which
     * is the point of the follower half of the split.
     */
    case Followers = 'followers';

    case Members = 'members';

    /**
     * Members who have attended within CommunityMember::ACTIVE_WINDOW_DAYS.
     *
     * The narrowest audience that is not a tier: for the thing you only want to
     * offer people who actually still turn up.
     */
    case ActiveMembers = 'active_members';

    case Tier = 'tier';
}
