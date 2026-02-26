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
                'slug' => 'run-club',
                'icon' => 'running',
            ],
            [
                'name' => 'Fitness Community',
                'slug' => 'fitness-community',
                'icon' => 'dumbbell',
            ],
            [
                'name' => 'Wellness Community',
                'slug' => 'wellness-community',
                'icon' => 'heart',
            ],
            [
                'name' => 'Art & Creative Community',
                'slug' => 'art-creative-community',
                'icon' => 'palette',
            ],
            [
                'name' => 'Photography Community',
                'slug' => 'photography-community',
                'icon' => 'camera',
            ],
            [
                'name' => 'Music Community',
                'slug' => 'music-community',
                'icon' => 'music',
            ],
            [
                'name' => 'Dance Community',
                'slug' => 'dance-community',
                'icon' => 'music-2',
            ],
            [
                'name' => 'Tech / Startup Community',
                'slug' => 'tech-startup-community',
                'icon' => 'laptop',
            ],
            [
                'name' => 'Book Club',
                'slug' => 'book-club',
                'icon' => 'book',
            ],
            [
                'name' => 'Sustainability Community',
                'slug' => 'sustainability-community',
                'icon' => 'leaf',
            ],
            [
                'name' => 'Food Community',
                'slug' => 'food-community',
                'icon' => 'utensils',
            ],
            [
                'name' => 'Travel Community',
                'slug' => 'travel-community',
                'icon' => 'plane',
            ],
            [
                'name' => 'Student Community',
                'slug' => 'student-community',
                'icon' => 'graduation-cap',
            ],
            [
                'name' => 'Professional / Networking Community',
                'slug' => 'professional-networking-community',
                'icon' => 'users',
            ],
            [
                'name' => 'Hobby Community',
                'slug' => 'hobby-community',
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
