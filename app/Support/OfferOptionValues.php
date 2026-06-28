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

    /**
     * @var array<int, string> Offered in return: community offers_in_return / business expects.
     *                         Additive — the original 5 broad slugs stay valid; new granular slugs are appended.
     */
    public const DELIVERABLE = [
        'social_media', 'event_activation', 'product_placement', 'community_reach', 'review_feedback',
        'minimum_attendance', 'minimum_spend', 'tagged_stories', 'instagram_post_reel', 'ugc_content',
        'reviews', 'product_feedback', 'community_photos', 'newsletter_mention', 'long_term_partnership',
        'open_to_ideas',
    ];

    /** @var array<int, string> Community asks (needs[]). */
    public const NEED = ['venue', 'food_drink', 'sponsor', 'products', 'discount', 'other'];

    /** @var array<int, string> What a Kolab is meant to achieve (goal). */
    public const GOAL = [
        'more_visits', 'product_awareness', 'content_tagged_posts', 'reviews', 'sales_revenue',
        'community_event', 'product_testing', 'recurring_partnership', 'community_perk', 'open_to_ideas',
    ];

    /** @var array<int, string> How communities can interact with a product. */
    public const PRODUCT_INTERACTION = [
        'try_samples', 'review_it', 'create_content', 'use_during_event', 'give_feedback',
        'giveaway', 'discount_code', 'sell_during_event', 'open_to_ideas',
    ];

    /** @var array<int, string> "Best for" chips on a venue promotion. */
    public const VENUE_FIT = [
        'coffee', 'brunch', 'dinner', 'drinks', 'wellness', 'shopping', 'workshops', 'content',
        'after_run', 'after_work', 'networking', 'pop_ups', 'recurring_plans',
    ];

    /** @var array<int, string> "Why communities will like this" chips. */
    public const KOLAB_HIGHLIGHT = [
        'good_location', 'nice_space_for_groups', 'great_photo_spot', 'healthy_sporty_offer',
        'free_samples', 'discount_for_members', 'good_for_after_work', 'good_after_workout',
        'recurring_kolabs', 'unique_experience', 'new_product_to_try', 'premium_experience',
        'easy_public_transport', 'outdoor_friendly', 'cozy_indoor_space', 'good_for_content',
    ];

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
            OfferOption::KIND_GOAL => self::GOAL,
            OfferOption::KIND_PRODUCT_INTERACTION => self::PRODUCT_INTERACTION,
            OfferOption::KIND_VENUE_FIT => self::VENUE_FIT,
            OfferOption::KIND_KOLAB_HIGHLIGHT => self::KOLAB_HIGHLIGHT,
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
