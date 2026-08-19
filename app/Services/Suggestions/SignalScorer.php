<?php

declare(strict_types=1);

namespace App\Services\Suggestions;

use App\Enums\SuggestionAudience;
use App\Support\Matching\CategoryFitMatrix;
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
     * @return array{0: float, 1: string, 2: array<string, mixed>}|null
     */
    private function offerNeedFit(PairContext $context): ?array
    {
        $offers = array_values(array_unique($context->viewerOffers));
        $needs = array_values(array_unique($context->counterpartNeeds));

        if ($offers === [] || $needs === []) {
            return null;
        }

        $overlap = array_values(array_intersect($offers, $needs));

        if ($overlap === []) {
            return [0.0, 'offer_need_none', []];
        }

        return [min(1.0, count($overlap) / count($needs)), 'offer_need_overlap', [
            'items' => $overlap,
        ]];
    }

    /**
     * Proven delivery. For a business audience: what the community actually
     * delivered (reels/stories) plus its received ratings. For a community
     * audience: the business's reliability record. Both come out of real
     * collaboration history, which is why this is the signal worth selling.
     *
     * The reason names only the components that are actually non-zero. A
     * completed collaboration with deliverables but no review leaves
     * `averageRating` null, and the mirror case leaves `contentDelivered` at 0 —
     * a single sentence naming both would sell "0 reviews from past partners,
     * rated 0.0" as a reason to collaborate.
     *
     * The two audiences are not mirror images. Only the business audience has a
     * content-only sentence, because content delivered is a *community-side*
     * metric (spec 3.3: the community audience reads a business's
     * `business_partner_statuses` plus reviews received, never content output).
     * A community audience with neither a review nor a rating therefore has no
     * reliability record to show and the signal is dropped, rather than borrowing
     * the community sentence and crediting a business with posts it never made.
     *
     * @return array{0: float, 1: string, 2: array<string, mixed>}|null
     */
    private function deliveryProof(PairContext $context): ?array
    {
        if ($context->reviewCount === 0 && $context->contentDelivered === 0) {
            return null;
        }

        $ratingPart = $context->averageRating !== null
            ? min(1.0, $context->averageRating / 5.0)
            : 0.0;
        $repeatPart = min(1.0, $context->repeatRatio ?? 0.0);
        $contentPart = min(1.0, $context->contentDelivered / 6.0);

        $value = min(1.0, ($ratingPart * 0.4) + ($repeatPart * 0.3) + ($contentPart * 0.3));

        $rating = (float) ($context->averageRating ?? 0);
        $hasRating = $context->averageRating !== null && $context->averageRating > 0.0;

        if ($context->audience === SuggestionAudience::Business) {
            return match (true) {
                $context->contentDelivered > 0 && $hasRating => [$value, 'delivery_proof_community', [
                    'content' => $context->contentDelivered,
                    'rating' => $rating,
                ]],
                $context->contentDelivered > 0 => [$value, 'delivery_proof_content', [
                    'content' => $context->contentDelivered,
                ]],
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
            default => null,
        };
    }

    /**
     * @return array{0: float, 1: string, 2: array<string, mixed>}|null
     */
    private function momentum(PairContext $context): ?array
    {
        if ($context->recentEventCount === 0 && ! $context->hasActiveSeries) {
            return null;
        }

        $value = min(1.0, $context->recentEventCount / 4.0);

        if ($context->hasActiveSeries) {
            $value = min(1.0, $value + 0.25);
        }

        return [$value, 'momentum', [
            'count' => $context->recentEventCount,
            'days' => (int) config('suggestions.momentum_window_days'),
        ]];
    }

    /**
     * Median of the attendance actually reported.
     *
     * PairContext already rejects a zero or negative `pastAttendance` entry, so
     * this filter is defence in depth rather than a live path — it is what would
     * keep a future caller that bypasses the invariant from medianing unreported
     * events into "expect around 0 people".
     */
    private function expectedAttendance(PairContext $context): ?int
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

        return $context->communitySize !== null && $context->communitySize > 0
            ? (int) round($context->communitySize * 0.25)
            : null;
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
