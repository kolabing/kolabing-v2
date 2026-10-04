<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\BusinessProfile;
use App\Models\City;
use App\Models\CommunityProfile;
use App\Models\Kolab;
use App\Models\Profile;
use App\Services\CityResolver;
use App\Services\KolabService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * BE-FX-60 — filtering Explore by a city must find the listings that are in it.
 *
 * `kolabs.preferred_city` is a free-text NAME filled from the venue's Google
 * Places `locality`, which for a Mexico City venue is "Ciudad de México" or the
 * alcaldía ("Cuajimalps"), while `GET /api/v1/cities` and the picker offer
 * "Mexico City". The filter compared the two literally, so the only two real
 * Mexico City listings in production were invisible to a Mexico City filter.
 */
class KolabCityFilterTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        City::factory()->create(['name' => 'Mexico City', 'country' => 'Mexico', 'is_active' => true]);
        City::factory()->create(['name' => 'Barcelona', 'country' => 'Spain', 'is_active' => true]);

        app(CityResolver::class)->flush();
    }

    public function test_browse_by_canonical_city_finds_kolabs_stored_under_the_google_spelling(): void
    {
        $viewer = Profile::factory()->business()->create();

        Kolab::factory()->published()->create(['preferred_city' => 'Ciudad de México']);
        Kolab::factory()->published()->create(['preferred_city' => 'Cuajimalps']);
        Kolab::factory()->published()->create(['preferred_city' => 'Mexico City']);
        Kolab::factory()->published()->create(['preferred_city' => 'Barcelona']);

        $this->actingAs($viewer)
            ->getJson('/api/v1/kolabs?city=Mexico City')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 3);
    }

    public function test_browse_by_city_is_case_insensitive_and_still_excludes_other_cities(): void
    {
        $viewer = Profile::factory()->business()->create();

        Kolab::factory()->published()->create(['preferred_city' => 'barcelona']);
        Kolab::factory()->published()->create(['preferred_city' => 'Ciudad de México']);

        $this->actingAs($viewer)
            ->getJson('/api/v1/kolabs?city=Barcelona')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_browse_by_an_unknown_city_matches_only_that_exact_name(): void
    {
        $viewer = Profile::factory()->business()->create();

        Kolab::factory()->published()->create(['preferred_city' => 'Atlantis']);
        Kolab::factory()->published()->create(['preferred_city' => 'Barcelona']);

        $this->actingAs($viewer)
            ->getJson('/api/v1/kolabs?city=Atlantis')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_discovery_city_filter_finds_the_google_spelling(): void
    {
        $viewer = Profile::factory()->community()->create();
        CommunityProfile::factory()->create([
            'profile_id' => $viewer->id,
            'name' => 'Runners CDMX',
            'community_type' => 'sports_community',
        ]);

        $business = Profile::factory()->business()->create();
        BusinessProfile::factory()->create([
            'profile_id' => $business->id,
            'name' => 'Babüin Café',
            'city_name' => 'Mexico City',
        ]);

        Kolab::factory()->published()->venuePromotion()->forCreator($business)->create([
            'title' => 'Roma Sur coffee mornings',
            'preferred_city' => 'Ciudad de México',
            'availability_mode' => 'flexible',
            'availability_start' => now()->addWeek(),
            'availability_end' => now()->addMonth(),
        ]);

        $response = $this->actingAs($viewer)
            ->getJson('/api/v1/discovery/opportunities?feed=all&city=Mexico City');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.data.0.title', 'Roma Sur coffee mornings');
    }

    public function test_discovery_defaults_to_the_viewers_city_and_city_all_opts_out(): void
    {
        $barcelona = City::query()->where('name', 'Barcelona')->firstOrFail();
        $viewer = Profile::factory()->community()->create();
        CommunityProfile::factory()->create([
            'profile_id' => $viewer->id,
            'name' => 'Kontakt BCN',
            'community_type' => 'social_community',
            'city_id' => $barcelona->id,
        ]);

        foreach (['Barcelona' => 'Rooftop in Barcelona', 'Ciudad de México' => 'Roma Sur coffee mornings'] as $city => $title) {
            $business = Profile::factory()->business()->create();
            BusinessProfile::factory()->create(['profile_id' => $business->id, 'name' => $title, 'city_name' => $city]);
            Kolab::factory()->published()->venuePromotion()->forCreator($business)->create([
                'title' => $title,
                'preferred_city' => $city,
                'availability_mode' => 'flexible',
                'availability_start' => now()->addWeek(),
                'availability_end' => now()->addMonth(),
            ]);
        }

        $this->actingAs($viewer)
            ->getJson('/api/v1/discovery/opportunities?feed=all')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.data.0.title', 'Rooftop in Barcelona');

        $this->actingAs($viewer)
            ->getJson('/api/v1/discovery/opportunities?feed=all&city=all')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 2);
    }

    /**
     * BE-FX-82 — prod, 2026-10-04: a business in L'Hospitalet de Llobregat (a
     * Barcelona metro town, Google's `locality` for its venue) saw an empty
     * Explore, because the default city filter used that name verbatim.
     */
    public function test_discovery_defaults_a_barcelona_metro_viewer_to_barcelona(): void
    {
        $viewer = $this->businessViewerIn("L'Hospitalet de Llobregat");
        $this->communityKolabIn('Barcelona', 'Brunch run in Barcelona');
        $this->communityKolabIn('Ciudad de México', 'Roma Sur run club');

        $this->actingAs($viewer)
            ->getJson('/api/v1/discovery/opportunities?feed=all')
            ->assertStatus(200)
            ->assertJsonPath('meta.applied_filters.city', 'Barcelona')
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.data.0.title', 'Brunch run in Barcelona');
    }

    public function test_discovery_does_not_default_to_a_city_it_does_not_know(): void
    {
        $viewer = $this->businessViewerIn('Atlantis');
        $this->communityKolabIn('Barcelona', 'Brunch run in Barcelona');
        $this->communityKolabIn('Ciudad de México', 'Roma Sur run club');

        $response = $this->actingAs($viewer)
            ->getJson('/api/v1/discovery/opportunities?feed=all')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 2);

        $this->assertNull($response->json('meta.applied_filters.city'));

        // An explicit city still filters, known or not.
        $this->actingAs($viewer)
            ->getJson('/api/v1/discovery/opportunities?feed=all&city=Barcelona')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_the_recommended_feed_is_not_emptied_by_an_unknown_viewer_city(): void
    {
        $viewer = $this->businessViewerIn('Atlantis');
        $this->communityKolabIn('Barcelona', 'Brunch run in Barcelona');

        $this->actingAs($viewer)
            ->getJson('/api/v1/discovery/opportunities?feed=recommended')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 1);
    }

    private function businessViewerIn(string $cityName): Profile
    {
        $viewer = Profile::factory()->business()->create();
        BusinessProfile::factory()->create([
            'profile_id' => $viewer->id,
            'name' => 'Padel club',
            'city_name' => $cityName,
            'city_id' => null,
        ]);

        return $viewer;
    }

    private function communityKolabIn(string $city, string $title): void
    {
        $community = Profile::factory()->community()->create();
        CommunityProfile::factory()->create([
            'profile_id' => $community->id,
            'name' => $title.' crew',
            'community_type' => 'sports_community',
        ]);

        Kolab::factory()->published()->forCreator($community)->create([
            'title' => $title,
            'preferred_city' => $city,
            'availability_mode' => 'flexible',
            'availability_start' => now()->addWeek(),
            'availability_end' => now()->addMonth(),
        ]);
    }

    public function test_creating_a_venue_kolab_stores_the_canonical_city(): void
    {
        $business = Profile::factory()->business()->create();
        BusinessProfile::factory()->create([
            'profile_id' => $business->id,
            'name' => 'Exploradores Club de Cafe',
            'city_name' => 'Mexico City',
            'primary_venue' => [
                'name' => 'Exploradores Club de Cafe',
                'venue_type' => 'cafe',
                'capacity' => 100,
                'formatted_address' => 'Av. Santa Fe 601, 05310 Cuajimalps, CDMX, Mexico',
                // What Google actually returned as the venue's locality.
                'city' => 'Cuajimalps',
                'country' => 'Mexico',
                'photos' => [],
            ],
        ]);

        $kolab = app(KolabService::class)->create($business->fresh(), [
            'intent_type' => 'venue_promotion',
            'title' => 'Coffee tastings for local communities',
            'description' => 'Host your meetup at our Santa Fe cafe.',
            'offering' => ['venue'],
            'availability_mode' => 'flexible',
            'availability_start' => now()->addDay()->toDateString(),
        ]);

        $this->assertSame('Mexico City', $kolab->preferred_city);
    }

    public function test_updating_a_kolab_canonicalizes_a_city_typed_by_hand(): void
    {
        $business = Profile::factory()->business()->create();
        $kolab = Kolab::factory()->forCreator($business)->create([
            'intent_type' => 'product_promotion',
            'preferred_city' => 'Barcelona',
        ]);

        $updated = app(KolabService::class)->update($kolab, ['preferred_city' => 'CDMX']);

        $this->assertSame('Mexico City', $updated->preferred_city);
    }
}
