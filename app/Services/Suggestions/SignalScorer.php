<?php

declare(strict_types=1);

namespace App\Services\Suggestions;

use App\Enums\SuggestionAudience;
use App\Support\Matching\CategoryFitMatrix;
use Illuminate\Support\Facades\Lang;

/**
 * Scores one candidate pair across six signals. Pure: no database access, no
 * randomness, no clock. A signal with no data behind it returns null, is
 * dropped from the weighted sum, and its weight is removed from the
 * denominator — so a cold-start profile is scored fairly on what we do know
 * and labelled with a lower `confidence` instead of being unfairly penalised.
 */
class SignalScorer
{
    /**
     * @return array{score: int, confidence: string, signals: array<int, array<string, mixed>>}
     */
    public function score(PairContext $context): array
    {
        $weights = config('suggestions.weights');

        $raw = [
            'category_fit' => $this->categoryFit($context),
            'location_fit' => $this->locationFit($context),
            'scale_fit' => $this->scaleFit($context),
            'offer_need_fit' => $this->offerNeedFit($context),
            'delivery_proof' => $this->deliveryProof($context),
            'momentum' => $this->momentum($context),
        ];

        $signals = [];
        $weightedSum = 0.0;
        $availableWeight = 0.0;

        foreach ($raw as $key => $result) {
            if ($result === null) {
                continue;
            }

            [$value, $reason] = $result;
            $weight = (float) $weights[$key];

            $weightedSum += $weight * $value;
            $availableWeight += $weight;

            $signals[] = [
                'key' => $key,
                'label' => $this->label($key),
                'weight' => $weight,
                'score' => round($value, 3),
                'reason' => $reason,
            ];
        }

        $score = $availableWeight > 0.0
            ? (int) round($weightedSum / $availableWeight * 100)
            : 0;

        return [
            'score' => $score,
            'confidence' => $this->confidence($availableWeight),
            'signals' => $signals,
        ];
    }

    /**
     * A business declares several categories, so score every one and keep the
     * best. The maximum is taken over the *non-null* results only: an unmapped
     * pairing is no data, never a mid-range guess, which is where this policy
     * deliberately parts ways with Explore's ranking fallback.
     *
     * @return array{0: float, 1: string}|null
     */
    private function categoryFit(PairContext $context): ?array
    {
        $best = null;
        $bestCategory = null;

        foreach ($context->businessCategories as $category) {
            $score = CategoryFitMatrix::score($context->communityType, $category);

            if ($score !== null && ($best === null || $score > $best)) {
                $best = $score;
                $bestCategory = $category;
            }
        }

        if ($best === null) {
            return null;
        }

        return [$best, __('suggestions.reason.category_fit', [
            'community_type' => $this->vocabulary('community_type', (string) $context->communityType),
            'business_category' => $this->vocabulary('business_category', (string) $bestCategory),
        ])];
    }

    /**
     * @return array{0: float, 1: string}|null
     */
    private function locationFit(PairContext $context): ?array
    {
        if ($context->distanceKm !== null) {
            $max = (float) config('suggestions.max_distance_km');
            $value = max(0.0, 1.0 - ($context->distanceKm / $max));

            return [$value, __('suggestions.reason.location_distance', [
                'km' => number_format($context->distanceKm, 1),
            ])];
        }

        if ($context->viewerCityId === null || $context->counterpartCityId === null) {
            return null;
        }

        return $context->viewerCityId === $context->counterpartCityId
            ? [1.0, __('suggestions.reason.location_same_city')]
            : [0.0, __('suggestions.reason.location_other_city')];
    }

    /**
     * Expected attendance against the venue that would host it. Perfect fit is
     * "fills the room without overflowing"; both under-filling and overflowing
     * lose points, and overflow is reported so the copy can name the constraint.
     *
     * @return array{0: float, 1: string}|null
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

        return [$value, __('suggestions.reason.scale_fit', [
            'expected' => $expected,
            'capacity' => $context->venueCapacity,
        ])];
    }

    /**
     * @return array{0: float, 1: string}|null
     */
    private function offerNeedFit(PairContext $context): ?array
    {
        if ($context->viewerOffers === [] || $context->counterpartNeeds === []) {
            return null;
        }

        $overlap = array_values(array_intersect($context->viewerOffers, $context->counterpartNeeds));
        $value = count($overlap) / count($context->counterpartNeeds);

        if ($overlap === []) {
            return [0.0, __('suggestions.reason.offer_need_none')];
        }

        return [min(1.0, $value), __('suggestions.reason.offer_need_overlap', [
            'items' => implode(', ', array_map(
                fn (string $item): string => str_replace('_', ' ', $item),
                $overlap
            )),
        ])];
    }

    /**
     * Proven delivery. For a business audience: what the community actually
     * delivered (reels/stories) plus its received ratings. For a community
     * audience: the business's reliability record. Both come out of real
     * collaboration history, which is why this is the signal worth selling.
     *
     * @return array{0: float, 1: string}|null
     */
    private function deliveryProof(PairContext $context): ?array
    {
        if ($context->reviewCount === 0 && $context->contentDelivered === 0) {
            return null;
        }

        $ratingPart = $context->averageRating !== null
            ? min(1.0, $context->averageRating / 5.0)
            : 0.0;
        $repeatPart = $context->repeatRatio ?? 0.0;
        $contentPart = min(1.0, $context->contentDelivered / 6.0);

        $value = ($ratingPart * 0.4) + ($repeatPart * 0.3) + ($contentPart * 0.3);

        if ($context->audience === SuggestionAudience::Business) {
            return [$value, __('suggestions.reason.delivery_proof_community', [
                'content' => $context->contentDelivered,
                'rating' => number_format((float) ($context->averageRating ?? 0), 1),
            ])];
        }

        return [$value, __('suggestions.reason.delivery_proof_business', [
            'reviews' => $context->reviewCount,
            'rating' => number_format((float) ($context->averageRating ?? 0), 1),
        ])];
    }

    /**
     * @return array{0: float, 1: string}|null
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

        return [$value, __('suggestions.reason.momentum', [
            'count' => $context->recentEventCount,
            'days' => (int) config('suggestions.momentum_window_days'),
        ])];
    }

    private function expectedAttendance(PairContext $context): ?int
    {
        if ($context->pastAttendance !== []) {
            $values = $context->pastAttendance;
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

    private function confidence(float $availableWeight): string
    {
        $thresholds = config('suggestions.confidence_thresholds');

        return match (true) {
            $availableWeight >= (float) $thresholds['high'] => 'high',
            $availableWeight >= (float) $thresholds['medium'] => 'medium',
            default => 'low',
        };
    }

    private function label(string $key): string
    {
        return __('suggestions.signal.'.$key);
    }

    /**
     * Human label for a matrix slug, falling back to the de-underscored slug
     * so a matrix that grows a column can never render an empty reason line.
     */
    private function vocabulary(string $group, string $value): string
    {
        $key = 'suggestions.vocabulary.'.$group.'.'.$value;

        return Lang::has($key)
            ? (string) __($key)
            : str_replace('_', ' ', $value);
    }
}
