<?php

declare(strict_types=1);

namespace Tests\Feature\MultiKolab;

use App\Enums\MultiKolabEligibleAccountType;
use App\Enums\MultiKolabRoleApplicationStatus;
use App\Enums\MultiKolabRoleStatus;
use App\Exceptions\DuplicateRoleApplicationException;
use App\Models\MultiKolabEvent;
use App\Models\MultiKolabRole;
use App\Models\MultiKolabRoleApplication;
use App\Models\Profile;
use App\Services\MultiKolabRoleApplicationService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class MultiKolabRoleApplicationTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function service(): MultiKolabRoleApplicationService
    {
        return app(MultiKolabRoleApplicationService::class);
    }

    private function recruitingRole(MultiKolabEligibleAccountType $eligible): MultiKolabRole
    {
        $event = MultiKolabEvent::factory()->recruiting()->create();

        return MultiKolabRole::factory()->for($event, 'event')->create([
            'eligible_account_type' => $eligible,
            'status' => MultiKolabRoleStatus::Open,
            'positions_needed' => 1,
            'positions_filled' => 0,
        ]);
    }

    // --- Eligibility ------------------------------------------------------

    public function test_business_only_role_accepts_business_applicant(): void
    {
        $role = $this->recruitingRole(MultiKolabEligibleAccountType::Business);
        $applicant = Profile::factory()->business()->create();

        $application = $this->service()->apply($role, $applicant, ['pitch' => 'We would love to partner.']);

        $this->assertSame(MultiKolabRoleApplicationStatus::Pending, $application->status);
    }

    public function test_business_only_role_rejects_community_applicant(): void
    {
        $role = $this->recruitingRole(MultiKolabEligibleAccountType::Business);
        $applicant = Profile::factory()->community()->create();

        $this->expectException(InvalidArgumentException::class);
        $this->service()->apply($role, $applicant, ['pitch' => 'We would love to partner.']);
    }

    public function test_community_only_role_accepts_community_applicant(): void
    {
        $role = $this->recruitingRole(MultiKolabEligibleAccountType::Community);
        $applicant = Profile::factory()->community()->create();

        $application = $this->service()->apply($role, $applicant, ['pitch' => 'We would love to partner.']);

        $this->assertSame(MultiKolabRoleApplicationStatus::Pending, $application->status);
    }

    public function test_community_only_role_rejects_business_applicant(): void
    {
        $role = $this->recruitingRole(MultiKolabEligibleAccountType::Community);
        $applicant = Profile::factory()->business()->create();

        $this->expectException(InvalidArgumentException::class);
        $this->service()->apply($role, $applicant, ['pitch' => 'We would love to partner.']);
    }

    public function test_either_role_accepts_both_business_and_community(): void
    {
        $roleForBusiness = $this->recruitingRole(MultiKolabEligibleAccountType::Either);
        $business = Profile::factory()->business()->create();
        $this->assertSame(
            MultiKolabRoleApplicationStatus::Pending,
            $this->service()->apply($roleForBusiness, $business, ['pitch' => 'Pitch.'])->status,
        );

        $roleForCommunity = $this->recruitingRole(MultiKolabEligibleAccountType::Either);
        $community = Profile::factory()->community()->create();
        $this->assertSame(
            MultiKolabRoleApplicationStatus::Pending,
            $this->service()->apply($roleForCommunity, $community, ['pitch' => 'Pitch.'])->status,
        );
    }

    // --- Never gated by subscription or entitlement ------------------------

    public function test_applying_never_checks_business_subscription_or_event_creator_entitlement(): void
    {
        $role = $this->recruitingRole(MultiKolabEligibleAccountType::Either);
        $applicant = Profile::factory()->business()->create();

        $this->assertFalse($applicant->hasActiveSubscription());
        $this->assertFalse($applicant->hasEventCreatorEntitlement());

        $application = $this->service()->apply($role, $applicant, ['pitch' => 'Pitch.']);

        $this->assertSame(MultiKolabRoleApplicationStatus::Pending, $application->status);
    }

    // --- Cannot apply to own event's role -----------------------------------

    public function test_organizer_cannot_apply_to_their_own_event_role(): void
    {
        $event = MultiKolabEvent::factory()->recruiting()->create();
        $role = MultiKolabRole::factory()->for($event, 'event')->create([
            'eligible_account_type' => MultiKolabEligibleAccountType::Either,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->service()->apply($role, $event->creatorProfile, ['pitch' => 'Pitch.']);
    }

    // --- Requires a non-draft, recruiting event / open role -----------------

    public function test_cannot_apply_to_a_role_on_a_draft_event(): void
    {
        $event = MultiKolabEvent::factory()->create(); // draft
        $role = MultiKolabRole::factory()->for($event, 'event')->create([
            'eligible_account_type' => MultiKolabEligibleAccountType::Either,
        ]);
        $applicant = Profile::factory()->community()->create();

        $this->expectException(InvalidArgumentException::class);
        $this->service()->apply($role, $applicant, ['pitch' => 'Pitch.']);
    }

    public function test_cannot_apply_to_a_filled_role(): void
    {
        $role = $this->recruitingRole(MultiKolabEligibleAccountType::Either);
        $role->update(['status' => MultiKolabRoleStatus::Filled]);
        $applicant = Profile::factory()->community()->create();

        $this->expectException(InvalidArgumentException::class);
        $this->service()->apply($role, $applicant, ['pitch' => 'Pitch.']);
    }

    // --- Pitch required ------------------------------------------------------

    public function test_apply_requires_a_pitch(): void
    {
        $role = $this->recruitingRole(MultiKolabEligibleAccountType::Either);
        $applicant = Profile::factory()->community()->create();

        $this->expectException(InvalidArgumentException::class);
        $this->service()->apply($role, $applicant, ['pitch' => '']);
    }

    // --- Unique (role, applicant) — deterministic conflict -------------------

    public function test_duplicate_application_is_rejected_deterministically(): void
    {
        $role = $this->recruitingRole(MultiKolabEligibleAccountType::Either);
        $applicant = Profile::factory()->community()->create();
        $this->service()->apply($role, $applicant, ['pitch' => 'Pitch.']);

        $this->expectException(DuplicateRoleApplicationException::class);
        $this->service()->apply($role, $applicant, ['pitch' => 'Second try.']);
    }

    public function test_the_db_unique_constraint_backstops_the_pre_check(): void
    {
        $role = $this->recruitingRole(MultiKolabEligibleAccountType::Either);
        $applicant = Profile::factory()->community()->create();
        MultiKolabRoleApplication::factory()->for($role, 'role')->for($applicant, 'applicantProfile')->create();

        $this->expectException(QueryException::class);
        MultiKolabRoleApplication::factory()->for($role, 'role')->for($applicant, 'applicantProfile')->create();
    }

    // --- Owner-only shortlist / decline --------------------------------------

    public function test_only_the_organizer_can_shortlist(): void
    {
        $role = $this->recruitingRole(MultiKolabEligibleAccountType::Either);
        $applicant = Profile::factory()->community()->create();
        $application = $this->service()->apply($role, $applicant, ['pitch' => 'Pitch.']);
        $stranger = Profile::factory()->business()->create();

        $this->assertFalse($stranger->can('shortlist', $application));
        $this->assertTrue($role->event->creatorProfile->can('shortlist', $application));

        $shortlisted = $this->service()->shortlist($application, $role->event->creatorProfile);
        $this->assertSame(MultiKolabRoleApplicationStatus::Shortlisted, $shortlisted->status);
    }

    public function test_only_the_organizer_can_decline(): void
    {
        $role = $this->recruitingRole(MultiKolabEligibleAccountType::Either);
        $applicant = Profile::factory()->community()->create();
        $application = $this->service()->apply($role, $applicant, ['pitch' => 'Pitch.']);
        $stranger = Profile::factory()->business()->create();

        $this->assertFalse($stranger->can('decline', $application));

        $declined = $this->service()->decline($application, $role->event->creatorProfile);
        $this->assertSame(MultiKolabRoleApplicationStatus::Declined, $declined->status);
    }

    // --- Applicant-only withdrawal --------------------------------------------

    public function test_only_the_applicant_can_withdraw(): void
    {
        $role = $this->recruitingRole(MultiKolabEligibleAccountType::Either);
        $applicant = Profile::factory()->community()->create();
        $application = $this->service()->apply($role, $applicant, ['pitch' => 'Pitch.']);

        $this->assertFalse($role->event->creatorProfile->can('withdraw', $application));
        $this->assertTrue($applicant->can('withdraw', $application));

        $withdrawn = $this->service()->withdraw($application, $applicant, null);
        $this->assertSame(MultiKolabRoleApplicationStatus::Withdrawn, $withdrawn->status);
    }

    // --- Withdrawal reason required only when withdrawing an accepted app ----

    public function test_withdrawing_a_pending_application_does_not_require_a_reason(): void
    {
        $role = $this->recruitingRole(MultiKolabEligibleAccountType::Either);
        $applicant = Profile::factory()->community()->create();
        $application = $this->service()->apply($role, $applicant, ['pitch' => 'Pitch.']);

        $withdrawn = $this->service()->withdraw($application, $applicant, null);

        $this->assertSame(MultiKolabRoleApplicationStatus::Withdrawn, $withdrawn->status);
        $this->assertNull($withdrawn->withdrawal_reason);
    }

    public function test_withdrawing_an_accepted_application_requires_a_reason(): void
    {
        $role = $this->recruitingRole(MultiKolabEligibleAccountType::Either);
        $role->update(['status' => MultiKolabRoleStatus::Filled, 'positions_filled' => 1]);
        $applicant = Profile::factory()->community()->create();
        $application = MultiKolabRoleApplication::factory()
            ->for($role, 'role')
            ->for($applicant, 'applicantProfile')
            ->accepted()
            ->create();

        $this->expectException(InvalidArgumentException::class);
        $this->service()->withdraw($application, $applicant, null);
    }

    // --- Transactional withdrawal: decrement + reopen role, never below zero -

    public function test_withdrawing_an_accepted_application_decrements_positions_filled_and_reopens_role(): void
    {
        $role = $this->recruitingRole(MultiKolabEligibleAccountType::Either);
        $role->update(['status' => MultiKolabRoleStatus::Filled, 'positions_filled' => 1]);
        $applicant = Profile::factory()->community()->create();
        $application = MultiKolabRoleApplication::factory()
            ->for($role, 'role')
            ->for($applicant, 'applicantProfile')
            ->accepted()
            ->create();

        $withdrawn = $this->service()->withdraw($application, $applicant, 'No longer available.');

        $this->assertSame(MultiKolabRoleApplicationStatus::Withdrawn, $withdrawn->status);
        $this->assertSame('No longer available.', $withdrawn->withdrawal_reason);

        $freshRole = $role->fresh();
        $this->assertSame(0, $freshRole->positions_filled);
        $this->assertSame(MultiKolabRoleStatus::Open, $freshRole->status);
    }

    public function test_positions_filled_never_drops_below_zero(): void
    {
        $role = $this->recruitingRole(MultiKolabEligibleAccountType::Either);
        // Simulate an already-inconsistent role (defensive floor, should
        // never happen via normal acceptance flow — Task 6).
        $role->update(['positions_filled' => 0]);
        $applicant = Profile::factory()->community()->create();
        $application = MultiKolabRoleApplication::factory()
            ->for($role, 'role')
            ->for($applicant, 'applicantProfile')
            ->accepted()
            ->create();

        $this->service()->withdraw($application, $applicant, 'Reason.');

        $this->assertSame(0, $role->fresh()->positions_filled);
    }
}
