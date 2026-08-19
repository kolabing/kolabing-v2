<?php

declare(strict_types=1);

namespace App\Support\Matching;

/**
 * Single source for the community-type × business-category affinity matrix.
 *
 * Both the Explore feed (App\Services\DiscoveryOpportunityService) and the
 * nightly suggestion scorer read this one copy so the two surfaces can never
 * drift apart.
 *
 * Keys on both levels are the underscore-separated, lower-cased forms produced
 * by the callers' own normalisation; this class performs no normalisation of
 * its own. A missing pairing yields null — "no data for this signal" — rather
 * than 0.0, which would instead assert the pair is a *bad* match. Callers that
 * weight signals drop a null signal and renormalise the remaining weights.
 */
final class CategoryFitMatrix
{
    /**
     * @var array<string, array<string, float>>
     */
    public const MATRIX = [
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
     * Score one (community type, business category) pairing.
     *
     * Returns null when either side is missing or the pairing is not in the
     * matrix, so a weighted scorer can skip the signal entirely.
     */
    public static function score(?string $communityType, ?string $businessCategory): ?float
    {
        if ($communityType === null || $businessCategory === null) {
            return null;
        }

        return self::MATRIX[$communityType][$businessCategory] ?? null;
    }
}
