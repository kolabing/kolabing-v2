<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\CommunityMemberStatus;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\CommunityTier;
use App\Models\Profile;
use App\Services\CommunityService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CommunityEndpointsTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function makeCommunity(Profile $owner, array $data = []): Community
    {
        return app(CommunityService::class)->create($owner, array_merge([
            'name' => 'Kappa Delta',
            'type' => 'student_community',
        ], $data));
    }

    public function test_leader_creates_one_community_with_default_tier_then_blocked(): void
    {
        $leader = Profile::factory()->community()->create();

        $response = $this->actingAs($leader)->postJson('/api/v1/communities', [
            'name' => 'Kappa Delta — Beta Chi',
            'type' => 'student_community',
        ]);

        // `data.type` is inherited from the owner's community_profile.community_type
        // (a real 17-slug) when present, so it isn't pinned to the posted value.
        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Kappa Delta — Beta Chi')
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
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.my_can_manage', true);
    }

    /*
     |-------------------------------------------------------------------------
     | BE-FX-15 — managed-but-not-owned communities must be listable
     |-------------------------------------------------------------------------
     */

    private function addMember(Community $community, Profile $profile, bool $canManage, ?CommunityMemberStatus $status = null): CommunityMember
    {
        $factory = CommunityMember::factory()->forCommunity($community);

        if ($canManage) {
            $factory = $factory->manager();
        }

        return $factory->create([
            'profile_id' => $profile->id,
            'tier_id' => $community->defaultTier->id,
            'status' => ($status ?? CommunityMemberStatus::Active)->value,
        ]);
    }

    public function test_me_communities_includes_a_community_the_viewer_manages_but_does_not_own(): void
    {
        $owner = Profile::factory()->community()->create();
        $community = $this->makeCommunity($owner);

        $manager = Profile::factory()->attendee()->create();
        $this->addMember($community, $manager, canManage: true);

        $this->actingAs($manager)->getJson('/api/v1/me/communities')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $community->id)
            ->assertJsonPath('data.0.owner_profile_id', $owner->id)
            ->assertJsonPath('data.0.my_can_manage', true);
    }

    public function test_me_communities_excludes_a_plain_member_and_an_inactive_manager(): void
    {
        $owner = Profile::factory()->community()->create();
        $community = $this->makeCommunity($owner);

        $plainMember = Profile::factory()->attendee()->create();
        $this->addMember($community, $plainMember, canManage: false);

        $removedManager = Profile::factory()->attendee()->create();
        $this->addMember($community, $removedManager, canManage: true, status: CommunityMemberStatus::Removed);

        $this->actingAs($plainMember)->getJson('/api/v1/me/communities')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');

        $this->actingAs($removedManager)->getJson('/api/v1/me/communities')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    public function test_me_communities_lists_owned_and_managed_together_without_duplicates(): void
    {
        $viewer = Profile::factory()->community()->create();
        $own = $this->makeCommunity($viewer, ['name' => 'My own']);

        $otherOwner = Profile::factory()->community()->create();
        $managed = $this->makeCommunity($otherOwner, ['name' => 'Co-run']);
        $this->addMember($managed, $viewer, canManage: true);

        // A membership row on a community the viewer ALSO owns must not duplicate it.
        $this->addMember($own, Profile::factory()->attendee()->create(), canManage: true);

        $response = $this->actingAs($viewer)->getJson('/api/v1/me/communities')
            ->assertStatus(200)
            ->assertJsonCount(2, 'data');

        $ids = collect($response->json('data'))->pluck('id')->sort()->values()->all();
        $this->assertSame(collect([$own->id, $managed->id])->sort()->values()->all(), $ids);

        foreach ($response->json('data') as $row) {
            $this->assertTrue($row['my_can_manage'], 'Every row in /me/communities is manageable by the viewer.');
        }
    }

    public function test_me_communities_does_not_run_a_query_per_community(): void
    {
        $viewer = Profile::factory()->community()->create();
        $own = $this->makeCommunity($viewer, ['name' => 'Own']);
        $this->addMember($own, Profile::factory()->attendee()->create(), canManage: false);

        foreach (['Alpha', 'Beta', 'Gamma'] as $name) {
            $otherOwner = Profile::factory()->community()->create();
            $managed = $this->makeCommunity($otherOwner, ['name' => $name]);
            $this->addMember($managed, $viewer, canManage: true);
        }

        $canManageQueries = 0;
        DB::listen(function (QueryExecuted $event) use (&$canManageQueries): void {
            if (str_contains($event->sql, 'can_manage')) {
                $canManageQueries++;
            }
        });

        $this->actingAs($viewer)->getJson('/api/v1/me/communities')
            ->assertStatus(200)
            ->assertJsonCount(4, 'data');

        // The can_manage set must be read in bulk, not once per listed community —
        // a per-row lookup would make this 4 (and grow with the list).
        $this->assertLessThanOrEqual(
            1,
            $canManageQueries,
            "GET /me/communities ran {$canManageQueries} can_manage queries for 4 communities — that is an N+1."
        );
    }

    public function test_my_can_manage_separates_owner_manager_and_plain_member(): void
    {
        $owner = Profile::factory()->community()->create();
        $community = $this->makeCommunity($owner);

        $manager = Profile::factory()->attendee()->create();
        $this->addMember($community, $manager, canManage: true);

        $plainMember = Profile::factory()->attendee()->create();
        $this->addMember($community, $plainMember, canManage: false);

        $outsider = Profile::factory()->attendee()->create();

        // GET /communities/{id} uses the resource's per-viewer lazy path.
        $this->actingAs($owner)->getJson("/api/v1/communities/{$community->id}")
            ->assertStatus(200)->assertJsonPath('data.my_can_manage', true);
        $this->actingAs($manager)->getJson("/api/v1/communities/{$community->id}")
            ->assertStatus(200)->assertJsonPath('data.my_can_manage', true);
        $this->actingAs($plainMember)->getJson("/api/v1/communities/{$community->id}")
            ->assertStatus(200)->assertJsonPath('data.my_can_manage', false);
        $this->actingAs($outsider)->getJson("/api/v1/communities/{$community->id}")
            ->assertStatus(200)->assertJsonPath('data.my_can_manage', false);

        // GET /me/memberships uses the bulk-hydrated path — same answer.
        $this->actingAs($manager)->getJson('/api/v1/me/memberships')
            ->assertStatus(200)
            ->assertJsonPath('data.0.community.my_can_manage', true);
        $this->actingAs($plainMember)->getJson('/api/v1/me/memberships')
            ->assertStatus(200)
            ->assertJsonPath('data.0.community.my_can_manage', false);
    }

    public function test_my_can_manage_is_additive_and_leaves_the_existing_keys_untouched(): void
    {
        $owner = Profile::factory()->community()->create();
        $community = $this->makeCommunity($owner);

        $response = $this->actingAs($owner)->getJson('/api/v1/me/communities')
            ->assertStatus(200);

        $row = $response->json('data.0');

        $this->assertArrayHasKey('my_can_manage', $row);
        $this->assertSame($community->id, $row['id']);
        $this->assertSame($owner->id, $row['owner_profile_id']);
        $this->assertSame($community->name, $row['name']);
        $this->assertSame('open', $row['join_policy']);
        $this->assertSame(0, $row['member_count']);
        $this->assertTrue($row['is_member']);
        $this->assertNull($row['my_join_request_status']);
        $this->assertSame(0, $row['my_points']);
        $this->assertNull($row['my_tier']);
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

    public function test_leader_can_invite_member_by_email(): void
    {
        $leader = Profile::factory()->community()->create();
        $community = $this->makeCommunity($leader, ['join_policy' => 'invite_only']);
        $invitee = Profile::factory()->attendee()->create(['email' => 'invitee@example.com']);

        // Email is normalised (case/whitespace) before lookup.
        $this->actingAs($leader)->postJson("/api/v1/communities/{$community->id}/members", [
            'email' => '  Invitee@Example.com ',
        ])->assertStatus(201)
            ->assertJsonPath('data.profile_id', $invitee->id);

        $this->assertSame(1, $community->members()->where('profile_id', $invitee->id)->count());
    }

    public function test_invite_by_unknown_email_returns_profile_not_found(): void
    {
        $leader = Profile::factory()->community()->create();
        $community = $this->makeCommunity($leader);

        $this->actingAs($leader)->postJson("/api/v1/communities/{$community->id}/members", [
            'email' => 'nobody@example.com',
        ])->assertStatus(404)
            ->assertJsonPath('error', 'profile_not_found');
    }

    public function test_invite_by_email_with_initial_tier(): void
    {
        $leader = Profile::factory()->community()->create();
        $community = $this->makeCommunity($leader);
        $tier = CommunityTier::factory()->forCommunity($community)->create(['rank' => 3]);
        $invitee = Profile::factory()->attendee()->create(['email' => 'tiered@example.com']);

        $this->actingAs($leader)->postJson("/api/v1/communities/{$community->id}/members", [
            'email' => 'tiered@example.com',
            'tier_id' => $tier->id,
        ])->assertStatus(201)
            ->assertJsonPath('data.profile_id', $invitee->id)
            ->assertJsonPath('data.tier_id', $tier->id);
    }

    public function test_invite_by_email_with_foreign_tier_is_rejected(): void
    {
        $leader = Profile::factory()->community()->create();
        $community = $this->makeCommunity($leader);
        $foreignTier = CommunityTier::factory()->create(['rank' => 2]); // different community
        Profile::factory()->attendee()->create(['email' => 'tiered@example.com']);

        $this->actingAs($leader)->postJson("/api/v1/communities/{$community->id}/members", [
            'email' => 'tiered@example.com',
            'tier_id' => $foreignTier->id,
        ])->assertStatus(422)
            ->assertJsonPath('error', 'tier_not_in_community');
    }

    public function test_invite_requires_email_or_profile_id(): void
    {
        $leader = Profile::factory()->community()->create();
        $community = $this->makeCommunity($leader);

        $this->actingAs($leader)->postJson("/api/v1/communities/{$community->id}/members", [])
            ->assertStatus(422);
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
