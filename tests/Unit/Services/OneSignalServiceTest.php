<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\OneSignalService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OneSignalServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_send_push_to_users_merges_only_allowlisted_message_options(): void
    {
        config()->set('services.onesignal.app_id', 'app-123');
        config()->set('services.onesignal.rest_api_key', 'key-123');
        config()->set('services.onesignal.base_url', 'https://onesignal.test');

        Http::fake([
            'https://onesignal.test/notifications' => Http::response(['id' => 'msg-123'], 200),
        ]);

        $service = app(OneSignalService::class);

        $service->sendPushToUsers(
            userIds: ['profile-123'],
            title: 'New Message',
            body: 'Hello there',
            data: [
                'type' => 'new_message',
                'deeplink' => '/application/application-123/chat',
            ],
            messageOptions: [
                'subtitle' => 'Messages',
                'ios_category' => 'kolabing_messages',
                'buttons' => [
                    ['id' => 'open_message_thread', 'text' => 'Open Chat'],
                ],
                'unsupported' => 'ignore-me',
            ],
        );

        Http::assertSent(function (Request $request): bool {
            return $request['subtitle'] === 'Messages'
                && $request['ios_category'] === 'kolabing_messages'
                && $request['buttons'][0]['id'] === 'open_message_thread'
                && ! array_key_exists('unsupported', $request->data())
                && $request['data']['type'] === 'new_message';
        });
    }
}
