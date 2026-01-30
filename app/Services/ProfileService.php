<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CollaborationStatus;
use App\Models\Collaboration;
use App\Models\NotificationPreference;
use App\Models\Profile;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

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
     * Get the full profile with subscription and notification preferences.
     */
    public function getFullProfile(Profile $profile): Profile
    {
        $this->loadProfileRelationships($profile);
        $profile->load('notificationPreferences');

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

    /**
     * Update the profile and extended profile data.
     *
     * @param  array<string, mixed>  $profileData
     * @param  array<string, mixed>  $extendedProfileData
     */
    public function updateProfile(
        Profile $profile,
        array $profileData,
        array $extendedProfileData
    ): Profile {
        return DB::transaction(function () use ($profile, $profileData, $extendedProfileData): Profile {
            // Update base profile data
            if (! empty($profileData)) {
                $profile->update($profileData);
            }

            // Update extended profile based on user type
            if (! empty($extendedProfileData)) {
                if ($profile->isBusiness() && $profile->businessProfile) {
                    $profile->businessProfile->update($extendedProfileData);
                } elseif ($profile->isCommunity() && $profile->communityProfile) {
                    $profile->communityProfile->update($extendedProfileData);
                }
            }

            // Reload relationships
            $this->loadProfileRelationships($profile);

            return $profile;
        });
    }

    /**
     * Soft delete the profile and revoke all tokens.
     */
    public function deleteProfile(Profile $profile): bool
    {
        return DB::transaction(function () use ($profile): bool {
            // Revoke all tokens
            $profile->tokens()->delete();

            // Soft delete the profile
            return $profile->delete();
        });
    }

    /**
     * Get completed collaborations for a profile (public view).
     *
     * @return LengthAwarePaginator<Collaboration>
     */
    public function getCompletedCollaborations(Profile $profile, int $perPage = 10): LengthAwarePaginator
    {
        return Collaboration::query()
            ->where('status', CollaborationStatus::Completed)
            ->where(function ($q) use ($profile): void {
                $q->where('creator_profile_id', $profile->id)
                    ->orWhere('applicant_profile_id', $profile->id);
            })
            ->with([
                'collabOpportunity',
                'creatorProfile.businessProfile',
                'creatorProfile.communityProfile',
                'applicantProfile.businessProfile',
                'applicantProfile.communityProfile',
            ])
            ->orderByDesc('completed_at')
            ->paginate($perPage);
    }

    /**
     * Get or create notification preferences for a profile.
     */
    public function getOrCreateNotificationPreferences(Profile $profile): NotificationPreference
    {
        return $profile->notificationPreferences()->firstOrCreate(
            ['profile_id' => $profile->id],
            [
                'email_notifications' => true,
                'whatsapp_notifications' => true,
                'new_application_alerts' => true,
                'collaboration_updates' => true,
                'marketing_tips' => false,
            ]
        );
    }

    /**
     * Update notification preferences.
     *
     * @param  array<string, bool>  $preferencesData
     */
    public function updateNotificationPreferences(
        Profile $profile,
        array $preferencesData
    ): NotificationPreference {
        $preferences = $this->getOrCreateNotificationPreferences($profile);
        $preferences->update($preferencesData);

        return $preferences;
    }
}
