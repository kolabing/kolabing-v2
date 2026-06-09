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

/**
 * Per-member chat block overrides tier access (NF-16 chat follow-up).
 */
class ChatBanTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function makeChat(): array
    {
        $leader = Profile::factory()->community()->create();
        $community = app(CommunityService::class)->create($leader, ['name' => 'Run Club', 'type' => 'greek']);

        // Custom chat "Socials" → slug "socials".
        $chat = $this->actingAs($leader)
            ->postJson("/api/v1/communities/{$community->id}/chats", ['name' => 'Socials'])
            ->json('data');

        // A tier that grants the chat + a member on it.
        $tier = CommunityTier::factory()->forCommunity($community)->create([
            'rank' => 3,
            'permissions' => ['view' => [], 'chat_channels' => ['socials'], 'perks' => [], 'capabilities' => []],
        ]);
        $member = Profile::factory()->attendee()->create();
        CommunityMember::factory()->forCommunity($community)->create([
            'profile_id' => $member->id, 'tier_id' => $tier->id, 'status' => 'active',
        ]);

        return [$leader, $community, $chat, $member];
    }

    public function test_block_overrides_tier_then_unblock_restores(): void
    {
        [$leader, , $chat, $member] = $this->makeChat();

        // Granted member sees the chat (main + socials = 2) and can post.
        $this->actingAs($member)->getJson('/api/v1/chats')
            ->assertStatus(200)->assertJsonCount(2, 'data');
        $this->actingAs($member)->postJson("/api/v1/chats/{$chat['id']}/messages", ['content' => 'hi'])
            ->assertStatus(201);

        // Leader blocks the member.
        $this->actingAs($leader)
            ->postJson("/api/v1/chats/{$chat['id']}/bans", ['profile_id' => $member->id])
            ->assertStatus(201)
            ->assertJsonPath('data.banned_profile_ids', [$member->id]);

        // Now the chat is gone from their inbox and posting is forbidden.
        $this->actingAs($member)->getJson('/api/v1/chats')
            ->assertStatus(200)->assertJsonCount(1, 'data'); // main only
        $this->actingAs($member)->postJson("/api/v1/chats/{$chat['id']}/messages", ['content' => 'again'])
            ->assertStatus(403);

        // Unblock → access restored.
        $this->actingAs($leader)
            ->deleteJson("/api/v1/chats/{$chat['id']}/bans/{$member->id}")
            ->assertStatus(200);
        $this->actingAs($member)->getJson('/api/v1/chats')
            ->assertStatus(200)->assertJsonCount(2, 'data');
    }

    public function test_cannot_block_a_manager_and_non_manager_cannot_block(): void
    {
        [$leader, $community, $chat, $member] = $this->makeChat();

        // A manager member can't be blocked (no-op).
        $manager = Profile::factory()->attendee()->create();
        CommunityMember::factory()->forCommunity($community)->create([
            'profile_id' => $manager->id, 'tier_id' => $community->defaultTier->id,
            'status' => 'active', 'can_manage' => true,
        ]);
        $this->actingAs($leader)
            ->postJson("/api/v1/chats/{$chat['id']}/bans", ['profile_id' => $manager->id])
            ->assertStatus(201)
            ->assertJsonPath('data.banned_profile_ids', []); // not added

        // A non-manager can't block anyone.
        $this->actingAs($member)
            ->postJson("/api/v1/chats/{$chat['id']}/bans", ['profile_id' => $manager->id])
            ->assertStatus(403);
    }
}
