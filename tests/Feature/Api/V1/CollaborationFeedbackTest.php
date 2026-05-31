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
use App\Models\CollabOpportunity;
use App\Models\Collaboration;
use App\Models\CollaborationFeedback;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CollaborationFeedbackTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Build a business creator + community applicant + ACTIVE collaboration
     * with both participant slots populated, returning all four key actors.
     *
     * @return array{collab: Collaboration, business: Profile, community: Profile, opportunity: CollabOpportunity}
     */
    private function makeActiveCollab(): array
    {
        $business = Profile::factory()->business()->create();
        BusinessSubscription::query()->updateOrCreate(
            ['profile_id' => $business->id],
            ['source' => SubscriptionSource::Maintainer, 'status' => SubscriptionStatus::Active],
        );

        $community = Profile::factory()->community()->create();

        $opportunity = CollabOpportunity::factory()->published()->create([
            'creator_profile_id' => $business->id,
            'creator_profile_type' => UserType::Business,
        ]);

        $application = Application::factory()->create([
            'collab_opportunity_id' => $opportunity->id,
            'applicant_profile_id' => $community->id,
            'applicant_profile_type' => UserType::Community,
            'status' => ApplicationStatus::Accepted,
        ]);

        $collab = Collaboration::factory()->active()->create([
            'collab_opportunity_id' => $opportunity->id,
            'application_id' => $application->id,
            'creator_profile_id' => $business->id,
            'applicant_profile_id' => $community->id,
            'business_profile_id' => $business->businessProfile?->id,
            'community_profile_id' => $community->communityProfile?->id,
        ]);

        return ['collab' => $collab, 'business' => $business, 'community' => $community, 'opportunity' => $opportunity];
    }

    public function test_business_submits_full_feedback_and_xp_is_awarded(): void
    {
        ['collab' => $collab, 'business' => $business] = $this->makeActiveCollab();

        $response = $this->actingAs($business)
            ->postJson(route('api.v1.collaborations.feedback.store', $collab), [
                'rating' => 5,
                'expectation_match' => true,
                'would_recommend' => true,
                'posts_reels' => 3,
                'stories_posted' => 12,
                'revenue' => '450.50',
            ]);

        $response->assertCreated();
        $this->assertDatabaseHas('collaboration_feedback', [
            'collaboration_id' => $collab->id,
            'reviewer_profile_id' => $business->id,
            'reviewer_type' => 'business',
            'reviewer_role' => 'creator',
            'rating' => 5,
            'mirrored_from_review' => false,
        ]);
        // CollaborationComplete XP fires on feedback submission (Q7).
        $this->assertDatabaseHas('point_ledger', [
            'profile_id' => $business->id,
            'event_type' => 'collaboration_complete',
            'reference_id' => $collab->id,
        ]);
    }

    public function test_community_submits_feedback_with_benefits_only(): void
    {
        ['collab' => $collab, 'community' => $community] = $this->makeActiveCollab();

        $this->actingAs($community)
            ->postJson(route('api.v1.collaborations.feedback.store', $collab), [
                'rating' => 4,
                'expectation_match' => false,
                'would_recommend' => true,
                'benefits' => 'Free coffee for the whole group',
            ])
            ->assertCreated();

        $this->assertDatabaseHas('collaboration_feedback', [
            'collaboration_id' => $collab->id,
            'reviewer_profile_id' => $community->id,
            'reviewer_type' => 'community',
            'reviewer_role' => 'applicant',
            'benefits' => 'Free coffee for the whole group',
        ]);
    }

    public function test_community_sending_business_only_fields_is_rejected(): void
    {
        ['collab' => $collab, 'community' => $community] = $this->makeActiveCollab();

        $this->actingAs($community)
            ->postJson(route('api.v1.collaborations.feedback.store', $collab), [
                'rating' => 4,
                'expectation_match' => true,
                'would_recommend' => true,
                'revenue' => '500',
                'stories_posted' => 10,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['revenue', 'stories_posted']);
    }

    public function test_business_sending_benefits_is_rejected(): void
    {
        ['collab' => $collab, 'business' => $business] = $this->makeActiveCollab();

        $this->actingAs($business)
            ->postJson(route('api.v1.collaborations.feedback.store', $collab), [
                'rating' => 4,
                'expectation_match' => true,
                'would_recommend' => true,
                'benefits' => 'should be rejected',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['benefits']);
    }

    public function test_duplicate_submission_returns_409(): void
    {
        ['collab' => $collab, 'business' => $business] = $this->makeActiveCollab();

        $payload = ['rating' => 5, 'expectation_match' => true, 'would_recommend' => true];

        $this->actingAs($business)
            ->postJson(route('api.v1.collaborations.feedback.store', $collab), $payload)
            ->assertCreated();

        $this->actingAs($business)
            ->postJson(route('api.v1.collaborations.feedback.store', $collab), $payload)
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'feedback_already_submitted');
    }

    public function test_put_edits_own_row_while_partner_has_not_submitted(): void
    {
        ['collab' => $collab, 'business' => $business] = $this->makeActiveCollab();

        $this->actingAs($business)
            ->postJson(route('api.v1.collaborations.feedback.store', $collab), [
                'rating' => 3, 'expectation_match' => true, 'would_recommend' => true, 'revenue' => '100',
            ])->assertCreated();

        $this->actingAs($business)
            ->putJson(route('api.v1.collaborations.feedback.update', $collab), [
                'rating' => 5,
                'revenue' => '999.99',
            ])
            ->assertOk();

        $this->assertDatabaseHas('collaboration_feedback', [
            'collaboration_id' => $collab->id,
            'reviewer_profile_id' => $business->id,
            'rating' => 5,
            'revenue' => '999.99',
        ]);
    }

    public function test_put_after_partner_submits_returns_423_locked(): void
    {
        ['collab' => $collab, 'business' => $business, 'community' => $community] = $this->makeActiveCollab();

        $this->actingAs($business)
            ->postJson(route('api.v1.collaborations.feedback.store', $collab), [
                'rating' => 4, 'expectation_match' => true, 'would_recommend' => true,
            ])->assertCreated();

        $this->actingAs($community)
            ->postJson(route('api.v1.collaborations.feedback.store', $collab), [
                'rating' => 5, 'expectation_match' => true, 'would_recommend' => true,
            ])->assertCreated();

        $this->actingAs($business)
            ->putJson(route('api.v1.collaborations.feedback.update', $collab), [
                'rating' => 1,
            ])
            ->assertStatus(423)
            ->assertJsonPath('error_code', 'feedback_locked');
    }

    public function test_complete_returns_422_awaiting_own_feedback_when_caller_has_not_submitted(): void
    {
        ['collab' => $collab, 'business' => $business] = $this->makeActiveCollab();

        $this->actingAs($business)
            ->postJson(route('api.v1.collaborations.complete', $collab))
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'awaiting_own_feedback');

        $this->assertSame(CollaborationStatus::Active, $collab->fresh()->status);
    }

    public function test_complete_returns_422_awaiting_partner_feedback(): void
    {
        ['collab' => $collab, 'business' => $business] = $this->makeActiveCollab();

        $this->actingAs($business)
            ->postJson(route('api.v1.collaborations.feedback.store', $collab), [
                'rating' => 5, 'expectation_match' => true, 'would_recommend' => true,
            ])->assertCreated();

        $this->actingAs($business)
            ->postJson(route('api.v1.collaborations.complete', $collab))
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'awaiting_partner_feedback')
            ->assertJsonPath('errors.pending_feedback_from', ['community']);
    }

    public function test_complete_succeeds_when_both_feedbacks_exist(): void
    {
        ['collab' => $collab, 'business' => $business, 'community' => $community] = $this->makeActiveCollab();

        foreach ([$business, $community] as $actor) {
            $this->actingAs($actor)
                ->postJson(route('api.v1.collaborations.feedback.store', $collab), [
                    'rating' => 4, 'expectation_match' => true, 'would_recommend' => true,
                ])->assertCreated();
        }

        $this->actingAs($business)
            ->postJson(route('api.v1.collaborations.complete', $collab))
            ->assertOk();

        $fresh = $collab->fresh();
        $this->assertSame(CollaborationStatus::Completed, $fresh->status);
        $this->assertNotNull($fresh->completed_at);
    }

    public function test_review_mirrors_to_feedback_so_complete_succeeds(): void
    {
        ['collab' => $collab, 'business' => $business, 'community' => $community] = $this->makeActiveCollab();

        // Both parties on the legacy app path: post /review only.
        foreach ([$business, $community] as $actor) {
            $this->actingAs($actor)
                ->postJson(route('api.v1.collaborations.review', $collab), [
                    'rating' => 4,
                ])->assertCreated();
        }

        $this->assertSame(2, CollaborationFeedback::query()
            ->where('collaboration_id', $collab->id)
            ->where('mirrored_from_review', true)
            ->count());

        // /complete now succeeds because both stub feedback rows exist.
        $this->actingAs($business)
            ->postJson(route('api.v1.collaborations.complete', $collab))
            ->assertOk();
    }

    public function test_feedback_submit_upgrades_existing_mirrored_stub(): void
    {
        ['collab' => $collab, 'business' => $business] = $this->makeActiveCollab();

        // Legacy /review call writes the mirrored stub first.
        $this->actingAs($business)
            ->postJson(route('api.v1.collaborations.review', $collab), ['rating' => 3])
            ->assertCreated();

        $stubBefore = CollaborationFeedback::query()
            ->where('collaboration_id', $collab->id)
            ->where('reviewer_profile_id', $business->id)
            ->firstOrFail();
        $this->assertTrue($stubBefore->mirrored_from_review);
        $this->assertNull($stubBefore->expectation_match);

        // Now the user upgrades to the rich endpoint.
        $this->actingAs($business)
            ->postJson(route('api.v1.collaborations.feedback.store', $collab), [
                'rating' => 5,
                'expectation_match' => true,
                'would_recommend' => true,
                'revenue' => '300',
            ])
            ->assertCreated();

        $upgraded = $stubBefore->fresh();
        $this->assertFalse($upgraded->mirrored_from_review);
        $this->assertTrue($upgraded->expectation_match);
        $this->assertSame(5, $upgraded->rating);
        $this->assertSame('300.00', $upgraded->revenue);
    }

    public function test_lapsed_business_can_still_submit_feedback(): void
    {
        ['collab' => $collab, 'business' => $business] = $this->makeActiveCollab();

        // Lapse the subscription.
        BusinessSubscription::query()
            ->where('profile_id', $business->id)
            ->update(['status' => SubscriptionStatus::Inactive]);

        $business->refresh();
        $this->assertFalse($business->hasActiveSubscription());

        $this->actingAs($business)
            ->postJson(route('api.v1.collaborations.feedback.store', $collab), [
                'rating' => 4, 'expectation_match' => true, 'would_recommend' => true,
            ])
            ->assertCreated();
    }

    public function test_resource_exposes_pending_and_own_feedback(): void
    {
        ['collab' => $collab, 'business' => $business, 'community' => $community] = $this->makeActiveCollab();

        $this->actingAs($business)
            ->postJson(route('api.v1.collaborations.feedback.store', $collab), [
                'rating' => 5, 'expectation_match' => true, 'would_recommend' => true,
            ])->assertCreated();

        $response = $this->actingAs($business)
            ->getJson(route('api.v1.collaborations.show', $collab));

        $response->assertOk()
            ->assertJsonPath('data.pending_feedback_from', ['community'])
            ->assertJsonPath('data.viewer_must_submit_feedback', false)
            ->assertJsonPath('data.own_feedback.rating', 5)
            ->assertJsonPath('data.partner_feedback', null);

        $communityView = $this->actingAs($community)
            ->getJson(route('api.v1.collaborations.show', $collab));
        $communityView->assertOk()
            ->assertJsonPath('data.viewer_must_submit_feedback', true);
    }

    public function test_partner_feedback_appears_only_when_both_real_rows_exist(): void
    {
        ['collab' => $collab, 'business' => $business, 'community' => $community] = $this->makeActiveCollab();

        foreach ([$business, $community] as $actor) {
            $this->actingAs($actor)
                ->postJson(route('api.v1.collaborations.feedback.store', $collab), [
                    'rating' => 4, 'expectation_match' => true, 'would_recommend' => false,
                ])->assertCreated();
        }

        $response = $this->actingAs($business)
            ->getJson(route('api.v1.collaborations.show', $collab));

        $response->assertOk()
            ->assertJsonPath('data.partner_feedback.rating', 4)
            ->assertJsonPath('data.partner_feedback.would_recommend', false)
            ->assertJsonMissingPath('data.partner_feedback.revenue')
            ->assertJsonMissingPath('data.partner_feedback.benefits');
    }
}
