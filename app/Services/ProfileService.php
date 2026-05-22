<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CollaborationStatus;
use App\Enums\KolabStatus;
use App\Enums\NotificationType;
use App\Enums\OfferStatus;
use App\Models\Collaboration;
use App\Models\NotificationPreference;
use App\Models\Profile;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ProfileService
{
    public function __construct(
        private readonly NotificationService $notificationService
    ) {}

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
                    $extendedProfileData = $this->normalizeBusinessProfileData(
                        $profile->businessProfile,
                        $extendedProfileData
                    );
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
     * Delete the user's account with full data-integrity cleanup.
     *
     * Runs inside a transaction and, in order:
     *   1. Frees the email by scrubbing it to a non-conflicting placeholder so
     *      the address can be re-registered (the unique index on profiles.email
     *      survives the soft delete, so the live value must be released).
     *   2. Closes the user's open posts (draft/published kolabs and
     *      collab_opportunities) so they no longer surface anywhere.
     *   3. Cancels the user's scheduled/active collaborations and notifies the
     *      counterparty in-app. Completed collaborations are left intact.
     *   4. Revokes all tokens.
     *   5. Soft deletes the profile.
     */
    public function deleteProfile(Profile $profile): bool
    {
        return DB::transaction(function () use ($profile): bool {
            // 1. Free the email so it can be re-registered. The unique index on
            //    profiles.email is not deleted_at-aware, so the soft-deleted row
            //    would otherwise keep the address locked forever.
            $profile->forceFill([
                'email' => "deleted+{$profile->id}@kolabing.invalid",
            ])->save();

            // 2. Close the user's open posts (both post systems).
            $profile->kolabs()
                ->whereIn('status', [KolabStatus::Draft, KolabStatus::Published])
                ->update(['status' => KolabStatus::Closed]);

            $profile->createdOpportunities()
                ->whereIn('status', [OfferStatus::Draft, OfferStatus::Published])
                ->update(['status' => OfferStatus::Closed]);

            // 3. Cancel scheduled/active collaborations and notify the counterparty.
            $this->cancelActiveCollaborations($profile);

            // 4. Revoke all tokens.
            $profile->tokens()->delete();

            // 5. Soft delete the profile.
            return $profile->delete();
        });
    }

    /**
     * Cancel the deleting profile's scheduled/active collaborations and send an
     * in-app notification to the other participant. Completed and already
     * cancelled collaborations are skipped.
     */
    private function cancelActiveCollaborations(Profile $profile): void
    {
        $collaborations = Collaboration::query()
            ->whereIn('status', [CollaborationStatus::Scheduled, CollaborationStatus::Active])
            ->where(function ($q) use ($profile): void {
                $q->where('creator_profile_id', $profile->id)
                    ->orWhere('applicant_profile_id', $profile->id);
            })
            ->with(['creatorProfile', 'applicantProfile', 'collabOpportunity'])
            ->get();

        foreach ($collaborations as $collaboration) {
            $collaboration->update([
                'status' => CollaborationStatus::Cancelled,
            ]);

            // Resolve the counterparty (the participant who is NOT deleting).
            $counterparty = $collaboration->creator_profile_id === $profile->id
                ? $collaboration->applicantProfile
                : $collaboration->creatorProfile;

            if ($counterparty === null) {
                continue;
            }

            $title = $collaboration->collabOpportunity?->title ?? 'a collaboration';

            $this->notificationService->createNotification(
                recipient: $counterparty,
                type: NotificationType::ApplicationDeclined,
                title: 'Collaboration Cancelled',
                body: "\"{$title}\" was cancelled because the other participant deleted their account.",
                targetId: $collaboration->id,
                targetType: 'collaboration',
            );
        }
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
                'event',
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

    /**
     * Normalize business profile updates for categories compatibility.
     *
     * @param  array<string, mixed>  $extendedProfileData
     * @return array<string, mixed>
     */
    private function normalizeBusinessProfileData(
        \App\Models\BusinessProfile $businessProfile,
        array $extendedProfileData
    ): array {
        $categories = $extendedProfileData['categories'] ?? null;
        $businessType = $extendedProfileData['business_type'] ?? null;

        if (is_array($categories) && $categories !== []) {
            $normalizedCategories = array_values(array_unique($categories));

            $extendedProfileData['categories'] = $normalizedCategories;
            $extendedProfileData['business_type'] = $normalizedCategories[0];

            return $extendedProfileData;
        }

        if (is_string($businessType) && $businessType !== '') {
            $extendedProfileData['categories'] = [$businessType];
            $extendedProfileData['business_type'] = $businessType;

            return $extendedProfileData;
        }

        if (array_key_exists('categories', $extendedProfileData) && $categories === []) {
            $extendedProfileData['categories'] = $businessProfile->normalizedCategories();
            $extendedProfileData['business_type'] = $extendedProfileData['categories'][0] ?? $businessProfile->business_type;
        }

        return $extendedProfileData;
    }
}
