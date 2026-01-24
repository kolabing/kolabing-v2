<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Profile;

class ProfileService
{
    /**
     * Get the authenticated user with all related profile data.
     */
    public function getAuthenticatedUser(Profile $profile): Profile
    {
        $this->loadProfileRelationships($profile);

        return $profile;
    }

    /**
     * Load profile relationships based on user type.
     */
    public function loadProfileRelationships(Profile $profile): void
    {
        if ($profile->isBusiness()) {
            $profile->load(['businessProfile.city', 'subscription']);
        } else {
            $profile->load(['communityProfile.city']);
        }
    }
}
