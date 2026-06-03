<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\CommunityTier;
use App\Models\Profile;
use App\Services\CommunityService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CommunityEndpointsTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function makeCommunity(Profile $owner, array $data = []): Community
    {
        return app(CommunityService::class)->create($owner, array_merge([
            'name' => 'Kappa Delta',
            'type' => 'greek',
        ], $data));
    }

    public function test_leader_creates_one_community_with_default_tier_then_blocked(): void
    {
        $leader = Profile::factory()->community()->create();

        $response = $this->actingAs($leader)->postJson('/api/v1/communities', [
            'name' => 'Kappa Delta — Beta Chi',
            'type' => 'greek',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Kappa Delta — Beta Chi')
            ->assertJsonPath('data.type', 'greek')
            ->assertJsonPath('data.is_primary', true)
            ->assertJsonPath('data.join_policy', 'open')
            ->assertJsonPath('data.member_count', 0);

        $communityId = $response->json('data.id');
        $this->assertSame(1, CommunityTier::query()->where('community_id', $communityId)->where('is_default', true)->count());

        // Second community is gated by the NEW community cap, not the paywall.
        $second = $this->actingAs($leader)->postJson('/api/v1/communities', [
            'name' => 'Second',
            'type' => 'other',
        ]);
        $second->assertStatus(422)->assertJsonPath('error', 'community_limit_reached');
    }

    public function test_me_communities_lists_owned(): void
    {
        $leader = Profile::factory()->community()->create();
        $this->makeCommunity($leader);

        $this->actingAs($leader)->getJson('/api/v1/me/communities')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_tier_crud_over_http(): void
    {
        $leader = Profile::factory()->community()->create();
        $community = $this->makeCommunity($leader);

        $create = $this->actingAs($leader)->postJson("/api/v1/communities/{$community->id}/tiers", [
            'name' => 'Exec',
            'rank' => 3,
            'color' => '#FFD861',
            'assignment_rule' => 'xp_threshold',
            'threshold' => 500,
        ]);
        $create->assertStatus(201)
            ->assertJsonPath('data.assignment_rule', 'xp_threshold')
            ->assertJsonPath('data.threshold', 500)
            ->assertJsonPath('data.permissions.view', []);

        $tierId = $create->json('data.id');

        $this->actingAs($leader)->patchJson("/api/v1/tiers/{$tierId}", ['name' => 'Executive'])
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'Executive');

        // Default tier cannot be deleted.
        $defaultId = $community->defaultTier->id;
        $this->actingAs($leader)->deleteJson("/api/v1/tiers/{$defaultId}")
            ->assertStatus(422)
            ->assertJsonPath('error', 'cannot_delete_default_tier');

        $this->actingAs($leader)->deleteJson("/api/v1/tiers/{$tierId}")->assertStatus(200);
    }

    public function test_non_manager_cannot_create_tier(): void
    {
        $leader = Profile::factory()->community()->create();
        $community = $this->makeCommunity($leader);
        $outsider = Profile::factory()->attendee()->create();

        $this->actingAs($outsider)->postJson("/api/v1/communities/{$community->id}/tiers", [
            'name' => 'X', 'rank' => 2, 'assignment_rule' => 'manual',
        ])->assertStatus(403);
    }

    public function test_open_community_self_join_and_roster(): void
    {
        $leader = Profile::factory()->community()->create();
        $community = $this->makeCommunity($leader);
        $member = Profile::factory()->attendee()->create();

        $join = $this->actingAs($member)->postJson("/api/v1/communities/{$community->id}/join");
        $join->assertStatus(201)
            ->assertJsonPath('data.profile_id', $member->id)
            ->assertJsonPath('data.tier.is_default', true);

        // Roster renders nested tier + profile.
        $roster = $this->actingAs($leader)->getJson("/api/v1/communities/{$community->id}/members");
        $roster->assertStatus(200)
            ->assertJsonPath('data.members.0.profile_id', $member->id)
            ->assertJsonPath('data.members.0.tier.is_default', true)
            ->assertJsonStructure(['data' => ['members' => [['profile' => ['name', 'avatar_url']]]]]);
    }

    public function test_invite_only_blocks_self_join_but_leader_can_add(): void
    {
        $leader = Profile::factory()->community()->create();
        $community = $this->makeCommunity($leader, ['join_policy' => 'invite_only']);
        $member = Profile::factory()->attendee()->create();

        $this->actingAs($member)->postJson("/api/v1/communities/{$community->id}/join")
            ->assertStatus(403)
            ->assertJsonPath('error', 'invite_only');

        $this->actingAs($leader)->postJson("/api/v1/communities/{$community->id}/members", [
            'profile_id' => $member->id,
        ])->assertStatus(201)->assertJsonPath('data.profile_id', $member->id);
    }

    public function test_leader_sets_member_tier_and_can_manage(): void
    {
        $leader = Profile::factory()->community()->create();
        $community = $this->makeCommunity($leader);
        $exec = CommunityTier::factory()->forCommunity($community)->create(['rank' => 3]);
        $memberProfile = Profile::factory()->attendee()->create();
        $member = CommunityMember::factory()->forCommunity($community)->create([
            'profile_id' => $memberProfile->id,
            'tier_id' => $community->defaultTier->id,
        ]);

        $this->actingAs($leader)->patchJson(
            "/api/v1/communities/{$community->id}/members/{$member->id}",
            ['tier_id' => $exec->id, 'can_manage' => true]
        )->assertStatus(200)
            ->assertJsonPath('data.tier_id', $exec->id)
            ->assertJsonPath('data.can_manage', true);
    }

    public function test_member_cannot_be_assigned_a_tier_from_another_community(): void
    {
        $leader = Profile::factory()->community()->create();
        $community = $this->makeCommunity($leader);
        $member = CommunityMember::factory()->forCommunity($community)->create();
        $foreignTier = CommunityTier::factory()->create(['rank' => 2]); // different community

        $this->actingAs($leader)->patchJson(
            "/api/v1/communities/{$community->id}/members/{$member->id}",
            ['tier_id' => $foreignTier->id]
        )->assertStatus(422)->assertJsonPath('error', 'tier_not_in_community');
    }

    public function test_me_memberships_returns_community_and_tier(): void
    {
        $leader = Profile::factory()->community()->create();
        $community = $this->makeCommunity($leader);
        $member = Profile::factory()->attendee()->create();
        $this->actingAs($member)->postJson("/api/v1/communities/{$community->id}/join")->assertStatus(201);

        $this->actingAs($member)->getJson('/api/v1/me/memberships')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.community.id', $community->id)
            ->assertJsonPath('data.0.tier.is_default', true);
    }
}
