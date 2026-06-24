<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\ApplicationStatus;
use App\Enums\CollaborationStatus;
use App\Enums\NotificationType;
use App\Enums\SubscriptionSource;
use App\Enums\SubscriptionStatus;
use App\Enums\UserType;
use App\Models\Application;
use App\Models\BusinessSubscription;
use App\Models\Collaboration;
use App\Models\Kolab;
use App\Models\Notification;
use App\Models\Profile;
use App\Services\CollaborationFeedbackService;
use App\Services\CollaborationService;
use App\Services\PushNotificationService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use Tests\TestCase;

class CollaborationCompletionNotificationTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Build a business creator + community applicant + scheduled collaboration.
     *
     * @return array{collab: Collaboration, business: Profile, community: Profile, kolab: Kolab}
     */
    private function makeCollab(CollaborationStatus $status = CollaborationStatus::Scheduled): array
    {
        Queue::fake();

        $business = Profile::factory()->business()->create();
        BusinessSubscription::query()->updateOrCreate(
            ['profile_id' => $business->id],
            ['source' => SubscriptionSource::Maintainer, 'status' => SubscriptionStatus::Active],
        );

        $community = Profile::factory()->community()->create();

        $kolab = Kolab::factory()->published()->create([
            'creator_profile_id' => $business->id,
            'title' => 'Summer Pop-Up',
        ]);

        $application = Application::factory()->create([
            'kolab_id' => $kolab->id,
            'applicant_profile_id' => $community->id,
            'applicant_profile_type' => UserType::Community,
            'status' => ApplicationStatus::Accepted,
        ]);

        $collab = Collaboration::factory()->state(['status' => $status])->create([
            'kolab_id' => $kolab->id,
            'application_id' => $application->id,
            'creator_profile_id' => $business->id,
            'applicant_profile_id' => $community->id,
            'business_profile_id' => $business->businessProfile?->id,
            'community_profile_id' => $community->communityProfile?->id,
            'activated_at' => $status === CollaborationStatus::Active ? now() : null,
        ]);

        return ['collab' => $collab, 'business' => $business, 'community' => $community, 'kolab' => $kolab];
    }

    private function row(string $profileId, NotificationType $type, string $collabId): Notification
    {
        $notification = Notification::query()
            ->where('profile_id', $profileId)
            ->where('type', $type)
            ->where('target_type', 'collaboration')
            ->where('target_id', $collabId)
            ->first();

        $this->assertNotNull($notification, "Expected {$type->value} notification for {$profileId}");

        return $notification;
    }

    public function test_activate_notifies_both_parties_with_actor_aware_copy(): void
    {
        ['collab' => $collab, 'business' => $business, 'community' => $community] = $this->makeCollab();

        // Business creator activates.
        app(CollaborationService::class)->activate($collab, $business);

        $actorRow = $this->row($business->id, NotificationType::CollaborationActivated, $collab->id);
        $counterpartRow = $this->row($community->id, NotificationType::CollaborationActivated, $collab->id);

        $this->assertSame('You marked the collaboration for "Summer Pop-Up" as active.', $actorRow->body);
        $this->assertStringContainsString('marked your collaboration for "Summer Pop-Up" as active.', $counterpartRow->body);
        $this->assertStringNotContainsString('You marked', $counterpartRow->body);
    }

    public function test_complete_notifies_both_parties_with_actor_aware_copy(): void
    {
        ['collab' => $collab, 'business' => $business, 'community' => $community] = $this->makeCollab(CollaborationStatus::Active);

        config()->set('collaborations.complete_requires_feedback', false);

        app(CollaborationService::class)->complete($collab, $community);

        $actorRow = $this->row($community->id, NotificationType::CollaborationCompleted, $collab->id);
        $counterpartRow = $this->row($business->id, NotificationType::CollaborationCompleted, $collab->id);

        $this->assertSame('You marked the collaboration for "Summer Pop-Up" as complete.', $actorRow->body);
        $this->assertStringContainsString('marked your collaboration for "Summer Pop-Up" as complete.', $counterpartRow->body);
    }

    public function test_auto_complete_notifies_both_parties_with_shared_auto_copy(): void
    {
        ['collab' => $collab, 'business' => $business, 'community' => $community] = $this->makeCollab(CollaborationStatus::Active);

        app(CollaborationService::class)->autoComplete($collab);

        $expected = 'Your collaboration for "Summer Pop-Up" was automatically marked complete.';

        $this->assertSame($expected, $this->row($business->id, NotificationType::CollaborationCompleted, $collab->id)->body);
        $this->assertSame($expected, $this->row($community->id, NotificationType::CollaborationCompleted, $collab->id)->body);
    }

    public function test_cancel_notifies_both_parties_with_actor_aware_copy(): void
    {
        ['collab' => $collab, 'business' => $business, 'community' => $community] = $this->makeCollab(CollaborationStatus::Active);

        app(CollaborationService::class)->cancel($collab, 'Venue closed', $business);

        $actorRow = $this->row($business->id, NotificationType::CollaborationCancelled, $collab->id);
        $counterpartRow = $this->row($community->id, NotificationType::CollaborationCancelled, $collab->id);

        $this->assertSame('You cancelled the collaboration for "Summer Pop-Up".', $actorRow->body);
        $this->assertStringContainsString('cancelled your collaboration for "Summer Pop-Up".', $counterpartRow->body);
    }

    public function test_admin_force_complete_notifies_both_parties(): void
    {
        ['collab' => $collab, 'business' => $business, 'community' => $community] = $this->makeCollab(CollaborationStatus::Active);

        app(CollaborationService::class)->adminForceComplete($collab, 'Resolved manually');

        $this->row($business->id, NotificationType::CollaborationCompleted, $collab->id);
        $this->row($community->id, NotificationType::CollaborationCompleted, $collab->id);
    }

    public function test_admin_force_complete_uses_actor_less_automatic_copy(): void
    {
        ['collab' => $collab, 'business' => $business, 'community' => $community] = $this->makeCollab(CollaborationStatus::Active);

        app(CollaborationService::class)->adminForceComplete($collab, 'Resolved manually');

        $expected = 'Your collaboration for "Summer Pop-Up" was automatically marked complete.';

        $this->assertSame($expected, $this->row($business->id, NotificationType::CollaborationCompleted, $collab->id)->body);
        $this->assertSame($expected, $this->row($community->id, NotificationType::CollaborationCompleted, $collab->id)->body);
    }

    public function test_completion_notification_is_localized_per_recipient(): void
    {
        ['collab' => $collab, 'business' => $business, 'community' => $community] = $this->makeCollab(CollaborationStatus::Active);

        $community->update(['preferred_locale' => 'es']);

        app(CollaborationService::class)->autoComplete($collab);

        $spanishRow = $this->row($community->id, NotificationType::CollaborationCompleted, $collab->id);
        $this->assertSame(
            'Tu colaboración para "Summer Pop-Up" se ha marcado automáticamente como completada.',
            $spanishRow->body,
        );
        $this->assertSame('Colaboración completada', $spanishRow->title);

        // The null-locale business recipient still gets the English fallback copy.
        $englishRow = $this->row($business->id, NotificationType::CollaborationCompleted, $collab->id);
        $this->assertSame(
            'Your collaboration for "Summer Pop-Up" was automatically marked complete.',
            $englishRow->body,
        );
        $this->assertSame('Collaboration completed', $englishRow->title);
    }

    public function test_feedback_submission_notifies_both_parties_with_actor_aware_copy(): void
    {
        ['collab' => $collab, 'business' => $business, 'community' => $community] = $this->makeCollab(CollaborationStatus::Active);

        app(CollaborationFeedbackService::class)->submit($collab, $business, [
            'rating' => 5,
            'expectation_match' => true,
            'would_recommend' => true,
            'posts_reels' => 2,
            'stories_posted' => 4,
            'revenue' => '100.00',
        ]);

        $actorRow = $this->row($business->id, NotificationType::CollaborationFeedbackReceived, $collab->id);
        $counterpartRow = $this->row($community->id, NotificationType::CollaborationFeedbackReceived, $collab->id);

        $this->assertSame('Feedback submitted', $actorRow->title);
        $this->assertSame('Your feedback for "Summer Pop-Up" has been recorded.', $actorRow->body);
        $this->assertSame('New feedback', $counterpartRow->title);
        $this->assertStringContainsString('left feedback for your collaboration "Summer Pop-Up".', $counterpartRow->body);
    }

    public function test_created_notification_dispatched_to_both_parties(): void
    {
        Queue::fake();

        $business = Profile::factory()->business()->create();
        BusinessSubscription::query()->updateOrCreate(
            ['profile_id' => $business->id],
            ['source' => SubscriptionSource::Maintainer, 'status' => SubscriptionStatus::Active],
        );
        $community = Profile::factory()->community()->create();

        $kolab = Kolab::factory()->published()->create([
            'creator_profile_id' => $business->id,
            'title' => 'Summer Pop-Up',
        ]);

        $application = Application::factory()->create([
            'kolab_id' => $kolab->id,
            'applicant_profile_id' => $community->id,
            'applicant_profile_type' => UserType::Community,
            'status' => ApplicationStatus::Accepted,
        ]);

        $collab = app(CollaborationService::class)->createFromApplication($application);

        $expected = 'Your collaboration for "Summer Pop-Up" is set up. Tap to view the details.';
        $this->assertSame($expected, $this->row($business->id, NotificationType::CollaborationCreated, $collab->id)->body);
        $this->assertSame($expected, $this->row($community->id, NotificationType::CollaborationCreated, $collab->id)->body);
        $this->assertSame('Collaboration started', $this->row($business->id, NotificationType::CollaborationCreated, $collab->id)->title);
    }

    /**
     * @return iterable<string, array{0: NotificationType}>
     */
    public static function collaborationDeeplinkTypes(): iterable
    {
        yield 'created' => [NotificationType::CollaborationCreated];
        yield 'activated' => [NotificationType::CollaborationActivated];
        yield 'feedback' => [NotificationType::CollaborationFeedbackReceived];
        yield 'completed' => [NotificationType::CollaborationCompleted];
        yield 'cancelled' => [NotificationType::CollaborationCancelled];
        yield 'day_reminder' => [NotificationType::CollabDayReminder];
        yield 'followup_reminder' => [NotificationType::CollabFollowUpReminder];
    }

    #[DataProvider('collaborationDeeplinkTypes')]
    public function test_resolve_deeplink_points_to_collaboration_detail(NotificationType $type): void
    {
        $service = app(PushNotificationService::class);

        $method = new ReflectionMethod($service, 'resolveDeeplink');
        $method->setAccessible(true);

        $this->assertSame('/collaboration/abc-123', $method->invoke($service, $type, 'abc-123'));
    }
}
