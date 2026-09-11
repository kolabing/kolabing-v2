<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\City;
use Database\Seeders\CitySeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CitySeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_seeder_activates_exactly_the_curated_beachhead_cities(): void
    {
        $this->seed(CitySeeder::class);

        $active = City::query()->where('is_active', true)->orderBy('sort_order')->pluck('name');

        $this->assertSame([
            'Barcelona', 'Madrid', 'Valencia', 'Sevilla', 'Bilbao',
            'Mexico City', 'Tallinn', 'Berlin', 'Paris', 'Warsaw',
        ], $active->all());
    }

    public function test_seeder_deactivates_previously_active_spanish_cities_outside_the_beachhead(): void
    {
        // Simulate rows left active by a prior seeder run (e.g. Malaga/Granada/Cadiz/Cordoba/
        // San Sebastian, the old active set) — re-running the seeder must correct them, not
        // just leave them as they were.
        City::factory()->create(['name' => 'Malaga', 'country' => 'Spain', 'is_active' => true, 'sort_order' => 2]);
        City::factory()->create(['name' => 'Granada', 'country' => 'Spain', 'is_active' => true, 'sort_order' => 6]);

        $this->seed(CitySeeder::class);

        $this->assertFalse(City::query()->where('name', 'Malaga')->value('is_active'));
        $this->assertFalse(City::query()->where('name', 'Granada')->value('is_active'));
    }

    public function test_seeder_seeds_international_cities_with_their_real_country(): void
    {
        $this->seed(CitySeeder::class);

        $this->assertSame('Mexico', City::query()->where('name', 'Mexico City')->value('country'));
        $this->assertSame('Estonia', City::query()->where('name', 'Tallinn')->value('country'));
        $this->assertSame('Germany', City::query()->where('name', 'Berlin')->value('country'));
        $this->assertSame('France', City::query()->where('name', 'Paris')->value('country'));
        $this->assertSame('Poland', City::query()->where('name', 'Warsaw')->value('country'));
    }

    public function test_seeder_is_idempotent_on_repeated_runs(): void
    {
        $this->seed(CitySeeder::class);
        $countAfterFirstRun = City::query()->count();

        $this->seed(CitySeeder::class);

        $this->assertSame($countAfterFirstRun, City::query()->count());
    }
}
