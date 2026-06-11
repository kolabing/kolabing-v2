<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\EventVisibility;
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
            // Discover only surfaces PUBLIC events; default the fixtures to public.
            'visibility' => EventVisibility::Public->value,
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

    public function test_discover_includes_public_future_events(): void
    {
        $attendee = Profile::factory()->attendee()->create();
        $city = City::factory()->create();

        // Public event 10 days out (helper defaults to public + future).
        $public = $this->createHostedEvent($city, 'run_club', 'Public Club');

        $response = $this->actingAs($attendee)
            ->getJson('/api/v1/events/discover?city_id='.$city->id);

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.events')
            ->assertJsonPath('data.events.0.id', $public->id)
            ->assertJsonPath('data.events.0.visibility', 'public');
    }

    public function test_discover_excludes_members_only_events(): void
    {
        $attendee = Profile::factory()->attendee()->create();
        $city = City::factory()->create();

        // A members-visibility event in the same city must NOT surface in discover.
        $this->createHostedEvent($city, 'run_club', 'Members Club', [
            'visibility' => EventVisibility::Members->value,
        ]);

        $this->actingAs($attendee)
            ->getJson('/api/v1/events/discover?city_id='.$city->id)
            ->assertStatus(200)
            ->assertJsonCount(0, 'data.events');
    }

    public function test_discover_date_week_filters_to_this_week(): void
    {
        $attendee = Profile::factory()->attendee()->create();
        $city = City::factory()->create();

        // Mid-week (Wednesday of the current week) — inside the Mon..Sun window.
        $inWeek = now()->startOfWeek()->addDays(2)->setTime(18, 0);
        $thisWeek = $this->createHostedEvent($city, 'run_club', 'This Week', [
            'event_date' => $inWeek->toDateString(),
            'starts_at' => $inWeek,
            'ends_at' => $inWeek->copy()->addHours(2),
        ]);

        // Next week — outside the window.
        $nextWeek = now()->startOfWeek()->addWeek()->addDays(2)->setTime(18, 0);
        $this->createHostedEvent($city, 'run_club', 'Next Week', [
            'event_date' => $nextWeek->toDateString(),
            'starts_at' => $nextWeek,
            'ends_at' => $nextWeek->copy()->addHours(2),
        ]);

        $this->actingAs($attendee)
            ->getJson('/api/v1/events/discover?city_id='.$city->id.'&date=week')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.events')
            ->assertJsonPath('data.events.0.id', $thisWeek->id);
    }

    public function test_discover_date_weekend_filters_to_this_weekend(): void
    {
        $attendee = Profile::factory()->attendee()->create();
        $city = City::factory()->create();

        // This week's Saturday — inside the Sat..Sun window.
        $saturday = now()->startOfWeek()->addDays(5)->setTime(11, 0);
        $weekendEvent = $this->createHostedEvent($city, 'run_club', 'Weekend', [
            'event_date' => $saturday->toDateString(),
            'starts_at' => $saturday,
            'ends_at' => $saturday->copy()->addHours(2),
        ]);

        // This week's Wednesday — a weekday, excluded by date=weekend.
        $wednesday = now()->startOfWeek()->addDays(2)->setTime(18, 0);
        $this->createHostedEvent($city, 'run_club', 'Midweek', [
            'event_date' => $wednesday->toDateString(),
            'starts_at' => $wednesday,
            'ends_at' => $wednesday->copy()->addHours(2),
        ]);

        $this->actingAs($attendee)
            ->getJson('/api/v1/events/discover?city_id='.$city->id.'&date=weekend')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.events')
            ->assertJsonPath('data.events.0.id', $weekendEvent->id);
    }

    public function test_discover_date_month_filters_to_this_month(): void
    {
        $attendee = Profile::factory()->attendee()->create();
        $city = City::factory()->create();

        // Mid current month — inside the window.
        $inMonth = now()->startOfMonth()->addDays(14)->setTime(18, 0);
        $thisMonth = $this->createHostedEvent($city, 'run_club', 'This Month', [
            'event_date' => $inMonth->toDateString(),
            'starts_at' => $inMonth,
            'ends_at' => $inMonth->copy()->addHours(2),
        ]);

        // Next month — outside the window.
        $nextMonth = now()->startOfMonth()->addMonthNoOverflow()->addDays(10)->setTime(18, 0);
        $this->createHostedEvent($city, 'run_club', 'Next Month', [
            'event_date' => $nextMonth->toDateString(),
            'starts_at' => $nextMonth,
            'ends_at' => $nextMonth->copy()->addHours(2),
        ]);

        $this->actingAs($attendee)
            ->getJson('/api/v1/events/discover?city_id='.$city->id.'&date=month')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.events')
            ->assertJsonPath('data.events.0.id', $thisMonth->id);
    }

    public function test_discover_matches_event_own_city_when_community_has_no_profile(): void
    {
        $attendee = Profile::factory()->attendee()->create();
        $city = City::factory()->create();
        $owner = Profile::factory()->business()->create();

        // Leader-created community: type=other, NO community profile (so no profile
        // city). Without events.city_id this event could never be discovered.
        $community = Community::factory()->create([
            'name' => 'Real Run Club',
            'type' => 'other',
            'community_profile_id' => null,
        ]);

        $event = Event::factory()->forProfile($owner)->create([
            'community_id' => $community->id,
            'city_id' => $city->id,
            // is_active=false is how community upcoming events are actually created;
            // discover must still surface it (the relaxed gate).
            'is_active' => false,
            'visibility' => EventVisibility::Public->value,
            'event_date' => now()->addDays(10)->toDateString(),
            'starts_at' => now()->addDays(10),
            'ends_at' => now()->addDays(10)->addHours(2),
        ]);

        $this->actingAs($attendee)
            ->getJson('/api/v1/events/discover?city_id='.$city->id)
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.events')
            ->assertJsonPath('data.events.0.id', $event->id)
            ->assertJsonPath('data.events.0.city_id', $city->id)
            ->assertJsonPath('data.events.0.city_name', $city->name);
    }

    public function test_discover_event_own_city_overrides_community_profile_city(): void
    {
        $attendee = Profile::factory()->attendee()->create();
        $eventCity = City::factory()->create(['name' => 'Madrid']);
        $communityCity = City::factory()->create(['name' => 'Barcelona']);

        // Community sits in Barcelona, but the event pins itself to Madrid.
        $event = $this->createHostedEvent($communityCity, 'run_club', 'Mismatch Club', [
            'city_id' => $eventCity->id,
            'is_active' => false,
        ]);

        // Shows under the EVENT's city (Madrid)...
        $this->actingAs($attendee)
            ->getJson('/api/v1/events/discover?city_id='.$eventCity->id)
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.events')
            ->assertJsonPath('data.events.0.id', $event->id)
            ->assertJsonPath('data.events.0.city_name', 'Madrid');

        // ...and NOT under the community's city (Barcelona), since its own city wins.
        $this->actingAs($attendee)
            ->getJson('/api/v1/events/discover?city_id='.$communityCity->id)
            ->assertStatus(200)
            ->assertJsonCount(0, 'data.events');
    }

    public function test_discover_falls_back_to_community_profile_city(): void
    {
        $attendee = Profile::factory()->attendee()->create();
        $city = City::factory()->create(['name' => 'Valencia']);

        // No events.city_id → effective city = community profile city.
        $event = $this->createHostedEvent($city, 'run_club', 'Fallback Club', [
            'city_id' => null,
        ]);

        $this->actingAs($attendee)
            ->getJson('/api/v1/events/discover?city_id='.$city->id)
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.events')
            ->assertJsonPath('data.events.0.id', $event->id)
            ->assertJsonPath('data.events.0.city_name', 'Valencia');
    }

    public function test_discover_surfaces_inactive_public_upcoming_event(): void
    {
        $attendee = Profile::factory()->attendee()->create();
        $city = City::factory()->create();

        // is_active=false is the real production state of community upcoming events.
        $event = $this->createHostedEvent($city, 'run_club', 'Inactive Club', [
            'is_active' => false,
        ]);

        $this->actingAs($attendee)
            ->getJson('/api/v1/events/discover?city_id='.$city->id)
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.events')
            ->assertJsonPath('data.events.0.id', $event->id);
    }

    public function test_discover_upcoming_excludes_past_events(): void
    {
        $attendee = Profile::factory()->attendee()->create();
        $city = City::factory()->create();

        $future = $this->createHostedEvent($city, 'run_club', 'Future');

        // A public but PAST event — excluded by the future-inclusive default.
        $past = now()->subDays(5)->setTime(18, 0);
        $this->createHostedEvent($city, 'run_club', 'Past', [
            'event_date' => $past->toDateString(),
            'starts_at' => $past,
            'ends_at' => $past->copy()->addHours(2),
        ]);

        $this->actingAs($attendee)
            ->getJson('/api/v1/events/discover?city_id='.$city->id.'&date=upcoming')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.events')
            ->assertJsonPath('data.events.0.id', $future->id);
    }
}
