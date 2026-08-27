<?php

use App\Enums\EventVisibility;
use App\Enums\UserType;
use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Admin\BadgeController as AdminBadgeController;
use App\Http\Controllers\Admin\BlogController as AdminBlogController;
use App\Http\Controllers\Admin\BusinessVisibilityBoostController as AdminBusinessVisibilityBoostController;
use App\Http\Controllers\Admin\ChallengeController as AdminChallengeController;
use App\Http\Controllers\Admin\ChallengeDefaultsController as AdminChallengeDefaultsController;
use App\Http\Controllers\Admin\CommunityVerificationController as AdminCommunityVerificationController;
use App\Http\Controllers\Admin\CompanySettingController as AdminCompanySettingController;
use App\Http\Controllers\Admin\CrmController as AdminCrmController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\GamificationController as AdminGamificationController;
use App\Http\Controllers\Admin\IconLibraryController as AdminIconLibraryController;
use App\Http\Controllers\Admin\KolabController as AdminKolabController;
use App\Http\Controllers\Admin\ManagedUserController;
use App\Http\Controllers\Admin\OfferOptionController as AdminOfferOptionController;
use App\Http\Controllers\Admin\PartnerRewardController as AdminPartnerRewardController;
use App\Http\Controllers\Admin\RankingController as AdminRankingController;
use App\Http\Controllers\Admin\ReviewController as AdminReviewController;
use App\Http\Controllers\Admin\RewardEconomicsController as AdminRewardEconomicsController;
use App\Http\Controllers\Admin\StatsController as AdminStatsController;
use App\Http\Controllers\Admin\TaskController as AdminTaskController;
use App\Http\Controllers\Admin\TypeController as AdminTypeController;
use App\Http\Controllers\Admin\XpEarnRuleController as AdminXpEarnRuleController;
use App\Http\Controllers\Admin\XpLevelController as AdminXpLevelController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\DirectoryController;
use App\Http\Controllers\NewsletterController;
use App\Http\Controllers\PasswordResetPageController;
use App\Http\Controllers\PublicEventPageController;
use App\Http\Controllers\PublicKolabPageController;
use App\Http\Controllers\PublicProfilePageController;
use App\Models\BlogPost;
use App\Models\Event;
use App\Models\Profile;
use App\Models\RankingPage;
use App\Support\PublicEventLink;
use App\Support\PublicKolabLink;
use App\Support\PublicProfileLink;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Kolabing Web App (app.kolabing.com)
|--------------------------------------------------------------------------
| Server-rendered Blade shells; Alpine + the inline API client (in the webapp
| layout) drive auth + all data via the same-origin /api/v1. Registered BEFORE
| the marketing routes so the app host wins at "/"; on any other host these do
| not match and the marketing site below is served instead.
|
| Localised for SEO/GEO: the same page set is registered at the root (default
| locale, en) AND under an /es and /ca prefix. `SetWebappLocale` reads the
| {locale} prefix; the layout emits hreflang alternates. No route names (all
| in-app links are literal, locale-prefixed client-side). Config: config/webapp.php.
*/
$webappRoutes = function (): void {
    // The app host has no landing page of its own — kolabing.com is where the
    // product is pitched. Anyone arriving here (typed URL, bookmark, or a
    // requireAuth() bounce) goes straight to the sign-in screen; signing up is
    // its own page at /register.
    Route::get('/', function (?string $locale = null) {
        return redirect(($locale ? '/'.$locale : '').'/login');
    });
    Route::view('/login', 'webapp.login');
    Route::view('/register', 'webapp.register');
    Route::view('/dashboard', 'webapp.dashboard');
    Route::view('/subscription', 'webapp.subscription');
    // Stripe sends the buyer back here with ?session_id=…; the page confirms the
    // purchase against the API instead of trusting the redirect.
    Route::view('/subscription/success', 'webapp.subscription-success');
    Route::view('/welcome', 'webapp.welcome');
    Route::view('/feed', 'webapp.feed');
    // Suggested partners (BE-NF-39). Behind the same `feature:suggestions` gate as
    // the three endpoints it reads, so with the flag off the page 404s instead of
    // rendering an empty state over an API that is answering 404 — see
    // EnsureFeatureEnabled, which aborts(404) for a non-JSON request.
    Route::view('/suggestions', 'webapp.suggestions')->middleware('feature:suggestions');
    Route::view('/notifications', 'webapp.notifications');
    // Chat. One route for the whole inbox: the two-pane layout swaps threads
    // client-side, and ?thread= / ?application= / ?collaboration= deep-link into
    // one (resolved against GET /chats, so no extra endpoint is needed).
    Route::view('/chats', 'webapp.chats');
    /*
     * Events and the door. `/checkin/{token}` is what a QR points at: it accepts
     * either the short code or the long token, signs the visitor in if they are not
     * already, and then performs the check-in. Order matters — the literal /create
     * must be declared before the {event} catch-all.
     */
    Route::view('/events', 'webapp.events');
    Route::view('/events/{event}', 'webapp.event-detail');
    Route::view('/checkin/{token}', 'webapp.checkin');

    // A ghost invite (#246). Served on the app host so the SAME url is the one
    // the Universal Link / App Link hands to the app when it IS installed —
    // this page only ever renders for the person who has to go and get it.
    Route::get('/i/{code}', [\App\Http\Controllers\GhostInvitePageController::class, 'show'])
        ->name('webapp.ghost-invite');
    /*
     * Tickets, and the other side of the door.
     *
     * `/tickets` is the attendee's wallet — the seats they hold, each with the QR
     * that gets them in. `/admit/{code}` is what that QR points at, opened by the
     * HOST's camera: the person admitted is not the person signed in, which is the
     * whole difference from /checkin/{token} above (there the attendee scans a code
     * the host is displaying). Neither route is auth-gated at the route level; both
     * pages call requireAuth(), which carries the destination in `?next=` so a QR
     * scanned on a phone that is not signed in still completes after login.
     */
    /*
     * Multi-Kolab events on the panel: one organizer recruiting several partners
     * into one date. Deliberately NOT under /events — that path is the attendee
     * happening (a door with a QR), a different object with a different audience.
     * The URL shape matches the mobile client's `/multi-kolab-events/:id` so a link
     * pasted between the two resolves.
     */
    Route::view('/multi-kolab-events', 'webapp.multi-kolab-events');
    Route::view('/multi-kolab-events/{event}', 'webapp.multi-kolab-event-detail');

    Route::view('/tickets', 'webapp.tickets');
    Route::view('/admit/{code}', 'webapp.admit');
    // Attendee onboarding: the four steps the mobile app runs, same endpoint.
    Route::view('/onboarding/attendee', 'webapp.onboarding-attendee');
    // Kolabs — order matters: literal + edit before the {kolab} catch-all.
    Route::view('/kolabs', 'webapp.kolabs');
    Route::view('/kolabs/create', 'webapp.kolab-form');
    Route::view('/kolabs/{kolab}/edit', 'webapp.kolab-form');
    Route::view('/kolabs/{kolab}', 'webapp.kolab-detail');
    // The design folds applications into My Kolabs → Requests; this route keeps
    // the standalone URL working by opening that same tab.
    Route::view('/applications', 'webapp.kolabs', ['initialTab' => 'requests']);
    /*
     * One collaboration, end to end (BE-NF-45). The panel had the list and nothing
     * behind it, so a web-only user could accept an application and then never start,
     * confirm, finish or review the thing — while the dashboard was already telling
     * them to leave the review. Everything the page needs already existed on
     * /api/v1/collaborations/{id}; only the screen was missing.
     */
    Route::view('/collaborations/{collaboration}', 'webapp.collaboration-detail');
    // Public profile of any business/community, seen from inside the app.
    Route::view('/profiles/{profile}', 'webapp.profile');
    // Public invitation landing page. On the APP host because Alpine needs
    // 'unsafe-eval' and Google Sign-In needs accounts.google.com — the CSP grants
    // both only here. Not auth-gated: it IS the front door for a new member.
    Route::get('/c/{slug}', [\App\Http\Controllers\CommunityJoinPageController::class, 'show'])
        ->name('communities.join-page');

    Route::view('/account', 'webapp.account');
    // Profile section tabs (BE-NF-35). Settings stay inside the Details page's
    // existing accordion — splitting them would be churn with no user benefit.
    Route::view('/account/gallery', 'webapp.account-gallery');
    Route::view('/account/events', 'webapp.account-events');
    Route::view('/account/preview', 'webapp.account-preview');

    // Community Hub — the members & tiers surface (BE-NF-29). All literals
    // under /community; no catch-all segment, so order is not load-bearing.
    Route::view('/community', 'webapp.community');
    Route::view('/community/members', 'webapp.community-members');
    Route::view('/community/requests', 'webapp.community-requests');
    Route::view('/community/tiers', 'webapp.community-tiers');
    Route::view('/community/economy', 'webapp.community-economy');
    Route::view('/community/leaderboard', 'webapp.community-leaderboard');
    Route::view('/community/settings', 'webapp.community-settings');
};

