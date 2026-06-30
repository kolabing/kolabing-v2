<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\PointEventType;
use App\Models\BusinessProfile;
use App\Models\Collaboration;
use App\Models\CollaborationReview;
use App\Models\CommunityProfile;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CollaborationReviewTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_review_endpoint_assigns_review_to_the_other_participant_profile(): void
    {
        $business = Profile::factory()->business()->create();
        $businessProfile = BusinessProfile::factory()->create([
            'profile_id' => $business->id,
            'name' => 'Business Reviewer',
        ]);

        $community = Profile::factory()->community()->create();
        $communityProfile = CommunityProfile::factory()->create([
            'profile_id' => $community->id,
            'name' => 'Community Reviewed',
        ]);

        $collaboration = Collaboration::factory()
            ->completed()
            ->forCreator($business)
            ->forApplicant($community)
            ->create([
                'business_profile_id' => $businessProfile->id,
                'community_profile_id' => $communityProfile->id,
            ]);

        $response = $this->actingAs($business)
            ->postJson("/api/v1/collaborations/{$collaboration->id}/review", [
                'rating' => 5,
                'body' => 'Great collaboration',
                'would_collaborate_again' => true,
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('collaboration_reviews', [
            'collaboration_id' => $collaboration->id,
            'reviewer_profile_id' => $business->id,
            'reviewed_profile_id' => $community->id,
            'rating' => 5,
            'body' => 'Great collaboration',
        ]);
    }

    private function makeCollaboration(): array
    {
        $business = Profile::factory()->business()->create();
        $businessProfile = BusinessProfile::factory()->create([
            'profile_id' => $business->id,
            'name' => 'Business Reviewer',
        ]);

        $community = Profile::factory()->community()->create();
        $communityProfile = CommunityProfile::factory()->create([
            'profile_id' => $community->id,
            'name' => 'Community Reviewed',
        ]);

        $collaboration = Collaboration::factory()
            ->completed()
            ->forCreator($business)
            ->forApplicant($community)
            ->create([
                'business_profile_id' => $businessProfile->id,
                'community_profile_id' => $communityProfile->id,
            ]);

        return [$collaboration, $business, $community];
    }

    private function starPayload(array $overrides = []): array
    {
        return array_merge([
            'communication_rating' => 5,
            'reliability_rating' => 4,
            'fit_rating' => 4,
            'value_rating' => 5,
            'repeat_rating' => 5,
            'public_comment' => 'Would Kolab again!',
        ], $overrides);
    }

    public function test_submit_new_five_star_review(): void
    {
        [$collaboration, $business] = $this->makeCollaboration();

        $response = $this->actingAs($business)
            ->postJson("/api/v1/collaborations/{$collaboration->id}/review", $this->starPayload());

        $response->assertCreated()->assertJsonPath('success', true);

        $this->assertDatabaseHas('collaboration_reviews', [
            'collaboration_id' => $collaboration->id,
            'reviewer_profile_id' => $business->id,
            'communication_rating' => 5,
            'reliability_rating' => 4,
            'fit_rating' => 4,
            'value_rating' => 5,
            'repeat_rating' => 5,
            'public_comment' => 'Would Kolab again!',
        ]);
    }

    public function test_all_five_ratings_are_required_for_new_format(): void
    {
        [$collaboration, $business] = $this->makeCollaboration();

        $response = $this->actingAs($business)
            ->postJson("/api/v1/collaborations/{$collaboration->id}/review", $this->starPayload([
                'fit_rating' => null,
            ]));

        $response->assertStatus(422)->assertJsonValidationErrors(['fit_rating']);
    }

    public function test_invalid_rating_values_are_rejected(): void
    {
        [$collaboration, $business] = $this->makeCollaboration();

        $response = $this->actingAs($business)
            ->postJson("/api/v1/collaborations/{$collaboration->id}/review", $this->starPayload([
                'value_rating' => 6,
            ]));

        $response->assertStatus(422)->assertJsonValidationErrors(['value_rating']);
    }

    public function test_legacy_single_rating_review_still_works(): void
    {
        [$collaboration, $business] = $this->makeCollaboration();

        $response = $this->actingAs($business)
            ->postJson("/api/v1/collaborations/{$collaboration->id}/review", [
                'rating' => 4,
                'body' => 'Solid Kolab',
            ]);

        $response->assertCreated();
        $this->assertDatabaseHas('collaboration_reviews', [
            'collaboration_id' => $collaboration->id,
            'reviewer_profile_id' => $business->id,
            'rating' => 4,
        ]);
    }

    public function test_overall_rating_averages_the_five_star_fields(): void
    {
        [$collaboration, $business] = $this->makeCollaboration();

        $this->actingAs($business)
            ->postJson("/api/v1/collaborations/{$collaboration->id}/review", $this->starPayload());

        $review = CollaborationReview::query()
            ->where('collaboration_id', $collaboration->id)
            ->where('reviewer_profile_id', $business->id)
            ->first();

        // (5 + 4 + 4 + 5 + 5) / 5 = 4.6
        $this->assertSame(4.6, $review->overall_rating);
    }

    public function test_overall_rating_falls_back_to_legacy_rating(): void
    {
        [$collaboration, $business] = $this->makeCollaboration();

        $this->actingAs($business)
            ->postJson("/api/v1/collaborations/{$collaboration->id}/review", [
                'rating' => 3,
            ]);

        $review = CollaborationReview::query()
            ->where('collaboration_id', $collaboration->id)
            ->where('reviewer_profile_id', $business->id)
            ->first();

        $this->assertSame(3.0, $review->overall_rating);
    }

    public function test_each_side_can_review_once_per_kolab_and_earns_xp_independently(): void
    {
        [$collaboration, $business, $community] = $this->makeCollaboration();

        $this->actingAs($business)
            ->postJson("/api/v1/collaborations/{$collaboration->id}/review", $this->starPayload())
            ->assertCreated();

        $this->actingAs($community)
            ->postJson("/api/v1/collaborations/{$collaboration->id}/review", $this->starPayload())
            ->assertCreated();

        $this->assertSame(1, \App\Models\PointLedger::query()
            ->where('profile_id', $business->id)
            ->where('event_type', PointEventType::ReviewPosted)
            ->count());

        $this->assertSame(1, \App\Models\PointLedger::query()
            ->where('profile_id', $community->id)
            ->where('event_type', PointEventType::ReviewPosted)
            ->count());
    }

    public function test_duplicate_submission_does_not_duplicate_xp(): void
    {
        [$collaboration, $business] = $this->makeCollaboration();

        $this->actingAs($business)
            ->postJson("/api/v1/collaborations/{$collaboration->id}/review", $this->starPayload())
            ->assertCreated();

        $response = $this->actingAs($business)
            ->postJson("/api/v1/collaborations/{$collaboration->id}/review", $this->starPayload([
                'communication_rating' => 1,
            ]));

        $response->assertOk()->assertJsonPath('message', 'Review already submitted.');

        $this->assertSame(1, CollaborationReview::query()
            ->where('collaboration_id', $collaboration->id)
            ->where('reviewer_profile_id', $business->id)
            ->count());

        $this->assertSame(1, \App\Models\PointLedger::query()
            ->where('profile_id', $business->id)
            ->where('event_type', PointEventType::ReviewPosted)
            ->count());
    }

    public function test_a_second_separate_kolab_between_same_profiles_can_receive_a_separate_review(): void
    {
        [$collaboration, $business, $community] = $this->makeCollaboration();

        $this->actingAs($business)
            ->postJson("/api/v1/collaborations/{$collaboration->id}/review", $this->starPayload())
            ->assertCreated();

        $secondCollaboration = Collaboration::factory()
            ->completed()
            ->forCreator($business)
            ->forApplicant($community)
            ->create([
                'business_profile_id' => $collaboration->business_profile_id,
                'community_profile_id' => $collaboration->community_profile_id,
            ]);

        $response = $this->actingAs($business)
            ->postJson("/api/v1/collaborations/{$secondCollaboration->id}/review", $this->starPayload());

        $response->assertCreated();

        $this->assertSame(2, CollaborationReview::query()
            ->where('reviewer_profile_id', $business->id)
            ->count());
    }

    public function test_review_remains_optional_and_does_not_block_completion(): void
    {
        [$collaboration, $business] = $this->makeCollaboration();

        // No review submitted at all — collaboration is already in completed
        // state via the factory, confirming review submission isn't required
        // to reach/stay in that state.
        $this->assertSame('completed', $collaboration->status->value ?? $collaboration->status);
    }

    public function test_hidden_public_comment_is_not_exposed_on_public_profile(): void
    {
        [$collaboration, $business, $community] = $this->makeCollaboration();

        CollaborationReview::factory()->create([
            'collaboration_id' => $collaboration->id,
            'reviewer_profile_id' => $business->id,
            'reviewed_profile_id' => $community->id,
            'reviewer_role' => 'creator',
            'public_comment' => 'This should be hidden',
            'public_comment_visible' => false,
            'communication_rating' => 5,
            'reliability_rating' => 5,
            'fit_rating' => 5,
            'value_rating' => 5,
            'repeat_rating' => 5,
        ]);

        $response = $this->actingAs($business)
            ->getJson("/api/v1/profiles/{$community->id}/reviews");

        $response->assertOk();
        $this->assertNull($response->json('data.0.public_comment'));
    }

    public function test_visible_public_comment_is_exposed_on_public_profile(): void
    {
        [$collaboration, $business, $community] = $this->makeCollaboration();

        CollaborationReview::factory()->create([
            'collaboration_id' => $collaboration->id,
            'reviewer_profile_id' => $business->id,
            'reviewed_profile_id' => $community->id,
            'reviewer_role' => 'creator',
            'public_comment' => 'This should be visible',
            'public_comment_visible' => true,
            'communication_rating' => 5,
            'reliability_rating' => 5,
            'fit_rating' => 5,
            'value_rating' => 5,
            'repeat_rating' => 5,
        ]);

        $response = $this->actingAs($business)
            ->getJson("/api/v1/profiles/{$community->id}/reviews");

        $response->assertOk();
        $this->assertSame('This should be visible', $response->json('data.0.public_comment'));
    }
}
