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
use App\Models\CollaborationReview;
use App\Models\Kolab;
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

    public function test_business_submits_full_feedback_and_xp_is_awarded(): void
    {
        ['collab' => $collab, 'business' => $business] = $this->makeActiveCollab();

        $response = $this->actingAs($business)
            ->postJson(route('api.v1.collaborations.feedback.store', $collab), [
                'rating' => 5,
                'expectation_match' => true,
                'would_recommend' => true,
                'would_collaborate_again' => true,
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
                'would_collaborate_again' => true,
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
                'would_collaborate_again' => true,
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
                'would_collaborate_again' => true,
                'benefits' => 'should be rejected',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['benefits']);
    }

    public function test_duplicate_submission_returns_409(): void
    {
        ['collab' => $collab, 'business' => $business] = $this->makeActiveCollab();

        $payload = ['rating' => 5, 'expectation_match' => true, 'would_recommend' => true, 'would_collaborate_again' => true];

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
                'rating' => 3, 'expectation_match' => true, 'would_recommend' => true, 'would_collaborate_again' => true, 'revenue' => '100',
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
                'rating' => 4, 'expectation_match' => true, 'would_recommend' => true, 'would_collaborate_again' => true,
            ])->assertCreated();

        $this->actingAs($community)
            ->postJson(route('api.v1.collaborations.feedback.store', $collab), [
                'rating' => 5, 'expectation_match' => true, 'would_recommend' => true, 'would_collaborate_again' => true,
            ])->assertCreated();

        $this->actingAs($business)
            ->putJson(route('api.v1.collaborations.feedback.update', $collab), [
                'rating' => 1,
            ])
            ->assertStatus(423)
            ->assertJsonPath('error_code', 'feedback_locked');
    }

    public function test_complete_returns_422_awaiting_own_completion_confirmation_when_caller_has_not_confirmed(): void
    {
        // PR 1 (2026-06-26): /complete no longer cares about feedback at all —
        // it gates on the lightweight completion-confirmation table.
        ['collab' => $collab, 'business' => $business] = $this->makeActiveCollab();

        $this->actingAs($business)
            ->postJson(route('api.v1.collaborations.complete', $collab))
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'awaiting_own_completion_confirmation');

        $this->assertSame(CollaborationStatus::Active, $collab->fresh()->status);
    }

    public function test_complete_returns_422_awaiting_partner_completion_confirmation(): void
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

    public function test_submitting_feedback_alone_does_not_satisfy_complete(): void
    {
        // Completion gates purely on real completion confirmations — there is
        // no feedback-based fallback. Feedback alone must NOT complete the Kolab.
        ['collab' => $collab, 'business' => $business, 'community' => $community] = $this->makeActiveCollab();

        foreach ([$business, $community] as $actor) {
            $this->actingAs($actor)
                ->postJson(route('api.v1.collaborations.feedback.store', $collab), [
                    'rating' => 4, 'expectation_match' => true, 'would_recommend' => true, 'would_collaborate_again' => true,
                ])->assertCreated();
        }

        $this->actingAs($business)
            ->postJson(route('api.v1.collaborations.complete', $collab))
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'awaiting_own_completion_confirmation');

        $this->assertSame(CollaborationStatus::Active, $collab->fresh()->status);
        $this->assertDatabaseMissing('collaboration_completions', [
            'collaboration_id' => $collab->id,
        ]);
    }

    public function test_review_alone_does_not_satisfy_complete(): void
    {
        ['collab' => $collab, 'business' => $business, 'community' => $community] = $this->makeActiveCollab();

        foreach ([$business, $community] as $actor) {
            $this->actingAs($actor)
                ->postJson(route('api.v1.collaborations.review', $collab), [
                    'rating' => 4,
                ])->assertCreated();
        }

        // The /review mirror still writes feedback stubs (impact data), but
        // those no longer satisfy the completion gate.
        $this->actingAs($business)
            ->postJson(route('api.v1.collaborations.complete', $collab))
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'awaiting_own_completion_confirmation');

        $this->assertSame(CollaborationStatus::Active, $collab->fresh()->status);
    }

    public function test_complete_requires_partner_completion_even_when_partner_left_feedback(): void
    {
        // The caller confirmed via /completion; the partner only left feedback.
        // Feedback is not a confirmation, so /complete must wait on the partner.
        ['collab' => $collab, 'business' => $business, 'community' => $community] = $this->makeActiveCollab();

        $this->actingAs($business)
            ->postJson(route('api.v1.collaborations.completion.store', $collab), ['status' => 'yes'])
            ->assertCreated();

        $this->actingAs($community)
            ->postJson(route('api.v1.collaborations.feedback.store', $collab), [
                'rating' => 5, 'expectation_match' => true, 'would_recommend' => true, 'would_collaborate_again' => true,
            ])->assertCreated();

        $this->actingAs($business)
            ->postJson(route('api.v1.collaborations.complete', $collab))
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'awaiting_partner_completion_confirmation')
            ->assertJsonPath('errors.pending_completion_from', ['community']);

        $this->assertSame(CollaborationStatus::Active, $collab->fresh()->status);
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
                'would_collaborate_again' => true,
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
                'rating' => 4, 'expectation_match' => true, 'would_recommend' => true, 'would_collaborate_again' => true,
            ])
            ->assertCreated();
    }

    public function test_resource_exposes_pending_and_own_feedback(): void
    {
        ['collab' => $collab, 'business' => $business, 'community' => $community] = $this->makeActiveCollab();

        $this->actingAs($business)
            ->postJson(route('api.v1.collaborations.feedback.store', $collab), [
                'rating' => 5, 'expectation_match' => true, 'would_recommend' => true, 'would_collaborate_again' => true,
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
                    'rating' => 4, 'expectation_match' => true, 'would_recommend' => false, 'would_collaborate_again' => false,
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

    public function test_would_collaborate_again_is_required_on_feedback(): void
    {
        ['collab' => $collab, 'business' => $business] = $this->makeActiveCollab();

        $this->actingAs($business)
            ->postJson(route('api.v1.collaborations.feedback.store', $collab), [
                'rating' => 5,
                'expectation_match' => true,
                'would_recommend' => true,
                // would_collaborate_again intentionally omitted.
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['would_collaborate_again']);
    }

    public function test_would_collaborate_again_persists_and_is_emitted(): void
    {
        ['collab' => $collab, 'business' => $business] = $this->makeActiveCollab();

        $this->actingAs($business)
            ->postJson(route('api.v1.collaborations.feedback.store', $collab), [
                'rating' => 5,
                'expectation_match' => true,
                'would_recommend' => true,
                'would_collaborate_again' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.would_collaborate_again', true);

        $this->assertDatabaseHas('collaboration_feedback', [
            'collaboration_id' => $collab->id,
            'reviewer_profile_id' => $business->id,
            'would_collaborate_again' => true,
        ]);

        // Surfaced in own_feedback on the collaboration resource too.
        $this->actingAs($business)
            ->getJson(route('api.v1.collaborations.show', $collab))
            ->assertOk()
            ->assertJsonPath('data.own_feedback.would_collaborate_again', true);
    }

    public function test_submitting_feedback_creates_exactly_one_public_review_for_the_reviewer(): void
    {
        ['collab' => $collab, 'business' => $business] = $this->makeActiveCollab();

        $this->actingAs($business)
            ->postJson(route('api.v1.collaborations.feedback.store', $collab), [
                'rating' => 4,
                'expectation_match' => true,
                'would_recommend' => true,
                'would_collaborate_again' => true,
            ])->assertCreated();

        $reviews = CollaborationReview::query()
            ->where('collaboration_id', $collab->id)
            ->where('reviewer_profile_id', $business->id)
            ->get();

        $this->assertCount(1, $reviews);
        $review = $reviews->first();
        $this->assertSame(4, $review->rating);
        $this->assertTrue($review->would_collaborate_again);
        // reviewed_profile_id is the partner (cross-party attribution).
        $this->assertSame($collab->applicant_profile_id, $review->reviewed_profile_id);
        $this->assertSame('creator', $review->reviewer_role);
    }

    public function test_community_benefits_text_is_mirrored_into_review_body(): void
    {
        ['collab' => $collab, 'community' => $community] = $this->makeActiveCollab();

        $this->actingAs($community)
            ->postJson(route('api.v1.collaborations.feedback.store', $collab), [
                'rating' => 5,
                'expectation_match' => true,
                'would_recommend' => true,
                'would_collaborate_again' => false,
                'benefits' => 'Great exposure and free drinks',
            ])->assertCreated();

        $review = CollaborationReview::query()
            ->where('collaboration_id', $collab->id)
            ->where('reviewer_profile_id', $community->id)
            ->firstOrFail();

        $this->assertSame('Great exposure and free drinks', $review->body);
        $this->assertFalse($review->would_collaborate_again);
    }

    public function test_resubmitting_via_edit_updates_the_same_review_no_duplicate(): void
    {
        ['collab' => $collab, 'business' => $business] = $this->makeActiveCollab();

        $this->actingAs($business)
            ->postJson(route('api.v1.collaborations.feedback.store', $collab), [
                'rating' => 3,
                'expectation_match' => true,
                'would_recommend' => true,
                'would_collaborate_again' => false,
            ])->assertCreated();

        // Edit the feedback while the partner has not submitted.
        $this->actingAs($business)
            ->putJson(route('api.v1.collaborations.feedback.update', $collab), [
                'rating' => 5,
                'expectation_match' => true,
                'would_recommend' => true,
                'would_collaborate_again' => true,
            ])->assertOk();

        // Still exactly one feedback row and one review row, both updated.
        $this->assertSame(1, CollaborationFeedback::query()
            ->where('collaboration_id', $collab->id)
            ->where('reviewer_profile_id', $business->id)
            ->count());

        $reviews = CollaborationReview::query()
            ->where('collaboration_id', $collab->id)
            ->where('reviewer_profile_id', $business->id)
            ->get();

        $this->assertCount(1, $reviews);
        $this->assertSame(5, $reviews->first()->rating);
        $this->assertTrue($reviews->first()->would_collaborate_again);
    }

    public function test_feedback_mirror_does_not_create_a_second_feedback_row_no_loop(): void
    {
        ['collab' => $collab, 'business' => $business] = $this->makeActiveCollab();

        $this->actingAs($business)
            ->postJson(route('api.v1.collaborations.feedback.store', $collab), [
                'rating' => 4,
                'expectation_match' => true,
                'would_recommend' => true,
                'would_collaborate_again' => true,
            ])->assertCreated();

        // The feedback->review mirror must NOT trigger the inverse review->feedback
        // mirror: exactly one feedback row, and it is NOT a mirrored stub.
        $feedbacks = CollaborationFeedback::query()
            ->where('collaboration_id', $collab->id)
            ->where('reviewer_profile_id', $business->id)
            ->get();

        $this->assertCount(1, $feedbacks);
        $this->assertFalse($feedbacks->first()->mirrored_from_review);
    }
}
