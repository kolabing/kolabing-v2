<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CommunityType;
use App\Enums\FileUploadType;
use App\Enums\IntentType;
use App\Enums\JoinPolicy;
use App\Enums\MissionTrigger;
use App\Models\Community;
use App\Models\Kolab;
use App\Models\Profile;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

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
        private readonly CommunityService $communityService,
        private readonly KolabService $kolabService,
        private readonly MissionService $missionService,
    ) {}

    /**
     * Fire a mission trigger for an earner, fully guarded: a mission failure
     * must never break onboarding. Mirrors the communityPoints guarded pattern.
     *
     * @param  array<string, mixed>  $context
     */
    private function recordMission(Profile $earner, MissionTrigger $trigger, array $context = []): void
    {
        try {
            $this->missionService->record($earner, $trigger, 1, $context);
        } catch (\Throwable $e) {
            Log::warning('Mission record failed (onboarding)', [
                'profile_id' => $earner->id,
                'trigger' => $trigger->value,
                'error' => $e->getMessage(),
            ]);
        }
    }

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

        $profile = DB::transaction(function () use ($profile, $data, $handle): Profile {
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

        // Missions: completing attendee onboarding is the discrete "profile
        // completed" event for an attendee. Guarded, runs after commit.
        $this->recordMission($profile, MissionTrigger::ProfileCompleted, ['reference_id' => $profile->id]);

        return $profile;
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
        $photoUploaded = false;

        $profile = DB::transaction(function () use ($profile, $data, &$photoUploaded): Profile {
            // Update phone number on main profile if provided
            if (isset($data['phone_number'])) {
                $profile->update(['phone_number' => $data['phone_number']]);
            }

            // Handle profile photo upload
            $profilePhotoUrl = $this->handleProfilePhoto(
                $data['profile_photo'] ?? null,
                $profile->id
            );
            $photoUploaded = $profilePhotoUrl !== null;
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
                'product_type' => $data['product_type'] ?? $businessProfile->product_type ?? 'other',
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
            $this->provisionBusinessAutoOffer($profile);

            // Refresh and load relationships
            $profile->refresh();
            $this->profileService->loadProfileRelationships($profile);

            return $profile;
        });

        // Missions: business onboarding-complete is the discrete "business
        // profile completed" event; fire the photo mission only when a photo was
        // actually resolved this run. Both guarded, after commit.
        $this->recordMission($profile, MissionTrigger::BusinessProfileCompleted, ['reference_id' => $profile->id]);

        if ($photoUploaded) {
            $this->recordMission($profile, MissionTrigger::BusinessPhotoUploaded, ['reference_id' => $profile->id]);
        }

        return $profile;
    }

    /**
     * Create + publish the single free auto-offer for a freshly onboarded
     * business, mirroring the validated KolabController::store -> publish path.
     *
     * Free tier = exactly one auto-offer. Idempotent: skips when the business
     * already owns any kolab. has_venue=true -> VenuePromotion (requires a
     * primary_venue on the profile); otherwise -> ProductPromotion.
     *
     * Public + shared so BOTH onboarding-complete and the one-shot register
     * path (AuthService::registerBusiness) provision the same auto-offer with
     * identical logic. Must be invoked AFTER the business profile (name, city,
     * primary_venue) is persisted so the composed kolab has the right data.
     */
    public function provisionBusinessAutoOffer(Profile $profile): void
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
                    'product_type' => $businessProfile->product_type ?: 'other',
                ];
            }

            $kolab = $this->kolabService->create($profile, $payload);
            // The onboarding auto-offer is the ONE free business post; it bypasses
            // the publish paywall. Every other business publish requires a sub.
            $this->kolabService->publish($kolab, [], allowUnsubscribedAutoOffer: true);
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
        $photoUploaded = false;

        $profile = DB::transaction(function () use ($profile, $data, &$photoUploaded): Profile {
            // Update phone number on main profile if provided
            if (isset($data['phone_number'])) {
                $profile->update(['phone_number' => $data['phone_number']]);
            }

            // Handle profile photo upload
            $profilePhotoUrl = $this->handleProfilePhoto(
                $data['profile_photo'] ?? null,
                $profile->id
            );
            $photoUploaded = $profilePhotoUrl !== null;

            // Update community profile
            $communityProfile = $profile->communityProfile;
            $resolvedPhoto = $profilePhotoUrl ?? $communityProfile->profile_photo;
            $communityProfile->fill([
                'name' => $data['name'],
                'about' => $data['about'] ?? null,
                'community_type' => $data['community_type'],
                'community_size' => $data['community_size'] ?? $communityProfile->community_size,
                'city_id' => $data['city_id'],
                'instagram' => $this->sanitizeSocialHandle($data['instagram'] ?? null),
                'tiktok' => $this->sanitizeSocialHandle($data['tiktok'] ?? null),
                'website' => $data['website'] ?? null,
                'profile_photo' => $resolvedPhoto,
            ]);

            // Verification: when proof channels are submitted during onboarding,
            // run the shared transition (unverified/rejected → pending; verified
            // stays verified but is flagged for admin re-check).
            $channels = $data['verification_channels'] ?? null;
            if (is_array($channels) && $channels !== []) {
                $communityProfile->applyVerificationChannelSubmission($channels);
            }

            $communityProfile->save();

            // Auto-provision the management community (the member/attendee roster
            // + main chat) the first time a community onboards, so they never
            // need the manual "New community" form. Idempotent + tolerant; the
            // SAME helper runs from the one-shot register path.
            $this->provisionManagementCommunity($profile);

            // FX-13 forward-sync: a community account's profile photo and its
            // community-group logo are ONE image. Mirror the onboarding photo
            // onto the group (now guaranteed to exist after the auto-create).
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

        // Missions: community onboarding-complete is the discrete "community
        // profile completed" event; fire the photo mission only when a photo was
        // actually resolved this run. Both guarded, after commit.
        $this->recordMission($profile, MissionTrigger::CommunityProfileCompleted, ['reference_id' => $profile->id]);

        if ($photoUploaded) {
            $this->recordMission($profile, MissionTrigger::CommunityPhotoUploaded, ['reference_id' => $profile->id]);
        }

        return $profile;
    }

    /**
     * Create the management community (member/attendee roster + main chat) for a
     * freshly onboarded/registered community owner, so they never need the manual
     * "New community" form.
     *
     * Public + shared so BOTH onboarding-complete and the one-shot register path
     * (AuthService::registerCommunity) provision the same community with identical
     * logic. Idempotent: skips when the owner already owns a community. Tolerant:
     * a failure here is logged and must never break registration/onboarding.
     * Must be invoked AFTER the community profile (name, type) is persisted.
     */
    public function provisionManagementCommunity(Profile $profile): void
    {
        $communityProfile = $profile->communityProfile;

        if ($communityProfile === null) {
            return;
        }

        // Idempotent: never create a second management community.
        if (Community::query()->where('owner_profile_id', $profile->id)->exists()) {
            return;
        }

        try {
            $this->communityService->create($profile, [
                'name' => $communityProfile->name,
                'type' => $this->mapCommunityType($communityProfile->community_type),
                'join_policy' => JoinPolicy::Open->value,
                'community_profile_id' => $communityProfile->id,
            ]);
        } catch (\Throwable $e) {
            // Never let management-community provisioning break registration.
            Log::error('Failed to auto-provision management community', [
                'profile_id' => $profile->id,
                'error' => $e->getMessage(),
            ]);
        }
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
