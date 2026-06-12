<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\FileUploadType;
use App\Models\Community;
use App\Models\Profile;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * @phpstan-type BusinessOnboardingData array{
 *     name: string,
 *     about?: string|null,
 *     business_type?: string|null,
 *     categories?: array<int, string>,
 *     city_id?: string|null,
 *     city_name?: string|null,
 *     phone_number?: string|null,
 *     instagram?: string|null,
 *     website?: string|null,
 *     profile_photo?: string|null,
 *     primary_venue: array<string, mixed>
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
 * @phpstan-type AttendeeOnboardingData array{
 *     name: string,
 *     handle: string,
 *     city_id?: string|null,
 *     interests?: array<int, string>,
 *     community_ids?: array<int, string>,
 *     photo?: string|null
 * }
 */
class OnboardingService
{
    public function __construct(
        private readonly ProfileService $profileService,
        private readonly FileUploadService $fileUploadService,
        private readonly BusinessVenueService $businessVenueService,
        private readonly HandleService $handleService,
        private readonly CommunityMemberService $communityMemberService,
    ) {}

    /**
     * Complete attendee onboarding (re-runnable). Persists identity (name,
     * handle, city, interests) on the base profile, uploads the photo to
     * avatar_url, and auto-joins each OPEN community in community_ids
     * (invite-only communities are skipped silently).
     *
     * @param  AttendeeOnboardingData  $data
     *
     * @throws ValidationException when the handle is taken or malformed.
     */
    public function completeAttendeeOnboarding(Profile $profile, array $data): Profile
    {
        $handle = $this->handleService->normalize($data['handle']);

        if (! $this->handleService->isValidFormat($handle)) {
            throw ValidationException::withMessages([
                'handle' => __('The handle must be 3 to 20 characters: lowercase letters, numbers, or underscores'),
            ]);
        }

        if (! $this->handleService->isAvailable($handle, $profile->id)) {
            throw ValidationException::withMessages([
                'handle' => __('That handle is already taken'),
            ]);
        }

        return DB::transaction(function () use ($profile, $data, $handle): Profile {
            $photoUrl = $this->handleProfilePhoto($data['photo'] ?? null, $profile->id);

            $profile->update([
                'name' => $data['name'],
                'handle' => $handle,
                'city_id' => $data['city_id'] ?? $profile->city_id,
                'interests' => array_values(array_unique($data['interests'] ?? [])),
                'avatar_url' => $photoUrl ?? $profile->avatar_url,
            ]);

            $this->autoJoinCommunities($profile, $data['community_ids'] ?? []);

            $profile->refresh();
            $this->profileService->loadProfileRelationships($profile);

            return $profile;
        });
    }

    /**
     * Auto-join each OPEN community by id. Invite-only communities are ignored
     * silently (the join service throws 'invite_only', which we swallow).
     *
     * @param  array<int, string>  $communityIds
     */
    private function autoJoinCommunities(Profile $profile, array $communityIds): void
    {
        if ($communityIds === []) {
            return;
        }

        $communities = Community::query()
            ->whereIn('id', array_values(array_unique($communityIds)))
            ->get();

        foreach ($communities as $community) {
            try {
                $this->communityMemberService->join($community, $profile);
            } catch (DomainException) {
                // invite-only — skip silently per the contract.
            }
        }
    }

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
            $resolvedCity = $this->businessVenueService->resolveCity(
                $data['city_id'] ?? null,
                $data['city_name'] ?? $data['primary_venue']['city'] ?? null
            );
            $primaryVenue = $this->businessVenueService->normalizePrimaryVenue(
                $data['primary_venue'],
                $profile->id,
                $profile->businessProfile?->primary_venue
            );
            $categories = $this->normalizeBusinessCategories($data, $profile->businessProfile);

            // Update business profile
            $businessProfile = $profile->businessProfile;
            $businessProfile->update([
                'name' => $data['name'],
                'about' => $data['about'] ?? null,
                'business_type' => $categories[0] ?? $businessProfile->business_type,
                'categories' => $categories,
                'city_id' => $resolvedCity?->id,
                'city_name' => $resolvedCity?->name ?? $data['city_name'] ?? $primaryVenue['city'],
                'city_country' => $resolvedCity?->country ?? $primaryVenue['country'],
                'instagram' => $this->sanitizeSocialHandle($data['instagram'] ?? null),
                'website' => $data['website'] ?? null,
                'profile_photo' => $profilePhotoUrl ?? $businessProfile->profile_photo,
                'primary_venue' => $primaryVenue,
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
            $resolvedPhoto = $profilePhotoUrl ?? $communityProfile->profile_photo;
            $communityProfile->update([
                'name' => $data['name'],
                'about' => $data['about'] ?? null,
                'community_type' => $data['community_type'],
                'city_id' => $data['city_id'],
                'instagram' => $this->sanitizeSocialHandle($data['instagram'] ?? null),
                'tiktok' => $this->sanitizeSocialHandle($data['tiktok'] ?? null),
                'website' => $data['website'] ?? null,
                'profile_photo' => $resolvedPhoto,
            ]);

            // FX-13 forward-sync: a community account's profile photo and its
            // community-group logo are ONE image. ProfileService::updateProfile
            // mirrors profile_photo -> Community.avatar_url, but onboarding writes
            // the community_profile directly and bypassed that mirror, so the photo
            // picked at onboarding never reached an already-created group. Mirror it
            // here too (no-op when the leader has no group yet; the group's create
            // path inherits this same profile_photo as its avatar).
            if ($resolvedPhoto !== null) {
                Community::query()
                    ->where('owner_profile_id', $profile->id)
                    ->update(['avatar_url' => $resolvedPhoto]);
            }

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

    /**
     * Normalize the ordered business categories.
     *
     * @param  BusinessOnboardingData  $data
     * @return array<int, string>
     */
    private function normalizeBusinessCategories(array $data, ?\App\Models\BusinessProfile $businessProfile): array
    {
        $categories = $data['categories'] ?? [];

        if (is_array($categories) && $categories !== []) {
            return array_values(array_unique($categories));
        }

        if (! empty($data['business_type'])) {
            return [$data['business_type']];
        }

        return $businessProfile?->normalizedCategories() ?? [];
    }
}
