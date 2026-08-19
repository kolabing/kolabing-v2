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
 * Row keys are community-type slugs as stored (the `community_types` seed
 * vocabulary); column keys are business category / venue / product values as
 * stored. Lookups are exact-match: this class performs no normalisation, and
 * callers are expected to pass values they have already normalised themselves
 * (Explore, for one, trims and lower-cases before calling).
 *
 * A missing pairing yields null — "no data for this signal" — rather than 0.0,
 * which would instead assert the pair is a *bad* match.
 *
 * This class is the lookup **table** only. Aggregation policy belongs to each
 * caller and deliberately differs: Explore takes max() across a Kolab's
 * categories, floors at 0.25 when it has none, adds a seeking bonus, and maps
 * unmapped pairs onto a 0.4–0.65 fallback so it can always rank something. The
 * suggestion scorer instead treats an unmapped pair as *no data*, drops the
 * signal and renormalises the remaining weights, because a suggestion must say
 * "we don't know" rather than invent a mid-range score. Do not move either
 * policy in here on the assumption they should match.
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
