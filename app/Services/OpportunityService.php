<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Profile;

class OpportunityService
{
    /**
     * Check if a business user is barred from creating opportunities by the paywall.
     *
     * Businesses get NO free self-created opportunity: an unsubscribed business is
     * always at the limit. The only free business post is the onboarding auto-offer,
     * which is provisioned via KolabService and does not pass through this gate.
     * Communities are never gated.
     */
    public function hasReachedFreemiumCollabLimit(Profile $profile): bool
    {
        if (! $profile->isBusiness()) {
            return false;
        }

        return ! $profile->hasActiveSubscription();
    }
}
