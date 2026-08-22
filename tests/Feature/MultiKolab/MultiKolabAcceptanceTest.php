<?php

declare(strict_types=1);

namespace Tests\Feature\MultiKolab;

use App\Enums\ApplicationStatus;
use App\Enums\CollaborationStatus;
use App\Enums\KolabStatus;
use App\Enums\MultiKolabEligibleAccountType;
use App\Enums\MultiKolabRoleApplicationStatus;
use App\Enums\MultiKolabRoleStatus;
use App\Exceptions\RoleCapacityExceededException;
use App\Models\Application;
use App\Models\Collaboration;
use App\Models\Kolab;
use App\Models\MultiKolabEvent;
use App\Models\MultiKolabRole;
use App\Models\MultiKolabRoleApplication;
use App\Models\Profile;
use App\Services\MultiKolabRoleApplicationService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class MultiKolabAcceptanceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function service(): MultiKolabRoleApplicationService
    {
        return app(MultiKolabRoleApplicationService::class);
    }

    /**
     * @return array{event: MultiKolabEvent, role: MultiKolabRole, application: MultiKolabRoleApplication, organizer: Profile, applicant: Profile}
     */
    private function pendingApplication(int $positionsNeeded = 1): array
    {
        $organizer = Profile::factory()->business()->create();
        $event = MultiKolabEvent::factory()->recruiting()->for($organizer, 'creatorProfile')->create();
        $role = MultiKolabRole::factory()->for($event, 'event')->create([
            'eligible_account_type' => MultiKolabEligibleAccountType::Either,
            'status' => MultiKolabRoleStatus::Open,
            'positions_needed' => $positionsNeeded,
            'positions_filled' => 0,
        ]);
        $applicant = Profile::factory()->community()->create();
        $application = $this->service()->apply($role, $applicant, ['pitch' => 'We would love to partner.']);

        return compact('event', 'role', 'application', 'organizer', 'applicant');
    }

    // --- Happy path: one Kolab + accepted canonical Application + Collaboration

    public function test_accepting_creates_exactly_one_child_kolab_application_and_collaboration(): void
    {
        ['role' => $role, 'application' => $application, 'organizer' => $organizer, 'applicant' => $applicant] = $this->pendingApplication();

        $kolab = $this->service()->accept($application, $organizer);

        $this->assertInstanceOf(Kolab::class, $kolab);
        $this->assertSame(KolabStatus::Published, $kolab->status);
        $this->assertSame($organizer->id, $kolab->creator_profile_id);
        $this->assertSame($role->event->id, $kolab->multi_kolab_event_id);
        $this->assertSame($role->id, $kolab->multi_kolab_role_id);

        $this->assertSame(1, Application::query()->where('kolab_id', $kolab->id)->count());
        $canonicalApplication = Application::query()->where('kolab_id', $kolab->id)->firstOrFail();
        $this->assertSame(ApplicationStatus::Accepted, $canonicalApplication->status);
        $this->assertSame($applicant->id, $canonicalApplication->applicant_profile_id);

        $this->assertSame(1, Collaboration::query()->where('kolab_id', $kolab->id)->count());
        $collaboration = Collaboration::query()->where('kolab_id', $kolab->id)->firstOrFail();
        $this->assertSame(CollaborationStatus::Scheduled, $collaboration->status);
        $this->assertSame($canonicalApplication->id, $collaboration->application_id);

        $freshApplication = $application->fresh();
        $this->assertSame(MultiKolabRoleApplicationStatus::Accepted, $freshApplication->status);
        $this->assertSame($kolab->id, $freshApplication->kolab_id);
        $this->assertNotNull($freshApplication->accepted_at);

        $freshRole = $role->fresh();
        $this->assertSame(1, $freshRole->positions_filled);
        $this->assertSame(MultiKolabRoleStatus::Filled, $freshRole->status);
    }

    public function test_accepting_never_checks_business_subscription_or_event_creator_entitlement_on_the_applicant(): void
    {
        ['application' => $application, 'organizer' => $organizer, 'applicant' => $applicant] = $this->pendingApplication();
        $this->assertFalse($applicant->hasActiveSubscription());
        $this->assertFalse($applicant->hasEventCreatorEntitlement());

        $kolab = $this->service()->accept($application, $organizer);

        $this->assertSame(KolabStatus::Published, $kolab->status);
    }

    // --- Idempotency ---------------------------------------------------------

    public function test_repeated_acceptance_returns_the_same_kolab_without_incrementing_capacity(): void
    {
        ['role' => $role, 'application' => $application, 'organizer' => $organizer] = $this->pendingApplication();

        $first = $this->service()->accept($application, $organizer);
        $second = $this->service()->accept($application->fresh(), $organizer);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, $role->fresh()->positions_filled);
        $this->assertSame(1, Kolab::query()->where('multi_kolab_role_id', $role->id)->count());
        $this->assertSame(1, Application::query()->where('kolab_id', $first->id)->count());
        $this->assertSame(1, Collaboration::query()->where('kolab_id', $first->id)->count());
    }

    // --- Ownership -------------------------------------------------------------

    public function test_only_the_organizer_can_accept(): void
    {
        ['application' => $application] = $this->pendingApplication();
        $stranger = Profile::factory()->business()->create();

        $this->expectException(InvalidArgumentException::class);
        $this->service()->accept($application, $stranger);
    }

    // --- Capacity ---------------------------------------------------------------

    public function test_accepting_a_role_already_at_capacity_is_rejected(): void
    {
        ['role' => $role, 'organizer' => $organizer] = $this->pendingApplication(1);
        $role->update(['positions_filled' => 1, 'status' => MultiKolabRoleStatus::Filled]);

        $secondApplicant = Profile::factory()->community()->create();
        // Bypass service->apply() eligibility (role is Filled) to construct a
        // second pending application directly, simulating one that was
        // shortlisted before the role filled.
        $secondApplication = MultiKolabRoleApplication::factory()
            ->for($role, 'role')
            ->for($secondApplicant, 'applicantProfile')
            ->create(['status' => MultiKolabRoleApplicationStatus::Pending]);

        $this->expectException(RoleCapacityExceededException::class);
        $this->service()->accept($secondApplication, $organizer);
    }

    public function test_two_sequential_accepts_on_a_one_position_role_yield_one_success_one_conflict(): void
    {
        ['role' => $role, 'application' => $firstApplication, 'organizer' => $organizer] = $this->pendingApplication(1);
        $secondApplicant = Profile::factory()->community()->create();
        $secondApplication = MultiKolabRoleApplication::factory()
            ->for($role, 'role')
            ->for($secondApplicant, 'applicantProfile')
            ->create(['status' => MultiKolabRoleApplicationStatus::Pending]);

        $this->service()->accept($firstApplication, $organizer);

        $this->expectException(RoleCapacityExceededException::class);
        $this->service()->accept($secondApplication, $organizer);
    }

    public function test_accepting_only_marks_role_filled_once_capacity_is_fully_reached(): void
    {
        ['role' => $role, 'application' => $firstApplication, 'organizer' => $organizer] = $this->pendingApplication(2);
        $secondApplicant = Profile::factory()->community()->create();
        $secondApplication = MultiKolabRoleApplication::factory()
            ->for($role, 'role')
            ->for($secondApplicant, 'applicantProfile')
            ->create(['status' => MultiKolabRoleApplicationStatus::Pending]);

        $this->service()->accept($firstApplication, $organizer);
        $this->assertSame(MultiKolabRoleStatus::Open, $role->fresh()->status);

        $this->service()->accept($secondApplication, $organizer);
        $this->assertSame(MultiKolabRoleStatus::Filled, $role->fresh()->status);
        $this->assertSame(2, $role->fresh()->positions_filled);
    }

    // --- Cannot accept a non-pending/shortlisted application --------------------

    public function test_cannot_accept_a_declined_application(): void
    {
        ['application' => $application, 'organizer' => $organizer] = $this->pendingApplication();
        $this->service()->decline($application, $organizer);

        $this->expectException(InvalidArgumentException::class);
        $this->service()->accept($application->fresh(), $organizer);
    }

    // --- Existing Application/Collaboration regression not broken ---------------

    public function test_ordinary_kolab_application_acceptance_is_unaffected(): void
    {
        $creator = Profile::factory()->business()->create();
        \App\Models\BusinessSubscription::query()->create([
            'profile_id' => $creator->id,
            'source' => \App\Enums\SubscriptionSource::Maintainer,
            'status' => \App\Enums\SubscriptionStatus::Active,
        ]);
        $applicant = Profile::factory()->community()->create();
        $kolab = \App\Models\Kolab::factory()->create([
            'creator_profile_id' => $creator->id,
            'status' => KolabStatus::Published,
            'intent_type' => \App\Enums\IntentType::VenuePromotion,
        ]);
        $application = Application::factory()->create([
            'kolab_id' => $kolab->id,
            'status' => ApplicationStatus::Pending,
        ]);

        $result = app(\App\Services\ApplicationService::class)->accept($application);

        $this->assertSame(ApplicationStatus::Accepted, $result['application']->status);
        $this->assertSame(CollaborationStatus::Scheduled, $result['collaboration']->status);
    }
}
