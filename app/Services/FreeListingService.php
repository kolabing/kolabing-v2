<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CollaborationStatus;
use App\Enums\FreeListingState;
use App\Models\BusinessProfile;
use App\Models\Collaboration;
use App\Models\Profile;
use Illuminate\Support\Carbon;

/**
 * Free-listing expiry (issue #341, BE-NF-73). A business's free listing ends
 * at the EARLIER of `kolab_limit` completed kolabs or `day_limit` days after
 * its profile was created — numbers confirmed by Daniel 2026-09-25 (deliverable
 * 01239a7b, decision d4: 3 kolabs, 90 days).
 *
 * Live-read only, like {@see Profile::hasActiveSubscription()} and
 * {@see Profile::hasEventCreatorEntitlement()} — nothing is persisted, so the
 * result always reflects the current kolab count and config with no derived
 * column to keep in sync.
 *
 * This service computes state/expiry and exposes it (API + admin), but does
 * NOT yet gate any access — the notification copy, grace period, and delist
 * mechanics from issue #341's acceptance criteria are still open questions
 * for Daniel. {@see isEnforcedForCity()} is off (empty list) everywhere by
 * design until that follow-up lands.
 */
class FreeListingService
{
    public function state(Profile $profile): FreeListingState
    {
        if (! $profile->isBusiness()) {
            return FreeListingState::NotApplicable;
        }

        if ($profile->hasActiveSubscription()) {
            return FreeListingState::Subscribed;
        }

        $endsAt = $this->endsAt($profile);

        return $endsAt !== null && $endsAt->isPast()
            ? FreeListingState::Expired
            : FreeListingState::Free;
    }

    /**
     * The EARLIER of: the moment the business's Nth completed kolab happened,
     * or `day_limit` days after the business profile was created. Null when
     * the profile is not a business or has no business profile yet.
     */
    public function endsAt(Profile $profile): ?Carbon
    {
        $businessProfile = $profile->businessProfile;

        if (! $profile->isBusiness() || $businessProfile === null) {
            return null;
        }

        $dayLimitEndsAt = $this->dayLimitEndsAt($businessProfile);
        $kolabLimitEndsAt = $this->kolabLimitEndsAt($businessProfile);

        if ($kolabLimitEndsAt === null) {
            return $dayLimitEndsAt;
        }

        return $kolabLimitEndsAt->lessThan($dayLimitEndsAt) ? $kolabLimitEndsAt : $dayLimitEndsAt;
    }

    /**
     * How many completed kolabs and how many days remain before each gate
     * fires, clamped at 0 — lets a client render its own "ending soon" cue
     * from the remaining counts rather than a hardcoded server threshold
     * (issue #341 doesn't specify one yet).
     *
     * @return array{kolabs_remaining: int, days_remaining: int}
     */
    public function remaining(Profile $profile): array
    {
        $businessProfile = $profile->businessProfile;

        if (! $profile->isBusiness() || $businessProfile === null) {
            return ['kolabs_remaining' => 0, 'days_remaining' => 0];
        }

        $kolabLimit = (int) config('subscriptions.free_listing.kolab_limit');
        $dayLimit = (int) config('subscriptions.free_listing.day_limit');

        return [
            'kolabs_remaining' => max(0, $kolabLimit - $this->completedKolabCount($businessProfile)),
            'days_remaining' => max(0, $dayLimit - (int) $businessProfile->created_at->diffInDays(now())),
        ];
    }

    /**
     * Whether the per-city enforcement toggle is on for this city. Off
     * everywhere by default (issue #341 AC 3) until a city hits the traction
     * bar and Daniel switches it on.
     */
    public function isEnforcedForCity(?string $cityId): bool
    {
        if ($cityId === null) {
            return false;
        }

        return in_array($cityId, (array) config('subscriptions.free_listing.enforcement_city_ids'), true);
    }

    private function dayLimitEndsAt(BusinessProfile $businessProfile): Carbon
    {
        $dayLimit = (int) config('subscriptions.free_listing.day_limit');

        return $businessProfile->created_at->copy()->addDays($dayLimit);
    }

    private function kolabLimitEndsAt(BusinessProfile $businessProfile): ?Carbon
    {
        $kolabLimit = (int) config('subscriptions.free_listing.kolab_limit');

        if ($kolabLimit < 1) {
            return null;
        }

        $nthCompleted = Collaboration::query()
            ->where('business_profile_id', $businessProfile->id)
            ->where('status', CollaborationStatus::Completed)
            ->orderBy('completed_at')
            ->skip($kolabLimit - 1)
            ->first();

        return $nthCompleted?->completed_at;
    }

    private function completedKolabCount(BusinessProfile $businessProfile): int
    {
        return Collaboration::query()
            ->where('business_profile_id', $businessProfile->id)
            ->where('status', CollaborationStatus::Completed)
            ->count();
    }
}
