<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Application;
use App\Models\ChatThread;
use App\Models\CollabOpportunity;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ChatActiveListTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @return array{0: Profile, 1: Profile, 2: Application}
     */
    private function makeConversation(): array
    {
        $businessCreator = Profile::factory()->business()->create();
        $communityApplicant = Profile::factory()->community()->create();

        $opportunity = CollabOpportunity::factory()
            ->published()
            ->forCreator($businessCreator)
            ->create();

        $application = Application::factory()
            ->forOpportunity($opportunity)
            ->forApplicant($communityApplicant)
            ->create();

        return [$businessCreator, $communityApplicant, $application];
    }

    public function test_sending_a_message_creates_thread_and_sets_last_message_at(): void
    {
        [$business, $community, $application] = $this->makeConversation();

        $this->actingAs($community)
            ->postJson("/api/v1/applications/{$application->id}/messages", ['content' => 'Hi there'])
            ->assertStatus(201);

        $thread = ChatThread::query()->where('application_id', $application->id)->first();

        $this->assertNotNull($thread);
        $this->assertSame('collaboration', $thread->type->value);
        $this->assertNotNull($thread->last_message_at);
        $this->assertSame(1, $thread->messages()->count());
    }

    public function test_business_active_chats_lists_only_threads_with_messages(): void
    {
        [$business, $community, $application] = $this->makeConversation();

        // No message yet → a bare match must NOT appear in the business inbox.
        $this->actingAs($business)->getJson('/api/v1/chats')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');

        // Community sends one message.
        $this->actingAs($community)
            ->postJson("/api/v1/applications/{$application->id}/messages", ['content' => 'Hello'])
            ->assertStatus(201);

        $this->actingAs($business)->getJson('/api/v1/chats')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'collaboration')
            ->assertJsonPath('data.0.application_id', $application->id)
            ->assertJsonPath('data.0.unread_count', 1);
    }

    public function test_unread_count_endpoint_totals_across_threads(): void
    {
        [$business, $community, $application] = $this->makeConversation();

        $this->actingAs($community)
            ->postJson("/api/v1/applications/{$application->id}/messages", ['content' => 'One'])
            ->assertStatus(201);
        $this->actingAs($community)
            ->postJson("/api/v1/applications/{$application->id}/messages", ['content' => 'Two'])
            ->assertStatus(201);

        $this->actingAs($business)->getJson('/api/v1/chats/unread-count')
            ->assertStatus(200)
            ->assertJsonPath('data.total', 2);
    }

    public function test_community_sees_its_collaboration_thread(): void
    {
        [$business, $community, $application] = $this->makeConversation();

        $this->actingAs($business)
            ->postJson("/api/v1/applications/{$application->id}/messages", ['content' => 'Welcome'])
            ->assertStatus(201);

        $this->actingAs($community)->getJson('/api/v1/chats')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.application_id', $application->id);
    }

    public function test_outsider_does_not_see_the_thread(): void
    {
        [$business, $community, $application] = $this->makeConversation();
        $outsider = Profile::factory()->business()->create();

        $this->actingAs($community)
            ->postJson("/api/v1/applications/{$application->id}/messages", ['content' => 'Private'])
            ->assertStatus(201);

        $this->actingAs($outsider)->getJson('/api/v1/chats')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }
}
