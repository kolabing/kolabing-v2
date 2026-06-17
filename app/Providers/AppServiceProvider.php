<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Application;
use App\Models\Collaboration;
use App\Models\Community;
use App\Models\Kolab;
use App\Policies\ApplicationPolicy;
use App\Policies\CollaborationPolicy;
use App\Policies\CommunityPolicy;
use App\Policies\KolabPolicy;
use App\Services\PostmarkClient;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use PostHog\PostHog;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(PostmarkClient::class, function ($app): PostmarkClient {
            $config = $app['config'];

            return new PostmarkClient(
                token: $config->get('services.postmark.key'),
                from: $config->get('services.postmark.from', 'hello@kolabing.com'),
                fromName: $config->get('services.postmark.from_name', 'Kolabing'),
                messageStream: $config->get('services.postmark.message_stream', 'outbound'),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerPolicies();
        $this->initializePostHog();
        $this->configurePasswordReset();
    }

    /**
     * Configure the password reset URL for the mobile app.
     */
    private function configurePasswordReset(): void
    {
        ResetPassword::createUrlUsing(function (mixed $notifiable, string $token): string {
            return config('app.url').'/reset-password?token='.$token.'&email='.urlencode($notifiable->getEmailForPasswordReset());
        });
    }

    /**
     * Register the application's policies.
     */
    private function registerPolicies(): void
    {
        Gate::policy(Application::class, ApplicationPolicy::class);
        Gate::policy(Collaboration::class, CollaborationPolicy::class);
        Gate::policy(Kolab::class, KolabPolicy::class);
        Gate::policy(Community::class, CommunityPolicy::class);
    }

    private function initializePostHog(): void
    {
        if (! config('posthog.enabled')) {
            return;
        }

        $apiKey = config('posthog.api_key') ?: config('posthog.project_api_key');

        if (blank($apiKey)) {
            return;
        }

        PostHog::init($apiKey, [
            'host' => config('posthog.host', 'https://eu.i.posthog.com'),
        ]);
    }
}
