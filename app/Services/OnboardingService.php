<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\FileUploadType;
use App\Models\Profile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
        private readonly ProfileService $profileService,
        private readonly FileUploadService $fileUploadService
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
            $profilePhotoUrl = $this->handleProfilePhoto(
                $data['profile_photo'] ?? null,
                $profile->id
            );

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
            $profilePhotoUrl = $this->handleProfilePhoto(
                $data['profile_photo'] ?? null,
                $profile->id
            );

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
     *
     * @param  string|null  $profilePhoto  Base64 encoded image or URL
     * @param  string  $profileId  The profile ID for organizing storage
     * @return string|null The uploaded file URL or null if no upload
     */
    private function handleProfilePhoto(?string $profilePhoto, string $profileId): ?string
    {
        if (empty($profilePhoto)) {
            return null;
        }

        try {
            // Check if it's a URL (external image or already uploaded)
            if (filter_var($profilePhoto, FILTER_VALIDATE_URL)) {
                // If it's already a storage URL from our system, return as-is
                $appUrl = config('app.url');
                if (str_starts_with($profilePhoto, $appUrl)) {
                    return $profilePhoto;
                }

                // Download and store external URL
                return $this->fileUploadService->uploadFromUrl(
                    $profilePhoto,
                    FileUploadType::ProfilePhoto,
                    $profileId
                );
            }

            // Check if it's base64 encoded
            if (preg_match('/^data:image\/(jpeg|jpg|png|gif|webp);base64,/i', $profilePhoto)) {
                return $this->fileUploadService->uploadFromBase64(
                    $profilePhoto,
                    FileUploadType::ProfilePhoto,
                    $profileId
                );
            }

            // Try to decode as raw base64 (without data URI prefix)
            if (base64_decode($profilePhoto, true) !== false) {
                return $this->fileUploadService->uploadFromBase64(
                    $profilePhoto,
                    FileUploadType::ProfilePhoto,
                    $profileId
                );
            }

            Log::warning('Invalid profile photo format provided', [
                'profile_id' => $profileId,
                'format_detected' => 'unknown',
            ]);

            return null;

        } catch (\Exception $e) {
            Log::error('Failed to upload profile photo', [
                'profile_id' => $profileId,
                'error' => $e->getMessage(),
            ]);

            // Return null to allow onboarding to continue without photo
            return null;
        }
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
