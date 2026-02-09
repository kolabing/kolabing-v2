<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\BusinessProfile;
use App\Models\Collaboration;
use App\Models\CommunityProfile;
use App\Models\Event;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CollaborationQrCodeTest extends TestCase
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
    | Generate QR Code (POST /api/v1/collaborations/{collaboration}/qr-code)
    |--------------------------------------------------------------------------
    */

    public function test_creator_can_generate_qr_code(): void
    {
        $creator = $this->createBusinessProfile();
        $applicant = $this->createCommunityProfile();
        $collaboration = $this->createCollaborationForProfiles($creator, $applicant);

        $response = $this->actingAs($creator)
            ->postJson("/api/v1/collaborations/{$collaboration->id}/qr-code");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'event_id',
                    'qr_code_url',
                ],
            ]);

        $data = $response->json('data');
        $this->assertNotNull($data['event_id']);
        $this->assertNotNull($data['qr_code_url']);
        $this->assertStringContains('/api/v1/events/', $data['qr_code_url']);
        $this->assertStringContains('token=', $data['qr_code_url']);

        // Collaboration should be updated
        $collaboration->refresh();
        $this->assertNotNull($collaboration->event_id);
        $this->assertNotNull($collaboration->qr_code_url);
    }

    public function test_applicant_can_generate_qr_code(): void
    {
        $creator = $this->createBusinessProfile();
        $applicant = $this->createCommunityProfile();
        $collaboration = $this->createCollaborationForProfiles($creator, $applicant);

        $response = $this->actingAs($applicant)
            ->postJson("/api/v1/collaborations/{$collaboration->id}/qr-code");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_qr_code_creates_event_if_none_exists(): void
    {
        $creator = $this->createBusinessProfile();
        $applicant = $this->createCommunityProfile();
        $collaboration = $this->createCollaborationForProfiles($creator, $applicant);

        $this->assertNull($collaboration->event_id);

        $response = $this->actingAs($creator)
            ->postJson("/api/v1/collaborations/{$collaboration->id}/qr-code");

        $response->assertStatus(200);

        $collaboration->refresh();
        $this->assertNotNull($collaboration->event_id);

        $this->assertDatabaseHas('events', [
            'id' => $collaboration->event_id,
            'profile_id' => $creator->id,
            'is_active' => true,
        ]);
    }

    public function test_qr_code_reuses_existing_event(): void
    {
        $creator = $this->createBusinessProfile();
        $applicant = $this->createCommunityProfile();
        $event = Event::factory()->forProfile($creator)->create();

        $collaboration = Collaboration::factory()
            ->forCreator($creator)
            ->forApplicant($applicant)
            ->scheduled()
            ->create(['event_id' => $event->id]);

        $response = $this->actingAs($creator)
            ->postJson("/api/v1/collaborations/{$collaboration->id}/qr-code");

        $response->assertStatus(200)
            ->assertJsonPath('data.event_id', $event->id);

        // Should not create a new event
        $this->assertDatabaseCount('events', 1);
    }

    public function test_non_participant_cannot_generate_qr_code(): void
    {
        $creator = $this->createBusinessProfile();
        $applicant = $this->createCommunityProfile();
        $outsider = $this->createBusinessProfile();
        $collaboration = $this->createCollaborationForProfiles($creator, $applicant);

        $response = $this->actingAs($outsider)
            ->postJson("/api/v1/collaborations/{$collaboration->id}/qr-code");

        $response->assertStatus(403);
    }

    public function test_generate_qr_code_requires_authentication(): void
    {
        $collaboration = Collaboration::factory()->create();

        $this->postJson("/api/v1/collaborations/{$collaboration->id}/qr-code")
            ->assertStatus(401);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function assertStringContains(string $needle, string $haystack): void
    {
        $this->assertTrue(
            str_contains($haystack, $needle),
            "Failed asserting that '{$haystack}' contains '{$needle}'."
        );
    }
}
