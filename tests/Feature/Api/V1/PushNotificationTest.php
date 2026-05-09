<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\NotificationType;
use App\Jobs\Notifications\SendPushNotificationJob;
use App\Models\DeviceToken;
use App\Models\Profile;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PushNotificationTest extends TestCase
{
    use LazilyRefreshDatabase;

    private NotificationService $notificationService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->notificationService = app(NotificationService::class);
        config(['notifications.push_delivery_enabled' => true]);
    }

    public function test_push_job_dispatched_when_recipient_has_active_device_token(): void
    {
        Queue::fake();

        $recipient = Profile::factory()->business()->create();
        DeviceToken::factory()->create([
            'profile_id' => $recipient->id,
            'token' => 'fcm-token-abc123',
            'platform' => 'ios',
        ]);

        $actor = Profile::factory()->community()->create();

        $notification = $this->notificationService->createNotification(
            recipient: $recipient,
            type: NotificationType::ApplicationReceived,
            title: 'New application received',
            body: 'Someone applied to your opportunity.',
            actor: $actor,
            targetId: 'application-uuid',
            targetType: 'application',
            dedupeKey: 'application_received:application-uuid',
        );

        Queue::assertPushed(SendPushNotificationJob::class, function (SendPushNotificationJob $job) use ($notification): bool {
            return $job->notificationId === $notification->id;
        });
    }

    public function test_push_job_not_dispatched_when_recipient_has_no_active_device_tokens(): void
    {
        Queue::fake();

        $recipient = Profile::factory()->business()->create([
            'device_token' => null,
        ]);

        $this->notificationService->createNotification(
            recipient: $recipient,
            type: NotificationType::ApplicationReceived,
            title: 'New application received',
            body: 'Someone applied.',
        );

        Queue::assertNotPushed(SendPushNotificationJob::class);
    }

    public function test_notification_db_record_created_regardless_of_device_token(): void
    {
        Queue::fake();

        $recipient = Profile::factory()->business()->create([
            'device_token' => null,
        ]);

        $notification = $this->notificationService->createNotification(
            recipient: $recipient,
            type: NotificationType::NewMessage,
            title: 'New Message',
            body: 'Hello there!',
            targetId: 'some-application-id',
            targetType: 'application',
            dedupeKey: 'message:message-uuid',
        );

        $this->assertDatabaseHas('notifications', [
            'id' => $notification->id,
            'profile_id' => $recipient->id,
            'title' => 'New Message',
            'body' => 'Hello there!',
            'target_id' => 'some-application-id',
        ]);
    }

    public function test_send_push_notification_job_has_correct_retry_config(): void
    {
        $job = new SendPushNotificationJob('notification-uuid');

        $this->assertSame(3, $job->tries);
        $this->assertSame([10, 30, 90], $job->backoff());
    }
}