/*
 * Universal Links (iOS) and App Links (Android) for the app host. Published here so
 * a single check-in URL opens the app when it is installed and the browser when it
 * is not — the QR never has to know which.
 *
 * Both 404 until the mobile identifiers are configured. That is deliberate: Apple's
 * CDN caches the association file, so a placeholder would be cached too and would
 * have to be waited out rather than fixed.
 */
Route::domain(config('webapp.host'))->group(function (): void {
    Route::get('/.well-known/apple-app-site-association', function () {
        $appId = config('webapp.app_links.apple_app_id');

        abort_if(blank($appId), 404);

        return response()->json([
            'applinks' => [
                'details' => [[
                    'appIDs' => [$appId],
                    'components' => array_map(
                        static fn (string $path): array => ['/' => $path, 'comment' => 'Handled in-app'],
                        config('webapp.app_links.paths', [])
                    ),
                ]],
            ],
            // Declared so a future password-manager or handoff feature does not need
            // a second round of DNS-level plumbing.
            'webcredentials' => ['apps' => [$appId]],
        ])->header('Content-Type', 'application/json');
    })->name('webapp.apple-app-site-association');

    Route::get('/.well-known/assetlinks.json', function () {
        $fingerprints = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) config('webapp.app_links.android_sha256'))
        )));

        abort_if($fingerprints === [], 404);

        return response()->json([[
            'relation' => ['delegate_permission/common.handle_all_urls'],
            'target' => [
                'namespace' => 'android_app',
                'package_name' => config('webapp.app_links.android_package'),
                'sha256_cert_fingerprints' => $fingerprints,
            ],
        ]])->header('Content-Type', 'application/json');
    })->name('webapp.assetlinks');
});

