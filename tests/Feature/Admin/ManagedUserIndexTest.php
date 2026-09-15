<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\BusinessProfile;
use App\Models\City;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Daniel 2026-09-14: the users list was "a lot of stale tests" and "not UI/UX
 * friendly ... to show listed users per city" -- covers the search/city/type
 * filters, the default test-row hiding, and the city map data added in response.
 */
class ManagedUserIndexTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function maintainer(): User
    {
        return User::factory()->create(['is_maintainer' => true]);
    }

    public function test_the_index_renders_the_filter_bar_and_a_city_column(): void
    {
        $this->actingAs($this->maintainer(), 'admin')
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('Search name or email', false)
            ->assertSee('>City<', false);
    }

    public function test_searching_by_name_finds_the_matching_profile_only(): void
    {
        $profile = Profile::factory()->business()->create(['email' => 'antonio@grupoexploradores.com']);
        BusinessProfile::factory()->for($profile, 'profile')->create(['name' => 'Exploradores de Café']);

        $other = Profile::factory()->business()->create(['email' => 'owner@someotherbusiness.com']);
        BusinessProfile::factory()->for($other, 'profile')->create(['name' => 'Some Other Business']);

        $this->actingAs($this->maintainer(), 'admin')
            ->get(route('admin.users.index', ['q' => 'Exploradores']))
            ->assertOk()
            ->assertSee('Exploradores de Café', false)
            ->assertDontSee('Some Other Business', false);
    }

    public function test_city_filter_narrows_to_that_city_only(): void
    {
        $madrid = City::factory()->create(['name' => 'Madrid']);
        $barcelona = City::factory()->create(['name' => 'Barcelona']);

        $inMadrid = Profile::factory()->business()->create(['email' => 'owner@madridco.com']);
        BusinessProfile::factory()->for($inMadrid, 'profile')->create(['name' => 'Madrid Co', 'city_id' => $madrid->id]);

        $inBarcelona = Profile::factory()->business()->create(['email' => 'owner@barcaco.com']);
        BusinessProfile::factory()->for($inBarcelona, 'profile')->create(['name' => 'Barca Co', 'city_id' => $barcelona->id]);

        $this->actingAs($this->maintainer(), 'admin')
            ->get(route('admin.users.index', ['city_id' => $madrid->id]))
            ->assertOk()
            ->assertSee('Madrid Co', false)
            ->assertDontSee('Barca Co', false);
    }

    public function test_test_flagged_profiles_are_hidden_by_default_and_shown_on_request(): void
    {
        $real = Profile::factory()->business()->create(['is_test_user' => false, 'email' => 'owner@realbusiness.com']);
        BusinessProfile::factory()->for($real, 'profile')->create(['name' => 'Real Business']);

        $test = Profile::factory()->business()->create(['is_test_user' => true, 'email' => 'qa@realbusiness-qa.com']);
        BusinessProfile::factory()->for($test, 'profile')->create(['name' => 'QA Test Business']);

        $default = $this->actingAs($this->maintainer(), 'admin')->get(route('admin.users.index'));
        $default->assertOk()->assertSee('Real Business', false)->assertDontSee('QA Test Business', false);

        $shown = $this->actingAs($this->maintainer(), 'admin')->get(route('admin.users.index', ['show_test' => 1]));
        $shown->assertOk()->assertSee('Real Business', false)->assertSee('QA Test Business', false);
    }

    public function test_a_test_ish_email_is_hidden_by_default_even_without_the_flag(): void
    {
        $profile = Profile::factory()->business()->create(['is_test_user' => false, 'email' => 'clark-test-1@example.com']);
        BusinessProfile::factory()->for($profile, 'profile')->create(['name' => 'Unflagged Legacy QA Row']);

        $this->actingAs($this->maintainer(), 'admin')
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertDontSee('Unflagged Legacy QA Row', false);
    }

    public function test_the_map_shows_a_city_with_a_listed_business(): void
    {
        $city = City::factory()->create(['name' => 'Mexico City']);
        $profile = Profile::factory()->business()->create(['email' => 'owner@cdmxco.com']);
        BusinessProfile::factory()->for($profile, 'profile')->create(['name' => 'CDMX Co', 'city_id' => $city->id]);

        // The per-marker tooltip text is set client-side by Leaflet JS, not present
        // in the server-rendered HTML -- assert on the embedded @json() data instead,
        // which the browser's JS reads to build the map.
        $this->actingAs($this->maintainer(), 'admin')
            ->get(route('admin.users.index'))
            ->assertOk()
            ->assertSee('Map — listings by city', false)
            ->assertSee('"city":"Mexico City"', false)
            ->assertSee('"n":1', false)
            ->assertSee('"active":false', false);
    }
}
