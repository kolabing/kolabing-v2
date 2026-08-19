<?php

declare(strict_types=1);

namespace App\Services\Suggestions;

use App\Enums\SuggestionAudience;
use App\Support\Matching\CategoryFitMatrix;
use App\Support\Matching\OfferTypeAliases;
use LogicException;

/**
 * Scores one candidate pair across six signals. Pure: no database access, no
 * randomness, no clock, and no localisation. A signal with no data behind it
 * returns null, is dropped from the weighted sum, and its weight is removed
 * from the denominator — so a cold-start profile is scored fairly on what we do
 * know and labelled with a lower `confidence` instead of being unfairly
 * penalised.
 *
 * A signal carries a `reason_key` plus raw `reason_params`, never a finished
 * sentence: generation runs in a nightly command under the app's default
 * locale, so a rendered reason would reach every reader in that one language.
 * SignalReasonRenderer turns the keys into a sentence at read time, in the
 * reader's locale. `reason_key` is separate from `key` because one signal picks
 * different sentences depending on its data.
 */
class SignalScorer
{
    /**
     * Volume divisor for the business audience: the deliverables of roughly one
     * full Kolab (a couple of posts plus a set of stories). A community that has
     * delivered that much has proved it delivers.
     */
    private const FULL_CONTENT_SET = 6.0;

    /**
     * Volume divisor for the community audience, anchored on a threshold the
     * product already ships rather than an invented number:
     * `gamification_business.tiers.community_favourite.min_completed_kolabs` is
     * 8, the count at which a business earns the top partner tier and wears the
     * badge on its profile. Saturating there keeps the scorer and the badge
     * telling the reader the same story: 1 completed Kolab (Active Partner)
     * scores 0.125, 3 (Trusted Partner) 0.375, 8 or more a full 1.0.
     *
     * Deliberately a constant here rather than a read of the gamification
     * config: a tier retune must not silently shift every suggestion score, the
     * failure mode this class already guards against for its weights.
     */
    private const FULL_COLLABORATION_RECORD = 8.0;

    /**
     * @return array{score: int, confidence: string, signals: array<int, array{key: string, reason_key: string, reason_params: array<string, mixed>, weight: float, score: float}>}
     */
    public function score(PairContext $context): array
    {
        $raw = [
            'category_fit' => $this->categoryFit($context),
            'location_fit' => $this->locationFit($context),
            'scale_fit' => $this->scaleFit($context),
            'offer_need_fit' => $this->offerNeedFit($context),
            'delivery_proof' => $this->deliveryProof($context),
            'momentum' => $this->momentum($context),
        ];

        $weights = $this->weights(array_keys($raw));

        $signals = [];
        $weightedSum = 0.0;
        $availableWeight = 0.0;
        $availableBasisPoints = 0;

        foreach ($raw as $key => $result) {
            if ($result === null) {
                continue;
            }

            [$value, $reasonKey, $reasonParams] = $result;
            $weight = $weights[$key];

            $weightedSum += $weight * $value;
            $availableWeight += $weight;
            $availableBasisPoints += (int) round($weight * 10000);

            $signals[] = [
                'key' => $key,
                'reason_key' => $reasonKey,
                'reason_params' => $reasonParams,
                'weight' => $weight,
                'score' => round($value, 3),
            ];
        }

        $score = $availableWeight > 0.0
            ? max(0, min(100, (int) round($weightedSum / $availableWeight * 100)))
            : 0;

        return [
            'score' => $score,
            'confidence' => $this->confidence($availableBasisPoints),
            'signals' => $signals,
        ];
    }

    /**
     * A signal whose weight key is missing from config reads as 0.0, which drops
     * it out of *both* the numerator and the denominator: every score shifts
     * fleet-wide and confidence loses a band, with no error raised anywhere. The
     * config docblock openly invites tuning, so validate the key set rather than
     * trust it — an extra key is just as wrong as a missing one, since it is
     * weight the renormalisation will never see.
     *
     * @param  array<int, string>  $signalKeys
     * @return array<string, float>
     */
    private function weights(array $signalKeys): array
    {
        return $this->floatConfigMap('suggestions.weights', $signalKeys);
    }

    /**
     * The sibling of the weights failure above, and worse: a missing or renamed
     * `confidence_thresholds` key reads as `(float) null`, every threshold
     * becomes 0.0, and *every* suggestion in the batch is silently labelled
     * `high` — the one label a reader is meant to trust.
     *
     * @return array<string, float>
     */
    private function confidenceThresholds(): array
    {
        return $this->floatConfigMap('suggestions.confidence_thresholds', ['high', 'medium']);
    }

