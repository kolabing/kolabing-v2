<?php

use App\Http\Middleware\EnsureUserType;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Register middleware aliases
        $middleware->alias([
            'abilities' => CheckAbilities::class,
            'ability' => CheckForAnyAbility::class,
            'user_type' => EnsureUserType::class,
        ]);

        // Sanctum stateful domains for API
        $middleware->statefulApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
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
