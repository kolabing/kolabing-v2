<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\ApplicationStatus;
use App\Enums\CollaborationStatus;
use App\Enums\SubscriptionSource;
use App\Enums\SubscriptionStatus;
use App\Enums\UserType;
use App\Models\Application;
use App\Models\BusinessSubscription;
use App\Models\Collaboration;
use App\Models\CollaborationFeedback;
use App\Models\Kolab;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * PR 1 (2026-06-26): lightweight, required completion-confirmation step
 * (yes/no/not_yet), decoupled from the rich, optional collaboration_feedback
 * table. /complete now gates on this instead.
 */
class CollaborationCompletionConfirmationTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @return array{collab: Collaboration, business: Profile, community: Profile, opportunity: Kolab}
     */
    private function makeActiveCollab(): array
    {
        $business = Profile::factory()->business()->create();
        BusinessSubscription::query()->updateOrCreate(
            ['profile_id' => $business->id],
            ['source' => SubscriptionSource::Maintainer, 'status' => SubscriptionStatus::Active],
        );

        $community = Profile::factory()->community()->create();

        $opportunity = Kolab::factory()->published()->create([
            'creator_profile_id' => $business->id,
        ]);

        $application = Application::factory()->create([
            'kolab_id' => $opportunity->id,
            'applicant_profile_id' => $community->id,
            'applicant_profile_type' => UserType::Community,
            'status' => ApplicationStatus::Accepted,
        ]);

        $collab = Collaboration::factory()->active()->create([
            'kolab_id' => $opportunity->id,
            'application_id' => $application->id,
            'creator_profile_id' => $business->id,
            'applicant_profile_id' => $community->id,
            'business_profile_id' => $business->businessProfile?->id,
            'community_profile_id' => $community->communityProfile?->id,
        ]);

        return ['collab' => $collab, 'business' => $business, 'community' => $community, 'opportunity' => $opportunity];
    }

    public function test_submitting_completion_confirmation_awards_xp(): void
    {
        ['collab' => $collab, 'business' => $business] = $this->makeActiveCollab();

        $this->actingAs($business)
            ->postJson(route('api.v1.collaborations.completion.store', $collab), ['status' => 'yes'])
            ->assertCreated();

        $this->assertDatabaseHas('collaboration_completions', [
            'collaboration_id' => $collab->id,
            'profile_id' => $business->id,
            'role' => 'creator',
            'status' => 'yes',
        ]);

        $this->assertDatabaseHas('point_ledger', [
            'profile_id' => $business->id,
            'event_type' => 'collaboration_completion_confirmed',
            'reference_id' => $collab->id,
        ]);
    }

    public function test_resubmitting_to_change_status_does_not_duplicate_xp(): void
    {
        ['collab' => $collab, 'business' => $business] = $this->makeActiveCollab();

        $this->actingAs($business)
            ->postJson(route('api.v1.collaborations.completion.store', $collab), ['status' => 'not_yet'])
            ->assertCreated();

        $this->actingAs($business)
            ->postJson(route('api.v1.collaborations.completion.store', $collab), ['status' => 'yes'])
            ->assertCreated();

        $this->assertSame(1, \App\Models\CollaborationCompletion::query()
            ->where('collaboration_id', $collab->id)
            ->where('profile_id', $business->id)
            ->count());

        $this->assertSame(1, \App\Models\PointLedger::query()
            ->where('profile_id', $business->id)
            ->where('event_type', 'collaboration_completion_confirmed')
            ->where('reference_id', $collab->id)
            ->count());

        $this->assertDatabaseHas('collaboration_completions', [
            'collaboration_id' => $collab->id,
            'profile_id' => $business->id,
            'status' => 'yes',
        ]);
    }

    public function test_complete_returns_422_when_caller_has_not_confirmed(): void
    {
        ['collab' => $collab, 'business' => $business] = $this->makeActiveCollab();

        $this->actingAs($business)
            ->postJson(route('api.v1.collaborations.complete', $collab))
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'awaiting_own_completion_confirmation');
    }

    public function test_complete_returns_422_when_partner_has_not_confirmed(): void
    {
        ['collab' => $collab, 'business' => $business] = $this->makeActiveCollab();

        $this->actingAs($business)
            ->postJson(route('api.v1.collaborations.completion.store', $collab), ['status' => 'yes'])
            ->assertCreated();

        $this->actingAs($business)
            ->postJson(route('api.v1.collaborations.complete', $collab))
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'awaiting_partner_completion_confirmation')
            ->assertJsonPath('errors.pending_completion_from', ['community']);
    }

    public function test_complete_succeeds_when_both_confirm_yes(): void
    {
        ['collab' => $collab, 'business' => $business, 'community' => $community] = $this->makeActiveCollab();

        foreach ([$business, $community] as $actor) {
            $this->actingAs($actor)
                ->postJson(route('api.v1.collaborations.completion.store', $collab), ['status' => 'yes'])
                ->assertCreated();
        }

        $this->actingAs($business)
            ->postJson(route('api.v1.collaborations.complete', $collab))
            ->assertOk();

        $fresh = $collab->fresh();
        $this->assertSame(CollaborationStatus::Completed, $fresh->status);
    }

    public function test_complete_returns_422_when_partner_confirmed_no(): void
    {
        ['collab' => $collab, 'business' => $business, 'community' => $community] = $this->makeActiveCollab();

        $this->actingAs($business)
            ->postJson(route('api.v1.collaborations.completion.store', $collab), ['status' => 'yes'])
            ->assertCreated();

        $this->actingAs($community)
            ->postJson(route('api.v1.collaborations.completion.store', $collab), ['status' => 'no'])
            ->assertCreated();

        $this->actingAs($business)
            ->postJson(route('api.v1.collaborations.complete', $collab))
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'completion_not_confirmed')
            ->assertJsonPath('errors.own_status', 'yes')
            ->assertJsonPath('errors.partner_status', 'no');

        $this->assertSame(CollaborationStatus::Active, $collab->fresh()->status);
    }

    public function test_complete_returns_422_when_partner_confirmed_not_yet(): void
    {
        ['collab' => $collab, 'business' => $business, 'community' => $community] = $this->makeActiveCollab();

        $this->actingAs($business)
            ->postJson(route('api.v1.collaborations.completion.store', $collab), ['status' => 'yes'])
            ->assertCreated();

        $this->actingAs($community)
            ->postJson(route('api.v1.collaborations.completion.store', $collab), ['status' => 'not_yet'])
            ->assertCreated();

        $this->actingAs($business)
            ->postJson(route('api.v1.collaborations.complete', $collab))
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'completion_not_confirmed');

        $this->assertSame(CollaborationStatus::Active, $collab->fresh()->status);
    }

    public function test_complete_returns_completion_not_confirmed_when_own_answer_is_not_yet_and_partner_silent(): void
    {
        // 2026-06-27 QA fix: the caller's OWN non-yes answer must be reported
        // even when the partner hasn't answered at all — never the misleading
        // "awaiting_partner_completion_confirmation".
        ['collab' => $collab, 'business' => $business] = $this->makeActiveCollab();

        $this->actingAs($business)
            ->postJson(route('api.v1.collaborations.completion.store', $collab), ['status' => 'not_yet'])
            ->assertCreated();

        $this->actingAs($business)
            ->postJson(route('api.v1.collaborations.complete', $collab))
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'completion_not_confirmed')
            ->assertJsonPath('errors.own_status', 'not_yet')
            ->assertJsonPath('errors.partner_status', null);
    }

    public function test_complete_returns_completion_not_confirmed_when_own_answer_is_no_and_partner_silent(): void
    {
        ['collab' => $collab, 'business' => $business] = $this->makeActiveCollab();

        $this->actingAs($business)
            ->postJson(route('api.v1.collaborations.completion.store', $collab), ['status' => 'no'])
            ->assertCreated();

        $this->actingAs($business)
            ->postJson(route('api.v1.collaborations.complete', $collab))
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'completion_not_confirmed')
            ->assertJsonPath('errors.own_status', 'no');
    }

    public function test_complete_returns_awaiting_partner_only_when_caller_confirmed_yes(): void
    {
        ['collab' => $collab, 'business' => $business] = $this->makeActiveCollab();

        $this->actingAs($business)
            ->postJson(route('api.v1.collaborations.completion.store', $collab), ['status' => 'yes'])
            ->assertCreated();

        $this->actingAs($business)
            ->postJson(route('api.v1.collaborations.complete', $collab))
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'awaiting_partner_completion_confirmation');
    }

    public function test_admin_force_complete_bypasses_confirmation_gate_and_awards_no_xp(): void
    {
        ['collab' => $collab, 'opportunity' => $kolab] = $this->makeActiveCollab();

        $maintainer = User::factory()->create(['is_maintainer' => true]);

        $this->actingAs($maintainer, 'admin')
            ->post(route('admin.kolabs.collaboration.complete', $kolab), [
                'reason' => 'Resolved manually.',
            ])
            ->assertRedirect(route('admin.kolabs.edit', $kolab));

        $fresh = $collab->fresh();
        $this->assertSame(CollaborationStatus::Completed, $fresh->status);
        $this->assertNull($fresh->completed_by_profile_id);

        $this->assertDatabaseMissing('point_ledger', [
            'event_type' => 'collaboration_completion_confirmed',
            'reference_id' => $collab->id,
        ]);
    }

    public function test_invalid_status_value_is_rejected(): void
    {
        ['collab' => $collab, 'business' => $business] = $this->makeActiveCollab();

        $this->actingAs($business)
            ->postJson(route('api.v1.collaborations.completion.store', $collab), ['status' => 'maybe'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    public function test_backfill_migration_creates_implicit_yes_rows_without_awarding_xp(): void
    {
        ['collab' => $collab, 'business' => $business] = $this->makeActiveCollab();

        CollaborationFeedback::factory()->create([
            'collaboration_id' => $collab->id,
            'reviewer_profile_id' => $business->id,
            'reviewer_type' => 'business',
            'reviewer_role' => 'creator',
            'mirrored_from_review' => false,
        ]);

        $this->assertDatabaseMissing('collaboration_completions', [
            'collaboration_id' => $collab->id,
            'profile_id' => $business->id,
        ]);

        (require database_path('migrations/2026_06_27_000001_backfill_collaboration_completions_from_feedback.php'))->up();

        $this->assertDatabaseHas('collaboration_completions', [
            'collaboration_id' => $collab->id,
            'profile_id' => $business->id,
            'role' => 'creator',
            'status' => 'yes',
        ]);

        $this->assertDatabaseMissing('point_ledger', [
            'event_type' => 'collaboration_completion_confirmed',
        ]);
    }

    public function test_backfill_migration_is_idempotent(): void
    {
        ['collab' => $collab, 'business' => $business] = $this->makeActiveCollab();

        CollaborationFeedback::factory()->create([
            'collaboration_id' => $collab->id,
            'reviewer_profile_id' => $business->id,
            'reviewer_type' => 'business',
            'reviewer_role' => 'creator',
            'mirrored_from_review' => false,
        ]);

        $migration = require database_path('migrations/2026_06_27_000001_backfill_collaboration_completions_from_feedback.php');
        $migration->up();
        $migration->up();

        $this->assertSame(1, \App\Models\CollaborationCompletion::query()
            ->where('collaboration_id', $collab->id)
            ->where('profile_id', $business->id)
            ->count());
    }

    public function test_resource_exposes_completion_state(): void
    {
        ['collab' => $collab, 'business' => $business, 'community' => $community] = $this->makeActiveCollab();

        $this->actingAs($business)
            ->postJson(route('api.v1.collaborations.completion.store', $collab), ['status' => 'yes'])
            ->assertCreated();

        $businessView = $this->actingAs($business)
            ->getJson(route('api.v1.collaborations.show', $collab));

        $businessView->assertOk()
            ->assertJsonPath('data.pending_completion_from', ['community'])
            ->assertJsonPath('data.viewer_must_confirm_completion', false)
            ->assertJsonPath('data.own_completion.status', 'yes')
            ->assertJsonPath('data.partner_completion_status', null);

        $communityView = $this->actingAs($community)
            ->getJson(route('api.v1.collaborations.show', $collab));

        $communityView->assertOk()
            ->assertJsonPath('data.viewer_must_confirm_completion', true)
            ->assertJsonPath('data.partner_completion_status', 'yes');
    }

    public function test_resource_marks_viewer_who_answered_not_yet_as_still_pending(): void
    {
        // A viewer who answered no/not_yet has NOT confirmed yes, so the
        // resource must keep telling them to confirm and list them as pending —
        // otherwise the client shows "ready" while /complete still 422s.
        ['collab' => $collab, 'business' => $business] = $this->makeActiveCollab();

        $this->actingAs($business)
            ->postJson(route('api.v1.collaborations.completion.store', $collab), ['status' => 'not_yet'])
            ->assertCreated();

        $this->actingAs($business)
            ->getJson(route('api.v1.collaborations.show', $collab))
            ->assertOk()
            ->assertJsonPath('data.viewer_must_confirm_completion', true)
            ->assertJsonPath('data.own_completion.status', 'not_yet')
            ->assertJsonFragment(['pending_completion_from' => ['business', 'community']]);
    }

    public function test_completion_response_is_shaped_and_hides_internal_columns(): void
    {
        ['collab' => $collab, 'business' => $business] = $this->makeActiveCollab();

        $response = $this->actingAs($business)
            ->postJson(route('api.v1.collaborations.completion.store', $collab), ['status' => 'yes', 'note' => 'done'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'yes')
            ->assertJsonPath('data.note', 'done');

        $data = $response->json('data');
        $this->assertSame(['status', 'note', 'created_at', 'updated_at'], array_keys($data));
        $this->assertArrayNotHasKey('role', $data);
        $this->assertArrayNotHasKey('profile_id', $data);
        $this->assertArrayNotHasKey('collaboration_id', $data);
    }

    public function test_submitting_completion_on_terminal_collaboration_is_rejected_and_awards_no_xp(): void
    {
        ['collab' => $collab, 'business' => $business] = $this->makeActiveCollab();

        $collab->update(['status' => CollaborationStatus::Completed]);

        $this->actingAs($business)
            ->postJson(route('api.v1.collaborations.completion.store', $collab), ['status' => 'yes'])
            ->assertStatus(422);

        $this->assertDatabaseMissing('collaboration_completions', [
            'collaboration_id' => $collab->id,
            'profile_id' => $business->id,
        ]);
        $this->assertDatabaseMissing('point_ledger', [
            'profile_id' => $business->id,
            'event_type' => 'collaboration_completion_confirmed',
            'reference_id' => $collab->id,
        ]);
    }
}