Route::domain(config('webapp.host'))
    ->middleware(\App\Http\Middleware\SetWebappLocale::class)
    ->group($webappRoutes);

Route::domain(config('webapp.host'))
    ->prefix('{locale}')
    ->where(['locale' => 'es|ca'])
    ->middleware(\App\Http\Middleware\SetWebappLocale::class)
    ->group($webappRoutes);

Route::get('/', function (\App\Services\PublicKolabFeedService $kolabFeed) {
    /*
     * The homepage strip reads the same gate as /kolabs, through the same service, so
     * the shop window can never advertise something the listing would hide. Six is one
     * tidy row at every breakpoint; `cache_marketing` gives it a 5-minute shared cache,
     * which is the right staleness for a page nobody reloads waiting for a new listing.
     *
     * The strip is decoration and the homepage is the top of the funnel, so a database
     * that is unreachable — or a schema that has not been migrated yet — must cost us
     * the strip, not the page. `/kolabs` deliberately does NOT swallow the same error:
     * a page whose whole subject is the listings should fail loudly rather than render
     * an empty one and imply nothing is open.
     */
    $activeKolabs = collect();

    // Nothing to show, and nothing to ask the database, while the surface is off.
    if (config('kolabing.public_kolabs.enabled')) {
        try {
            $activeKolabs = $kolabFeed->highlights(6);
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    return view('welcome', ['activeKolabs' => $activeKolabs]);
})->name('home')->middleware('cache_marketing');

// Legacy invite links still point at the marketing host. The page itself moved
// to the app host, where the CSP allows Alpine ('unsafe-eval') and Google
// Sign-In — under the marketing policy it could not run at all (BE-NF-38).
Route::get('/i/{code}', function (string $code) {
    // An invite shared from the app carries the app host, but people paste and
    // retype links. Sending kolabing.com/i/... to the app host keeps a mistyped
    // domain working rather than 404ing on someone who is trying to join.
    return redirect()->away(rtrim(config('webapp.url'), '/').'/i/'.$code, 302);
})->name('ghost-invite.redirect');

Route::get('/c/{slug}', function (string $slug) {
    $query = request()->getQueryString();

    return redirect()->away(
        rtrim(config('webapp.url'), '/').'/c/'.$slug.($query ? '?'.$query : ''),
        301,
    );
})->name('communities.join-page.legacy');

Route::post('/newsletter', [NewsletterController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('newsletter.store');

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

    // Multi-Kolab Event Creator entitlement — independent of the business
    // subscription above; both Business and Community profiles are eligible.
    Route::post('/users/{profile}/event-creator/grant', [ManagedUserController::class, 'grantEventCreatorEntitlement'])->name('users.event-creator.grant');
    Route::post('/users/{profile}/event-creator/revoke', [ManagedUserController::class, 'revokeEventCreatorEntitlement'])->name('users.event-creator.revoke');

    // Community verification — submit proof channels (mobile), maintainer verifies
    // / rejects here. State lives on community_profiles.verification_*.
    Route::get('/community-verification', [AdminCommunityVerificationController::class, 'index'])->name('community-verification.index');
    Route::post('/users/{profile}/verification/verify', [AdminCommunityVerificationController::class, 'verify'])->name('users.verification.verify');
    Route::post('/users/{profile}/verification/reject', [AdminCommunityVerificationController::class, 'reject'])->name('users.verification.reject');

    Route::get('/kolabs', [AdminKolabController::class, 'index'])->name('kolabs.index');
    Route::get('/kolabs/{kolab}/edit', [AdminKolabController::class, 'edit'])->name('kolabs.edit');
    Route::put('/kolabs/{kolab}', [AdminKolabController::class, 'update'])->name('kolabs.update');
    Route::delete('/kolabs/{kolab}', [AdminKolabController::class, 'destroy'])->name('kolabs.destroy');
    Route::post('/kolabs/{kolab}/collaboration/cancel', [AdminKolabController::class, 'cancelCollaboration'])->name('kolabs.collaboration.cancel');
    Route::post('/kolabs/{kolab}/collaboration/complete', [AdminKolabController::class, 'completeCollaboration'])->name('kolabs.collaboration.complete');

    Route::get('/stats', [AdminStatsController::class, 'index'])->name('stats.index');

    Route::get('/reviews', [AdminReviewController::class, 'index'])->name('reviews.index');
    Route::post('/reviews/{review}/toggle-comment', [AdminReviewController::class, 'toggleComment'])->name('reviews.toggle-comment');

    // Marketing / SEO blog — maintainer-authored posts served publicly at /blog.
    Route::get('/blog', [AdminBlogController::class, 'index'])->name('blog.index');
    Route::get('/blog/create', [AdminBlogController::class, 'create'])->name('blog.create');
    Route::post('/blog', [AdminBlogController::class, 'store'])->name('blog.store');
    Route::get('/blog/{post}/edit', [AdminBlogController::class, 'edit'])->name('blog.edit');
    Route::put('/blog/{post}', [AdminBlogController::class, 'update'])->name('blog.update');
    Route::delete('/blog/{post}', [AdminBlogController::class, 'destroy'])->name('blog.destroy');

    // Company / legal details — populate the public Terms + Privacy pages and
    // the consent version that drives the mobile re-consent gate.
    Route::get('/company-settings', [AdminCompanySettingController::class, 'edit'])->name('company-settings.edit');
    Route::put('/company-settings', [AdminCompanySettingController::class, 'update'])->name('company-settings.update');

    // CRM (businesses / communities / ambassadors) + Tasks
    Route::get('/crm', [AdminCrmController::class, 'index'])->name('crm.index');
    Route::get('/crm/board', [AdminCrmController::class, 'board'])->name('crm.board');
    Route::get('/crm/export', [AdminCrmController::class, 'export'])->name('crm.export');
    Route::post('/crm/columns', [AdminCrmController::class, 'saveColumns'])->name('crm.columns');
    Route::get('/crm/create', [AdminCrmController::class, 'create'])->name('crm.create');
    Route::post('/crm', [AdminCrmController::class, 'store'])->name('crm.store');
    Route::get('/crm/{account}/edit', [AdminCrmController::class, 'edit'])->name('crm.edit');
    Route::get('/crm/{account}', [AdminCrmController::class, 'show'])->name('crm.show');
    Route::put('/crm/{account}', [AdminCrmController::class, 'update'])->name('crm.update');
    Route::delete('/crm/{account}', [AdminCrmController::class, 'destroy'])->name('crm.destroy');
    Route::post('/crm/{account}/stage', [AdminCrmController::class, 'moveStage'])->name('crm.stage');
    Route::post('/crm/{account}/activity', [AdminCrmController::class, 'addActivity'])->name('crm.activity');
    Route::post('/crm/{account}/first-touch', [AdminCrmController::class, 'firstTouch'])->name('crm.first-touch');

    // Community-rankings directory: publish/unpublish pages + moderate testimonials.
    // (Re-ranking is done in /admin/crm via score + metrics.rank_override + listed.)
    Route::get('/rankings', [AdminRankingController::class, 'index'])->name('rankings.index');
    Route::post('/rankings/{page}/publish', [AdminRankingController::class, 'togglePublish'])->name('rankings.publish');
    Route::post('/rankings/{page}/spotlight', [AdminRankingController::class, 'toggleSpotlight'])->name('rankings.spotlight');
    Route::post('/rankings/testimonials/{testimonial}/{decision}', [AdminRankingController::class, 'moderate'])->name('rankings.testimonials.moderate');

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
        // Read-only oversight: overview + leaderboards.
        Route::get('/overview', [AdminGamificationController::class, 'overview'])->name('overview');
        Route::get('/leaderboards/communities', [AdminGamificationController::class, 'communityLeaderboard'])->name('leaderboards.communities');
        Route::get('/leaderboards/communities/{community}', [AdminGamificationController::class, 'communityLeaderboard'])->name('leaderboards.communities.show');
        Route::get('/leaderboards/global', [AdminGamificationController::class, 'globalLeaderboard'])->name('leaderboards.global');

        // Global partner-reward catalogue ("Redeem your XP") — full CRUD.
        Route::get('/partner-rewards', [AdminPartnerRewardController::class, 'index'])->name('partner-rewards.index');
        Route::get('/partner-rewards/create', [AdminPartnerRewardController::class, 'create'])->name('partner-rewards.create');
        Route::post('/partner-rewards', [AdminPartnerRewardController::class, 'store'])->name('partner-rewards.store');
        Route::get('/partner-rewards/{partnerReward}/edit', [AdminPartnerRewardController::class, 'edit'])->name('partner-rewards.edit');
        Route::put('/partner-rewards/{partnerReward}', [AdminPartnerRewardController::class, 'update'])->name('partner-rewards.update');
        Route::delete('/partner-rewards/{partnerReward}', [AdminPartnerRewardController::class, 'destroy'])->name('partner-rewards.destroy');

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

        Route::get('/business-visibility-boost', [AdminBusinessVisibilityBoostController::class, 'edit'])->name('business-visibility-boost.edit');
        Route::put('/business-visibility-boost', [AdminBusinessVisibilityBoostController::class, 'update'])->name('business-visibility-boost.update');
    });
});

