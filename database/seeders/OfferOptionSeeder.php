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
            OfferOption::KIND_DELIVERABLE => [
                ['name' => 'Social Media', 'slug' => 'social_media', 'icon' => 'share-2'],
                ['name' => 'Event Activation', 'slug' => 'event_activation', 'icon' => 'sparkles'],
                ['name' => 'Product Placement', 'slug' => 'product_placement', 'icon' => 'package'],
                ['name' => 'Community Reach', 'slug' => 'community_reach', 'icon' => 'users'],
                ['name' => 'Review & Feedback', 'slug' => 'review_feedback', 'icon' => 'star'],
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
        ];
    }
}
