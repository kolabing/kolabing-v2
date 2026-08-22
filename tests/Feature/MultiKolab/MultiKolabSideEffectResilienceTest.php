<?php

declare(strict_types=1);

namespace Tests\Feature\MultiKolab;

use App\Enums\MultiKolabEligibleAccountType;
use App\Enums\MultiKolabRoleApplicationStatus;
use App\Enums\MultiKolabRoleStatus;
use App\Models\Application;
use App\Models\Collaboration;
use App\Models\Kolab;
use App\Models\MultiKolabEvent;
use App\Models\MultiKolabRole;
use App\Models\Profile;
use App\Services\MultiKolabEventService;
use App\Services\MultiKolabRoleApplicationService;
use App\Services\NotificationService;
use App\Services\OrganizerEntitlementService;
use App\Services\PostHog\PostHogService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use Mockery;
use Tests\TestCase;

/**
 * Proves a failure in a notification/analytics side effect never rolls back
 * or invalidates committed Multi-Kolab domain state, and never surfaces as
 * an uncaught 500 for an operation that actually succeeded. Mirrors the
 * resilience pattern already established in
 * {@see \App\Services\ApplicationService::accept()} (post-commit,
 * try/catch + report()).
 */
class MultiKolabSideEffectResilienceTest extends TestCase
{
    use LazilyRefreshDatabase;

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
     * Bind a NotificationService partial mock where exactly one method
     * throws; everything else behaves normally.
     */
    private function makeNotificationServiceThrowOn(string $method): void
    {
        $mock = Mockery::mock(NotificationService::class)->makePartial();
        $mock->shouldReceive($method)->andThrow(new \RuntimeException("boom: {$method}"));
        $this->app->instance(NotificationService::class, $mock);
    }

    private function makePostHogThrow(): void
    {
        $mock = Mockery::mock(PostHogService::class)->makePartial();
        $mock->shouldReceive('capture')->andThrow(new \RuntimeException('boom: posthog'));
        $this->app->instance(PostHogService::class, $mock);
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

    // --- accept(): notification failure -----------------------------------

    public function test_accept_survives_notification_failure_and_commits_full_state(): void
    {
        Exceptions::fake();
        ['organizer' => $organizer, 'role' => $role] = $this->recruitingEventWithRole();
        $applicant = Profile::factory()->community()->create();
        $application = $this->applicationService()->apply($role, $applicant, ['pitch' => 'Pitch.']);

        $this->makeNotificationServiceThrowOn('notifyMultiKolabApplicantAccepted');

        $kolab = $this->applicationService()->accept($application, $organizer);

        $this->assertInstanceOf(Kolab::class, $kolab);
        $this->assertSame(1, Application::query()->where('kolab_id', $kolab->id)->where('status', 'accepted')->count());
        $this->assertSame(1, Collaboration::query()->where('kolab_id', $kolab->id)->where('status', 'scheduled')->count());
        $this->assertSame(MultiKolabRoleApplicationStatus::Accepted, $application->fresh()->status);
        $this->assertSame($kolab->id, $application->fresh()->kolab_id);
        $this->assertSame(1, $role->fresh()->positions_filled);
        $this->assertSame(MultiKolabRoleStatus::Filled, $role->fresh()->status);

        Exceptions::assertReported(fn (\RuntimeException $e): bool => $e->getMessage() === 'boom: notifyMultiKolabApplicantAccepted');
    }

    // --- accept(): PostHog failure --------------------------------------------

    public function test_accept_survives_posthog_failure_and_commits_full_state(): void
    {
        Exceptions::fake();
        ['organizer' => $organizer, 'role' => $role] = $this->recruitingEventWithRole();
        $applicant = Profile::factory()->community()->create();
        $application = $this->applicationService()->apply($role, $applicant, ['pitch' => 'Pitch.']);

        $this->makePostHogThrow();

        $kolab = $this->applicationService()->accept($application, $organizer);

        $this->assertInstanceOf(Kolab::class, $kolab);
        $this->assertSame(MultiKolabRoleApplicationStatus::Accepted, $application->fresh()->status);
        $this->assertSame(1, $role->fresh()->positions_filled);
        $this->assertSame(MultiKolabRoleStatus::Filled, $role->fresh()->status);

        Exceptions::assertReported(fn (\RuntimeException $e): bool => $e->getMessage() === 'boom: posthog');
    }

    // --- accept(): retry after a side-effect failure does not duplicate ------

    public function test_accept_retry_after_notification_failure_does_not_duplicate_records_or_capacity(): void
    {
        Exceptions::fake();
        ['organizer' => $organizer, 'role' => $role] = $this->recruitingEventWithRole();
        $applicant = Profile::factory()->community()->create();
        $application = $this->applicationService()->apply($role, $applicant, ['pitch' => 'Pitch.']);

        $this->makeNotificationServiceThrowOn('notifyMultiKolabApplicantAccepted');
        $first = $this->applicationService()->accept($application, $organizer);

        // Retry — the first call already committed and returned successfully
        // (the notification failure did not raise), so this is a genuine
        // idempotent replay, not a recovery from a failed accept.
        $second = $this->applicationService()->accept($application->fresh(), $organizer);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, $role->fresh()->positions_filled);
        $this->assertSame(1, Kolab::query()->where('multi_kolab_role_id', $role->id)->count());
        $this->assertSame(1, Application::query()->where('kolab_id', $first->id)->count());
        $this->assertSame(1, Collaboration::query()->where('kolab_id', $first->id)->count());
    }

