<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BusinessSubscription;
use App\Models\Profile;

class SubscriptionService
{
    /**
     * Get the subscription for a business profile.
     */
    public function getSubscription(Profile $profile): ?BusinessSubscription
    {
        if (! $profile->isBusiness()) {
            return null;
        }

        return $profile->subscription;
    }
}
