<?php

declare(strict_types=1);

namespace Tests\Unit\MultiKolab;

use App\Enums\MultiKolabCompensationType;
use App\Enums\MultiKolabEligibleAccountType;
use App\Enums\MultiKolabEventStatus;
use App\Enums\MultiKolabRoleApplicationStatus;
use App\Enums\MultiKolabRoleStatus;
use App\Enums\OrganizerCapability;
use App\Models\Kolab;
use App\Models\MultiKolabEvent;
use App\Models\MultiKolabEventStatusEvent;
use App\Models\MultiKolabRole;
use App\Models\MultiKolabRoleApplication;
use App\Models\OrganizerEntitlement;
use App\Models\Profile;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class MultiKolabModelsTest extends TestCase
{
    use LazilyRefreshDatabase;

    // --- Enum wire values (must match the frozen API contract) -----------

    public function test_multi_kolab_event_status_values_match_contract(): void
    {
        $this->assertSame(
            ['draft', 'recruiting', 'confirmed', 'completed', 'cancelled', 'expired'],
            array_map(fn (MultiKolabEventStatus $c): string => $c->value, MultiKolabEventStatus::cases()),
        );
    }

    public function test_multi_kolab_role_status_values_match_contract(): void
    {
        $this->assertSame(
            ['open', 'filled', 'closed'],
            array_map(fn (MultiKolabRoleStatus $c): string => $c->value, MultiKolabRoleStatus::cases()),
        );
    }

    public function test_multi_kolab_role_application_status_values_match_contract(): void
    {
        $this->assertSame(
            ['pending', 'shortlisted', 'accepted', 'declined', 'withdrawn'],
            array_map(fn (MultiKolabRoleApplicationStatus $c): string => $c->value, MultiKolabRoleApplicationStatus::cases()),
        );
    }

    public function test_multi_kolab_eligible_account_type_values_match_contract(): void
    {
        $this->assertSame(
            ['business', 'community', 'either'],
            array_map(fn (MultiKolabEligibleAccountType $c): string => $c->value, MultiKolabEligibleAccountType::cases()),
        );
    }

    public function test_multi_kolab_compensation_type_values_match_contract(): void
    {
        $this->assertSame(
            ['paid', 'sponsored_in_kind', 'value_exchange', 'negotiable'],
            array_map(fn (MultiKolabCompensationType $c): string => $c->value, MultiKolabCompensationType::cases()),
        );
    }

    public function test_organizer_capability_values_match_contract(): void
    {
        $this->assertSame(
            ['event_creator'],
            array_map(fn (OrganizerCapability $c): string => $c->value, OrganizerCapability::cases()),
        );
    }

    // --- MultiKolabEvent ----------------------------------------------

    public function test_multi_kolab_event_has_uuid_primary_key_and_casts(): void
    {
        $creator = Profile::factory()->business()->create();

        $event = MultiKolabEvent::factory()->for($creator, 'creatorProfile')->create([
            'status' => MultiKolabEventStatus::Draft,
            'eligible_account_type' => MultiKolabEligibleAccountType::Either,
            'venue_needed' => true,
        ]);

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $event->id,
        );
        $this->assertInstanceOf(MultiKolabEventStatus::class, $event->fresh()->status);
        $this->assertInstanceOf(MultiKolabEligibleAccountType::class, $event->fresh()->eligible_account_type);
        $this->assertTrue($event->fresh()->venue_needed);
    }

    public function test_multi_kolab_event_belongs_to_creator_profile(): void
    {
        $creator = Profile::factory()->community()->create();
        $event = MultiKolabEvent::factory()->for($creator, 'creatorProfile')->create();

        $this->assertTrue($event->creatorProfile->is($creator));
    }

    public function test_multi_kolab_event_has_many_roles(): void
    {
        $event = MultiKolabEvent::factory()->create();
        $role = MultiKolabRole::factory()->for($event, 'event')->create();

        $this->assertTrue($event->roles->contains($role));
    }

    public function test_multi_kolab_event_has_many_status_events(): void
    {
        $event = MultiKolabEvent::factory()->create();
        $statusEvent = MultiKolabEventStatusEvent::factory()->for($event, 'event')->create();

        $this->assertTrue($event->statusEvents->contains($statusEvent));
    }

    // --- MultiKolabRole --------------------------------------------------

    public function test_multi_kolab_role_belongs_to_event_and_has_many_applications(): void
    {
        $event = MultiKolabEvent::factory()->create();
        $role = MultiKolabRole::factory()->for($event, 'event')->create();
        $application = MultiKolabRoleApplication::factory()->for($role, 'role')->create();

        $this->assertTrue($role->event->is($event));
        $this->assertTrue($role->applications->contains($application));
    }

    public function test_multi_kolab_role_casts_status_eligible_account_type_and_compensation_type(): void
    {
        $role = MultiKolabRole::factory()->create([
            'status' => MultiKolabRoleStatus::Open,
            'eligible_account_type' => MultiKolabEligibleAccountType::Community,
            'compensation_type' => MultiKolabCompensationType::ValueExchange,
        ]);

        $fresh = $role->fresh();
        $this->assertInstanceOf(MultiKolabRoleStatus::class, $fresh->status);
        $this->assertInstanceOf(MultiKolabEligibleAccountType::class, $fresh->eligible_account_type);
        $this->assertInstanceOf(MultiKolabCompensationType::class, $fresh->compensation_type);
    }

    public function test_multi_kolab_role_defaults_positions_filled_to_zero(): void
    {
        $role = MultiKolabRole::factory()->create(['positions_needed' => 3]);

        $this->assertSame(0, $role->fresh()->positions_filled);
        $this->assertSame(3, $role->fresh()->positions_needed);
    }

    // --- MultiKolabRoleApplication ----------------------------------------

    public function test_role_application_belongs_to_role_and_applicant_profile(): void
    {
        $role = MultiKolabRole::factory()->create();
        $applicant = Profile::factory()->community()->create();
        $application = MultiKolabRoleApplication::factory()
            ->for($role, 'role')
            ->for($applicant, 'applicantProfile')
            ->create();

        $this->assertTrue($application->role->is($role));
        $this->assertTrue($application->applicantProfile->is($applicant));
    }

    public function test_role_application_child_kolab_relation_is_nullable(): void
    {
        $application = MultiKolabRoleApplication::factory()->create();

        $this->assertNull($application->kolab_id);
        $this->assertNull($application->kolab);
    }

    public function test_role_application_can_link_to_a_child_kolab(): void
    {
        $kolab = Kolab::factory()->create();
        $application = MultiKolabRoleApplication::factory()->create(['kolab_id' => $kolab->id]);

        $this->assertTrue($application->fresh()->kolab->is($kolab));
    }

    public function test_role_application_enforces_unique_role_and_applicant(): void
    {
        $role = MultiKolabRole::factory()->create();
        $applicant = Profile::factory()->community()->create();

        MultiKolabRoleApplication::factory()
            ->for($role, 'role')
            ->for($applicant, 'applicantProfile')
            ->create();

        $this->expectException(QueryException::class);

        MultiKolabRoleApplication::factory()
            ->for($role, 'role')
            ->for($applicant, 'applicantProfile')
            ->create();
    }

    public function test_role_application_casts_status(): void
    {
        $application = MultiKolabRoleApplication::factory()->create([
            'status' => MultiKolabRoleApplicationStatus::Shortlisted,
        ]);

        $this->assertInstanceOf(MultiKolabRoleApplicationStatus::class, $application->fresh()->status);
    }

    // --- MultiKolabEventStatusEvent ---------------------------------------

    public function test_status_event_belongs_to_event_and_optional_actor(): void
    {
        $event = MultiKolabEvent::factory()->create();
        $actor = Profile::factory()->business()->create();
        $statusEvent = MultiKolabEventStatusEvent::factory()
            ->for($event, 'event')
            ->for($actor, 'actorProfile')
            ->create();

        $this->assertTrue($statusEvent->event->is($event));
        $this->assertTrue($statusEvent->actorProfile->is($actor));
    }

    public function test_status_event_actor_profile_may_be_null(): void
    {
        $statusEvent = MultiKolabEventStatusEvent::factory()->create(['actor_profile_id' => null]);

        $this->assertNull($statusEvent->actorProfile);
    }

    // --- OrganizerEntitlement ----------------------------------------------

    public function test_organizer_entitlement_belongs_to_profile_and_casts_capability(): void
    {
        $profile = Profile::factory()->business()->create();
        $entitlement = OrganizerEntitlement::factory()
            ->for($profile, 'profile')
            ->create(['capability' => OrganizerCapability::EventCreator]);

        $this->assertTrue($entitlement->profile->is($profile));
        $this->assertInstanceOf(OrganizerCapability::class, $entitlement->fresh()->capability);
    }

    public function test_profile_has_many_organizer_entitlements(): void
    {
        $profile = Profile::factory()->community()->create();
        $entitlement = OrganizerEntitlement::factory()->for($profile, 'profile')->create();

        $this->assertTrue($profile->organizerEntitlements->contains($entitlement));
    }

    // --- Kolab parent/role relations (additive) ----------------------------

    public function test_kolab_multi_kolab_event_and_role_relations_are_nullable(): void
    {
        $kolab = Kolab::factory()->create();

        $this->assertNull($kolab->multi_kolab_event_id);
        $this->assertNull($kolab->multi_kolab_role_id);
        $this->assertNull($kolab->multiKolabEvent);
        $this->assertNull($kolab->multiKolabRole);
    }

    public function test_kolab_can_link_to_parent_multi_kolab_event_and_role(): void
    {
        $event = MultiKolabEvent::factory()->create();
        $role = MultiKolabRole::factory()->for($event, 'event')->create();

        $kolab = Kolab::factory()->create([
            'multi_kolab_event_id' => $event->id,
            'multi_kolab_role_id' => $role->id,
        ]);

        $this->assertTrue($kolab->fresh()->multiKolabEvent->is($event));
        $this->assertTrue($kolab->fresh()->multiKolabRole->is($role));
    }
}
