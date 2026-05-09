<?php

declare(strict_types=1);

namespace Tests\Unit\Notifications;

use App\Jobs\Notifications\SendPushNotificationJob;
use App\Models\DeviceToken;
use App\Models\Notification;
use App\Models\NotificationDelivery;
use App\Models\Profile;
use App\Services\Notifications\DeviceTokenService;
use App\Services\Notifications\FcmClient;
use App\Services\Notifications\NotificationPayloadFactory;
use App\Support\Notifications\NotificationMetrics;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class SendPushNotificationJobTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_job_sends_to_all_active_tokens_and_records_deliveries(): void
    {
        $profile = Profile::factory()->business()->create();
        $notification = Notification::factory()->forProfile($profile)->create([
            'title' => 'New application received',
            'body' => 'A creator applied to your kolab.',
            'is_push' => true,
            'target_id' => 'application-uuid',
            'target_type' => 'application',
            'deeplink' => '/application/application-uuid',
        ]);

        $tokenA = DeviceToken::factory()->create([
            'profile_id' => $profile->id,
            'token' => 'token-a',
        ]);
        $tokenB = DeviceToken::factory()->create([
            'profile_id' => $profile->id,
            'token' => 'token-b',
        ]);

        $fcmClient = Mockery::mock(FcmClient::class);
        $fcmClient->shouldReceive('send')->once()->with(
            Mockery::on(fn (DeviceToken $token): bool => $token->is($tokenA)),
            'New application received',
            'A creator applied to your kolab.',
            Mockery::type('array'),
        )->andReturn('provider-message-a');
        $fcmClient->shouldReceive('send')->once()->with(
            Mockery::on(fn (DeviceToken $token): bool => $token->is($tokenB)),
            'New application received',
            'A creator applied to your kolab.',
            Mockery::type('array'),
        )->andReturn('provider-message-b');
        $fcmClient->shouldReceive('isInvalidToken')->never();

        $job = new SendPushNotificationJob($notification->id);
        $job->handle(
            $fcmClient,
            app(NotificationPayloadFactory::class),
            app(DeviceTokenService::class),
            app(NotificationMetrics::class),
        );

        $this->assertDatabaseHas('notification_deliveries', [
            'notification_id' => $notification->id,
            'device_token_id' => $tokenA->id,
            'status' => 'sent',
            'provider_message_id' => 'provider-message-a',
        ]);

        $this->assertDatabaseHas('notification_deliveries', [
            'notification_id' => $notification->id,
            'device_token_id' => $tokenB->id,
            'status' => 'sent',
            'provider_message_id' => 'provider-message-b',
        ]);

        $this->assertEquals(2, NotificationDelivery::query()->count());
        $this->assertNotNull($tokenA->fresh()->last_delivered_at);
        $this->assertNotNull($tokenB->fresh()->last_delivered_at);
    }

    public function test_job_deactivates_invalid_tokens(): void
    {
        $profile = Profile::factory()->business()->create();
        $notification = Notification::factory()->forProfile($profile)->create([
            'is_push' => true,
        ]);
        $deviceToken = DeviceToken::factory()->create([
            'profile_id' => $profile->id,
            'token' => 'dead-token',
        ]);

        $exception = new RuntimeException('UNREGISTERED');

        $fcmClient = Mockery::mock(FcmClient::class);
        $fcmClient->shouldReceive('send')->once()->andThrow($exception);
        $fcmClient->shouldReceive('isInvalidToken')->once()->with($exception)->andReturn(true);

        $job = new SendPushNotificationJob($notification->id);
        $job->handle(
            $fcmClient,
            app(NotificationPayloadFactory::class),
            app(DeviceTokenService::class),
            app(NotificationMetrics::class),
        );

        $this->assertDatabaseHas('notification_deliveries', [
            'notification_id' => $notification->id,
            'device_token_id' => $deviceToken->id,
            'status' => 'invalid_token',
            'last_error_code' => 'invalid_token',
        ]);

        $deviceToken->refresh();
        $this->assertFalse($deviceToken->is_active);
        $this->assertSame('invalid_token', $deviceToken->invalid_reason);
    }

    public function test_job_marks_transient_failures_and_rethrows_for_retry(): void
    {
        $profile = Profile::factory()->business()->create();
        $notification = Notification::factory()->forProfile($profile)->create([
            'is_push' => true,
        ]);
        $deviceToken = DeviceToken::factory()->create([
            'profile_id' => $profile->id,
            'token' => 'flaky-token',
        ]);

        $exception = new RuntimeException('temporary fcm outage');

        $fcmClient = Mockery::mock(FcmClient::class);
        $fcmClient->shouldReceive('send')->once()->andThrow($exception);
        $fcmClient->shouldReceive('isInvalidToken')->once()->with($exception)->andReturn(false);

        $job = new SendPushNotificationJob($notification->id);

        try {
            $job->handle(
                $fcmClient,
                app(NotificationPayloadFactory::class),
                app(DeviceTokenService::class),
                app(NotificationMetrics::class),
            );

            $this->fail('Expected transient push delivery failure to be rethrown.');
        } catch (RuntimeException $caught) {
            $this->assertSame('temporary fcm outage', $caught->getMessage());
        }

        $this->assertDatabaseHas('notification_deliveries', [
            'notification_id' => $notification->id,
            'device_token_id' => $deviceToken->id,
            'status' => 'failed',
        ]);
    }
}
