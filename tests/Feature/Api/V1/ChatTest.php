<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Application;
use App\Models\ChatMessage;
use App\Models\CollabOpportunity;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ChatTest extends TestCase
{
    use LazilyRefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | Get Messages (GET /api/v1/applications/{application}/messages)
    |--------------------------------------------------------------------------
    */

    public function test_get_messages_requires_authentication(): void
    {
        $application = Application::factory()->create();

        $response = $this->getJson("/api/v1/applications/{$application->id}/messages");

        $response->assertStatus(401);
    }

    public function test_applicant_can_get_messages(): void
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

        ChatMessage::factory()
            ->count(3)
            ->forApplication($application)
            ->fromSender($businessCreator)
            ->create();

        $response = $this->actingAs($communityApplicant)
            ->getJson("/api/v1/applications/{$application->id}/messages");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.total', 3);
    }

    public function test_opportunity_creator_can_get_messages(): void
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

        ChatMessage::factory()
            ->count(2)
            ->forApplication($application)
            ->fromSender($communityApplicant)
            ->create();

        $response = $this->actingAs($businessCreator)
            ->getJson("/api/v1/applications/{$application->id}/messages");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.total', 2);
    }

    public function test_non_participant_cannot_get_messages(): void
    {
        $businessCreator = Profile::factory()->business()->create();
        $communityApplicant = Profile::factory()->community()->create();
        $otherUser = Profile::factory()->business()->create();

        $opportunity = CollabOpportunity::factory()
            ->published()
            ->forCreator($businessCreator)
            ->create();

        $application = Application::factory()
            ->forOpportunity($opportunity)
            ->forApplicant($communityApplicant)
            ->create();

        $response = $this->actingAs($otherUser)
            ->getJson("/api/v1/applications/{$application->id}/messages");

        $response->assertStatus(403);
    }

    /*
    |--------------------------------------------------------------------------
    | Send Message (POST /api/v1/applications/{application}/messages)
    |--------------------------------------------------------------------------
    */

    public function test_send_message_requires_authentication(): void
    {
        $application = Application::factory()->create();

        $response = $this->postJson("/api/v1/applications/{$application->id}/messages", [
            'content' => 'Hello!',
        ]);

        $response->assertStatus(401);
    }

    public function test_applicant_can_send_message(): void
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

        $response = $this->actingAs($communityApplicant)
            ->postJson("/api/v1/applications/{$application->id}/messages", [
                'content' => 'Hello! I am interested in this opportunity.',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.content', 'Hello! I am interested in this opportunity.');

        $this->assertDatabaseHas('chat_messages', [
            'application_id' => $application->id,
            'sender_profile_id' => $communityApplicant->id,
            'content' => 'Hello! I am interested in this opportunity.',
        ]);
    }

    public function test_opportunity_creator_can_send_message(): void
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

        $response = $this->actingAs($businessCreator)
            ->postJson("/api/v1/applications/{$application->id}/messages", [
                'content' => 'Thank you for your interest!',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('chat_messages', [
            'application_id' => $application->id,
            'sender_profile_id' => $businessCreator->id,
        ]);
    }

    public function test_non_participant_cannot_send_message(): void
    {
        $businessCreator = Profile::factory()->business()->create();
        $communityApplicant = Profile::factory()->community()->create();
        $otherUser = Profile::factory()->community()->create();

        $opportunity = CollabOpportunity::factory()
            ->published()
            ->forCreator($businessCreator)
            ->create();

        $application = Application::factory()
            ->forOpportunity($opportunity)
            ->forApplicant($communityApplicant)
            ->create();

        $response = $this->actingAs($otherUser)
            ->postJson("/api/v1/applications/{$application->id}/messages", [
                'content' => 'Trying to send a message',
            ]);

        $response->assertStatus(403);
    }

    public function test_send_message_validates_content(): void
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

        // Empty content
        $response = $this->actingAs($communityApplicant)
            ->postJson("/api/v1/applications/{$application->id}/messages", [
                'content' => '',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['content']);
    }

    /*
    |--------------------------------------------------------------------------
    | Mark as Read (POST /api/v1/applications/{application}/messages/read)
    |--------------------------------------------------------------------------
    */

    public function test_mark_messages_as_read(): void
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

        // Create messages from business (unread for community)
        ChatMessage::factory()
            ->count(3)
            ->forApplication($application)
            ->fromSender($businessCreator)
            ->create();

        $response = $this->actingAs($communityApplicant)
            ->postJson("/api/v1/applications/{$application->id}/messages/read");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.marked_count', 3);

        // Verify messages are marked as read
        $this->assertEquals(0, ChatMessage::query()
            ->where('application_id', $application->id)
            ->whereNull('read_at')
            ->count());
    }

    public function test_mark_as_read_only_marks_other_users_messages(): void
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

        // Messages from business (should be marked)
        ChatMessage::factory()
            ->count(2)
            ->forApplication($application)
            ->fromSender($businessCreator)
            ->create();

        // Messages from community (should NOT be marked when community marks as read)
        ChatMessage::factory()
            ->count(2)
            ->forApplication($application)
            ->fromSender($communityApplicant)
            ->create();

        $response = $this->actingAs($communityApplicant)
            ->postJson("/api/v1/applications/{$application->id}/messages/read");

        $response->assertStatus(200)
            ->assertJsonPath('data.marked_count', 2);
    }

    /*
    |--------------------------------------------------------------------------
    | Unread Count (GET /api/v1/me/unread-messages-count)
    |--------------------------------------------------------------------------
    */

    public function test_get_unread_count(): void
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

        // Unread messages from business
        ChatMessage::factory()
            ->count(5)
            ->forApplication($application)
            ->fromSender($businessCreator)
            ->create();

        $response = $this->actingAs($communityApplicant)
            ->getJson('/api/v1/me/unread-messages-count');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total', 5);
    }

    public function test_unread_count_excludes_own_messages(): void
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

        // Messages from business (unread for community)
        ChatMessage::factory()
            ->count(3)
            ->forApplication($application)
            ->fromSender($businessCreator)
            ->create();

        // Messages from community (should not count as unread for community)
        ChatMessage::factory()
            ->count(2)
            ->forApplication($application)
            ->fromSender($communityApplicant)
            ->create();

        $response = $this->actingAs($communityApplicant)
            ->getJson('/api/v1/me/unread-messages-count');

        $response->assertStatus(200)
            ->assertJsonPath('data.total', 3);
    }

    /*
    |--------------------------------------------------------------------------
    | Message Response Structure
    |--------------------------------------------------------------------------
    */

    public function test_message_response_structure(): void
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

        ChatMessage::factory()
            ->forApplication($application)
            ->fromSender($businessCreator)
            ->create(['content' => 'Test message']);

        $response = $this->actingAs($communityApplicant)
            ->getJson("/api/v1/applications/{$application->id}/messages");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'application_id',
                            'sender_profile',
                            'content',
                            'is_own',
                            'is_read',
                            'read_at',
                            'created_at',
                        ],
                    ],
                ],
                'meta' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                ],
            ]);
    }
}