    // --- accept(): role_filled notification failure doesn't block the applicant one

    public function test_role_filled_notification_failure_does_not_prevent_applicant_accepted_notification(): void
    {
        Exceptions::fake();
        ['organizer' => $organizer, 'role' => $role] = $this->recruitingEventWithRole(1);
        $applicant = Profile::factory()->community()->create();
        $application = $this->applicationService()->apply($role, $applicant, ['pitch' => 'Pitch.']);

        $mock = Mockery::mock(NotificationService::class)->makePartial();
        $mock->shouldReceive('notifyMultiKolabRoleFilled')->andThrow(new \RuntimeException('boom: role filled'));
        $mock->shouldReceive('notifyMultiKolabApplicantAccepted')->once()->passthru();
        $this->app->instance(NotificationService::class, $mock);

        $this->applicationService()->accept($application, $organizer);

        $this->assertSame(1, \App\Models\Notification::query()
            ->where('profile_id', $applicant->id)
            ->where('type', \App\Enums\NotificationType::MultiKolabApplicantAccepted)
            ->count());
        Exceptions::assertReported(fn (\RuntimeException $e): bool => $e->getMessage() === 'boom: role filled');
    }

    // --- Accepted-partner withdrawal: notification failure --------------------

    public function test_withdraw_accepted_survives_notification_failure_and_commits_state(): void
    {
        Exceptions::fake();
        ['organizer' => $organizer, 'role' => $role] = $this->recruitingEventWithRole();
        $applicant = Profile::factory()->community()->create();
        $application = $this->applicationService()->apply($role, $applicant, ['pitch' => 'Pitch.']);
        $this->applicationService()->accept($application, $organizer);

        $this->makeNotificationServiceThrowOn('notifyMultiKolabPartnerWithdrew');

        $withdrawn = $this->applicationService()->withdraw($application->fresh(), $applicant, 'Change of plans.');

        $this->assertSame(MultiKolabRoleApplicationStatus::Withdrawn, $withdrawn->status);
        $this->assertSame('Change of plans.', $withdrawn->withdrawal_reason);
        $this->assertSame(0, $role->fresh()->positions_filled);
        $this->assertSame(MultiKolabRoleStatus::Open, $role->fresh()->status);

        Exceptions::assertReported(fn (\RuntimeException $e): bool => $e->getMessage() === 'boom: notifyMultiKolabPartnerWithdrew');
    }

    // --- Accepted-partner withdrawal: PostHog failure --------------------------

    public function test_withdraw_accepted_survives_posthog_failure_and_commits_state(): void
    {
        Exceptions::fake();
        ['organizer' => $organizer, 'role' => $role] = $this->recruitingEventWithRole();
        $applicant = Profile::factory()->community()->create();
        $application = $this->applicationService()->apply($role, $applicant, ['pitch' => 'Pitch.']);
        $this->applicationService()->accept($application, $organizer);

        $this->makePostHogThrow();

        $withdrawn = $this->applicationService()->withdraw($application->fresh(), $applicant, 'Change of plans.');

        $this->assertSame(MultiKolabRoleApplicationStatus::Withdrawn, $withdrawn->status);
        $this->assertSame(0, $role->fresh()->positions_filled);

        Exceptions::assertReported(fn (\RuntimeException $e): bool => $e->getMessage() === 'boom: posthog');
    }

    // --- Withdraw retry never decrements capacity twice -------------------------

