<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\BusinessProfile;
use App\Models\City;
use App\Models\Kolab;
use App\Models\Profile;
use App\Services\CityResolver;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * BE-FX-60 — the rows written before the fix still hold the Google spelling.
 */
class CanonicalizeStoredCitiesTest extends TestCase
{
    use LazilyRefreshDatabase;

    private City $mexicoCity;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mexicoCity = City::factory()->create([
            'name' => 'Mexico City',
            'country' => 'Mexico',
            'is_active' => true,
        ]);

        app(CityResolver::class)->flush();
    }

    public function test_dry_run_reports_without_writing(): void
    {
        $kolab = Kolab::factory()->published()->create(['preferred_city' => 'Ciudad de México']);

        $this->artisan('cities:canonicalize')
            ->expectsOutputToContain('Dry run')
            ->assertSuccessful();

        $this->assertSame('Ciudad de México', $kolab->fresh()->preferred_city);
    }

    public function test_apply_rewrites_kolabs_and_fills_the_business_profile_city(): void
    {
        $kolab = Kolab::factory()->published()->create(['preferred_city' => 'Cuajimalps']);
        $untouched = Kolab::factory()->published()->create(['preferred_city' => 'Atlantis']);

        $business = Profile::factory()->business()->create();
        $profile = BusinessProfile::factory()->create([
            'profile_id' => $business->id,
            'city_id' => null,
            'city_name' => 'Ciudad de México',
            'city_country' => 'Mexico',
        ]);

        $this->artisan('cities:canonicalize --apply')->assertSuccessful();

        $this->assertSame('Mexico City', $kolab->fresh()->preferred_city);
        $this->assertSame('Atlantis', $untouched->fresh()->preferred_city);

        $profile->refresh();
        $this->assertSame('Mexico City', $profile->city_name);
        $this->assertSame($this->mexicoCity->id, $profile->city_id);
        $this->assertSame('Mexico', $profile->city_country);
    }
}
