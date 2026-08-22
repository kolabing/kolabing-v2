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

    /**
     * The two relationships are independent axes: a member who never tapped
     * follow is not a follower, and being one does not imply the other.
     */
    public function test_membership_and_following_are_independent(): void
    {
        $community = $this->community();
        $member = $this->person();

        $community->members()->create([
            'profile_id' => $member->id,
            'can_manage' => false,
            'status' => CommunityMemberStatus::Active->value,
            'joined_at' => Carbon::now(),
        ]);

        $this->assertFalse(CommunityFollower::query()
            ->where('community_id', $community->id)
            ->where('profile_id', $member->id)
            ->exists());

        // A member may still follow; it changes nothing about their membership.
        $this->actingAs($member)
            ->postJson("/api/v1/communities/{$community->id}/follow")
            ->assertStatus(201);

        $this->assertTrue($community->members()
            ->where('profile_id', $member->id)
            ->where('status', CommunityMemberStatus::Active->value)
            ->exists());
    }
}
