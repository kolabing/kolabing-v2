<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\BusinessProfile;
use App\Models\Collaboration;
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
}