    /**
     * Reads a config map of floats, refusing anything but exactly the expected
     * key set. A published config file can be edited, renamed or dropped without
     * a code change, so neither map may be trusted to be shaped as written.
     *
     * @param  array<int, string>  $expectedKeys
     * @return array<string, float>
     */
    private function floatConfigMap(string $configKey, array $expectedKeys): array
    {
        $values = config($configKey);

        if (! is_array($values)) {
            throw new LogicException(sprintf(
                'config(%s) must be an array keyed by [%s], got [%s].',
                $configKey,
                implode(', ', $expectedKeys),
                get_debug_type($values)
            ));
        }

        $missing = array_diff($expectedKeys, array_keys($values));
        $unexpected = array_diff(array_keys($values), $expectedKeys);

        if ($missing !== [] || $unexpected !== []) {
            throw new LogicException(sprintf(
                'config(%s) must key exactly [%s]; missing [%s], unexpected [%s].',
                $configKey,
                implode(', ', $expectedKeys),
                implode(', ', $missing),
                implode(', ', $unexpected)
            ));
        }

        return array_map(static fn (mixed $value): float => (float) $value, $values);
    }

    /**
     * A business declares several categories, so score every one and keep the
     * best. The maximum is taken over the *non-null* results only: an unmapped
     * pairing is no data, never a mid-range guess, which is where this policy
     * deliberately parts ways with Explore's ranking fallback.
     *
     * Both sides are normalised first: the matrix is exact-match, so a stored
     * `"Cafe"` or `"Food & Drink"` would otherwise miss every row forever and
     * silently take this signal's weight with it.
     *
     * @return array{0: float, 1: string, 2: array<string, mixed>}|null
     */
    private function categoryFit(PairContext $context): ?array
    {
        $communityType = $context->communityType !== null
            ? CategoryFitMatrix::normalize($context->communityType)
            : null;

        $best = null;
        $bestCategory = null;

        foreach ($context->businessCategories as $category) {
            $normalized = CategoryFitMatrix::normalize($category);
            $score = CategoryFitMatrix::score($communityType, $normalized);

            if ($score !== null && ($best === null || $score > $best)) {
                $best = $score;
                $bestCategory = $normalized;
            }
        }

        if ($best === null) {
            return null;
        }

        return [$best, 'category_fit', [
            'community_type' => (string) $communityType,
            'business_category' => (string) $bestCategory,
        ]];
    }

    /**
     * @return array{0: float, 1: string, 2: array<string, mixed>}|null
     */
    private function locationFit(PairContext $context): ?array
    {
        $max = (float) config('suggestions.max_distance_km');

        if ($context->distanceKm !== null && $max > 0.0) {
            $value = max(0.0, 1.0 - ($context->distanceKm / $max));

            return [$value, 'location_distance', ['km' => $context->distanceKm]];
        }

        if ($context->viewerCityId === null || $context->counterpartCityId === null) {
            return null;
        }

        return $context->viewerCityId === $context->counterpartCityId
            ? [1.0, 'location_same_city', []]
            : [0.0, 'location_other_city', []];
    }

    /**
     * Expected attendance against the venue that would host it. Perfect fit is
     * "fills the room without overflowing"; both under-filling and overflowing
     * lose points, and overflow is reported so the copy can name the constraint.
     *
     * @return array{0: float, 1: string, 2: array<string, mixed>}|null
     */
    private function scaleFit(PairContext $context): ?array
    {
        $expected = $this->expectedAttendance($context);

        if ($expected === null || $context->venueCapacity === null || $context->venueCapacity <= 0) {
            return null;
        }

        $ratio = $expected / $context->venueCapacity;

        $value = match (true) {
            $ratio <= 1.0 => $ratio,
            default => max(0.0, 1.0 - (($ratio - 1.0) / 2.0)),
        };

        return [$value, 'scale_fit', [
            'expected' => $expected,
            'capacity' => $context->venueCapacity,
        ]];
    }

    /**
     * How much of what the counterpart asked for the viewer already offers.
     *
     * Both sides are reduced to their *informative* canonical slugs first, which
     * drops `other`: "other" matching "other" is not evidence of a fit, and
     * counting it would both inflate the coverage ratio and pre-fill a Kolab
     * asking for `other`. A side left with nothing informative is no data — null,
     * not a 0.0 that would assert the pair is a bad match.
     *
     * @return array{0: float, 1: string, 2: array<string, mixed>}|null
     */
    private function offerNeedFit(PairContext $context): ?array
    {
        $needs = OfferTypeAliases::canonicalSet($context->counterpartNeeds);

        if (OfferTypeAliases::canonicalSet($context->viewerOffers) === [] || $needs === []) {
            return null;
        }

        $overlap = $this->offerOverlap($context);

        if ($overlap === []) {
            return [0.0, 'offer_need_none', []];
        }

        return [min(1.0, count($overlap) / count($needs)), 'offer_need_overlap', [
            'items' => $overlap,
        ]];
    }

