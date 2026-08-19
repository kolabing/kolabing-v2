<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CollaborationStatus;
use App\Enums\IntentType;
use App\Enums\KolabStatus;
use App\Enums\MultiKolabEligibleAccountType;
use App\Enums\MultiKolabEventStatus;
use App\Enums\MultiKolabRoleStatus;
use App\Enums\UserType;
use App\Models\Collaboration;
use App\Models\Kolab;
use App\Models\MultiKolabEvent;
use App\Models\MultiKolabRole;
use App\Models\Profile;
use Illuminate\Contracts\Pagination\LengthAwarePaginator as LengthAwarePaginatorContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class DiscoveryOpportunityService
{
    /**
     * @var array<string, array<int, string>>
     */
    private const OFFER_TYPE_ALIASES = [
        'venue' => ['venue', 'venue_space'],
        'food_drink' => ['food_drink', 'free_drinks'],
        'discount' => ['discount', 'discount_code'],
        'products' => ['products', 'free_samples'],
        'social_media' => ['social_media'],
        'content_creation' => ['content_creation'],
        'sponsorship' => ['sponsorship'],
        'other' => ['other', 'commission'],
    ];

    /**
     * @var array<int, string>
     */
    private const DELIVERABLE_TYPES = [
        'social_media',
        'event_activation',
        'product_placement',
        'community_reach',
        'review_feedback',
    ];

    /**
     * @var array<int, string>
     */
    private const NEED_TYPES = [
        'venue',
        'food_drink',
        'sponsor',
        'products',
        'discount',
        'other',
    ];

    /**
     * @var array<int, array{key: string, label: string, weight: float}>
     */
    private const MATCH_SIGNALS = [
        ['key' => 'category_fit', 'label' => 'Category fit', 'weight' => 0.45],
        ['key' => 'location', 'label' => 'Location', 'weight' => 0.20],
        ['key' => 'value_fit', 'label' => 'Value fit', 'weight' => 0.20],
        ['key' => 'past_activity', 'label' => 'Past activity', 'weight' => 0.15],
    ];

    /**
     * @var array<string, array<string, float>>
     */
    private const COMMUNITY_BUSINESS_CATEGORY_SCORES = [
        'food_community' => [
            'cafe' => 1.0,
            'restaurant' => 0.98,
            'food_truck' => 0.95,
            'bakery' => 0.9,
            'bar' => 0.72,
            'bar_lounge' => 0.72,
            'beverage' => 0.88,
            'food_product' => 0.86,
            'coworking' => 0.22,
        ],
        'run_club' => [
            'sports_facility' => 1.0,
            'gym' => 0.96,
            'cafe' => 0.87,
            'restaurant' => 0.7,
            'hotel' => 0.55,
            'retail' => 0.42,
        ],
        'fitness_community' => [
            'sports_facility' => 1.0,
            'gym' => 0.96,
            'cafe' => 0.82,
            'restaurant' => 0.68,
            'health_beauty' => 0.75,
        ],
        'wellness_community' => [
            'health_beauty' => 0.95,
            'salon' => 0.92,
            'cafe' => 0.78,
            'hotel' => 0.74,
            'gym' => 0.72,
        ],
        'tech_startup_community' => [
            'coworking' => 1.0,
            'hotel' => 0.76,
            'cafe' => 0.7,
            'tech_gadget' => 0.85,
        ],
        'professional_networking_community' => [
            'coworking' => 0.98,
            'hotel' => 0.82,
            'cafe' => 0.74,
        ],
        'student_community' => [
            'coworking' => 0.84,
            'cafe' => 0.8,
            'restaurant' => 0.72,
            'retail' => 0.66,
        ],
    ];

    /**
     * @return array{
     *     paginator: LengthAwarePaginatorContract,
     *     meta: array<string, mixed>
     * }
     */
    public function discover(Profile $viewer, array $filters, int $perPage = 15): array
    {
        $viewerRole = $this->resolveViewerRole($viewer);
        $viewer->loadMissing([
            'businessProfile',
            'communityProfile.city',
        ]);

        $normalizedFilters = $this->normalizeFilters($filters, $perPage);

        $baseQuery = $this->makeBaseQuery($viewer, $viewerRole);
        $hasPublishedResults = (clone $baseQuery)->exists();

        $query = $this->makeBaseQuery($viewer, $viewerRole);

        $this->applyCommonFilters($query, $normalizedFilters);
        $this->applyRoleAwareFilters($query, $normalizedFilters, $viewerRole);
        $scoredResults = $query
            ->get()
            ->map(function (Kolab $kolab) use ($viewer, $normalizedFilters, $viewerRole): Kolab {
                $matchPayload = $this->buildMatchPayload($kolab, $viewer, $normalizedFilters, $viewerRole);

                $kolab->setAttribute('discovery_match_score', $matchPayload['score']);
                $kolab->setAttribute('discovery_match_breakdown', $matchPayload['breakdown']);
                $kolab->setAttribute('discovery_business_offer', $this->buildBusinessOfferBlock($kolab));
                $kolab->setAttribute('discovery_community_request', $this->buildCommunityRequestBlock($kolab));
                $kolab->setAttribute('discovery_match', $matchPayload);

                return $kolab;
            });
        $scoredResults = $this->enrichDiscoveryCardMetadata($scoredResults);

        $kolabItems = $scoredResults->map(fn (Kolab $kolab): array => [
            'item_type' => 'kolab',
            'model' => $kolab,
            'score' => (int) ($kolab->getAttribute('discovery_match_score') ?? 0),
            'timestamp' => $kolab->published_at?->timestamp ?? 0,
            'sort_date' => ($kolab->availability_end ?? $kolab->availability_start)?->toDateString(),
        ]);

        $multiKolabBaseQuery = $this->makeMultiKolabRoleBaseQuery($viewer, $viewerRole);
        $hasPublishedResults = $hasPublishedResults || (clone $multiKolabBaseQuery)->exists();

        $multiKolabQuery = $this->makeMultiKolabRoleBaseQuery($viewer, $viewerRole);
        $this->applyMultiKolabRoleFilters($multiKolabQuery, $normalizedFilters);
        $multiKolabItems = $this->buildMultiKolabRoleItems($multiKolabQuery, $viewer, $normalizedFilters);

        $combinedItems = $kolabItems->concat($multiKolabItems);

        $sortedResults = $this->sortCombinedItems($combinedItems, $normalizedFilters['sort'], $normalizedFilters['feed']);
        $currentPage = $normalizedFilters['page'];
        $total = $sortedResults->count();
        $pageItems = $sortedResults
            ->forPage($currentPage, $normalizedFilters['per_page'])
            ->values();

        $paginator = new LengthAwarePaginator(
            $pageItems,
            $total,
            $normalizedFilters['per_page'],
            $currentPage,
            [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
                'pageName' => 'page',
            ]
        );

        return [
            'paginator' => $paginator,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'feed' => $normalizedFilters['feed'],
                'viewer_role' => $viewerRole,
                'applied_filters' => $this->buildAppliedFilters($normalizedFilters),
                'empty_reason' => $this->resolveEmptyReason(
                    $paginator,
                    $hasPublishedResults,
                    $normalizedFilters['feed']
                ),
            ],
        ];
    }

    private function resolveViewerRole(Profile $viewer): string
    {
        return match ($viewer->user_type) {
            UserType::Business => 'business',
            UserType::Community => 'community',
            default => throw new InvalidArgumentException(
                'Discovery is only available for business and community profiles.'
            ),
        };
    }

    /**
     * @param  Collection<int, Kolab>  $results
     * @return Collection<int, Kolab>
     */
    private function enrichDiscoveryCardMetadata(Collection $results): Collection
    {
        $businessCreatorIds = $results
            ->filter(fn (Kolab $kolab): bool => $kolab->creatorProfile?->isBusiness() ?? false)
            ->pluck('creator_profile_id')
            ->unique()
            ->values()
            ->all();

        $activityByCreator = $this->buildBusinessActivityMetadata($businessCreatorIds);

        return $results->map(function (Kolab $kolab) use ($activityByCreator): Kolab {
            $kolab->setAttribute('discovery_area', $this->resolveDiscoveryArea($kolab));

            if (! ($kolab->creatorProfile?->isBusiness() ?? false)) {
                $kolab->setAttribute('discovery_past_events_count', null);
                $kolab->setAttribute('discovery_active_this_month', null);
                $kolab->setAttribute('discovery_active_this_month_label', null);

                return $kolab;
            }

            $activity = $activityByCreator[$kolab->creator_profile_id] ?? [
                'past_events_count' => null,
                'active_this_month' => null,
                'active_this_month_label' => null,
            ];

            $kolab->setAttribute('discovery_past_events_count', $activity['past_events_count']);
            $kolab->setAttribute('discovery_active_this_month', $activity['active_this_month']);
            $kolab->setAttribute('discovery_active_this_month_label', $activity['active_this_month_label']);

            return $kolab;
        });
    }

    /**
     * @param  array<int, string>  $businessCreatorIds
     * @return array<string, array{
     *     past_events_count: int|null,
     *     active_this_month: bool|null,
     *     active_this_month_label: string|null
     * }>
     */
    private function buildBusinessActivityMetadata(array $businessCreatorIds): array
    {
        if ($businessCreatorIds === []) {
            return [];
        }

        $monthStart = Carbon::now()->startOfMonth();
        $monthEnd = Carbon::now()->endOfMonth();
        $activityByCreator = [];

        foreach ($businessCreatorIds as $businessCreatorId) {
            $activityByCreator[$businessCreatorId] = [
                'past_events_count' => 0,
                'active_this_month' => false,
                'active_this_month_label' => null,
            ];
        }

        $creatorKolabs = Kolab::query()
            ->whereIn('creator_profile_id', $businessCreatorIds)
            ->whereIn('status', [KolabStatus::Published, KolabStatus::Closed])
            ->get([
                'creator_profile_id',
                'published_at',
                'past_events',
            ]);

        foreach ($creatorKolabs as $creatorKolab) {
            $creatorId = $creatorKolab->creator_profile_id;

            if (! array_key_exists($creatorId, $activityByCreator)) {
                continue;
            }

            if ($creatorKolab->published_at?->betweenIncluded($monthStart, $monthEnd) ?? false) {
                $activityByCreator[$creatorId]['active_this_month'] = true;
            }

            $pastEvents = $this->normalizePastEventActivityPayload($creatorKolab->past_events);
            $activityByCreator[$creatorId]['past_events_count'] += count($pastEvents);

            foreach ($pastEvents as $pastEvent) {
                if ($this->isCurrentMonthDateString($pastEvent['date'] ?? null, $monthStart, $monthEnd)) {
                    $activityByCreator[$creatorId]['active_this_month'] = true;

                    break;
                }
            }
        }

        $recentCollaborations = Collaboration::query()
            ->where('status', CollaborationStatus::Completed)
            ->whereBetween('completed_at', [$monthStart, $monthEnd])
            ->where(function (Builder $query) use ($businessCreatorIds): void {
                $query->whereIn('creator_profile_id', $businessCreatorIds)
                    ->orWhereIn('applicant_profile_id', $businessCreatorIds);
            })
            ->get([
                'creator_profile_id',
                'applicant_profile_id',
            ]);

        foreach ($recentCollaborations as $collaboration) {
            foreach ([$collaboration->creator_profile_id, $collaboration->applicant_profile_id] as $profileId) {
                if ($profileId !== null && array_key_exists($profileId, $activityByCreator)) {
                    $activityByCreator[$profileId]['active_this_month'] = true;
                }
            }
        }

        foreach ($activityByCreator as &$activity) {
            $pastEventsCount = $activity['past_events_count'];

            $activity['past_events_count'] = $pastEventsCount > 0 ? $pastEventsCount : null;
            $activity['active_this_month'] = $activity['active_this_month'] ? true : null;
            $activity['active_this_month_label'] = $activity['active_this_month'] ? 'Active this month' : null;
        }
        unset($activity);

        return $activityByCreator;
    }

    private function resolveDiscoveryArea(Kolab $kolab): ?string
    {
        $area = $this->sanitizeDiscoveryArea($kolab->area, $kolab->preferred_city);

        if ($area !== null) {
            return $area;
        }

        if (! ($kolab->creatorProfile?->isBusiness() ?? false)) {
            return null;
        }

        $primaryVenue = $kolab->creatorProfile?->businessProfile?->primary_venue;

        return is_array($primaryVenue)
            ? $this->resolveNeighborhoodFromPrimaryVenue($primaryVenue, $kolab->preferred_city)
            : null;
    }

    private function resolveNeighborhoodFromPrimaryVenue(array $primaryVenue, ?string $preferredCity): ?string
    {
        foreach (['neighborhood', 'neighbourhood', 'district', 'area', 'borough', 'sublocality', 'suburb'] as $key) {
            $value = $primaryVenue[$key] ?? null;

            if (! is_string($value) || $value === '') {
                continue;
            }

            $area = $this->sanitizeDiscoveryArea($value, $preferredCity);

            if ($area !== null) {
                return $area;
            }
        }

        return null;
    }

    private function sanitizeDiscoveryArea(?string $area, ?string $preferredCity): ?string
    {
        $normalizedArea = $this->normalizeNullableString($area);

        if ($normalizedArea === null) {
            return null;
        }

        $normalizedCity = $this->normalizeComparableLocationLabel($preferredCity);

        if ($normalizedCity === null) {
            return $normalizedArea;
        }

        $segments = preg_split('/\s*(?:,|\/|\|)\s*/', $normalizedArea) ?: [$normalizedArea];
        $keptSegments = [];

        foreach ($segments as $segment) {
            $trimmedSegment = trim($segment);

            if ($trimmedSegment === '') {
                continue;
            }

            $candidate = $this->normalizeComparableLocationLabel($trimmedSegment);

            if ($candidate === null || $candidate === $normalizedCity) {
                continue;
            }

            if (preg_match('/\b'.preg_quote($normalizedCity, '/').'\b/u', $candidate) === 1) {
                continue;
            }

            $keptSegments[] = $trimmedSegment;
        }

        if ($keptSegments !== []) {
            return implode(', ', $keptSegments);
        }

        $candidate = $this->normalizeComparableLocationLabel($normalizedArea);

        if ($candidate === null || $candidate === $normalizedCity) {
            return null;
        }

        if (preg_match('/\b'.preg_quote($normalizedCity, '/').'\b/u', $candidate) === 1) {
            return null;
        }

        return $normalizedArea;
    }

    private function normalizeComparableLocationLabel(?string $value): ?string
    {
        $normalizedValue = $this->normalizeNullableString($value);

        if ($normalizedValue === null) {
            return null;
        }

        return (string) Str::of(Str::lower($normalizedValue))
            ->ascii()
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->squish();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function normalizePastEventActivityPayload(mixed $pastEvents): array
    {
        if (! is_array($pastEvents)) {
            return [];
        }

        return array_values(array_filter($pastEvents, static fn (mixed $event): bool => is_array($event)));
    }

    private function isCurrentMonthDateString(mixed $value, Carbon $monthStart, Carbon $monthEnd): bool
    {
        if (! is_string($value) || $value === '') {
            return false;
        }

        try {
            return Carbon::parse($value)->betweenIncluded($monthStart, $monthEnd);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function normalizeFilters(array $filters, int $perPage): array
    {
        $feed = isset($filters['feed']) && $filters['feed'] === 'all' ? 'all' : 'recommended';
        $sort = isset($filters['sort']) && is_string($filters['sort']) && $filters['sort'] !== ''
            ? $filters['sort']
            : ($feed === 'recommended' ? 'recommended' : 'recent');

        return [
            'feed' => $feed,
            'sort' => $sort,
            'page' => max((int) ($filters['page'] ?? 1), 1),
            'per_page' => min(max($perPage, 1), 100),
            'search' => $this->normalizeNullableString($filters['search'] ?? null),
            'city' => $this->normalizeNullableString($filters['city'] ?? null),
            'availability_mode' => $this->normalizeNullableString($filters['availability_mode'] ?? null),
            'availability_from' => $this->normalizeNullableString($filters['availability_from'] ?? null),
            'availability_to' => $this->normalizeNullableString($filters['availability_to'] ?? null),
            'need_types' => $this->normalizeStringArray($filters['need_types'] ?? []),
            'community_types' => $this->normalizeStringArray($filters['community_types'] ?? []),
            'audience_size_band' => $this->normalizeNullableString($filters['audience_size_band'] ?? null),
            'offers_in_return' => $this->normalizeStringArray($filters['offers_in_return'] ?? []),
            'venue_preferences' => $this->normalizeStringArray($filters['venue_preferences'] ?? []),
            'intent_types' => $this->normalizeStringArray($filters['intent_types'] ?? []),
            'offer_types' => $this->normalizeStringArray($filters['offer_types'] ?? []),
            'venue_types' => $this->normalizeStringArray($filters['venue_types'] ?? []),
            'product_types' => $this->normalizeStringArray($filters['product_types'] ?? []),
            'expected_deliverables' => $this->normalizeStringArray($filters['expected_deliverables'] ?? []),
            'community_requirement_band' => $this->normalizeNullableString($filters['community_requirement_band'] ?? null),
        ];
    }

    private function makeBaseQuery(Profile $viewer, string $viewerRole): Builder
    {
        $query = Kolab::query()
            ->where('status', KolabStatus::Published)
            ->where('creator_profile_id', '!=', $viewer->id)
            // Canonical child Kolabs created by Multi-Kolab role acceptance
            // are internal partnership records, not ordinary open offers —
            // they surface through the typed multi_kolab_role projection
            // instead (Review item #7).
            ->whereNull('multi_kolab_event_id')
            ->with([
                'creatorProfile' => function ($query): void {
                    $query->select('id', 'user_type', 'avatar_url')
                        ->with([
                            'businessProfile:profile_id,name,business_type,categories,city_name,primary_venue',
                            'communityProfile:profile_id,name,community_type,city_id',
                        ]);
                },
            ]);

        $this->applyRecipientVisibilityScope($query, $viewer);
        $this->applyRoleScope($query, $viewerRole);
        $this->applyActiveAvailabilityFilter($query);
        $this->excludeAlreadyAppliedKolabs($query, $viewer);
        $this->excludeBlockedCreators($query, $viewer);

        return $query;
    }

    /**
     * UGC moderation (App Review Guideline 1.2): drop kolabs whose creator the
     * viewer has blocked, or who has blocked the viewer.
     */
    private function excludeBlockedCreators(Builder $query, Profile $viewer): void
    {
        $blockedIds = app(ModerationService::class)->blockedIds($viewer);

        if ($blockedIds === []) {
            return;
        }

        $query->whereNotIn('kolabs.creator_profile_id', $blockedIds);
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
     * @param  Builder<Kolab>  $query
     */
    private function applyRecipientVisibilityScope(Builder $query, Profile $viewer): void
    {
        $query->where(function (Builder $visibilityQuery) use ($viewer): void {
            $visibilityQuery->whereNull('recipient_community_id')
                ->orWhere('recipient_community_id', $viewer->id);
        });
    }

    private function applyRoleScope(Builder $query, string $viewerRole): void
    {
        if ($viewerRole === 'business') {
            $query->where('intent_type', IntentType::CommunitySeeking);

            return;
        }

        $query->whereIn('intent_type', [
            IntentType::VenuePromotion,
            IntentType::ProductPromotion,
        ]);
    }

    private function applyActiveAvailabilityFilter(Builder $query): void
    {
        $today = Carbon::today()->toDateString();

        $query->where(function (Builder $innerQuery) use ($today): void {
            $innerQuery
                ->where(function (Builder $noAvailabilityQuery): void {
                    $noAvailabilityQuery->whereNull('availability_start')
                        ->whereNull('availability_end');
                })
                ->orWhereRaw('COALESCE(availability_end, availability_start) >= ?', [$today]);
        });
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyCommonFilters(Builder $query, array $filters): void
    {
        if ($filters['search'] !== null) {
            $this->applySearchFilter($query, $filters['search']);
        }

        if ($filters['city'] !== null) {
            $query->where('preferred_city', $filters['city']);
        }

        if ($filters['availability_mode'] !== null) {
            $query->where('availability_mode', $filters['availability_mode']);
        }

        if ($filters['availability_from'] !== null) {
            $query->where(function (Builder $innerQuery) use ($filters): void {
                $innerQuery
                    ->where(function (Builder $noAvailabilityQuery): void {
                        $noAvailabilityQuery->whereNull('availability_start')
                            ->whereNull('availability_end');
                    })
                    ->orWhereRaw('COALESCE(availability_end, availability_start) >= ?', [$filters['availability_from']]);
            });
        }

        if ($filters['availability_to'] !== null) {
            $query->where(function (Builder $innerQuery) use ($filters): void {
                $innerQuery
                    ->where(function (Builder $noAvailabilityQuery): void {
                        $noAvailabilityQuery->whereNull('availability_start')
                            ->whereNull('availability_end');
                    })
                    ->orWhere(function (Builder $datedQuery) use ($filters): void {
                        $datedQuery->whereNotNull('availability_start')
                            ->where('availability_start', '<=', $filters['availability_to']);
                    });
            });
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyRoleAwareFilters(Builder $query, array $filters, string $viewerRole): void
    {
        if ($viewerRole === 'business') {
            if ($filters['need_types'] !== []) {
                $this->applyTextMatchFilter($query, 'kolabs.needs', $this->expandTerms($filters['need_types']));
            }

            if ($filters['community_types'] !== []) {
                $this->applyTextMatchFilter($query, 'kolabs.community_types', $this->expandTerms($filters['community_types']));
            }

            if ($filters['audience_size_band'] !== null) {
                $this->applyAudienceBandFilter($query, $filters['audience_size_band']);
            }

            if ($filters['offers_in_return'] !== []) {
                $this->applyTextMatchFilter($query, 'kolabs.offers_in_return', $this->expandTerms($filters['offers_in_return']));
            }

            if ($filters['venue_preferences'] !== []) {
                $query->whereIn('venue_preference', $filters['venue_preferences']);
            }

            return;
        }

        if ($filters['intent_types'] !== []) {
            $query->whereIn('intent_type', $filters['intent_types']);
        }

        if ($filters['offer_types'] !== []) {
            $this->applyTextMatchFilter($query, 'kolabs.offering', $this->expandOfferTypeTerms($filters['offer_types']));
        }

        if ($filters['venue_types'] !== []) {
            $query->whereIn('venue_type', $filters['venue_types']);
        }

        if ($filters['product_types'] !== []) {
            $query->whereIn('product_type', $filters['product_types']);
        }

        if ($filters['expected_deliverables'] !== []) {
            $this->applyTextMatchFilter($query, 'kolabs.expects', $this->expandTerms($filters['expected_deliverables']));
        }

        if ($filters['community_requirement_band'] !== null) {
            $this->applyCommunityRequirementBandFilter($query, $filters['community_requirement_band']);
        }
    }

    /**
     * @param  array<int, string>  $terms
     */
    private function applyTextMatchFilter(Builder $query, string $column, array $terms): void
    {
        [$expression, $bindings] = $this->textAnyContainsExpression($column, $terms);
        $query->whereRaw($expression, $bindings);
    }

    private function applyAudienceBandFilter(Builder $query, string $band): void
    {
        $query->where(function (Builder $innerQuery) use ($band): void {
            $this->applyNumericBandCondition($innerQuery, 'community_size', $band);
            $innerQuery->orWhere(function (Builder $attendanceQuery) use ($band): void {
                $this->applyNumericBandCondition($attendanceQuery, 'typical_attendance', $band);
            });
        });
    }

    private function applyCommunityRequirementBandFilter(Builder $query, string $band): void
    {
        if ($band === 'open') {
            $query->whereNull('min_community_size');

            return;
        }

        $query->whereNotNull('min_community_size');

        match ($band) {
            'small' => $query->where('min_community_size', '<', 100),
            'medium' => $query->whereBetween('min_community_size', [100, 499]),
            'large' => $query->whereBetween('min_community_size', [500, 1999]),
            'xlarge' => $query->where('min_community_size', '>=', 2000),
            default => null,
        };
    }

    private function applySearchFilter(Builder $query, string $search): void
    {
        $searchTerm = '%'.strtolower($search).'%';
        $likeOperator = $this->getCaseInsensitiveLikeOperator();

        $query->where(function (Builder $searchQuery) use ($searchTerm, $likeOperator): void {
            if ($likeOperator === 'ilike') {
                $searchQuery->where('kolabs.title', 'ilike', $searchTerm)
                    ->orWhere('kolabs.description', 'ilike', $searchTerm)
                    ->orWhere('kolabs.preferred_city', 'ilike', $searchTerm)
                    ->orWhereHas('creatorProfile.businessProfile', function (Builder $profileQuery) use ($searchTerm): void {
                        $profileQuery->where('name', 'ilike', $searchTerm);
                    })
                    ->orWhereHas('creatorProfile.communityProfile', function (Builder $profileQuery) use ($searchTerm): void {
                        $profileQuery->where('name', 'ilike', $searchTerm);
                    });

                return;
            }

            $searchQuery->whereRaw('LOWER(kolabs.title) LIKE ?', [$searchTerm])
                ->orWhereRaw('LOWER(kolabs.description) LIKE ?', [$searchTerm])
                ->orWhereRaw('LOWER(kolabs.preferred_city) LIKE ?', [$searchTerm])
                ->orWhereHas('creatorProfile.businessProfile', function (Builder $profileQuery) use ($searchTerm): void {
                    $profileQuery->whereRaw('LOWER(name) LIKE ?', [$searchTerm]);
                })
                ->orWhereHas('creatorProfile.communityProfile', function (Builder $profileQuery) use ($searchTerm): void {
                    $profileQuery->whereRaw('LOWER(name) LIKE ?', [$searchTerm]);
                });
        });
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{0: string, 1: array<int, mixed>}
     */
    private function buildScoreExpression(Profile $viewer, array $filters, string $viewerRole): array
    {
        $parts = [];
        $bindings = [];
        $viewerCity = $this->resolveViewerCity($viewer);

        [$freshnessExpression, $freshnessBindings] = $this->buildFreshnessScoreExpression();
        $parts[] = $freshnessExpression;
        array_push($bindings, ...$freshnessBindings);

        if ($viewerCity !== null) {
            $parts[] = 'CASE WHEN preferred_city = ? THEN 40 ELSE 0 END';
            $bindings[] = $viewerCity;
        }

        if ($viewerRole === 'business') {
            $capabilities = $this->inferBusinessCapabilities($viewer);
            if ($capabilities !== []) {
                [$expression, $expressionBindings] = $this->textAnyContainsExpression('kolabs.needs', $this->expandTerms($capabilities));
                $parts[] = "CASE WHEN {$expression} THEN 30 ELSE 0 END";
                array_push($bindings, ...$expressionBindings);
            }

            $desiredReturns = $filters['offers_in_return'] !== []
                ? $filters['offers_in_return']
                : $this->inferBusinessDesiredReturns($viewer);

            if ($desiredReturns !== []) {
                [$expression, $expressionBindings] = $this->textAnyContainsExpression('kolabs.offers_in_return', $this->expandTerms($desiredReturns));
                $parts[] = "CASE WHEN {$expression} THEN 15 ELSE 0 END";
                array_push($bindings, ...$expressionBindings);
            }

            $affinityTerms = $this->inferBusinessCommunityAffinityTerms($viewer, $filters['community_types']);
            if ($affinityTerms !== []) {
                [$expression, $expressionBindings] = $this->textAnyContainsExpression('kolabs.community_types', $affinityTerms);
                $parts[] = "CASE WHEN {$expression} THEN 20 ELSE 0 END";
                array_push($bindings, ...$expressionBindings);
            }

            if ($this->viewerHasUsableVenue($viewer)) {
                $parts[] = "CASE WHEN venue_preference IN ('business_provides', 'no_venue') THEN 10 ELSE 0 END";
            }

            $capacity = $this->resolveViewerVenueCapacity($viewer);
            if ($capacity !== null) {
                $parts[] = 'CASE WHEN COALESCE(typical_attendance, community_size, 0) > 0 AND COALESCE(typical_attendance, community_size, 0) <= ? THEN 10 ELSE 0 END';
                $bindings[] = $capacity;
            }
        } else {
            $communityAffinityTerms = $this->inferCommunityAffinityTerms($viewer);
            if ($communityAffinityTerms !== []) {
                [$expression, $expressionBindings] = $this->textAnyContainsExpression('kolabs.seeking_communities', $communityAffinityTerms);
                $parts[] = "CASE WHEN {$expression} THEN 30 ELSE 0 END";
                array_push($bindings, ...$expressionBindings);
            }

            $desiredOfferTypes = $filters['offer_types'] !== []
                ? $filters['offer_types']
                : $this->inferCommunityDesiredOfferTypes($viewer);

            if ($desiredOfferTypes !== []) {
                [$expression, $expressionBindings] = $this->textAnyContainsExpression('kolabs.offering', $this->expandOfferTypeTerms($desiredOfferTypes));
                $parts[] = "CASE WHEN {$expression} THEN 20 ELSE 0 END";
                array_push($bindings, ...$expressionBindings);
            }

            $desiredDeliverables = $filters['expected_deliverables'] !== []
                ? $filters['expected_deliverables']
                : ['social_media', 'community_reach', 'event_activation'];

            [$expression, $expressionBindings] = $this->textAnyContainsExpression('kolabs.expects', $this->expandTerms($desiredDeliverables));
            $parts[] = "CASE WHEN {$expression} THEN 15 ELSE 0 END";
            array_push($bindings, ...$expressionBindings);

            $preferredIntents = $filters['intent_types'] !== []
                ? $filters['intent_types']
                : $this->inferCommunityPreferredIntents($viewer);

            if ($preferredIntents !== []) {
                $placeholders = implode(', ', array_fill(0, count($preferredIntents), '?'));
                $parts[] = "CASE WHEN intent_type IN ({$placeholders}) THEN 10 ELSE 0 END";
                array_push($bindings, ...$preferredIntents);
            }
        }

        if ($parts === []) {
            return ['0', []];
        }

        return ['('.implode(' + ', $parts).')', $bindings];
    }

    /**
     * @return array{0: string, 1: array<int, string>}
     */
    private function buildFreshnessScoreExpression(): array
    {
        return [
            'CASE WHEN published_at >= ? THEN 10 WHEN published_at >= ? THEN 5 ELSE 0 END',
            [
                Carbon::now()->subDays(7)->toDateTimeString(),
                Carbon::now()->subDays(30)->toDateTimeString(),
            ],
        ];
    }

    private function applySorting(Builder $query, string $sort, string $feed): void
    {
        $resolvedSort = $sort !== '' ? $sort : ($feed === 'recommended' ? 'recommended' : 'recent');

        if ($resolvedSort === 'ending_soon') {
            $query
                ->orderByRaw('CASE WHEN COALESCE(availability_end, availability_start) IS NULL THEN 1 ELSE 0 END ASC')
                ->orderByRaw('COALESCE(availability_end, availability_start) ASC')
                ->orderByDesc('published_at');

            return;
        }

        if ($resolvedSort === 'recommended') {
            $query
                ->orderByDesc('discovery_match_score')
                ->orderByDesc('published_at');

            return;
        }

        $query->orderByDesc('published_at');
    }

    /**
     * Base query for Multi-Kolab roles eligible for the Explore feed: the
     * role must be `open` with remaining capacity, on a `recruiting` event,
     * matching the viewer's account type (or `either`), and never on an
     * event the viewer themselves organizes. Search/city are applied
     * separately by {@see applyMultiKolabRoleFilters()} (mirroring the
     * Kolab base-query/common-filter split above) so `hasPublishedResults`
     * can be checked before those filters narrow the result set.
     *
     * @return Builder<MultiKolabRole>
     */
    private function makeMultiKolabRoleBaseQuery(Profile $viewer, string $viewerRole): Builder
    {
        $eligibleTypes = $viewerRole === 'business'
            ? [MultiKolabEligibleAccountType::Business, MultiKolabEligibleAccountType::Either]
            : [MultiKolabEligibleAccountType::Community, MultiKolabEligibleAccountType::Either];

        return MultiKolabRole::query()
            ->where('status', MultiKolabRoleStatus::Open)
            ->whereColumn('positions_filled', '<', 'positions_needed')
            ->whereIn('eligible_account_type', $eligibleTypes)
            ->whereHas('event', function (Builder $eventQuery) use ($viewer): void {
                $eventQuery->where('status', MultiKolabEventStatus::Recruiting)
                    ->where('creator_profile_id', '!=', $viewer->id);
            });
    }

    /**
     * Filter support matrix for Multi-Kolab role items (Review item #6):
     *
     * A. Directly supported — semantically meaningful and applied below:
     *    `city` (event city), `search` (role/event title, need, city).
     * B. Safely translatable — none currently; every other filter either
     *    has no Multi-Kolab equivalent or would require guessing intent.
     * C. Unsupported / not semantically meaningful for a role item, so an
     *    active filter here must EXCLUDE role items rather than silently
     *    ignore the filter and still return them: `availability_mode`,
     *    `availability_from`, `availability_to` (ordinary Kolabs have an
     *    explicit availability window; Multi-Kolab roles don't), plus every
     *    ordinary-Kolab-only facet (`need_types`, `community_types`,
     *    `audience_size_band`, `offers_in_return`, `venue_preferences`,
     *    `intent_types`, `offer_types`, `venue_types`, `product_types`,
     *    `expected_deliverables`, `community_requirement_band`).
     *
     * @var array<int, string>
     */
    private const MULTI_KOLAB_ROLE_UNSUPPORTED_FILTER_KEYS = [
        'availability_mode',
        'availability_from',
        'availability_to',
        'need_types',
        'community_types',
        'audience_size_band',
        'offers_in_return',
        'venue_preferences',
        'intent_types',
        'offer_types',
        'venue_types',
        'product_types',
        'expected_deliverables',
        'community_requirement_band',
    ];

    /**
     * @param  array<string, mixed>  $filters
     */
    private function hasUnsupportedMultiKolabRoleFilter(array $filters): bool
    {
        foreach (self::MULTI_KOLAB_ROLE_UNSUPPORTED_FILTER_KEYS as $key) {
            $value = $filters[$key] ?? null;

            if (is_array($value) ? $value !== [] : $value !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  Builder<MultiKolabRole>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyMultiKolabRoleFilters(Builder $query, array $filters): void
    {
        if ($this->hasUnsupportedMultiKolabRoleFilter($filters)) {
            // The response must never claim a filter is applied while
            // returning role items that ignored it — exclude rather than
            // leak (Review item #6).
            $query->whereRaw('1 = 0');

            return;
        }

        if ($filters['city'] !== null) {
            $query->whereHas('event', function (Builder $eventQuery) use ($filters): void {
                $eventQuery->where('city', $filters['city']);
            });
        }

        if ($filters['search'] !== null) {
            $searchTerm = '%'.strtolower($filters['search']).'%';
            $likeOperator = $this->getCaseInsensitiveLikeOperator();

            $query->where(function (Builder $searchQuery) use ($searchTerm, $likeOperator): void {
                if ($likeOperator === 'ilike') {
                    $searchQuery->where('title', 'ilike', $searchTerm)
                        ->orWhere('need', 'ilike', $searchTerm)
                        ->orWhereHas('event', function (Builder $eventQuery) use ($searchTerm): void {
                            $eventQuery->where('title', 'ilike', $searchTerm)
                                ->orWhere('city', 'ilike', $searchTerm);
                        });

                    return;
                }

                $searchQuery->whereRaw('LOWER(title) LIKE ?', [$searchTerm])
                    ->orWhereRaw('LOWER(need) LIKE ?', [$searchTerm])
                    ->orWhereHas('event', function (Builder $eventQuery) use ($searchTerm): void {
                        $eventQuery->whereRaw('LOWER(title) LIKE ?', [$searchTerm])
                            ->orWhereRaw('LOWER(city) LIKE ?', [$searchTerm]);
                    });
            });
        }
    }

    /**
     * @param  Builder<MultiKolabRole>  $query
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array{item_type: string, model: MultiKolabRole, score: int, timestamp: int, sort_date: ?string}>
     */
    private function buildMultiKolabRoleItems(Builder $query, Profile $viewer, array $filters): Collection
    {
        $roles = $query
            ->with([
                'event' => function ($eventQuery): void {
                    $eventQuery->with([
                        'creatorProfile' => function ($profileQuery): void {
                            $profileQuery->select('id', 'user_type', 'avatar_url')
                                ->with([
                                    'businessProfile:profile_id,name',
                                    'communityProfile:profile_id,name',
                                ]);
                        },
                    ]);
                },
            ])
            ->get();

        return $roles->map(function (MultiKolabRole $role) use ($viewer): array {
            $event = $role->event;
            $score = $this->buildMultiKolabRoleScore($role, $event, $viewer);
            $role->setAttribute('discovery_match_score', $score);

            return [
                'item_type' => 'multi_kolab_role',
                'model' => $role,
                'score' => $score,
                'timestamp' => $event->published_at?->timestamp ?? 0,
                'sort_date' => $this->resolveMultiKolabRoleTargetDate($event),
            ];
        });
    }

    private function buildMultiKolabRoleScore(MultiKolabRole $role, MultiKolabEvent $event, Profile $viewer): int
    {
        $score = 0;
        $publishedAt = $event->published_at;

        if ($publishedAt !== null) {
            if ($publishedAt->greaterThanOrEqualTo(Carbon::now()->subDays(7))) {
                $score += 10;
            } elseif ($publishedAt->greaterThanOrEqualTo(Carbon::now()->subDays(30))) {
                $score += 5;
            }
        }

        $viewerCity = $this->resolveViewerCity($viewer);

        if ($viewerCity !== null && $event->city !== null && $event->city === $viewerCity) {
            $score += 40;
        }

        return $score;
    }

    /**
     * Analogous "sortable target date" for a Multi-Kolab role, used by the
     * `ending_soon` sort: an exact-date event sorts by its `event_date`; a
     * ranged event sorts by the end of its range (falling back to the start
     * if no end is set) — the same "soonest deadline first" intent as the
     * ordinary Kolab `availability_end`/`availability_start` pair.
     */
    private function resolveMultiKolabRoleTargetDate(MultiKolabEvent $event): ?string
    {
        if ($event->date_mode === 'exact') {
            return $event->event_date?->toDateString();
        }

        return ($event->date_range_end ?? $event->date_range_start)?->toDateString();
    }

    /**
     * Generic replacement for {@see sortScoredResults()} that sorts a
     * heterogeneous collection of wrapped feed items (Kolabs and Multi-Kolab
     * roles) using only the scalar `score`/`timestamp`/`sort_date` keys
     * every item is tagged with, so both item types share one deterministic
     * order, pagination, and tie-break (by the underlying model's UUID —
     * every item's model has a unique `id`, so this never produces
     * duplicates/omissions across pages regardless of item type).
     *
     * @param  Collection<int, array{item_type: string, model: \Illuminate\Database\Eloquent\Model, score: int, timestamp: int, sort_date: ?string}>  $items
     * @return Collection<int, array{item_type: string, model: \Illuminate\Database\Eloquent\Model, score: int, timestamp: int, sort_date: ?string}>
     */
    private function sortCombinedItems(Collection $items, string $sort, string $feed): Collection
    {
        $resolvedSort = $sort !== '' ? $sort : ($feed === 'recommended' ? 'recommended' : 'recent');

        if ($resolvedSort === 'ending_soon') {
            return $items
                ->sort(function (array $left, array $right): int {
                    $leftHasNoDate = $left['sort_date'] === null;
                    $rightHasNoDate = $right['sort_date'] === null;

                    if ($leftHasNoDate !== $rightHasNoDate) {
                        return $leftHasNoDate <=> $rightHasNoDate;
                    }

                    $leftDate = $left['sort_date'] ?? '9999-12-31';
                    $rightDate = $right['sort_date'] ?? '9999-12-31';

                    if ($leftDate !== $rightDate) {
                        return $leftDate <=> $rightDate;
                    }

                    if ($right['timestamp'] !== $left['timestamp']) {
                        return $right['timestamp'] <=> $left['timestamp'];
                    }

                    return $this->itemModelId($left) <=> $this->itemModelId($right);
                })
                ->values();
        }

        if ($resolvedSort === 'recommended') {
            return $items
                ->sortByDesc(function (array $item): string {
                    $score = str_pad((string) $item['score'], 3, '0', STR_PAD_LEFT);
                    $timestamp = str_pad((string) $item['timestamp'], 12, '0', STR_PAD_LEFT);

                    return $score.$timestamp.'_'.$this->itemModelId($item);
                })
                ->values();
        }

        return $items
            ->sort(function (array $left, array $right): int {
                if ($right['timestamp'] !== $left['timestamp']) {
                    return $right['timestamp'] <=> $left['timestamp'];
                }

                return $this->itemModelId($left) <=> $this->itemModelId($right);
            })
            ->values();
    }

    /**
     * @param  array{item_type: string, model: \Illuminate\Database\Eloquent\Model, score: int, timestamp: int, sort_date: ?string}  $item
     */
    private function itemModelId(array $item): string
    {
        return (string) $item['model']->id;
    }

    private function resolveViewerCity(Profile $viewer): ?string
    {
        if ($viewer->isBusiness()) {
            return $viewer->businessProfile?->city_name;
        }

        return $viewer->communityProfile?->city?->name;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function buildAppliedFilters(array $filters): array
    {
        return Arr::where($filters, static function (mixed $value, string $key): bool {
            if (in_array($key, ['page', 'per_page'], true)) {
                return false;
            }

            if (is_array($value)) {
                return $value !== [];
            }

            return $value !== null && $value !== '';
        });
    }

    private function resolveEmptyReason(LengthAwarePaginator $paginator, bool $hasPublishedResults, string $feed): ?string
    {
        if ($paginator->total() > 0) {
            return null;
        }

        if (! $hasPublishedResults) {
            return 'no_published_results';
        }

        return $feed === 'recommended'
            ? 'no_recommended_matches'
            : 'no_results_after_filters';
    }

    private function buildBusinessOfferBlock(Kolab $kolab): ?array
    {
        if ($kolab->intent_type === IntentType::CommunitySeeking) {
            return null;
        }

        return [
            'offer_headline' => is_string($kolab->offer_headline) && $kolab->offer_headline !== ''
                ? $kolab->offer_headline
                : Str::limit($kolab->description, 50, ''),
            'base_offer' => is_string($kolab->base_offer) && $kolab->base_offer !== ''
                ? $kolab->base_offer
                : $kolab->description,
            'offer_types' => $this->normalizeOfferTypes($kolab->offering),
            'venue_type' => $kolab->venue_type,
            'product_type' => $kolab->product_type,
            'seeking_communities' => $this->normalizeTagObjects($kolab->seeking_communities),
            'min_community_size' => $kolab->min_community_size,
            'expected_deliverables' => $this->normalizeDeliverables($kolab->expects),
        ];
    }

    private function buildCommunityRequestBlock(Kolab $kolab): ?array
    {
        if ($kolab->intent_type !== IntentType::CommunitySeeking) {
            return null;
        }

        return [
            'need_types' => $this->normalizeNeedTypes($kolab->needs),
            'community_types' => $this->normalizeTagObjects($kolab->community_types),
            'community_size' => $kolab->community_size,
            'typical_attendance' => $kolab->typical_attendance,
            'offers_in_return' => $this->normalizeDeliverables($kolab->offers_in_return),
            'venue_preference' => $kolab->venue_preference,
        ];
    }

    private function buildMatchPayload(Kolab $kolab, Profile $viewer, array $filters, string $viewerRole): array
    {
        $locationScore = $this->resolveLocationScore($kolab, $viewer);
        $pastActivityScore = $this->resolvePastActivitySignal($kolab->published_at);
        $components = $viewerRole === 'business'
            ? $this->resolveBusinessViewerMatchComponents($kolab, $viewer, $filters)
            : $this->resolveCommunityViewerMatchComponents($kolab, $viewer, $filters);
        $reasons = $components['reasons'];

        if ($locationScore >= 1.0) {
            $reasons[] = 'city_match';
        }

        if ($pastActivityScore >= 0.65) {
            $reasons[] = 'freshness_match';
        }

        $breakdown = collect(self::MATCH_SIGNALS)
            ->map(function (array $signal) use ($components, $locationScore, $pastActivityScore): array {
                $score = match ($signal['key']) {
                    'category_fit' => $components['category_fit'],
                    'location' => $locationScore,
                    'value_fit' => $components['value_fit'],
                    'past_activity' => $pastActivityScore,
                    default => 0.0,
                };

                return [
                    'key' => $signal['key'],
                    'label' => $signal['label'],
                    'weight' => $signal['weight'],
                    'score' => round($score, 2),
                ];
            })
            ->values()
            ->all();

        $score = (int) round(collect($breakdown)->sum(
            fn (array $signal): float => $signal['weight'] * $signal['score']
        ) * 100);

        return [
            'feed' => $filters['feed'],
            'score' => $score,
            'tier' => $this->resolveScoreTier($score),
            'reasons' => array_values(array_unique($reasons)),
            'breakdown' => $breakdown,
        ];
    }

    private function resolveFreshnessScore(?Carbon $publishedAt): int
    {
        if ($publishedAt === null) {
            return 0;
        }

        if ($publishedAt->greaterThanOrEqualTo(Carbon::now()->subDays(7))) {
            return 10;
        }

        if ($publishedAt->greaterThanOrEqualTo(Carbon::now()->subDays(30))) {
            return 5;
        }

        return 0;
    }

    private function resolveLocationScore(Kolab $kolab, Profile $viewer): float
    {
        $viewerCity = $this->resolveViewerCity($viewer);

        if ($viewerCity !== null && $kolab->preferred_city === $viewerCity) {
            return 1.0;
        }

        return 0.0;
    }

    private function resolvePastActivitySignal(?Carbon $publishedAt): float
    {
        if ($publishedAt === null) {
            return 0.2;
        }

        if ($publishedAt->greaterThanOrEqualTo(Carbon::now()->subDays(7))) {
            return 1.0;
        }

        if ($publishedAt->greaterThanOrEqualTo(Carbon::now()->subDays(30))) {
            return 0.65;
        }

        return 0.3;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{category_fit: float, value_fit: float, reasons: array<int, string>}
     */
    private function resolveBusinessViewerMatchComponents(Kolab $kolab, Profile $viewer, array $filters): array
    {
        $reasons = [];
        $needs = $this->normalizeNeedTypes($kolab->needs);
        $capabilities = $this->inferBusinessCapabilities($viewer);
        $needScore = $this->hasAnyOverlap($needs, $capabilities) ? 1.0 : 0.0;

        if ($needScore === 1.0) {
            $reasons[] = 'need_type_match';
        }

        $communityTypes = $this->extractTagKeys($this->normalizeTagObjects($kolab->community_types));
        $affinityTerms = $this->normalizeTagKeys(
            $this->inferBusinessCommunityAffinityTerms($viewer, $filters['community_types'])
        );
        $communityAffinityScore = $this->hasAnyOverlap($communityTypes, $affinityTerms) ? 1.0 : 0.0;

        if ($communityAffinityScore === 1.0) {
            $reasons[] = 'community_type_match';
        }

        $offersInReturn = $this->normalizeDeliverables($kolab->offers_in_return);
        $desiredReturns = $filters['offers_in_return'] !== []
            ? $filters['offers_in_return']
            : $this->inferBusinessDesiredReturns($viewer);
        $offersInReturnScore = $this->hasAnyOverlap($offersInReturn, $desiredReturns) ? 1.0 : 0.0;

        if ($offersInReturnScore === 1.0) {
            $reasons[] = 'offers_in_return_match';
        }

        $venueScore = 0.5;
        if ($this->viewerHasUsableVenue($viewer)) {
            $venueScore = in_array($kolab->venue_preference, ['business_provides', 'no_venue'], true)
                ? 1.0
                : 0.25;

            if ($venueScore === 1.0) {
                $reasons[] = 'venue_preference_match';
            }
        }

        $capacityScore = 0.5;
        $capacity = $this->resolveViewerVenueCapacity($viewer);
        $audience = $kolab->typical_attendance ?? $kolab->community_size;
        if ($capacity !== null && $audience !== null && $audience > 0) {
            $capacityScore = min(1.0, round($capacity / $audience, 2));

            if ($capacityScore >= 1.0) {
                $reasons[] = 'audience_size_match';
            }
        }

        return [
            'category_fit' => $this->averageScores([$needScore, $communityAffinityScore]),
            'value_fit' => $this->averageScores([$offersInReturnScore, $venueScore, $capacityScore]),
            'reasons' => $reasons,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{category_fit: float, value_fit: float, reasons: array<int, string>}
     */
    private function resolveCommunityViewerMatchComponents(Kolab $kolab, Profile $viewer, array $filters): array
    {
        $reasons = [];
        $communityType = Str::of((string) $viewer->communityProfile?->community_type)
            ->lower()
            ->trim()
            ->value();

        $categoryFit = $this->resolveCommunityCategoryFit($communityType, $kolab);
        if ($categoryFit >= 0.75) {
            $reasons[] = 'community_affinity_match';
        }

        $offerTypes = $this->normalizeOfferTypes($kolab->offering);
        $desiredOfferTypes = $filters['offer_types'] !== []
            ? $filters['offer_types']
            : $this->inferCommunityDesiredOfferTypes($viewer);
        $offerTypeScore = $this->hasAnyOverlap($offerTypes, $desiredOfferTypes) ? 1.0 : 0.0;

        if ($offerTypeScore === 1.0) {
            $reasons[] = 'offer_type_match';
        }

        $expectedDeliverables = $this->normalizeDeliverables($kolab->expects);
        $desiredDeliverables = $filters['expected_deliverables'] !== []
            ? $filters['expected_deliverables']
            : ['social_media', 'community_reach', 'event_activation'];
        $deliverableScore = $this->hasAnyOverlap($expectedDeliverables, $desiredDeliverables) ? 1.0 : 0.35;

        if ($deliverableScore === 1.0) {
            $reasons[] = 'expected_deliverable_match';
        }

        $seekingCommunities = $this->extractTagKeys($this->normalizeTagObjects($kolab->seeking_communities));
        $affinityTerms = $this->normalizeTagKeys($this->inferCommunityAffinityTerms($viewer));
        $seekingCommunityScore = $this->hasAnyOverlap($seekingCommunities, $affinityTerms) ? 1.0 : 0.3;

        if ($seekingCommunityScore === 1.0) {
            $reasons[] = 'community_affinity_match';
        }

        $preferredIntents = $filters['intent_types'] !== []
            ? $filters['intent_types']
            : $this->inferCommunityPreferredIntents($viewer);
        if (in_array($kolab->intent_type->value, $preferredIntents, true)) {
            $reasons[] = 'intent_type_match';
        }

        return [
            'category_fit' => round(min(1.0, $categoryFit + ($seekingCommunityScore === 1.0 ? 0.1 : 0.0)), 2),
            'value_fit' => $this->averageScores([$offerTypeScore, $deliverableScore, $seekingCommunityScore]),
            'reasons' => $reasons,
        ];
    }

    private function resolveCommunityCategoryFit(string $communityType, Kolab $kolab): float
    {
        $businessCategories = $this->resolveKolabBusinessCategories($kolab);

        if ($businessCategories === []) {
            return 0.25;
        }

        $scores = array_map(
            fn (string $category): float => $this->resolveCategoryAffinityScore($communityType, $category),
            $businessCategories
        );

        return round(max($scores), 2);
    }

    /**
     * @return array<int, string>
     */
    private function resolveKolabBusinessCategories(Kolab $kolab): array
    {
        $categories = [];

        foreach ($kolab->creatorProfile?->businessProfile?->normalizedCategories() ?? [] as $category) {
            $categories[] = $this->normalizeCategoryValue($category);
        }

        if (is_string($kolab->venue_type) && $kolab->venue_type !== '') {
            $categories[] = $this->normalizeCategoryValue($kolab->venue_type);
        }

        if (is_string($kolab->product_type) && $kolab->product_type !== '') {
            $categories[] = $this->normalizeCategoryValue($kolab->product_type);
        }

        return array_values(array_unique(array_filter($categories)));
    }

    private function resolveCategoryAffinityScore(string $communityType, string $businessCategory): float
    {
        $mappedScore = self::COMMUNITY_BUSINESS_CATEGORY_SCORES[$communityType][$businessCategory] ?? null;
        if ($mappedScore !== null) {
            return $mappedScore;
        }

        return match ($businessCategory) {
            'cafe', 'restaurant', 'bar', 'bar_lounge', 'bakery' => 0.65,
            'hotel', 'event_space', 'rooftop', 'beach_club' => 0.55,
            'coworking' => 0.35,
            'retail', 'fashion' => 0.45,
            default => 0.4,
        };
    }

    private function normalizeCategoryValue(string $value): string
    {
        return Str::of($value)
            ->trim()
            ->lower()
            ->replace([' ', '-'], '_')
            ->value();
    }

    /**
     * @param  array<int, float>  $scores
     */
    private function averageScores(array $scores): float
    {
        $filtered = array_values(array_filter($scores, static fn (float $score): bool => $score >= 0.0));

        if ($filtered === []) {
            return 0.0;
        }

        return round(array_sum($filtered) / count($filtered), 2);
    }

    private function resolveScoreTier(int $score): string
    {
        return match (true) {
            $score >= 70 => 'high',
            $score >= 35 => 'medium',
            default => 'low',
        };
    }

    /**
     * @param  array<int, string>  $values
     * @return array<int, string>
     */
    private function normalizeStringArray(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(function (mixed $value): ?string {
            return $this->normalizeNullableString($value);
        }, $values))));
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * @param  array<int, string>  $values
     * @return array<int, string>
     */
    private function expandTerms(array $values): array
    {
        $expanded = [];

        foreach ($values as $value) {
            $normalizedValue = Str::of($value)
                ->replace(['-', '/'], ' ')
                ->trim()
                ->lower()
                ->value();

            if ($normalizedValue === '') {
                continue;
            }

            $expanded[] = $normalizedValue;
            $expanded[] = str_replace('_', ' ', $normalizedValue);

            if ($normalizedValue === 'food_drink') {
                $expanded[] = 'food & drink';
                $expanded[] = 'food and drink';
            }
        }

        return array_values(array_unique(array_filter($expanded)));
    }

    /**
     * @param  array<int, string>  $offerTypes
     * @return array<int, string>
     */
    private function expandOfferTypeTerms(array $offerTypes): array
    {
        $expanded = [];

        foreach ($offerTypes as $offerType) {
            foreach (self::OFFER_TYPE_ALIASES[$offerType] ?? [$offerType] as $alias) {
                array_push($expanded, ...$this->expandTerms([$alias]));
            }
        }

        return array_values(array_unique($expanded));
    }

    /**
     * @return array{0: string, 1: array<int, string>}
     */
    private function textAnyContainsExpression(string $column, array $terms): array
    {
        $terms = array_values(array_unique(array_filter(array_map(static function (mixed $term): string {
            return Str::of((string) $term)->lower()->trim()->value();
        }, $terms))));

        if ($terms === []) {
            return ['1 = 0', []];
        }

        $columnExpression = $this->columnTextExpression($column);
        $clauses = [];
        $bindings = [];

        foreach ($terms as $term) {
            $clauses[] = "{$columnExpression} LIKE ?";
            $bindings[] = '%'.$term.'%';
        }

        return ['('.implode(' OR ', $clauses).')', $bindings];
    }

    private function columnTextExpression(string $column): string
    {
        $driver = Kolab::query()->getConnection()->getDriverName();

        return match ($driver) {
            'pgsql' => "LOWER(COALESCE(CAST({$column} AS TEXT), ''))",
            'mysql', 'mariadb' => "LOWER(COALESCE(CAST({$column} AS CHAR), ''))",
            default => "LOWER(COALESCE({$column}, ''))",
        };
    }

    private function getCaseInsensitiveLikeOperator(): string
    {
        $driver = DB::connection()->getDriverName();

        return $driver === 'pgsql' ? 'ilike' : 'like';
    }

    private function applyNumericBandCondition(Builder $query, string $column, string $band): void
    {
        match ($band) {
            'small' => $query->where($column, '<', 100),
            'medium' => $query->whereBetween($column, [100, 499]),
            'large' => $query->whereBetween($column, [500, 1999]),
            'xlarge' => $query->where($column, '>=', 2000),
            default => null,
        };
    }

    /**
     * @return array<int, string>
     */
    private function inferBusinessCapabilities(Profile $viewer): array
    {
        $capabilities = [];

        if ($this->viewerHasUsableVenue($viewer)) {
            $capabilities[] = 'venue';
        }

        foreach ($viewer->businessProfile?->normalizedCategories() ?? [] as $category) {
            $capabilities = array_merge($capabilities, match ($category) {
                'cafe', 'restaurant', 'bar', 'bakery', 'hotel' => ['food_drink', 'discount'],
                'retail', 'salon' => ['products', 'discount'],
                'gym', 'coworking' => ['venue'],
                default => [],
            });
        }

        return array_values(array_unique($capabilities));
    }

    /**
     * @return array<int, string>
     */
    private function inferBusinessDesiredReturns(Profile $viewer): array
    {
        $returns = ['social_media', 'community_reach'];

        foreach ($viewer->businessProfile?->normalizedCategories() ?? [] as $category) {
            $returns = array_merge($returns, match ($category) {
                'cafe', 'restaurant', 'bar', 'hotel', 'coworking', 'gym' => ['event_activation'],
                'retail', 'salon', 'bakery' => ['product_placement', 'review_feedback'],
                default => [],
            });
        }

        return array_values(array_unique($returns));
    }

    /**
     * @param  array<int, string>  $explicitTerms
     * @return array<int, string>
     */
    private function inferBusinessCommunityAffinityTerms(Profile $viewer, array $explicitTerms): array
    {
        if ($explicitTerms !== []) {
            return $this->expandTerms($explicitTerms);
        }

        $terms = [];

        foreach ($viewer->businessProfile?->normalizedCategories() ?? [] as $category) {
            $terms = array_merge($terms, match ($category) {
                'cafe', 'restaurant', 'bar', 'bakery' => ['food', 'lifestyle'],
                'gym' => ['fitness', 'wellness', 'run club'],
                'hotel' => ['travel', 'lifestyle'],
                'coworking' => ['tech', 'startup', 'professional', 'student'],
                'salon' => ['wellness', 'beauty', 'lifestyle'],
                'retail' => ['fashion', 'lifestyle'],
                default => [],
            });
        }

        return array_values(array_unique($terms));
    }

    /**
     * @return array<int, string>
     */
    private function inferCommunityAffinityTerms(Profile $viewer): array
    {
        $communityType = Str::of((string) $viewer->communityProfile?->community_type)
            ->lower()
            ->trim()
            ->value();

        return match ($communityType) {
            'run_club' => ['run club', 'running', 'fitness', 'wellness'],
            'fitness_community' => ['fitness', 'wellness', 'sports'],
            'wellness_community' => ['wellness', 'fitness'],
            'art_creative_community' => ['art', 'creative'],
            'photography_community' => ['photography', 'creative', 'art'],
            'music_community' => ['music'],
            'dance_community' => ['dance'],
            'tech_startup_community' => ['tech', 'startup'],
            'book_club' => ['book', 'reading'],
            'sustainability_community' => ['sustainability', 'eco'],
            'food_community' => ['food', 'foodies'],
            'travel_community' => ['travel'],
            'student_community' => ['student', 'campus'],
            'professional_networking_community' => ['professional', 'networking'],
            'hobby_community' => ['hobby'],
            default => [],
        };
    }

    /**
     * @return array<int, string>
     */
    private function inferCommunityDesiredOfferTypes(Profile $viewer): array
    {
        $communityType = Str::of((string) $viewer->communityProfile?->community_type)
            ->lower()
            ->trim()
            ->value();

        return match ($communityType) {
            'run_club', 'fitness_community', 'wellness_community' => ['venue', 'food_drink', 'products'],
            'art_creative_community', 'photography_community', 'music_community', 'dance_community' => ['venue', 'social_media', 'content_creation', 'sponsorship'],
            'tech_startup_community', 'student_community', 'professional_networking_community' => ['venue', 'discount', 'sponsorship'],
            'food_community' => ['food_drink', 'venue'],
            'travel_community' => ['venue', 'discount', 'social_media'],
            default => ['venue'],
        };
    }

    /**
     * @return array<int, string>
     */
    private function inferCommunityPreferredIntents(Profile $viewer): array
    {
        $communityType = Str::of((string) $viewer->communityProfile?->community_type)
            ->lower()
            ->trim()
            ->value();

        return match ($communityType) {
            'food_community', 'sustainability_community' => [IntentType::ProductPromotion->value],
            default => [IntentType::VenuePromotion->value],
        };
    }

    private function viewerHasUsableVenue(Profile $viewer): bool
    {
        return is_array($viewer->businessProfile?->primary_venue) && $viewer->businessProfile?->primary_venue !== [];
    }

    private function resolveViewerVenueCapacity(Profile $viewer): ?int
    {
        $capacity = $viewer->businessProfile?->primary_venue['capacity'] ?? null;

        return is_numeric($capacity) ? (int) $capacity : null;
    }

    /**
     * @return array<int, string>
     */
    private function normalizeOfferTypes(mixed $offering): array
    {
        $resolved = [];

        foreach ($this->extractStringValues($offering) as $value) {
            $lower = Str::of($value)->lower()->trim()->value();

            foreach (self::OFFER_TYPE_ALIASES as $stableKey => $aliases) {
                if (in_array($lower, $aliases, true)) {
                    $resolved[] = $stableKey;
                    break;
                }
            }
        }

        return array_values(array_unique($resolved));
    }

    /**
     * @return array<int, string>
     */
    private function normalizeDeliverables(mixed $values): array
    {
        return array_values(array_filter(
            array_unique($this->extractStringValues($values)),
            fn (string $value): bool => in_array($value, self::DELIVERABLE_TYPES, true)
        ));
    }

    /**
     * @return array<int, string>
     */
    private function normalizeNeedTypes(mixed $values): array
    {
        return array_values(array_filter(
            array_unique($this->extractStringValues($values)),
            fn (string $value): bool => in_array($value, self::NEED_TYPES, true)
        ));
    }

    /**
     * @return array<int, array{key: string, label: string}>
     */
    private function normalizeTagObjects(mixed $values): array
    {
        $normalized = [];

        foreach ($this->extractStringValues($values) as $value) {
            $key = $this->normalizeTagKey($value);

            if ($key === null) {
                continue;
            }

            $normalized[$key] = [
                'key' => $key,
                'label' => $this->normalizeTagLabel($value),
            ];
        }

        return array_values($normalized);
    }

    private function normalizeTagKey(string $value): ?string
    {
        $normalized = Str::of($value)
            ->trim()
            ->replace('&', ' and ')
            ->replace(['/', '-'], ' ')
            ->lower()
            ->value();

        if ($normalized === '') {
            return null;
        }

        $normalized = preg_replace('/\s+/', '_', $normalized);

        return $normalized === null ? null : trim($normalized, '_');
    }

    private function normalizeTagLabel(string $value): string
    {
        $key = $this->normalizeTagKey($value);

        if ($key === null) {
            return $value;
        }

        return match ($key) {
            'food_drink' => 'Food & Drink',
            default => Str::of($key)->replace('_', ' ')->title()->value(),
        };
    }

    /**
     * @return array<int, string>
     */
    private function extractStringValues(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        if (isset($values['types']) && is_array($values['types'])) {
            return $this->extractStringValues($values['types']);
        }

        $resolved = [];

        foreach ($values as $key => $value) {
            if (is_int($key) && is_string($value) && trim($value) !== '') {
                $resolved[] = Str::of($value)->lower()->trim()->value();

                continue;
            }

            if (is_string($key) && filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) !== false) {
                $resolved[] = Str::of($key)->lower()->trim()->value();
            }
        }

        return array_values(array_unique(array_filter($resolved)));
    }

    /**
     * @param  array<int, array{key: string, label: string}>  $tags
     * @return array<int, string>
     */
    private function extractTagKeys(array $tags): array
    {
        return array_values(array_map(static fn (array $tag): string => $tag['key'], $tags));
    }

    /**
     * @param  array<int, string>  $values
     * @return array<int, string>
     */
    private function normalizeTagKeys(array $values): array
    {
        $normalized = [];

        foreach ($values as $value) {
            $key = $this->normalizeTagKey($value);

            if ($key !== null) {
                $normalized[] = $key;
            }
        }

        return array_values(array_unique($normalized));
    }

    /**
     * @param  array<int, string>  $left
     * @param  array<int, string>  $right
     */
    private function hasAnyOverlap(array $left, array $right): bool
    {
        return array_intersect($left, $right) !== [];
    }
}
