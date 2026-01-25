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
                'name' => 'Running Club',
                'slug' => 'running-club',
                'icon' => 'running',
            ],
            [
                'name' => 'Club de Ciclismo',
                'slug' => 'club-de-ciclismo',
                'icon' => 'bicycle',
            ],
            [
                'name' => 'Grupo de Yoga',
                'slug' => 'grupo-de-yoga',
                'icon' => 'yoga',
            ],
            [
                'name' => 'Club de Fitness',
                'slug' => 'club-de-fitness',
                'icon' => 'dumbbell',
            ],
            [
                'name' => 'Grupo de Senderismo',
                'slug' => 'grupo-de-senderismo',
                'icon' => 'mountain',
            ],
            [
                'name' => 'Club de Padel',
                'slug' => 'club-de-padel',
                'icon' => 'racket',
            ],
            [
                'name' => 'Grupo de Arte',
                'slug' => 'grupo-de-arte',
                'icon' => 'palette',
            ],
            [
                'name' => 'Club de Lectura',
                'slug' => 'club-de-lectura',
                'icon' => 'book',
            ],
            [
                'name' => 'Grupo de Fotografia',
                'slug' => 'grupo-de-fotografia',
                'icon' => 'camera',
            ],
            [
                'name' => 'Comunidad Tech',
                'slug' => 'comunidad-tech',
                'icon' => 'laptop',
            ],
            [
                'name' => 'Grupo de Networking',
                'slug' => 'grupo-de-networking',
                'icon' => 'users',
            ],
            [
                'name' => 'Club Gastronomico',
                'slug' => 'club-gastronomico',
                'icon' => 'utensils',
            ],
            [
                'name' => 'Grupo de Viajes',
                'slug' => 'grupo-de-viajes',
                'icon' => 'plane',
            ],
            [
                'name' => 'Comunidad de Musica',
                'slug' => 'comunidad-de-musica',
                'icon' => 'music',
            ],
            [
                'name' => 'Otro',
                'slug' => 'otro',
                'icon' => 'ellipsis',
            ],
        ];
    }
}
