<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Http\Requests\Api\V1\CommunityOnboardingRequest;
use App\Models\CommunityType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CommunityTypeSeeder extends Seeder
{
    /**
     * Display name + icon per canonical slug. Keyed by the underscore slugs in
     * CommunityOnboardingRequest::COMMUNITY_TYPES, which is the single source of
     * truth the API validates and the app sends/stores on profiles.
     *
     * @var array<string, array{name: string, icon: string}>
     */
    private const META = [
        'run_club' => ['name' => 'Run Club', 'icon' => 'footprints'],
        'fitness_community' => ['name' => 'Fitness Community', 'icon' => 'dumbbell'],
        'wellness_community' => ['name' => 'Wellness Community', 'icon' => 'heart'],
        'art_creative_community' => ['name' => 'Art & Creative Community', 'icon' => 'palette'],
        'photography_community' => ['name' => 'Photography Community', 'icon' => 'camera'],
        'music_community' => ['name' => 'Music Community', 'icon' => 'music'],
        'dance_community' => ['name' => 'Dance Community', 'icon' => 'music-2'],
        'tech_startup_community' => ['name' => 'Tech / Startup Community', 'icon' => 'laptop'],
        'book_club' => ['name' => 'Book Club', 'icon' => 'book'],
        'sustainability_community' => ['name' => 'Sustainability Community', 'icon' => 'leaf'],
        'food_community' => ['name' => 'Food Community', 'icon' => 'utensils'],
        'travel_community' => ['name' => 'Travel Community', 'icon' => 'plane'],
        'student_community' => ['name' => 'Student Community', 'icon' => 'graduation-cap'],
        'professional_networking_community' => ['name' => 'Professional / Networking Community', 'icon' => 'users'],
        'business_coworking' => ['name' => 'Business / Coworking', 'icon' => 'briefcase'],
        'hobby_community' => ['name' => 'Hobby Community', 'icon' => 'star'],
        'other' => ['name' => 'Other', 'icon' => 'ellipsis'],
    ];

    /**
     * Seed community_types as an exact mirror of the canonical underscore
     * slug list. Stale slugs (e.g. the old hyphenated ones) are pruned so
     * the table is a true source of truth.
     */
    public function run(): void
    {
        $slugs = CommunityOnboardingRequest::COMMUNITY_TYPES;

        foreach (array_values($slugs) as $index => $slug) {
            $meta = self::META[$slug] ?? ['name' => Str::headline($slug), 'icon' => 'ellipsis'];

            CommunityType::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $meta['name'],
                    'icon' => $meta['icon'],
                    'sort_order' => $index + 1,
                    'is_active' => true,
                ]
            );
        }

        CommunityType::query()->whereNotIn('slug', $slugs)->delete();
    }
}
