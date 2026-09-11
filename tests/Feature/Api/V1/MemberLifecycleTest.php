<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\CommunityMemberStatus;
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
 * Active Member, and the three counts (kolabing-app#147).
 *
 * The definitions under test:
 *   Follower      — interested
 *   Member        — belongs
 *   Active Member — belongs AND attended within 90 days
 *
 * The part worth guarding is that going quiet **does not** cost you membership.
 * A member who has not been in four months is still a member; they only stop
 * counting as Active, and their next check-in flips them back with no other
 * action taken by anyone.
 */
class MemberLifecycleTest extends TestCase
{
    use LazilyRefreshDatabase;

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
        $profile = Profile::query()->create([
            'email' => 'person-'.uniqid().'@example.test',
            'password' => 'secret1234',
            'user_type' => UserType::Attendee,
        ]);
        AttendeeProfile::factory()->create(['profile_id' => $profile->id]);

        return $profile;
    }

    private function community(?Profile $owner = null): Community
    {
        return Community::query()->create([
            'owner_profile_id' => ($owner ?? $this->leader())->id,
            'name' => 'Lifecycle Club',
            'slug' => 'lifecycle-'.uniqid(),
            'type' => 'running',
            'join_policy' => JoinPolicy::Open->value,
        ]);
    }

    private function member(Community $community, Profile $profile, ?Carbon $lastAttended): CommunityMember
    {
        return $community->members()->create([
            'profile_id' => $profile->id,
            'status' => CommunityMemberStatus::Active->value,
            'joined_at' => Carbon::now()->subYear(),
            'last_attended_at' => $lastAttended,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | The 90-day window
    |--------------------------------------------------------------------------
    */

    public function test_a_member_who_attended_recently_is_active(): void
    {
        $community = $this->community();
        $member = $this->member($community, $this->attendee(), Carbon::now()->subDays(30));

        $this->assertTrue($member->isActiveMember());
        $this->assertSame(1, $community->members()->activeMembers()->count());
    }

    /**
     * The boundary, both sides of it. 90 is the window, so day 89 is in and day
     * 91 is out — and if that ever changes it changes in one constant.
     */
    public function test_the_window_boundary(): void
    {
        $community = $this->community();
        $inside = $this->member($community, $this->attendee(), Carbon::now()->subDays(89));
        $outside = $this->member($community, $this->attendee(), Carbon::now()->subDays(91));

        $this->assertTrue($inside->isActiveMember());
        $this->assertFalse($outside->isActiveMember());
        $this->assertSame(1, $community->members()->activeMembers()->count());
    }

    /**
     * The one that matters: going quiet costs the Active flag and nothing else.
     */
    public function test_an_inactive_member_is_still_a_member(): void
    {
        $community = $this->community();
        $profile = $this->attendee();
        $this->member($community, $profile, Carbon::now()->subDays(200));

        $this->assertSame(0, $community->members()->activeMembers()->count());
        $this->assertTrue(
            $community->members()
                ->where('profile_id', $profile->id)
                ->where('status', CommunityMemberStatus::Active->value)
                ->exists(),
            'they stopped being Active, not a Member'
        );
    }

    public function test_a_member_who_never_attended_is_not_active(): void
    {
        $community = $this->community();
        $member = $this->member($community, $this->attendee(), null);

        $this->assertFalse($member->isActiveMember());
    }

    /**
     * Attending again makes them Active with no other action — the stamp lands
     * inside the check-in, so nothing has to be recalculated on a schedule.
     */
    public function test_checking_in_again_makes_a_lapsed_member_active(): void
    {
        $leader = $this->leader();
        $community = $this->community($leader);
        $profile = $this->attendee();
        $this->member($community, $profile, Carbon::now()->subDays(200));

        $event = Event::factory()->forProfile($leader)->create([
            'community_id' => $community->id,
            'is_active' => true,
            'checkin_token' => 'lifecycle-token',
            'event_date' => Carbon::today(),
            'starts_at' => Carbon::today()->setTime(18, 0),
        ]);

        $this->actingAs($profile)
            ->postJson('/api/v1/checkin', ['token' => 'lifecycle-token'])
            ->assertSuccessful();

        $this->assertSame(1, $community->members()->activeMembers()->count());
    }

    /**
     * Attending a community's event as a stranger is not membership activity —
     * otherwise "active member" would quietly mean "person who came once".
     */
    public function test_a_non_members_check_in_creates_no_membership_activity(): void
    {
        $leader = $this->leader();
        $community = $this->community($leader);
        $stranger = $this->attendee();

        $event = Event::factory()->forProfile($leader)->create([
            'community_id' => $community->id,
            'is_active' => true,
            'checkin_token' => 'stranger-token',
            'event_date' => Carbon::today(),
        ]);

        $this->actingAs($stranger)
            ->postJson('/api/v1/checkin', ['token' => 'stranger-token'])
            ->assertSuccessful();

        $this->assertSame(0, $community->members()->count());
        $this->assertSame(0, $community->members()->activeMembers()->count());
    }

    /*
    |--------------------------------------------------------------------------
    | The counts
    |--------------------------------------------------------------------------
    */

    public function test_a_leader_sees_all_three_counts(): void
    {
        $leader = $this->leader();
        $community = $this->community($leader);

        $this->member($community, $this->attendee(), Carbon::now()->subDays(10));
        $this->member($community, $this->attendee(), Carbon::now()->subDays(200));
        CommunityFollower::query()->create([
            'community_id' => $community->id,
            'profile_id' => $this->attendee()->id,
            'followed_at' => Carbon::now(),
        ]);

        // Two members (one active, one lapsed) and one follower who is neither
        // — but the two members now follow too (#146), so followers is 3.
        $this->actingAs($leader)
            ->getJson("/api/v1/communities/{$community->id}/stats")
            ->assertSuccessful()
            ->assertJsonPath('data.audience.members', 2)
            ->assertJsonPath('data.audience.active_members', 1)
            ->assertJsonPath('data.audience.active_window_days', CommunityMember::ACTIVE_WINDOW_DAYS);
    }

    /**
     * The public number is Active Members, not the total. Total membership only
     * ever grows, so after a couple of years it stops describing a community and
     * starts flattering it.
     */
    public function test_the_public_payload_hides_the_total_member_count(): void
    {
        $community = $this->community();
        $this->member($community, $this->attendee(), Carbon::now()->subDays(10));
        $this->member($community, $this->attendee(), Carbon::now()->subDays(500));

        $this->actingAs($this->attendee())
            ->getJson("/api/v1/communities/{$community->id}")
            ->assertSuccessful()
            ->assertJsonPath('audience.active_members', 1)
            ->assertJsonMissingPath('audience.members');
    }

    /*
    |--------------------------------------------------------------------------
    | The membership prompt's inputs (#148)
    |--------------------------------------------------------------------------
    */

    public function test_a_check_in_says_which_community_and_whether_you_belong(): void
    {
        $leader = $this->leader();
        $community = $this->community($leader);
        $stranger = $this->attendee();

        Event::factory()->forProfile($leader)->create([
            'community_id' => $community->id,
            'is_active' => true,
            'checkin_token' => 'prompt-token',
            'event_date' => Carbon::today(),
        ]);

        $this->actingAs($stranger)
            ->postJson('/api/v1/checkin', ['token' => 'prompt-token'])
            ->assertSuccessful()
            ->assertJsonPath('data.community.id', $community->id)
            ->assertJsonPath('data.community.name', $community->name)
            ->assertJsonPath('data.is_member', false);
    }

    public function test_an_existing_member_is_reported_as_one(): void
    {
        $leader = $this->leader();
        $community = $this->community($leader);
        $profile = $this->attendee();
        $this->member($community, $profile, null);

        Event::factory()->forProfile($leader)->create([
            'community_id' => $community->id,
            'is_active' => true,
            'checkin_token' => 'member-token',
            'event_date' => Carbon::today(),
        ]);

        $this->actingAs($profile)
            ->postJson('/api/v1/checkin', ['token' => 'member-token'])
            ->assertSuccessful()
            ->assertJsonPath('data.is_member', true);
    }

    /**
     * No community, nothing to join, nothing to ask.
     */
    public function test_an_event_without_a_community_reports_no_community(): void
    {
        $host = Profile::factory()->business()->create();

        Event::factory()->forProfile($host)->create([
            'community_id' => null,
            'is_active' => true,
            'checkin_token' => 'solo-token',
            'event_date' => Carbon::today(),
        ]);

        $this->actingAs($this->attendee())
            ->postJson('/api/v1/checkin', ['token' => 'solo-token'])
            ->assertSuccessful()
            ->assertJsonPath('data.community', null)
            ->assertJsonPath('data.is_member', null);
    }

    /**
     * The backfill's job, checked at the model level: a membership created
     * through the service always carries its follow.
     */
    public function test_membership_created_through_the_service_carries_a_follow(): void
    {
        $community = $this->community();
        $profile = $this->attendee();

        $this->actingAs($profile)
            ->postJson("/api/v1/communities/{$community->id}/join")
            ->assertSuccessful();

        $this->assertTrue(CommunityFollower::query()
            ->where('community_id', $community->id)
            ->where('profile_id', $profile->id)
            ->exists());
    }
}
