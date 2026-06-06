<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\UserType;
use App\Jobs\SendPostHogEvent;
use App\Models\Profile;
use App\Services\PostHog\PostHogService;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PostHogServiceTest extends TestCase
{
    public function test_capture_queues_event_when_posthog_is_enabled(): void
    {
        Queue::fake();
        config()->set('posthog.enabled', true);
        config()->set('posthog.project_api_key', 'phc_test');

        $service = app(PostHogService::class);

        $service->capture('profile-123', 'user_registered', [
            'user_type' => 'business',
        ]);

        Queue::assertPushed(SendPostHogEvent::class, function (SendPostHogEvent $job): bool {
            return $job->distinctId === 'profile-123'
                && $job->event === 'user_registered'
                && $job->properties['user_type'] === 'business';
        });
    }

    public function test_capture_does_not_queue_event_when_posthog_is_disabled(): void
    {
        Queue::fake();
        config()->set('posthog.enabled', false);
        config()->set('posthog.project_api_key', 'phc_test');

        app(PostHogService::class)->capture('profile-123', 'user_registered');

        Queue::assertNothingPushed();
    }

    public function test_capture_does_not_queue_event_without_project_api_key(): void
    {
        Queue::fake();
        config()->set('posthog.enabled', true);
        config()->set('posthog.project_api_key', null);

        app(PostHogService::class)->capture('profile-123', 'user_registered');

        Queue::assertNothingPushed();
    }

    public function test_capture_accepts_profile_and_adds_safe_profile_properties(): void
    {
        Queue::fake();
        config()->set('posthog.enabled', true);
        config()->set('posthog.project_api_key', 'phc_test');

        $profile = new Profile([
            'email' => 'business@example.com',
            'user_type' => UserType::Business,
        ]);
        $profile->id = 'profile-123';
        $profile->exists = true;

        app(PostHogService::class)->capture($profile, 'login_completed', [
            'method' => 'password',
        ]);

        Queue::assertPushed(SendPostHogEvent::class, function (SendPostHogEvent $job): bool {
            return $job->distinctId === 'profile-123'
                && $job->event === 'login_completed'
                && $job->properties['method'] === 'password'
                && $job->properties['user_id'] === 'profile-123'
                && $job->properties['user_type'] === 'business';
        });
    }

    public function test_capture_does_not_queue_event_when_profile_opted_out(): void
    {
        Queue::fake();
        config()->set('posthog.enabled', true);
        config()->set('posthog.project_api_key', 'phc_test');

        $profile = new Profile([
            'user_type' => UserType::Business,
            'analytics_opt_out' => true,
        ]);
        $profile->id = 'profile-123';
        $profile->exists = true;

        app(PostHogService::class)->capture($profile, 'login_completed');

        Queue::assertNothingPushed();
    }
}
