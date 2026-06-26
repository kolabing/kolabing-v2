<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AppleIAPController;
use App\Http\Controllers\Api\V1\AppleWebhookController;
use App\Http\Controllers\Api\V1\ApplicationController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BadgeController;
use App\Http\Controllers\Api\V1\ChallengeCompletionController;
use App\Http\Controllers\Api\V1\ChallengeController;
use App\Http\Controllers\Api\V1\ChatController;
use App\Http\Controllers\Api\V1\CheckinController;
use App\Http\Controllers\Api\V1\CollaborationChallengeBonusController;
use App\Http\Controllers\Api\V1\CollaborationChallengeController;
use App\Http\Controllers\Api\V1\CollaborationController;
use App\Http\Controllers\Api\V1\CollaborationQrCodeController;
use App\Http\Controllers\Api\V1\CommunityBadgeController;
use App\Http\Controllers\Api\V1\CommunityController;
use App\Http\Controllers\Api\V1\CommunityGoalController;
use App\Http\Controllers\Api\V1\CommunityJoinRequestController;
use App\Http\Controllers\Api\V1\CommunityMemberController;
use App\Http\Controllers\Api\V1\CommunityRewardController;
use App\Http\Controllers\Api\V1\CommunityRewardsHubController;
use App\Http\Controllers\Api\V1\CommunityTierController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\DeviceTokenController;
use App\Http\Controllers\Api\V1\DiscoveryOpportunityController;
use App\Http\Controllers\Api\V1\EventController;
use App\Http\Controllers\Api\V1\EventDiscoveryController;
use App\Http\Controllers\Api\V1\EventPhotoController;
use App\Http\Controllers\Api\V1\EventRewardController;
use App\Http\Controllers\Api\V1\EventSignupController;
use App\Http\Controllers\Api\V1\FriendshipController;
use App\Http\Controllers\Api\V1\GalleryController;
use App\Http\Controllers\Api\V1\GamificationConfigController;
use App\Http\Controllers\Api\V1\GamificationController;
use App\Http\Controllers\Api\V1\GamificationStatsController;
use App\Http\Controllers\Api\V1\KolabController;
use App\Http\Controllers\Api\V1\LeaderboardController;
use App\Http\Controllers\Api\V1\LookupController;
use App\Http\Controllers\Api\V1\MeRewardsOverviewController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\NotificationPreferenceController;
use App\Http\Controllers\Api\V1\OnboardingController;
use App\Http\Controllers\Api\V1\OpportunityController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\ReferralController;
use App\Http\Controllers\Api\V1\RewardWalletController;
use App\Http\Controllers\Api\V1\SpinWheelController;
use App\Http\Controllers\Api\V1\SubscriptionController;
use App\Http\Controllers\Api\V1\SystemChallengeController;
use App\Http\Controllers\Api\V1\UploadController;
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

    Route::post('auth/apple', [AuthController::class, 'apple'])
        ->name('api.v1.auth.apple');

    Route::post('auth/register/business', [AuthController::class, 'registerBusiness'])
        ->name('api.v1.auth.register.business');

    Route::post('auth/register/community', [AuthController::class, 'registerCommunity'])
        ->name('api.v1.auth.register.community');

    Route::post('auth/register/attendee', [AuthController::class, 'registerAttendee'])
        ->name('api.v1.auth.register.attendee');

    Route::post('auth/login', [AuthController::class, 'login'])
        ->name('api.v1.auth.login');

    Route::post('auth/refresh', [AuthController::class, 'refresh'])
        ->name('api.v1.auth.refresh');

    Route::post('auth/forgot-password', [AuthController::class, 'forgotPassword'])
        ->name('api.v1.auth.forgot-password');

    Route::post('auth/reset-password', [AuthController::class, 'resetPassword'])
        ->name('api.v1.auth.reset-password');

    // Apple Server Notifications V2 Webhook (public — verified via JWS signature)
    Route::post('webhooks/apple', AppleWebhookController::class)
        ->name('api.v1.webhooks.apple');

    // Lookups
    Route::get('cities', [LookupController::class, 'cities'])
        ->name('api.v1.cities');

    Route::get('places/autocomplete', [LookupController::class, 'autocompletePlaces'])
        ->name('api.v1.places.autocomplete');

    Route::get('places/details', [LookupController::class, 'placeDetails'])
        ->name('api.v1.places.details');

    Route::get('places/photo', [LookupController::class, 'placePhoto'])
        ->name('api.v1.places.photo');

    Route::get('lookup/business-types', [LookupController::class, 'businessTypes'])
        ->name('api.v1.lookup.business-types');

    Route::get('lookup/community-types', [LookupController::class, 'communityTypes'])
        ->name('api.v1.lookup.community-types');

    // Kolab offer taxonomies (admin-managed in /admin/offer-options).
    Route::get('lookup/offerings', [LookupController::class, 'offerings'])
        ->name('api.v1.lookup.offerings');

    Route::get('lookup/deliverables', [LookupController::class, 'deliverables'])
        ->name('api.v1.lookup.deliverables');

    Route::get('lookup/needs', [LookupController::class, 'needs'])
        ->name('api.v1.lookup.needs');

    Route::get('lookup/product-types', [LookupController::class, 'productTypes'])
        ->name('api.v1.lookup.product-types');

    Route::get('lookup/venue-types', [LookupController::class, 'venueTypes'])
        ->name('api.v1.lookup.venue-types');

    Route::get('lookup/goals', [LookupController::class, 'goals'])
        ->name('api.v1.lookup.goals');

    Route::get('lookup/product-interactions', [LookupController::class, 'productInteractions'])
        ->name('api.v1.lookup.product-interactions');

    Route::get('lookup/venue-fits', [LookupController::class, 'venueFits'])
        ->name('api.v1.lookup.venue-fits');

    Route::get('lookup/kolab-highlights', [LookupController::class, 'kolabHighlights'])
        ->name('api.v1.lookup.kolab-highlights');

    // Universal @handle availability check (leaks no PII; usable pre-auth too).
    Route::get('handle/available', [LookupController::class, 'handleAvailable'])
        ->name('api.v1.handle.available');

    /*
    |--------------------------------------------------------------------------
    | Protected Routes
    |--------------------------------------------------------------------------
    */

    Route::middleware(['auth:sanctum', 'log_auth_token_first_use', 'touch_profile_activity'])->group(function (): void {
        // City suggestions
        Route::post('cities/suggest', [LookupController::class, 'suggestCity'])
            ->name('api.v1.cities.suggest');

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

        // Onboarding - Attendee only
        Route::put('onboarding/attendee', [OnboardingController::class, 'attendee'])
            ->middleware('user_type:attendee')
            ->name('api.v1.onboarding.attendee');

        /*
        |--------------------------------------------------------------------------
        | Profile Management
        |--------------------------------------------------------------------------
        */

        // Register / update FCM device token
        Route::post('me/device-token', [DeviceTokenController::class, 'store'])
            ->name('api.v1.me.device-token');

        // Get full profile with subscription
        Route::get('me/profile', [ProfileController::class, 'show'])
            ->name('api.v1.me.profile');

        // Update profile
        Route::put('me/profile', [ProfileController::class, 'update'])
            ->name('api.v1.me.profile.update');

        // Dashboard
        Route::get('me/dashboard', DashboardController::class)
            ->name('api.v1.me.dashboard');

        // Delete account (soft delete)
        Route::delete('me/account', [ProfileController::class, 'destroy'])
            ->name('api.v1.me.account.destroy');

        /*
        |--------------------------------------------------------------------------
        | Notification Preferences
        |--------------------------------------------------------------------------
        */

        // Get notification preferences
        Route::get('me/notification-preferences', [NotificationPreferenceController::class, 'show'])
            ->name('api.v1.me.notification-preferences');

        // Update notification preferences
        Route::put('me/notification-preferences', [NotificationPreferenceController::class, 'update'])
            ->name('api.v1.me.notification-preferences.update');

        /*
        |--------------------------------------------------------------------------
        | Subscription (Business only)
        |--------------------------------------------------------------------------
        */

        // Get subscription details
        Route::get('me/subscription', [SubscriptionController::class, 'show'])
            ->name('api.v1.me.subscription');

        // Apple IAP (iOS only)
        Route::post('me/subscription/apple-verify', [AppleIAPController::class, 'verify'])
            ->name('api.v1.me.subscription.apple-verify');

        Route::post('me/subscription/apple-restore', [AppleIAPController::class, 'restore'])
            ->name('api.v1.me.subscription.apple-restore');

        Route::post('referrals/validate', [ReferralController::class, 'validateCode'])
            ->name('api.v1.referrals.validate');

        /*
        |--------------------------------------------------------------------------
        | Gallery
        |--------------------------------------------------------------------------
        */

        // List own gallery photos
        Route::get('me/gallery', [GalleryController::class, 'index'])
            ->name('api.v1.me.gallery');

        // Upload gallery photo
        Route::post('me/gallery', [GalleryController::class, 'store'])
            ->name('api.v1.me.gallery.store');

        // Delete gallery photo
        Route::delete('me/gallery/{photo}', [GalleryController::class, 'destroy'])
            ->name('api.v1.me.gallery.destroy');

        // View another profile's gallery
        Route::get('profiles/{profile}/gallery', [GalleryController::class, 'show'])
            ->name('api.v1.profiles.gallery');

        /*
        |--------------------------------------------------------------------------
        | Events (Past Events)
        |--------------------------------------------------------------------------
        */

        // List events (own or by profile_id query param)
        Route::get('events', [EventController::class, 'index'])
            ->name('api.v1.events.index');

        // Event Discovery (must be BEFORE events/{event} route)
        Route::get('events/discover', EventDiscoveryController::class)
            ->name('api.v1.events.discover');

        // Get single event
        Route::get('events/{event}', [EventController::class, 'show'])
            ->name('api.v1.events.show');

        // Create event
        Route::post('events', [EventController::class, 'store'])
            ->name('api.v1.events.store');

        // Update event
        Route::put('events/{event}', [EventController::class, 'update'])
            ->name('api.v1.events.update');

        // Delete event (scope = this | following | series for recurring)
        Route::delete('events/{event}', [EventController::class, 'destroy'])
            ->name('api.v1.events.destroy');

        // NF-16 recurring — extend a series' rolling window
        Route::post('event-series/{series}/extend', [EventController::class, 'extendSeries'])
            ->name('api.v1.event-series.extend');

        // NF-CHAT Phase 3 — event sign-up (RSVP) + event chat
        Route::post('events/{event}/signup', [EventSignupController::class, 'store'])
            ->name('api.v1.events.signup.store');
        Route::delete('events/{event}/signup', [EventSignupController::class, 'destroy'])
            ->name('api.v1.events.signup.destroy');
        Route::get('events/{event}/signups', [EventSignupController::class, 'index'])
            ->name('api.v1.events.signups.index');
        Route::post('events/{event}/chat', [ChatController::class, 'storeEventChat'])
            ->name('api.v1.events.chat.store');

        // NF-16 — add/remove photos on an existing event (creator / can_manage)
        Route::post('events/{event}/photos', [EventPhotoController::class, 'store'])
            ->name('api.v1.events.photos.store');
        Route::delete('events/{event}/photos/{photo}', [EventPhotoController::class, 'destroy'])
            ->name('api.v1.events.photos.destroy');

        /*
        |--------------------------------------------------------------------------
        | Gamification - Check-in
        |--------------------------------------------------------------------------
        */

        // Generate QR check-in token for an event
        Route::post('events/{event}/generate-qr', [CheckinController::class, 'generateQr'])
            ->name('api.v1.events.generate-qr');

        // Check in using QR token
        Route::post('checkin', [CheckinController::class, 'checkin'])
            ->name('api.v1.checkin');

        // List check-ins for an event
        Route::get('events/{event}/checkins', [CheckinController::class, 'index'])
            ->name('api.v1.events.checkins');

        /*
        |--------------------------------------------------------------------------
        | Gamification - Challenges
        |--------------------------------------------------------------------------
        */

        // List challenges for an event (system + custom)
        Route::get('events/{event}/challenges', [ChallengeController::class, 'index'])
            ->name('api.v1.events.challenges.index');

        // Create custom challenge for an event
        Route::post('events/{event}/challenges', [ChallengeController::class, 'store'])
            ->name('api.v1.events.challenges.store');

        // Update a custom challenge
        Route::put('challenges/{challenge}', [ChallengeController::class, 'update'])
            ->name('api.v1.challenges.update');

        // Delete a custom challenge
        Route::delete('challenges/{challenge}', [ChallengeController::class, 'destroy'])
            ->name('api.v1.challenges.destroy');

        /*
        |--------------------------------------------------------------------------
        | Gamification - Challenge Completion
        |--------------------------------------------------------------------------
        */

        // Initiate a peer-to-peer challenge
        Route::post('challenges/initiate', [ChallengeCompletionController::class, 'initiate'])
            ->name('api.v1.challenges.initiate');

        // Verify a challenge completion
        Route::post('challenge-completions/{challengeCompletion}/verify', [ChallengeCompletionController::class, 'verify'])
            ->name('api.v1.challenge-completions.verify');

        // Reject a challenge completion
        Route::post('challenge-completions/{challengeCompletion}/reject', [ChallengeCompletionController::class, 'reject'])
            ->name('api.v1.challenge-completions.reject');

        // My challenge completions
        Route::get('me/challenge-completions', [ChallengeCompletionController::class, 'myCompletions'])
            ->name('api.v1.me.challenge-completions');

        /*
        |--------------------------------------------------------------------------
        | Gamification - Leaderboard
        |--------------------------------------------------------------------------
        */

        // Event leaderboard
        Route::get('events/{event}/leaderboard', [LeaderboardController::class, 'eventLeaderboard'])
            ->name('api.v1.events.leaderboard');

        // Global leaderboard (optional ?community_id= for chapter-scoped view)
        Route::get('leaderboard/global', [LeaderboardController::class, 'globalLeaderboard'])
            ->name('api.v1.leaderboard.global');

        /*
        |--------------------------------------------------------------------------
        | Communities — members & customisable tiers (NF-6)
        |--------------------------------------------------------------------------
        */
        Route::get('me/communities', [CommunityController::class, 'index'])
            ->name('api.v1.me.communities');
        Route::get('me/memberships', [CommunityController::class, 'myMemberships'])
            ->name('api.v1.me.memberships');

        Route::post('communities', [CommunityController::class, 'store'])
            ->name('api.v1.communities.store');
        // Public discovery — must be registered before the {community} wildcard
        // so "discover" is not captured as a route-model-bound community.
        Route::get('communities/discover', [CommunityController::class, 'discover'])
            ->name('api.v1.communities.discover');
        // Token-based invite join — literal "join" segment before the {community}
        // wildcard so the token is not mistaken for a community id.
        Route::post('communities/join/{token}', [CommunityController::class, 'joinByToken'])
            ->name('api.v1.communities.join-by-token');
        Route::get('communities/{community}', [CommunityController::class, 'show'])
            ->name('api.v1.communities.show');
        Route::patch('communities/{community}', [CommunityController::class, 'update'])
            ->name('api.v1.communities.update');
        Route::get('communities/{community}/invite', [CommunityController::class, 'invite'])
            ->name('api.v1.communities.invite');
        Route::post('communities/{community}/join', [CommunityController::class, 'join'])
            ->name('api.v1.communities.join');

        // Invite-only join requests (request → leader approves/declines).
        Route::post('communities/{community}/join-requests', [CommunityJoinRequestController::class, 'store'])
            ->name('api.v1.communities.join-requests.store');
        Route::get('communities/{community}/join-requests', [CommunityJoinRequestController::class, 'index'])
            ->name('api.v1.communities.join-requests.index');
        Route::post('join-requests/{joinRequest}/approve', [CommunityJoinRequestController::class, 'approve'])
            ->name('api.v1.join-requests.approve');
        Route::post('join-requests/{joinRequest}/decline', [CommunityJoinRequestController::class, 'decline'])
            ->name('api.v1.join-requests.decline');

        Route::get('communities/{community}/tiers', [CommunityTierController::class, 'index'])
            ->name('api.v1.communities.tiers.index');
        Route::post('communities/{community}/tiers', [CommunityTierController::class, 'store'])
            ->name('api.v1.communities.tiers.store');
        Route::patch('tiers/{tier}', [CommunityTierController::class, 'update'])
            ->name('api.v1.tiers.update');
        Route::delete('tiers/{tier}', [CommunityTierController::class, 'destroy'])
            ->name('api.v1.tiers.destroy');

        Route::get('communities/{community}/members', [CommunityMemberController::class, 'index'])
            ->name('api.v1.communities.members.index');
        Route::post('communities/{community}/members', [CommunityMemberController::class, 'store'])
            ->name('api.v1.communities.members.store');
        Route::patch('communities/{community}/members/{member}', [CommunityMemberController::class, 'update'])
            ->name('api.v1.communities.members.update');
        Route::delete('communities/{community}/members/{member}', [CommunityMemberController::class, 'destroy'])
            ->name('api.v1.communities.members.destroy');

        /*
        |--------------------------------------------------------------------------
        | Community gamification — goals, rewards, badges, points (per-community)
        |--------------------------------------------------------------------------
        | Leader CRUD is manage-gated in the controllers (owner / can_manage),
        | like the roster & tier endpoints. Member reads are open to viewers.
        */

        // Goals
        Route::get('communities/{community}/goals', [CommunityGoalController::class, 'index'])
            ->name('api.v1.communities.goals.index');
        Route::post('communities/{community}/goals', [CommunityGoalController::class, 'store'])
            ->name('api.v1.communities.goals.store');
        Route::put('goals/{goal}', [CommunityGoalController::class, 'update'])
            ->name('api.v1.goals.update');
        Route::delete('goals/{goal}', [CommunityGoalController::class, 'destroy'])
            ->name('api.v1.goals.destroy');

        // Rewards (leader CRUD)
        Route::get('communities/{community}/rewards', [CommunityRewardController::class, 'index'])
            ->name('api.v1.communities.rewards.index');
        Route::post('communities/{community}/rewards', [CommunityRewardController::class, 'store'])
            ->name('api.v1.communities.rewards.store');
        Route::put('rewards/{reward}', [CommunityRewardController::class, 'update'])
            ->name('api.v1.rewards.update');
        Route::delete('rewards/{reward}', [CommunityRewardController::class, 'destroy'])
            ->name('api.v1.rewards.destroy');

        // Badges (leader CRUD)
        Route::get('communities/{community}/badges', [CommunityBadgeController::class, 'index'])
            ->name('api.v1.communities.badges.index');
        Route::post('communities/{community}/badges', [CommunityBadgeController::class, 'store'])
            ->name('api.v1.communities.badges.store');
        Route::put('badges/{badge}', [CommunityBadgeController::class, 'update'])
            ->name('api.v1.badges.update');
        Route::delete('badges/{badge}', [CommunityBadgeController::class, 'destroy'])
            ->name('api.v1.badges.destroy');

        // Member rewards hub + redeem
        Route::get('communities/{community}/rewards-hub', [CommunityRewardsHubController::class, 'show'])
            ->name('api.v1.communities.rewards-hub');
        Route::post('communities/{community}/rewards/{reward}/redeem', [CommunityRewardsHubController::class, 'redeem'])
            ->name('api.v1.communities.rewards.redeem');

        // Per-community POINTS leaderboard (tier + badge_count + points rows)
        Route::get('communities/{community}/leaderboard', [LeaderboardController::class, 'communityLeaderboard'])
            ->name('api.v1.communities.leaderboard');

        // Personal rewards overview (global XP + partner rewards + per-community)
        Route::get('me/rewards-overview', [MeRewardsOverviewController::class, 'show'])
            ->name('api.v1.me.rewards-overview');

        /*
        |--------------------------------------------------------------------------
        | Gamification - Rewards (Organizer Management)
        |--------------------------------------------------------------------------
        */

        // List rewards for an event
        Route::get('events/{event}/rewards', [EventRewardController::class, 'index'])
            ->name('api.v1.events.rewards.index');

        // Create reward for an event
        Route::post('events/{event}/rewards', [EventRewardController::class, 'store'])
            ->name('api.v1.events.rewards.store');

        // Update a reward
        Route::put('event-rewards/{eventReward}', [EventRewardController::class, 'update'])
            ->name('api.v1.event-rewards.update');

        // Delete a reward
        Route::delete('event-rewards/{eventReward}', [EventRewardController::class, 'destroy'])
            ->name('api.v1.event-rewards.destroy');

        /*
        |--------------------------------------------------------------------------
        | Gamification - Spin the Wheel
        |--------------------------------------------------------------------------
        */

        // Spin the wheel after verified challenge completion
        Route::post('rewards/spin', [SpinWheelController::class, 'spin'])
            ->name('api.v1.rewards.spin');

        /*
        |--------------------------------------------------------------------------
        | Gamification - Reward Wallet
        |--------------------------------------------------------------------------
        */

        // List my reward claims
        Route::get('me/rewards', [RewardWalletController::class, 'index'])
            ->name('api.v1.me.rewards');

        // Confirm redemption (static route must come before parameterized route)
        Route::post('reward-claims/confirm-redeem', [RewardWalletController::class, 'confirmRedeem'])
            ->name('api.v1.reward-claims.confirm-redeem');

        // Generate redeem QR token for a reward claim
        Route::post('reward-claims/{rewardClaim}/generate-redeem-qr', [RewardWalletController::class, 'generateRedeemQr'])
            ->name('api.v1.reward-claims.generate-redeem-qr');

        /*
        |--------------------------------------------------------------------------
        | Gamification - Stats & Game Card
        |--------------------------------------------------------------------------
        */

        // My gamification stats
        Route::get('me/gamification-stats', [GamificationStatsController::class, 'myStats'])
            ->name('api.v1.me.gamification-stats');

        // Public game card for a profile
        Route::get('profiles/{profile}/game-card', [GamificationStatsController::class, 'gameCard'])
            ->name('api.v1.profiles.game-card');

        /*
        |--------------------------------------------------------------------------
        | Gamification - Badges
        |--------------------------------------------------------------------------
        */

        // List all system badges
        Route::get('badges', [BadgeController::class, 'index'])
            ->name('api.v1.badges.index');

        // My awarded badges
        Route::get('me/badges', [BadgeController::class, 'myBadges'])
            ->name('api.v1.me.badges');

        /*
        |--------------------------------------------------------------------------
        | Public Profile
        |--------------------------------------------------------------------------
        */

        // Look up a profile's public card by exact email or @handle — must be
        // registered before the {profile} wildcard so "lookup" is not captured
        // as a route-model-bound profile.
        Route::get('profiles/lookup', [ProfileController::class, 'lookup'])
            ->name('api.v1.profiles.lookup');

        // View public profile
        Route::get('profiles/{profile}', [ProfileController::class, 'publicProfile'])
            ->name('api.v1.profiles.show');

        // View profile's received reviews
        Route::get('profiles/{profile}/reviews', [ProfileController::class, 'profileReviews'])
            ->name('api.v1.profiles.reviews');

        // View public-facing community profile
        Route::get('communities/{community}/public-profile', [ProfileController::class, 'communityPublicProfile'])
            ->name('api.v1.communities.public-profile');

        // View profile's completed collaborations
        Route::get('profiles/{profile}/collaborations', [ProfileController::class, 'profileCollaborations'])
            ->name('api.v1.profiles.collaborations');

        /*
        |--------------------------------------------------------------------------
        | Friends (member-to-member friend graph, NF-17)
        |--------------------------------------------------------------------------
        */

        // Send a friend request (auto-accepts a reverse pending request)
        Route::post('friends/{profile}', [FriendshipController::class, 'store'])
            ->name('api.v1.friends.store');

        // Accept an incoming friend request
        Route::post('friends/{profile}/accept', [FriendshipController::class, 'accept'])
            ->name('api.v1.friends.accept');

        // Decline an incoming friend request
        Route::post('friends/{profile}/decline', [FriendshipController::class, 'decline'])
            ->name('api.v1.friends.decline');

        // Remove a friend or cancel an outgoing request
        Route::delete('friends/{profile}', [FriendshipController::class, 'destroy'])
            ->name('api.v1.friends.destroy');

        // List my accepted friends (paginated)
        Route::get('me/friends', [FriendshipController::class, 'index'])
            ->name('api.v1.me.friends');

        // List my incoming friend requests ({data, count})
        Route::get('me/friend-requests', [FriendshipController::class, 'requests'])
            ->name('api.v1.me.friend-requests');

        /*
        |--------------------------------------------------------------------------
        | Opportunities
        |--------------------------------------------------------------------------
        */

        // Role-aware discovery feed for Explore
        Route::get('discovery/opportunities', DiscoveryOpportunityController::class)
            ->name('api.v1.discovery.opportunities');

        // Browse opportunities (public list of published)
        Route::get('opportunities', [OpportunityController::class, 'index'])
            ->name('api.v1.opportunities.index');

        // My opportunities
        Route::get('me/opportunities', [OpportunityController::class, 'myOpportunities'])
            ->name('api.v1.me.opportunities');

        // Single opportunity
        Route::get('opportunities/{opportunity}', [OpportunityController::class, 'show'])
            ->name('api.v1.opportunities.show');

        // Create opportunity
        Route::post('opportunities', [OpportunityController::class, 'store'])
            ->name('api.v1.opportunities.store');

        // Update opportunity
        Route::put('opportunities/{opportunity}', [OpportunityController::class, 'update'])
            ->name('api.v1.opportunities.update');

        // Delete opportunity
        Route::delete('opportunities/{opportunity}', [OpportunityController::class, 'destroy'])
            ->name('api.v1.opportunities.destroy');

        // Publish opportunity
        Route::post('opportunities/{opportunity}/publish', [OpportunityController::class, 'publish'])
            ->name('api.v1.opportunities.publish');

        // Close opportunity
        Route::post('opportunities/{opportunity}/close', [OpportunityController::class, 'close'])
            ->name('api.v1.opportunities.close');

        /*
        |--------------------------------------------------------------------------
        | Kolabs
        |--------------------------------------------------------------------------
        */

        // Browse published kolabs
        Route::get('kolabs', [KolabController::class, 'index'])
            ->name('api.v1.kolabs.index');

        // My kolabs (MUST be before {kolab})
        Route::get('kolabs/me', [KolabController::class, 'myKolabs'])
            ->name('api.v1.kolabs.me');

        // Create kolab
        Route::post('kolabs', [KolabController::class, 'store'])
            ->name('api.v1.kolabs.store');

        // Single kolab
        Route::get('kolabs/{kolab}', [KolabController::class, 'show'])
            ->name('api.v1.kolabs.show');

        // Update kolab
        Route::put('kolabs/{kolab}', [KolabController::class, 'update'])
            ->name('api.v1.kolabs.update');

        // Delete kolab
        Route::delete('kolabs/{kolab}', [KolabController::class, 'destroy'])
            ->name('api.v1.kolabs.destroy');

        // Publish kolab
        Route::post('kolabs/{kolab}/publish', [KolabController::class, 'publish'])
            ->name('api.v1.kolabs.publish');

        // Close kolab
        Route::post('kolabs/{kolab}/close', [KolabController::class, 'close'])
            ->name('api.v1.kolabs.close');

        // List applications for a kolab (creator only)
        Route::get('kolabs/{kolab}/applications', [ApplicationController::class, 'forOpportunity'])
            ->name('api.v1.kolabs.applications.index');

        // Apply to a kolab
        Route::post('kolabs/{kolab}/applications', [ApplicationController::class, 'store'])
            ->name('api.v1.kolabs.applications.store');

        /*
        |--------------------------------------------------------------------------
        | Applications
        |--------------------------------------------------------------------------
        */

        // List applications for an opportunity (creator only)
        Route::get('opportunities/{opportunity}/applications', [ApplicationController::class, 'forOpportunity'])
            ->name('api.v1.opportunities.applications.index');

        // Apply to opportunity
        Route::post('opportunities/{opportunity}/applications', [ApplicationController::class, 'store'])
            ->name('api.v1.opportunities.applications.store');

        // Get application details
        Route::get('applications/{application}', [ApplicationController::class, 'show'])
            ->name('api.v1.applications.show');

        // Accept application
        Route::post('applications/{application}/accept', [ApplicationController::class, 'accept'])
            ->name('api.v1.applications.accept');

        // Decline application
        Route::post('applications/{application}/decline', [ApplicationController::class, 'decline'])
            ->name('api.v1.applications.decline');

        // Withdraw application
        Route::post('applications/{application}/withdraw', [ApplicationController::class, 'withdraw'])
            ->name('api.v1.applications.withdraw');

        // My sent applications
        Route::get('me/applications', [ApplicationController::class, 'myApplications'])
            ->name('api.v1.me.applications');

        // Received applications
        Route::get('me/received-applications', [ApplicationController::class, 'receivedApplications'])
            ->name('api.v1.me.received-applications');

        /*
        |--------------------------------------------------------------------------
        | Chat Messages
        |--------------------------------------------------------------------------
        */

        // Get chat messages for an application
        Route::get('applications/{application}/messages', [ChatController::class, 'index'])
            ->name('api.v1.applications.messages.index');

        // Send a chat message
        Route::post('applications/{application}/messages', [ChatController::class, 'store'])
            ->name('api.v1.applications.messages.store');

        // Mark messages as read
        Route::post('applications/{application}/messages/read', [ChatController::class, 'markAsRead'])
            ->name('api.v1.applications.messages.read');

        // Get unread message count
        Route::get('me/unread-messages-count', [ChatController::class, 'unreadCount'])
            ->name('api.v1.me.unread-messages-count');

        // NF-CHAT Phase 1 — active-chats inbox (role-scoped thread list)
        Route::get('chats', [ChatController::class, 'chats'])
            ->name('api.v1.chats.index');
        Route::get('chats/unread-count', [ChatController::class, 'unreadCountAcrossThreads'])
            ->name('api.v1.chats.unread-count');

        // NF-CHAT Phase 2 — community chats + generic thread messages
        Route::post('communities/{community}/chats', [ChatController::class, 'storeCommunityChat'])
            ->name('api.v1.communities.chats.store');
        // Rename / delete a custom community chat (manager only)
        Route::patch('chats/{thread}', [ChatController::class, 'updateCommunityChat'])
            ->name('api.v1.chats.update');
        Route::delete('chats/{thread}', [ChatController::class, 'destroyCommunityChat'])
            ->name('api.v1.chats.destroy');
        // Per-member chat block/unblock (manager only)
        Route::get('chats/{thread}/bans', [ChatController::class, 'bans'])
            ->name('api.v1.chats.bans.index');
        Route::post('chats/{thread}/bans', [ChatController::class, 'storeBan'])
            ->name('api.v1.chats.bans.store');
        Route::delete('chats/{thread}/bans/{profile}', [ChatController::class, 'destroyBan'])
            ->name('api.v1.chats.bans.destroy');
        Route::get('chats/{thread}/messages', [ChatController::class, 'threadMessages'])
            ->name('api.v1.chats.messages.index');
        Route::post('chats/{thread}/messages', [ChatController::class, 'storeThreadMessage'])
            ->name('api.v1.chats.messages.store');
        Route::post('chats/{thread}/read', [ChatController::class, 'readThread'])
            ->name('api.v1.chats.read');

        /*
        |--------------------------------------------------------------------------
        | Notifications
        |--------------------------------------------------------------------------
        */

        // List notifications
        Route::get('me/notifications', [NotificationController::class, 'index'])
            ->name('api.v1.me.notifications');

        // Unread count
        Route::get('me/notifications/unread-count', [NotificationController::class, 'unreadCount'])
            ->name('api.v1.me.notifications.unread-count');

        // Mark single as read
        Route::post('me/notifications/{notification}/read', [NotificationController::class, 'markAsRead'])
            ->name('api.v1.me.notifications.read');

        // Mark all as read
        Route::post('me/notifications/read-all', [NotificationController::class, 'markAllAsRead'])
            ->name('api.v1.me.notifications.read-all');

        /*
        |--------------------------------------------------------------------------
        | Collaborations
        |--------------------------------------------------------------------------
        */

        // List my collaborations
        Route::get('collaborations', [CollaborationController::class, 'index'])
            ->name('api.v1.collaborations.index');

        // Get collaboration details
        Route::get('collaborations/{collaboration}', [CollaborationController::class, 'show'])
            ->name('api.v1.collaborations.show');

        // Activate collaboration
        Route::post('collaborations/{collaboration}/activate', [CollaborationController::class, 'activate'])
            ->name('api.v1.collaborations.activate');

        // Complete collaboration
        Route::post('collaborations/{collaboration}/complete', [CollaborationController::class, 'complete'])
            ->name('api.v1.collaborations.complete');

        // Cancel collaboration
        Route::post('collaborations/{collaboration}/cancel', [CollaborationController::class, 'cancel'])
            ->name('api.v1.collaborations.cancel');

        // Leave a review for a completed collaboration (one per reviewer, idempotent)
        Route::post('collaborations/{collaboration}/review', [CollaborationController::class, 'review'])
            ->name('api.v1.collaborations.review');

        // Rich, role-specific completion feedback (gates the /complete endpoint).
        Route::post('collaborations/{collaboration}/feedback', [CollaborationController::class, 'feedback'])
            ->name('api.v1.collaborations.feedback.store');
        Route::put('collaborations/{collaboration}/feedback', [CollaborationController::class, 'updateFeedback'])
            ->name('api.v1.collaborations.feedback.update');

        // Sync selected challenges for a collaboration
        Route::put('collaborations/{collaboration}/challenges', [CollaborationChallengeController::class, 'sync'])
            ->name('api.v1.collaborations.challenges.sync');

        // Create custom challenge for a collaboration
        Route::post('collaborations/{collaboration}/challenges', [CollaborationChallengeController::class, 'store'])
            ->name('api.v1.collaborations.challenges.store');

        // Business-set bonus on a per-challenge basis (business participant only)
        Route::put('collaborations/{collaboration}/challenges/{challenge}/bonus', [CollaborationChallengeBonusController::class, 'upsert'])
            ->name('api.v1.collaborations.challenges.bonus.upsert');
        Route::delete('collaborations/{collaboration}/challenges/{challenge}/bonus', [CollaborationChallengeBonusController::class, 'destroy'])
            ->name('api.v1.collaborations.challenges.bonus.destroy');

        // Generate QR code for a collaboration
        Route::post('collaborations/{collaboration}/qr-code', [CollaborationQrCodeController::class, 'store'])
            ->name('api.v1.collaborations.qr-code');

        // List system challenges
        Route::get('challenges/system', SystemChallengeController::class)
            ->name('api.v1.challenges.system');

        /*
        |--------------------------------------------------------------------------
        | Generic File Uploads
        |--------------------------------------------------------------------------
        */

        Route::post('uploads', [UploadController::class, 'store'])
            ->name('api.v1.uploads.store');

        /*
        |--------------------------------------------------------------------------
        | Gamification - Wallet & Rewards
        |--------------------------------------------------------------------------
        */

        Route::get('gamification/wallet', [GamificationController::class, 'wallet'])
            ->name('api.v1.gamification.wallet');

        Route::get('gamification/ledger', [GamificationController::class, 'ledger'])
            ->name('api.v1.gamification.ledger');

        Route::get('gamification/badges', [GamificationController::class, 'badges'])
            ->name('api.v1.gamification.badges');

        // Server-owned XP economy (levels + per-action awards). Replaces the
        // app's hardcoded ladder and earn-amount labels. Cached, busted on
        // admin write via XpEarnRuleService / XpLevelService.
        Route::get('gamification/config', GamificationConfigController::class)
            ->name('api.v1.gamification.config');

        Route::get('gamification/referral-code', [GamificationController::class, 'referralCode'])
            ->name('api.v1.gamification.referral-code');

        Route::post('gamification/withdrawal', [GamificationController::class, 'withdrawal'])
            ->name('api.v1.gamification.withdrawal');
    });
});