    public function test_withdraw_retry_does_not_decrement_capacity_again(): void
    {
        ['organizer' => $organizer, 'role' => $role] = $this->recruitingEventWithRole();
        $applicant = Profile::factory()->community()->create();
        $application = $this->applicationService()->apply($role, $applicant, ['pitch' => 'Pitch.']);
        $this->applicationService()->accept($application, $organizer);

        $this->applicationService()->withdraw($application->fresh(), $applicant, 'Reason.');

        $this->expectException(\InvalidArgumentException::class);
        // A second withdraw on an already-Withdrawn application must be
        // rejected outright (status guard), never re-decrement capacity.
        $this->applicationService()->withdraw($application->fresh(), $applicant, 'Reason again.');
    }

    public function test_withdraw_retry_after_capacity_decrement_leaves_positions_filled_unchanged(): void
    {
        ['organizer' => $organizer, 'role' => $role] = $this->recruitingEventWithRole();
        $applicant = Profile::factory()->community()->create();
        $application = $this->applicationService()->apply($role, $applicant, ['pitch' => 'Pitch.']);
        $this->applicationService()->accept($application, $organizer);
        $this->applicationService()->withdraw($application->fresh(), $applicant, 'Reason.');

        try {
            $this->applicationService()->withdraw($application->fresh(), $applicant, 'Reason again.');
        } catch (\InvalidArgumentException) {
            // expected
        }

        $this->assertSame(0, $role->fresh()->positions_filled);
    }

    // --- apply(): notification failure never surfaces as an uncaught 500 -------

    public function test_apply_survives_notification_failure(): void
    {
        Exceptions::fake();
        ['role' => $role] = $this->recruitingEventWithRole();
        $applicant = Profile::factory()->community()->create();

        $this->makeNotificationServiceThrowOn('notifyMultiKolabApplicationReceived');

        $application = $this->applicationService()->apply($role, $applicant, ['pitch' => 'Pitch.']);

        $this->assertSame(MultiKolabRoleApplicationStatus::Pending, $application->status);
        $this->assertDatabaseHas('multi_kolab_role_applications', ['id' => $application->id]);
        Exceptions::assertReported(fn (\RuntimeException $e): bool => $e->getMessage() === 'boom: notifyMultiKolabApplicationReceived');
    }

    public function test_apply_survives_posthog_failure(): void
    {
        Exceptions::fake();
        ['role' => $role] = $this->recruitingEventWithRole();
        $applicant = Profile::factory()->community()->create();

        $this->makePostHogThrow();

        $application = $this->applicationService()->apply($role, $applicant, ['pitch' => 'Pitch.']);

        $this->assertSame(MultiKolabRoleApplicationStatus::Pending, $application->status);
        Exceptions::assertReported(fn (\RuntimeException $e): bool => $e->getMessage() === 'boom: posthog');
    }

    // --- shortlist(): analytics failure ----------------------------------------

    public function test_shortlist_survives_posthog_failure(): void
    {
        Exceptions::fake();
        ['organizer' => $organizer, 'role' => $role] = $this->recruitingEventWithRole();
        $applicant = Profile::factory()->community()->create();
        $application = $this->applicationService()->apply($role, $applicant, ['pitch' => 'Pitch.']);

        $this->makePostHogThrow();

        $shortlisted = $this->applicationService()->shortlist($application, $organizer);

        $this->assertSame(MultiKolabRoleApplicationStatus::Shortlisted, $shortlisted->status);
        Exceptions::assertReported(fn (\RuntimeException $e): bool => $e->getMessage() === 'boom: posthog');
    }

    // --- decline(): notification failure ----------------------------------------

    public function test_decline_survives_notification_failure(): void
    {
        Exceptions::fake();
        ['organizer' => $organizer, 'role' => $role] = $this->recruitingEventWithRole();
        $applicant = Profile::factory()->community()->create();
        $application = $this->applicationService()->apply($role, $applicant, ['pitch' => 'Pitch.']);

        $this->makeNotificationServiceThrowOn('notifyMultiKolabApplicantDeclined');

        $declined = $this->applicationService()->decline($application, $organizer);

        $this->assertSame(MultiKolabRoleApplicationStatus::Declined, $declined->status);
        Exceptions::assertReported(fn (\RuntimeException $e): bool => $e->getMessage() === 'boom: notifyMultiKolabApplicantDeclined');
    }

    // --- confirm(): notification failure -----------------------------------------

