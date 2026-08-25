<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\ApplicationStatus;
use App\Enums\ChatThreadType;
use App\Models\Application;
use App\Models\ChatThread;
use App\Models\Kolab;
use App\Models\Notification;
use App\Models\NotificationReminder;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * The generic thread endpoints (`/chats/{thread}/…`) against COLLABORATION threads.
 *
 * Everything else exercises them with community/event threads, but the web panel
 * reads every conversation — Kolab chats included — through this one path so it
 * has a single rendering code path. That made an untested assumption load-bearing:
 * these tests pin it.
 */
class ChatCollaborationThreadEndpointTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @return array{0: Profile, 1: Profile, 2: Application}
     */
    private function makeConversation(): array
    {
        $business = Profile::factory()->business()->create();
        $community = Profile::factory()->community()->create();

        $application = Application::factory()
            ->forKolab(Kolab::factory()->published()->forCreator($business)->create())
            ->forApplicant($community)
            ->create();

        return [$business, $community, $application];
    }

    private function threadFor(Application $application): ChatThread
    {
        /** @var ChatThread $thread */
        $thread = ChatThread::query()->where('application_id', $application->id)->firstOrFail();

        return $thread;
    }

    /**
     * The thread as a client would find it BEFORE any message exists — threads are
     * created lazily, so the notification tests below need one without paying the
     * side-effects of sending a message first.
     */
    private function emptyThreadFor(Application $application): ChatThread
    {
        /** @var ChatThread $thread */
        $thread = ChatThread::query()->create([
            'application_id' => $application->id,
            'type' => ChatThreadType::Collaboration->value,
        ]);

        return $thread;
    }

    private function newMessageNotificationCount(Profile $recipient, Application $application): int
    {
        return Notification::query()
            ->where('profile_id', $recipient->id)
            ->where('type', 'new_message')
            ->where('target_type', 'application')
            ->where('target_id', $application->id)
            ->count();
    }

    public function test_a_kolab_conversation_is_readable_through_the_thread_endpoint(): void
    {
        [$business, $community, $application] = $this->makeConversation();

        $this->actingAs($community)
            ->postJson("/api/v1/applications/{$application->id}/messages", ['content' => 'First'])
            ->assertStatus(201);
        $this->actingAs($business)
            ->postJson("/api/v1/applications/{$application->id}/messages", ['content' => 'Second'])
            ->assertStatus(201);

        // Oldest-first, under `data.messages` — the shape the web panel renders.
        $this->actingAs($business)
            ->getJson("/api/v1/chats/{$this->threadFor($application)->id}/messages")
            ->assertOk()
            ->assertJsonCount(2, 'data.messages')
            ->assertJsonPath('data.messages.0.content', 'First')
            ->assertJsonPath('data.messages.1.content', 'Second')
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.last_page', 1);
    }

    public function test_reading_a_kolab_thread_clears_its_unread_count(): void
    {
        [$business, $community, $application] = $this->makeConversation();

        $this->actingAs($community)
            ->postJson("/api/v1/applications/{$application->id}/messages", ['content' => 'Are you in?'])
            ->assertStatus(201);

        $this->actingAs($business)
            ->getJson('/api/v1/chats/unread-count')
            ->assertOk()
            ->assertJsonPath('data.total', 1);

        // Opening the thread through the generic endpoint must move the same read
        // pointer the application endpoint uses, or the badge would never clear.
        $this->actingAs($business)
            ->getJson("/api/v1/chats/{$this->threadFor($application)->id}/messages")
            ->assertOk();

        $this->actingAs($business)
            ->getJson('/api/v1/chats/unread-count')
            ->assertOk()
            ->assertJsonPath('data.total', 0);
    }

    public function test_marking_a_kolab_thread_read_is_accepted(): void
    {
        [$business, $community, $application] = $this->makeConversation();

        $this->actingAs($community)
            ->postJson("/api/v1/applications/{$application->id}/messages", ['content' => 'Ping'])
            ->assertStatus(201);

        $this->actingAs($business)
            ->postJson("/api/v1/chats/{$this->threadFor($application)->id}/read")
            ->assertOk();

        $this->actingAs($business)
            ->getJson('/api/v1/chats/unread-count')
            ->assertOk()
            ->assertJsonPath('data.total', 0);
    }

    public function test_an_outsider_cannot_read_a_kolab_thread(): void
    {
        [, $community, $application] = $this->makeConversation();

        $this->actingAs($community)
            ->postJson("/api/v1/applications/{$application->id}/messages", ['content' => 'Private'])
            ->assertStatus(201);

        $outsider = Profile::factory()->business()->create();

        $this->actingAs($outsider)
            ->getJson("/api/v1/chats/{$this->threadFor($application)->id}/messages")
            ->assertForbidden();

        $this->actingAs($outsider)
            ->postJson("/api/v1/chats/{$this->threadFor($application)->id}/messages", ['content' => 'Let me in'])
            ->assertForbidden();
    }

    public function test_a_kolab_thread_cannot_be_renamed_or_deleted_as_a_channel(): void
    {
        [$business, $community, $application] = $this->makeConversation();

        $this->actingAs($community)
            ->postJson("/api/v1/applications/{$application->id}/messages", ['content' => 'Hello'])
            ->assertStatus(201);

        $thread = $this->threadFor($application);

        // Channel management belongs to custom community chats only — a participant
        // must not be able to rename or delete the conversation itself.
        $this->actingAs($business)
            ->patchJson("/api/v1/chats/{$thread->id}", ['name' => 'Renamed'])
            ->assertForbidden();

        $this->actingAs($business)
            ->deleteJson("/api/v1/chats/{$thread->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('chat_threads', ['id' => $thread->id]);
    }

    /*
     |-------------------------------------------------------------------------
     | BE-FX-13 — the generic endpoint must notify, exactly like the application one
     |-------------------------------------------------------------------------
     */

    public function test_a_kolab_message_sent_through_the_thread_endpoint_notifies_the_other_party(): void
    {
        [$business, $community, $application] = $this->makeConversation();
        $thread = $this->emptyThreadFor($application);

        $this->actingAs($community)
            ->postJson("/api/v1/chats/{$thread->id}/messages", ['content' => 'Are you in?'])
            ->assertStatus(201);

        // EXACTLY one notification for the recipient, and none for the sender.
        $this->assertSame(1, $this->newMessageNotificationCount($business, $application));
        $this->assertSame(0, $this->newMessageNotificationCount($community, $application));

        // No stray chat_thread-targeted fan-out on top of it.
        $this->assertDatabaseMissing('notifications', [
            'target_id' => $thread->id,
            'target_type' => 'chat_thread',
        ]);
    }

    public function test_a_kolab_message_notifies_exactly_once_on_either_route(): void
    {
        [$business, $community, $application] = $this->makeConversation();
        $thread = $this->emptyThreadFor($application);

        $this->actingAs($community)
            ->postJson("/api/v1/chats/{$thread->id}/messages", ['content' => 'Via the thread route'])
            ->assertStatus(201);
        $this->actingAs($community)
            ->postJson("/api/v1/applications/{$application->id}/messages", ['content' => 'Via the application route'])
            ->assertStatus(201);

        // Two messages, two notifications — one per message, neither route doubling.
        $this->assertSame(2, $this->newMessageNotificationCount($business, $application));
        $this->assertSame(
            2,
            Notification::query()->where('type', 'new_message')->count(),
            'A message must produce one new_message notification in total, on either route.',
        );
    }

    public function test_a_kolab_message_through_the_thread_endpoint_arms_the_unread_message_reminder(): void
    {
        [$business, $community, $application] = $this->makeConversation();
        $thread = $this->emptyThreadFor($application);

        $this->actingAs($community)
            ->postJson("/api/v1/chats/{$thread->id}/messages", ['content' => 'Still waiting'])
            ->assertStatus(201);

        $reminder = NotificationReminder::query()
            ->where('profile_id', $business->id)
            ->where('type', 'unread_message')
            ->where('entity_id', $application->id)
            ->where('entity_type', 'application')
            ->first();

        $this->assertNotNull($reminder, 'The unread-message reminder must see thread-route messages.');
        $this->assertNull($reminder->cancelled_at);
        $this->assertNotNull($reminder->scheduled_for);
    }

    public function test_the_thread_endpoint_keeps_the_declined_application_suppression(): void
    {
        [$business, $community, $application] = $this->makeConversation();
        $thread = $this->emptyThreadFor($application);
        $application->forceFill(['status' => ApplicationStatus::Declined->value])->save();

        $this->actingAs($community)
            ->postJson("/api/v1/chats/{$thread->id}/messages", ['content' => 'Reconsider?'])
            ->assertForbidden();

        $this->assertSame(0, $this->newMessageNotificationCount($business, $application));
        $this->assertDatabaseMissing('chat_messages', ['thread_id' => $thread->id]);
    }

    public function test_the_thread_endpoint_response_shape_is_unchanged(): void
    {
        [, $community, $application] = $this->makeConversation();
        $thread = $this->emptyThreadFor($application);

        $this->actingAs($community)
            ->postJson("/api/v1/chats/{$thread->id}/messages", ['content' => 'Shape check'])
            ->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.content', 'Shape check')
            ->assertJsonPath('data.application_id', $application->id)
            ->assertJsonPath('data.thread_id', $thread->id)
            ->assertJsonPath('data.is_own', true)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['id', 'application_id', 'thread_id', 'sender_profile', 'content', 'is_own', 'is_read', 'read_at', 'created_at'],
            ]);

        // The message still belongs to the same (single) thread — no duplicate row.
        $this->assertSame(1, ChatThread::query()->where('application_id', $application->id)->count());
        $this->assertDatabaseHas('chat_messages', [
            'thread_id' => $thread->id,
            'application_id' => $application->id,
        ]);
    }
}
