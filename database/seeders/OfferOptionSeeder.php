<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\OfferOption;
use Illuminate\Database\Seeder;

/**
 * Seeds the three kolab offer taxonomies with the EXACT slugs hardcoded today, so
 * existing payloads keep validating after the DB becomes the source of truth:
 *  - offering    → CreateKolabRequest::OFFERING_VALUES (business `offering[]`)
 *  - deliverable → community `offers_in_return[]` / business `expects[]`
 *  - need        → community `needs[]`
 * Labels/icons can be edited in /admin/offer-options; slugs are the wire contract.
 */
class OfferOptionSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->options() as $kind => $items) {
            foreach ($items as $index => $item) {
                $existing = OfferOption::query()
                    ->where('kind', $kind)->where('slug', $item['slug'])->first();

                $attributes = [
                    'name' => $item['name'],
                    'icon' => $item['icon'],
                    'sort_order' => $index + 1,
                ];

                if ($existing) {
                    // Re-seed: refresh name/icon/order, but never clobber an admin's
                    // is_active toggle.
                    $existing->update($attributes);

                    continue;
                }

                OfferOption::query()->create($attributes + [
                    'kind' => $kind,
                    'slug' => $item['slug'],
                    'is_active' => true,
                ]);
            }
        }
    }

    /**
     * @return array<string, list<array{name: string, slug: string, icon: string}>>
     */
    private function options(): array
    {
        return [
            // What a BUSINESS offers (offering[]).
            OfferOption::KIND_OFFERING => [
                ['name' => 'Venue', 'slug' => 'venue', 'icon' => 'building'],
                ['name' => 'Venue Space', 'slug' => 'venue_space', 'icon' => 'door-open'],
                ['name' => 'Food & Drink', 'slug' => 'food_drink', 'icon' => 'utensils'],
                ['name' => 'Free Drinks', 'slug' => 'free_drinks', 'icon' => 'wine'],
                ['name' => 'Discount', 'slug' => 'discount', 'icon' => 'percent'],
                ['name' => 'Products', 'slug' => 'products', 'icon' => 'package'],
                ['name' => 'Social Media', 'slug' => 'social_media', 'icon' => 'share-2'],
                ['name' => 'Content Creation', 'slug' => 'content_creation', 'icon' => 'camera'],
                ['name' => 'Sponsorship', 'slug' => 'sponsorship', 'icon' => 'gift'],
                ['name' => 'Other', 'slug' => 'other', 'icon' => 'ellipsis'],
            ],
            // What's offered IN RETURN (community offers_in_return[] / business expects[]).
            // Additive: the original 5 broad rows stay — do not remove them.
            OfferOption::KIND_DELIVERABLE => [
                ['name' => 'Social Media', 'slug' => 'social_media', 'icon' => 'share-2'],
                ['name' => 'Event Activation', 'slug' => 'event_activation', 'icon' => 'sparkles'],
                ['name' => 'Product Placement', 'slug' => 'product_placement', 'icon' => 'package'],
                ['name' => 'Community Reach', 'slug' => 'community_reach', 'icon' => 'users'],
                ['name' => 'Review & Feedback', 'slug' => 'review_feedback', 'icon' => 'star'],
                ['name' => 'Minimum Attendance', 'slug' => 'minimum_attendance', 'icon' => 'users'],
                ['name' => 'Minimum Revenue / Spend', 'slug' => 'minimum_spend', 'icon' => 'banknote'],
                ['name' => 'Tagged Stories', 'slug' => 'tagged_stories', 'icon' => 'at-sign'],
                ['name' => 'Instagram Post or Reel', 'slug' => 'instagram_post_reel', 'icon' => 'instagram'],
                ['name' => 'UGC / Content', 'slug' => 'ugc_content', 'icon' => 'camera'],
                ['name' => 'Reviews', 'slug' => 'reviews', 'icon' => 'star'],
                ['name' => 'Product Feedback', 'slug' => 'product_feedback', 'icon' => 'message-square'],
                ['name' => 'Community Photos', 'slug' => 'community_photos', 'icon' => 'image'],
                ['name' => 'Newsletter Mention', 'slug' => 'newsletter_mention', 'icon' => 'mail'],
                ['name' => 'Long-Term Partnership', 'slug' => 'long_term_partnership', 'icon' => 'handshake'],
                ['name' => 'Open to Ideas', 'slug' => 'open_to_ideas', 'icon' => 'lightbulb'],
            ],
            // What a COMMUNITY asks for (needs[]).
            OfferOption::KIND_NEED => [
                ['name' => 'Venue', 'slug' => 'venue', 'icon' => 'building'],
                ['name' => 'Food & Drink', 'slug' => 'food_drink', 'icon' => 'utensils'],
                ['name' => 'Sponsor', 'slug' => 'sponsor', 'icon' => 'gift'],
                ['name' => 'Products', 'slug' => 'products', 'icon' => 'package'],
                ['name' => 'Discount', 'slug' => 'discount', 'icon' => 'percent'],
                ['name' => 'Other', 'slug' => 'other', 'icon' => 'ellipsis'],
            ],
            // Product-promotion picker + product onboarding (slugs match App\Enums\ProductType).
            OfferOption::KIND_PRODUCT_TYPE => [
                ['name' => 'Food Product', 'slug' => 'food_product', 'icon' => 'utensils'],
                ['name' => 'Beverage', 'slug' => 'beverage', 'icon' => 'wine'],
                ['name' => 'Health & Beauty', 'slug' => 'health_beauty', 'icon' => 'sparkles'],
                ['name' => 'Sports Equipment', 'slug' => 'sports_equipment', 'icon' => 'dumbbell'],
                ['name' => 'Fashion', 'slug' => 'fashion', 'icon' => 'shirt'],
                ['name' => 'Tech Gadget', 'slug' => 'tech_gadget', 'icon' => 'smartphone'],
                ['name' => 'Experience / Service', 'slug' => 'experience_service', 'icon' => 'ticket'],
                ['name' => 'Other', 'slug' => 'other', 'icon' => 'ellipsis'],
            ],
            // Venue onboarding + venue-promotion picker (slugs match App\Enums\VenueType).
            OfferOption::KIND_VENUE_TYPE => [
                ['name' => 'Restaurant', 'slug' => 'restaurant', 'icon' => 'utensils'],
                ['name' => 'Cafe', 'slug' => 'cafe', 'icon' => 'coffee'],
                ['name' => 'Bar / Lounge', 'slug' => 'bar_lounge', 'icon' => 'martini'],
                ['name' => 'Hotel', 'slug' => 'hotel', 'icon' => 'bed-double'],
                ['name' => 'Coworking', 'slug' => 'coworking', 'icon' => 'briefcase'],
                ['name' => 'Sports Facility', 'slug' => 'sports_facility', 'icon' => 'dumbbell'],
                ['name' => 'Event Space', 'slug' => 'event_space', 'icon' => 'party-popper'],
                ['name' => 'Rooftop', 'slug' => 'rooftop', 'icon' => 'sun'],
                ['name' => 'Beach Club', 'slug' => 'beach_club', 'icon' => 'umbrella'],
                ['name' => 'Retail Store', 'slug' => 'retail_store', 'icon' => 'store'],
                ['name' => 'Other', 'slug' => 'other', 'icon' => 'ellipsis'],
            ],
            // What a business Kolab is meant to achieve.
            OfferOption::KIND_GOAL => [
                ['name' => 'More Visits', 'slug' => 'more_visits', 'icon' => 'map-pin'],
                ['name' => 'Product Awareness', 'slug' => 'product_awareness', 'icon' => 'megaphone'],
                ['name' => 'Content / Tagged Posts', 'slug' => 'content_tagged_posts', 'icon' => 'camera'],
                ['name' => 'Reviews', 'slug' => 'reviews', 'icon' => 'star'],
                ['name' => 'Sales / Revenue', 'slug' => 'sales_revenue', 'icon' => 'banknote'],
                ['name' => 'Community Event', 'slug' => 'community_event', 'icon' => 'calendar'],
                ['name' => 'Product Testing', 'slug' => 'product_testing', 'icon' => 'flask-conical'],
                ['name' => 'Recurring Partnership', 'slug' => 'recurring_partnership', 'icon' => 'repeat'],
                ['name' => 'Community Perk / Member Discount', 'slug' => 'community_perk', 'icon' => 'percent'],
                ['name' => 'Open to Ideas', 'slug' => 'open_to_ideas', 'icon' => 'lightbulb'],
            ],
            // How communities can interact with a product (product promotion only).
            OfferOption::KIND_PRODUCT_INTERACTION => [
                ['name' => 'Try Samples', 'slug' => 'try_samples', 'icon' => 'package'],
                ['name' => 'Review It', 'slug' => 'review_it', 'icon' => 'star'],
                ['name' => 'Create Content', 'slug' => 'create_content', 'icon' => 'camera'],
                ['name' => 'Use During an Event', 'slug' => 'use_during_event', 'icon' => 'calendar'],
                ['name' => 'Give Feedback', 'slug' => 'give_feedback', 'icon' => 'message-square'],
                ['name' => 'Offer as a Giveaway', 'slug' => 'giveaway', 'icon' => 'gift'],
                ['name' => 'Promote a Discount Code', 'slug' => 'discount_code', 'icon' => 'percent'],
                ['name' => 'Sell During an Event', 'slug' => 'sell_during_event', 'icon' => 'shopping-cart'],
                ['name' => 'Open to Ideas', 'slug' => 'open_to_ideas', 'icon' => 'lightbulb'],
            ],
            // "Best for:" chips on a venue promotion.
            OfferOption::KIND_VENUE_FIT => [
                ['name' => 'Coffee', 'slug' => 'coffee', 'icon' => 'coffee'],
                ['name' => 'Brunch', 'slug' => 'brunch', 'icon' => 'utensils'],
                ['name' => 'Dinner', 'slug' => 'dinner', 'icon' => 'utensils'],
                ['name' => 'Drinks', 'slug' => 'drinks', 'icon' => 'wine'],
                ['name' => 'Wellness', 'slug' => 'wellness', 'icon' => 'sparkles'],
                ['name' => 'Shopping', 'slug' => 'shopping', 'icon' => 'shopping-bag'],
                ['name' => 'Workshops', 'slug' => 'workshops', 'icon' => 'hammer'],
                ['name' => 'Content', 'slug' => 'content', 'icon' => 'camera'],
                ['name' => 'After-Run', 'slug' => 'after_run', 'icon' => 'footprints'],
                ['name' => 'After-Work', 'slug' => 'after_work', 'icon' => 'briefcase'],
                ['name' => 'Networking', 'slug' => 'networking', 'icon' => 'users'],
                ['name' => 'Pop-Ups', 'slug' => 'pop_ups', 'icon' => 'store'],
                ['name' => 'Recurring Plans', 'slug' => 'recurring_plans', 'icon' => 'repeat'],
            ],
            // "Why communities will like this" chips.
            OfferOption::KIND_KOLAB_HIGHLIGHT => [
                ['name' => 'Good Location', 'slug' => 'good_location', 'icon' => 'map-pin'],
                ['name' => 'Nice Space for Groups', 'slug' => 'nice_space_for_groups', 'icon' => 'users'],
                ['name' => 'Great Photo Spot', 'slug' => 'great_photo_spot', 'icon' => 'camera'],
                ['name' => 'Healthy / Sporty Offer', 'slug' => 'healthy_sporty_offer', 'icon' => 'dumbbell'],
                ['name' => 'Free Samples', 'slug' => 'free_samples', 'icon' => 'package'],
                ['name' => 'Discount for Members', 'slug' => 'discount_for_members', 'icon' => 'percent'],
                ['name' => 'Good for After-Work Plans', 'slug' => 'good_for_after_work', 'icon' => 'briefcase'],
                ['name' => 'Good After a Workout', 'slug' => 'good_after_workout', 'icon' => 'dumbbell'],
                ['name' => 'Can Host Recurring Kolabs', 'slug' => 'recurring_kolabs', 'icon' => 'repeat'],
                ['name' => 'Unique Experience', 'slug' => 'unique_experience', 'icon' => 'sparkles'],
                ['name' => 'New Product to Try', 'slug' => 'new_product_to_try', 'icon' => 'package'],
                ['name' => 'Premium Experience', 'slug' => 'premium_experience', 'icon' => 'gem'],
                ['name' => 'Easy to Reach by Public Transport', 'slug' => 'easy_public_transport', 'icon' => 'train'],
                ['name' => 'Outdoor-Friendly', 'slug' => 'outdoor_friendly', 'icon' => 'sun'],
                ['name' => 'Cozy Indoor Space', 'slug' => 'cozy_indoor_space', 'icon' => 'home'],
                ['name' => 'Good for Content', 'slug' => 'good_for_content', 'icon' => 'camera'],
            ],
        ];
    }
}
