<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\NotificationType;
use App\Models\Notification;
use App\Models\Profile;
use App\Services\PushNotificationService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PushNotificationServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_new_message_push_includes_ios_rich_metadata_and_application_chat_deeplink(): void
    {
        config()->set('services.onesignal.app_id', 'app-123');
        config()->set('services.onesignal.rest_api_key', 'key-123');
        config()->set('services.onesignal.base_url', 'https://onesignal.test');

        Http::fake([
            'https://onesignal.test/notifications' => Http::response(['id' => 'msg-123'], 200),
        ]);

        $recipient = Profile::factory()->business()->create();
        Notification::factory()->count(7)->forProfile($recipient)->unread()->create();

        $service = app(PushNotificationService::class);

        $service->send(
            $recipient,
            'Finish your Kolab',
            'Draft reminder',
            NotificationType::NewMessage,
            'application-123',
        );

        Http::assertSent(function (Request $request): bool {
            return $request['data']['type'] === NotificationType::NewMessage->value
                && $request['subtitle'] === ['en' => 'Messages']
                && $request['ios_category'] === 'kolabing_messages'
                && $request['ios_interruption_level'] === 'active'
                && $request['ios_badgeType'] === 'SetTo'
                && $request['ios_badgeCount'] === 7
                && $request['data']['deeplink'] === '/application/application-123/chat'
                && $request['data']['thread_id'] === 'messages_application-123'
                && $request['data']['badge'] === 7
                && $request['buttons'][0]['id'] === 'open_message_thread'
                && $request['buttons'][1]['id'] === 'open_notifications'
                && $request['data']['action_deeplinks']['open_message_thread'] === '/application/application-123/chat'
                && $request['data']['action_deeplinks']['open_notifications'] === '/notifications';
        });
    }

    public function test_application_received_push_includes_application_category_and_actions(): void
    {
        config()->set('services.onesignal.app_id', 'app-123');
        config()->set('services.onesignal.rest_api_key', 'key-123');
        config()->set('services.onesignal.base_url', 'https://onesignal.test');

        Http::fake([
            'https://onesignal.test/notifications' => Http::response(['id' => 'msg-456'], 200),
        ]);

        $recipient = Profile::factory()->business()->create();
        $service = app(PushNotificationService::class);

        $service->send(
            $recipient,
            'New Application',
            'A new application is waiting.',
            NotificationType::ApplicationReceived,
            'application-456',
        );

        Http::assertSent(function (Request $request): bool {
            return $request['data']['type'] === NotificationType::ApplicationReceived->value
                && $request['subtitle'] === ['en' => 'New application']
                && $request['ios_category'] === 'kolabing_applications'
                && $request['ios_interruption_level'] === 'active'
                && $request['data']['deeplink'] === '/application/application-456'
                && $request['data']['thread_id'] === 'applications_application-456'
                && $request['buttons'][0]['id'] === 'view_application'
                && $request['buttons'][1]['id'] === 'open_notifications'
                && $request['data']['action_deeplinks']['view_application'] === '/application/application-456';
        });
    }

    public function test_send_builds_expected_deeplinks_for_reminder_notifications(): void
    {
        config()->set('services.onesignal.app_id', 'app-123');
        config()->set('services.onesignal.rest_api_key', 'key-123');
        config()->set('services.onesignal.base_url', 'https://onesignal.test');

        Http::fake([
            'https://onesignal.test/notifications' => Http::response(['id' => 'msg-123'], 200),
        ]);

        $recipient = Profile::factory()->business()->create();
        $service = app(PushNotificationService::class);

        $service->send(
            $recipient,
            'Finish your Kolab',
            'Draft reminder',
            NotificationType::KolabCreateIncomplete,
            'kolab-123',
        );

        $service->send(
            $recipient,
            'You have a pending application',
            'Application reminder',
            NotificationType::ApplicationPending,
            'application-123',
        );

        $service->send(
            $recipient,
            'You have an unread message',
            'Message reminder',
            NotificationType::UnreadMessage,
            'application-999',
        );

        Http::assertSent(function (Request $request): bool {
            return $request['data']['type'] === NotificationType::KolabCreateIncomplete->value
                && $request['data']['deeplink'] === '/kolabs/kolab-123/edit';
        });

        Http::assertSent(function (Request $request): bool {
            return $request['data']['type'] === NotificationType::ApplicationPending->value
                && $request['data']['deeplink'] === '/application/application-123';
        });

        Http::assertSent(function (Request $request): bool {
            return $request['data']['type'] === NotificationType::UnreadMessage->value
                && $request['data']['deeplink'] === '/application/application-999/chat';
        });
    }
}
