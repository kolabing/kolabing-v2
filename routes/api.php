<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\LookupController;
use App\Http\Controllers\Api\V1\OnboardingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group.
|
*/

Route::prefix('v1')->group(function (): void {
    /*
    |--------------------------------------------------------------------------
    | Public Routes
    |--------------------------------------------------------------------------
    */

    // Authentication
    Route::post('auth/google', [AuthController::class, 'google'])
        ->name('api.v1.auth.google');

    // Lookups
    Route::get('cities', [LookupController::class, 'cities'])
        ->name('api.v1.cities');

    Route::get('lookup/business-types', [LookupController::class, 'businessTypes'])
        ->name('api.v1.lookup.business-types');

    Route::get('lookup/community-types', [LookupController::class, 'communityTypes'])
        ->name('api.v1.lookup.community-types');

    /*
    |--------------------------------------------------------------------------
    | Protected Routes
    |--------------------------------------------------------------------------
    */

    Route::middleware('auth:sanctum')->group(function (): void {
        // Authentication
        Route::get('auth/me', [AuthController::class, 'me'])
            ->name('api.v1.auth.me');

        Route::post('auth/logout', [AuthController::class, 'logout'])
            ->name('api.v1.auth.logout');

        // Onboarding - Business only
        Route::put('onboarding/business', [OnboardingController::class, 'business'])
            ->middleware('user_type:business')
            ->name('api.v1.onboarding.business');

        // Onboarding - Community only
        Route::put('onboarding/community', [OnboardingController::class, 'community'])
            ->middleware('user_type:community')
            ->name('api.v1.onboarding.community');
    });
});
