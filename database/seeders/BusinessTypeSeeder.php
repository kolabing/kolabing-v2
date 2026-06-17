<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Http\Requests\Api\V1\BusinessOnboardingRequest;
use App\Models\BusinessType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BusinessTypeSeeder extends Seeder
{
    /**
     * Display name + icon + onboarding path per canonical slug. Keyed by the
     * underscore slugs in BusinessOnboardingRequest::BUSINESS_TYPES, which is
     * the single source of truth the API validates and the app sends/stores on
     * profiles.
     *
     * applies_to drives the goal-based category filter on the app:
     * - venue:   physical hospitality venues (cafe, restaurant, bar, bakery,
     *            coworking, gym, salon, hotel)
     * - both:    valid on either path (retail can be a shop or a brand; other
     *            is the catch-all)
     * - product: product/service-only types (none in the current canon yet)
     *
     * @var array<string, array{name: string, icon: string, applies_to: string}>
     */
    private const META = [
        'cafe' => ['name' => 'Cafe', 'icon' => 'coffee', 'applies_to' => 'venue'],
        'restaurant' => ['name' => 'Restaurant', 'icon' => 'utensils', 'applies_to' => 'venue'],
        'bar' => ['name' => 'Bar', 'icon' => 'beer', 'applies_to' => 'venue'],
        'bakery' => ['name' => 'Bakery', 'icon' => 'croissant', 'applies_to' => 'venue'],
        'coworking' => ['name' => 'Coworking', 'icon' => 'building', 'applies_to' => 'venue'],
        'gym' => ['name' => 'Gym', 'icon' => 'dumbbell', 'applies_to' => 'venue'],
        'salon' => ['name' => 'Salon', 'icon' => 'scissors', 'applies_to' => 'venue'],
        'retail' => ['name' => 'Retail', 'icon' => 'shopping-bag', 'applies_to' => 'both'],
        'hotel' => ['name' => 'Hotel', 'icon' => 'bed', 'applies_to' => 'venue'],
        'other' => ['name' => 'Other', 'icon' => 'ellipsis', 'applies_to' => 'both'],
    ];

    /**
     * Seed business_types as an exact mirror of the canonical underscore
     * slug list. Stale slugs (e.g. the old Spanish hyphenated ones) are
     * pruned so the table is a true source of truth.
     */
    public function run(): void
    {
        $slugs = BusinessOnboardingRequest::BUSINESS_TYPES;

        foreach (array_values($slugs) as $index => $slug) {
            $meta = self::META[$slug] ?? ['name' => Str::headline($slug), 'icon' => 'ellipsis', 'applies_to' => 'both'];

            BusinessType::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $meta['name'],
                    'icon' => $meta['icon'],
                    'applies_to' => $meta['applies_to'] ?? 'both',
                    'sort_order' => $index + 1,
                    'is_active' => true,
                ]
            );
        }

        BusinessType::query()->whereNotIn('slug', $slugs)->delete();
    }
}
