<?php

use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Admin\BadgeController as AdminBadgeController;
use App\Http\Controllers\Admin\ChallengeController as AdminChallengeController;
use App\Http\Controllers\Admin\ChallengeDefaultsController as AdminChallengeDefaultsController;
use App\Http\Controllers\Admin\CrmController as AdminCrmController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\IconLibraryController as AdminIconLibraryController;
use App\Http\Controllers\Admin\KolabController as AdminKolabController;
use App\Http\Controllers\Admin\ManagedUserController;
use App\Http\Controllers\Admin\OfferOptionController as AdminOfferOptionController;
use App\Http\Controllers\Admin\RewardEconomicsController as AdminRewardEconomicsController;
use App\Http\Controllers\Admin\StatsController as AdminStatsController;
use App\Http\Controllers\Admin\TaskController as AdminTaskController;
use App\Http\Controllers\Admin\TypeController as AdminTypeController;
use App\Http\Controllers\Admin\XpEarnRuleController as AdminXpEarnRuleController;
use App\Http\Controllers\Admin\XpLevelController as AdminXpLevelController;
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

    // CRM (businesses / communities / ambassadors) + Tasks
    Route::get('/crm', [AdminCrmController::class, 'index'])->name('crm.index');
    Route::post('/crm/columns', [AdminCrmController::class, 'saveColumns'])->name('crm.columns');
    Route::get('/crm/create', [AdminCrmController::class, 'create'])->name('crm.create');
    Route::post('/crm', [AdminCrmController::class, 'store'])->name('crm.store');
    Route::get('/crm/{account}/edit', [AdminCrmController::class, 'edit'])->name('crm.edit');
    Route::put('/crm/{account}', [AdminCrmController::class, 'update'])->name('crm.update');
    Route::delete('/crm/{account}', [AdminCrmController::class, 'destroy'])->name('crm.destroy');

    Route::get('/tasks', [AdminTaskController::class, 'index'])->name('tasks.index');
    Route::get('/tasks/create', [AdminTaskController::class, 'create'])->name('tasks.create');
    Route::post('/tasks', [AdminTaskController::class, 'store'])->name('tasks.store');
    Route::get('/tasks/{task}/edit', [AdminTaskController::class, 'edit'])->name('tasks.edit');
    Route::put('/tasks/{task}', [AdminTaskController::class, 'update'])->name('tasks.update');
    Route::delete('/tasks/{task}', [AdminTaskController::class, 'destroy'])->name('tasks.destroy');

    // Kolab offer taxonomies (offering / deliverable / need / product_type / venue_type)
    // — source of truth the app reads via /lookup/{offerings,deliverables,needs,
    // product-types,venue-types}.
    Route::get('/offer-options', [AdminOfferOptionController::class, 'index'])->name('offer-options.index');
    Route::get('/offer-options/create', [AdminOfferOptionController::class, 'create'])->name('offer-options.create');
    Route::post('/offer-options', [AdminOfferOptionController::class, 'store'])->name('offer-options.store');
    Route::get('/offer-options/{kind}/{id}/edit', [AdminOfferOptionController::class, 'edit'])->name('offer-options.edit');
    Route::put('/offer-options/{kind}/{id}', [AdminOfferOptionController::class, 'update'])->name('offer-options.update');
    Route::delete('/offer-options/{kind}/{id}', [AdminOfferOptionController::class, 'destroy'])->name('offer-options.destroy');
    Route::post('/offer-options/{kind}/{id}/toggle', [AdminOfferOptionController::class, 'toggle'])->name('offer-options.toggle');
    Route::post('/offer-options/{kind}/reorder', [AdminOfferOptionController::class, 'reorder'])->name('offer-options.reorder');

    // Business / community type taxonomies — the lists the app shows (and the
    // business onboarding filters by via applies_to). Source of truth the app reads
    // via /lookup/{business-types,community-types}.
    Route::get('/types', [AdminTypeController::class, 'index'])->name('types.index');
    Route::get('/types/create', [AdminTypeController::class, 'create'])->name('types.create');
    Route::post('/types', [AdminTypeController::class, 'store'])->name('types.store');
    Route::get('/types/{kind}/{id}/edit', [AdminTypeController::class, 'edit'])->name('types.edit');
    Route::put('/types/{kind}/{id}', [AdminTypeController::class, 'update'])->name('types.update');
    Route::delete('/types/{kind}/{id}', [AdminTypeController::class, 'destroy'])->name('types.destroy');
    Route::post('/types/{kind}/{id}/toggle', [AdminTypeController::class, 'toggle'])->name('types.toggle');
    Route::post('/types/{kind}/reorder', [AdminTypeController::class, 'reorder'])->name('types.reorder');

    // Personalised-icon library — the SVGs the mobile app renders. Offer options and
    // any other taxonomy pick from here; admins can upload new SVGs.
    Route::get('/icons', [AdminIconLibraryController::class, 'index'])->name('icons.index');
    Route::post('/icons', [AdminIconLibraryController::class, 'store'])->name('icons.store');
    Route::delete('/icons/{icon}', [AdminIconLibraryController::class, 'destroy'])->name('icons.destroy');

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
        Route::get('/badges/system-b/{slug}/edit', [AdminBadgeController::class, 'editSystemB'])->name('badges.system-b.edit');
        Route::put('/badges/system-b/{slug}', [AdminBadgeController::class, 'updateSystemB'])->name('badges.system-b.update');
        Route::get('/badges/{badge}/edit', [AdminBadgeController::class, 'edit'])->name('badges.edit');
        Route::put('/badges/{badge}', [AdminBadgeController::class, 'update'])->name('badges.update');

        Route::get('/earn-rules', [AdminXpEarnRuleController::class, 'index'])->name('earn-rules.index');
        Route::get('/earn-rules/{earnRule}/edit', [AdminXpEarnRuleController::class, 'edit'])->name('earn-rules.edit');
        Route::put('/earn-rules/{earnRule}', [AdminXpEarnRuleController::class, 'update'])->name('earn-rules.update');

        Route::get('/levels', [AdminXpLevelController::class, 'index'])->name('levels.index');
        Route::get('/levels/{level}/edit', [AdminXpLevelController::class, 'edit'])->name('levels.edit');
        Route::put('/levels/{level}', [AdminXpLevelController::class, 'update'])->name('levels.update');

        Route::get('/economics', [AdminRewardEconomicsController::class, 'edit'])->name('economics.edit');
        Route::put('/economics', [AdminRewardEconomicsController::class, 'update'])->name('economics.update');
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
