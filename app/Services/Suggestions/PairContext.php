<?php

declare(strict_types=1);

namespace App\Services\Suggestions;

use App\Enums\SuggestionAudience;

/**
 * Everything the scorer needs about one candidate pair, pre-loaded by
 * PairCandidateFinder. The scorer never touches the database — that keeps
 * scoring a pure function and keeps the queries batched in one place.
 */
readonly class PairContext
{
    /**
     * @param  array<int, string>  $businessCategories  every category the business declares
     * @param  array<int, int>  $pastAttendance  attendee_count of the community's past events
     * @param  array<int, string>  $viewerOffers  what the viewer can give
     * @param  array<int, string>  $counterpartNeeds  what the counterpart wants
     * @param  array<string, mixed>  $evidence  ids + aggregates for the audit trail
     */
    public function __construct(
        public SuggestionAudience $audience,
        public string $viewerProfileId,
        public string $counterpartProfileId,
        public ?string $communityType,
        public array $businessCategories,
        public ?string $viewerCityId,
        public ?string $counterpartCityId,
        public ?float $distanceKm,
        public array $pastAttendance,
        public ?int $communitySize,
        public ?int $venueCapacity,
        public array $viewerOffers,
        public array $counterpartNeeds,
        public ?float $averageRating,
        public ?float $repeatRatio,
        public int $contentDelivered,
        public int $reviewCount,
        public int $recentEventCount,
        public bool $hasActiveSeries,
        public array $evidence = [],
    ) {}
}
