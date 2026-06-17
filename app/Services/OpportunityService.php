<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Profile;

class OpportunityService
{
    /**
     * Check if a business user has reached the freemium collaboration limit.
     *
     * Unsubscribed business profiles may only accumulate 0 collaborations before
     * being required to subscribe. Once they have >=1 collaboration, further
     * paid collaboration creation/publishing is blocked until they subscribe.
     */
    public function hasReachedFreemiumCollabLimit(Profile $profile): bool
    {
        if (! $profile->isBusiness()) {
            return false;
        }

        if ($profile->hasActiveSubscription()) {
            return false;
        }

        return $profile->createdCollaborations()->count() >= 1;
    }
}
