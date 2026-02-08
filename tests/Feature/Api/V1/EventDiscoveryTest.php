<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\BusinessProfile;
use App\Models\Event;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class EventDiscoveryTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Create an event at specific coordinates.
     */
    private function createEventAt(float $lat, float $lng, bool $active = true): Event
    {
        $owner = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $owner->id]);

        return Event::factory()->forProfile($owner)->create([
            'location_lat' => $lat,
            'location_lng' => $lng,
            'is_active' => $active,
        ]);
    }

    // Barcelona center: 41.3874, 2.1686
    // ~0.3km away:  41.3900, 2.1700
    // ~3.6km away:  41.4200, 2.1700
    // ~7km away:    41.4500, 2.1700
    // ~46km away:   41.8000, 2.1700
    // ~180km away:  43.0000, 2.1700

    /*
    |--------------------------------------------------------------------------
    | Happy Paths
    |--------------------------------------------------------------------------
    */

    public function test_discover_returns_nearby_active_events(): void
    {
        $attendee = Profile::factory()->attendee()->create();
        $nearEvent = $this->createEventAt(41.3900, 2.1700);

        $response = $this->actingAs($attendee)
            ->getJson('/api/v1/events/discover?lat=41.3874&lng=2.1686');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.events');
    }

    public function test_discover_returns_distance_km_in_response(): void
    {
        $attendee = Profile::factory()->attendee()->create();
        $this->createEventAt(41.3900, 2.1700);

        $response = $this->actingAs($attendee)
            ->getJson('/api/v1/events/discover?lat=41.3874&lng=2.1686');

        $response->assertStatus(200);

        $events = $response->json('data.events');
        $this->assertArrayHasKey('distance_km', $events[0]);
        $this->assertIsFloat($events[0]['distance_km']);
    }

    public function test_discover_returns_pagination_metadata(): void
    {
        $attendee = Profile::factory()->attendee()->create();
        $this->createEventAt(41.3900, 2.1700);

        $response = $this->actingAs($attendee)
            ->getJson('/api/v1/events/discover?lat=41.3874&lng=2.1686');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'events',
                    'pagination' => [
                        'current_page',
                        'total_pages',
                        'total_count',
                        'per_page',
                    ],
                ],
            ]);
    }

    public function test_discover_orders_by_distance(): void
    {
        $attendee = Profile::factory()->attendee()->create();
        $farEvent = $this->createEventAt(41.4500, 2.1700);
        $nearEvent = $this->createEventAt(41.3880, 2.1690);

        $response = $this->actingAs($attendee)
            ->getJson('/api/v1/events/discover?lat=41.3874&lng=2.1686');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data.events');

        $events = $response->json('data.events');
        $this->assertEquals($nearEvent->id, $events[0]['id']);
        $this->assertEquals($farEvent->id, $events[1]['id']);
    }

    public function test_discover_respects_limit_parameter(): void
    {
        $attendee = Profile::factory()->attendee()->create();
        $this->createEventAt(41.3900, 2.1700);
        $this->createEventAt(41.3910, 2.1710);
        $this->createEventAt(41.3920, 2.1720);

        $response = $this->actingAs($attendee)
            ->getJson('/api/v1/events/discover?lat=41.3874&lng=2.1686&limit=2');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data.events')
            ->assertJsonPath('data.pagination.per_page', 2)
            ->assertJsonPath('data.pagination.total_count', 3);
    }

    public function test_discover_defaults_radius_to_50km(): void
    {
        $attendee = Profile::factory()->attendee()->create();
        $this->createEventAt(41.4200, 2.1700); // ~3.6km away (within 50km)

        $response = $this->actingAs($attendee)
            ->getJson('/api/v1/events/discover?lat=41.3874&lng=2.1686');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.events');
    }

    public function test_discover_respects_custom_radius(): void
    {
        $attendee = Profile::factory()->attendee()->create();
        $this->createEventAt(41.4200, 2.1700); // ~3.6km away
        $this->createEventAt(41.3880, 2.1690); // ~0.07km away

        // With a 2km radius, only the very close event should appear
        $response = $this->actingAs($attendee)
            ->getJson('/api/v1/events/discover?lat=41.3874&lng=2.1686&radius_km=2');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.events');
    }

    /*
    |--------------------------------------------------------------------------
    | Exclusion Paths
    |--------------------------------------------------------------------------
    */

    public function test_discover_excludes_inactive_events(): void
    {
        $attendee = Profile::factory()->attendee()->create();
        $this->createEventAt(41.3900, 2.1700, active: false);

        $response = $this->actingAs($attendee)
            ->getJson('/api/v1/events/discover?lat=41.3874&lng=2.1686');

        $response->assertStatus(200)
            ->assertJsonCount(0, 'data.events');
    }

    public function test_discover_excludes_events_without_location(): void
    {
        $attendee = Profile::factory()->attendee()->create();
        $owner = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $owner->id]);
        Event::factory()->forProfile($owner)->create([
            'location_lat' => null,
            'location_lng' => null,
            'is_active' => true,
        ]);

        $response = $this->actingAs($attendee)
            ->getJson('/api/v1/events/discover?lat=41.3874&lng=2.1686');

        $response->assertStatus(200)
            ->assertJsonCount(0, 'data.events');
    }

    public function test_discover_excludes_events_outside_radius(): void
    {
        $attendee = Profile::factory()->attendee()->create();
        $this->createEventAt(43.0000, 2.1700); // ~180km away

        $response = $this->actingAs($attendee)
            ->getJson('/api/v1/events/discover?lat=41.3874&lng=2.1686&radius_km=50');

        $response->assertStatus(200)
            ->assertJsonCount(0, 'data.events');
    }

    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    public function test_discover_validates_required_parameters(): void
    {
        $attendee = Profile::factory()->attendee()->create();

        $this->actingAs($attendee)
            ->getJson('/api/v1/events/discover')
            ->assertStatus(422);

        $this->actingAs($attendee)
            ->getJson('/api/v1/events/discover?lat=41.3874')
            ->assertStatus(422);

        $this->actingAs($attendee)
            ->getJson('/api/v1/events/discover?lng=2.1686')
            ->assertStatus(422);
    }

    public function test_discover_validates_lat_lng_ranges(): void
    {
        $attendee = Profile::factory()->attendee()->create();

        $this->actingAs($attendee)
            ->getJson('/api/v1/events/discover?lat=999&lng=2.1686')
            ->assertStatus(422);

        $this->actingAs($attendee)
            ->getJson('/api/v1/events/discover?lat=41.3874&lng=999')
            ->assertStatus(422);
    }

    public function test_discover_validates_radius_range(): void
    {
        $attendee = Profile::factory()->attendee()->create();

        $this->actingAs($attendee)
            ->getJson('/api/v1/events/discover?lat=41.3874&lng=2.1686&radius_km=0')
            ->assertStatus(422);

        $this->actingAs($attendee)
            ->getJson('/api/v1/events/discover?lat=41.3874&lng=2.1686&radius_km=300')
            ->assertStatus(422);
    }

    public function test_discover_validates_limit_range(): void
    {
        $attendee = Profile::factory()->attendee()->create();

        $this->actingAs($attendee)
            ->getJson('/api/v1/events/discover?lat=41.3874&lng=2.1686&limit=0')
            ->assertStatus(422);

        $this->actingAs($attendee)
            ->getJson('/api/v1/events/discover?lat=41.3874&lng=2.1686&limit=100')
            ->assertStatus(422);
    }

    /*
    |--------------------------------------------------------------------------
    | Auth
    |--------------------------------------------------------------------------
    */

    public function test_unauthenticated_returns_401(): void
    {
        $this->getJson('/api/v1/events/discover?lat=41.3874&lng=2.1686')
            ->assertStatus(401);
    }
}
