<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\CommunityMemberStatus;
use App\Enums\JoinPolicy;
use App\Enums\UserType;
use App\Models\Community;
use App\Models\CommunityFollower;
use App\Models\CommunityMember;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The test the follower/member split exists to satisfy (kolabing-app#138).
 *
 * Two halves, and both matter:
 *
 *  - a member who existed before the split keeps everything they had. This
 *    ships to a database with real communities and real members on it, and the
 *    unacceptable outcome is someone waking up locked out of a community they
 *    belong to.
 *  - a follower gets none of it. The whole reason followers live in their own
 *    table is that no member-gated query can start matching them by accident.
 */
class CommunityMemberAccessRegressionTest extends TestCase
{
    use LazilyRefreshDatabase;

    private Profile $leader;

    private Community $community;

    private Profile $member;

    private Profile $follower;

    protected function setUp(): void
    {
        parent::setUp();

        $this->leader = $this->profile(UserType::Community);

        $this->community = Community::query()->create([
            'owner_profile_id' => $this->leader->id,
            'name' => 'Regression Club',
            'slug' => 'regression-'.uniqid(),
            'type' => 'running',
            'join_policy' => JoinPolicy::Open->value,
        ]);

        $tier = $this->community->tiers()->create([
            'name' => 'Member',
            'rank' => 1,
            'assignment_rule' => 'manual',
            'permissions' => ['view' => [], 'chat_channels' => [], 'perks' => [], 'capabilities' => []],
            'is_default' => true,
        ]);

        // A member of the shape that already exists on production: active,
        // with a tier, created before any of this feature's tables existed.
        $this->member = $this->profile(UserType::Attendee);
        CommunityMember::query()->create([
            'community_id' => $this->community->id,
            'profile_id' => $this->member->id,
            'tier_id' => $tier->id,
            'can_manage' => false,
            'status' => CommunityMemberStatus::Active->value,
            'joined_at' => Carbon::now()->subMonths(3),
        ]);

        // Someone who only follows.
        $this->follower = $this->profile(UserType::Attendee);
        CommunityFollower::query()->create([
            'community_id' => $this->community->id,
            'profile_id' => $this->follower->id,
            'followed_at' => Carbon::now(),
        ]);
    }

    private function profile(UserType $type): Profile
    {
        return Profile::query()->create([
            'email' => 'p-'.uniqid().'@example.test',
            'password' => 'secret1234',
            'user_type' => $type,
        ]);
    }

    // -------------------------------------------------------------------------
    // The existing member keeps everything
    // -------------------------------------------------------------------------

    public function test_an_existing_member_is_still_a_member(): void
    {
        $this->assertTrue(
            $this->community->members()
                ->where('profile_id', $this->member->id)
                ->where('status', CommunityMemberStatus::Active->value)
                ->exists()
        );
    }

    public function test_an_existing_member_still_sees_the_community_in_memberships(): void
    {
        $response = $this->actingAs($this->member)->getJson('/api/v1/me/memberships');

        $response->assertStatus(200);

        $ids = collect($response->json('data'))->pluck('community.id')->all();
        $this->assertContains($this->community->id, $ids);
    }

    /**
     * The membership payload keeps the exact keys it had. An app build already
     * installed reads these; none may disappear or be renamed.
     */
    public function test_the_membership_payload_shape_is_unchanged(): void
    {
        $response = $this->actingAs($this->member)->getJson('/api/v1/me/memberships');

        $response->assertStatus(200);
        $first = $response->json('data.0');

        foreach (['community', 'tier', 'can_manage', 'status', 'joined_at'] as $key) {
            $this->assertArrayHasKey($key, $first, "membership key `$key` disappeared");
        }

        // Still a flat array, not an object — turning it into one to add a
        // sibling section is exactly what this feature avoided doing.
        $this->assertIsList($response->json('data'));
    }

    public function test_an_existing_member_keeps_their_tier(): void
    {
        $response = $this->actingAs($this->member)->getJson('/api/v1/me/memberships');

        $this->assertNotNull($response->json('data.0.tier'));
    }

    public function test_the_leader_still_reads_the_roster_and_sees_the_member(): void
    {
        $response = $this->actingAs($this->leader)
            ->getJson("/api/v1/communities/{$this->community->id}/members");

        $response->assertStatus(200);

        $ids = collect($response->json('data.members'))->pluck('profile_id')->all();
        $this->assertContains($this->member->id, $ids);
    }

    /**
     * The roster is members only. A follower must not turn up in it — this is
     * the query that would have started matching followers if they shared a
     * table with a discriminator column.
     */
    public function test_the_roster_excludes_followers(): void
    {
        $response = $this->actingAs($this->leader)
            ->getJson("/api/v1/communities/{$this->community->id}/members");

        $ids = collect($response->json('data.members'))->pluck('profile_id')->all();
        $this->assertNotContains($this->follower->id, $ids);
    }

    // -------------------------------------------------------------------------
    // The follower gets none of it
    // -------------------------------------------------------------------------

    public function test_a_follower_is_not_a_member(): void
    {
        $this->assertFalse(
            $this->community->members()
                ->where('profile_id', $this->follower->id)
                ->exists()
        );
    }

    public function test_a_follower_sees_no_membership(): void
    {
        $response = $this->actingAs($this->follower)->getJson('/api/v1/me/memberships');

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('community.id')->all();
        $this->assertNotContains($this->community->id, $ids);
    }

    public function test_a_follower_cannot_read_the_roster(): void
    {
        $this->actingAs($this->follower)
            ->getJson("/api/v1/communities/{$this->community->id}/members")
            ->assertStatus(403);
    }

    // -------------------------------------------------------------------------
    // The two axes, as the app will read them
    // -------------------------------------------------------------------------

    public function test_the_community_payload_separates_member_from_follower(): void
    {
        $asMember = $this->actingAs($this->member)
            ->getJson("/api/v1/communities/{$this->community->id}")
            ->json('data');

        $this->assertTrue($asMember['is_member'], 'the member should read as a member');
        $this->assertFalse($asMember['is_following'], 'a member who never followed is not following');

        $asFollower = $this->actingAs($this->follower)
            ->getJson("/api/v1/communities/{$this->community->id}")
            ->json('data');

        $this->assertFalse($asFollower['is_member'], 'a follower is not a member');
        $this->assertTrue($asFollower['is_following']);
        $this->assertSame(1, $asFollower['follower_count']);
    }

    public function test_my_follows_lists_only_what_i_follow(): void
    {
        $response = $this->actingAs($this->follower)->getJson('/api/v1/me/community-follows');

        $response->assertStatus(200);
        $ids = collect($response->json('data'))->pluck('community.id')->all();
        $this->assertSame([$this->community->id], $ids);

        // The member never followed, so their list is empty.
        $this->actingAs($this->member)
            ->getJson('/api/v1/me/community-follows')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }
}