    /**
     * What the viewer can give that the counterpart actually wants.
     *
     * Public because FormatSuggester proposes the same list as the Kolab's offer,
     * and the card shows it twice — once as this signal's reason line, once as
     * the proposed format. Two implementations would eventually disagree.
     *
     * Compared on canonical form and returned in the viewer's own spelling: a
     * business declares `venue_space` where a community asks for `venue`, and a
     * plain intersection would call that no overlap — a false 0.0, describing
     * the pair as an actively bad match. The returned spelling has to stay the
     * viewer's, because this list pre-fills a Kolab field validated against the
     * viewer's own taxonomy.
     *
     * @return array<int, string>
     */
    public function offerOverlap(PairContext $context): array
    {
        return OfferTypeAliases::intersect($context->viewerOffers, $context->counterpartNeeds);
    }

    /**
     * Proven delivery, in one shape across both audiences —
     * `0.4 x rating + 0.3 x repeat + 0.3 x volume` — but with an
     * audience-specific `volume`, because the two sides prove delivery with
     * different artefacts (spec 3.3):
     *
     * - business audience: volume is the reels and stories the community
     *   actually posted for past Kolabs (`contentDelivered`).
     * - community audience: volume is `completedCollaborations`, from
     *   `business_partner_statuses.completed_kolabs_count`. A business does not
     *   deliver posts, so reading content here would score *and* describe the
     *   wrong subject.
     *
     * `PairContext` carries both counts and the arm the audience does not use is
     * zero. The two are indistinguishable by range, so nothing but this
     * selection keeps them apart — which is why a test pins that a business
     * audience never reads `completedCollaborations`.
     *
     * The reason names only the components that are actually non-zero. A
     * completed collaboration with deliverables but no review leaves
     * `averageRating` null, and the mirror case leaves the volume count at 0 — a
     * single sentence naming both would sell "0 reviews from past partners,
     * rated 0.0" as a reason to collaborate. The guard below is audience-correct
     * for the same reason the volume term is, and it guarantees at least one
     * component is non-zero, which is what makes the fallback arm of each branch
     * truthful.
     *
     * @return array{0: float, 1: string, 2: array<string, mixed>}|null
     */
    private function deliveryProof(PairContext $context): ?array
    {
        $isBusinessAudience = $context->audience === SuggestionAudience::Business;

        $volumeCount = $isBusinessAudience
            ? $context->contentDelivered
            : $context->completedCollaborations;

        if ($context->reviewCount === 0 && $volumeCount === 0) {
            return null;
        }

        $ratingPart = $context->averageRating !== null
            ? min(1.0, $context->averageRating / 5.0)
            : 0.0;
        $repeatPart = min(1.0, $context->repeatRatio ?? 0.0);
        $volumePart = $isBusinessAudience
            ? min(1.0, $volumeCount / self::FULL_CONTENT_SET)
            : min(1.0, $volumeCount / self::FULL_COLLABORATION_RECORD);

        $value = min(1.0, ($ratingPart * 0.4) + ($repeatPart * 0.3) + ($volumePart * 0.3));

        $rating = (float) ($context->averageRating ?? 0);
        $hasRating = $context->averageRating !== null && $context->averageRating > 0.0;

        if ($isBusinessAudience) {
            return match (true) {
                $volumeCount > 0 && $hasRating => [$value, 'delivery_proof_community', [
                    'content' => $volumeCount,
                    'rating' => $rating,
                ]],
                $volumeCount > 0 => [$value, 'delivery_proof_content', ['content' => $volumeCount]],
                $hasRating => [$value, 'delivery_proof_rating', ['rating' => $rating]],
                default => [$value, 'delivery_proof_reviews', ['reviews' => $context->reviewCount]],
            };
        }

        return match (true) {
            $context->reviewCount > 0 && $hasRating => [$value, 'delivery_proof_business', [
                'reviews' => $context->reviewCount,
                'rating' => $rating,
            ]],
            $context->reviewCount > 0 => [$value, 'delivery_proof_reviews', [
                'reviews' => $context->reviewCount,
            ]],
            $hasRating => [$value, 'delivery_proof_rating', ['rating' => $rating]],
            default => [$value, 'delivery_proof_collaborations', ['collaborations' => $volumeCount]],
        };
    }

