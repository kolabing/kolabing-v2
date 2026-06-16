<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CollaborationStatus;
use App\Enums\KolabStatus;
use App\Enums\NotificationType;
use App\Enums\OfferStatus;
use App\Models\Collaboration;
use App\Models\CollaborationReview;
use App\Models\Community;
use App\Models\Kolab;
use App\Models\NotificationPreference;
use App\Models\Profile;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
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
        } elseif ($profile->isAttendee()) {
            // Attendee identity lives on the base profile; load the base city
            // plus the attendee extended profile (gamification stats).
            $profile->load(['attendeeProfile', 'city']);
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

                    // Same-image sync: a community's profile photo IS its logo —
                    // mirror it to the community entity's avatar_url so the hub,
                    // detail and my-communities screens all match.
                    if (array_key_exists('profile_photo', $extendedProfileData)) {
                        Community::query()
                            ->where('owner_profile_id', $profile->id)
                            ->update(['avatar_url' => $extendedProfileData['profile_photo']]);
                    }
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
     * Get reviews received by a profile (public view).
     *
     * @return LengthAwarePaginator<CollaborationReview>
     */
    public function getReceivedReviews(Profile $profile, int $perPage = 10): LengthAwarePaginator
    {
        return CollaborationReview::query()
            ->where('reviewed_profile_id', $profile->id)
            ->whereNotNull('rating')
            ->with([
                'reviewerProfile.businessProfile',
                'reviewerProfile.communityProfile',
            ])
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    /**
     * Get a safe public-facing community profile payload.
     *
     * @throws ModelNotFoundException
     */
    public function getCommunityPublicProfile(Profile $profile): Profile
    {
        if (! $profile->isCommunity()) {
            $exception = new ModelNotFoundException;
            $exception->setModel(Profile::class, [$profile->id]);

            throw $exception;
        }

        $profile->load([
            'communityProfile.city',
            'galleryPhotos',
        ]);

        $completedCollaborationsCount = Collaboration::query()
            ->where('status', CollaborationStatus::Completed)
            ->where(function ($query) use ($profile): void {
                $query->where('creator_profile_id', $profile->id)
                    ->orWhere('applicant_profile_id', $profile->id);
            })
            ->count();

        $publishedKolabsCount = Kolab::query()
            ->where('creator_profile_id', $profile->id)
            ->whereIn('status', [KolabStatus::Published, KolabStatus::Closed])
            ->count();

        $pastEvents = $this->buildCommunityPastEvents($profile);
        $pastCollaborations = $this->buildCommunityPastCollaborations($profile);

        $profile->setAttribute('community_public_stats', [
            'completed_collaborations_count' => $completedCollaborationsCount,
            'published_kolabs_count' => $publishedKolabsCount,
            'past_events_count' => count($pastEvents),
        ]);
        $profile->setAttribute('community_public_past_events', $pastEvents);
        $profile->setAttribute('community_public_photos', $this->buildCommunityPhotos($profile, $pastEvents));
        $profile->setAttribute('community_public_gallery', $this->buildCommunityGallery($profile));
        $profile->setAttribute('community_public_past_collaborations', $pastCollaborations);

        return $profile;
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
                'message_notifications' => true,
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

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildCommunityPastEvents(Profile $profile): array
    {
        /** @var Collection<int, Kolab> $kolabs */
        $kolabs = Kolab::query()
            ->where('creator_profile_id', $profile->id)
            ->whereIn('status', [KolabStatus::Published, KolabStatus::Closed])
            ->orderByDesc('published_at')
            ->get(['id', 'past_events']);

        return $kolabs
            ->flatMap(function (Kolab $kolab): array {
                if (! is_array($kolab->past_events)) {
                    return [];
                }

                return array_map(function (mixed $event) use ($kolab): ?array {
                    if (! is_array($event)) {
                        return null;
                    }

                    return [
                        'source_kolab_id' => $kolab->id,
                        'name' => isset($event['name']) && is_string($event['name']) ? $event['name'] : null,
                        'date' => isset($event['date']) && is_string($event['date']) ? $event['date'] : null,
                        'partner_name' => isset($event['partner_name']) && is_string($event['partner_name']) ? $event['partner_name'] : null,
                        'media' => $this->normalizeMediaCollection($event['media'] ?? $event['photos'] ?? []),
                    ];
                }, $kolab->past_events);
            })
            ->filter(fn (?array $event): bool => $event !== null)
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $pastEvents
     * @return array<int, array{url: string, source: string}>
     */
    private function buildCommunityPhotos(Profile $profile, array $pastEvents): array
    {
        $photos = collect();

        $profilePhoto = $profile->communityProfile?->profile_photo;
        if (is_string($profilePhoto) && $profilePhoto !== '') {
            $photos->push([
                'url' => $profilePhoto,
                'source' => 'profile_photo',
            ]);
        }

        foreach ($profile->galleryPhotos as $photo) {
            if (! is_string($photo->url) || $photo->url === '') {
                continue;
            }

            $photos->push([
                'url' => $photo->url,
                'source' => 'gallery',
            ]);
        }

        foreach ($pastEvents as $event) {
            foreach ($event['media'] ?? [] as $media) {
                if (! is_array($media)) {
                    continue;
                }

                $url = $media['type'] === 'image'
                    ? ($media['url'] ?? null)
                    : ($media['thumbnail_url'] ?? null);

                if (! is_string($url) || $url === '') {
                    continue;
                }

                $photos->push([
                    'url' => $url,
                    'source' => 'past_event',
                ]);
            }
        }

        return $photos
            ->unique('url')
            ->take(10)
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{id: string, url: string}>
     */
    private function buildCommunityGallery(Profile $profile): array
    {
        return $profile->galleryPhotos
            ->filter(fn ($photo): bool => is_string($photo->url) && $photo->url !== '')
            ->sortBy('sort_order')
            ->values()
            ->map(fn ($photo): array => [
                'id' => $photo->id,
                'url' => $photo->url,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildCommunityPastCollaborations(Profile $profile): array
    {
        return Collaboration::query()
            ->where('status', CollaborationStatus::Completed)
            ->where(function ($query) use ($profile): void {
                $query->where('creator_profile_id', $profile->id)
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
            ->limit(10)
            ->get()
            ->map(function (Collaboration $collaboration) use ($profile): array {
                $partner = $collaboration->creator_profile_id === $profile->id
                    ? $collaboration->applicantProfile
                    : $collaboration->creatorProfile;

                return [
                    'id' => $collaboration->id,
                    'title' => $collaboration->collabOpportunity?->title,
                    'partner_name' => $partner?->getExtendedProfile()?->name,
                    'partner_avatar_url' => $partner?->avatar_url,
                    'completed_at' => $collaboration->completed_at?->toIso8601String(),
                    'status' => $collaboration->status->value,
                ];
            })
            ->all();
    }

    /**
     * @return array<int, array{url: string, type: string, thumbnail_url: string|null, sort_order: int}>
     */
    private function normalizeMediaCollection(mixed $media): array
    {
        if (! is_array($media)) {
            return [];
        }

        $normalized = [];

        foreach (array_values($media) as $index => $item) {
            if (is_string($item) && filter_var($item, FILTER_VALIDATE_URL)) {
                $normalized[] = [
                    'url' => $item,
                    'type' => 'image',
                    'thumbnail_url' => null,
                    'sort_order' => $index,
                ];

                continue;
            }

            if (! is_array($item) || ! isset($item['url']) || ! is_string($item['url'])) {
                continue;
            }

            $type = isset($item['type']) && is_string($item['type']) && $item['type'] !== ''
                ? $item['type']
                : 'image';

            $normalized[] = [
                'url' => $item['url'],
                'type' => $type === 'photo' ? 'image' : $type,
                'thumbnail_url' => isset($item['thumbnail_url']) && is_string($item['thumbnail_url'])
                    ? $item['thumbnail_url']
                    : null,
                'sort_order' => isset($item['sort_order']) && is_numeric($item['sort_order'])
                    ? (int) $item['sort_order']
                    : $index,
            ];
        }

        return array_values($normalized);
    }
}
