<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\BusinessProfile;
use App\Models\CommunityProfile;
use App\Models\Kolab;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class OpportunityCompatibilityTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_show_endpoint_resolves_published_kolab_id_without_persisting_compatibility_row(): void
    {
        $viewer = Profile::factory()->community()->create();
        $creator = Profile::factory()->business()->create();
        BusinessProfile::factory()->create([
            'profile_id' => $creator->id,
            'name' => 'Casa Sol',
        ]);

        $kolab = Kolab::factory()
            ->published()
            ->venuePromotion()
            ->forCreator($creator)
            ->create([
                'title' => 'Sunset rooftop collab',
                'description' => 'Host your creator event on our rooftop',
            ]);

        $response = $this->actingAs($viewer)
            ->getJson("/api/v1/opportunities/{$kolab->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $kolab->id)
            ->assertJsonPath('data.title', 'Sunset rooftop collab')
            ->assertJsonPath('data.status', 'published')
            ->assertJsonPath('data.preferred_city', $kolab->preferred_city);

        $this->assertDatabaseMissing('collab_opportunities', [
            'id' => $kolab->id,
        ]);
    }

    public function test_apply_endpoint_accepts_published_kolab_id_and_creates_compatibility_opportunity(): void
    {
        $creator = Profile::factory()->business()->create();
        BusinessProfile::factory()->create([
            'profile_id' => $creator->id,
            'name' => 'Casa Sol',
        ]);

        $applicant = Profile::factory()->community()->create(['phone_number' => '+34600000001']);
        CommunityProfile::factory()->create([
            'profile_id' => $applicant->id,
            'name' => 'Sevilla Run Club',
            'instagram' => 'sevillarunclub',
        ]);

        $kolab = Kolab::factory()
            ->published()
            ->venuePromotion()
            ->forCreator($creator)
            ->create([
                'title' => 'Sunset rooftop collab',
                'description' => 'Host your creator event on our rooftop',
            ]);

        $response = $this->actingAs($applicant)
            ->postJson("/api/v1/opportunities/{$kolab->id}/applications", [
                'message' => 'sounds cool',
                'availability' => 'Available on weekends and evenings throughout the month.',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.collab_opportunity_id', $kolab->id)
            ->assertJsonPath('data.collab_opportunity.id', $kolab->id)
            ->assertJsonPath('data.collab_opportunity.title', 'Sunset rooftop collab');

        $this->assertDatabaseHas('collab_opportunities', [
            'id' => $kolab->id,
            'title' => 'Sunset rooftop collab',
            'creator_profile_id' => $creator->id,
            'status' => 'published',
        ]);

        $this->assertDatabaseHas('applications', [
            'collab_opportunity_id' => $kolab->id,
            'applicant_profile_id' => $applicant->id,
            'message' => 'sounds cool',
        ]);
    }
}
