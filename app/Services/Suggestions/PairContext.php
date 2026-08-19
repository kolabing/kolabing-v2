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
 * wrong), `communitySize`/`venueCapacity`, the four offer/need arrays, and
 * `averageRating`/`repeatRatio` (a swap pushes a 4.6 rating through the repeat
 * term and out the other side as a score above 100).
 *
 * The offer/need arrays come as two mirrored pairs, deliberately not collapsed
 * into one: `viewerOffers ∩ counterpartNeeds` is what the viewer would *give*
 * (the Kolab's `offer`), and `counterpartOffers ∩ viewerNeeds` is what it would
 * *ask for* in return (`expects` on a business Kolab, the `required_if` `needs`
 * on a `community_seeking` one). One intersection cannot serve both slots
 * because the vocabularies differ: a business gives from `OfferOption`'s
 * `offering` kind and asks from `deliverable`, while a community gives from
 * `deliverable` and asks from `need`. A single array would fill one required
 * field and leave the other unfillable.
 *
 * `contentDelivered` and `completedCollaborations` are the one pair no invariant
 * can separate — both are counts, both are `>= 0`. They are the
 * audience-specific halves of `delivery_proof`'s volume term: the caller
 * populates the half its audience uses and leaves the other 0. Only the
 * scorer's audience check keeps them apart, so that selection is pinned by its
 * own test rather than by a range assertion here. Over-populating both is
 * harmless (the unused half is ignored); swapping them is not, which is why this
 * note exists.
 */
final readonly class PairContext
{
    /**
     * @param  array<int, string>  $businessCategories  every category the business declares
     * @param  array<int, int>  $pastAttendance  attendee_count of the community's past events
     * @param  array<int, string>  $viewerOffers  what the viewer can give
     * @param  array<int, string>  $counterpartNeeds  what the counterpart wants
     * @param  array<int, string>  $counterpartOffers  what the counterpart can give
     * @param  array<int, string>  $viewerNeeds  what the viewer wants
     * @param  int  $contentDelivered  posts/stories a community delivered; the business audience's volume term
     * @param  int  $completedCollaborations  business_partner_statuses.completed_kolabs_count; the community audience's volume term
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
        public array $counterpartOffers,
        public array $viewerNeeds,
        public ?float $averageRating,
        public ?float $repeatRatio,
        public int $contentDelivered,
        public int $completedCollaborations,
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
            'completedCollaborations' => $completedCollaborations,
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
