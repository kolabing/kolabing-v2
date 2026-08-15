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
use App\Services\Admin\CompanySettingService;
use App\Services\PostmarkClient;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
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
        $this->shareLegalPageData();
        $this->shareWebappLocaleData();
    }

    /**
     * Share locale helpers with every Kolabing Web App view: the current-locale
     * base path (''/'/es'/'/ca') for building locale-aware links, plus the absolute
     * per-locale URLs (hreflang/canonical) and relative paths (language switcher)
     * for the current page.
     */
    private function shareWebappLocaleData(): void
    {
        View::composer('webapp.*', function ($view): void {
            $loc = app()->getLocale();
            $default = (string) config('webapp.default_locale', 'en');
            $all = (array) config('webapp.locales', ['en']);
            $prefixed = (array) config('webapp.prefixed_locales', []);

            $rawPath = ltrim(request()->path(), '/');
            foreach ($prefixed as $pl) {
                if ($rawPath === $pl) {
                    $rawPath = '';
                    break;
                }
                if (str_starts_with($rawPath, $pl.'/')) {
                    $rawPath = substr($rawPath, strlen($pl) + 1);
                    break;
                }
            }
            $suffix = $rawPath === '' ? '/' : '/'.$rawPath;
            $origin = 'https://'.config('webapp.host');

            $paths = [];
            $urls = [];
            foreach ($all as $l) {
                $lb = $l === $default ? '' : '/'.$l;
                $paths[$l] = $lb.($suffix === '/' ? '/' : $suffix);
                $urls[$l] = $origin.$paths[$l];
            }

            $view->with([
                'loc' => $loc,
                'defaultLocale' => $default,
                'base' => $loc === $default ? '' : '/'.$loc,
                'allLocales' => $all,
                'localeUrls' => $urls,
                'localePaths' => $paths,
            ]);
        });
    }

    /**
     * Share the admin-managed company / legal details with the public Terms of
     * Service + Privacy Policy pages so their placeholders render live values.
     */
    private function shareLegalPageData(): void
    {
        View::composer(
            ['pages.terms', 'pages.privacy', 'pages.es.terms', 'pages.es.privacy'],
            function ($view): void {
                $view->with('company', app(CompanySettingService::class)->current());
            },
        );
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
