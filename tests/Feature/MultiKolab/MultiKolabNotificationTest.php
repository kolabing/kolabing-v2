<?php

declare(strict_types=1);

namespace Tests\Feature\MultiKolab;

use App\Enums\CollaborationStatus;
use App\Enums\NotificationType;
use App\Jobs\SendPostHogEvent;
use App\Models\MultiKolabEvent;
use App\Models\MultiKolabRole;
use App\Models\Notification;
use App\Models\Profile;
use App\Services\MultiKolabEventService;
use App\Services\MultiKolabRoleApplicationService;
use App\Services\OrganizerEntitlementService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MultiKolabNotificationTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('posthog.enabled', true);
        config()->set('posthog.project_api_key', 'phc_test');
    }

    private function eventService(): MultiKolabEventService
    {
        return app(MultiKolabEventService::class);
    }

    private function applicationService(): MultiKolabRoleApplicationService
    {
        return app(MultiKolabRoleApplicationService::class);
    }

    private function entitle(Profile $profile): void
    {
        app(OrganizerEntitlementService::class)->grant($profile);
    }

    /**
     * @return array{organizer: Profile, event: MultiKolabEvent, role: MultiKolabRole}
     */
    private function recruitingEventWithRole(int $positionsNeeded = 1): array
    {
        $organizer = Profile::factory()->business()->create();
        $this->entitle($organizer);
        $event = $this->eventService()->createDraft($organizer, [
            'title' => 'Launch Weekend',
            'description' => 'A great event.',
        ]);
        $role = $this->eventService()->addRole($event, [
            'title' => 'Partner', 'eligible_account_type' => 'either', 'positions_needed' => $positionsNeeded,
        ]);
        $published = $this->eventService()->publish($event->fresh(), $organizer);

        return ['organizer' => $organizer, 'event' => $published, 'role' => $role];
    }

    private function assertExactlyOneNotification(Profile $recipient, NotificationType $type, string $targetId): void
    {
        $this->assertSame(1, Notification::query()
            ->where('profile_id', $recipient->id)
            ->where('type', $type)
            ->where('target_id', $targetId)
            ->count());
    }

    // --- Application received --------------------------------------------------

    public function test_applying_notifies_the_organizer_exactly_once(): void
    {
        Queue::fake();
        ['organizer' => $organizer, 'role' => $role] = $this->recruitingEventWithRole();
        $applicant = Profile::factory()->community()->create();

        $application = $this->applicationService()->apply($role, $applicant, ['pitch' => 'Pitch.']);

        $this->assertExactlyOneNotification($organizer, NotificationType::MultiKolabApplicationReceived, $application->id);
        Queue::assertPushed(SendPostHogEvent::class, fn (SendPostHogEvent $job): bool => $job->event === 'role_application_submitted');
    }

    // --- Accepted / declined -----------------------------------------------------

    public function test_accepting_notifies_the_applicant_exactly_once_even_on_retry(): void
    {
        Queue::fake();
        ['organizer' => $organizer, 'role' => $role] = $this->recruitingEventWithRole();
        $applicant = Profile::factory()->community()->create();
        $application = $this->applicationService()->apply($role, $applicant, ['pitch' => 'Pitch.']);

        $this->applicationService()->accept($application, $organizer);
        // Retry — must not send a second notification (idempotent accept()).
        $this->applicationService()->accept($application->fresh(), $organizer);

        $this->assertExactlyOneNotification($applicant, NotificationType::MultiKolabApplicantAccepted, $application->id);
        Queue::assertPushed(SendPostHogEvent::class, function (SendPostHogEvent $job): bool {
            return $job->event === 'applicant_accepted';
        });
        $this->assertSame(1, collect(Queue::pushed(SendPostHogEvent::class))
            ->filter(fn (SendPostHogEvent $job): bool => $job->event === 'applicant_accepted')
            ->count());
    }

    public function test_declining_notifies_the_applicant_exactly_once(): void
    {
        Queue::fake();
        ['role' => $role, 'organizer' => $organizer] = $this->recruitingEventWithRole();
        $applicant = Profile::factory()->community()->create();
        $application = $this->applicationService()->apply($role, $applicant, ['pitch' => 'Pitch.']);

        $this->applicationService()->decline($application, $organizer);

        $this->assertExactlyOneNotification($applicant, NotificationType::MultiKolabApplicantDeclined, $application->id);
    }

    // --- Role filled ---------------------------------------------------------------

    public function test_role_filled_notifies_the_organizer_exactly_once(): void
    {
        Queue::fake();
        ['organizer' => $organizer, 'role' => $role] = $this->recruitingEventWithRole(1);
        $applicant = Profile::factory()->community()->create();
        $application = $this->applicationService()->apply($role, $applicant, ['pitch' => 'Pitch.']);

        $this->applicationService()->accept($application, $organizer);

        $this->assertExactlyOneNotification($organizer, NotificationType::MultiKolabRoleFilled, $role->id);
        Queue::assertPushed(SendPostHogEvent::class, fn (SendPostHogEvent $job): bool => $job->event === 'role_filled');
    }

    public function test_role_filled_notification_not_sent_until_capacity_is_fully_reached(): void
    {
        Queue::fake();
        ['organizer' => $organizer, 'role' => $role] = $this->recruitingEventWithRole(2);
        $applicantA = Profile::factory()->community()->create();
        $applicationA = $this->applicationService()->apply($role, $applicantA, ['pitch' => 'A']);

        $this->applicationService()->accept($applicationA, $organizer);

        $this->assertSame(0, Notification::query()
            ->where('type', NotificationType::MultiKolabRoleFilled)
            ->count());
    }

    // --- Partner withdrawal (post-acceptance only) ------------------------------

    public function test_withdrawing_an_accepted_application_notifies_the_organizer_exactly_once(): void
    {
        Queue::fake();
        ['organizer' => $organizer, 'role' => $role] = $this->recruitingEventWithRole();
        $applicant = Profile::factory()->community()->create();
        $application = $this->applicationService()->apply($role, $applicant, ['pitch' => 'Pitch.']);
        $this->applicationService()->accept($application, $organizer);

        $this->applicationService()->withdraw($application->fresh(), $applicant, 'Change of plans.');

        $this->assertExactlyOneNotification($organizer, NotificationType::MultiKolabPartnerWithdrew, $application->id);
        Queue::assertPushed(SendPostHogEvent::class, fn (SendPostHogEvent $job): bool => $job->event === 'partner_withdrew');
    }

    public function test_withdrawing_a_pending_application_does_not_notify_the_organizer(): void
    {
        Queue::fake();
        ['role' => $role] = $this->recruitingEventWithRole();
        $applicant = Profile::factory()->community()->create();
        $application = $this->applicationService()->apply($role, $applicant, ['pitch' => 'Pitch.']);

        $this->applicationService()->withdraw($application, $applicant, null);

        $this->assertSame(0, Notification::query()
            ->where('type', NotificationType::MultiKolabPartnerWithdrew)
            ->count());
    }

    // --- Event confirmed / cancelled --------------------------------------------

    public function test_confirming_notifies_every_accepted_partner_exactly_once(): void
    {
        Queue::fake();
        ['organizer' => $organizer, 'event' => $event, 'role' => $role] = $this->recruitingEventWithRole();
        $applicant = Profile::factory()->community()->create();
        $application = $this->applicationService()->apply($role, $applicant, ['pitch' => 'Pitch.']);
        $this->applicationService()->accept($application, $organizer);

        $this->eventService()->confirm($event->fresh(), $organizer);

        $this->assertExactlyOneNotification($applicant, NotificationType::MultiKolabEventConfirmed, $event->id);
        Queue::assertPushed(SendPostHogEvent::class, fn (SendPostHogEvent $job): bool => $job->event === 'event_confirmed');
    }

    public function test_cancelling_notifies_every_accepted_partner_exactly_once(): void
    {
        Queue::fake();
        ['organizer' => $organizer, 'event' => $event, 'role' => $role] = $this->recruitingEventWithRole();
        $applicant = Profile::factory()->community()->create();
        $application = $this->applicationService()->apply($role, $applicant, ['pitch' => 'Pitch.']);
        $this->applicationService()->accept($application, $organizer);

        $this->eventService()->cancel($event->fresh(), $organizer, 'Venue fell through.');

        $this->assertExactlyOneNotification($applicant, NotificationType::MultiKolabEventCancelled, $event->id);
        Queue::assertPushed(SendPostHogEvent::class, fn (SendPostHogEvent $job): bool => $job->event === 'event_cancelled');
    }

    public function test_cancelling_a_draft_with_no_accepted_partners_sends_no_partner_notification(): void
    {
        Queue::fake();
        $organizer = Profile::factory()->business()->create();
        $event = $this->eventService()->createDraft($organizer, ['title' => 'Launch Weekend']);

        $this->eventService()->cancel($event, $organizer, 'Change of plans.');

        $this->assertSame(0, Notification::query()->where('type', NotificationType::MultiKolabEventCancelled)->count());
    }

    // --- draft_started / role_added / event_published PostHog ------------------

    public function test_draft_started_role_added_and_event_published_analytics(): void
    {
        Queue::fake();
        $organizer = Profile::factory()->business()->create();
        $this->entitle($organizer);

        $event = $this->eventService()->createDraft($organizer, ['title' => 'Launch Weekend', 'description' => 'Desc']);
        Queue::assertPushed(SendPostHogEvent::class, fn (SendPostHogEvent $job): bool => $job->event === 'draft_started');

        $this->eventService()->addRole($event, ['title' => 'Partner', 'eligible_account_type' => 'either']);
        Queue::assertPushed(SendPostHogEvent::class, fn (SendPostHogEvent $job): bool => $job->event === 'role_added');

        $this->eventService()->publish($event->fresh(), $organizer);
        Queue::assertPushed(SendPostHogEvent::class, fn (SendPostHogEvent $job): bool => $job->event === 'event_published');
    }

    // --- child_kolab_completed ----------------------------------------------------

    public function test_completing_a_child_kolab_collaboration_emits_child_kolab_completed(): void
    {
        Queue::fake();
        ['organizer' => $organizer, 'role' => $role] = $this->recruitingEventWithRole();
        $applicant = Profile::factory()->community()->create();
        $application = $this->applicationService()->apply($role, $applicant, ['pitch' => 'Pitch.']);
        $kolab = $this->applicationService()->accept($application, $organizer);
        $collaboration = $kolab->collaborations()->firstOrFail();

        $collaboration->update(['status' => CollaborationStatus::Completed, 'completed_at' => now()]);

        Queue::assertPushed(SendPostHogEvent::class, fn (SendPostHogEvent $job): bool => $job->event === 'child_kolab_completed');
    }

    public function test_completing_an_ordinary_kolab_collaboration_does_not_emit_child_kolab_completed(): void
    {
        Queue::fake();
        $creator = Profile::factory()->business()->create();
        \App\Models\BusinessSubscription::query()->create([
            'profile_id' => $creator->id,
            'source' => \App\Enums\SubscriptionSource::Maintainer,
            'status' => \App\Enums\SubscriptionStatus::Active,
        ]);
        $kolab = \App\Models\Kolab::factory()->published()->create(['creator_profile_id' => $creator->id]);
        $application = \App\Models\Application::factory()->create(['kolab_id' => $kolab->id, 'status' => 'pending']);
        $result = app(\App\Services\ApplicationService::class)->accept($application);

        $result['collaboration']->update(['status' => CollaborationStatus::Completed, 'completed_at' => now()]);

        Queue::assertNotPushed(SendPostHogEvent::class, fn (SendPostHogEvent $job): bool => $job->event === 'child_kolab_completed');
    }

    // --- Incomplete-draft reminder cap: 24h, 72h, never a third, never after publish

    public function test_draft_reminder_fires_at_24h_and_72h_and_never_a_third_time(): void
    {
        Queue::fake();
        $organizer = Profile::factory()->business()->create();
        $event = $this->eventService()->createDraft($organizer, ['title' => 'Launch Weekend']);

        $this->assertDatabaseHas('notification_reminders', [
            'profile_id' => $organizer->id,
            'type' => NotificationType::MultiKolabEventDraftIncomplete->value,
            'entity_id' => $event->id,
            'entity_type' => 'multi_kolab_event',
            'cancelled_at' => null,
        ]);

        $this->travel(20)->hours();
        $this->artisan('notifications:send-reminders')->assertExitCode(0);
        $this->assertSame(0, Notification::query()->where('type', NotificationType::MultiKolabEventDraftIncomplete)->count());

        $this->travel(5)->hours(); // now ~25h since creation
        $this->artisan('notifications:send-reminders')->assertExitCode(0);
        $this->assertSame(1, Notification::query()->where('type', NotificationType::MultiKolabEventDraftIncomplete)->count());

        $this->travel(47)->hours(); // ~72h
        $this->artisan('notifications:send-reminders')->assertExitCode(0);
        $this->assertSame(2, Notification::query()->where('type', NotificationType::MultiKolabEventDraftIncomplete)->count());

        $this->travel(100)->hours(); // long past 72h — must never fire a 3rd
        $this->artisan('notifications:send-reminders')->assertExitCode(0);
        $this->assertSame(2, Notification::query()->where('type', NotificationType::MultiKolabEventDraftIncomplete)->count());
    }

    public function test_draft_reminder_never_fires_after_publish(): void
    {
        Queue::fake();
        $organizer = Profile::factory()->business()->create();
        $this->entitle($organizer);
        $event = $this->eventService()->createDraft($organizer, ['title' => 'Launch Weekend', 'description' => 'Desc']);
        $this->eventService()->addRole($event, ['title' => 'Partner', 'eligible_account_type' => 'either']);
        $this->eventService()->publish($event->fresh(), $organizer);

        $this->travel(80)->hours();
        $this->artisan('notifications:send-reminders')->assertExitCode(0);

        $this->assertSame(0, Notification::query()->where('type', NotificationType::MultiKolabEventDraftIncomplete)->count());
    }

    public function test_draft_reminder_never_fires_after_cancel(): void
    {
        Queue::fake();
        $organizer = Profile::factory()->business()->create();
        $event = $this->eventService()->createDraft($organizer, ['title' => 'Launch Weekend']);
        $this->eventService()->cancel($event, $organizer, 'Changed my mind.');

        $this->travel(80)->hours();
        $this->artisan('notifications:send-reminders')->assertExitCode(0);

        $this->assertSame(0, Notification::query()->where('type', NotificationType::MultiKolabEventDraftIncomplete)->count());
    }
}