Route::get('/reset-password', [PasswordResetPageController::class, 'show'])->name('password.reset');
Route::post('/reset-password', [PasswordResetPageController::class, 'update'])->name('password.reset.update');

Route::view('/for-businesses', 'pages.for-businesses')->name('for-businesses')->middleware('cache_marketing');
Route::view('/for-communities', 'pages.for-communities')->name('for-communities')->middleware('cache_marketing');
Route::view('/pricing', 'pages.pricing')->name('pricing')->middleware('cache_marketing');
Route::view('/es/pricing', 'pages.es.pricing')->name('pricing.es')->middleware('cache_marketing');
Route::view('/support', 'pages.support')->name('support')->middleware('cache_marketing');
Route::view('/careers', 'pages.careers')->name('careers')->middleware('cache_marketing');
Route::view('/privacy', 'pages.privacy')->name('privacy')->middleware('cache_marketing');
Route::view('/terms', 'pages.terms')->name('terms')->middleware('cache_marketing');
Route::view('/es/privacy', 'pages.es.privacy')->name('privacy.es')->middleware('cache_marketing');
Route::view('/es/terms', 'pages.es.terms')->name('terms.es')->middleware('cache_marketing');

// Shareable public profile teaser (marketing host, indexable). The slug is
// `name-<uuid tail>`; see App\Support\PublicProfileLink.
Route::get('/p/{slug}', [PublicProfilePageController::class, 'show'])->name('public-profile')->middleware('cache_marketing');

