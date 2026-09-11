<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\EventVisibility;
use App\Models\City;
use App\Models\Community;
use App\Models\CommunityFollower;
use App\Models\CommunityProfile;
use App\Models\Event;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * `GET /events/discover?following=1` — the events of communities the VIEWER
 * follows (kolabing-app#142).
 *
 * This is what makes following mean something: before it, a follow was recorded
 * and then read by nothing, so the tap had no consequence anywhere in the app.
 *
 * The two properties worth guarding are here as tests, not comments: a follower
 * still does not see member-only events (following is not membership —
 * kolabing-app#138), and asking for "my follows" with nothing to resolve the
 * viewer against returns NOTHING rather than everything.
 */
class EventDiscoveryFollowingTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * A community that hosts events, in $city.
     */
    private function createCommunity(City $city, string $name = 'Real Run Club', string $type = 'run_club'): Community
    {
        $communityProfile = CommunityProfile::factory()->create([
            'city_id' => $city->id,
            'community_type' => $type,
        ]);

        return Community::factory()->create([
            'name' => $name,
            'type' => $type,
            'community_profile_id' => $communityProfile->id,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createEvent(Community $community, array $attributes = []): Event
    {
        $owner = Profile::factory()->business()->create();

        return Event::factory()->forProfile($owner)->create(array_merge([
            'community_id' => $community->id,
            'location_lat' => 41.3900,
            'location_lng' => 2.1700,
            'is_active' => true,
            'visibility' => EventVisibility::Public->value,
            'event_date' => now()->addDays(10)->toDateString(),
            'starts_at' => now()->addDays(10),
            'ends_at' => now()->addDays(10)->addHours(2),
        ], $attributes));
    }

    private function follow(Profile $profile, Community $community): void
    {
        CommunityFollower::query()->create([
            'community_id' => $community->id,
            'profile_id' => $profile->id,
            'followed_at' => Carbon::now(),
        ]);
    }

    public function test_following_returns_only_events_of_followed_communities(): void
    {
        $attendee = Profile::factory()->attendee()->create();
        $city = City::factory()->create();

        $followed = $this->createCommunity($city, 'Followed Club');
        $stranger = $this->createCommunity($city, 'Some Other Club');

        $this->follow($attendee, $followed);

        $mine = $this->createEvent($followed);
        $this->createEvent($stranger);

        $response = $this->actingAs($attendee)
            ->getJson('/api/v1/events/discover?following=1');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.events')
            ->assertJsonPath('data.events.0.id', $mine->id);
    }

    /**
     * The point of making lat/lng optional: a community you followed is
     * relevant wherever it is, and a city is only ever a guess at relevance.
     */
    public function test_following_needs_no_city_or_coordinates(): void
    {
        $attendee = Profile::factory()->attendee()->create();
        $madrid = City::factory()->create(['name' => 'Madrid']);

        $followed = $this->createCommunity($madrid, 'Madrid Run Club');
        $this->follow($attendee, $followed);
        $event = $this->createEvent($followed);

        $this->actingAs($attendee)
            ->getJson('/api/v1/events/discover?following=1')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.events')
            ->assertJsonPath('data.events.0.id', $event->id);
    }

    public function test_following_composes_with_the_date_filter(): void
    {
        $attendee = Profile::factory()->attendee()->create();
        $city = City::factory()->create();

        $followed = $this->createCommunity($city);
        $this->follow($attendee, $followed);

        $today = $this->createEvent($followed, [
            'event_date' => now()->toDateString(),
            'starts_at' => now()->setTime(18, 0),
            'ends_at' => now()->setTime(20, 0),
        ]);
        // Also the followed community's, but not today.
        $this->createEvent($followed);

        $this->actingAs($attendee)
            ->getJson('/api/v1/events/discover?following=1&date=today')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.events')
            ->assertJsonPath('data.events.0.id', $today->id);
    }

    /**
     * The security-critical one. Following is not membership: a member-only
     * event of a community you follow is exactly as invisible as it is to a
     * stranger (kolabing-app#138).
     */
    public function test_a_followed_communitys_member_only_event_stays_hidden(): void
    {
        $attendee = Profile::factory()->attendee()->create();
        $city = City::factory()->create();

        $followed = $this->createCommunity($city);
        $this->follow($attendee, $followed);

        $public = $this->createEvent($followed);
        $this->createEvent($followed, ['visibility' => EventVisibility::Members->value]);

        $this->actingAs($attendee)
            ->getJson('/api/v1/events/discover?following=1')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.events')
            ->assertJsonPath('data.events.0.id', $public->id);
    }

    public function test_following_nothing_returns_an_empty_page_not_every_event(): void
    {
        $attendee = Profile::factory()->attendee()->create();
        $city = City::factory()->create();

        // Two events exist; the attendee follows neither host.
        $this->createEvent($this->createCommunity($city, 'A'));
        $this->createEvent($this->createCommunity($city, 'B'));

        $this->actingAs($attendee)
            ->getJson('/api/v1/events/discover?following=1')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data.events')
            ->assertJsonPath('data.pagination.total_count', 0);
    }

    /**
     * `following` reads the token, never the query string — so it cannot be
     * pointed at someone else's follows.
     */
    public function test_one_attendees_follows_do_not_leak_into_anothers_feed(): void
    {
        $mine = Profile::factory()->attendee()->create();
        $theirs = Profile::factory()->attendee()->create();
        $city = City::factory()->create();

        $theirCommunity = $this->createCommunity($city, 'Their Club');
        $this->follow($theirs, $theirCommunity);
        $this->createEvent($theirCommunity);

        $this->actingAs($mine)
            ->getJson('/api/v1/events/discover?following=1')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data.events');
    }

    /**
     * `following` relaxes the lat/lng requirement, and `required_without_all`
     * tests presence rather than truthiness — so a falsy `following` is
     * stripped before validation. Without that, `following=0` would be a way to
     * ask for every public event on the platform with no scope at all.
     */
    public function test_a_falsy_following_still_requires_a_city_or_coordinates(): void
    {
        $attendee = Profile::factory()->attendee()->create();

        $this->actingAs($attendee)
            ->getJson('/api/v1/events/discover?following=0')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['lat', 'lng']);
    }

    /**
     * An event with no host community cannot be followed, and must not sneak
     * into the followed feed.
     */
    public function test_an_event_without_a_host_community_is_not_in_the_following_feed(): void
    {
        $attendee = Profile::factory()->attendee()->create();
        $city = City::factory()->create();

        $followed = $this->createCommunity($city);
        $this->follow($attendee, $followed);
        $mine = $this->createEvent($followed);

        $owner = Profile::factory()->business()->create();
        Event::factory()->forProfile($owner)->create([
            'community_id' => null,
            'is_active' => true,
            'visibility' => EventVisibility::Public->value,
            'event_date' => now()->addDays(4)->toDateString(),
            'starts_at' => now()->addDays(4),
            'ends_at' => now()->addDays(4)->addHours(2),
        ]);

        $this->actingAs($attendee)
            ->getJson('/api/v1/events/discover?following=1')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.events')
            ->assertJsonPath('data.events.0.id', $mine->id);
    }
}
