<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\ChatThreadType;
use App\Models\ChatThread;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Event;
use App\Models\NotificationPreference;
use App\Models\Profile;
use App\Services\CommunityService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ChatMessageNotificationTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function community(Profile $leader): Community
    {
        return app(CommunityService::class)->create($leader, [
            'name' => 'Barcelona Run Club',
            'type' => 'greek',
        ]);
    }

    private function member(Community $community): Profile
    {
        $profile = Profile::factory()->attendee()->create();
        CommunityMember::factory()->forCommunity($community)->create([
            'profile_id' => $profile->id,
            'tier_id' => $community->defaultTier->id,
            'status' => 'active',
        ]);

        return $profile;
    }

    private function mainThreadId(Community $community): string
    {
        return ChatThread::query()
            ->where('community_id', $community->id)
            ->where('type', ChatThreadType::CommunityMain->value)
            ->value('id');
    }

    public function test_community_message_notifies_other_members_not_the_sender(): void
    {
        $leader = Profile::factory()->community()->create();
        $community = $this->community($leader);
        $a = $this->member($community);
        $b = $this->member($community);
        $threadId = $this->mainThreadId($community);

        $this->actingAs($a)
            ->postJson("/api/v1/chats/{$threadId}/messages", ['content' => 'Morning run Saturday?'])
            ->assertStatus(201);

        // Other members + leader get an in-app notification; the sender does not.
        $this->assertDatabaseHas('notifications', [
            'profile_id' => $b->id, 'type' => 'new_message',
            'target_id' => $threadId, 'target_type' => 'chat_thread',
        ]);
        $this->assertDatabaseHas('notifications', [
            'profile_id' => $leader->id, 'type' => 'new_message', 'target_id' => $threadId,
        ]);
        $this->assertDatabaseMissing('notifications', [
            'profile_id' => $a->id, 'target_id' => $threadId,
        ]);
    }

    public function test_member_who_opted_out_of_message_notifications_is_not_notified(): void
    {
        $leader = Profile::factory()->community()->create();
        $community = $this->community($leader);
        $a = $this->member($community);
        $b = $this->member($community);
        $threadId = $this->mainThreadId($community);

        NotificationPreference::query()->create([
            'profile_id' => $b->id,
            'message_notifications' => false,
        ]);

        $this->actingAs($a)
            ->postJson("/api/v1/chats/{$threadId}/messages", ['content' => 'ping'])
            ->assertStatus(201);

        $this->assertDatabaseMissing('notifications', [
            'profile_id' => $b->id, 'target_id' => $threadId,
        ]);
        // Leader (default ON) is still notified.
        $this->assertDatabaseHas('notifications', [
            'profile_id' => $leader->id, 'target_id' => $threadId,
        ]);
    }

    public function test_event_message_notifies_going_members(): void
    {
        $leader = Profile::factory()->community()->create();
        $community = $this->community($leader);
        $event = Event::factory()->create([
            'profile_id' => $leader->id,
            'community_id' => $community->id,
            'starts_at' => now()->addDays(2),
            'ends_at' => now()->addDays(2)->addHours(3),
        ]);

        $m1 = $this->member($community);
        $m2 = $this->member($community);
        $this->actingAs($m1)->postJson("/api/v1/events/{$event->id}/signup")->assertStatus(200);
        $this->actingAs($m2)->postJson("/api/v1/events/{$event->id}/signup")->assertStatus(200);
        $threadId = $this->actingAs($leader)->postJson("/api/v1/events/{$event->id}/chat")->json('data.id');

        $this->actingAs($m1)
            ->postJson("/api/v1/chats/{$threadId}/messages", ['content' => 'On my way'])
            ->assertStatus(201);

        // The other going member + leader are notified; the sender is not.
        $this->assertDatabaseHas('notifications', [
            'profile_id' => $m2->id, 'type' => 'new_message', 'target_id' => $threadId,
        ]);
        $this->assertDatabaseHas('notifications', [
            'profile_id' => $leader->id, 'target_id' => $threadId,
        ]);
        $this->assertDatabaseMissing('notifications', [
            'profile_id' => $m1->id, 'target_id' => $threadId,
        ]);
    }
}
