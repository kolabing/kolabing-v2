<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Application;
use App\Models\ChatThread;
use App\Models\Kolab;
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
}
