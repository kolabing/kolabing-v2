<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\ChatThreadType;
use App\Models\ChatThread;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\CommunityTier;
use App\Models\Profile;
use App\Services\CommunityService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CommunityChatTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function makeCommunity(Profile $owner): Community
    {
        return app(CommunityService::class)->create($owner, [
            'name' => 'Barcelona Run Club',
            'type' => 'greek',
        ]);
    }

    private function mainThread(Community $community): ChatThread
    {
        return ChatThread::query()
            ->where('community_id', $community->id)
            ->where('type', ChatThreadType::CommunityMain->value)
            ->firstOrFail();
    }

    public function test_new_community_auto_creates_one_main_chat(): void
    {
        $leader = Profile::factory()->community()->create();
        $community = $this->makeCommunity($leader);

        $mains = ChatThread::query()
            ->where('community_id', $community->id)
            ->where('type', ChatThreadType::CommunityMain->value)
            ->count();

        $this->assertSame(1, $mains);
    }

    public function test_leader_creates_custom_chats_up_to_five_then_capped(): void
    {
        $leader = Profile::factory()->community()->create();
        $community = $this->makeCommunity($leader);

        for ($i = 1; $i <= 5; $i++) {
            $this->actingAs($leader)
                ->postJson("/api/v1/communities/{$community->id}/chats", ['name' => "Chat $i"])
                ->assertStatus(201)
                ->assertJsonPath('data.type', 'community_custom');
        }

        $this->actingAs($leader)
            ->postJson("/api/v1/communities/{$community->id}/chats", ['name' => 'One too many'])
            ->assertStatus(422)
            ->assertJsonPath('error', 'chat_limit_reached');
    }

    public function test_non_manager_cannot_create_custom_chat(): void
    {
        $leader = Profile::factory()->community()->create();
        $community = $this->makeCommunity($leader);
        $outsider = Profile::factory()->community()->create();

        $this->actingAs($outsider)
            ->postJson("/api/v1/communities/{$community->id}/chats", ['name' => 'Nope'])
            ->assertStatus(403);
    }

    public function test_member_sees_custom_chat_by_default_and_when_restricted(): void
    {
        $leader = Profile::factory()->community()->create();
        $community = $this->makeCommunity($leader);

        // Leader creates the "Socials" custom chat → slug "socials".
        $this->actingAs($leader)
            ->postJson("/api/v1/communities/{$community->id}/chats", ['name' => 'Socials'])
            ->assertStatus(201)
            ->assertJsonPath('data.name', 'Socials')
            // The slug is the gating key the app needs for the per-tier picker.
            ->assertJsonPath('data.slug', 'socials');

        $grantedTier = CommunityTier::factory()->forCommunity($community)->create([
            'rank' => 3,
            'permissions' => ['view' => [], 'chat_channels' => ['socials'], 'perks' => [], 'capabilities' => []],
        ]);

        $granted = Profile::factory()->attendee()->create();
        CommunityMember::factory()->forCommunity($community)->create([
            'profile_id' => $granted->id,
            'tier_id' => $grantedTier->id,
            'status' => 'active',
        ]);

        $plain = Profile::factory()->attendee()->create();
        CommunityMember::factory()->forCommunity($community)->create([
            'profile_id' => $plain->id,
            'tier_id' => $community->defaultTier->id, // chat_channels: []
            'status' => 'active',
        ]);

        // Granted member: main + socials.
        $this->actingAs($granted)->getJson('/api/v1/chats')
            ->assertStatus(200)
            ->assertJsonCount(2, 'data');

        // Default-OPEN (Daniel 2026-06-10): creating "Socials" granted its slug to
        // EVERY tier (grantChatToAllTiers), incl. the default tier — so even a
        // plain member sees main + socials without an explicit grant.
        $this->actingAs($plain)->getJson('/api/v1/chats')
            ->assertStatus(200)
            ->assertJsonCount(2, 'data');

        // Restriction still works: the leader removes 'socials' from the default
        // tier → the plain member falls back to main only.
        CommunityTier::query()
            ->where('community_id', $community->id)
            ->where('is_default', true)
            ->firstOrFail()
            ->update(['permissions' => ['view' => [], 'chat_channels' => [], 'perks' => [], 'capabilities' => []]]);

        $this->actingAs($plain)->getJson('/api/v1/chats')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'community_main');
    }

    public function test_send_list_and_read_main_thread_messages(): void
    {
        $leader = Profile::factory()->community()->create();
        $community = $this->makeCommunity($leader);
        $main = $this->mainThread($community);

        $member = Profile::factory()->attendee()->create();
        CommunityMember::factory()->forCommunity($community)->create([
            'profile_id' => $member->id,
            'tier_id' => $community->defaultTier->id,
            'status' => 'active',
        ]);

        // Leader posts into the main chat.
        $this->actingAs($leader)
            ->postJson("/api/v1/chats/{$main->id}/messages", ['content' => 'Welcome all'])
            ->assertStatus(201)
            ->assertJsonPath('data.thread_id', $main->id)
            ->assertJsonPath('data.content', 'Welcome all');

        $this->assertNotNull($main->fresh()->last_message_at);

        // Before opening, the member's inbox shows 1 unread on the main thread.
        $beforeRead = $this->actingAs($member)->getJson('/api/v1/chats')->json('data.0.unread_count');
        $this->assertSame(1, $beforeRead);

        // Listing messages marks the thread read on open. The list MUST live at
        // data.messages (the app's generic chat client reads that key) — a
        // regression here showed the message on send but an empty thread on reopen.
        $this->actingAs($member)
            ->getJson("/api/v1/chats/{$main->id}/messages")
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonCount(1, 'data.messages')
            ->assertJsonPath('data.messages.0.content', 'Welcome all')
            ->assertJsonPath('data.messages.0.thread_id', $main->id);

        $afterRead = $this->actingAs($member)->getJson('/api/v1/chats')->json('data.0.unread_count');
        $this->assertSame(0, $afterRead);
    }

    public function test_outsider_cannot_access_community_thread(): void
    {
        $leader = Profile::factory()->community()->create();
        $community = $this->makeCommunity($leader);
        $main = $this->mainThread($community);
        $outsider = Profile::factory()->attendee()->create();

        $this->actingAs($outsider)
            ->getJson("/api/v1/chats/{$main->id}/messages")
            ->assertStatus(403);

        $this->actingAs($outsider)
            ->postJson("/api/v1/chats/{$main->id}/messages", ['content' => 'hi'])
            ->assertStatus(403);
    }
}
