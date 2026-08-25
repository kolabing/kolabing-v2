<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\CommunityMemberStatus;
use App\Enums\EventVisibility;
use App\Enums\JoinPolicy;
use App\Enums\UserType;
use App\Models\AttendeeProfile;
use App\Models\Community;
use App\Models\CommunityFollower;
use App\Models\CommunityMember;
use App\Models\Event;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The four audiences an event can have (kolabing-app#157, §15).
 *
 *   public → followers → members → active members
 *
 * Each is narrower than the last, and the tests below walk one person down the
 * ladder: the same follower who can join a `followers` event cannot join a
 * `members` one, and the same member who can join that cannot join an
 * `active_members` one once they have gone quiet.
 *
 * The rule lives in ONE place (`audienceRefusal`) precisely so the list view and
 * the detail view cannot disagree, and there is a test for that agreement too.
 */
class EventAudienceTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function leader(): Profile
    {
        return Profile::query()->create([
            'email' => 'leader-'.uniqid().'@example.test',
            'password' => 'secret1234',
            'user_type' => UserType::Community,
        ]);
    }

    private function attendee(): Profile
    {
        $profile = Profile::factory()->attendee()->create();
        AttendeeProfile::factory()->create(['profile_id' => $profile->id]);

        return $profile;
    }

    private function community(Profile $owner): Community
    {
        return Community::query()->create([
            'owner_profile_id' => $owner->id,
            'name' => 'Audience Club '.uniqid(),
            'slug' => 'audience-'.uniqid(),
            'type' => 'running',
            'join_policy' => JoinPolicy::Open->value,
        ]);
    }

    private function event(Profile $host, Community $community, EventVisibility $visibility): Event
    {
        return Event::factory()->forProfile($host)->create([
            'community_id' => $community->id,
            'visibility' => $visibility->value,
            'is_active' => true,
            'event_date' => Carbon::tomorrow(),
            'starts_at' => Carbon::tomorrow()->setTime(18, 0),
            'ends_at' => Carbon::tomorrow()->setTime(22, 0),
            'capacity' => null,
        ]);
    }

    private function follow(Community $community, Profile $profile): void
    {
        CommunityFollower::query()->create([
            'community_id' => $community->id,
            'profile_id' => $profile->id,
            'followed_at' => Carbon::now(),
        ]);
    }

    private function member(Community $community, Profile $profile, ?Carbon $lastAttended): CommunityMember
    {
        return $community->members()->create([
            'profile_id' => $profile->id,
            'status' => CommunityMemberStatus::Active->value,
            'joined_at' => Carbon::now()->subMonths(6),
            'last_attended_at' => $lastAttended,
        ]);
    }

    private function signup(Profile $profile, Event $event): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($profile)->postJson("/api/v1/events/{$event->id}/signup");
    }

    /*
    |--------------------------------------------------------------------------
    | Walking down the ladder
    |--------------------------------------------------------------------------
    */

    public function test_a_stranger_can_join_a_public_event(): void
    {
        $leader = $this->leader();
        $community = $this->community($leader);
        $event = $this->event($leader, $community, EventVisibility::Public);

        $this->signup($this->attendee(), $event)->assertSuccessful();
    }

    public function test_a_follower_can_join_a_followers_event(): void
    {
        $leader = $this->leader();
        $community = $this->community($leader);
        $event = $this->event($leader, $community, EventVisibility::Followers);

        $follower = $this->attendee();
        $this->follow($community, $follower);

        $this->signup($follower, $event)->assertSuccessful();
    }

    public function test_a_stranger_cannot_join_a_followers_event(): void
    {
        $leader = $this->leader();
        $community = $this->community($leader);
        $event = $this->event($leader, $community, EventVisibility::Followers);

        $this->signup($this->attendee(), $event)->assertStatus(422);
    }

    /**
     * The step that makes the ladder a ladder: following is not membership.
     */
    public function test_a_follower_cannot_join_a_members_event(): void
    {
        $leader = $this->leader();
        $community = $this->community($leader);
        $event = $this->event($leader, $community, EventVisibility::Members);

        $follower = $this->attendee();
        $this->follow($community, $follower);

        $this->signup($follower, $event)->assertStatus(422);
    }

    /**
     * A member always follows (kolabing-app#146), so the most open community
     * audience must never lock them out.
     */
    public function test_a_member_can_join_a_followers_event(): void
    {
        $leader = $this->leader();
        $community = $this->community($leader);
        $event = $this->event($leader, $community, EventVisibility::Followers);

        $member = $this->attendee();
        $this->member($community, $member, null);
        // Deliberately NO follow row — a membership predating the backfill must
        // not be refused from its own community's most open event.

        $this->signup($member, $event)->assertSuccessful();
    }

    public function test_an_active_member_can_join_an_active_members_event(): void
    {
        $leader = $this->leader();
        $community = $this->community($leader);
        $event = $this->event($leader, $community, EventVisibility::ActiveMembers);

        $member = $this->attendee();
        $this->member($community, $member, Carbon::now()->subDays(20));

        $this->signup($member, $event)->assertSuccessful();
    }

    /**
     * The narrowest step: still a member, no longer active.
     */
    public function test_a_lapsed_member_cannot_join_an_active_members_event(): void
    {
        $leader = $this->leader();
        $community = $this->community($leader);
        $event = $this->event($leader, $community, EventVisibility::ActiveMembers);

        $member = $this->attendee();
        $this->member($community, $member, Carbon::now()->subDays(200));

        $this->signup($member, $event)->assertStatus(422);
    }

    public function test_a_lapsed_member_can_still_join_a_members_event(): void
    {
        $leader = $this->leader();
        $community = $this->community($leader);
        $event = $this->event($leader, $community, EventVisibility::Members);

        $member = $this->attendee();
        $this->member($community, $member, Carbon::now()->subDays(200));

        // They are still a Member. Only Active lapsed.
        $this->signup($member, $event)->assertSuccessful();
    }

    /**
     * A leader is never locked out of their own community's event, at any level.
     */
    public function test_the_leader_passes_every_audience(): void
    {
        $leader = $this->leader();
        $community = $this->community($leader);

        foreach ([EventVisibility::Followers, EventVisibility::Members, EventVisibility::ActiveMembers] as $level) {
            $event = $this->event($leader, $community, $level);

            $this->signup($leader, $event)->assertSuccessful();
        }
    }

    /*
    |--------------------------------------------------------------------------
    | The list view must agree with the detail view
    |--------------------------------------------------------------------------
    */

    /**
     * The reason the rule was extracted into one method: three copies of an
     * access rule is how a list and a detail end up disagreeing about who can
     * see what.
     */
    public function test_the_listing_reports_the_same_access_as_the_signup_gate(): void
    {
        $leader = $this->leader();
        $community = $this->community($leader);
        $followersEvent = $this->event($leader, $community, EventVisibility::Followers);
        $membersEvent = $this->event($leader, $community, EventVisibility::Members);

        $follower = $this->attendee();
        $this->follow($community, $follower);

        $listed = collect(
            $this->actingAs($follower)
                ->getJson("/api/v1/events?community_id={$community->id}")
                ->assertSuccessful()
                ->json('data.events')
        )->keyBy('id');

        $this->assertTrue($listed[$followersEvent->id]['can_access']);
        $this->assertFalse($listed[$membersEvent->id]['can_access']);

        // And the gate agrees with what the list said.
        $this->signup($follower, $followersEvent)->assertSuccessful();
        $this->signup($follower, $membersEvent)->assertStatus(422);
    }

    /*
    |--------------------------------------------------------------------------
    | The feeds
    |--------------------------------------------------------------------------
    */

    /**
     * A `followers` event belongs in the Following feed — the viewer is one by
     * definition. It must NOT appear in the city feed, where a stranger is
     * browsing.
     */
    public function test_a_followers_event_is_in_the_following_feed_but_not_the_city_feed(): void
    {
        $leader = $this->leader();
        $community = $this->community($leader);
        $event = $this->event($leader, $community, EventVisibility::Followers);

        $follower = $this->attendee();
        $this->follow($community, $follower);

        $following = collect(
            $this->actingAs($follower)
                ->getJson('/api/v1/events/discover?following=1')
                ->json('data.events')
        )->pluck('id');

        $this->assertContains($event->id, $following);

        $stranger = $this->attendee();
        $city = collect(
            $this->actingAs($stranger)
                ->getJson('/api/v1/events/discover?lat=41.39&lng=2.17&radius_km=200')
                ->json('data.events')
        )->pluck('id');

        $this->assertNotContains($event->id, $city);
    }

    /**
     * Members-only events stay out of the follows feed — the decision recorded
     * in kolabing-v2#214 and unchanged: under-showing to a member beats
     * over-showing to a follower.
     */
    public function test_a_members_event_stays_out_of_the_following_feed(): void
    {
        $leader = $this->leader();
        $community = $this->community($leader);
        $this->event($leader, $community, EventVisibility::Members);
        $public = $this->event($leader, $community, EventVisibility::Public);

        $member = $this->attendee();
        $this->member($community, $member, Carbon::now());
        $this->follow($community, $member);

        $ids = collect(
            $this->actingAs($member)
                ->getJson('/api/v1/events/discover?following=1')
                ->json('data.events')
        )->pluck('id');

        $this->assertContains($public->id, $ids);
        $this->assertCount(1, $ids);
    }
}
