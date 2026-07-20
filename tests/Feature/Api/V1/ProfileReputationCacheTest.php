<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\BusinessSubscription;
use App\Models\Collaboration;
use App\Models\CollaborationReview;
use App\Models\CommunityProfile;
use App\Models\Profile;
use App\Services\ProfileService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProfileReputationCacheTest extends TestCase
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

    private function reviewedCollaboration(Profile $reviewer, Profile $reviewed): CollaborationReview
    {
        $collaboration = Collaboration::factory()
            ->completed()
            ->forCreator($reviewer)
            ->forApplicant($reviewed)
            ->create();

        return CollaborationReview::factory()->create([
            'collaboration_id' => $collaboration->id,
            'reviewer_profile_id' => $reviewer->id,
            'reviewed_profile_id' => $reviewed->id,
            'reviewer_role' => 'creator',
            'rating' => 5,
            'communication_rating' => 5,
            'reliability_rating' => 5,
            'fit_rating' => 5,
            'value_rating' => 5,
            'repeat_rating' => 5,
        ]);
    }

    private function countReputationQueries(int &$counter): void
    {
        DB::listen(function ($query) use (&$counter): void {
            if (str_contains($query->sql, 'collaboration_reviews')
                || (str_contains($query->sql, 'from "collaborations"') && str_contains($query->sql, 'count(*)'))) {
                $counter++;
            }
        });
    }

    public function test_reputation_summary_is_cached_across_consecutive_calls(): void
    {
        $community = $this->makeReviewedCommunity();
        $reviewer = Profile::factory()->business()->create();
        $this->reviewedCollaboration($reviewer, $community);

        $service = app(ProfileService::class);

        // First call warms the cache.
        $service->getReputationSummary($community);

        $queries = 0;
        $this->countReputationQueries($queries);

        $second = $service->getReputationSummary($community);

        $this->assertSame(1, $second['review_count']);
        $this->assertSame(
            0,
            $queries,
            'A second reputation read for the same profile must be served from cache (no queries).'
        );
    }

    public function test_submitting_a_review_invalidates_the_cached_reputation(): void
    {
        $community = $this->makeReviewedCommunity();
        $reviewerA = Profile::factory()->business()->create();
        $reviewerB = Profile::factory()->business()->create();

        $this->reviewedCollaboration($reviewerA, $community);

        $service = app(ProfileService::class);
        $this->assertSame(1, $service->getReputationSummary($community)['review_count']);

        // A new review from a different reviewer must bust the cache.
        $this->reviewedCollaboration($reviewerB, $community);

        $this->assertSame(
            2,
            $service->getReputationSummary($community)['review_count'],
            'Creating a review must invalidate the cached reputation summary.'
        );
    }

    public function test_completing_a_collaboration_invalidates_completed_kolabs_count(): void
    {
        $community = $this->makeReviewedCommunity();
        $partner = Profile::factory()->business()->create();

        $service = app(ProfileService::class);
        $this->assertSame(0, $service->getReputationSummary($community)['completed_kolabs_count']);

        Collaboration::factory()
            ->completed()
            ->forCreator($partner)
            ->forApplicant($community)
            ->create();

        $this->assertSame(
            1,
            $service->getReputationSummary($community)['completed_kolabs_count'],
            'Completing a collaboration must invalidate the cached completed_kolabs_count.'
        );
    }

    public function test_public_profile_endpoint_computes_completed_kolabs_count_once(): void
    {
        $community = $this->makeReviewedCommunity();
        $viewer = Profile::factory()->business()->create();
        BusinessSubscription::factory()->active()->create(['profile_id' => $viewer->id]);

        $completedCountQueries = 0;
        DB::listen(function ($query) use (&$completedCountQueries): void {
            if (str_contains($query->sql, 'from "collaborations"') && str_contains($query->sql, 'count(*)')) {
                $completedCountQueries++;
            }
        });

        $this->actingAs($viewer)
            ->getJson("/api/v1/profiles/{$community->id}")
            ->assertOk();

        $this->assertSame(
            1,
            $completedCountQueries,
            'completed_kolabs_count must be computed once, not duplicated across resource + reputation.'
        );
    }
}
