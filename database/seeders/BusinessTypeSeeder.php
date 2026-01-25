<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\BusinessType;
use Illuminate\Database\Seeder;

class BusinessTypeSeeder extends Seeder
{
    /**
     * Seed the business_types table with Spanish market focused business types.
     */
    public function run(): void
    {
        $types = $this->getBusinessTypes();

        foreach ($types as $index => $type) {
            BusinessType::query()->updateOrCreate(
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
     * Get all business types for the Spanish market.
     *
     * @return array<int, array{name: string, slug: string, icon: string}>
     */
    private function getBusinessTypes(): array
    {
        return [
            [
                'name' => 'Restaurante',
                'slug' => 'restaurante',
                'icon' => 'utensils',
            ],
            [
                'name' => 'Cafeteria',
                'slug' => 'cafeteria',
                'icon' => 'coffee',
            ],
            [
                'name' => 'Bar',
                'slug' => 'bar',
                'icon' => 'beer',
            ],
            [
                'name' => 'Hotel',
                'slug' => 'hotel',
                'icon' => 'bed',
            ],
            [
                'name' => 'Gimnasio',
                'slug' => 'gimnasio',
                'icon' => 'dumbbell',
            ],
            [
                'name' => 'Spa y Bienestar',
                'slug' => 'spa-y-bienestar',
                'icon' => 'spa',
            ],
            [
                'name' => 'Tienda de Moda',
                'slug' => 'tienda-de-moda',
                'icon' => 'shirt',
            ],
            [
                'name' => 'Tienda de Deportes',
                'slug' => 'tienda-de-deportes',
                'icon' => 'basketball',
            ],
            [
                'name' => 'Peluqueria',
                'slug' => 'peluqueria',
                'icon' => 'scissors',
            ],
            [
                'name' => 'Centro de Belleza',
                'slug' => 'centro-de-belleza',
                'icon' => 'sparkles',
            ],
            [
                'name' => 'Clinica Dental',
                'slug' => 'clinica-dental',
                'icon' => 'tooth',
            ],
            [
                'name' => 'Centro Medico',
                'slug' => 'centro-medico',
                'icon' => 'stethoscope',
            ],
            [
                'name' => 'Coworking',
                'slug' => 'coworking',
                'icon' => 'building',
            ],
            [
                'name' => 'Discoteca',
                'slug' => 'discoteca',
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
