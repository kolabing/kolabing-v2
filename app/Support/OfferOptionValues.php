<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\ProductType;
use App\Enums\VenueType;
use App\Models\OfferOption;
use Throwable;

/**
 * Resolves the valid slugs for the kolab offer taxonomies (offering / deliverable
 * / need / product_type / venue_type) for validation. Reads the admin-managed
 * offer_options table (source of truth); falls back to the hardcoded launch
 * defaults when the table is empty or unavailable (pre-migration / pre-seed) so
 * payloads never break. The fallback slugs MUST stay identical to OfferOptionSeeder
 * (and, for product_type/venue_type, to the ProductType/VenueType enums).
 */
final class OfferOptionValues
{
    /** @var array<int, string> */
    public const OFFERING = [
        'venue', 'venue_space', 'food_drink', 'free_drinks', 'discount',
        'products', 'social_media', 'content_creation', 'sponsorship', 'other',
    ];

    /** @var array<int, string> Offered in return: community offers_in_return / business expects. */
    public const DELIVERABLE = [
        'social_media', 'event_activation', 'product_placement', 'community_reach', 'review_feedback',
    ];

    /** @var array<int, string> Community asks (needs[]). */
    public const NEED = ['venue', 'food_drink', 'sponsor', 'products', 'discount', 'other'];

    /**
     * Active slugs for a kind: DB-backed, falling back to the launch defaults.
     *
     * @return list<string>
     */
    public static function for(string $kind): array
    {
        $fallback = match ($kind) {
            OfferOption::KIND_OFFERING => self::OFFERING,
            OfferOption::KIND_DELIVERABLE => self::DELIVERABLE,
            OfferOption::KIND_NEED => self::NEED,
            OfferOption::KIND_PRODUCT_TYPE => ProductType::values(),
            OfferOption::KIND_VENUE_TYPE => VenueType::values(),
            default => [],
        };

        try {
            $slugs = OfferOption::activeSlugs($kind);

            return $slugs !== [] ? $slugs : array_values($fallback);
        } catch (Throwable) {
            // Table missing (pre-migration) or DB error → keep validating on defaults.
            return array_values($fallback);
        }
    }
}
