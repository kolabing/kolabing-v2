<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\ApplicationController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ChallengeCompletionController;
use App\Http\Controllers\Api\V1\ChallengeController;
use App\Http\Controllers\Api\V1\ChatController;
use App\Http\Controllers\Api\V1\CheckinController;
use App\Http\Controllers\Api\V1\CollaborationController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\EventController;
use App\Http\Controllers\Api\V1\EventRewardController;
use App\Http\Controllers\Api\V1\GalleryController;
use App\Http\Controllers\Api\V1\LeaderboardController;
use App\Http\Controllers\Api\V1\LookupController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\NotificationPreferenceController;
use App\Http\Controllers\Api\V1\OnboardingController;
use App\Http\Controllers\Api\V1\OpportunityController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\SpinWheelController;
use App\Http\Controllers\Api\V1\StripeWebhookController;
use App\Http\Controllers\Api\V1\RewardWalletController;
use App\Http\Controllers\Api\V1\SubscriptionController;
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

    Route::post('auth/register/business', [AuthController::class, 'registerBusiness'])
        ->name('api.v1.auth.register.business');

    Route::post('auth/register/community', [AuthController::class, 'registerCommunity'])
        ->name('api.v1.auth.register.community');

    Route::post('auth/register/attendee', [AuthController::class, 'registerAttendee'])
        ->name('api.v1.auth.register.attendee');

    Route::post('auth/login', [AuthController::class, 'login'])
        ->name('api.v1.auth.login');

    Route::post('auth/forgot-password', [AuthController::class, 'forgotPassword'])
        ->name('api.v1.auth.forgot-password');

    Route::post('auth/reset-password', [AuthController::class, 'resetPassword'])
        ->name('api.v1.auth.reset-password');

    // Stripe Webhook
    Route::post('webhooks/stripe', StripeWebhookController::class)
        ->name('api.v1.webhooks.stripe');

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

        /*
        |--------------------------------------------------------------------------
        | Profile Management
        |--------------------------------------------------------------------------
        */

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

        // Create Stripe checkout session
        Route::post('me/subscription/checkout', [SubscriptionController::class, 'checkout'])
            ->name('api.v1.me.subscription.checkout');

        // Get Stripe billing portal URL
        Route::get('me/subscription/portal', [SubscriptionController::class, 'portal'])
            ->name('api.v1.me.subscription.portal');

        // Cancel subscription at period end
        Route::post('me/subscription/cancel', [SubscriptionController::class, 'cancel'])
            ->name('api.v1.me.subscription.cancel');

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

        // Get single event
        Route::get('events/{event}', [EventController::class, 'show'])
            ->name('api.v1.events.show');

        // Create event
        Route::post('events', [EventController::class, 'store'])
            ->name('api.v1.events.store');

        // Update event
        Route::put('events/{event}', [EventController::class, 'update'])
            ->name('api.v1.events.update');

        // Delete event
        Route::delete('events/{event}', [EventController::class, 'destroy'])
            ->name('api.v1.events.destroy');

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

        // Global leaderboard
        Route::get('leaderboard/global', [LeaderboardController::class, 'globalLeaderboard'])
            ->name('api.v1.leaderboard.global');

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
        | Public Profile
        |--------------------------------------------------------------------------
        */

        // View public profile
        Route::get('profiles/{profile}', [ProfileController::class, 'publicProfile'])
            ->name('api.v1.profiles.show');

        // View profile's completed collaborations
        Route::get('profiles/{profile}/collaborations', [ProfileController::class, 'profileCollaborations'])
            ->name('api.v1.profiles.collaborations');

        /*
        |--------------------------------------------------------------------------
        | Opportunities
        |--------------------------------------------------------------------------
        */

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
    });
});
