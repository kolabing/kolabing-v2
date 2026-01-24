<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\City;
use Illuminate\Database\Seeder;

class CitySeeder extends Seeder
{
    /**
     * Seed the cities table.
     */
    public function run(): void
    {
        $cities = [
            ['name' => 'Barcelona', 'country' => 'Spain'],
            ['name' => 'Madrid', 'country' => 'Spain'],
            ['name' => 'Valencia', 'country' => 'Spain'],
            ['name' => 'Sevilla', 'country' => 'Spain'],
            ['name' => 'Bilbao', 'country' => 'Spain'],
            ['name' => 'Malaga', 'country' => 'Spain'],
            ['name' => 'Zaragoza', 'country' => 'Spain'],
            ['name' => 'Palma', 'country' => 'Spain'],
        ];

        foreach ($cities as $city) {
            City::query()->updateOrCreate(
                ['name' => $city['name']],
                $city
            );
        }
    }
}