// What's on — the attendee's front door: public events, no account needed to read.
// Only EventVisibility::Public reaches these pages (see PublicEventPageController).
Route::get('/events', [PublicEventPageController::class, 'index'])->name('public-events')->middleware('cache_marketing');
Route::get('/events/{slug}', [PublicEventPageController::class, 'show'])->name('public-event')->middleware('cache_marketing');

/*
 * The marketplace on the open web: active Kolabs, no account needed to read one.
 * Separate from /events on purpose — an event is something you attend, a Kolab is a
 * partnership offer, and the two answer different searches. What may be shown is
 * decided in PublicKolabFeedService (which Kolabs) and PublicKolabPoster (whose name).
 */
Route::get('/kolabs', [PublicKolabPageController::class, 'index'])->name('public-kolabs')->middleware('cache_marketing');
Route::get('/kolabs/{slug}', [PublicKolabPageController::class, 'show'])->name('public-kolab')->middleware('cache_marketing');

Route::get('/blog', [BlogController::class, 'index'])->name('blog.index')->middleware('cache_marketing');
Route::get('/blog/{post}', [BlogController::class, 'show'])->name('blog.show')->middleware('cache_marketing');

// Community rankings directory (public GTM lead-magnet pages).
Route::get('/communities', [DirectoryController::class, 'index'])->name('directory.index')->middleware('cache_marketing');
Route::get('/communities/how-we-rank', [DirectoryController::class, 'howWeRank'])->name('directory.how-we-rank')->middleware('cache_marketing');
// Social layer + claim (literal paths before the {city} catch-alls).
Route::post('/communities/claim', [DirectoryController::class, 'claim'])
    ->middleware('throttle:10,1')->name('directory.claim');
