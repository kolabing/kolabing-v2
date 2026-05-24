<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\IntentType;
use App\Enums\NotificationType;
use App\Jobs\SendPushNotification;
use App\Models\Application;
use App\Models\BusinessProfile;
use App\Models\CollabOpportunity;
use App\Models\CommunityProfile;
use App\Models\Notification;
use App\Models\Profile;
use App\Services\ApplicationService;
use App\Services\ChatService;
use App\Services\KolabService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NotificationReminderTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_draft_kolab_reminder_resets_after_update_and_stops_after_publish(): void
    {
        Queue::fake();

        $creator = Profile::factory()->community()->create();
        CommunityProfile::factory()->create(['profile_id' => $creator->id]);

        $kolabService = app(KolabService::class);

        $kolab = $kolabService->create($creator, $this->communitySeekingKolabPayload());

        $this->assertDatabaseHas('notification_reminders', [
            'profile_id' => $creator->id,
            'type' => NotificationType::KolabCreateIncomplete->value,
            'entity_id' => $kolab->id,
            'entity_type' => 'kolab',
            'cancelled_at' => null,
        ]);

        $this->travel(90)->minutes();
        $kolabService->update($kolab->fresh(), [
            'title' => 'Updated draft title',
        ]);

        $this->travel(30)->minutes();
        $this->artisan('notifications:send-reminders')->assertExitCode(0);

        $this->assertSame(0, Notification::query()
            ->where('profile_id', $creator->id)
            ->where('type', NotificationType::KolabCreateIncomplete)
            ->count());

        $this->travel(90)->minutes();
        $this->artisan('notifications:send-reminders')->assertExitCode(0);

        $this->assertDatabaseHas('notifications', [
            'profile_id' => $creator->id,
            'type' => NotificationType::KolabCreateIncomplete->value,
            'title' => 'Finish your Kolab',
            'body' => "Your Kolab is still in draft. Complete it and publish when you're ready.",
            'target_id' => $kolab->id,
            'target_type' => 'kolab',
        ]);

        Queue::assertPushed(SendPushNotification::class, function (SendPushNotification $job) use ($creator, $kolab): bool {
            return $job->recipient->id === $creator->id
                && $job->type === NotificationType::KolabCreateIncomplete
                && $job->targetId === $kolab->id;
        });

        $kolabService->publish($kolab->fresh());

        $this->travel(24)->hours();
        $this->artisan('notifications:send-reminders')->assertExitCode(0);

        $this->assertSame(1, Notification::query()
            ->where('profile_id', $creator->id)
            ->where('type', NotificationType::KolabCreateIncomplete)
            ->count());
    }

    public function test_pending_application_reminder_sends_once_and_stops_after_decline(): void
    {
        Queue::fake();

        $creator = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $creator->id]);

        $applicant = Profile::factory()->community()->create();
        CommunityProfile::factory()->create(['profile_id' => $applicant->id]);

        $opportunity = CollabOpportunity::factory()
            ->published()
            ->forCreator($creator)
            ->create();

        $applicationService = app(ApplicationService::class);
        $application = $applicationService->apply($applicant, $opportunity, [
            'message' => 'Interested in collaborating.',
            'availability' => 'Weekdays after 18:00',
        ]);

        $this->assertDatabaseHas('notification_reminders', [
            'profile_id' => $creator->id,
            'type' => NotificationType::ApplicationPending->value,
            'entity_id' => $application->id,
            'entity_type' => 'application',
            'cancelled_at' => null,
        ]);

        $this->travel(2)->hours();
        $this->artisan('notifications:send-reminders')->assertExitCode(0);

        $this->assertDatabaseHas('notifications', [
            'profile_id' => $creator->id,
            'type' => NotificationType::ApplicationPending->value,
            'title' => 'You have a pending application',
            'body' => 'A new application is waiting for your review. Open it to accept or decline.',
            'target_id' => $application->id,
            'target_type' => 'application',
        ]);

        $applicationService->decline($application->fresh());

        $this->travel(24)->hours();
        $this->artisan('notifications:send-reminders')->assertExitCode(0);

        $this->assertSame(1, Notification::query()
            ->where('profile_id', $creator->id)
            ->where('type', NotificationType::ApplicationPending)
            ->count());
    }

    public function test_unread_message_reminder_sends_once_and_stops_after_read(): void
    {
        Queue::fake();

        ['creator' => $creator, 'applicant' => $applicant, 'application' => $application] = $this->createConversation();

        $chatService = app(ChatService::class);

        $chatService->sendMessage($applicant, $application, [
            'content' => 'Checking in about this Kolab.',
        ]);

        $this->assertDatabaseHas('notification_reminders', [
            'profile_id' => $creator->id,
            'type' => NotificationType::UnreadMessage->value,
            'entity_id' => $application->id,
            'entity_type' => 'application',
            'cancelled_at' => null,
        ]);

        $this->travel(2)->hours();
        $this->artisan('notifications:send-reminders')->assertExitCode(0);

        $this->assertDatabaseHas('notifications', [
            'profile_id' => $creator->id,
            'type' => NotificationType::UnreadMessage->value,
            'title' => 'You have an unread message',
            'body' => 'Someone sent you a message about your Kolab. Open the chat to reply.',
            'target_id' => $application->id,
            'target_type' => 'application',
        ]);

        $chatService->markMessagesAsRead($creator, $application->fresh());

        $this->travel(24)->hours();
        $this->artisan('notifications:send-reminders')->assertExitCode(0);

        $this->assertSame(1, Notification::query()
            ->where('profile_id', $creator->id)
            ->where('type', NotificationType::UnreadMessage)
            ->count());
    }

    /**
     * @return array{intent_type: string, title: string, description: string, preferred_city: string, needs: array<int, string>, community_types: array<int, string>, community_size: int, typical_attendance: int, offers_in_return: array<int, string>, venue_preference: string}
     */
    private function communitySeekingKolabPayload(): array
    {
        return [
            'intent_type' => IntentType::CommunitySeeking->value,
            'title' => 'Sunset wellness meetup',
            'description' => 'Looking for a local venue partner for a small community wellness event.',
            'preferred_city' => 'Barcelona',
            'needs' => ['venue', 'food_drink'],
            'community_types' => ['wellness'],
            'community_size' => 300,
            'typical_attendance' => 40,
            'offers_in_return' => ['social_media', 'community_reach'],
            'venue_preference' => 'no_venue',
        ];
    }

    /**
     * @return array{creator: Profile, applicant: Profile, application: Application}
     */
    private function createConversation(): array
    {
        $creator = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $creator->id]);

        $applicant = Profile::factory()->community()->create();
        CommunityProfile::factory()->create(['profile_id' => $applicant->id]);

        $opportunity = CollabOpportunity::factory()
            ->published()
            ->forCreator($creator)
            ->create();

        $application = Application::factory()
            ->forOpportunity($opportunity)
            ->forApplicant($applicant)
            ->create();

        return [
            'creator' => $creator,
            'applicant' => $applicant,
            'application' => $application,
        ];
    }
}
