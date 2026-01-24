<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Profile;
use Illuminate\Support\Facades\DB;

/**
 * @phpstan-type BusinessOnboardingData array{
 *     name: string,
 *     about?: string|null,
 *     business_type: string,
 *     city_id: string,
 *     phone_number?: string|null,
 *     instagram?: string|null,
 *     website?: string|null,
 *     profile_photo?: string|null
 * }
 * @phpstan-type CommunityOnboardingData array{
 *     name: string,
 *     about?: string|null,
 *     community_type: string,
 *     city_id: string,
 *     phone_number?: string|null,
 *     instagram?: string|null,
 *     tiktok?: string|null,
 *     website?: string|null,
 *     profile_photo?: string|null
 * }
 */
class OnboardingService
{
    public function __construct(
        private readonly ProfileService $profileService
    ) {}

    /**
     * Complete business user onboarding.
     *
     * @param  BusinessOnboardingData  $data
     */
    public function completeBusinessOnboarding(Profile $profile, array $data): Profile
    {
        return DB::transaction(function () use ($profile, $data): Profile {
            // Update phone number on main profile if provided
            if (isset($data['phone_number'])) {
                $profile->update(['phone_number' => $data['phone_number']]);
            }

            // Handle profile photo upload
            $profilePhotoUrl = $this->handleProfilePhoto($data['profile_photo'] ?? null);

            // Update business profile
            $businessProfile = $profile->businessProfile;
            $businessProfile->update([
                'name' => $data['name'],
                'about' => $data['about'] ?? null,
                'business_type' => $data['business_type'],
                'city_id' => $data['city_id'],
                'instagram' => $this->sanitizeSocialHandle($data['instagram'] ?? null),
                'website' => $data['website'] ?? null,
                'profile_photo' => $profilePhotoUrl ?? $businessProfile->profile_photo,
            ]);

            // Refresh and load relationships
            $profile->refresh();
            $this->profileService->loadProfileRelationships($profile);

            return $profile;
        });
    }

    /**
     * Complete community user onboarding.
     *
     * @param  CommunityOnboardingData  $data
     */
    public function completeCommunityOnboarding(Profile $profile, array $data): Profile
    {
        return DB::transaction(function () use ($profile, $data): Profile {
            // Update phone number on main profile if provided
            if (isset($data['phone_number'])) {
                $profile->update(['phone_number' => $data['phone_number']]);
            }

            // Handle profile photo upload
            $profilePhotoUrl = $this->handleProfilePhoto($data['profile_photo'] ?? null);

            // Update community profile
            $communityProfile = $profile->communityProfile;
            $communityProfile->update([
                'name' => $data['name'],
                'about' => $data['about'] ?? null,
                'community_type' => $data['community_type'],
                'city_id' => $data['city_id'],
                'instagram' => $this->sanitizeSocialHandle($data['instagram'] ?? null),
                'tiktok' => $this->sanitizeSocialHandle($data['tiktok'] ?? null),
                'website' => $data['website'] ?? null,
                'profile_photo' => $profilePhotoUrl ?? $communityProfile->profile_photo,
            ]);

            // Refresh and load relationships
            $profile->refresh();
            $this->profileService->loadProfileRelationships($profile);

            return $profile;
        });
    }

    /**
     * Handle profile photo upload.
     * Accepts base64 encoded image or URL.
     */
    private function handleProfilePhoto(?string $profilePhoto): ?string
    {
        if (empty($profilePhoto)) {
            return null;
        }

        // Check if it's a URL
        if (filter_var($profilePhoto, FILTER_VALIDATE_URL)) {
            return $profilePhoto;
        }

        // Check if it's base64 encoded
        if (preg_match('/^data:image\/(jpeg|jpg|png|gif);base64,/', $profilePhoto)) {
            // TODO: Implement actual base64 to storage upload
            // For now, return null as this requires storage configuration
            // In production, this would upload to Supabase Storage or similar
            return null;
        }

        return null;
    }

    /**
     * Sanitize social media handle by removing @ symbol if present.
     */
    private function sanitizeSocialHandle(?string $handle): ?string
    {
        if (empty($handle)) {
            return null;
        }

        return ltrim($handle, '@');
    }
}