Route::get('/communities/claim/verify/{token}', [DirectoryController::class, 'verifyClaim'])->name('directory.claim.verify');
Route::post('/communities/vouch', [DirectoryController::class, 'vouch'])
    ->middleware('throttle:40,1')->name('directory.vouch');
Route::post('/communities/testimonial', [DirectoryController::class, 'testimonial'])
    ->middleware('throttle:10,1')->name('directory.testimonial');
Route::get('/communities/{city}/badge/{id}', [DirectoryController::class, 'badge'])->name('directory.badge');
Route::get('/communities/{city}', [DirectoryController::class, 'show'])->name('directory.city')->middleware('cache_marketing');
Route::get('/communities/{city}/{slug}', [DirectoryController::class, 'topic'])->name('directory.topic')->middleware('cache_marketing');

Route::get('/sitemap.xml', function () {
    $urls = [
        route('home'),
        route('for-businesses'),
        route('for-communities'),
        route('pricing'),
        route('pricing.es'),
        route('support'),
        route('careers'),
        route('privacy'),
        route('terms'),
        route('privacy.es'),
        route('terms.es'),
    ];

    // A hub with nothing in it is a thin page: it stays out of the sitemap and
    // serves `noindex` (see the marketing layout) until it has something to show.
    $posts = BlogPost::query()->published()->orderByDesc('published_at')->pluck('slug');
    if ($posts->isNotEmpty()) {
        $urls[] = route('blog.index');
        foreach ($posts as $slug) {
            $urls[] = route('blog.show', $slug);
        }
    }

    /*
     * Public events. Only upcoming ones, and only `visibility = public` — the same
     * gate the pages themselves use, so the sitemap can never advertise a
     * members-only event's URL.
     */
    $publicEvents = Event::query()
        ->where('visibility', EventVisibility::Public)
        ->where(fn ($query) => $query
            ->where('starts_at', '>=', now())
            ->orWhere('event_date', '>=', now()->toDateString()))
        ->orderByRaw('COALESCE(starts_at, event_date) ASC')
        ->limit(500)
        ->get();
    if ($publicEvents->isNotEmpty()) {
        $urls[] = route('public-events');
        foreach ($publicEvents as $publicEvent) {
            $urls[] = PublicEventLink::urlFor($publicEvent);
        }
    }

    /*
     * Public Kolabs, only once the data is worth indexing. The pages themselves serve
     * `noindex` under the same flag (BE-FX-20), and a sitemap that advertises URLs the
     * page asks Google to ignore is a contradiction, so both read one config value.
     */
    if (config('kolabing.public_kolabs.enabled') && config('kolabing.public_kolabs.indexable')) {
        $publicKolabs = app(\App\Services\PublicKolabFeedService::class)
            ->publishable()
            ->orderByDesc('published_at')
            ->limit(500)
            ->get();

        if ($publicKolabs->isNotEmpty()) {
            $urls[] = route('public-kolabs');
            foreach ($publicKolabs as $publicKolab) {
                $urls[] = PublicKolabLink::urlFor($publicKolab);
            }
        }
    }

    $rankingPages = RankingPage::query()->published()->orderBy('sort')->get(['city', 'topic', 'slug']);
    if ($rankingPages->isNotEmpty()) {
        $urls[] = route('directory.index');
        $urls[] = route('directory.how-we-rank');
        foreach ($rankingPages as $page) {
            // A hub page is a city; the rest hang off a city as a topic.
            $urls[] = $page->topic === null
                ? route('directory.city', $page->slug)
                : route('directory.topic', [$page->city, $page->slug]);
        }
    }

    /*
     * Public profile teasers, and the bar is deliberately higher than "exists".
     * A completed collaboration alone let a seeded test account into the index,
     * and a profile with no review and no photos is a near-duplicate of every
     * other empty profile — exactly the thin-page cluster that drags a domain
     * down once there are hundreds of them. Require something a reader would
     * actually come for: a review, or a real gallery.
     */
    foreach (Profile::query()
        ->whereIn('user_type', [UserType::Business, UserType::Community])
        ->whereHas('receivedReviews', fn ($query) => $query->whereNotNull('rating'))
        ->orWhere(fn ($query) => $query
            ->whereIn('user_type', [UserType::Business, UserType::Community])
            ->has('galleryPhotos', '>=', 3))
        ->with(['businessProfile', 'communityProfile'])
        ->limit(500)
        ->get() as $profile) {
        $urls[] = route('public-profile', PublicProfileLink::slugFor($profile));
    }

    return response()->view('sitemap', [
        'urls' => $urls,
        'lastModified' => now()->toDateString(),
    ])->header('Content-Type', 'application/xml; charset=UTF-8');
})->name('sitemap')->middleware('cache_marketing');

