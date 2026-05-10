<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\IntentType;
use App\Enums\KolabStatus;
use App\Enums\UserType;
use App\Models\Kolab;
use App\Models\Profile;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
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
     * @return array{
     *     paginator: LengthAwarePaginator,
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

        [$scoreSql, $scoreBindings] = $this->buildScoreExpression($viewer, $normalizedFilters, $viewerRole);

        $query->select('kolabs.*')
            ->selectRaw("{$scoreSql} as discovery_match_score", $scoreBindings);

        $this->applySorting($query, $normalizedFilters['sort'], $normalizedFilters['feed']);

        $paginator = $query->paginate($normalizedFilters['per_page']);
        $paginator->setCollection(
            $paginator->getCollection()->map(function (Kolab $kolab) use ($viewer, $normalizedFilters, $viewerRole): Kolab {
                $kolab->setAttribute('discovery_business_offer', $this->buildBusinessOfferBlock($kolab));
                $kolab->setAttribute('discovery_community_request', $this->buildCommunityRequestBlock($kolab));
                $kolab->setAttribute(
                    'discovery_match',
                    $this->buildMatchPayload($kolab, $viewer, $normalizedFilters, $viewerRole)
                );

                return $kolab;
            })
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
            ->with([
                'creatorProfile' => function ($query): void {
                    $query->select('id', 'user_type', 'avatar_url')
                        ->with([
                            'businessProfile:profile_id,name',
                            'communityProfile:profile_id,name,community_type',
                        ]);
                },
            ]);

        $this->applyRoleScope($query, $viewerRole);
        $this->applyActiveAvailabilityFilter($query);
        $this->excludeAlreadyAppliedKolabs($query, $viewer);

        return $query;
    }

    private function excludeAlreadyAppliedKolabs(Builder $query, Profile $viewer): void
    {
        $query->whereNotExists(function ($subQuery) use ($viewer): void {
            $subQuery->selectRaw('1')
                ->from('applications')
                ->whereColumn('applications.collab_opportunity_id', 'kolabs.id')
                ->where('applications.applicant_profile_id', $viewer->id);
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
            if ($key === 'per_page') {
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
        $score = 0;
        $reasons = [];
        $viewerCity = $this->resolveViewerCity($viewer);

        if ($viewerCity !== null && $kolab->preferred_city === $viewerCity) {
            $score += 40;
            $reasons[] = 'city_match';
        }

        $freshnessScore = $this->resolveFreshnessScore($kolab->published_at);
        if ($freshnessScore > 0) {
            $score += $freshnessScore;
            $reasons[] = 'freshness_match';
        }

        if ($viewerRole === 'business') {
            $needs = $this->normalizeNeedTypes($kolab->needs);
            $capabilities = $this->inferBusinessCapabilities($viewer);

            if ($this->hasAnyOverlap($needs, $capabilities)) {
                $score += 30;
                $reasons[] = 'need_type_match';
            }

            $offersInReturn = $this->normalizeDeliverables($kolab->offers_in_return);
            $desiredReturns = $filters['offers_in_return'] !== []
                ? $filters['offers_in_return']
                : $this->inferBusinessDesiredReturns($viewer);

            if ($this->hasAnyOverlap($offersInReturn, $desiredReturns)) {
                $score += 15;
                $reasons[] = 'offers_in_return_match';
            }

            $communityTypes = $this->extractTagKeys($this->normalizeTagObjects($kolab->community_types));
            $affinityTerms = $this->normalizeTagKeys(
                $this->inferBusinessCommunityAffinityTerms($viewer, $filters['community_types'])
            );

            if ($this->hasAnyOverlap($communityTypes, $affinityTerms)) {
                $score += 20;
                $reasons[] = 'community_type_match';
            }

            if ($this->viewerHasUsableVenue($viewer) && in_array($kolab->venue_preference, ['business_provides', 'no_venue'], true)) {
                $score += 10;
                $reasons[] = 'venue_preference_match';
            }

            $capacity = $this->resolveViewerVenueCapacity($viewer);
            $audience = $kolab->typical_attendance ?? $kolab->community_size;

            if ($capacity !== null && $audience !== null && $audience > 0 && $audience <= $capacity) {
                $score += 10;
                $reasons[] = 'audience_size_match';
            }
        } else {
            $seekingCommunities = $this->extractTagKeys($this->normalizeTagObjects($kolab->seeking_communities));
            $affinityTerms = $this->normalizeTagKeys($this->inferCommunityAffinityTerms($viewer));

            if ($this->hasAnyOverlap($seekingCommunities, $affinityTerms)) {
                $score += 30;
                $reasons[] = 'community_affinity_match';
            }

            $offerTypes = $this->normalizeOfferTypes($kolab->offering);
            $desiredOfferTypes = $filters['offer_types'] !== []
                ? $filters['offer_types']
                : $this->inferCommunityDesiredOfferTypes($viewer);

            if ($this->hasAnyOverlap($offerTypes, $desiredOfferTypes)) {
                $score += 20;
                $reasons[] = 'offer_type_match';
            }

            $expectedDeliverables = $this->normalizeDeliverables($kolab->expects);
            $desiredDeliverables = $filters['expected_deliverables'] !== []
                ? $filters['expected_deliverables']
                : ['social_media', 'community_reach', 'event_activation'];

            if ($this->hasAnyOverlap($expectedDeliverables, $desiredDeliverables)) {
                $score += 15;
                $reasons[] = 'expected_deliverable_match';
            }

            $preferredIntents = $filters['intent_types'] !== []
                ? $filters['intent_types']
                : $this->inferCommunityPreferredIntents($viewer);

            if (in_array($kolab->intent_type->value, $preferredIntents, true)) {
                $score += 10;
                $reasons[] = 'intent_type_match';
            }
        }

        return [
            'feed' => $filters['feed'],
            'score' => $score,
            'tier' => $this->resolveScoreTier($score),
            'reasons' => array_values(array_unique($reasons)),
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
