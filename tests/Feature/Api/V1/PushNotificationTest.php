<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\NotificationType;
use App\Jobs\SendPushNotification;
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
    }

    public function test_push_job_dispatched_when_recipient_has_device_token(): void
    {
        Queue::fake();

        $recipient = Profile::factory()->business()->create([
            'device_token' => 'fcm-token-abc123',
            'device_platform' => 'ios',
        ]);

        $actor = Profile::factory()->community()->create();

        $this->notificationService->createNotification(
            recipient: $recipient,
            type: NotificationType::ApplicationReceived,
            title: 'New Application',
            body: 'Someone applied to your opportunity.',
            actor: $actor,
            targetId: 'application-uuid',
            targetType: 'application',
        );

        Queue::assertPushed(SendPushNotification::class, function (SendPushNotification $job) use ($recipient): bool {
            return $job->recipient->id === $recipient->id
                && $job->title === 'New Application'
                && $job->body === 'Someone applied to your opportunity.'
                && $job->type === NotificationType::ApplicationReceived
                && $job->targetId === 'application-uuid';
        });
    }

    public function test_push_job_not_dispatched_when_recipient_has_no_device_token(): void
    {
        Queue::fake();

        $recipient = Profile::factory()->business()->create([
            'device_token' => null,
        ]);

        $this->notificationService->createNotification(
            recipient: $recipient,
            type: NotificationType::ApplicationReceived,
            title: 'New Application',
            body: 'Someone applied.',
        );

        Queue::assertNotPushed(SendPushNotification::class);
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
        $recipient = Profile::factory()->business()->create();
        $job = new SendPushNotification(
            recipient: $recipient,
            title: 'Test',
            body: 'Test body',
            type: NotificationType::NewMessage,
        );

        $this->assertSame(3, $job->tries);
        $this->assertSame(10, $job->backoff);
    }
}
