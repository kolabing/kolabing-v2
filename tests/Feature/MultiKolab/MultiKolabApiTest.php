<?php

declare(strict_types=1);

namespace Tests\Feature\MultiKolab;

use App\Models\MultiKolabEvent;
use App\Models\MultiKolabRole;
use App\Models\MultiKolabRoleApplication;
use App\Models\Profile;
use App\Services\OrganizerEntitlementService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class MultiKolabApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function entitle(Profile $profile): void
    {
        app(OrganizerEntitlementService::class)->grant($profile);
    }

    // --- Entitlement ---------------------------------------------------------

    public function test_entitlement_endpoint_requires_authentication(): void
    {
        $this->getJson('/api/v1/me/organizer-entitlement')->assertStatus(401);
    }

    public function test_entitlement_endpoint_reflects_grant_state(): void
    {
        $profile = Profile::factory()->business()->create();

        $this->actingAs($profile)
            ->getJson('/api/v1/me/organizer-entitlement')
            ->assertOk()
            ->assertJsonPath('data.has_event_creator_entitlement', false);

        $this->entitle($profile);

        $this->actingAs($profile)
            ->getJson('/api/v1/me/organizer-entitlement')
            ->assertOk()
            ->assertJsonPath('data.has_event_creator_entitlement', true)
            ->assertJsonPath('data.capability', 'event_creator')
            ->assertJsonPath('data.source', 'maintainer');
    }

    // --- Draft creation --------------------------------------------------------

    public function test_creating_a_draft_does_not_require_entitlement(): void
    {
        $profile = Profile::factory()->community()->create();

        $response = $this->actingAs($profile)
            ->postJson('/api/v1/multi-kolab-events', ['title' => 'Launch Weekend'])
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.title', 'Launch Weekend')
            ->assertJsonPath('data.role_counts.total', 0)
            ->assertJsonStructure(['data' => ['id', 'roles', 'role_counts', 'created_at', 'updated_at']]);

        $this->assertDatabaseHas('multi_kolab_events', [
            'id' => $response->json('data.id'),
            'creator_profile_id' => $profile->id,
        ]);
    }

    public function test_creating_a_draft_without_title_is_rejected(): void
    {
        $profile = Profile::factory()->business()->create();

        $this->actingAs($profile)
            ->postJson('/api/v1/multi-kolab-events', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title']);
    }

    // --- Show / ownership ------------------------------------------------------

    public function test_only_the_creator_can_view_a_draft_event(): void
    {
        $creator = Profile::factory()->business()->create();
        $event = MultiKolabEvent::factory()->for($creator, 'creatorProfile')->create();
        $stranger = Profile::factory()->community()->create();

        $this->actingAs($stranger)
            ->getJson("/api/v1/multi-kolab-events/{$event->id}")
            ->assertStatus(403)
            ->assertJsonPath('errors.owner.0', 'not_owner');

        $this->actingAs($creator)
            ->getJson("/api/v1/multi-kolab-events/{$event->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $event->id);
    }

    public function test_anyone_can_view_a_recruiting_event(): void
    {
        $creator = Profile::factory()->business()->create();
        $event = MultiKolabEvent::factory()->recruiting()->for($creator, 'creatorProfile')->create();
        $viewer = Profile::factory()->community()->create();

        $this->actingAs($viewer)
            ->getJson("/api/v1/multi-kolab-events/{$event->id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'recruiting')
            ->assertJsonPath('data.viewer_application', null);
    }

    public function test_show_includes_the_viewers_own_application_but_not_others(): void
    {
        $creator = Profile::factory()->business()->create();
        $event = MultiKolabEvent::factory()->recruiting()->for($creator, 'creatorProfile')->create();
        $role = MultiKolabRole::factory()->for($event, 'event')->create(['eligible_account_type' => 'either']);

        $viewer = Profile::factory()->community()->create();
        $otherApplicant = Profile::factory()->community()->create();

        $this->actingAs($viewer)->postJson("/api/v1/multi-kolab-roles/{$role->id}/applications", ['pitch' => 'My pitch']);
        $this->actingAs($otherApplicant)->postJson("/api/v1/multi-kolab-roles/{$role->id}/applications", ['pitch' => 'Their pitch']);

        $response = $this->actingAs($viewer)->getJson("/api/v1/multi-kolab-events/{$event->id}")
            ->assertOk();

        $this->assertSame('My pitch', $response->json('data.viewer_application.pitch'));
        $this->assertStringNotContainsString('Their pitch', $response->getContent());
    }

    // --- Update ownership --------------------------------------------------------

    public function test_only_the_owner_can_update_the_event(): void
    {
        $creator = Profile::factory()->business()->create();
        $event = MultiKolabEvent::factory()->for($creator, 'creatorProfile')->create(['title' => 'Original']);
        $stranger = Profile::factory()->business()->create();

        $this->actingAs($stranger)
            ->patchJson("/api/v1/multi-kolab-events/{$event->id}", ['title' => 'Hijacked'])
            ->assertStatus(403);

        $this->actingAs($creator)
            ->patchJson("/api/v1/multi-kolab-events/{$event->id}", ['title' => 'Updated'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Updated');
    }

    // --- Roles -------------------------------------------------------------------

    public function test_owner_can_add_a_role_stranger_cannot(): void
    {
        $creator = Profile::factory()->business()->create();
        $event = MultiKolabEvent::factory()->for($creator, 'creatorProfile')->create();
        $stranger = Profile::factory()->business()->create();

        $this->actingAs($stranger)
            ->postJson("/api/v1/multi-kolab-events/{$event->id}/roles", [
                'title' => 'Partner', 'eligible_account_type' => 'either',
            ])
            ->assertStatus(403);

        $this->actingAs($creator)
            ->postJson("/api/v1/multi-kolab-events/{$event->id}/roles", [
                'title' => 'Partner', 'eligible_account_type' => 'either',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.title', 'Partner')
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.positions_filled', 0);
    }

    public function test_role_removal_with_accepted_application_returns_422(): void
    {
        $creator = Profile::factory()->business()->create();
        $event = MultiKolabEvent::factory()->for($creator, 'creatorProfile')->create();
        $role = MultiKolabRole::factory()->for($event, 'event')->create();
        MultiKolabRoleApplication::factory()->for($role, 'role')->create(['status' => 'accepted']);

        $this->actingAs($creator)
            ->deleteJson("/api/v1/multi-kolab-roles/{$role->id}")
            ->assertStatus(422)
            ->assertJsonPath('errors.role.0', 'role_has_accepted_application');
    }

    // --- Publish -------------------------------------------------------------------

    public function test_publish_without_entitlement_returns_403(): void
    {
        $creator = Profile::factory()->business()->create();
        $event = MultiKolabEvent::factory()->for($creator, 'creatorProfile')->create(['description' => 'Desc']);
        MultiKolabRole::factory()->for($event, 'event')->create();

        $this->actingAs($creator)
            ->postJson("/api/v1/multi-kolab-events/{$event->id}/publish")
            ->assertStatus(403)
            ->assertJsonPath('errors.entitlement.0', 'event_creator_required');
    }

    public function test_publish_validation_failure_returns_422_with_field_errors(): void
    {
        $creator = Profile::factory()->business()->create();
        $this->entitle($creator);
        $event = MultiKolabEvent::factory()->for($creator, 'creatorProfile')->create(['description' => null]); // no roles either

        $this->actingAs($creator)
            ->postJson("/api/v1/multi-kolab-events/{$event->id}/publish")
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['description', 'roles']]);
    }

    public function test_entitled_owner_can_publish(): void
    {
        $creator = Profile::factory()->business()->create();
        $this->entitle($creator);
        $event = MultiKolabEvent::factory()->for($creator, 'creatorProfile')->create(['description' => 'Desc']);
        MultiKolabRole::factory()->for($event, 'event')->create();

        $this->actingAs($creator)
            ->postJson("/api/v1/multi-kolab-events/{$event->id}/publish")
            ->assertOk()
            ->assertJsonPath('data.status', 'recruiting')
            ->assertJsonPath('data.role_counts.total', 1);
    }

    // --- Lifecycle transitions -----------------------------------------------------

    public function test_confirm_complete_and_terminal_cancel_transition(): void
    {
        $creator = Profile::factory()->business()->create();
        $this->entitle($creator);
        $event = MultiKolabEvent::factory()->for($creator, 'creatorProfile')->create(['description' => 'Desc']);
        MultiKolabRole::factory()->for($event, 'event')->create();
        $this->actingAs($creator)->postJson("/api/v1/multi-kolab-events/{$event->id}/publish")->assertOk();

        $this->actingAs($creator)
            ->postJson("/api/v1/multi-kolab-events/{$event->id}/confirm")
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed');

        $this->actingAs($creator)
            ->postJson("/api/v1/multi-kolab-events/{$event->id}/complete")
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');

        // Terminal — cannot cancel a completed event.
        $this->actingAs($creator)
            ->postJson("/api/v1/multi-kolab-events/{$event->id}/cancel", ['reason' => 'too late'])
            ->assertStatus(422)
            ->assertJsonPath('errors.status.0', 'invalid_transition');
    }

    public function test_cancel_requires_a_reason(): void
    {
        $creator = Profile::factory()->business()->create();
        $event = MultiKolabEvent::factory()->for($creator, 'creatorProfile')->create();

        $this->actingAs($creator)
            ->postJson("/api/v1/multi-kolab-events/{$event->id}/cancel", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['reason']);
    }

    public function test_cancel_succeeds_with_reason(): void
    {
        $creator = Profile::factory()->business()->create();
        $event = MultiKolabEvent::factory()->for($creator, 'creatorProfile')->create();

        $this->actingAs($creator)
            ->postJson("/api/v1/multi-kolab-events/{$event->id}/cancel", ['reason' => 'Change of plans'])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');
    }

    // --- Explore listing filters -----------------------------------------------

    public function test_explore_lists_only_recruiting_events_and_supports_filters(): void
    {
        $barcelonaOrganizer = Profile::factory()->business()->create();
        $recruiting = MultiKolabEvent::factory()->recruiting()->for($barcelonaOrganizer, 'creatorProfile')->create([
            'city' => 'Barcelona', 'category' => 'Music',
        ]);
        MultiKolabEvent::factory()->create(); // draft — must not appear
        MultiKolabEvent::factory()->recruiting()->create(['city' => 'Madrid']); // different city

        $viewer = Profile::factory()->community()->create();

        $response = $this->actingAs($viewer)
            ->getJson('/api/v1/multi-kolab-events?city=Barcelona')
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($recruiting->id, $ids);
        $this->assertCount(1, $ids);
        $this->assertSame('Barcelona', $response->json('data.0.city'));
        $this->assertArrayHasKey('creator_profile', $response->json('data.0'));
        $this->assertArrayHasKey('role_counts', $response->json('data.0'));
    }

    public function test_my_events_lists_own_events_regardless_of_status(): void
    {
        $creator = Profile::factory()->business()->create();
        $draft = MultiKolabEvent::factory()->for($creator, 'creatorProfile')->create();
        $recruiting = MultiKolabEvent::factory()->recruiting()->for($creator, 'creatorProfile')->create();
        MultiKolabEvent::factory()->recruiting()->create(); // someone else's

        $response = $this->actingAs($creator)
            ->getJson('/api/v1/multi-kolab-events/me')
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($draft->id, $ids);
        $this->assertContains($recruiting->id, $ids);
        $this->assertCount(2, $ids);
    }

    // --- Applications ----------------------------------------------------------

    public function test_applying_returns_201_with_the_frozen_shape(): void
    {
        $creator = Profile::factory()->business()->create();
        $event = MultiKolabEvent::factory()->recruiting()->for($creator, 'creatorProfile')->create();
        $role = MultiKolabRole::factory()->for($event, 'event')->create(['eligible_account_type' => 'either']);
        $applicant = Profile::factory()->community()->create();

        $this->actingAs($applicant)
            ->postJson("/api/v1/multi-kolab-roles/{$role->id}/applications", [
                'pitch' => 'We would love to partner.',
                'availability' => 'Weekends',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.kolab_id', null)
            ->assertJsonStructure(['data' => [
                'id', 'multi_kolab_role_id', 'applicant_profile_id', 'applicant_profile_type',
                'status', 'pitch', 'availability', 'kolab_id', 'created_at',
            ]]);
    }

    public function test_organizer_cannot_apply_to_own_event(): void
    {
        $creator = Profile::factory()->business()->create();
        $event = MultiKolabEvent::factory()->recruiting()->for($creator, 'creatorProfile')->create();
        $role = MultiKolabRole::factory()->for($event, 'event')->create(['eligible_account_type' => 'either']);

        $this->actingAs($creator)
            ->postJson("/api/v1/multi-kolab-roles/{$role->id}/applications", ['pitch' => 'Pitch'])
            ->assertStatus(403);
    }

    public function test_duplicate_application_returns_409(): void
    {
        $creator = Profile::factory()->business()->create();
        $event = MultiKolabEvent::factory()->recruiting()->for($creator, 'creatorProfile')->create();
        $role = MultiKolabRole::factory()->for($event, 'event')->create(['eligible_account_type' => 'either']);
        $applicant = Profile::factory()->community()->create();

        $this->actingAs($applicant)->postJson("/api/v1/multi-kolab-roles/{$role->id}/applications", ['pitch' => 'Pitch']);

        $this->actingAs($applicant)
            ->postJson("/api/v1/multi-kolab-roles/{$role->id}/applications", ['pitch' => 'Second'])
            ->assertStatus(409)
            ->assertJsonPath('errors.application.0', 'duplicate_application');
    }

    public function test_ineligible_applicant_returns_stable_error_code(): void
    {
        $creator = Profile::factory()->business()->create();
        $event = MultiKolabEvent::factory()->recruiting()->for($creator, 'creatorProfile')->create();
        $role = MultiKolabRole::factory()->for($event, 'event')->create(['eligible_account_type' => 'business']);
        $applicant = Profile::factory()->community()->create();

        $this->actingAs($applicant)
            ->postJson("/api/v1/multi-kolab-roles/{$role->id}/applications", ['pitch' => 'Pitch'])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.role.0', 'role_ineligible');
    }

    public function test_applying_to_a_non_recruiting_event_returns_stable_error_code(): void
    {
        $creator = Profile::factory()->business()->create();
        $event = MultiKolabEvent::factory()->for($creator, 'creatorProfile')->create(); // draft
        $role = MultiKolabRole::factory()->for($event, 'event')->create(['eligible_account_type' => 'either']);
        $applicant = Profile::factory()->community()->create();

        $this->actingAs($applicant)
            ->postJson("/api/v1/multi-kolab-roles/{$role->id}/applications", ['pitch' => 'Pitch'])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.event.0', 'event_not_recruiting');
    }

    public function test_applying_to_a_closed_role_returns_stable_error_code(): void
    {
        $creator = Profile::factory()->business()->create();
        $event = MultiKolabEvent::factory()->recruiting()->for($creator, 'creatorProfile')->create();
        $role = MultiKolabRole::factory()->for($event, 'event')->create([
            'eligible_account_type' => 'either', 'status' => 'closed',
        ]);
        $applicant = Profile::factory()->community()->create();

        $this->actingAs($applicant)
            ->postJson("/api/v1/multi-kolab-roles/{$role->id}/applications", ['pitch' => 'Pitch'])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('errors.role.0', 'role_not_open');
    }

    public function test_only_organizer_can_list_a_roles_applications(): void
    {
        $creator = Profile::factory()->business()->create();
        $event = MultiKolabEvent::factory()->recruiting()->for($creator, 'creatorProfile')->create();
        $role = MultiKolabRole::factory()->for($event, 'event')->create(['eligible_account_type' => 'either']);
        $applicant = Profile::factory()->community()->create();
        $this->actingAs($applicant)->postJson("/api/v1/multi-kolab-roles/{$role->id}/applications", ['pitch' => 'Pitch']);

        $this->actingAs($applicant)
            ->getJson("/api/v1/multi-kolab-roles/{$role->id}/applications")
            ->assertStatus(403);

        $this->actingAs($creator)
            ->getJson("/api/v1/multi-kolab-roles/{$role->id}/applications")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_shortlist_decline_and_withdraw_are_role_gated(): void
    {
        $creator = Profile::factory()->business()->create();
        $event = MultiKolabEvent::factory()->recruiting()->for($creator, 'creatorProfile')->create();
        $role = MultiKolabRole::factory()->for($event, 'event')->create(['eligible_account_type' => 'either']);
        $applicant = Profile::factory()->community()->create();
        $applicationId = $this->actingAs($applicant)
            ->postJson("/api/v1/multi-kolab-roles/{$role->id}/applications", ['pitch' => 'Pitch'])
            ->json('data.id');

        // Applicant cannot shortlist their own application.
        $this->actingAs($applicant)
            ->postJson("/api/v1/multi-kolab-role-applications/{$applicationId}/shortlist")
            ->assertStatus(403);

        $this->actingAs($creator)
            ->postJson("/api/v1/multi-kolab-role-applications/{$applicationId}/shortlist")
            ->assertOk()
            ->assertJsonPath('data.status', 'shortlisted');

        // Organizer cannot withdraw the applicant's application.
        $this->actingAs($creator)
            ->postJson("/api/v1/multi-kolab-role-applications/{$applicationId}/withdraw")
            ->assertStatus(403);

        $this->actingAs($applicant)
            ->postJson("/api/v1/multi-kolab-role-applications/{$applicationId}/withdraw")
            ->assertOk()
            ->assertJsonPath('data.status', 'withdrawn');
    }

    public function test_decline_by_organizer(): void
    {
        $creator = Profile::factory()->business()->create();
        $event = MultiKolabEvent::factory()->recruiting()->for($creator, 'creatorProfile')->create();
        $role = MultiKolabRole::factory()->for($event, 'event')->create(['eligible_account_type' => 'either']);
        $applicant = Profile::factory()->community()->create();
        $applicationId = $this->actingAs($applicant)
            ->postJson("/api/v1/multi-kolab-roles/{$role->id}/applications", ['pitch' => 'Pitch'])
            ->json('data.id');

        $this->actingAs($creator)
            ->postJson("/api/v1/multi-kolab-role-applications/{$applicationId}/decline")
            ->assertOk()
            ->assertJsonPath('data.status', 'declined');
    }

    // --- Accept — child-Kolab linkage, contract §8 ------------------------------

    public function test_accept_returns_the_frozen_composite_shape(): void
    {
        $creator = Profile::factory()->business()->create();
        $event = MultiKolabEvent::factory()->recruiting()->for($creator, 'creatorProfile')->create();
        $role = MultiKolabRole::factory()->for($event, 'event')->create([
            'eligible_account_type' => 'either', 'positions_needed' => 1,
        ]);
        $applicant = Profile::factory()->community()->create();
        $applicationId = $this->actingAs($applicant)
            ->postJson("/api/v1/multi-kolab-roles/{$role->id}/applications", ['pitch' => 'Pitch'])
            ->json('data.id');

        $response = $this->actingAs($creator)
            ->postJson("/api/v1/multi-kolab-role-applications/{$applicationId}/accept")
            ->assertOk()
            ->assertJsonStructure(['data' => [
                'application' => ['id', 'status', 'kolab_id'],
                'kolab' => ['id', 'status', 'collaboration_id'],
                'collaboration' => ['id', 'status', 'application_id'],
            ]]);

        $this->assertSame('accepted', $response->json('data.application.status'));
        $this->assertSame('published', $response->json('data.kolab.status'));
        $this->assertSame('scheduled', $response->json('data.collaboration.status'));
    }

    public function test_accept_capacity_conflict_returns_409(): void
    {
        $creator = Profile::factory()->business()->create();
        $event = MultiKolabEvent::factory()->recruiting()->for($creator, 'creatorProfile')->create();
        $role = MultiKolabRole::factory()->for($event, 'event')->create([
            'eligible_account_type' => 'either', 'positions_needed' => 1, 'positions_filled' => 1, 'status' => 'filled',
        ]);
        $applicant = Profile::factory()->community()->create();
        $application = MultiKolabRoleApplication::factory()
            ->for($role, 'role')->for($applicant, 'applicantProfile')->create(['status' => 'pending']);

        $this->actingAs($creator)
            ->postJson("/api/v1/multi-kolab-role-applications/{$application->id}/accept")
            ->assertStatus(409)
            ->assertJsonPath('errors.role.0', 'role_capacity_exceeded');
    }

    public function test_only_organizer_can_accept(): void
    {
        $creator = Profile::factory()->business()->create();
        $event = MultiKolabEvent::factory()->recruiting()->for($creator, 'creatorProfile')->create();
        $role = MultiKolabRole::factory()->for($event, 'event')->create(['eligible_account_type' => 'either']);
        $applicant = Profile::factory()->community()->create();
        $applicationId = $this->actingAs($applicant)
            ->postJson("/api/v1/multi-kolab-roles/{$role->id}/applications", ['pitch' => 'Pitch'])
            ->json('data.id');

        $this->actingAs($applicant)
            ->postJson("/api/v1/multi-kolab-role-applications/{$applicationId}/accept")
            ->assertStatus(403);
    }

    // --- Dashboard ----------------------------------------------------------------

    public function test_dashboard_returns_per_role_application_counts(): void
    {
        $creator = Profile::factory()->business()->create();
        $event = MultiKolabEvent::factory()->recruiting()->for($creator, 'creatorProfile')->create();
        $role = MultiKolabRole::factory()->for($event, 'event')->create([
            'eligible_account_type' => 'either', 'positions_needed' => 2,
        ]);
        MultiKolabRoleApplication::factory()->for($role, 'role')->create(['status' => 'pending']);
        MultiKolabRoleApplication::factory()->for($role, 'role')->create(['status' => 'pending']);
        MultiKolabRoleApplication::factory()->for($role, 'role')->create(['status' => 'shortlisted']);

        $stranger = Profile::factory()->business()->create();
        $this->actingAs($stranger)
            ->getJson("/api/v1/multi-kolab-events/{$event->id}/dashboard")
            ->assertStatus(403);

        $this->actingAs($creator)
            ->getJson("/api/v1/multi-kolab-events/{$event->id}/dashboard")
            ->assertOk()
            ->assertJsonPath('data.role_counts.total', 1)
            ->assertJsonPath('data.roles.0.application_counts.pending', 2)
            ->assertJsonPath('data.roles.0.application_counts.shortlisted', 1)
            ->assertJsonPath('data.roles.0.application_counts.accepted', 0);
    }

    // --- Existing Kolab Explore regression (must be unaffected) -------------------

    public function test_existing_kolab_explore_endpoint_is_unaffected(): void
    {
        \App\Models\Kolab::factory()->published()->create([
            'intent_type' => \App\Enums\IntentType::CommunitySeeking,
        ]);
        $viewer = Profile::factory()->business()->create();

        $this->actingAs($viewer)
            ->getJson('/api/v1/kolabs')
            ->assertOk()
            ->assertJsonPath('success', true);
    }
}
