<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\NotificationType;
use App\Models\Application;
use App\Models\BusinessProfile;
use App\Models\BusinessSubscription;
use App\Models\CollabOpportunity;
use App\Models\CommunityProfile;
use App\Models\Notification;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use LazilyRefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | Notification List (GET /api/v1/me/notifications)
    |--------------------------------------------------------------------------
    */

    public function test_notifications_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/me/notifications');

        $response->assertStatus(401);
    }

    public function test_notifications_returns_own_notifications(): void
    {
        $profile = Profile::factory()->business()->create();

        Notification::factory()
            ->count(3)
            ->forProfile($profile)
            ->create();

        $response = $this->actingAs($profile)
            ->getJson('/api/v1/me/notifications');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.total', 3);
    }

    public function test_notifications_does_not_return_other_users_notifications(): void
    {
        $profile = Profile::factory()->business()->create();
        $otherProfile = Profile::factory()->community()->create();

        Notification::factory()
            ->count(2)
            ->forProfile($profile)
            ->create();

        Notification::factory()
            ->count(3)
            ->forProfile($otherProfile)
            ->create();

        $response = $this->actingAs($profile)
            ->getJson('/api/v1/me/notifications');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 2);
    }

    public function test_notifications_returns_correct_structure(): void
    {
        $profile = Profile::factory()->business()->create();
        $actor = Profile::factory()->community()->create();

        Notification::factory()
            ->forProfile($profile)
            ->fromActor($actor)
            ->applicationReceived()
            ->create();

        $response = $this->actingAs($profile)
            ->getJson('/api/v1/me/notifications');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'type',
                        'title',
                        'body',
                        'is_read',
                        'read_at',
                        'created_at',
                        'actor_name',
                        'actor_avatar_url',
                        'target_id',
                        'target_type',
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

    public function test_notifications_ordered_newest_first(): void
    {
        $profile = Profile::factory()->business()->create();

        $older = Notification::factory()
            ->forProfile($profile)
            ->create(['created_at' => now()->subHour()]);

        $newer = Notification::factory()
            ->forProfile($profile)
            ->create(['created_at' => now()]);

        $response = $this->actingAs($profile)
            ->getJson('/api/v1/me/notifications');

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertEquals($newer->id, $data[0]['id']);
        $this->assertEquals($older->id, $data[1]['id']);
    }

    public function test_notifications_supports_pagination(): void
    {
        $profile = Profile::factory()->business()->create();

        Notification::factory()
            ->count(5)
            ->forProfile($profile)
            ->create();

        $response = $this->actingAs($profile)
            ->getJson('/api/v1/me/notifications?per_page=2');

        $response->assertStatus(200)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 5)
            ->assertJsonCount(2, 'data');
    }

    public function test_notifications_returns_empty_when_none(): void
    {
        $profile = Profile::factory()->business()->create();

        $response = $this->actingAs($profile)
            ->getJson('/api/v1/me/notifications');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.total', 0)
            ->assertJsonCount(0, 'data');
    }

    /*
    |--------------------------------------------------------------------------
    | Unread Count (GET /api/v1/me/notifications/unread-count)
    |--------------------------------------------------------------------------
    */

    public function test_unread_count_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/me/notifications/unread-count');

        $response->assertStatus(401);
    }

    public function test_unread_count_returns_correct_count(): void
    {
        $profile = Profile::factory()->business()->create();

        Notification::factory()
            ->count(3)
            ->forProfile($profile)
            ->unread()
            ->create();

        Notification::factory()
            ->count(2)
            ->forProfile($profile)
            ->read()
            ->create();

        $response = $this->actingAs($profile)
            ->getJson('/api/v1/me/notifications/unread-count');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.count', 3);
    }

    public function test_unread_count_returns_zero_when_all_read(): void
    {
        $profile = Profile::factory()->business()->create();

        Notification::factory()
            ->count(3)
            ->forProfile($profile)
            ->read()
            ->create();

        $response = $this->actingAs($profile)
            ->getJson('/api/v1/me/notifications/unread-count');

        $response->assertStatus(200)
            ->assertJsonPath('data.count', 0);
    }

    /*
    |--------------------------------------------------------------------------
    | Mark as Read (POST /api/v1/me/notifications/{notification}/read)
    |--------------------------------------------------------------------------
    */

    public function test_mark_as_read_requires_authentication(): void
    {
        $notification = Notification::factory()->create();

        $response = $this->postJson("/api/v1/me/notifications/{$notification->id}/read");

        $response->assertStatus(401);
    }

    public function test_mark_as_read_marks_notification(): void
    {
        $profile = Profile::factory()->business()->create();

        $notification = Notification::factory()
            ->forProfile($profile)
            ->unread()
            ->create();

        $response = $this->actingAs($profile)
            ->postJson("/api/v1/me/notifications/{$notification->id}/read");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $notification->id)
            ->assertJsonPath('data.is_read', true);

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_mark_as_read_returns_404_for_nonexistent(): void
    {
        $profile = Profile::factory()->business()->create();

        $response = $this->actingAs($profile)
            ->postJson('/api/v1/me/notifications/00000000-0000-0000-0000-000000000000/read');

        $response->assertStatus(404);
    }

    public function test_mark_as_read_returns_403_for_other_users_notification(): void
    {
        $profile = Profile::factory()->business()->create();
        $otherProfile = Profile::factory()->community()->create();

        $notification = Notification::factory()
            ->forProfile($otherProfile)
            ->unread()
            ->create();

        $response = $this->actingAs($profile)
            ->postJson("/api/v1/me/notifications/{$notification->id}/read");

        $response->assertStatus(403);
    }

    public function test_mark_as_read_already_read_notification_updates_read_at(): void
    {
        $profile = Profile::factory()->business()->create();

        $notification = Notification::factory()
            ->forProfile($profile)
            ->read()
            ->create();

        $originalReadAt = $notification->read_at;

        // Small delay to ensure time difference
        $this->travel(1)->seconds();

        $response = $this->actingAs($profile)
            ->postJson("/api/v1/me/notifications/{$notification->id}/read");

        $response->assertStatus(200)
            ->assertJsonPath('data.is_read', true);

        $updatedReadAt = $notification->fresh()->read_at;
        $this->assertNotEquals($originalReadAt->toIso8601String(), $updatedReadAt->toIso8601String());
    }

    /*
    |--------------------------------------------------------------------------
    | Mark All as Read (POST /api/v1/me/notifications/read-all)
    |--------------------------------------------------------------------------
    */

    public function test_mark_all_as_read_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/me/notifications/read-all');

        $response->assertStatus(401);
    }

    public function test_mark_all_as_read_marks_all_unread(): void
    {
        $profile = Profile::factory()->business()->create();

        Notification::factory()
            ->count(4)
            ->forProfile($profile)
            ->unread()
            ->create();

        Notification::factory()
            ->count(1)
            ->forProfile($profile)
            ->read()
            ->create();

        $response = $this->actingAs($profile)
            ->postJson('/api/v1/me/notifications/read-all');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.updated_count', 4);

        $this->assertEquals(
            0,
            Notification::query()
                ->where('profile_id', $profile->id)
                ->whereNull('read_at')
                ->count()
        );
    }

    public function test_mark_all_as_read_returns_zero_when_none_unread(): void
    {
        $profile = Profile::factory()->business()->create();

        Notification::factory()
            ->count(2)
            ->forProfile($profile)
            ->read()
            ->create();

        $response = $this->actingAs($profile)
            ->postJson('/api/v1/me/notifications/read-all');

        $response->assertStatus(200)
            ->assertJsonPath('data.updated_count', 0);
    }

    /*
    |--------------------------------------------------------------------------
    | Notification Creation (Integration Tests)
    |--------------------------------------------------------------------------
    */

    public function test_sending_message_creates_notification_for_recipient(): void
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

        // Send message from applicant to creator
        $this->actingAs($communityApplicant)
            ->postJson("/api/v1/applications/{$application->id}/messages", [
                'content' => 'Hello, I am interested!',
            ]);

        // Creator should have a new_message notification
        $this->assertDatabaseHas('notifications', [
            'profile_id' => $businessCreator->id,
            'type' => NotificationType::NewMessage->value,
            'title' => 'New Message',
            'target_id' => $application->id,
            'target_type' => 'application',
        ]);
    }

    public function test_applying_creates_notification_for_opportunity_owner(): void
    {
        $businessCreator = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $businessCreator->id]);

        $communityApplicant = Profile::factory()->community()->create(['phone_number' => '+34600000001']);
        CommunityProfile::factory()->create([
            'profile_id' => $communityApplicant->id,
            'instagram' => 'testuser',
        ]);

        $opportunity = CollabOpportunity::factory()
            ->published()
            ->forCreator($businessCreator)
            ->create();

        $response = $this->actingAs($communityApplicant)
            ->postJson("/api/v1/opportunities/{$opportunity->id}/applications", [
                'message' => 'I would love to collaborate with your business!',
                'availability' => 'Available on weekends and evenings throughout the month.',
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('notifications', [
            'profile_id' => $businessCreator->id,
            'type' => NotificationType::ApplicationReceived->value,
            'title' => 'New Application',
            'target_type' => 'application',
        ]);
    }

    public function test_accepting_application_creates_notification_for_applicant(): void
    {
        $businessCreator = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $businessCreator->id]);
        BusinessSubscription::factory()->active()->create(['profile_id' => $businessCreator->id]);

        $communityApplicant = Profile::factory()->community()->create();
        CommunityProfile::factory()->create(['profile_id' => $communityApplicant->id]);

        $opportunity = CollabOpportunity::factory()
            ->published()
            ->forCreator($businessCreator)
            ->create();

        $application = Application::factory()
            ->forOpportunity($opportunity)
            ->forApplicant($communityApplicant)
            ->pending()
            ->create();

        // Clear cached relationships to pick up subscription
        $businessCreator->refresh();

        $response = $this->actingAs($businessCreator)
            ->postJson("/api/v1/applications/{$application->id}/accept", [
                'scheduled_date' => now()->addWeek()->toDateString(),
                'contact_methods' => ['email' => $businessCreator->email],
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('notifications', [
            'profile_id' => $communityApplicant->id,
            'type' => NotificationType::ApplicationAccepted->value,
            'title' => 'Application Accepted',
            'target_id' => $application->id,
            'target_type' => 'application',
        ]);
    }

    public function test_declining_application_creates_notification_for_applicant(): void
    {
        $businessCreator = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $businessCreator->id]);

        $communityApplicant = Profile::factory()->community()->create();
        CommunityProfile::factory()->create(['profile_id' => $communityApplicant->id]);

        $opportunity = CollabOpportunity::factory()
            ->published()
            ->forCreator($businessCreator)
            ->create();

        $application = Application::factory()
            ->forOpportunity($opportunity)
            ->forApplicant($communityApplicant)
            ->pending()
            ->create();

        $this->actingAs($businessCreator)
            ->postJson("/api/v1/applications/{$application->id}/decline");

        $this->assertDatabaseHas('notifications', [
            'profile_id' => $communityApplicant->id,
            'type' => NotificationType::ApplicationDeclined->value,
            'title' => 'Application Declined',
            'target_id' => $application->id,
            'target_type' => 'application',
        ]);
    }
}
