<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\BusinessProfile;
use App\Models\Event;
use App\Models\Profile;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
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
            // Discover is public-only and future-inclusive: these fixtures must be
            // public + upcoming to surface.
            'visibility' => \App\Enums\EventVisibility::Public->value,
            'event_date' => now()->addDays(10)->toDateString(),
            'starts_at' => now()->addDays(10),
            'ends_at' => now()->addDays(10)->addHours(2),
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

    /**
     * Contract change (events-city-discover): is_active is NO LONGER a discover
     * gate. Community upcoming events are created is_active=false, so the old
     * blanket gate hid every one of them. Discoverability is now governed solely
     * by visibility=public + a future-inclusive date floor; an inactive public
     * upcoming event therefore SURFACES (in the geo path too).
     */
    public function test_discover_includes_inactive_public_upcoming_events(): void
    {
        $attendee = Profile::factory()->attendee()->create();
        $event = $this->createEventAt(41.3900, 2.1700, active: false);

        $response = $this->actingAs($attendee)
            ->getJson('/api/v1/events/discover?lat=41.3874&lng=2.1686');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.events')
            ->assertJsonPath('data.events.0.id', $event->id);
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

    /*
    |--------------------------------------------------------------------------
    | BE-FX-20 — one distance implementation, and CI runs the production one
    |--------------------------------------------------------------------------
    | The service used to branch on the driver: SQLite got a bounding box plus a
    | PHP calculation, Postgres got trigonometry in SQL. `phpunit.xml` pins the
    | suite to SQLite, so the branch that runs in production had no test at all —
    | structurally the same blind spot as BE-FX-12. These tests execute the
    | production expression, so there is only one path left to get wrong.
    */

    public function test_the_distance_is_computed_in_sql_not_in_php(): void
    {
        $attendee = Profile::factory()->attendee()->create();
        $this->createEventAt(41.3900, 2.1700);

        /** @var list<string> $statements */
        $statements = [];
        DB::listen(function (QueryExecuted $event) use (&$statements): void {
            $statements[] = $event->sql;
        });

        $this->actingAs($attendee)
            ->getJson('/api/v1/events/discover?lat=41.3874&lng=2.1686')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.events');

        $trig = array_values(array_filter(
            $statements,
            static fn (string $sql): bool => str_contains($sql, 'radians(') && str_contains($sql, 'location_lat'),
        ));

        $this->assertNotEmpty(
            $trig,
            'The great-circle distance must be computed in SQL on EVERY driver — otherwise CI never executes the production path.',
        );

        // And nothing may fall back to a bounding box: that was the second,
        // untested implementation, and it paginated before it filtered.
        $boxed = array_values(array_filter(
            $statements,
            static fn (string $sql): bool => str_contains($sql, 'location_lat') && str_contains($sql, 'between'),
        ));
        $this->assertSame([], $boxed, 'The bounding-box branch must be gone — one implementation only.');
    }

    public function test_distance_km_matches_the_haversine_reference(): void
    {
        $attendee = Profile::factory()->attendee()->create();

        // Exactly 0.1 degrees due north of the query point: 6371 * radians(0.1) km.
        $this->createEventAt(41.4874, 2.1686);
        $expected = 6371.0 * deg2rad(0.1);

        $response = $this->actingAs($attendee)
            ->getJson('/api/v1/events/discover?lat=41.3874&lng=2.1686')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.events');

        $this->assertEqualsWithDelta($expected, $response->json('data.events.0.distance_km'), 0.01);
    }

    public function test_a_zero_distance_event_does_not_blow_up_the_expression(): void
    {
        $attendee = Profile::factory()->attendee()->create();

        // The law-of-cosines form computes cos²+sin², which floats to 1+ε and makes
        // Postgres `acos()` raise "input is out of range" for an event AT the query
        // point. The atan2 form is unconditionally in range.
        $this->createEventAt(41.3874, 2.1686);

        $response = $this->actingAs($attendee)
            ->getJson('/api/v1/events/discover?lat=41.3874&lng=2.1686')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.events');

        $this->assertEqualsWithDelta(0.0, $response->json('data.events.0.distance_km'), 0.001);
    }

    public function test_results_are_ordered_by_distance_across_the_whole_set_not_within_a_page(): void
    {
        $attendee = Profile::factory()->attendee()->create();

        // Inserted farthest-first, so insertion order is the WRONG answer.
        $far = $this->createEventAt(41.7500, 2.1686);   // ~40km
        $mid = $this->createEventAt(41.6600, 2.1686);   // ~30km
        $near = $this->createEventAt(41.5700, 2.1686);  // ~20km
        $nearest = $this->createEventAt(41.4800, 2.1686); // ~10km

        $response = $this->actingAs($attendee)
            ->getJson('/api/v1/events/discover?lat=41.3874&lng=2.1686&limit=2')
            ->assertStatus(200)
            ->assertJsonCount(2, 'data.events')
            ->assertJsonPath('data.pagination.total_count', 4);

        // Page 1 must be the two globally nearest — not the first two rows the
        // database happened to return, re-sorted among themselves.
        $this->assertSame(
            [$nearest->id, $near->id],
            collect($response->json('data.events'))->pluck('id')->all(),
        );

        $page2 = $this->actingAs($attendee)
            ->getJson('/api/v1/events/discover?lat=41.3874&lng=2.1686&limit=2&page=2')
            ->assertStatus(200)
            ->assertJsonCount(2, 'data.events');

        $this->assertSame(
            [$mid->id, $far->id],
            collect($page2->json('data.events'))->pluck('id')->all(),
        );
    }

    public function test_the_total_counts_only_events_inside_the_radius(): void
    {
        $attendee = Profile::factory()->attendee()->create();

        $inside = $this->createEventAt(41.3900, 2.1700); // ~0.3km

        // A bounding-box CORNER: within ±(50/111)° lat and ±(50/(111·cos φ))° lng of
        // the query point, but ~69km away as the crow flies. The box-then-paginate
        // implementation counted it in `total_count` and then dropped it from the
        // page, so the count and the list disagreed.
        $this->createEventAt(41.3874 + 0.44, 2.1686 + 0.59);

        $this->actingAs($attendee)
            ->getJson('/api/v1/events/discover?lat=41.3874&lng=2.1686&radius_km=50')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.events')
            ->assertJsonPath('data.events.0.id', $inside->id)
            ->assertJsonPath('data.pagination.total_count', 1);
    }

    public function test_an_event_just_outside_the_radius_is_excluded_and_just_inside_is_kept(): void
    {
        $attendee = Profile::factory()->attendee()->create();

        // 0.1 degrees north ≈ 11.1207 km. A 11.2 km radius keeps it, 11.0 km does not.
        $this->createEventAt(41.4874, 2.1686);

        $this->actingAs($attendee)
            ->getJson('/api/v1/events/discover?lat=41.3874&lng=2.1686&radius_km=11.2')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.events');

        $this->actingAs($attendee)
            ->getJson('/api/v1/events/discover?lat=41.3874&lng=2.1686&radius_km=11')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data.events')
            ->assertJsonPath('data.pagination.total_count', 0);
    }
}
