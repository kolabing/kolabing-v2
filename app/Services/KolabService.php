<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\IntentType;
use App\Enums\KolabStatus;
use App\Models\Kolab;
use App\Models\Profile;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class KolabService
{
    /**
     * Browse published kolabs with filters.
     *
     * @param  array{
     *     intent_type?: string,
     *     city?: string,
     *     venue_type?: string,
     *     product_type?: string,
     *     needs?: array<string>,
     *     community_types?: array<string>,
     *     search?: string,
     * }  $filters
     */
    public function browse(Profile $viewer, array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = Kolab::query()
            ->where('status', KolabStatus::Published)
            ->with('creatorProfile');

        $this->applyRecipientVisibilityScope($query, $viewer);
        $this->applyFilters($query, $filters);

        return $query
            ->orderByDesc('published_at')
            ->paginate($perPage);
    }

    /**
     * Get kolabs created by a profile.
     *
     * @param  array{status?: string}  $filters
     */
    public function getMyKolabs(Profile $profile, array $filters, int $perPage = 10): LengthAwarePaginator
    {
        $query = Kolab::query()
            ->where('creator_profile_id', $profile->id)
            ->with('creatorProfile');

        if (isset($filters['status']) && $filters['status'] !== '') {
            $status = KolabStatus::tryFrom($filters['status']);
            if ($status !== null) {
                $query->where('status', $status);
            }
        }

        return $query
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    /**
     * Create a new kolab in draft status.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(Profile $creator, array $data): Kolab
    {
        $data = $this->normalizeKolabPayload($data);
        $data = $this->applyCommunitySeekingDefaults($data);
        $data = $this->normalizeOfferContract($data, $data['intent_type']);

        if ($data['intent_type'] === IntentType::VenuePromotion->value) {
            $data = $this->enrichVenuePromotionData($creator, $data);
        }

        return Kolab::query()->create([
            'creator_profile_id' => $creator->id,
            'intent_type' => $data['intent_type'],
            'status' => KolabStatus::Draft,
            'title' => $data['title'],
            'description' => $data['description'],
            'offer_headline' => $data['offer_headline'] ?? null,
            'base_offer' => $data['base_offer'] ?? null,
            'negotiation_triggers' => $data['negotiation_triggers'] ?? [],
            'preferred_city' => $data['preferred_city'],
            'area' => $data['area'] ?? null,
            'media' => $data['media'] ?? null,
            'availability_mode' => $data['availability_mode'] ?? null,
            'availability_start' => $data['availability_start'] ?? null,
            'availability_end' => $data['availability_end'] ?? null,
            'selected_time' => $data['selected_time'] ?? null,
            'recurring_days' => $data['recurring_days'] ?? null,
            'needs' => $data['needs'] ?? null,
            'community_types' => $data['community_types'] ?? null,
            'community_size' => $data['community_size'] ?? null,
            'typical_attendance' => $data['typical_attendance'] ?? null,
            'offers_in_return' => $data['offers_in_return'] ?? null,
            'venue_preference' => $data['venue_preference'] ?? null,
            'venue_name' => $data['venue_name'] ?? null,
            'venue_type' => $data['venue_type'] ?? null,
            'capacity' => $data['capacity'] ?? null,
            'venue_address' => $data['venue_address'] ?? null,
            'product_name' => $data['product_name'] ?? null,
            'product_type' => $data['product_type'] ?? null,
            'offering' => $data['offering'] ?? null,
            'seeking_communities' => $data['seeking_communities'] ?? null,
            'min_community_size' => $data['min_community_size'] ?? null,
            'expects' => $data['expects'] ?? null,
            'past_events' => $data['past_events'] ?? null,
        ]);
    }

    /**
     * Update an existing kolab.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Kolab $kolab, array $data): Kolab
    {
        $data = $this->normalizeKolabPayload($data);

        $intentType = $data['intent_type'] ?? $kolab->intent_type->value;
        $data = $this->applyCommunitySeekingDefaults($data, $intentType, $kolab->venue_preference);
        $data = $this->normalizeOfferContract($data, $intentType, $kolab);

        if ($intentType === IntentType::VenuePromotion->value) {
            $data = $this->enrichVenuePromotionData($kolab->creatorProfile, $data);
        }

        $kolab->update($data);
        $kolab->refresh();

        return $kolab;
    }

    /**
     * Delete a kolab. Only draft kolabs can be deleted.
     *
     * @throws InvalidArgumentException
     */
    public function delete(Kolab $kolab): void
    {
        if (! $kolab->isDraft()) {
            throw new InvalidArgumentException(
                'Only draft kolabs can be deleted.'
            );
        }

        $kolab->delete();
    }

    /**
     * Publish a kolab. Only draft kolabs can be published.
     * Community seeking intent is free; other intents require subscription.
     *
     * @param  array{recipient_community_id?: string|null}  $data
     * @throws InvalidArgumentException
     */
    public function publish(Kolab $kolab, array $data = []): Kolab
    {
        if (! $kolab->isDraft()) {
            throw new InvalidArgumentException(
                'Only draft kolabs can be published.'
            );
        }

        $creator = $kolab->creatorProfile;

        if ($kolab->intent_type !== IntentType::CommunitySeeking
            && ! $creator->hasActiveSubscription()
            && $creator->hasUsedFreeKolab()) {
            throw new InvalidArgumentException(
                'A subscription is required to publish this type of kolab.'
            );
        }

        $recipientCommunityId = $this->resolveRecipientCommunityId($kolab, $data);

        $kolab->update([
            'recipient_community_id' => $recipientCommunityId,
            'status' => KolabStatus::Published,
            'published_at' => Carbon::now(),
        ]);

        $kolab->refresh();

        return $kolab;
    }

    /**
     * Close a published kolab.
     *
     * @throws InvalidArgumentException
     */
    public function close(Kolab $kolab): Kolab
    {
        if (! $kolab->isPublished()) {
            throw new InvalidArgumentException(
                'Only published kolabs can be closed.'
            );
        }

        $kolab->update([
            'status' => KolabStatus::Closed,
        ]);

        $kolab->refresh();

        return $kolab;
    }

    /**
     * Apply filters to the kolab query.
     *
     * @param  Builder<Kolab>  $query
     * @param  array{
     *     intent_type?: string,
     *     city?: string,
     *     venue_type?: string,
     *     product_type?: string,
     *     needs?: array<string>,
     *     community_types?: array<string>,
     *     search?: string,
     * }  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        if (isset($filters['intent_type']) && $filters['intent_type'] !== '') {
            $intentType = IntentType::tryFrom($filters['intent_type']);
            if ($intentType !== null) {
                $query->where('intent_type', $intentType);
            }
        }

        if (isset($filters['city']) && $filters['city'] !== '') {
            $query->where('preferred_city', $filters['city']);
        }

        if (isset($filters['venue_type']) && $filters['venue_type'] !== '') {
            $query->where('venue_type', $filters['venue_type']);
        }

        if (isset($filters['product_type']) && $filters['product_type'] !== '') {
            $query->where('product_type', $filters['product_type']);
        }

        if (isset($filters['needs']) && ! empty($filters['needs'])) {
            $query->where(function (Builder $q) use ($filters) {
                foreach ($filters['needs'] as $need) {
                    $q->orWhereJsonContains('needs', $need);
                }
            });
        }

        if (isset($filters['community_types']) && ! empty($filters['community_types'])) {
            $query->where(function (Builder $q) use ($filters) {
                foreach ($filters['community_types'] as $communityType) {
                    $q->orWhereJsonContains('community_types', $communityType);
                }
            });
        }

        if (isset($filters['search']) && $filters['search'] !== '') {
            $searchTerm = '%'.strtolower($filters['search']).'%';
            $likeOperator = $this->getCaseInsensitiveLikeOperator();

            $query->where(function (Builder $q) use ($searchTerm, $likeOperator) {
                if ($likeOperator === 'ilike') {
                    $q->where('kolabs.title', 'ilike', $searchTerm)
                        ->orWhere('kolabs.description', 'ilike', $searchTerm);
                } else {
                    $q->whereRaw('LOWER(kolabs.title) LIKE ?', [$searchTerm])
                        ->orWhereRaw('LOWER(kolabs.description) LIKE ?', [$searchTerm]);
                }
            });
        }
    }

    /**
     * @param  Builder<Kolab>  $query
     */
    private function applyRecipientVisibilityScope(Builder $query, Profile $viewer): void
    {
        $query->where(function (Builder $visibilityQuery) use ($viewer): void {
            $visibilityQuery->whereNull('recipient_community_id')
                ->orWhere('recipient_community_id', $viewer->id);
        });
    }

    /**
     * Get the case-insensitive LIKE operator based on database driver.
     */
    private function getCaseInsensitiveLikeOperator(): string
    {
        $driver = DB::connection()->getDriverName();

        return $driver === 'pgsql' ? 'ilike' : 'like';
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function enrichVenuePromotionData(Profile $creator, array $data): array
    {
        $creator->loadMissing('businessProfile');

        $primaryVenue = $creator->businessProfile?->primary_venue;

        if (! is_array($primaryVenue) || empty($primaryVenue)) {
            throw new InvalidArgumentException(
                'A primary venue profile is required before creating a venue promotion kolab.'
            );
        }

        $data['preferred_city'] = $data['preferred_city'] ?? $primaryVenue['city'] ?? null;
        $data['venue_name'] = $primaryVenue['name'] ?? null;
        $data['venue_type'] = $primaryVenue['venue_type'] ?? null;
        $data['capacity'] = $primaryVenue['capacity'] ?? null;
        $data['venue_address'] = $primaryVenue['formatted_address'] ?? null;

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeKolabPayload(array $data): array
    {
        if (array_key_exists('media', $data)) {
            $data['media'] = $this->normalizeMediaCollection($data['media']);
        }

        if (array_key_exists('past_events', $data)) {
            $data['past_events'] = $this->normalizePastEvents($data['past_events']);
        }

        if (array_key_exists('offer_headline', $data) && is_string($data['offer_headline'])) {
            $data['offer_headline'] = trim($data['offer_headline']);
        }

        if (array_key_exists('base_offer', $data) && is_string($data['base_offer'])) {
            $data['base_offer'] = trim($data['base_offer']);
        }

        if (array_key_exists('negotiation_triggers', $data)) {
            $data['negotiation_triggers'] = $this->normalizeNegotiationTriggers($data['negotiation_triggers']);
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function applyCommunitySeekingDefaults(array $data, ?string $intentType = null, ?string $currentVenuePreference = null): array
    {
        $resolvedIntentType = $intentType ?? $data['intent_type'] ?? null;

        if ($resolvedIntentType !== IntentType::CommunitySeeking->value) {
            return $data;
        }

        $venuePreference = $data['venue_preference'] ?? $currentVenuePreference;

        if (is_string($venuePreference) && $venuePreference !== '') {
            $data['venue_preference'] = $venuePreference;

            return $data;
        }

        $data['venue_preference'] = 'no_venue';

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeOfferContract(array $data, string $intentType, ?Kolab $existingKolab = null): array
    {
        if ($intentType === IntentType::CommunitySeeking->value) {
            $data['offer_headline'] = null;
            $data['base_offer'] = null;
            $data['negotiation_triggers'] = [];

            return $data;
        }

        $description = isset($data['description']) && is_string($data['description']) && trim($data['description']) !== ''
            ? trim($data['description'])
            : trim((string) $existingKolab?->description);

        $existingHeadline = is_string($existingKolab?->offer_headline) && $existingKolab->offer_headline !== ''
            ? $existingKolab->offer_headline
            : null;

        $existingBaseOffer = is_string($existingKolab?->base_offer) && $existingKolab->base_offer !== ''
            ? $existingKolab->base_offer
            : null;

        if (! array_key_exists('offer_headline', $data) || ! is_string($data['offer_headline']) || $data['offer_headline'] === '') {
            $data['offer_headline'] = $existingHeadline ?? ($description !== '' ? Str::limit($description, 50, '') : null);
        }

        if (! array_key_exists('base_offer', $data) || ! is_string($data['base_offer']) || $data['base_offer'] === '') {
            $data['base_offer'] = $existingBaseOffer ?? ($description !== '' ? $description : null);
        }

        if (! array_key_exists('negotiation_triggers', $data) && $existingKolab === null) {
            $data['negotiation_triggers'] = [];
        }

        return $data;
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

            $normalized[] = [
                'url' => $item['url'],
                'type' => $this->normalizeMediaType($item['type'] ?? null),
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

    private function normalizeMediaType(mixed $type): string
    {
        if (! is_string($type) || $type === '') {
            return 'image';
        }

        return $type === 'photo' ? 'image' : $type;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function normalizePastEvents(mixed $pastEvents): array
    {
        if (! is_array($pastEvents)) {
            return [];
        }

        $normalized = [];

        foreach ($pastEvents as $event) {
            if (! is_array($event)) {
                continue;
            }

            $photos = $event['photos'] ?? [];
            unset($event['photos']);

            $event['media'] = $this->normalizeMediaCollection($event['media'] ?? $photos);
            $normalized[] = $event;
        }

        return array_values($normalized);
    }

    /**
     * @return array<int, array{condition: string, additional_offer: string}>
     */
    private function normalizeNegotiationTriggers(mixed $triggers): array
    {
        if (! is_array($triggers)) {
            return [];
        }

        $normalized = [];

        foreach ($triggers as $trigger) {
            if (! is_array($trigger)) {
                continue;
            }

            $condition = isset($trigger['condition']) && is_string($trigger['condition'])
                ? trim($trigger['condition'])
                : '';
            $additionalOffer = isset($trigger['additional_offer']) && is_string($trigger['additional_offer'])
                ? trim($trigger['additional_offer'])
                : '';

            if ($condition === '' || $additionalOffer === '') {
                continue;
            }

            $normalized[] = [
                'condition' => $condition,
                'additional_offer' => $additionalOffer,
            ];
        }

        return array_values($normalized);
    }

    /**
     * @param  array{recipient_community_id?: string|null}  $data
     */
    private function resolveRecipientCommunityId(Kolab $kolab, array $data): ?string
    {
        $recipientCommunityId = $data['recipient_community_id'] ?? null;
        if (! is_string($recipientCommunityId) || $recipientCommunityId === '') {
            return null;
        }

        $creator = $kolab->creatorProfile;
        if (! $creator->isBusiness() || $kolab->intent_type === IntentType::CommunitySeeking) {
            throw new InvalidArgumentException(
                'Direct community proposals are only available for business venue and product kolabs.'
            );
        }

        if (! $creator->hasActiveSubscription()) {
            throw new InvalidArgumentException(
                'An active subscription is required to send a direct community proposal.'
            );
        }

        $recipient = Profile::query()->find($recipientCommunityId);
        if ($recipient === null || ! $recipient->isCommunity()) {
            throw new InvalidArgumentException(
                'The recipient community must reference a community profile.'
            );
        }

        return $recipient->id;
    }
}
