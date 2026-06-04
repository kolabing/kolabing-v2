<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Event;
use App\Models\Profile;
use App\Services\CommunityService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class EventChatTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function community(Profile $leader): Community
    {
        return app(CommunityService::class)->create($leader, [
            'name' => 'Barcelona Run Club',
            'type' => 'greek',
        ]);
    }

    private function upcomingEvent(Community $community, array $overrides = []): Event
    {
        return Event::factory()->create(array_merge([
            'profile_id' => $community->owner_profile_id,
            'community_id' => $community->id,
            'starts_at' => now()->addDays(2),
            'ends_at' => now()->addDays(2)->addHours(3),
        ], $overrides));
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

    public function test_leader_creates_event_chat(): void
    {
        $leader = Profile::factory()->community()->create();
        $community = $this->community($leader);
        $event = $this->upcomingEvent($community);

        $this->actingAs($leader)
            ->postJson("/api/v1/events/{$event->id}/chat")
            ->assertStatus(201)
            ->assertJsonPath('data.type', 'event')
            ->assertJsonPath('data.event_id', $event->id);
    }

    public function test_non_manager_cannot_create_event_chat(): void
    {
        $leader = Profile::factory()->community()->create();
        $community = $this->community($leader);
        $event = $this->upcomingEvent($community);
        $member = $this->member($community);

        $this->actingAs($member)->postJson("/api/v1/events/{$event->id}/chat")->assertStatus(403);
    }

    public function test_only_going_members_access_event_chat(): void
    {
        $leader = Profile::factory()->community()->create();
        $community = $this->community($leader);
        $event = $this->upcomingEvent($community, ['capacity' => 1]);

        $going = $this->member($community);
        $waitlisted = $this->member($community);
        $outsider = Profile::factory()->attendee()->create();

        $this->actingAs($going)->postJson("/api/v1/events/{$event->id}/signup")->assertStatus(200);
        $this->actingAs($waitlisted)->postJson("/api/v1/events/{$event->id}/signup")->assertStatus(200);

        $threadId = $this->actingAs($leader)
            ->postJson("/api/v1/events/{$event->id}/chat")->json('data.id');

        // Going member + leader: access. Waitlisted + outsider: denied.
        $this->actingAs($going)->getJson("/api/v1/chats/{$threadId}/messages")->assertStatus(200);
        $this->actingAs($leader)->getJson("/api/v1/chats/{$threadId}/messages")->assertStatus(200);
        $this->actingAs($waitlisted)->getJson("/api/v1/chats/{$threadId}/messages")->assertStatus(403);
        $this->actingAs($outsider)->getJson("/api/v1/chats/{$threadId}/messages")->assertStatus(403);
    }

    public function test_event_thread_appears_in_chats_for_going_member_and_send_works(): void
    {
        $leader = Profile::factory()->community()->create();
        $community = $this->community($leader);
        $event = $this->upcomingEvent($community);
        $member = $this->member($community);

        $this->actingAs($member)->postJson("/api/v1/events/{$event->id}/signup")->assertStatus(200);
        $threadId = $this->actingAs($leader)->postJson("/api/v1/events/{$event->id}/chat")->json('data.id');

        // Going member posts a message.
        $this->actingAs($member)
            ->postJson("/api/v1/chats/{$threadId}/messages", ['content' => 'See you there!'])
            ->assertStatus(201)
            ->assertJsonPath('data.thread_id', $threadId);

        // The event thread shows up in the going member's inbox.
        $threads = $this->actingAs($member)->getJson('/api/v1/chats')->assertStatus(200)->json('data');
        $eventThread = collect($threads)->firstWhere('type', 'event');
        $this->assertNotNull($eventThread);
        $this->assertSame($event->id, $eventThread['event_id']);

        // And the message is readable back.
        $this->actingAs($member)->getJson("/api/v1/chats/{$threadId}/messages")
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.messages')
            ->assertJsonPath('data.messages.0.content', 'See you there!');
    }
}
