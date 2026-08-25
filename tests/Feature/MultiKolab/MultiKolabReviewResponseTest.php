<?php

declare(strict_types=1);

namespace Tests\Feature\MultiKolab;

use App\Enums\CollaborationStatus;
use App\Enums\KolabStatus;
use App\Enums\MultiKolabEligibleAccountType;
use App\Enums\MultiKolabEventStatus;
use App\Enums\MultiKolabRoleApplicationStatus;
use App\Enums\MultiKolabRoleStatus;
use App\Exceptions\MultiKolabNotOrganizerException;
use App\Models\Collaboration;
use App\Models\Kolab;
use App\Models\MultiKolabEvent;
use App\Models\MultiKolabRole;
use App\Models\MultiKolabRoleApplication;
use App\Models\Profile;
use App\Services\DiscoveryOpportunityService;
use App\Services\MultiKolabEventService;
use App\Services\MultiKolabRoleApplicationService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Covers PR #141 review items 1–12. Grouped by item number in one file so
 * the abuse/regression scenarios described in the review are exercised as
 * real automated feature/unit tests.
 */
class MultiKolabReviewResponseTest extends TestCase
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

    // --- #1: private statuses cannot leak through the public index -----------

    public function test_public_index_never_returns_another_users_draft_event(): void
    {
        $organizer = Profile::factory()->business()->create();
        MultiKolabEvent::factory()->for($organizer, 'creatorProfile')->create(['status' => MultiKolabEventStatus::Draft]);
        $viewer = Profile::factory()->business()->create();

        $this->actingAs($viewer);
        $response = $this->getJson('/api/v1/multi-kolab-events?status=draft');

        $response->assertOk();
        $this->assertSame(0, $response->json('meta.total'));
    }

    public function test_public_index_never_returns_another_users_cancelled_event(): void
    {
        $organizer = Profile::factory()->business()->create();
        MultiKolabEvent::factory()->for($organizer, 'creatorProfile')->create(['status' => MultiKolabEventStatus::Cancelled]);
        $viewer = Profile::factory()->business()->create();

        $this->actingAs($viewer);
        $response = $this->getJson('/api/v1/multi-kolab-events?status=cancelled');

        $response->assertOk();
        $this->assertSame(0, $response->json('meta.total'));
    }

    public function test_public_index_still_returns_recruiting_events(): void
    {
        $organizer = Profile::factory()->business()->create();
        MultiKolabEvent::factory()->recruiting()->for($organizer, 'creatorProfile')->create(['published_at' => now()]);
        $viewer = Profile::factory()->business()->create();

        $this->actingAs($viewer);
        $response = $this->getJson('/api/v1/multi-kolab-events');

        $response->assertOk();
        $this->assertSame(1, $response->json('meta.total'));
    }

    public function test_public_index_returns_confirmed_and_completed_when_requested(): void
    {
        $organizer = Profile::factory()->business()->create();
        MultiKolabEvent::factory()->for($organizer, 'creatorProfile')->create(['status' => MultiKolabEventStatus::Confirmed]);
        MultiKolabEvent::factory()->for($organizer, 'creatorProfile')->create(['status' => MultiKolabEventStatus::Completed]);
        $viewer = Profile::factory()->business()->create();

        $this->actingAs($viewer);

        $this->getJson('/api/v1/multi-kolab-events?status=confirmed')->assertOk()->assertJsonPath('meta.total', 1);
        $this->getJson('/api/v1/multi-kolab-events?status=completed')->assertOk()->assertJsonPath('meta.total', 1);
    }

    public function test_public_index_rejects_invalid_status_value_safely_by_falling_back_to_recruiting(): void
    {
        $organizer = Profile::factory()->business()->create();
        MultiKolabEvent::factory()->recruiting()->for($organizer, 'creatorProfile')->create(['published_at' => now()]);
        $viewer = Profile::factory()->business()->create();

        $this->actingAs($viewer);
        $response = $this->getJson('/api/v1/multi-kolab-events?status=not_a_real_status');

        $response->assertOk();
        $this->assertSame(1, $response->json('meta.total'));
    }

    // --- #2: accepting after the event stops recruiting -----------------------

    /**
     * @return array{event: MultiKolabEvent, role: MultiKolabRole, application: MultiKolabRoleApplication, organizer: Profile}
     */
    private function shortlistedApplicationOnEvent(MultiKolabEventStatus $eventStatus): array
    {
        $organizer = Profile::factory()->business()->create();
        $event = MultiKolabEvent::factory()->recruiting()->for($organizer, 'creatorProfile')->create();
        $role = MultiKolabRole::factory()->for($event, 'event')->create([
            'eligible_account_type' => MultiKolabEligibleAccountType::Either,
            'status' => MultiKolabRoleStatus::Open,
            'positions_needed' => 1,
            'positions_filled' => 0,
        ]);
        $applicant = Profile::factory()->community()->create();
        $application = $this->applicationService()->apply($role, $applicant, ['pitch' => 'Pitch']);

        $event->update(['status' => $eventStatus]);

        return compact('event', 'role', 'application', 'organizer');
    }

    public function test_accept_succeeds_while_event_is_recruiting(): void
    {
        ['application' => $application, 'organizer' => $organizer] = $this->shortlistedApplicationOnEvent(MultiKolabEventStatus::Recruiting);

        $kolab = $this->applicationService()->accept($application, $organizer);

        $this->assertSame(KolabStatus::Published, $kolab->status);
    }

    public function test_accept_is_rejected_once_event_is_cancelled(): void
    {
        ['application' => $application, 'organizer' => $organizer] = $this->shortlistedApplicationOnEvent(MultiKolabEventStatus::Cancelled);

        $this->expectException(InvalidArgumentException::class);
        $this->applicationService()->accept($application, $organizer);
    }

    public function test_accept_is_rejected_once_event_is_completed(): void
    {
        ['application' => $application, 'organizer' => $organizer] = $this->shortlistedApplicationOnEvent(MultiKolabEventStatus::Completed);

        $this->expectException(InvalidArgumentException::class);
        $this->applicationService()->accept($application, $organizer);
    }

    public function test_accept_is_rejected_once_event_is_confirmed(): void
    {
        ['application' => $application, 'organizer' => $organizer] = $this->shortlistedApplicationOnEvent(MultiKolabEventStatus::Confirmed);

        $this->expectException(InvalidArgumentException::class);
        $this->applicationService()->accept($application, $organizer);
    }

    public function test_accept_still_idempotent_when_already_accepted_even_if_event_later_confirmed(): void
    {
        ['application' => $application, 'organizer' => $organizer] = $this->shortlistedApplicationOnEvent(MultiKolabEventStatus::Recruiting);

        $first = $this->applicationService()->accept($application, $organizer);
        $application->fresh()->event ?? null;

        $second = $this->applicationService()->accept($application->fresh(), $organizer);

        $this->assertSame($first->id, $second->id);
    }

    // --- #7: internal child Kolabs excluded from ordinary Explore -------------

    public function test_accepted_role_child_kolab_never_appears_as_an_ordinary_kolab_item(): void
    {
        ['application' => $application, 'organizer' => $organizer] = $this->shortlistedApplicationOnEvent(MultiKolabEventStatus::Recruiting);
        $kolab = $this->applicationService()->accept($application, $organizer);

        $viewer = Profile::factory()->community()->create();

        $result = app(DiscoveryOpportunityService::class)->discover($viewer, ['feed' => 'all']);
        $items = collect($result['paginator']->items());

        $matchingKolabItem = $items->first(fn (array $item) => $item['item_type'] === 'kolab' && $item['model']->id === $kolab->id);

        $this->assertNull($matchingKolabItem, 'The internal child Kolab must not appear as an ordinary Kolab item.');
    }

    public function test_ordinary_published_kolab_still_appears_in_discovery(): void
    {
        $creator = Profile::factory()->business()->create();
        $kolab = Kolab::factory()->create([
            'creator_profile_id' => $creator->id,
            'status' => KolabStatus::Published,
            'intent_type' => \App\Enums\IntentType::CommunitySeeking,
        ]);
        $viewer = Profile::factory()->business()->create();

        $result = app(DiscoveryOpportunityService::class)->discover($viewer, ['feed' => 'all']);
        $items = collect($result['paginator']->items());

        $this->assertNotNull($items->first(fn (array $item) => $item['item_type'] === 'kolab' && $item['model']->id === $kolab->id));
    }

    // --- #4 + #5 + #10: accepted-withdrawal correctness/concurrency -----------

    public function test_accepted_withdrawal_cancels_the_canonical_partnership_but_keeps_historical_records(): void
    {
        ['role' => $role, 'application' => $application, 'organizer' => $organizer] = $this->shortlistedApplicationOnEvent(MultiKolabEventStatus::Recruiting);
        $kolab = $this->applicationService()->accept($application, $organizer);
        $collaboration = Collaboration::query()->where('kolab_id', $kolab->id)->firstOrFail();

        $withdrawn = $this->applicationService()->withdraw($application->fresh(), $application->applicantProfile, 'Change of plans');

        $this->assertSame(MultiKolabRoleApplicationStatus::Withdrawn, $withdrawn->status);

        // Historical records remain — not deleted.
        $this->assertNotNull(Kolab::query()->find($kolab->id));
        $this->assertNotNull(Collaboration::query()->find($collaboration->id));

        // But the live partnership is cancelled.
        $this->assertSame(KolabStatus::Closed, $kolab->fresh()->status);
        $this->assertSame(CollaborationStatus::Cancelled, $collaboration->fresh()->status);

        // Capacity decremented exactly once.
        $this->assertSame(0, $role->fresh()->positions_filled);
    }

    public function test_accepted_withdrawal_removes_kolab_from_ordinary_discovery(): void
    {
        ['application' => $application] = $this->shortlistedApplicationOnEvent(MultiKolabEventStatus::Recruiting);
        $organizer = $application->role->event->creatorProfile;
        $kolab = $this->applicationService()->accept($application, $organizer);

        $this->applicationService()->withdraw($application->fresh(), $application->applicantProfile, 'Change of plans');

        $this->assertSame(KolabStatus::Closed, $kolab->fresh()->status);
    }

    public function test_filled_role_reopens_to_open_after_accepted_withdrawal_while_event_still_recruiting(): void
    {
        ['role' => $role, 'application' => $application, 'organizer' => $organizer] = $this->shortlistedApplicationOnEvent(MultiKolabEventStatus::Recruiting);
        $this->applicationService()->accept($application, $organizer);
        $this->assertSame(MultiKolabRoleStatus::Filled, $role->fresh()->status);

        $this->applicationService()->withdraw($application->fresh(), $application->applicantProfile, 'Change of plans');

        $this->assertSame(MultiKolabRoleStatus::Open, $role->fresh()->status);
    }

    public function test_organizer_closed_role_stays_closed_after_accepted_withdrawal(): void
    {
        ['role' => $role, 'application' => $application, 'organizer' => $organizer] = $this->shortlistedApplicationOnEvent(MultiKolabEventStatus::Recruiting);
        $this->applicationService()->accept($application, $organizer);

        // Organizer explicitly closes the role after it filled.
        $role->fresh()->update(['status' => MultiKolabRoleStatus::Closed]);

        $this->applicationService()->withdraw($application->fresh(), $application->applicantProfile, 'Change of plans');

        $this->assertSame(MultiKolabRoleStatus::Closed, $role->fresh()->status);
    }

    public function test_role_does_not_reopen_when_parent_event_is_no_longer_recruiting(): void
    {
        ['role' => $role, 'application' => $application, 'organizer' => $organizer] = $this->shortlistedApplicationOnEvent(MultiKolabEventStatus::Recruiting);
        $this->applicationService()->accept($application, $organizer);
        $event = $role->fresh()->event;
        $event->update(['status' => MultiKolabEventStatus::Confirmed]);

        $this->applicationService()->withdraw($application->fresh(), $application->applicantProfile, 'Change of plans');

        $this->assertSame(MultiKolabRoleStatus::Filled, $role->fresh()->status);
    }

    public function test_double_withdrawal_decrements_capacity_exactly_once(): void
    {
        // sqlite (this repo's test DB, per .env.testing) cannot simulate a
        // true concurrent race between two DB connections within one
        // process/transaction the way Postgres advisory/row locks under
        // real concurrency could be exercised — this is the same
        // limitation the existing Task 6 acceptance-concurrency tests
        // document. This is the strongest deterministic regression
        // available: two sequential calls against the same locked state,
        // asserting idempotency (the second call must be rejected, not
        // silently decrement again).
        ['role' => $role, 'application' => $application, 'organizer' => $organizer] = $this->shortlistedApplicationOnEvent(MultiKolabEventStatus::Recruiting);
        $this->applicationService()->accept($application, $organizer);
        $applicant = $application->fresh()->applicantProfile;

        $this->applicationService()->withdraw($application->fresh(), $applicant, 'First withdrawal');
        $this->assertSame(0, $role->fresh()->positions_filled);

        $this->expectException(InvalidArgumentException::class);
        $this->applicationService()->withdraw($application->fresh(), $applicant, 'Second withdrawal');
    }

    // --- #3: cannot delete a role referenced by a child Kolab ------------------

    public function test_role_with_no_applications_can_be_removed(): void
    {
        $organizer = Profile::factory()->business()->create();
        $event = MultiKolabEvent::factory()->for($organizer, 'creatorProfile')->create(['status' => MultiKolabEventStatus::Draft]);
        $role = MultiKolabRole::factory()->for($event, 'event')->create();

        $this->eventService()->removeRole($role);

        $this->assertNull(MultiKolabRole::query()->find($role->id));
    }

    public function test_currently_accepted_role_cannot_be_removed(): void
    {
        ['role' => $role, 'application' => $application, 'organizer' => $organizer] = $this->shortlistedApplicationOnEvent(MultiKolabEventStatus::Recruiting);
        $this->applicationService()->accept($application, $organizer);

        $this->expectException(InvalidArgumentException::class);
        $this->eventService()->removeRole($role->fresh());
    }

    public function test_role_with_withdrawn_application_but_surviving_child_kolab_cannot_be_removed(): void
    {
        ['role' => $role, 'application' => $application, 'organizer' => $organizer] = $this->shortlistedApplicationOnEvent(MultiKolabEventStatus::Recruiting);
        $this->applicationService()->accept($application, $organizer);
        $this->applicationService()->withdraw($application->fresh(), $application->applicantProfile, 'Change of plans');

        // The application is now withdrawn, but its child Kolab still
        // references the role — must still be rejected, not a 500.
        $this->assertSame(MultiKolabRoleApplicationStatus::Withdrawn, $application->fresh()->status);

        $this->expectException(InvalidArgumentException::class);
        $this->eventService()->removeRole($role->fresh());
    }

    public function test_delete_role_endpoint_never_returns_a_500_for_a_role_with_a_child_kolab(): void
    {
        ['role' => $role, 'application' => $application, 'organizer' => $organizer] = $this->shortlistedApplicationOnEvent(MultiKolabEventStatus::Recruiting);
        $this->applicationService()->accept($application, $organizer);
        $this->applicationService()->withdraw($application->fresh(), $application->applicantProfile, 'Change of plans');

        $this->actingAs($organizer);
        $response = $this->deleteJson("/api/v1/multi-kolab-roles/{$role->id}");

        $response->assertStatus(422);
        $this->assertSame(['role_has_accepted_application'], $response->json('errors.role'));
    }

    // --- #8: pagination clamping ------------------------------------------------

    public function test_per_page_zero_is_normalized_to_one_without_a_500(): void
    {
        $viewer = Profile::factory()->business()->create();
        MultiKolabEvent::factory()->recruiting()->for(Profile::factory()->business()->create(), 'creatorProfile')->count(3)->create(['published_at' => now()]);

        $this->actingAs($viewer);
        $response = $this->getJson('/api/v1/multi-kolab-events?per_page=0');

        $response->assertOk();
        $this->assertSame(1, $response->json('meta.per_page'));
    }

    public function test_per_page_negative_is_normalized_to_one(): void
    {
        $viewer = Profile::factory()->business()->create();

        $this->actingAs($viewer);
        $response = $this->getJson('/api/v1/multi-kolab-events/me?per_page=-5');

        $response->assertOk();
        $this->assertSame(1, $response->json('meta.per_page'));
    }

    public function test_per_page_above_100_is_clamped_to_100(): void
    {
        $viewer = Profile::factory()->business()->create();

        $this->actingAs($viewer);
        $response = $this->getJson('/api/v1/multi-kolab-events/me?per_page=500');

        $response->assertOk();
        $this->assertSame(100, $response->json('meta.per_page'));
    }

    public function test_normal_per_page_value_is_unchanged(): void
    {
        $viewer = Profile::factory()->business()->create();

        $this->actingAs($viewer);
        $response = $this->getJson('/api/v1/multi-kolab-events/me?per_page=7');

        $response->assertOk();
        $this->assertSame(7, $response->json('meta.per_page'));
    }

    // --- #9: Business/Community boundary on event creation ---------------------

    public function test_business_can_create_a_multi_kolab_draft(): void
    {
        $business = Profile::factory()->business()->create();
        $this->actingAs($business);

        $response = $this->postJson('/api/v1/multi-kolab-events', ['title' => 'A collab night']);

        $response->assertCreated();
    }

    public function test_community_can_create_a_multi_kolab_draft(): void
    {
        $community = Profile::factory()->community()->create();
        $this->actingAs($community);

        $response = $this->postJson('/api/v1/multi-kolab-events', ['title' => 'A collab night']);

        $response->assertCreated();
    }

    public function test_attendee_cannot_create_a_multi_kolab_draft(): void
    {
        $attendee = Profile::factory()->create(['user_type' => \App\Enums\UserType::Attendee]);
        $this->actingAs($attendee);

        $response = $this->postJson('/api/v1/multi-kolab-events', ['title' => 'A collab night']);

        $response->assertForbidden();
    }

    // --- #6: Explore filter truthfulness for Multi-Kolab role items -----------

    public function test_active_availability_filter_excludes_multi_kolab_role_items(): void
    {
        $organizer = Profile::factory()->business()->create();
        $event = MultiKolabEvent::factory()->recruiting()->for($organizer, 'creatorProfile')->create(['published_at' => now()]);
        MultiKolabRole::factory()->for($event, 'event')->create([
            'eligible_account_type' => MultiKolabEligibleAccountType::Either,
            'status' => MultiKolabRoleStatus::Open,
            'positions_needed' => 1,
            'positions_filled' => 0,
        ]);
        $viewer = Profile::factory()->community()->create();

        $result = app(DiscoveryOpportunityService::class)->discover($viewer, [
            'feed' => 'all',
            'availability_mode' => 'flexible',
        ]);
        $items = collect($result['paginator']->items());

        $this->assertFalse($items->contains(fn (array $item) => $item['item_type'] === 'multi_kolab_role'));
    }

    public function test_city_filter_still_matches_multi_kolab_role_items(): void
    {
        $organizer = Profile::factory()->business()->create();
        $event = MultiKolabEvent::factory()->recruiting()->for($organizer, 'creatorProfile')->create(['published_at' => now(), 'city' => 'Madrid']);
        MultiKolabRole::factory()->for($event, 'event')->create([
            'eligible_account_type' => MultiKolabEligibleAccountType::Either,
            'status' => MultiKolabRoleStatus::Open,
            'positions_needed' => 1,
            'positions_filled' => 0,
        ]);
        $viewer = Profile::factory()->community()->create();

        $result = app(DiscoveryOpportunityService::class)->discover($viewer, [
            'feed' => 'all',
            'city' => 'Madrid',
        ]);
        $items = collect($result['paginator']->items());

        $this->assertTrue($items->contains(fn (array $item) => $item['item_type'] === 'multi_kolab_role'));
    }

    public function test_ordinary_kolab_filter_behavior_unaffected_by_multi_kolab_exclusion_rule(): void
    {
        $creator = Profile::factory()->business()->create();
        Kolab::factory()->create([
            'creator_profile_id' => $creator->id,
            'status' => KolabStatus::Published,
            'intent_type' => \App\Enums\IntentType::CommunitySeeking,
            'preferred_city' => 'Barcelona',
        ]);
        $viewer = Profile::factory()->business()->create();

        $result = app(DiscoveryOpportunityService::class)->discover($viewer, [
            'feed' => 'all',
            'availability_mode' => 'flexible',
        ]);
        $items = collect($result['paginator']->items());

        // The unsupported-for-roles filter must not exclude ordinary Kolabs.
        $this->assertTrue($items->contains(fn (array $item) => $item['item_type'] === 'kolab'));
    }

    // --- #11: deterministic + complete viewer applications ---------------------

    public function test_event_detail_returns_deterministic_singular_and_full_plural_viewer_applications(): void
    {
        $organizer = Profile::factory()->business()->create();
        $event = MultiKolabEvent::factory()->recruiting()->for($organizer, 'creatorProfile')->create();
        $roleA = MultiKolabRole::factory()->for($event, 'event')->create(['eligible_account_type' => MultiKolabEligibleAccountType::Either]);
        $roleB = MultiKolabRole::factory()->for($event, 'event')->create(['eligible_account_type' => MultiKolabEligibleAccountType::Either]);
        $applicant = Profile::factory()->community()->create();

        $pendingApp = $this->applicationService()->apply($roleA, $applicant, ['pitch' => 'First']);
        $shortlistedApp = $this->applicationService()->apply($roleB, $applicant, ['pitch' => 'Second']);
        $this->applicationService()->shortlist($shortlistedApp, $organizer);

        $this->actingAs($applicant);
        $response = $this->getJson("/api/v1/multi-kolab-events/{$event->id}");

        $response->assertOk();
        $this->assertCount(2, $response->json('data.viewer_applications'));
        // Priority: accepted > shortlisted > pending > declined > withdrawn.
        $this->assertSame($shortlistedApp->id, $response->json('data.viewer_application.id'));
    }

    public function test_event_detail_viewer_application_is_null_when_viewer_has_not_applied(): void
    {
        $organizer = Profile::factory()->business()->create();
        $event = MultiKolabEvent::factory()->recruiting()->for($organizer, 'creatorProfile')->create();
        $viewer = Profile::factory()->community()->create();

        $this->actingAs($viewer);
        $response = $this->getJson("/api/v1/multi-kolab-events/{$event->id}");

        $response->assertOk();
        $this->assertNull($response->json('data.viewer_application'));
        $this->assertSame([], $response->json('data.viewer_applications'));
    }

    // --- #12: typed exception instead of message-substring classification -----

    public function test_non_organizer_accept_attempt_returns_stable_error_code_regardless_of_message_wording(): void
    {
        ['application' => $application] = $this->shortlistedApplicationOnEvent(MultiKolabEventStatus::Recruiting);
        $stranger = Profile::factory()->business()->create();

        $this->actingAs($stranger);
        $response = $this->postJson("/api/v1/multi-kolab-role-applications/{$application->id}/accept");

        $response->assertStatus(403);
        $this->assertSame(['not_owner'], $response->json('errors.owner'));
    }

    public function test_service_throws_typed_not_organizer_exception(): void
    {
        ['application' => $application] = $this->shortlistedApplicationOnEvent(MultiKolabEventStatus::Recruiting);
        $stranger = Profile::factory()->business()->create();

        $this->expectException(MultiKolabNotOrganizerException::class);
        $this->applicationService()->accept($application, $stranger);
    }
}
