<?php

declare(strict_types=1);

namespace App\Support\Matching;

use Illuminate\Support\Str;

/**
 * Single source for the community-type × business-category affinity matrix.
 *
 * Both the Explore feed (App\Services\DiscoveryOpportunityService) and the
 * nightly suggestion scorer read this one copy so the two surfaces can never
 * drift apart.
 *
 * Row keys are community-type slugs as stored (the `community_types` seed
 * vocabulary); column keys are business category / venue / product values as
 * stored. Lookups are exact-match: `score()` performs no normalisation, and
 * callers are expected to pass values they have already put through
 * `normalize()` — or, better, `canonicalise()` — which live here rather than in
 * each caller, so the two surfaces reading this table can never disagree about
 * what a stored `"Food Truck"` or `"tienda-de-deportes"` resolves to.
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
     * Stored category slugs that mean a column of the matrix but are not
     * spelled like one, and that `normalize()` cannot rescue because the
     * difference is a word rather than a separator.
     *
     * Verified against the production database (read-only) on 2026-08-19:
     * `business_profiles.categories` carries Spanish slugs alongside the English
     * vocabulary — `restaurante` (2 rows), `cafeteria` (1), `gimnasio` (1),
     * `tienda-de-deportes` (2), `centro-de-belleza` (1). Without this map each of
     * those businesses silently loses `category_fit` forever.
     *
     * Keys are in normalised form, because `canonicalise()` normalises first.
     *
     * @var array<string, string>
     */
    private const ALIASES = [
        'restaurante' => 'restaurant',
        'cafeteria' => 'cafe',
        'gimnasio' => 'gym',
        'centro_de_belleza' => 'health_beauty',
        'tienda_de_deportes' => 'sports_facility',
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

    /**
     * Put a stored community type or business category into the slug form this
     * table keys on: `" Food Truck"` and `"food-truck"` both become
     * `food_truck`. Every caller of `score()` must go through this first — the
     * lookup is exact-match, so an un-normalised value silently returns null for
     * every row and the caller loses the signal without an error.
     */
    public static function normalize(string $value): string
    {
        return Str::of($value)
            ->trim()
            ->lower()
            ->replace([' ', '-'], '_')
            ->value();
    }

    /**
     * `normalize()` plus the alias map: the full journey from a stored value to
     * a key this table can actually be looked up with. Normalisation collapses
     * separators and case; this additionally folds the Spanish slugs the live
     * `business_profiles.categories` column carries onto their English twins.
     *
     * Normalising is done here rather than demanded of the caller because the
     * lookup is exact-match and the alias keys are themselves normalised: a
     * caller that passed a raw `"Tienda-de-Deportes"` straight in would miss the
     * alias as silently as it would miss the matrix row.
     *
     * Deliberately *not* claiming to canonicalise everything. Six community
     * types in the live data (`art_creative_community`,
     * `sustainability_community`, `photography_community`, `hobby_community`,
     * `dance_community`, `other`) have no row here at all. They are not aliases
     * of anything and inventing a mapping would score a pair on a resemblance
     * nobody checked, so those pairings keep returning null and their callers
     * drop the signal honestly.
     */
    public static function canonicalise(string $value): string
    {
        $normalized = self::normalize($value);

        return self::ALIASES[$normalized] ?? $normalized;
    }
}
