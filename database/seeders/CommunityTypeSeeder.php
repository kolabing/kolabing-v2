<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\CommunityType;
use Illuminate\Database\Seeder;

class CommunityTypeSeeder extends Seeder
{
    /**
     * Seed the community_types table with Spanish market focused community types.
     */
    public function run(): void
    {
        $types = $this->getCommunityTypes();

        foreach ($types as $index => $type) {
            CommunityType::query()->updateOrCreate(
                ['slug' => $type['slug']],
                [
                    'name' => $type['name'],
                    'slug' => $type['slug'],
                    'icon' => $type['icon'],
                    'sort_order' => $index + 1,
                    'is_active' => true,
                ]
            );
        }
    }

    /**
     * Get all community types for the Spanish market.
     *
     * @return array<int, array{name: string, slug: string, icon: string}>
     */
    private function getCommunityTypes(): array
    {
        return [
            [
                'name' => 'Run Club',
                'slug' => 'run_club',
                'icon' => 'running',
            ],
            [
                'name' => 'Fitness Community',
                'slug' => 'fitness_community',
                'icon' => 'dumbbell',
            ],
            [
                'name' => 'Wellness Community',
                'slug' => 'wellness_community',
                'icon' => 'heart',
            ],
            [
                'name' => 'Art & Creative Community',
                'slug' => 'art_creative_community',
                'icon' => 'palette',
            ],
            [
                'name' => 'Photography Community',
                'slug' => 'photography_community',
                'icon' => 'camera',
            ],
            [
                'name' => 'Music Community',
                'slug' => 'music_community',
                'icon' => 'music',
            ],
            [
                'name' => 'Dance Community',
                'slug' => 'dance_community',
                'icon' => 'music-2',
            ],
            [
                'name' => 'Tech / Startup Community',
                'slug' => 'tech_startup_community',
                'icon' => 'laptop',
            ],
            [
                'name' => 'Book Club',
                'slug' => 'book_club',
                'icon' => 'book',
            ],
            [
                'name' => 'Sustainability Community',
                'slug' => 'sustainability_community',
                'icon' => 'leaf',
            ],
            [
                'name' => 'Food Community',
                'slug' => 'food_community',
                'icon' => 'utensils',
            ],
            [
                'name' => 'Travel Community',
                'slug' => 'travel_community',
                'icon' => 'plane',
            ],
            [
                'name' => 'Student Community',
                'slug' => 'student_community',
                'icon' => 'graduation-cap',
            ],
            [
                'name' => 'Professional / Networking Community',
                'slug' => 'professional_networking_community',
                'icon' => 'users',
            ],
            [
                'name' => 'Business / Coworking',
                'slug' => 'business_coworking',
                'icon' => 'briefcase',
            ],
            [
                'name' => 'Hobby Community',
                'slug' => 'hobby_community',
                'icon' => 'star',
            ],
            [
                'name' => 'Other',
                'slug' => 'other',
                'icon' => 'ellipsis',
            ],
        ];
    }
}
