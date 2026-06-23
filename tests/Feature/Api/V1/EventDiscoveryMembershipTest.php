<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\CommunityMemberStatus;
use App\Enums\EventVisibility;
use App\Models\City;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\CommunityProfile;
use App\Models\Event;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Viewer-aware discover: an attendee must see member-visibility events of the
 * communities they ACTIVELY belong to, alongside public events — while a
 * non-member still never sees those member events (the §8.6 invariant). The
 * city / date / type filters still apply to the member events.
 */
class EventDiscoveryMembershipTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Build a community sitting in $city and a members-visibility event it hosts.
     *
     * @param  array<string, mixed>  $eventAttributes
     * @return array{0: Community, 1: Event}
     */
    private function createCommunityWithMembersEvent(
        City $city,
        string $type = 'run_club',
        array $eventAttributes = []
    ): array {
        $owner = Profile::factory()->business()->create();

        $communityProfile = CommunityProfile::factory()->create([
            'city_id' => $city->id,
            'community_type' => $type,
        ]);

        $community = Community::factory()->create([
            'type' => $type,
            'community_profile_id' => $communityProfile->id,
        ]);

        $event = Event::factory()->forProfile($owner)->create(array_merge([
            'community_id' => $community->id,
            'city_id' => $city->id,
            'location_lat' => 41.3900,
            'location_lng' => 2.1700,
            'is_active' => false,
            'visibility' => EventVisibility::Members->value,
            'event_date' => now()->addDays(10)->toDateString(),
            'starts_at' => now()->addDays(10),
            'ends_at' => now()->addDays(10)->addHours(2),
        ], $eventAttributes));

        return [$community, $event];
    }

    private function joinCommunity(
        Profile $member,
        Community $community,
        CommunityMemberStatus $status = CommunityMemberStatus::Active
    ): void {
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'profile_id' => $member->id,
            'status' => $status->value,
        ]);
    }

    public function test_discover_includes_members_event_of_joined_community(): void
    {
        $attendee = Profile::factory()->attendee()->create();
        $city = City::factory()->create();
        [$community, $event] = $this->createCommunityWithMembersEvent($city);
        $this->joinCommunity($attendee, $community);

        $this->actingAs($attendee)
            ->getJson('/api/v1/events/discover?city_id='.$city->id)
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.events')
            ->assertJsonPath('data.events.0.id', $event->id);
    }

    public function test_discover_still_excludes_members_event_for_non_member(): void
    {
        $stranger = Profile::factory()->attendee()->create();
        $city = City::factory()->create();
        $this->createCommunityWithMembersEvent($city);

        $this->actingAs($stranger)
            ->getJson('/api/v1/events/discover?city_id='.$city->id)
            ->assertStatus(200)
            ->assertJsonCount(0, 'data.events');
    }

    public function test_discover_member_events_still_respect_city_filter(): void
    {
        $attendee = Profile::factory()->attendee()->create();
        $barcelona = City::factory()->create(['name' => 'Barcelona']);
        $madrid = City::factory()->create(['name' => 'Madrid']);

        // Joined community, but its event is in Madrid.
        [$community] = $this->createCommunityWithMembersEvent($madrid);
        $this->joinCommunity($attendee, $community);

        // Filtering Barcelona must NOT surface the Madrid member event.
        $this->actingAs($attendee)
            ->getJson('/api/v1/events/discover?city_id='.$barcelona->id)
            ->assertStatus(200)
            ->assertJsonCount(0, 'data.events');
    }

    public function test_discover_excludes_member_events_when_membership_not_active(): void
    {
        $attendee = Profile::factory()->attendee()->create();
        $city = City::factory()->create();
        [$community] = $this->createCommunityWithMembersEvent($city);
        $this->joinCommunity($attendee, $community, CommunityMemberStatus::Removed);

        $this->actingAs($attendee)
            ->getJson('/api/v1/events/discover?city_id='.$city->id)
            ->assertStatus(200)
            ->assertJsonCount(0, 'data.events');
    }

    public function test_discover_geo_surfaces_member_event_of_joined_community(): void
    {
        $attendee = Profile::factory()->attendee()->create();
        $city = City::factory()->create();
        [$community, $event] = $this->createCommunityWithMembersEvent($city, eventAttributes: [
            'location_lat' => 41.3900,
            'location_lng' => 2.1700,
        ]);
        $this->joinCommunity($attendee, $community);

        $this->actingAs($attendee)
            ->getJson('/api/v1/events/discover?lat=41.3874&lng=2.1686')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.events')
            ->assertJsonPath('data.events.0.id', $event->id);
    }

    public function test_discover_combines_public_and_joined_member_events(): void
    {
        $attendee = Profile::factory()->attendee()->create();
        $city = City::factory()->create();

        // A joined community's members-only event.
        [$community, $memberEvent] = $this->createCommunityWithMembersEvent($city);
        $this->joinCommunity($attendee, $community);

        // A public event in the same city, hosted by a different community.
        [, $publicEvent] = $this->createCommunityWithMembersEvent($city, eventAttributes: [
            'visibility' => EventVisibility::Public->value,
        ]);

        $response = $this->actingAs($attendee)
            ->getJson('/api/v1/events/discover?city_id='.$city->id);

        $response->assertStatus(200)->assertJsonCount(2, 'data.events');

        $ids = collect($response->json('data.events'))->pluck('id')->all();
        $this->assertContains($memberEvent->id, $ids);
        $this->assertContains($publicEvent->id, $ids);
    }
}