    public function test_confirm_survives_notification_failure(): void
    {
        Exceptions::fake();
        ['organizer' => $organizer, 'event' => $event, 'role' => $role] = $this->recruitingEventWithRole();
        $applicant = Profile::factory()->community()->create();
        $application = $this->applicationService()->apply($role, $applicant, ['pitch' => 'Pitch.']);
        $this->applicationService()->accept($application, $organizer);

        $this->makeNotificationServiceThrowOn('notifyMultiKolabEventConfirmed');

        $confirmed = $this->eventService()->confirm($event->fresh(), $organizer);

        $this->assertSame(\App\Enums\MultiKolabEventStatus::Confirmed, $confirmed->status);
        Exceptions::assertReported(fn (\RuntimeException $e): bool => $e->getMessage() === 'boom: notifyMultiKolabEventConfirmed');
    }

    public function test_confirm_survives_posthog_failure(): void
    {
        Exceptions::fake();
        ['organizer' => $organizer, 'event' => $event] = $this->recruitingEventWithRole();

        $this->makePostHogThrow();

        $confirmed = $this->eventService()->confirm($event->fresh(), $organizer);

        $this->assertSame(\App\Enums\MultiKolabEventStatus::Confirmed, $confirmed->status);
        Exceptions::assertReported(fn (\RuntimeException $e): bool => $e->getMessage() === 'boom: posthog');
    }

    // --- cancel(): notification failure -------------------------------------------

    public function test_cancel_survives_notification_failure(): void
    {
        Exceptions::fake();
        ['organizer' => $organizer, 'event' => $event, 'role' => $role] = $this->recruitingEventWithRole();
        $applicant = Profile::factory()->community()->create();
        $application = $this->applicationService()->apply($role, $applicant, ['pitch' => 'Pitch.']);
        $this->applicationService()->accept($application, $organizer);

        $this->makeNotificationServiceThrowOn('notifyMultiKolabEventCancelled');

        $cancelled = $this->eventService()->cancel($event->fresh(), $organizer, 'Venue fell through.');

        $this->assertSame(\App\Enums\MultiKolabEventStatus::Cancelled, $cancelled->status);
        $this->assertSame('Venue fell through.', $cancelled->cancellation_reason);
        Exceptions::assertReported(fn (\RuntimeException $e): bool => $e->getMessage() === 'boom: notifyMultiKolabEventCancelled');
    }

    public function test_cancel_survives_posthog_failure(): void
    {
        Exceptions::fake();
        $organizer = Profile::factory()->business()->create();
        $event = $this->eventService()->createDraft($organizer, ['title' => 'Launch Weekend']);

        $this->makePostHogThrow();

        $cancelled = $this->eventService()->cancel($event, $organizer, 'Change of plans.');

        $this->assertSame(\App\Enums\MultiKolabEventStatus::Cancelled, $cancelled->status);
        Exceptions::assertReported(fn (\RuntimeException $e): bool => $e->getMessage() === 'boom: posthog');
    }

    // --- Stable error codes (Phase 5) ----------------------------------------------

    public function test_ineligible_applicant_gets_a_stable_error_code(): void
    {
        ['role' => $role] = $this->recruitingEventWithRole();
        $role->update(['eligible_account_type' => MultiKolabEligibleAccountType::Business]);
        $applicant = Profile::factory()->community()->create();

        try {
            $this->applicationService()->apply($role, $applicant, ['pitch' => 'Pitch.']);
            $this->fail('Expected rejection.');
        } catch (\App\Exceptions\MultiKolabApplicationRejectedException $e) {
            $this->assertSame('role_ineligible', $e->code());
        }
    }

    public function test_event_not_recruiting_gets_a_stable_error_code(): void
    {
        $organizer = Profile::factory()->business()->create();
        $event = $this->eventService()->createDraft($organizer, ['title' => 'Launch Weekend']);
        $role = $this->eventService()->addRole($event, ['title' => 'Partner', 'eligible_account_type' => 'either']);
        $applicant = Profile::factory()->community()->create();

        try {
            $this->applicationService()->apply($role, $applicant, ['pitch' => 'Pitch.']);
            $this->fail('Expected rejection.');
        } catch (\App\Exceptions\MultiKolabApplicationRejectedException $e) {
            $this->assertSame('event_not_recruiting', $e->code());
        }
    }

    public function test_role_not_open_gets_a_stable_error_code(): void
    {
        ['role' => $role] = $this->recruitingEventWithRole();
        $role->update(['status' => MultiKolabRoleStatus::Closed]);
        $applicant = Profile::factory()->community()->create();

        try {
            $this->applicationService()->apply($role, $applicant, ['pitch' => 'Pitch.']);
            $this->fail('Expected rejection.');
        } catch (\App\Exceptions\MultiKolabApplicationRejectedException $e) {
            $this->assertSame('role_not_open', $e->code());
        }
    }
}
