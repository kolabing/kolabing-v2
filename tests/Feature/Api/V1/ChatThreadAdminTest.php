<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\ChatThreadType;
use App\Models\ChatThread;
use App\Models\ChatThreadParticipant;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Event;
use App\Models\Profile;
use App\Services\ChatService;
use App\Services\CommunityService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ChatThreadAdminTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function community(Profile $leader): Community
    {
        return app(CommunityService::class)->create($leader, [
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

    private function customThread(Profile $leader, Community $community, bool $open = false): ChatThread
    {
        $thread = app(ChatService::class)->createCustomChat($community, $leader, 'Socials');

        if ($open) {
            $thread->forceFill(['is_open' => true])->save();
        }

        return $thread->fresh();
    }

    private function activeMember(Community $community): Profile
    {
        $profile = Profile::factory()->attendee()->create();
        CommunityMember::factory()->forCommunity($community)->create([
            'profile_id' => $profile->id,
            'tier_id' => $community->defaultTier->id,
            'status' => 'active',
        ]);

        return $profile;
    }

    private function eventThread(Profile $leader, Community $community): ChatThread
    {
        $event = Event::factory()->create([
            'profile_id' => $community->owner_profile_id,
            'community_id' => $community->id,
            'starts_at' => now()->addDays(2),
            'ends_at' => now()->addDays(2)->addHours(3),
        ]);

        return app(ChatService::class)->eventThreadFor($event, $leader);
    }

    // --- Delete scope -------------------------------------------------------

    public function test_leader_can_delete_custom_chat(): void
    {
        $leader = Profile::factory()->community()->create();
        $community = $this->community($leader);
        $thread = $this->customThread($leader, $community);

        $this->actingAs($leader)
            ->deleteJson("/api/v1/chats/{$thread->id}")
            ->assertStatus(200);

        $this->assertSoftDeleted('chat_threads', ['id' => $thread->id]);
    }

    public function test_leader_can_delete_event_chat(): void
    {
        $leader = Profile::factory()->community()->create();
        $community = $this->community($leader);
        $thread = $this->eventThread($leader, $community);

        $this->actingAs($leader)
            ->deleteJson("/api/v1/chats/{$thread->id}")
            ->assertStatus(200);

        $this->assertSoftDeleted('chat_threads', ['id' => $thread->id]);
    }

    public function test_main_chat_cannot_be_deleted(): void
    {
        $leader = Profile::factory()->community()->create();
        $community = $this->community($leader);
        $main = $this->mainThread($community);

        $this->actingAs($leader)
            ->deleteJson("/api/v1/chats/{$main->id}")
            ->assertStatus(422)
            ->assertJsonPath('error', 'cannot_delete_thread_type');

        $this->assertDatabaseHas('chat_threads', ['id' => $main->id, 'deleted_at' => null]);
    }

    public function test_collaboration_chat_cannot_be_deleted(): void
    {
        $leader = Profile::factory()->community()->create();
        $community = $this->community($leader);
        // A bare collaboration thread (no community) — must 422 on delete.
        $collab = ChatThread::query()->create(['type' => ChatThreadType::Collaboration->value]);

        // canManageThread requires a community; a collaboration thread has none,
        // so the manage check fails first (403) — it is never deletable either way.
        $this->actingAs($leader)
            ->deleteJson("/api/v1/chats/{$collab->id}")
            ->assertStatus(403);

        $this->assertDatabaseHas('chat_threads', ['id' => $collab->id, 'deleted_at' => null]);
    }

    public function test_non_manager_cannot_delete_custom_chat(): void
    {
        $leader = Profile::factory()->community()->create();
        $community = $this->community($leader);
        $thread = $this->customThread($leader, $community);
        $member = $this->activeMember($community);

        $this->actingAs($member)
            ->deleteJson("/api/v1/chats/{$thread->id}")
            ->assertStatus(403);
    }

    // --- Rename authz -------------------------------------------------------

    public function test_leader_can_rename_custom_chat(): void
    {
        $leader = Profile::factory()->community()->create();
        $community = $this->community($leader);
        $thread = $this->customThread($leader, $community);

        $this->actingAs($leader)
            ->patchJson("/api/v1/chats/{$thread->id}", ['name' => 'Renamed'])
            ->assertStatus(200)
            ->assertJsonPath('data.name', 'Renamed');

        $this->assertSame('Renamed', $thread->fresh()->name);
    }

    public function test_non_manager_cannot_rename_custom_chat(): void
    {
        $leader = Profile::factory()->community()->create();
        $community = $this->community($leader);
        $thread = $this->customThread($leader, $community);
        $member = $this->activeMember($community);

        $this->actingAs($member)
            ->patchJson("/api/v1/chats/{$thread->id}", ['name' => 'Nope'])
            ->assertStatus(403);
    }

    public function test_main_chat_cannot_be_renamed(): void
    {
        $leader = Profile::factory()->community()->create();
        $community = $this->community($leader);
        $main = $this->mainThread($community);

        $this->actingAs($leader)
            ->patchJson("/api/v1/chats/{$main->id}", ['name' => 'Nope'])
            ->assertStatus(422)
            ->assertJsonPath('error', 'cannot_rename_thread_type');
    }

    // --- Join eligibility ---------------------------------------------------

    public function test_active_member_joins_open_custom_chat(): void
    {
        $leader = Profile::factory()->community()->create();
        $community = $this->community($leader);
        $thread = $this->customThread($leader, $community, open: true);
        $member = $this->activeMember($community);

        $this->actingAs($member)
            ->postJson("/api/v1/chats/{$thread->id}/join")
            ->assertStatus(200);

        $this->assertDatabaseHas('chat_thread_participants', [
            'thread_id' => $thread->id,
            'profile_id' => $member->id,
            'state' => 'joined',
        ]);

        // Joining grants access to the thread messages.
        $this->actingAs($member)
            ->getJson("/api/v1/chats/{$thread->id}/messages")
            ->assertStatus(200);
    }

    public function test_non_member_cannot_join_open_custom_chat(): void
    {
        $leader = Profile::factory()->community()->create();
        $community = $this->community($leader);
        $thread = $this->customThread($leader, $community, open: true);
        $outsider = Profile::factory()->attendee()->create();

        $this->actingAs($outsider)
            ->postJson("/api/v1/chats/{$thread->id}/join")
            ->assertStatus(422)
            ->assertJsonPath('error', 'not_eligible');
    }

    public function test_member_cannot_join_closed_custom_chat(): void
    {
        $leader = Profile::factory()->community()->create();
        $community = $this->community($leader);
        $thread = $this->customThread($leader, $community, open: false);
        $member = $this->activeMember($community);

        $this->actingAs($member)
            ->postJson("/api/v1/chats/{$thread->id}/join")
            ->assertStatus(422)
            ->assertJsonPath('error', 'not_eligible');
    }

    public function test_banned_member_cannot_join_returns_403(): void
    {
        $leader = Profile::factory()->community()->create();
        $community = $this->community($leader);
        $thread = $this->customThread($leader, $community, open: true);
        $member = $this->activeMember($community);

        ChatThreadParticipant::factory()
            ->forThread($thread)
            ->forProfile($member)
            ->banned($leader)
            ->create();

        $this->actingAs($member)
            ->postJson("/api/v1/chats/{$thread->id}/join")
            ->assertStatus(403)
            ->assertJsonPath('error', 'banned');
    }

    // --- Ban removes access + blocks re-join --------------------------------

    public function test_ban_removes_access_and_blocks_rejoin(): void
    {
        $leader = Profile::factory()->community()->create();
        $community = $this->community($leader);
        $thread = $this->customThread($leader, $community, open: true);
        $member = $this->activeMember($community);

        // Join, confirm access.
        $this->actingAs($member)->postJson("/api/v1/chats/{$thread->id}/join")->assertStatus(200);
        $this->actingAs($member)->getJson("/api/v1/chats/{$thread->id}/messages")->assertStatus(200);

        // Leader bans the member.
        $this->actingAs($leader)
            ->postJson("/api/v1/chats/{$thread->id}/members/{$member->id}/remove")
            ->assertStatus(200);

        $this->assertDatabaseHas('chat_thread_participants', [
            'thread_id' => $thread->id,
            'profile_id' => $member->id,
            'state' => 'banned',
            'banned_by' => $leader->id,
        ]);

        // Access revoked.
        $this->actingAs($member)->getJson("/api/v1/chats/{$thread->id}/messages")->assertStatus(403);

        // Re-join blocked.
        $this->actingAs($member)
            ->postJson("/api/v1/chats/{$thread->id}/join")
            ->assertStatus(403)
            ->assertJsonPath('error', 'banned');

        // Banned thread no longer in the member's inbox.
        $threads = $this->actingAs($member)->getJson('/api/v1/chats')->assertStatus(200)->json('data');
        $this->assertNull(collect($threads)->firstWhere('id', $thread->id));
    }

    public function test_non_manager_cannot_ban(): void
    {
        $leader = Profile::factory()->community()->create();
        $community = $this->community($leader);
        $thread = $this->customThread($leader, $community, open: true);
        $member = $this->activeMember($community);
        $other = $this->activeMember($community);

        $this->actingAs($member)
            ->postJson("/api/v1/chats/{$thread->id}/members/{$other->id}/remove")
            ->assertStatus(403);
    }

    // --- Soft-deleted threads excluded everywhere ---------------------------

    public function test_soft_deleted_thread_excluded_from_list_and_messages(): void
    {
        $leader = Profile::factory()->community()->create();
        $community = $this->community($leader);
        $thread = $this->customThread($leader, $community, open: true);
        $member = $this->activeMember($community);
        $this->actingAs($member)->postJson("/api/v1/chats/{$thread->id}/join")->assertStatus(200);

        // Delete it.
        $this->actingAs($leader)->deleteJson("/api/v1/chats/{$thread->id}")->assertStatus(200);

        // Route model binding excludes soft-deleted → 404 on messages.
        $this->actingAs($member)->getJson("/api/v1/chats/{$thread->id}/messages")->assertStatus(404);

        // Not present in the inbox list.
        $threads = $this->actingAs($member)->getJson('/api/v1/chats')->assertStatus(200)->json('data');
        $this->assertNull(collect($threads)->firstWhere('id', $thread->id));
    }
}
