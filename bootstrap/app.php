<?php

use App\Http\Middleware\AddSecurityHeaders;
use App\Http\Middleware\CacheMarketingPage;
use App\Http\Middleware\CanonicalUrl;
use App\Http\Middleware\EnsureAdminUserIsMaintainer;
use App\Http\Middleware\EnsureFeatureEnabled;
use App\Http\Middleware\EnsureUserType;
use App\Http\Middleware\LogAuthTokenFirstUse;
use App\Http\Middleware\TouchProfileActivity;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;
use Sentry\Laravel\Integration;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    // Registers POST /broadcasting/auth (Sanctum-guarded) + loads routes/channels.php
    // so private chat channels (chat.thread.{id}, chat.application.{id}) can authorize
    // for Reverb real-time. Mobile clients send their Bearer token to this endpoint.
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
        ['middleware' => ['auth:sanctum']],
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Trust Laravel Cloud's edge/compute network as a proxy so isSecure()/getScheme()
        // (and everything downstream of it: the session/XSRF cookie Secure flag, CSRF checks,
        // URL generation) correctly see the original client's HTTPS request instead of the
        // plain-HTTP hop from Laravel Cloud's internal load balancer. `at: '*'` (not an IP
        // allowlist) is safe here because the app is only ever reachable through Laravel
        // Cloud's own edge + compute network, never directly from the public internet -- see
        // https://laravel.com/cloud/docs/network ("Your applications run in private networks
        // and are only made publicly accessible via the Cloud Edge Network"). Fixes
        // intermittent 419 (Page Expired) on /admin/login, diagnosed in #104.
        $middleware->trustProxies(at: '*');

        // One URL per page: fold www onto the apex host and drop trailing slashes
        // before anything else runs, so a duplicate never reaches a controller.
        $middleware->prepend(CanonicalUrl::class);

        $middleware->append(AddSecurityHeaders::class);

        // Register middleware aliases
        $middleware->alias([
            'abilities' => CheckAbilities::class,
            'ability' => CheckForAnyAbility::class,
            'log_auth_token_first_use' => LogAuthTokenFirstUse::class,
            'user_type' => EnsureUserType::class,
            // feature:{name} -> config("{name}.enabled"); 404s a flag that is off.
            'feature' => EnsureFeatureEnabled::class,
            'maintainer' => EnsureAdminUserIsMaintainer::class,
            'touch_profile_activity' => TouchProfileActivity::class,
            'cache_marketing' => CacheMarketingPage::class,
        ]);

        // Sanctum stateful domains for API
        $middleware->statefulApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        Integration::handles($exceptions);

        // Handle unauthenticated exceptions for API routes
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => __('Unauthenticated'),
                    'errors' => [
                        'auth' => [__('Authentication token is invalid or expired')],
                    ],
                ], 401);
            }
        });

        // Handle validation exceptions for API routes
        $exceptions->render(function (\Illuminate\Validation\ValidationException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => __('Validation failed'),
                    'errors' => $e->errors(),
                ], 422);
            }
        });

        // Handle model not found exceptions for API routes
        $exceptions->render(function (\Illuminate\Database\Eloquent\ModelNotFoundException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => __('Resource not found'),
                    'errors' => [
                        'resource' => [__('The requested resource was not found')],
                    ],
                ], 404);
            }
        });

        // Handle general exceptions for API routes
        $exceptions->render(function (\Throwable $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $statusCode = method_exists($e, 'getStatusCode')
                    ? $e->getStatusCode()
                    : 500;

                if ($statusCode === 500) {
                    return response()->json([
                        'success' => false,
                        'message' => __('Server error'),
                        'errors' => [
                            'server' => [__('An unexpected error occurred')],
                        ],
                    ], 500);
                }
            }
        });
    })->create();
