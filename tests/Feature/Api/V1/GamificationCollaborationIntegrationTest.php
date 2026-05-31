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

    public function test_submitting_feedback_awards_points_to_each_party(): void
    {
        // Post-2026-06-01: XP is awarded when each party POSTs /feedback, not
        // on /complete. See docs/plans 2026-06-01 feedback gate (Q7).
        $creator = $this->createBusinessProfile();
        $applicant = $this->createCommunityProfile();

        $collaboration = Collaboration::factory()
            ->forCreator($creator)
            ->forApplicant($applicant)
            ->active()
            ->create();

        $this->actingAs($creator)
            ->postJson("/api/v1/collaborations/{$collaboration->id}/feedback", [
                'rating' => 5, 'expectation_match' => true, 'would_recommend' => true,
            ])->assertCreated();

        $this->actingAs($applicant)
            ->postJson("/api/v1/collaborations/{$collaboration->id}/feedback", [
                'rating' => 5, 'expectation_match' => true, 'would_recommend' => true,
            ])->assertCreated();

        // Both parties earn 10 XP for participation + 50 XP first-kolab bonus.
        $this->assertDatabaseHas('wallets', ['profile_id' => $creator->id, 'points' => 60]);
        $this->assertDatabaseHas('wallets', ['profile_id' => $applicant->id, 'points' => 60]);

        $this->assertDatabaseHas('point_ledger', [
            'profile_id' => $creator->id,
            'points' => 10,
            'event_type' => 'collaboration_complete',
            'reference_id' => $collaboration->id,
        ]);
        $this->assertDatabaseHas('point_ledger', [
            'profile_id' => $applicant->id,
            'points' => 10,
            'event_type' => 'collaboration_complete',
            'reference_id' => $collaboration->id,
        ]);

        // /complete itself awards no XP — pure metadata flip.
        $this->actingAs($creator)
            ->postJson("/api/v1/collaborations/{$collaboration->id}/complete")
            ->assertOk();

        $this->assertDatabaseHas('wallets', ['profile_id' => $creator->id, 'points' => 60]);
        $this->assertDatabaseHas('wallets', ['profile_id' => $applicant->id, 'points' => 60]);
    }

    public function test_completing_twice_returns_422_terminal_state(): void
    {
        $creator = $this->createBusinessProfile();
        $applicant = $this->createCommunityProfile();

        $collaboration = Collaboration::factory()
            ->forCreator($creator)
            ->forApplicant($applicant)
            ->scheduled()
            ->create();

        // Submit feedback from both sides so the gate is satisfied.
        foreach ([$creator, $applicant] as $actor) {
            $this->actingAs($actor)
                ->postJson("/api/v1/collaborations/{$collaboration->id}/feedback", [
                    'rating' => 4, 'expectation_match' => true, 'would_recommend' => true,
                ])->assertCreated();
        }

        $firstResponse = $this->actingAs($creator)
            ->postJson("/api/v1/collaborations/{$collaboration->id}/complete");

        $firstResponse->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'completed');

        $secondResponse = $this->actingAs($creator)
            ->postJson("/api/v1/collaborations/{$collaboration->id}/complete");

        $secondResponse->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'invalid_status_transition')
            ->assertJsonPath('errors.status', 'completed');
    }

    public function test_submitting_feedback_evaluates_badges(): void
    {
        // Post-2026-06-01: badge evaluation runs inside awardPoints, which now
        // fires on /feedback rather than /complete. See plan doc Q7.
        $creator = $this->createBusinessProfile();
        $applicant = $this->createCommunityProfile();

        $collaboration = Collaboration::factory()
            ->forCreator($creator)
            ->forApplicant($applicant)
            ->active()
            ->create();

        foreach ([$creator, $applicant] as $actor) {
            $this->actingAs($actor)
                ->postJson("/api/v1/collaborations/{$collaboration->id}/feedback", [
                    'rating' => 5, 'expectation_match' => true, 'would_recommend' => true,
                ])->assertCreated();
        }

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
