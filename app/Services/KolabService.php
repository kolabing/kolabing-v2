<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\IntentType;
use App\Enums\KolabStatus;
use App\Enums\MissionTrigger;
use App\Enums\PointEventType;
use App\Exceptions\SubscriptionRequiredException;
use App\Models\Kolab;
use App\Models\KolabSuggestion;
use App\Models\PointLedger;
use App\Models\Profile;
use App\Services\Suggestions\SuggestionTelemetry;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class KolabService
{
    public function __construct(
        private readonly NotificationReminderService $notificationReminderService,
        private readonly GamificationWalletService $walletService,
        private readonly MissionService $missionService,
        private readonly NotificationService $notificationService,
        private readonly SuggestionTelemetry $suggestionTelemetry,
    ) {}

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
     *     saved?: string|bool,
     * }  $filters
     */
    public function browse(Profile $viewer, array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $savedOnly = filter_var($filters['saved'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $query = Kolab::query()
            ->where('status', KolabStatus::Published)
            ->withCount('applications')
            ->withExists([
                'savedByProfiles as is_saved' => function (Builder $q) use ($viewer): void {
                    $q->whereKey($viewer->id);
                },
                'applications as has_applied' => function (Builder $q) use ($viewer): void {
                    $q->where('applicant_profile_id', $viewer->id);
                },
            ])
            ->with([
                'creatorProfile' => function ($query) {
                    $query->with([
                        'events' => function ($q) {
                            $q->orderByDesc('event_date')->limit(5);
                        },
                        'events.photos' => function ($q) {
                            $q->orderBy('sort_order')->limit(10);
                        },
                        'galleryPhotos' => function ($q) {
                            $q->orderBy('sort_order')->limit(10);
                        },
                    ]);
                },
            ]);

        $this->applyRecipientVisibilityScope($query, $viewer);

        if ($savedOnly) {
            // The saved list returns exactly the viewer's saved kolabs — it does
            // NOT hide ones they've already applied to (they explicitly saved them).
            $query->whereHas('savedByProfiles', function (Builder $q) use ($viewer): void {
                $q->whereKey($viewer->id);
            });
        } else {
            $this->excludeAlreadyAppliedKolabs($query, $viewer);
            // Don't surface kolabs whose application dates are all in the past —
            // the applicant would hit a dead-end date picker ("No available
            // dates for this kolab"). Mirrors the apply-time guard in
            // ApplicationService. The saved list is left untouched: a saved
            // kolab that has since expired still shows so the user can see why.
            $query->withSelectableDates();
        }

        $this->applyFilters($query, $filters);

        return $query
            ->orderByDesc('published_at')
            ->paginate($perPage);
    }

    /**
     * Save (bookmark) a kolab for a profile. Idempotent — saving an
     * already-saved kolab is a no-op.
     */
    public function save(Profile $profile, Kolab $kolab): void
    {
        $profile->savedKolabs()->syncWithoutDetaching([$kolab->id]);
    }

    /**
     * Remove a kolab from a profile's saved list. Idempotent — unsaving a
     * kolab that was not saved is a no-op.
     */
    public function unsave(Profile $profile, Kolab $kolab): void
    {
        $profile->savedKolabs()->detach($kolab->id);
    }

    private function excludeAlreadyAppliedKolabs(Builder $query, Profile $viewer): void
    {
        $query->whereNotExists(function ($subQuery) use ($viewer): void {
            $subQuery->selectRaw('1')
                ->from('applications')
                ->whereColumn('applications.kolab_id', 'kolabs.id')
                ->where('applications.applicant_profile_id', $viewer->id);
        });
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
            ->withCount('applications')
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

        if ($data['intent_type'] === IntentType::CommunitySeeking->value) {
            $data = $this->enrichCommunitySeekingData($creator, $data);
        }

        $kolab = Kolab::query()->create([
            'creator_profile_id' => $creator->id,
            'intent_type' => $data['intent_type'],
            'status' => KolabStatus::Draft,
            'title' => $data['title'],
            'description' => $data['description'],
            'offer_headline' => $data['offer_headline'] ?? null,
            'base_offer' => $data['base_offer'] ?? null,
            'goal' => $data['goal'] ?? null,
            'highlights' => $data['highlights'] ?? null,
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

        $this->notificationReminderService->syncKolabDraftReminder($kolab);
        $this->markSuggestionConverted($creator, $kolab, $data);

        return $kolab;
    }

    /**
     * Close the suggestion funnel (BE-NF-28 §3.9): the row this Kolab came from
     * records which Kolab it produced, which is the only link between a card
     * being shown and a real collaboration existing.
     *
     * The viewer check repeats the `exists` scope in CreateKolabRequest on
     * purpose. That rule is what turns a stranger's id into a 422, but it is one
     * edit away from being weakened by someone who does not know it is
     * load-bearing, and without this second check the weakening would silently
     * mark another profile's row converted — retiring their suggestion and
     * crediting the wrong side of the funnel. One extra predicate on an indexed
     * primary-key lookup is a cheap price for that.
     *
     * `whereNull('converted_kolab_id')` makes the *first* conversion win, like
     * every other funnel marker on the row (`shown_at`, `clicked_at`,
     * `dismissed_at`): overwriting would lose which Kolab the suggestion
     * actually caused.
     *
     * Liveness is deliberately not required — see the rule's docblock: an
     * expired or dismissed row of the caller's own still converts, because a
     * stale card must never block Kolab creation.
     *
     * @param  array<string, mixed>  $data
     */
    private function markSuggestionConverted(Profile $creator, Kolab $kolab, array $data): void
    {
        $suggestionId = $data['suggestion_id'] ?? null;

        if (! is_string($suggestionId) || $suggestionId === '') {
            return;
        }

        $suggestion = KolabSuggestion::query()
            ->whereKey($suggestionId)
            ->where('viewer_profile_id', $creator->id)
            ->whereNull('converted_kolab_id')
            ->first();

        if ($suggestion === null) {
            return;
        }

        $suggestion->forceFill(['converted_kolab_id' => $kolab->id])->save();

        $this->suggestionTelemetry->converted($creator, $suggestion, $kolab);
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

        if ($intentType === IntentType::CommunitySeeking->value) {
            $data = $this->enrichCommunitySeekingData($kolab->creatorProfile, $data);
        }

        $kolab->update($data);
        $kolab->refresh();
        $this->notificationReminderService->syncKolabDraftReminder($kolab);

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

        $this->notificationReminderService->cancelKolabDraftReminder($kolab);
        $kolab->delete();
    }

    /**
     * Publish a kolab. Only draft kolabs can be published.
     * Community seeking intent is free; other intents require subscription.
     *
     * @param  array{recipient_community_id?: string|null}  $data
     *
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

        if ($creator->isBusiness()
            && $kolab->intent_type !== IntentType::CommunitySeeking
            && ! $creator->hasActiveSubscription()
            && $creator->hasUsedFreeKolab()) {
            throw new SubscriptionRequiredException(
                'A subscription is required to publish this type of kolab.'
            );
        }

        $this->assertMediaUrlsAreValid($kolab->media);

        $recipientCommunityId = $this->resolveRecipientCommunityId($kolab, $data);

        $kolab->update([
            'recipient_community_id' => $recipientCommunityId,
            'status' => KolabStatus::Published,
            'published_at' => Carbon::now(),
        ]);

        $kolab->refresh();
        $this->notificationReminderService->syncKolabDraftReminder($kolab);
        $this->awardPublishXpOnce($kolab);
        $this->recordPublishMissions($kolab, $creator);

        if ($creator->isBusiness()) {
            $this->notificationService->notifyKolabPublished($kolab);
        }

        return $kolab;
    }

    /**
     * Fire mission progress when a kolab is published. Always fires
     * kolab_published; additionally fires the by-type triggers we can map
     * cleanly from the real schema:
     *  - product_promotion intent  -> kolab_created_product
     *  - recurring availability    -> recurring_kolab_created (business creator)
     *                                 / recurring_kolab_hosted (community creator)
     * Fully guarded so a mission failure never blocks publishing. The remaining
     * by-type triggers (content/review/revenue, giveaway) have no stable source
     * field in the schema and stay inert (Phase 3 report).
     */
    private function recordPublishMissions(Kolab $kolab, Profile $creator): void
    {
        $triggers = [MissionTrigger::KolabPublished];

        if ($kolab->intent_type === IntentType::ProductPromotion) {
            $triggers[] = MissionTrigger::KolabCreatedProduct;
        }

        if ($this->isRecurringKolab($kolab)) {
            $triggers[] = $creator->isCommunity()
                ? MissionTrigger::RecurringKolabHosted
                : MissionTrigger::RecurringKolabCreated;
        }

        foreach ($triggers as $trigger) {
            $this->missionService->recordSafely($creator, $trigger, 1, ['reference_id' => $kolab->id]);
        }
    }

    /**
     * A kolab is recurring when its availability mode is "recurring" or it
     * carries recurring_days. Both come straight from the create payload.
     */
    private function isRecurringKolab(Kolab $kolab): bool
    {
        return $kolab->availability_mode === 'recurring'
            || (is_array($kolab->recurring_days) && $kolab->recurring_days !== []);
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
        $this->notificationReminderService->syncKolabDraftReminder($kolab);

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
     *     venue_mode?: string,
     *     product_type?: string,
     *     categories?: array<string>|string,
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

        if (isset($filters['venue_mode']) && $filters['venue_mode'] !== '') {
            $venuePreference = match ($filters['venue_mode']) {
                'business_venue' => 'business_provides',
                'community_venue' => 'community_provides',
                default => 'no_venue',
            };
            $query->where('venue_preference', $venuePreference);
        }

        if (isset($filters['product_type']) && $filters['product_type'] !== '') {
            $query->where('product_type', $filters['product_type']);
        }

        if (isset($filters['categories']) && ! empty($filters['categories'])) {
            $categories = is_array($filters['categories'])
                ? $filters['categories']
                : [$filters['categories']];

            $query->where(function (Builder $q) use ($categories) {
                foreach ($categories as $category) {
                    $q->orWhereJsonContains('community_types', $category);
                }
            });
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

                $q->orWhereHas('creatorProfile.businessProfile', function (Builder $bq) use ($searchTerm, $likeOperator) {
                    if ($likeOperator === 'ilike') {
                        $bq->where('name', 'ilike', $searchTerm);
                    } else {
                        $bq->whereRaw('LOWER(name) LIKE ?', [$searchTerm]);
                    }
                });

                $q->orWhereHas('creatorProfile.communityProfile', function (Builder $cq) use ($searchTerm, $likeOperator) {
                    if ($likeOperator === 'ilike') {
                        $cq->where('name', 'ilike', $searchTerm);
                    } else {
                        $cq->whereRaw('LOWER(name) LIKE ?', [$searchTerm]);
                    }
                });
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

    private function awardPublishXpOnce(Kolab $kolab): void
    {
        $alreadyAwarded = PointLedger::query()
            ->where('profile_id', $kolab->creator_profile_id)
            ->where('event_type', PointEventType::KolabPublished)
            ->where('reference_id', $kolab->id)
            ->exists();

        if ($alreadyAwarded) {
            return;
        }

        $this->walletService->awardPoints(
            $kolab->creator_profile_id,
            PointEventType::KolabPublished,
            $kolab->id,
            'Kolab published'
        );
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
     * Inherit the community's self-describing fields (type + size) from its
     * profile so they are NOT re-asked on every kolab — they already live on the
     * community_profile (set at onboarding). typical_attendance is intentionally
     * NOT inherited: it varies per kolab and stays a per-kolab input.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function enrichCommunitySeekingData(Profile $creator, array $data): array
    {
        $creator->loadMissing('communityProfile');
        $profile = $creator->communityProfile;

        if ($profile === null) {
            return $data;
        }

        if (empty($data['community_types']) && ! empty($profile->community_type)) {
            $data['community_types'] = [$profile->community_type];
        }

        if (empty($data['community_size']) && $profile->community_size !== null) {
            $data['community_size'] = $profile->community_size;
        }

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
     * Ensure any stored media URLs are present and absolute before publishing.
     *
     * Media is optional for kolabs, so an empty collection is allowed. When
     * media is present, each item must carry a valid absolute URL. The message
     * deliberately avoids the word "subscription" so the controller does not
     * mis-map it to a 402 paywall response.
     *
     * @param  array<int, array<string, mixed>>|null  $media
     *
     * @throws InvalidArgumentException
     */
    private function assertMediaUrlsAreValid(?array $media): void
    {
        if (empty($media)) {
            return;
        }

        foreach ($media as $item) {
            $url = is_array($item) ? ($item['url'] ?? null) : $item;

            if (! is_string($url) || $url === '' || ! filter_var($url, FILTER_VALIDATE_URL)) {
                throw new InvalidArgumentException(
                    'This kolab has an invalid or missing image URL. Please re-upload the media before publishing.'
                );
            }
        }
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