    /**
     * How live the counterpart is, in whichever artefact its side of the platform
     * produces: a community runs events (and may hold a live `event_series`
     * cadence, worth a bonus because a standing rule is a stronger commitment
     * than a run of one-offs), while a business publishes Kolabs and starts
     * collaborations.
     *
     * The two counts are not interchangeable and are selected by audience, for
     * the same reason `delivery_proof`'s volume term is: the reason line is a
     * claim about the *partner*. Reading a community viewer's own event count
     * here would describe the reader back to themselves under a sentence about
     * the business, and leaving the signal to drop instead would put every
     * community-audience card a confidence band lower than every business one —
     * a systematic asymmetry rather than honesty about missing data.
     *
     * The threshold is `suggestions.active_cadence` rather than a constant: it is
     * window-relative, it pairs with `momentum_window_days`, and it is an
     * admitted first guess that sets a number a reader sees. Unlike
     * `FULL_COLLABORATION_RECORD`, which stays a constant precisely so a
     * gamification tier retune cannot move suggestion scores, this mirrors
     * nothing. `(float) config(null)` is 0.0 and PHP 8 throws DivisionByZeroError
     * on it, so a missing or zeroed key drops the signal rather than killing the
     * whole nightly batch.
     *
     * @return array{0: float, 1: string, 2: array<string, mixed>}|null
     */
    private function momentum(PairContext $context): ?array
    {
        $days = (int) config('suggestions.momentum_window_days');
        $cadence = (float) config('suggestions.active_cadence');

        if ($cadence <= 0.0) {
            return null;
        }

        if ($context->audience === SuggestionAudience::Community) {
            if ($context->recentActivityCount === 0) {
                return null;
            }

            return [min(1.0, $context->recentActivityCount / $cadence), 'momentum_business', [
                'count' => $context->recentActivityCount,
                'days' => $days,
            ]];
        }

        if ($context->recentEventCount === 0 && ! $context->hasActiveSeries) {
            return null;
        }

        $value = min(1.0, $context->recentEventCount / $cadence);

        if ($context->hasActiveSeries) {
            $value = min(1.0, $value + 0.25);
        }

        return [$value, 'momentum', [
            'count' => $context->recentEventCount,
            'days' => $days,
        ]];
    }

    /**
     * Median of the attendance actually reported, falling back to a quarter of
     * the declared community size.
     *
     * Public because FormatSuggester proposes the same number as the event's
     * expected attendance, and the card shows it twice — once as this signal's
     * reason line, once as the proposed format. Two implementations would
     * eventually disagree, and the reader would see the disagreement.
     *
     * PairContext already rejects a zero or negative `pastAttendance` entry, so
     * this filter is defence in depth rather than a live path — it is what would
     * keep a future caller that bypasses the invariant from medianing unreported
     * events into "expect around 0 people". The size fallback needs the same
     * protection for a real reason: a community of one rounds to zero, and
     * "expect around 0 people" is a claim, not an absence of one. A fraction
     * misconfigured to nothing degrades the same way — no number rather than a
     * zero one.
     */
    public function expectedAttendance(PairContext $context): ?int
    {
        $values = array_values(array_filter(
            $context->pastAttendance,
            static fn (int $attendance): bool => $attendance > 0
        ));

        if ($values !== []) {
            sort($values);
            $middle = (int) floor((count($values) - 1) / 2);

            return (int) round(count($values) % 2 === 1
                ? $values[$middle]
                : ($values[$middle] + $values[$middle + 1]) / 2);
        }

        if ($context->communitySize === null || $context->communitySize <= 0) {
            return null;
        }

        $share = (int) round(
            $context->communitySize * (float) config('suggestions.community_size_attendance_fraction')
        );

        return $share > 0 ? $share : null;
    }

    /**
     * Compared in integer basis points, not floats. `location_fit + scale_fit +
     * delivery_proof` is 0.45 in decimal — exactly the `medium` threshold — but
     * accumulating those three doubles yields 0.44999999999999995559, which a
     * float comparison files as `low`. Three further subsets of the shipped
     * weights do the same.
     */
    private function confidence(int $availableBasisPoints): string
    {
        $thresholds = $this->confidenceThresholds();

        return match (true) {
            $availableBasisPoints >= (int) round($thresholds['high'] * 10000) => 'high',
            $availableBasisPoints >= (int) round($thresholds['medium'] * 10000) => 'medium',
            default => 'low',
        };
    }
}
