<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\UserType;
use App\Models\Profile;

/**
 * Whether a community's identity is withheld from the caller — the one place that
 * decides it.
 *
 * ROLES §2.5 lists "see the community NAME", "see the community LOGO" and "open a
 * community's full profile or contact" among the things a free business cannot do.
 * Until BE-FX-22 no profile resource checked a subscription at all, so the identity
 * a business is meant to pay for was one request away for anyone holding an id. The
 * mobile app drew a blur over a payload that carried the real name; the web panel
 * drew nothing. Neither was a gate, and a client-side blur never can be.
 *
 * It lives here rather than in a resource because it is needed by more than one —
 * `PublicProfileResource` (`GET /profiles/{id}`) and
 * `CommunityPublicProfileResource` (`GET /profiles/{id}/public-profile` and
 * `GET /communities/{id}/public-profile`) — and two copies of a paywall condition is
 * how one of them ends up wrong. `SuggestionResource` owns the same rule for the
 * suggestion surface, where the masked shape differs (it also withholds the
 * counterpart's id, because a card is not addressed by it).
 *
 * **The order of the guards is the whole rule.**
 * {@see Profile::hasActiveSubscription()} returns false for *every* non-business, so
 * testing the subscription alone — or before the role — masks every community and
 * every attendee viewer on the platform. That is the most damaging regression
 * available here; §12.5 and §18.6 of the backend map both warn about it, and
 * `PublicProfileMaskTest` pins it from four directions.
 */
final class CommunityIdentityMask
{
    /**
     * @param  Profile|null  $viewer  the authenticated caller, if any
     * @param  Profile  $subject  the profile being serialised
     */
    public static function applies(?Profile $viewer, Profile $subject): bool
    {
        // Not a business — never masked. This short-circuit must stay first.
        if ($viewer === null || ! $viewer->isBusiness()) {
            return false;
        }

        // Only a community's identity is paywalled. There is no business-identity
        // paywall, and an attendee has no public profile to withhold (§4.2).
        if ($subject->user_type !== UserType::Community) {
            return false;
        }

        // Nobody is masked from their own profile.
        if ($viewer->id === $subject->id) {
            return false;
        }

        return ! $viewer->hasActiveSubscription();
    }
}
