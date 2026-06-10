<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\City;
use App\Models\Community;
use App\Models\CommunityProfile;
use App\Models\Event;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Covers the CITY / DATE / TYPE filters and the community_name / community_type
 * resource fields added to GET /events/discover (NF-19). The legacy geo behaviour
 * is covered by EventDiscoveryTest.
 */
class EventDiscoveryFiltersTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Build an event hosted by a community that sits in $city and carries $type.
     *
     * @param  array<string, mixed>  $eventAttributes
     */
    private function createHostedEvent(
        City $city,
        string $type,
        string $communityName = 'Real Run Club',
        array $eventAttributes = []
    ): Event {
        $owner = Profile::factory()->business()->create();

        $communityProfile = CommunityProfile::factory()->create([
            'city_id' => $city->id,
            'community_type' => $type,
        ]);

        $community = Community::factory()->create([
            'name' => $communityName,
            'type' => $type,
            'community_profile_id' => $communityProfile->id,
        ]);

        return Event::factory()->forProfile($owner)->create(array_merge([
            'community_id' => $community->id,
            'location_lat' => 41.3900,
            'location_lng' => 2.1700,
            'is_active' => true,
            // Default to an upcoming event so date=today tests are explicit.
            'event_date' => now()->addDays(10)->toDateString(),
            'starts_at' => now()->addDays(10),
            'ends_at' => now()->addDays(10)->addHours(2),
        ], $eventAttributes));
    }

    public function test_discover_by_city_id_returns_only_that_citys_events(): void
    {
        $attendee = Profile::factory()->attendee()->create();

        $barcelona = City::factory()->create(['name' => 'Barcelona']);
        $madrid = City::factory()->create(['name' => 'Madrid']);

        $bcnEvent = $this->createHostedEvent($barcelona, 'run_club');
        $this->createHostedEvent($madrid, 'run_club');

        $response = $this->actingAs($attendee)
            ->getJson('/api/v1/events/discover?city_id='.$barcelona->id);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.events')
            ->assertJsonPath('data.events.0.id', $bcnEvent->id);
    }

    public function test_discover_date_today_filters_to_events_occurring_today(): void
    {
        $attendee = Profile::factory()->attendee()->create();
        $city = City::factory()->create();

        $todayEvent = $this->createHostedEvent($city, 'run_club', 'Today Club', [
            'event_date' => now()->toDateString(),
            'starts_at' => now()->setTime(18, 0),
            'ends_at' => now()->setTime(20, 0),
        ]);

        // Upcoming (not today) — must be excluded by date=today.
        $this->createHostedEvent($city, 'run_club', 'Future Club', [
            'event_date' => now()->addDays(5)->toDateString(),
            'starts_at' => now()->addDays(5),
            'ends_at' => now()->addDays(5)->addHours(2),
        ]);

        $response = $this->actingAs($attendee)
            ->getJson('/api/v1/events/discover?city_id='.$city->id.'&date=today');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.events')
            ->assertJsonPath('data.events.0.id', $todayEvent->id);
    }

    public function test_discover_by_type_filters_by_host_community_type(): void
    {
        $attendee = Profile::factory()->attendee()->create();
        $city = City::factory()->create();

        $runEvent = $this->createHostedEvent($city, 'run_club', 'Run Club');
        $this->createHostedEvent($city, 'book_club', 'Book Club');

        $response = $this->actingAs($attendee)
            ->getJson('/api/v1/events/discover?city_id='.$city->id.'&type=run_club');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.events')
            ->assertJsonPath('data.events.0.id', $runEvent->id);
    }

    public function test_discover_resource_exposes_community_name_and_type(): void
    {
        $attendee = Profile::factory()->attendee()->create();
        $city = City::factory()->create();

        $this->createHostedEvent($city, 'run_club', 'Real Run Club');

        $response = $this->actingAs($attendee)
            ->getJson('/api/v1/events/discover?city_id='.$city->id);

        $response->assertStatus(200)
            ->assertJsonPath('data.events.0.community_name', 'Real Run Club')
            ->assertJsonPath('data.events.0.community_type', 'run_club');
    }

    public function test_discover_geo_params_still_work_with_filters(): void
    {
        $attendee = Profile::factory()->attendee()->create();
        $city = City::factory()->create();

        // Near Barcelona center (41.3874, 2.1686).
        $this->createHostedEvent($city, 'run_club', 'Near Run Club', [
            'location_lat' => 41.3900,
            'location_lng' => 2.1700,
        ]);

        $response = $this->actingAs($attendee)
            ->getJson('/api/v1/events/discover?lat=41.3874&lng=2.1686&type=run_club');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.events')
            ->assertJsonPath('data.events.0.community_type', 'run_club');

        $events = $response->json('data.events');
        $this->assertArrayHasKey('distance_km', $events[0]);
    }

    public function test_discover_rejects_invalid_type_slug(): void
    {
        $attendee = Profile::factory()->attendee()->create();
        $city = City::factory()->create();

        $this->actingAs($attendee)
            ->getJson('/api/v1/events/discover?city_id='.$city->id.'&type=not_a_real_type')
            ->assertStatus(422);
    }
}
