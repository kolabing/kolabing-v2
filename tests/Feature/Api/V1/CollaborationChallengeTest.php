<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\ChallengeDifficulty;
use App\Models\BusinessProfile;
use App\Models\Challenge;
use App\Models\Collaboration;
use App\Models\CommunityProfile;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CollaborationChallengeTest extends TestCase
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

    private function createCollaborationForProfiles(Profile $creator, Profile $applicant): Collaboration
    {
        return Collaboration::factory()
            ->forCreator($creator)
            ->forApplicant($applicant)
            ->scheduled()
            ->create();
    }

    /*
    |--------------------------------------------------------------------------
    | Sync Challenges (PUT /api/v1/collaborations/{collaboration}/challenges)
    |--------------------------------------------------------------------------
    */

    public function test_participant_can_sync_challenges(): void
    {
        $creator = $this->createBusinessProfile();
        $applicant = $this->createCommunityProfile();
        $collaboration = $this->createCollaborationForProfiles($creator, $applicant);

        $challenges = Challenge::factory()->system()->count(3)->create();

        $response = $this->actingAs($creator)
            ->putJson("/api/v1/collaborations/{$collaboration->id}/challenges", [
                'selected_challenge_ids' => $challenges->pluck('id')->toArray(),
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Challenges updated successfully.')
            ->assertJsonCount(3, 'data.selected_challenge_ids');

        $this->assertDatabaseCount('collaboration_challenges', 3);
    }

    public function test_applicant_can_sync_challenges(): void
    {
        $creator = $this->createBusinessProfile();
        $applicant = $this->createCommunityProfile();
        $collaboration = $this->createCollaborationForProfiles($creator, $applicant);

        $challenges = Challenge::factory()->system()->count(2)->create();

        $response = $this->actingAs($applicant)
            ->putJson("/api/v1/collaborations/{$collaboration->id}/challenges", [
                'selected_challenge_ids' => $challenges->pluck('id')->toArray(),
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_sync_replaces_previous_challenges(): void
    {
        $creator = $this->createBusinessProfile();
        $applicant = $this->createCommunityProfile();
        $collaboration = $this->createCollaborationForProfiles($creator, $applicant);

        $oldChallenges = Challenge::factory()->system()->count(2)->create();
        $collaboration->challenges()->sync($oldChallenges->pluck('id')->toArray());

        $newChallenges = Challenge::factory()->system()->count(3)->create();

        $response = $this->actingAs($creator)
            ->putJson("/api/v1/collaborations/{$collaboration->id}/challenges", [
                'selected_challenge_ids' => $newChallenges->pluck('id')->toArray(),
            ]);

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data.selected_challenge_ids');

        // Old challenges should be removed
        foreach ($oldChallenges as $challenge) {
            $this->assertDatabaseMissing('collaboration_challenges', [
                'collaboration_id' => $collaboration->id,
                'challenge_id' => $challenge->id,
            ]);
        }

        // New challenges should be present
        foreach ($newChallenges as $challenge) {
            $this->assertDatabaseHas('collaboration_challenges', [
                'collaboration_id' => $collaboration->id,
                'challenge_id' => $challenge->id,
            ]);
        }
    }

    public function test_non_participant_cannot_sync_challenges(): void
    {
        $creator = $this->createBusinessProfile();
        $applicant = $this->createCommunityProfile();
        $outsider = $this->createBusinessProfile();
        $collaboration = $this->createCollaborationForProfiles($creator, $applicant);

        $challenges = Challenge::factory()->system()->count(2)->create();

        $response = $this->actingAs($outsider)
            ->putJson("/api/v1/collaborations/{$collaboration->id}/challenges", [
                'selected_challenge_ids' => $challenges->pluck('id')->toArray(),
            ]);

        $response->assertStatus(403);
    }

    public function test_sync_challenges_requires_at_least_one(): void
    {
        $creator = $this->createBusinessProfile();
        $applicant = $this->createCommunityProfile();
        $collaboration = $this->createCollaborationForProfiles($creator, $applicant);

        $response = $this->actingAs($creator)
            ->putJson("/api/v1/collaborations/{$collaboration->id}/challenges", [
                'selected_challenge_ids' => [],
            ]);

        $response->assertStatus(422);
    }

    public function test_sync_challenges_validates_uuid_existence(): void
    {
        $creator = $this->createBusinessProfile();
        $applicant = $this->createCommunityProfile();
        $collaboration = $this->createCollaborationForProfiles($creator, $applicant);

        $response = $this->actingAs($creator)
            ->putJson("/api/v1/collaborations/{$collaboration->id}/challenges", [
                'selected_challenge_ids' => ['00000000-0000-0000-0000-000000000000'],
            ]);

        $response->assertStatus(422);
    }

    public function test_sync_challenges_requires_authentication(): void
    {
        $collaboration = Collaboration::factory()->create();

        $this->putJson("/api/v1/collaborations/{$collaboration->id}/challenges", [
            'selected_challenge_ids' => [],
        ])->assertStatus(401);
    }

    /*
    |--------------------------------------------------------------------------
    | Create Custom Challenge (POST /api/v1/collaborations/{collaboration}/challenges)
    |--------------------------------------------------------------------------
    */

    public function test_participant_can_create_custom_challenge(): void
    {
        $creator = $this->createBusinessProfile();
        $applicant = $this->createCommunityProfile();
        $collaboration = $this->createCollaborationForProfiles($creator, $applicant);

        $response = $this->actingAs($creator)
            ->postJson("/api/v1/collaborations/{$collaboration->id}/challenges", [
                'name' => 'Custom Collab Challenge',
                'description' => 'A fun challenge for our collaboration',
                'difficulty' => 'medium',
                'points' => 20,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Custom Collab Challenge')
            ->assertJsonPath('data.description', 'A fun challenge for our collaboration')
            ->assertJsonPath('data.difficulty', 'medium')
            ->assertJsonPath('data.points', 20)
            ->assertJsonPath('data.is_system', false);

        $this->assertDatabaseHas('challenges', [
            'name' => 'Custom Collab Challenge',
            'is_system' => false,
            'points' => 20,
        ]);

        // Should be auto-attached to the collaboration
        $this->assertDatabaseHas('collaboration_challenges', [
            'collaboration_id' => $collaboration->id,
        ]);
    }

    public function test_custom_challenge_defaults_points_from_difficulty(): void
    {
        $creator = $this->createBusinessProfile();
        $applicant = $this->createCommunityProfile();
        $collaboration = $this->createCollaborationForProfiles($creator, $applicant);

        $response = $this->actingAs($creator)
            ->postJson("/api/v1/collaborations/{$collaboration->id}/challenges", [
                'name' => 'Easy Default Points Challenge',
                'difficulty' => 'easy',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.points', ChallengeDifficulty::Easy->points());

        $response = $this->actingAs($creator)
            ->postJson("/api/v1/collaborations/{$collaboration->id}/challenges", [
                'name' => 'Hard Default Points Challenge',
                'difficulty' => 'hard',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.points', ChallengeDifficulty::Hard->points());
    }

    public function test_non_participant_cannot_create_custom_challenge(): void
    {
        $creator = $this->createBusinessProfile();
        $applicant = $this->createCommunityProfile();
        $outsider = $this->createCommunityProfile();
        $collaboration = $this->createCollaborationForProfiles($creator, $applicant);

        $response = $this->actingAs($outsider)
            ->postJson("/api/v1/collaborations/{$collaboration->id}/challenges", [
                'name' => 'Unauthorized Challenge',
                'difficulty' => 'easy',
            ]);

        $response->assertStatus(403);
    }

    public function test_create_custom_challenge_validation_errors(): void
    {
        $creator = $this->createBusinessProfile();
        $applicant = $this->createCommunityProfile();
        $collaboration = $this->createCollaborationForProfiles($creator, $applicant);

        $response = $this->actingAs($creator)
            ->postJson("/api/v1/collaborations/{$collaboration->id}/challenges", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'difficulty']);
    }

    public function test_create_custom_challenge_name_too_short(): void
    {
        $creator = $this->createBusinessProfile();
        $applicant = $this->createCommunityProfile();
        $collaboration = $this->createCollaborationForProfiles($creator, $applicant);

        $response = $this->actingAs($creator)
            ->postJson("/api/v1/collaborations/{$collaboration->id}/challenges", [
                'name' => 'ab',
                'difficulty' => 'easy',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_create_custom_challenge_invalid_difficulty(): void
    {
        $creator = $this->createBusinessProfile();
        $applicant = $this->createCommunityProfile();
        $collaboration = $this->createCollaborationForProfiles($creator, $applicant);

        $response = $this->actingAs($creator)
            ->postJson("/api/v1/collaborations/{$collaboration->id}/challenges", [
                'name' => 'Valid Challenge Name',
                'difficulty' => 'impossible',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['difficulty']);
    }

    public function test_create_custom_challenge_requires_authentication(): void
    {
        $collaboration = Collaboration::factory()->create();

        $this->postJson("/api/v1/collaborations/{$collaboration->id}/challenges", [
            'name' => 'Test Challenge',
            'difficulty' => 'easy',
        ])->assertStatus(401);
    }
}
