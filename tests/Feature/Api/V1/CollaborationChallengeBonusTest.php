<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\BusinessProfile;
use App\Models\Challenge;
use App\Models\Collaboration;
use App\Models\CommunityProfile;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CollaborationChallengeBonusTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function business(): Profile
    {
        $profile = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $profile->id]);

        return $profile;
    }

    private function community(): Profile
    {
        $profile = Profile::factory()->community()->create();
        CommunityProfile::factory()->create(['profile_id' => $profile->id]);

        return $profile;
    }

    private function attachedCollab(Profile $business, Profile $community): array
    {
        $collab = Collaboration::factory()
            ->forCreator($business)
            ->forApplicant($community)
            ->scheduled()
            ->create();

        $challenge = Challenge::factory()->system()->create();
        $collab->challenges()->attach($challenge->id);

        return [$collab, $challenge];
    }

    public function test_business_can_upsert_a_bonus(): void
    {
        [$collab, $challenge] = $this->attachedCollab($this->business(), $this->community());
        $business = $collab->creatorProfile;

        $this->actingAs($business)
            ->putJson("/api/v1/collaborations/{$collab->id}/challenges/{$challenge->id}/bonus", [
                'bonus_type' => 'discount_percent',
                'bonus_value' => '20',
                'bonus_description' => '20% off entrée',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.bonus_type', 'discount_percent')
            ->assertJsonPath('data.bonus_value', '20');

        $this->assertDatabaseHas('collaboration_challenge_bonuses', [
            'collaboration_id' => $collab->id,
            'challenge_id' => $challenge->id,
            'bonus_type' => 'discount_percent',
            'bonus_value' => '20',
            'set_by_profile_id' => $business->id,
        ]);
    }

    public function test_upsert_replaces_existing_row(): void
    {
        [$collab, $challenge] = $this->attachedCollab($this->business(), $this->community());
        $business = $collab->creatorProfile;

        $this->actingAs($business)
            ->putJson("/api/v1/collaborations/{$collab->id}/challenges/{$challenge->id}/bonus", [
                'bonus_type' => 'free_item',
                'bonus_value' => 'Espresso',
            ])
            ->assertOk();

        $this->actingAs($business)
            ->putJson("/api/v1/collaborations/{$collab->id}/challenges/{$challenge->id}/bonus", [
                'bonus_type' => 'discount_percent',
                'bonus_value' => '15',
            ])
            ->assertOk();

        $this->assertDatabaseCount('collaboration_challenge_bonuses', 1);
        $this->assertDatabaseHas('collaboration_challenge_bonuses', [
            'collaboration_id' => $collab->id,
            'bonus_type' => 'discount_percent',
            'bonus_value' => '15',
        ]);
    }

    public function test_business_can_remove_a_bonus(): void
    {
        [$collab, $challenge] = $this->attachedCollab($this->business(), $this->community());
        $business = $collab->creatorProfile;

        $this->actingAs($business)
            ->putJson("/api/v1/collaborations/{$collab->id}/challenges/{$challenge->id}/bonus", [
                'bonus_type' => 'free_service',
                'bonus_value' => 'Tour of venue',
            ])
            ->assertOk();

        $this->actingAs($business)
            ->deleteJson("/api/v1/collaborations/{$collab->id}/challenges/{$challenge->id}/bonus")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.deleted', true);

        $this->assertDatabaseCount('collaboration_challenge_bonuses', 0);
    }

    public function test_community_user_cannot_set_a_bonus(): void
    {
        [$collab, $challenge] = $this->attachedCollab($this->business(), $this->community());
        $community = $collab->applicantProfile;

        $this->actingAs($community)
            ->putJson("/api/v1/collaborations/{$collab->id}/challenges/{$challenge->id}/bonus", [
                'bonus_type' => 'discount_percent',
                'bonus_value' => '10',
            ])
            ->assertStatus(403);

        $this->assertDatabaseCount('collaboration_challenge_bonuses', 0);
    }

    public function test_non_participant_business_cannot_set_a_bonus(): void
    {
        [$collab, $challenge] = $this->attachedCollab($this->business(), $this->community());
        $outsider = $this->business();

        $this->actingAs($outsider)
            ->putJson("/api/v1/collaborations/{$collab->id}/challenges/{$challenge->id}/bonus", [
                'bonus_type' => 'discount_percent',
                'bonus_value' => '10',
            ])
            ->assertStatus(403);
    }

    public function test_cannot_set_bonus_on_unattached_challenge(): void
    {
        [$collab] = $this->attachedCollab($this->business(), $this->community());
        $business = $collab->creatorProfile;

        $detached = Challenge::factory()->system()->create();

        $this->actingAs($business)
            ->putJson("/api/v1/collaborations/{$collab->id}/challenges/{$detached->id}/bonus", [
                'bonus_type' => 'discount_percent',
                'bonus_value' => '10',
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_discount_percent_must_be_within_range(): void
    {
        [$collab, $challenge] = $this->attachedCollab($this->business(), $this->community());
        $business = $collab->creatorProfile;

        $this->actingAs($business)
            ->putJson("/api/v1/collaborations/{$collab->id}/challenges/{$challenge->id}/bonus", [
                'bonus_type' => 'discount_percent',
                'bonus_value' => '150',
            ])
            ->assertStatus(422);
    }

    public function test_invalid_bonus_type_rejected(): void
    {
        [$collab, $challenge] = $this->attachedCollab($this->business(), $this->community());
        $business = $collab->creatorProfile;

        $this->actingAs($business)
            ->putJson("/api/v1/collaborations/{$collab->id}/challenges/{$challenge->id}/bonus", [
                'bonus_type' => 'goldbar',
                'bonus_value' => '1',
            ])
            ->assertStatus(422);
    }

    public function test_bonuses_appear_on_collab_detail(): void
    {
        [$collab, $challenge] = $this->attachedCollab($this->business(), $this->community());
        $business = $collab->creatorProfile;

        $this->actingAs($business)
            ->putJson("/api/v1/collaborations/{$collab->id}/challenges/{$challenge->id}/bonus", [
                'bonus_type' => 'free_item',
                'bonus_value' => 'Pastry',
            ])
            ->assertOk();

        $this->actingAs($business)
            ->getJson("/api/v1/collaborations/{$collab->id}")
            ->assertOk()
            ->assertJsonPath('data.challenge_bonuses.0.bonus_type', 'free_item')
            ->assertJsonPath('data.challenge_bonuses.0.bonus_value', 'Pastry');
    }
}
