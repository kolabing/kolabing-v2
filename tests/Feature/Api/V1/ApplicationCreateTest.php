<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\BusinessProfile;
use App\Models\CollabOpportunity;
use App\Models\CommunityProfile;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ApplicationCreateTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_short_message_is_accepted(): void
    {
        [$applicant, $opportunity] = $this->seedApplyContext();

        $response = $this->actingAs($applicant)
            ->postJson("/api/v1/opportunities/{$opportunity->id}/applications", [
                'message' => 'sounds cool', // 11 chars — would have failed the old min:20 rule
                'availability' => 'Available on weekends and evenings throughout the month.',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('applications', [
            'collab_opportunity_id' => $opportunity->id,
            'applicant_profile_id' => $applicant->id,
            'message' => 'sounds cool',
        ]);
    }

    public function test_empty_message_is_still_rejected(): void
    {
        [$applicant, $opportunity] = $this->seedApplyContext();

        $response = $this->actingAs($applicant)
            ->postJson("/api/v1/opportunities/{$opportunity->id}/applications", [
                'message' => '',
                'availability' => 'Available on weekends and evenings throughout the month.',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure(['errors' => ['message']]);
    }

    public function test_message_over_max_length_is_rejected(): void
    {
        [$applicant, $opportunity] = $this->seedApplyContext();

        $response = $this->actingAs($applicant)
            ->postJson("/api/v1/opportunities/{$opportunity->id}/applications", [
                'message' => str_repeat('a', 2001),
                'availability' => 'Available on weekends and evenings throughout the month.',
            ]);

        $response->assertStatus(422)
            ->assertJsonStructure(['errors' => ['message']]);
    }

    /**
     * @return array{0: Profile, 1: CollabOpportunity}
     */
    private function seedApplyContext(): array
    {
        $businessCreator = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $businessCreator->id]);

        $applicant = Profile::factory()->community()->create(['phone_number' => '+34600000001']);
        CommunityProfile::factory()->create([
            'profile_id' => $applicant->id,
            'instagram' => 'testuser',
        ]);

        $opportunity = CollabOpportunity::factory()
            ->published()
            ->forCreator($businessCreator)
            ->create();

        return [$applicant, $opportunity];
    }
}
