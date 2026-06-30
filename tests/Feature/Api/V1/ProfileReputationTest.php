<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\BusinessProfile;
use App\Models\Collaboration;
use App\Models\CollaborationReview;
use App\Models\CommunityProfile;
use App\Models\Profile;
use App\Services\ProfileService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ProfileReputationTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function makeReviewedCommunity(): Profile
    {
        $community = Profile::factory()->community()->create();
        CommunityProfile::factory()->create([
            'profile_id' => $community->id,
            'name' => 'Reviewed Community',
        ]);

        return $community;
    }

    private function makeBusinessReviewer(): Profile
    {
        $business = Profile::factory()->business()->create();
        BusinessProfile::factory()->create([
            'profile_id' => $business->id,
            'name' => 'Reviewer Business',
        ]);

        return $business;
    }

    private function reviewedCollaboration(Profile $reviewer, Profile $reviewed, array $reviewOverrides = []): CollaborationReview
    {
        $collaboration = Collaboration::factory()
            ->completed()
            ->forCreator($reviewer)
            ->forApplicant($reviewed)
            ->create();

        $defaults = [
            'communication_rating' => 5,
            'reliability_rating' => 5,
            'fit_rating' => 5,
            'value_rating' => 5,
            'repeat_rating' => 5,
        ];
        $merged = array_merge($defaults, $reviewOverrides);

        $starFields = ['communication_rating', 'reliability_rating', 'fit_rating', 'value_rating', 'repeat_rating'];
        $hasAllStars = collect($starFields)->every(fn ($field) => array_key_exists($field, $merged) && $merged[$field] !== null);

        // Mirrors CollaborationController::review(), which always backfills the
        // legacy `rating` column from the star ratings when they are present —
        // a star-rated review can never have `rating === null` in production.
        $legacyRating = $hasAllStars
            ? (int) round(array_sum(array_intersect_key($merged, array_flip($starFields))) / 5)
            : ($merged['rating'] ?? null);

        return CollaborationReview::factory()->create(array_merge([
            'collaboration_id' => $collaboration->id,
            'reviewer_profile_id' => $reviewer->id,
            'reviewed_profile_id' => $reviewed->id,
            'reviewer_role' => 'creator',
            'rating' => $legacyRating,
            'communication_rating' => 5,
            'reliability_rating' => 5,
            'fit_rating' => 5,
            'value_rating' => 5,
            'repeat_rating' => 5,
        ], $reviewOverrides));
    }

    public function test_profile_with_no_reviews_returns_safe_empty_reputation(): void
    {
        $community = $this->makeReviewedCommunity();

        $summary = app(ProfileService::class)->getReputationSummary($community);

        $this->assertNull($summary['average_rating']);
        $this->assertSame(0, $summary['review_count']);
        $this->assertSame(0, $summary['unique_partner_count']);
        $this->assertNull($summary['breakdown']);
    }

    public function test_reputation_summary_counts_reviews_received_by_profile(): void
    {
        $community = $this->makeReviewedCommunity();
        $reviewerA = $this->makeBusinessReviewer();
        $reviewerB = $this->makeBusinessReviewer();

        $this->reviewedCollaboration($reviewerA, $community);
        $this->reviewedCollaboration($reviewerB, $community);

        $summary = app(ProfileService::class)->getReputationSummary($community);

        $this->assertSame(2, $summary['review_count']);
        $this->assertSame(2, $summary['unique_partner_count']);
    }

    public function test_average_rating_uses_overall_rating(): void
    {
        $community = $this->makeReviewedCommunity();
        $reviewer = $this->makeBusinessReviewer();

        $this->reviewedCollaboration($reviewer, $community, [
            'communication_rating' => 4,
            'reliability_rating' => 4,
            'fit_rating' => 4,
            'value_rating' => 4,
            'repeat_rating' => 4,
        ]);

        $summary = app(ProfileService::class)->getReputationSummary($community);

        $this->assertSame(4.0, $summary['average_rating']);
    }

    public function test_repeated_kolabs_from_same_partner_increase_review_count_not_partner_count(): void
    {
        $community = $this->makeReviewedCommunity();
        $reviewer = $this->makeBusinessReviewer();

        $this->reviewedCollaboration($reviewer, $community);
        $this->reviewedCollaboration($reviewer, $community);

        $summary = app(ProfileService::class)->getReputationSummary($community);

        $this->assertSame(2, $summary['review_count']);
        $this->assertSame(1, $summary['unique_partner_count']);
    }

    public function test_hidden_comment_rating_still_counts_in_summary(): void
    {
        $community = $this->makeReviewedCommunity();
        $reviewer = $this->makeBusinessReviewer();

        $this->reviewedCollaboration($reviewer, $community, [
            'public_comment' => 'Hidden text',
            'public_comment_visible' => false,
        ]);

        $summary = app(ProfileService::class)->getReputationSummary($community);

        $this->assertSame(1, $summary['review_count']);
        $this->assertSame(5.0, $summary['average_rating']);
    }

    public function test_reviews_on_non_completed_collaboration_excluded_from_summary(): void
    {
        $community = $this->makeReviewedCommunity();
        $reviewer = $this->makeBusinessReviewer();

        $collaboration = Collaboration::factory()
            ->active()
            ->forCreator($reviewer)
            ->forApplicant($community)
            ->create();

        CollaborationReview::factory()->create([
            'collaboration_id' => $collaboration->id,
            'reviewer_profile_id' => $reviewer->id,
            'reviewed_profile_id' => $community->id,
            'reviewer_role' => 'creator',
            'rating' => null,
            'communication_rating' => 5,
            'reliability_rating' => 5,
            'fit_rating' => 5,
            'value_rating' => 5,
            'repeat_rating' => 5,
        ]);

        $summary = app(ProfileService::class)->getReputationSummary($community);

        $this->assertSame(0, $summary['review_count']);
        $this->assertNull($summary['average_rating']);
    }

    public function test_legacy_review_without_star_fields_contributes_via_fallback(): void
    {
        $community = $this->makeReviewedCommunity();
        $reviewer = $this->makeBusinessReviewer();

        $this->reviewedCollaboration($reviewer, $community, [
            'rating' => 3,
            'communication_rating' => null,
            'reliability_rating' => null,
            'fit_rating' => null,
            'value_rating' => null,
            'repeat_rating' => null,
        ]);

        $summary = app(ProfileService::class)->getReputationSummary($community);

        $this->assertSame(1, $summary['review_count']);
        $this->assertSame(3.0, $summary['average_rating']);
        $this->assertNull($summary['breakdown']);
    }

    public function test_breakdown_averages_star_fields(): void
    {
        $community = $this->makeReviewedCommunity();
        $reviewer = $this->makeBusinessReviewer();

        $this->reviewedCollaboration($reviewer, $community, [
            'communication_rating' => 5,
            'reliability_rating' => 4,
            'fit_rating' => 3,
            'value_rating' => 2,
            'repeat_rating' => 1,
        ]);

        $summary = app(ProfileService::class)->getReputationSummary($community);

        $this->assertSame(5.0, $summary['breakdown']['communication']);
        $this->assertSame(4.0, $summary['breakdown']['reliability']);
        $this->assertSame(3.0, $summary['breakdown']['fit']);
        $this->assertSame(2.0, $summary['breakdown']['value']);
        $this->assertSame(1.0, $summary['breakdown']['repeat']);
    }

    public function test_public_profile_endpoint_exposes_reputation_summary(): void
    {
        $community = $this->makeReviewedCommunity();
        $reviewer = $this->makeBusinessReviewer();

        $this->reviewedCollaboration($reviewer, $community);

        $response = $this->actingAs($reviewer)
            ->getJson("/api/v1/profiles/{$community->id}");

        $response->assertOk()
            ->assertJsonPath('data.reputation.review_count', 1)
            ->assertJsonPath('data.reputation.unique_partner_count', 1)
            ->assertJsonPath('data.reputation.average_rating', 5);
    }

    public function test_public_profile_endpoint_reputation_is_null_safe_with_no_reviews(): void
    {
        $community = $this->makeReviewedCommunity();
        $reviewer = $this->makeBusinessReviewer();

        $response = $this->actingAs($reviewer)
            ->getJson("/api/v1/profiles/{$community->id}");

        $response->assertOk()
            ->assertJsonPath('data.reputation.review_count', 0)
            ->assertJsonPath('data.reputation.average_rating', null)
            ->assertJsonPath('data.reputation.breakdown', null);
    }
}
