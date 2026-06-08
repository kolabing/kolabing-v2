<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs;

use App\Jobs\SendPostHogEvent;
use Mockery;
use PostHog\Client;
use PostHog\PostHog;
use Tests\TestCase;

class SendPostHogEventTest extends TestCase
{
    public function test_job_sends_capture_payload_to_posthog(): void
    {
        $client = Mockery::mock(Client::class);
        $client->shouldReceive('capture')
            ->once()
            ->ordered()
            ->with([
                'distinctId' => 'profile-123',
                'event' => 'user_registered',
                'properties' => [
                    'user_type' => 'business',
                    'environment' => 'testing',
                    'event_source' => 'backend',
                    'source' => 'backend',
                ],
            ])
            ->andReturnTrue();
        $client->shouldReceive('flush')
            ->once()
            ->ordered()
            ->andReturnTrue();

        PostHog::init(client: $client);

        config()->set('app.env', 'testing');
        config()->set('posthog.enabled', true);
        config()->set('posthog.project_api_key', 'phc_test');
        config()->set('posthog.host', 'https://eu.i.posthog.com');

        (new SendPostHogEvent(
            distinctId: 'profile-123',
            event: 'user_registered',
            properties: [
                'user_type' => 'business',
            ],
        ))->handle();
    }

    public function test_job_does_not_send_when_posthog_is_disabled(): void
    {
        $client = Mockery::mock(Client::class);
        $client->shouldNotReceive('capture');
        $client->shouldNotReceive('flush');

        PostHog::init(client: $client);

        config()->set('posthog.enabled', false);
        config()->set('posthog.project_api_key', 'phc_test');

        (new SendPostHogEvent(
            distinctId: 'profile-123',
            event: 'user_registered',
        ))->handle();
    }
}
