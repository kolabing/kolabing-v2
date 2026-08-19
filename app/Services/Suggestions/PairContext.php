<?php

declare(strict_types=1);

namespace App\Services\Suggestions;

use App\Enums\SuggestionAudience;
use InvalidArgumentException;

/**
 * Everything the scorer needs about one candidate pair, pre-loaded by
 * PairCandidateFinder. The scorer never touches the database — that keeps
 * scoring a pure function and keeps the queries batched in one place.
 *
 * The constructor asserts every range invariant the scorer relies on, because
 * nothing else can. There is a single production call site, and its arguments
 * contain several same-typed adjacencies that would swap silently:
 * `viewerProfileId`/`counterpartProfileId` (a swap writes the row for the wrong
 * profile, and the scorer never reads either field, so no score would look
 * wrong), `communitySize`/`venueCapacity`, `viewerOffers`/`counterpartNeeds`,
 * and `averageRating`/`repeatRatio` (a swap pushes a 4.6 rating through the
 * repeat term and out the other side as a score above 100).
 */
final readonly class PairContext
{
    /**
     * @param  array<int, string>  $businessCategories  every category the business declares
     * @param  array<int, int>  $pastAttendance  attendee_count of the community's past events
     * @param  array<int, string>  $viewerOffers  what the viewer can give
     * @param  array<int, string>  $counterpartNeeds  what the counterpart wants
     * @param  array<string, mixed>  $evidence  ids + aggregates for the audit trail
     *
     * @throws InvalidArgumentException
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
    ) {
        foreach (['viewerProfileId' => $viewerProfileId, 'counterpartProfileId' => $counterpartProfileId] as $field => $id) {
            if (trim($id) === '') {
                throw new InvalidArgumentException("PairContext [{$field}] must not be empty.");
            }
        }

        foreach ([
            'communitySize' => $communitySize,
            'venueCapacity' => $venueCapacity,
            'contentDelivered' => $contentDelivered,
            'reviewCount' => $reviewCount,
            'recentEventCount' => $recentEventCount,
        ] as $field => $count) {
            if ($count !== null && $count < 0) {
                throw new InvalidArgumentException("PairContext [{$field}] must not be negative, got [{$count}].");
            }
        }

        if ($distanceKm !== null && $distanceKm < 0.0) {
            throw new InvalidArgumentException("PairContext [distanceKm] must not be negative, got [{$distanceKm}].");
        }

        if ($averageRating !== null && ($averageRating < 0.0 || $averageRating > 5.0)) {
            throw new InvalidArgumentException("PairContext [averageRating] must be between 0 and 5, got [{$averageRating}].");
        }

        if ($repeatRatio !== null && ($repeatRatio < 0.0 || $repeatRatio > 1.0)) {
            throw new InvalidArgumentException("PairContext [repeatRatio] must be a ratio between 0 and 1, got [{$repeatRatio}].");
        }

        foreach ($pastAttendance as $attendance) {
            if (! is_int($attendance) || $attendance <= 0) {
                throw new InvalidArgumentException(
                    'PairContext [pastAttendance] must hold positive integers only; an event with no reported attendance is not evidence of scale and must be filtered out before it reaches here.'
                );
            }
        }
    }
}
