<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\NotificationType;
use App\Jobs\Notifications\SendScheduledNotificationJob;
use App\Models\Application;
use App\Models\BusinessProfile;
use App\Models\BusinessSubscription;
use App\Models\CollabOpportunity;
use App\Models\Collaboration;
use App\Models\CommunityProfile;
use App\Models\Event;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CollaborationNotificationTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'notifications.enabled_types.collaboration_scheduled' => true,
            'notifications.enabled_types.collaboration_rescheduled' => true,
            'notifications.enabled_types.collaboration_cancelled' => true,
            'notifications.enabled_types.collaboration_reminder_24h' => true,
            'notifications.enabled_types.collaboration_reminder_same_day' => true,
        ]);
    }

    public function test_accepting_application_creates_collaboration_scheduled_notifications_for_both_participants(): void
    {
        [$business, $community, $application] = $this->seedApplicationContext();

        $response = $this->actingAs($business)->postJson("/api/v1/applications/{$application->id}/accept", [
            'scheduled_date' => now()->addWeek()->toDateString(),
            'contact_methods' => ['email' => $business->email],
        ]);

        $this->assertSame(200, $response->status(), $response->getContent());

        $collaborationId = $response->json('data.collaboration.id');

        $this->assertDatabaseHas('notifications', [
            'profile_id' => $business->id,
            'type' => NotificationType::CollaborationScheduled->value,
            'target_id' => $collaborationId,
            'target_type' => 'collaboration',
        ]);

        $this->assertDatabaseHas('notifications', [
            'profile_id' => $community->id,
            'type' => NotificationType::CollaborationScheduled->value,
            'target_id' => $collaborationId,
            'target_type' => 'collaboration',
        ]);
    }

    public function test_cancelling_collaboration_creates_cancelled_notifications_for_both_participants(): void
    {
        [$business, $community, $collaboration] = $this->seedCollaborationContext();

        $response = $this->actingAs($business)->postJson("/api/v1/collaborations/{$collaboration->id}/cancel", [
            'reason' => 'The venue is unavailable so we need to cancel this plan.',
        ]);

        $this->assertSame(200, $response->status(), $response->getContent());

        $this->assertDatabaseHas('notifications', [
            'profile_id' => $business->id,
            'type' => NotificationType::CollaborationCancelled->value,
            'target_id' => $collaboration->id,
            'target_type' => 'collaboration',
        ]);

        $this->assertDatabaseHas('notifications', [
            'profile_id' => $community->id,
            'type' => NotificationType::CollaborationCancelled->value,
            'target_id' => $collaboration->id,
            'target_type' => 'collaboration',
        ]);
    }

    public function test_rescheduling_collaboration_creates_one_notification_per_participant_and_syncs_event_date(): void
    {
        [$business, $community, $collaboration] = $this->seedCollaborationContext();
        $event = Event::factory()->forProfile($business)->create([
            'event_date' => now()->addWeek()->toDateString(),
            'is_active' => true,
            'location_lat' => 41.3874,
            'location_lng' => 2.1686,
        ]);

        $collaboration->update([
            'event_id' => $event->id,
        ]);

        $newDate = now()->addDays(12)->toDateString();

        $response = $this->actingAs($community)->patchJson("/api/v1/collaborations/{$collaboration->id}/schedule", [
            'scheduled_date' => $newDate,
        ]);

        $this->assertSame(200, $response->status(), $response->getContent());

        $this->assertDatabaseHas('notifications', [
            'profile_id' => $business->id,
            'type' => NotificationType::CollaborationRescheduled->value,
            'target_id' => $collaboration->id,
        ]);

        $this->assertDatabaseHas('notifications', [
            'profile_id' => $community->id,
            'type' => NotificationType::CollaborationRescheduled->value,
            'target_id' => $collaboration->id,
        ]);

        $this->assertSame($newDate, $event->fresh()->event_date->toDateString());

        $retryResponse = $this->actingAs($community)->patchJson("/api/v1/collaborations/{$collaboration->id}/schedule", [
            'scheduled_date' => $newDate,
        ]);

        $this->assertSame(200, $retryResponse->status(), $retryResponse->getContent());
        $this->assertSame(
            2,
            \App\Models\Notification::query()
                ->where('type', NotificationType::CollaborationRescheduled)
                ->where('target_id', $collaboration->id)
                ->count()
        );
    }

    public function test_reminder_job_skips_cancelled_collaborations(): void
    {
        [$business, $community] = $this->seedProfiles();
        $otherCommunity = Profile::factory()->community()->create();
        CommunityProfile::factory()->create([
            'profile_id' => $otherCommunity->id,
            'name' => 'Other Community Applicant',
        ]);
        $opportunity = CollabOpportunity::factory()
            ->published()
            ->forCreator($business)
            ->create();

        $scheduledApplication = Application::factory()
            ->pending()
            ->forOpportunity($opportunity)
            ->forApplicant($community)
            ->create();

        $cancelledApplication = Application::factory()
            ->pending()
            ->forOpportunity($opportunity)
            ->forApplicant($otherCommunity)
            ->create();

        $scheduled = Collaboration::factory()
            ->scheduled()
            ->scheduledOn(now()->addDay()->toDateString())
            ->forCreator($business)
            ->forApplicant($community)
            ->forOpportunity($opportunity)
            ->forApplication($scheduledApplication)
            ->create();

        $cancelled = Collaboration::factory()
            ->cancelled()
            ->scheduledOn(now()->addDay()->toDateString())
            ->forCreator($business)
            ->forApplicant($otherCommunity)
            ->forOpportunity($opportunity)
            ->forApplication($cancelledApplication)
            ->create();

        $job = new SendScheduledNotificationJob('24h');
        $job->handle(app(\App\Services\NotificationService::class));

        $this->assertDatabaseHas('notifications', [
            'profile_id' => $business->id,
            'type' => NotificationType::CollaborationReminder24h->value,
            'target_id' => $scheduled->id,
        ]);

        $this->assertDatabaseHas('notifications', [
            'profile_id' => $community->id,
            'type' => NotificationType::CollaborationReminder24h->value,
            'target_id' => $scheduled->id,
        ]);

        $this->assertDatabaseMissing('notifications', [
            'type' => NotificationType::CollaborationReminder24h->value,
            'target_id' => $cancelled->id,
        ]);
    }

    /**
     * @return array{0: Profile, 1: Profile, 2: Application}
     */
    private function seedApplicationContext(): array
    {
        [$business, $community] = $this->seedProfiles();

        $opportunity = CollabOpportunity::factory()
            ->published()
            ->forCreator($business)
            ->create();

        $application = Application::factory()
            ->pending()
            ->forOpportunity($opportunity)
            ->forApplicant($community)
            ->create();

        return [$business, $community, $application];
    }

    /**
     * @return array{0: Profile, 1: Profile, 2: Collaboration}
     */
    private function seedCollaborationContext(): array
    {
        [$business, $community, $application] = $this->seedApplicationContext();

        $collaboration = Collaboration::factory()
            ->scheduled()
            ->scheduledOn(now()->addWeek()->toDateString())
            ->forCreator($business)
            ->forApplicant($community)
            ->forOpportunity($application->collabOpportunity)
            ->forApplication($application)
            ->create();

        return [$business, $community, $collaboration];
    }

    /**
     * @return array{0: Profile, 1: Profile}
     */
    private function seedProfiles(): array
    {
        $business = Profile::factory()->business()->create();
        BusinessProfile::factory()->create([
            'profile_id' => $business->id,
            'name' => 'Business Creator',
        ]);
        BusinessSubscription::factory()->active()->create([
            'profile_id' => $business->id,
        ]);

        $community = Profile::factory()->community()->create();
        CommunityProfile::factory()->create([
            'profile_id' => $community->id,
            'name' => 'Community Applicant',
        ]);

        return [$business, $community];
    }
}
