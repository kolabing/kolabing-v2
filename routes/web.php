<?php

use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Admin\BadgeController as AdminBadgeController;
use App\Http\Controllers\Admin\ChallengeController as AdminChallengeController;
use App\Http\Controllers\Admin\ChallengeDefaultsController as AdminChallengeDefaultsController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\KolabController as AdminKolabController;
use App\Http\Controllers\Admin\ManagedUserController;
use App\Http\Controllers\Admin\StatsController as AdminStatsController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::middleware('guest:admin')->group(function (): void {
    Route::get('/admin/login', [AdminAuthController::class, 'create'])->name('login');
    Route::post('/admin/login', [AdminAuthController::class, 'store']);
});

Route::middleware(['auth:admin', 'maintainer'])->prefix('admin')->as('admin.')->group(function (): void {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::post('/logout', [AdminAuthController::class, 'destroy'])->name('logout');

    Route::get('/users', [ManagedUserController::class, 'index'])->name('users.index');
    Route::get('/users/create', [ManagedUserController::class, 'create'])->name('users.create');
    Route::post('/users', [ManagedUserController::class, 'store'])->name('users.store');
    Route::get('/users/{profile}/edit', [ManagedUserController::class, 'edit'])->name('users.edit');
    Route::put('/users/{profile}', [ManagedUserController::class, 'update'])->name('users.update');
    Route::delete('/users/{profile}', [ManagedUserController::class, 'destroy'])->name('users.destroy');
    Route::post('/users/{profile}/subscription/grant', [ManagedUserController::class, 'grantSubscription'])->name('users.subscription.grant');
    Route::post('/users/{profile}/subscription/revoke', [ManagedUserController::class, 'revokeSubscription'])->name('users.subscription.revoke');

    Route::get('/kolabs', [AdminKolabController::class, 'index'])->name('kolabs.index');
    Route::get('/kolabs/{kolab}/edit', [AdminKolabController::class, 'edit'])->name('kolabs.edit');
    Route::put('/kolabs/{kolab}', [AdminKolabController::class, 'update'])->name('kolabs.update');
    Route::delete('/kolabs/{kolab}', [AdminKolabController::class, 'destroy'])->name('kolabs.destroy');
    Route::post('/kolabs/{kolab}/collaboration/cancel', [AdminKolabController::class, 'cancelCollaboration'])->name('kolabs.collaboration.cancel');
    Route::post('/kolabs/{kolab}/collaboration/complete', [AdminKolabController::class, 'completeCollaboration'])->name('kolabs.collaboration.complete');

    Route::get('/stats', [AdminStatsController::class, 'index'])->name('stats.index');

    Route::prefix('gamification')->as('gamification.')->group(function (): void {
        Route::get('/challenges/defaults', [AdminChallengeDefaultsController::class, 'index'])->name('challenges.defaults.index');
        Route::put('/challenges/defaults', [AdminChallengeDefaultsController::class, 'update'])->name('challenges.defaults.update');

        Route::get('/challenges', [AdminChallengeController::class, 'index'])->name('challenges.index');
        Route::get('/challenges/create', [AdminChallengeController::class, 'create'])->name('challenges.create');
        Route::post('/challenges', [AdminChallengeController::class, 'store'])->name('challenges.store');
        Route::get('/challenges/{challenge}/edit', [AdminChallengeController::class, 'edit'])->name('challenges.edit');
        Route::put('/challenges/{challenge}', [AdminChallengeController::class, 'update'])->name('challenges.update');
        Route::delete('/challenges/{challenge}', [AdminChallengeController::class, 'destroy'])->name('challenges.destroy');

        Route::get('/badges', [AdminBadgeController::class, 'index'])->name('badges.index');
        Route::get('/badges/{badge}/edit', [AdminBadgeController::class, 'edit'])->name('badges.edit');
        Route::put('/badges/{badge}', [AdminBadgeController::class, 'update'])->name('badges.update');
    });
});

Route::view('/for-businesses', 'pages.for-businesses')->name('for-businesses');
Route::view('/for-communities', 'pages.for-communities')->name('for-communities');
Route::view('/support', 'pages.support')->name('support');
Route::view('/careers', 'pages.careers')->name('careers');
Route::view('/privacy', 'pages.privacy')->name('privacy');
Route::view('/terms', 'pages.terms')->name('terms');

Route::get('/sitemap.xml', function () {
    $urls = [
        route('home'),
        route('for-businesses'),
        route('for-communities'),
        route('support'),
        route('careers'),
        route('privacy'),
        route('terms'),
    ];

    return response()->view('sitemap', [
        'urls' => $urls,
        'lastModified' => now()->toDateString(),
    ])->header('Content-Type', 'application/xml; charset=UTF-8');
})->name('sitemap');

Route::get('/llms.txt', function () {
    $content = implode("\n", [
        '# Kolabing',
        '',
        'Kolabing is a collaboration platform that helps local businesses and community groups launch in-person partnerships, events, and repeatable growth campaigns.',
        '',
        'Preferred pages:',
        '- Home: '.route('home'),
        '- For businesses: '.route('for-businesses'),
        '- For communities: '.route('for-communities'),
        '- Support: '.route('support'),
        '- Privacy: '.route('privacy'),
        '- Terms: '.route('terms'),
        '',
        'Contact: support@kolabing.com',
    ]);

    return response($content, 200)->header('Content-Type', 'text/plain; charset=UTF-8');
})->name('llms');

Route::get('/.well-known/security.txt', function () {
    $content = implode("\n", [
        'Contact: mailto:support@kolabing.com',
        'Expires: 2027-04-21T23:59:59.000Z',
        'Preferred-Languages: en',
        'Canonical: https://kolabing.com/.well-known/security.txt',
        'Policy: https://kolabing.com/privacy',
    ]);

    return response($content, 200)->header('Content-Type', 'text/plain; charset=UTF-8');
})->name('security.txt');
