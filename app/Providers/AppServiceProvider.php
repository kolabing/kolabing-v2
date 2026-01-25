<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Application;
use App\Models\CollabOpportunity;
use App\Models\Collaboration;
use App\Policies\ApplicationPolicy;
use App\Policies\CollaborationPolicy;
use App\Policies\OpportunityPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerPolicies();
    }

    /**
     * Register the application's policies.
     */
    private function registerPolicies(): void
    {
        Gate::policy(CollabOpportunity::class, OpportunityPolicy::class);
        Gate::policy(Application::class, ApplicationPolicy::class);
        Gate::policy(Collaboration::class, CollaborationPolicy::class);
    }
}
