<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\CommunityMemberStatus;
use App\Models\Community;
use App\Models\CommunityTier;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CommunityInviteTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function communityWithDefaultTier(Profile $owner, bool $inviteOnly = false): Community
    {
        $community = Community::factory()->forOwner($owner)->state([
            'join_policy' => $inviteOnly ? 'invite_only' : 'open',
        ])->create();

        CommunityTier::factory()->forCommunity($community)->create(['is_default' => true]);

        return $community;
    }

    public function test_invite_url_is_exposed_on_the_community_resource(): void
    {
        $owner = Profile::factory()->community()->create();
        $community = $this->communityWithDefaultTier($owner);

        $this->actingAs($owner)
            ->getJson("/api/v1/communities/{$community->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.invite_url', 'https://kolabing.com/c/'.$community->slug);
    }

    public function test_owner_gets_invite_link_for_open_community_without_token(): void
    {
        $owner = Profile::factory()->community()->create();
        $community = $this->communityWithDefaultTier($owner);

        $this->actingAs($owner)
            ->getJson("/api/v1/communities/{$community->id}/invite")
            ->assertStatus(200)
            ->assertJsonPath('data.invite_url', 'https://kolabing.com/c/'.$community->slug)
            ->assertJsonPath('data.token', null)
            ->assertJsonPath('data.token_url', null);
    }

    public function test_owner_gets_token_for_invite_only_community(): void
    {
        $owner = Profile::factory()->community()->create();
        $community = $this->communityWithDefaultTier($owner, inviteOnly: true);

        $response = $this->actingAs($owner)
            ->getJson("/api/v1/communities/{$community->id}/invite")
            ->assertStatus(200);

        $token = $response->json('data.token');
        $this->assertIsString($token);
        $this->assertNotEmpty($token);
        $response->assertJsonPath('data.token_url', 'https://kolabing.com/c/'.$community->slug.'?invite='.$token);

        // Token is persisted and idempotent across calls.
        $this->assertDatabaseHas('communities', ['id' => $community->id, 'invite_token' => $token]);
        $this->actingAs($owner)
            ->getJson("/api/v1/communities/{$community->id}/invite")
            ->assertJsonPath('data.token', $token);
    }

    public function test_non_manager_cannot_fetch_invite(): void
    {
        $owner = Profile::factory()->community()->create();
        $community = $this->communityWithDefaultTier($owner);
        $outsider = Profile::factory()->community()->create();

        $this->actingAs($outsider)
            ->getJson("/api/v1/communities/{$community->id}/invite")
            ->assertStatus(403);
    }

    public function test_token_join_bypasses_invite_only_gate(): void
    {
        $owner = Profile::factory()->community()->create();
        $community = $this->communityWithDefaultTier($owner, inviteOnly: true);
        $token = $community->ensureInviteToken();

        $joiner = Profile::factory()->community()->create();

        $this->actingAs($joiner)
            ->postJson("/api/v1/communities/join/{$token}")
            ->assertStatus(201);

        $this->assertDatabaseHas('community_members', [
            'community_id' => $community->id,
            'profile_id' => $joiner->id,
            'status' => CommunityMemberStatus::Active->value,
        ]);
    }

    public function test_token_join_with_invalid_token_returns_404(): void
    {
        $joiner = Profile::factory()->community()->create();

        $this->actingAs($joiner)
            ->postJson('/api/v1/communities/join/not-a-real-token')
            ->assertStatus(404)
            ->assertJsonPath('error', 'invalid_invite');
    }
}
