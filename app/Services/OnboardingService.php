<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CommunityType;
use App\Enums\FileUploadType;
use App\Enums\IntentType;
use App\Enums\JoinPolicy;
use App\Models\Community;
use App\Models\Kolab;
use App\Models\Profile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * @phpstan-type BusinessOnboardingData array{
 *     name: string,
 *     about?: string|null,
 *     offering?: string|null,
 *     business_type?: string|null,
 *     categories?: array<int, string>,
 *     has_venue?: bool,
 *     city_id?: string|null,
 *     city_name?: string|null,
 *     target_city_ids?: array<int, string>,
 *     offer_photos?: array<int, string>,
 *     phone_number?: string|null,
 *     instagram?: string|null,
 *     website?: string|null,
 *     profile_photo?: string|null,
 *     primary_venue?: array<string, mixed>|null
 * }
 * @phpstan-type CommunityOnboardingData array{
 *     name: string,
 *     about?: string|null,
 *     community_type: string,
 *     community_size?: int|null,
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
        private readonly FileUploadService $fileUploadService,
        private readonly BusinessVenueService $businessVenueService,
        private readonly CommunityService $communityService,
        private readonly KolabService $kolabService
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
            $businessProfile = $profile->businessProfile;

            $hasVenue = $data['has_venue'] ?? $businessProfile->has_venue ?? true;
            $primaryVenueInput = $data['primary_venue'] ?? null;

            $resolvedCity = $this->businessVenueService->resolveCity(
                $data['city_id'] ?? null,
                $data['city_name'] ?? $primaryVenueInput['city'] ?? null
            );

            $primaryVenue = ($hasVenue && is_array($primaryVenueInput))
                ? $this->businessVenueService->normalizePrimaryVenue(
                    $primaryVenueInput,
                    $profile->id,
                    $businessProfile->primary_venue
                )
                : ($hasVenue ? $businessProfile->primary_venue : null);

            $categories = $this->normalizeBusinessCategories($data, $businessProfile);

            $targetCityIds = array_values($data['target_city_ids'] ?? []);
            $offerPhotos = isset($data['offer_photos'])
                ? $this->businessVenueService->normalizePhotos($data['offer_photos'], $profile->id)
                : ($businessProfile->offer_photos ?? []);

            // Update business profile
            $businessProfile->update([
                'name' => $data['name'],
                'about' => $data['about'] ?? null,
                'offering' => $data['offering'] ?? $businessProfile->offering,
                'business_type' => $categories[0] ?? $businessProfile->business_type,
                'has_venue' => $hasVenue,
                'categories' => $categories,
                'city_id' => $resolvedCity?->id,
                'city_name' => $resolvedCity?->name ?? $data['city_name'] ?? $primaryVenue['city'] ?? null,
                'city_country' => $resolvedCity?->country ?? $primaryVenue['country'] ?? null,
                'target_city_ids' => $targetCityIds === [] ? ($businessProfile->target_city_ids ?? null) : $targetCityIds,
                'instagram' => $this->sanitizeSocialHandle($data['instagram'] ?? null),
                'website' => $data['website'] ?? null,
                'profile_photo' => $profilePhotoUrl ?? $businessProfile->profile_photo,
                'primary_venue' => $primaryVenue,
                'offer_photos' => $offerPhotos === [] ? null : $offerPhotos,
            ]);

            $profile->refresh();

            // Auto-provision the business's first, free, published kolab (the
            // "auto-offer") composed from the just-saved profile, so a free
            // business lands with one live listing and never has to open the
            // create form. Idempotent + tolerant: a failure here must not break
            // onboarding.
            $this->autoProvisionBusinessKolab($profile);

            // Refresh and load relationships
            $profile->refresh();
            $this->profileService->loadProfileRelationships($profile);

            return $profile;
        });
    }

    /**
     * Create + publish the single free auto-offer for a freshly onboarded
     * business, mirroring the validated KolabController::store -> publish path.
     *
     * Free tier = exactly one auto-offer. Idempotent: skips when the business
     * already owns any kolab. has_venue=true -> VenuePromotion (requires a
     * primary_venue on the profile); otherwise -> ProductPromotion.
     */
    private function autoProvisionBusinessKolab(Profile $profile): void
    {
        $businessProfile = $profile->businessProfile;

        if ($businessProfile === null) {
            return;
        }

        // Idempotent: never create a second auto-offer.
        if (Kolab::query()->where('creator_profile_id', $profile->id)->exists()) {
            return;
        }

        $hasVenue = (bool) $businessProfile->has_venue;
        $name = $businessProfile->name ?: 'My business';
        $about = $businessProfile->about;
        $offering = $businessProfile->offering;

        try {
            if ($hasVenue) {
                // VenuePromotion needs a primary_venue; KolabService enriches
                // venue_name/type/capacity/address + preferred_city from it.
                if (empty($businessProfile->primary_venue)) {
                    Log::info('Skipping venue auto-offer: no primary venue on profile', [
                        'profile_id' => $profile->id,
                    ]);

                    return;
                }

                $venue = $businessProfile->primary_venue;
                $description = $about
                    ?: ($offering ?: 'Partner with '.$name.' at our venue.');

                $payload = [
                    'intent_type' => IntentType::VenuePromotion->value,
                    'title' => $name,
                    'description' => $description,
                    'offering' => ['venue'],
                    // preferred_city is filled from the primary venue by the
                    // KolabService when omitted; pass our best guess too.
                    'preferred_city' => $venue['city'] ?? $businessProfile->city_name ?? null,
                    // Mirror the create form's availability default.
                    'availability_mode' => 'flexible',
                    'availability_start' => now()->addDay()->toDateString(),
                    // Reuse the venue's rehosted photos when present (optional at
                    // the service layer).
                    'media' => $this->venueMediaFromVenue($venue),
                ];
            } else {
                // ProductPromotion needs product_name + product_type + offering
                // + preferred_city.
                $description = $about
                    ?: ($offering ?: 'Collaborate with '.$name.' to promote our product.');

                // ProductPromotion requires a preferred_city; without one we
                // cannot build a valid offer, so skip rather than persist junk.
                if (empty($businessProfile->city_name)) {
                    Log::info('Skipping product auto-offer: unresolved preferred city', [
                        'profile_id' => $profile->id,
                    ]);

                    return;
                }

                $payload = [
                    'intent_type' => IntentType::ProductPromotion->value,
                    'title' => $name,
                    'description' => $description,
                    'offering' => ['products'],
                    'preferred_city' => $businessProfile->city_name,
                    'product_name' => $name,
                    'product_type' => 'other',
                ];
            }

            $kolab = $this->kolabService->create($profile, $payload);
            $this->kolabService->publish($kolab);
        } catch (\Throwable $e) {
            // Never let auto-offer provisioning break onboarding.
            Log::error('Failed to auto-provision business kolab', [
                'profile_id' => $profile->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Build the kolab media collection from a primary venue's stored photos.
     * Venue promotions require at least one media item; we reuse the venue's
     * already-rehosted photo URLs. Returns null when no usable photo exists
     * (the caller treats that as "skip" for venue auto-offers).
     *
     * @param  array<string, mixed>  $venue
     * @return array<int, array{url: string, type: string, sort_order: int}>|null
     */
    private function venueMediaFromVenue(array $venue): ?array
    {
        $photos = $venue['photos'] ?? [];

        if (! is_array($photos)) {
            return null;
        }

        $media = [];
        $order = 0;

        foreach ($photos as $photo) {
            if (is_string($photo) && filter_var($photo, FILTER_VALIDATE_URL)) {
                $media[] = [
                    'url' => $photo,
                    'type' => 'image',
                    'sort_order' => $order++,
                ];
            }
        }

        return $media === [] ? null : $media;
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
                'community_size' => $data['community_size'] ?? $communityProfile->community_size,
                'city_id' => $data['city_id'],
                'instagram' => $this->sanitizeSocialHandle($data['instagram'] ?? null),
                'tiktok' => $this->sanitizeSocialHandle($data['tiktok'] ?? null),
                'website' => $data['website'] ?? null,
                'profile_photo' => $profilePhotoUrl ?? $communityProfile->profile_photo,
            ]);

            // Auto-provision the management community (the member/attendee roster
            // + main chat) the first time a community onboards, so they never
            // need the manual "New community" form. Idempotent: only when the
            // owner has none yet.
            if (Community::query()->where('owner_profile_id', $profile->id)->doesntExist()) {
                $this->communityService->create($profile, [
                    'name' => $data['name'],
                    'type' => $this->mapCommunityType($data['community_type']),
                    'join_policy' => JoinPolicy::Open->value,
                    'community_profile_id' => $communityProfile->id,
                ]);
            }

            // Refresh and load relationships
            $profile->refresh();
            $this->profileService->loadProfileRelationships($profile);

            return $profile;
        });
    }

    /**
     * Map a community profile's taxonomy slug (from /community-types) to the
     * coarse Community management-entity type enum. Unknown slugs fall back to
     * "other" — the management community's type is not user-facing taxonomy.
     */
    private function mapCommunityType(string $slug): string
    {
        $s = strtolower($slug);

        return match (true) {
            str_contains($s, 'run') => CommunityType::Running->value,
            str_contains($s, 'fitness'),
            str_contains($s, 'wellness'),
            str_contains($s, 'sport') => CommunityType::Fitness->value,
            str_contains($s, 'business'),
            str_contains($s, 'coworking'),
            str_contains($s, 'professional') => CommunityType::Business->value,
            default => CommunityType::Other->value,
        };
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
