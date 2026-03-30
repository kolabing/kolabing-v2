<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\BusinessProfile;
use App\Models\Collaboration;
use App\Models\CommunityProfile;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class GamificationCollaborationIntegrationTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function createBusinessProfile(): Profile
    {
        $profile = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $profile->id]);

        return $profile;
    }

    private function createCommunityProfile(): Profile
    {
        $profile = Profile::factory()->community()->create();
        CommunityProfile::factory()->create(['profile_id' => $profile->id]);

        return $profile;
    }

    public function test_completing_collaboration_awards_points_to_both_parties(): void
    {
        $creator = $this->createBusinessProfile();
        $applicant = $this->createCommunityProfile();

        $collaboration = Collaboration::factory()
            ->forCreator($creator)
            ->forApplicant($applicant)
            ->active()
            ->create();

        $response = $this->actingAs($creator)
            ->postJson("/api/v1/collaborations/{$collaboration->id}/complete");

        $response->assertOk()
            ->assertJsonPath('success', true);

        // Both parties should have received 1 point
        $this->assertDatabaseHas('wallets', [
            'profile_id' => $creator->id,
            'points' => 1,
        ]);

        $this->assertDatabaseHas('wallets', [
            'profile_id' => $applicant->id,
            'points' => 1,
        ]);

        // Ledger entries for both parties
        $this->assertDatabaseHas('point_ledger', [
            'profile_id' => $creator->id,
            'event_type' => 'collaboration_complete',
            'reference_id' => $collaboration->id,
        ]);

        $this->assertDatabaseHas('point_ledger', [
            'profile_id' => $applicant->id,
            'event_type' => 'collaboration_complete',
            'reference_id' => $collaboration->id,
        ]);
    }

    public function test_completing_collaboration_evaluates_badges(): void
    {
        $creator = $this->createBusinessProfile();
        $applicant = $this->createCommunityProfile();

        $collaboration = Collaboration::factory()
            ->forCreator($creator)
            ->forApplicant($applicant)
            ->active()
            ->create();

        $this->actingAs($creator)
            ->postJson("/api/v1/collaborations/{$collaboration->id}/complete");

        // Both should earn first_kolab badge
        $this->assertDatabaseHas('earned_badges', [
            'profile_id' => $creator->id,
            'badge_slug' => 'first_kolab',
        ]);

        $this->assertDatabaseHas('earned_badges', [
            'profile_id' => $applicant->id,
            'badge_slug' => 'first_kolab',
        ]);
    }

    public function test_cancelling_collaboration_does_not_award_points(): void
    {
        $creator = $this->createBusinessProfile();
        $applicant = $this->createCommunityProfile();

        $collaboration = Collaboration::factory()
            ->forCreator($creator)
            ->forApplicant($applicant)
            ->active()
            ->create();

        $this->actingAs($creator)
            ->postJson("/api/v1/collaborations/{$collaboration->id}/cancel", [
                'reason' => 'Schedule conflict',
            ]);

        $this->assertDatabaseMissing('wallets', ['profile_id' => $creator->id]);
        $this->assertDatabaseMissing('wallets', ['profile_id' => $applicant->id]);
    }
}
