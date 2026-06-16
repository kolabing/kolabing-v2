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
     * Display name + icon per canonical slug. Keyed by the underscore slugs in
     * BusinessOnboardingRequest::BUSINESS_TYPES, which is the single source of
     * truth the API validates and the app sends/stores on profiles.
     *
     * @var array<string, array{name: string, icon: string}>
     */
    private const META = [
        'cafe' => ['name' => 'Cafe', 'icon' => 'coffee'],
        'restaurant' => ['name' => 'Restaurant', 'icon' => 'utensils'],
        'bar' => ['name' => 'Bar', 'icon' => 'beer'],
        'bakery' => ['name' => 'Bakery', 'icon' => 'croissant'],
        'coworking' => ['name' => 'Coworking', 'icon' => 'building'],
        'gym' => ['name' => 'Gym', 'icon' => 'dumbbell'],
        'salon' => ['name' => 'Salon', 'icon' => 'scissors'],
        'retail' => ['name' => 'Retail', 'icon' => 'shopping-bag'],
        'hotel' => ['name' => 'Hotel', 'icon' => 'bed'],
        'other' => ['name' => 'Other', 'icon' => 'ellipsis'],
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
            $meta = self::META[$slug] ?? ['name' => Str::headline($slug), 'icon' => 'ellipsis'];

            BusinessType::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $meta['name'],
                    'icon' => $meta['icon'],
                    'sort_order' => $index + 1,
                    'is_active' => true,
                ]
            );
        }

        BusinessType::query()->whereNotIn('slug', $slugs)->delete();
    }
}
