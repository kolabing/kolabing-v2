<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\CommunityMemberStatus;
use App\Enums\JoinPolicy;
use App\Enums\UserType;
use App\Models\Community;
use App\Models\CommunityFollower;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Following a community (kolabing-app#138).
 *
 * The tests that matter here are the negative ones: a follower must come away
 * with none of what membership grants.
 */
class CommunityFollowTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function community(JoinPolicy $policy = JoinPolicy::Open): Community
    {
        $owner = Profile::query()->create([
            'email' => 'leader-'.uniqid().'@example.test',
            'password' => 'secret1234',
            'user_type' => UserType::Community,
        ]);

        return Community::query()->create([
            'owner_profile_id' => $owner->id,
            'name' => 'Follow Test Club',
            'slug' => 'follow-'.uniqid(),
            'type' => 'running',
            'join_policy' => $policy->value,
        ]);
    }

    private function person(): Profile
    {
        return Profile::query()->create([
            'email' => 'person-'.uniqid().'@example.test',
            'password' => 'secret1234',
            'user_type' => UserType::Attendee,
        ]);
    }

    public function test_following_creates_one_row_and_returns_201(): void
    {
        $community = $this->community();
        $person = $this->person();

        $response = $this->actingAs($person)
            ->postJson("/api/v1/communities/{$community->id}/follow");

        $response->assertStatus(201)
            ->assertJsonPath('data.is_following', true)
            ->assertJsonPath('data.followers_count', 1);

        $this->assertSame(1, CommunityFollower::query()
            ->where('community_id', $community->id)
            ->where('profile_id', $person->id)
            ->count());
    }

    /**
     * A double tap, or a retry on a flaky connection, must not surface the
     * unique constraint as a 500.
     */
    public function test_following_twice_is_idempotent(): void
    {
        $community = $this->community();
        $person = $this->person();

        $this->actingAs($person)
            ->postJson("/api/v1/communities/{$community->id}/follow")
            ->assertStatus(201);

        $this->actingAs($person)
            ->postJson("/api/v1/communities/{$community->id}/follow")
            ->assertStatus(200)
            ->assertJsonPath('data.is_following', true)
            ->assertJsonPath('data.followers_count', 1);

        $this->assertSame(1, CommunityFollower::query()->count());
    }

    public function test_unfollowing_removes_the_row(): void
    {
        $community = $this->community();
        $person = $this->person();

        $this->actingAs($person)->postJson("/api/v1/communities/{$community->id}/follow");

        $this->actingAs($person)
            ->deleteJson("/api/v1/communities/{$community->id}/follow")
            ->assertStatus(200)
            ->assertJsonPath('data.is_following', false)
            ->assertJsonPath('data.followers_count', 0);

        $this->assertSame(0, CommunityFollower::query()->count());
    }

    public function test_unfollowing_when_not_following_is_a_no_op(): void
    {
        $community = $this->community();

        $this->actingAs($this->person())
            ->deleteJson("/api/v1/communities/{$community->id}/follow")
            ->assertStatus(200)
            ->assertJsonPath('data.is_following', false);
    }

    public function test_following_requires_authentication(): void
    {
        $community = $this->community();

        $this->postJson("/api/v1/communities/{$community->id}/follow")
            ->assertStatus(401);
    }

    // -------------------------------------------------------------------------
    // The point of the whole split: a follower is not a member.
    // -------------------------------------------------------------------------

    public function test_following_creates_no_membership(): void
    {
        $community = $this->community();
        $person = $this->person();

        $this->actingAs($person)->postJson("/api/v1/communities/{$community->id}/follow");

        $this->assertFalse(
            $community->members()
                ->where('profile_id', $person->id)
                ->where('status', CommunityMemberStatus::Active->value)
                ->exists()
        );
    }

    public function test_a_follower_cannot_read_the_member_roster(): void
    {
        $community = $this->community();
        $person = $this->person();

        $this->actingAs($person)->postJson("/api/v1/communities/{$community->id}/follow");

        // The roster is a manager surface and stays one.
        $this->actingAs($person)
            ->getJson("/api/v1/communities/{$community->id}/members")
            ->assertStatus(403);
    }

    public function test_a_follower_does_not_appear_in_the_membership_list(): void
    {
        $community = $this->community();
        $person = $this->person();

        $this->actingAs($person)->postJson("/api/v1/communities/{$community->id}/follow");

        $response = $this->actingAs($person)->getJson('/api/v1/me/memberships');

        $response->assertStatus(200);
        $memberships = $response->json('data.memberships') ?? $response->json('data') ?? [];
        $ids = collect($memberships)
            ->pluck('community.id')
            ->filter()
            ->all();

        $this->assertNotContains($community->id, $ids);
    }

    // -------------------------------------------------------------------------
    // The feed: a followed community's events show up without joining
    // -------------------------------------------------------------------------

    private function eventFor(Community $community, string $name, int $daysAhead = 3): \App\Models\Event
    {
        return \App\Models\Event::query()->create([
            'profile_id' => $community->owner_profile_id,
            'community_id' => $community->id,
            'name' => $name,
            'partner_name' => $community->name,
            'partner_type' => UserType::Community->value,
            'event_date' => Carbon::today()->addDays($daysAhead),
            'starts_at' => Carbon::today()->addDays($daysAhead)->setTime(18, 0),
            'visibility' => 'public',
            'is_active' => true,
        ]);
    }

    public function test_following_puts_a_communitys_events_in_my_feed(): void
    {
        $followed = $this->community();
        $ignored = $this->community();
        $this->eventFor($followed, 'Followed run');
        $this->eventFor($ignored, 'Someone elses run');

        $person = $this->person();
        $this->actingAs($person)->postJson("/api/v1/communities/{$followed->id}/follow");

        $response = $this->actingAs($person)
            ->getJson('/api/v1/events?following=me&time=upcoming');

        $response->assertStatus(200);
        $names = collect($response->json('data.events'))->pluck('name')->all();

        $this->assertSame(['Followed run'], $names);
    }

    /**
     * `following=me` has to count as a scoping filter. Without that the
     * controller's back-compat branch would AND it with "my own events" and the
     * feed would always be empty.
     */
    public function test_the_feed_is_not_narrowed_to_my_own_events(): void
    {
        $followed = $this->community();
        $this->eventFor($followed, 'Followed run');

        $person = $this->person();
        $this->actingAs($person)->postJson("/api/v1/communities/{$followed->id}/follow");

        // The viewer hosts nothing, so a `profile_id = me` fallback would hide
        // everything.
        $this->actingAs($person)
            ->getJson('/api/v1/events?following=me')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.events');
    }

    /**
     * The one that was wrong. `following=me` scoped by follow and nothing else,
     * so following a community — one tap, no approval, nobody asked — handed
     * over its member-only events, ids included. That is the precise privilege
     * the follower/member split exists to withhold (kolabing-app#138), and this
     * listing is worse than the signup gap in BACKLOG IF-28, because it is what
     * hands out the ids in the first place.
     */
    public function test_the_feed_does_not_include_a_followed_communitys_member_only_events(): void
    {
        $followed = $this->community();
        $person = $this->person();

        $this->eventFor($followed, 'Open to all');
        $this->eventFor($followed, 'Members only')->update(['visibility' => 'members']);

        $this->actingAs($person)
            ->postJson("/api/v1/communities/{$followed->id}/follow")
            ->assertSuccessful();

        $names = collect(
            $this->actingAs($person)
                ->getJson('/api/v1/events?following=me&time=upcoming')
                ->json('data.events')
        )->pluck('name')->all();

        $this->assertContains('Open to all', $names);
        $this->assertNotContains('Members only', $names, 'a follower is not a member');
    }

    /**
     * The gate belongs to the follows branch alone: a leader listing their OWN
     * events must still see the member-only ones.
     */
    public function test_the_public_only_gate_does_not_narrow_a_leaders_own_listing(): void
    {
        $community = $this->community();
        $this->eventFor($community, 'Members only')->update(['visibility' => 'members']);

        $owner = Profile::query()->findOrFail($community->owner_profile_id);

        $names = collect(
            $this->actingAs($owner)
                ->getJson('/api/v1/events?time=upcoming')
                ->json('data.events')
        )->pluck('name')->all();

        $this->assertContains('Members only', $names);
    }

    public function test_unfollowing_takes_the_events_back_out_of_the_feed(): void
    {
        $followed = $this->community();
        $this->eventFor($followed, 'Followed run');
        $person = $this->person();

        $this->actingAs($person)->postJson("/api/v1/communities/{$followed->id}/follow");
        $this->actingAs($person)->deleteJson("/api/v1/communities/{$followed->id}/follow");

        $this->actingAs($person)
            ->getJson('/api/v1/events?following=me')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data.events');
    }

    /**
     * The default listing still means "my own events" — the existing contract
     * every shipped build relies on.
     */
    public function test_the_unfiltered_listing_is_unchanged(): void
    {
        $followed = $this->community();
        $this->eventFor($followed, 'Followed run');
        $person = $this->person();
        $this->actingAs($person)->postJson("/api/v1/communities/{$followed->id}/follow");

        $this->actingAs($person)
            ->getJson('/api/v1/events')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data.events');
    }

    /**
     * The two relationships are independent axes: a member who never tapped
     * follow is not a follower, and being one does not imply the other.
     */
    /**
     * The rule CHANGED here — this test used to assert that a membership row
     * carries no follow, and that was a correct statement of the old rule
     * (kolabing-v2#211). The product model now says a Member is always a
     * Follower (kolabing-app#146), so the assertion inverts.
     *
     * Note it goes through the API rather than creating the row directly: the
     * follow is granted by CommunityMemberService, so a test that inserts a
     * membership behind the service's back proves nothing about the rule.
     */
    public function test_joining_makes_you_a_follower_too(): void
    {
        $community = $this->community();
        $person = $this->person();

        $this->actingAs($person)
            ->postJson("/api/v1/communities/{$community->id}/join")
            ->assertSuccessful();

        $this->assertTrue(
            CommunityFollower::query()
                ->where('community_id', $community->id)
                ->where('profile_id', $person->id)
                ->exists(),
            'a member should not have to press Follow separately'
        );
    }

    /**
     * The half that has NOT changed, and must not: following grants nothing.
     * This is the direction the separate tables exist to protect — every
     * member-gated query in the app reads `community_members`, and a follower
     * must never appear in it.
     */
    public function test_following_still_does_not_make_you_a_member(): void
    {
        $community = $this->community();
        $person = $this->person();

        $this->actingAs($person)
            ->postJson("/api/v1/communities/{$community->id}/follow")
            ->assertStatus(201);

        $this->assertFalse(
            $community->members()->where('profile_id', $person->id)->exists(),
            'following is interest, not membership'
        );
    }

    /**
     * Leaving must not take the follow with it: losing membership is not losing
     * interest, and unfollowing is something a person does deliberately.
     */
    public function test_leaving_a_community_keeps_the_follow(): void
    {
        $community = $this->community();
        $person = $this->person();

        $this->actingAs($person)->postJson("/api/v1/communities/{$community->id}/join")->assertSuccessful();
        $community->members()->where('profile_id', $person->id)->delete();

        $this->assertTrue(
            CommunityFollower::query()
                ->where('community_id', $community->id)
                ->where('profile_id', $person->id)
                ->exists()
        );
    }
}