Route::get('/llms.txt', function () {
    $lines = [
        '# Kolabing',
        '',
        'Kolabing is a collaboration platform that helps local businesses and community groups launch in-person partnerships, events, and repeatable growth campaigns.',
        '',
        'Preferred pages:',
        '- Home: '.route('home'),
        '- For businesses: '.route('for-businesses'),
        '- For communities: '.route('for-communities'),
        '- Pricing: '.route('pricing'),
        '- Blog: '.route('blog.index'),
        '- Support: '.route('support'),
        '- Privacy: '.route('privacy'),
        '- Terms: '.route('terms'),
    ];

    $posts = BlogPost::query()->published()->orderByDesc('published_at')->limit(20)->get(['slug', 'title']);
    if ($posts->isNotEmpty()) {
        $lines[] = '';
        $lines[] = 'Recent articles:';
        foreach ($posts as $post) {
            $lines[] = '- '.$post->title.': '.route('blog.show', $post->slug);
        }
    }

    $lines[] = '';
    $lines[] = 'Contact: support@kolabing.com';

    return response(implode("\n", $lines), 200)->header('Content-Type', 'text/plain; charset=UTF-8');
})->name('llms')->middleware('cache_marketing');

Route::get('/.well-known/security.txt', function () {
    $content = implode("\n", [
        'Contact: mailto:support@kolabing.com',
        'Expires: 2027-04-21T23:59:59.000Z',
        'Preferred-Languages: en',
        'Canonical: https://kolabing.com/.well-known/security.txt',
        'Policy: https://kolabing.com/privacy',
    ]);

    return response($content, 200)->header('Content-Type', 'text/plain; charset=UTF-8');
})->name('security.txt')->middleware('cache_marketing');
