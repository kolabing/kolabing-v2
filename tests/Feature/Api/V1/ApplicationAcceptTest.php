<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Application;
use App\Models\BusinessProfile;
use App\Models\BusinessSubscription;
use App\Models\CollabOpportunity;
use App\Models\Collaboration;
use App\Models\CommunityProfile;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ApplicationAcceptTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_accept_endpoint_is_idempotent_and_returns_current_state(): void
    {
        $business = Profile::factory()->business()->create();
        BusinessProfile::factory()->create([
            'profile_id' => $business->id,
            'name' => 'Business Creator',
        ]);
        BusinessSubscription::factory()->active()->create([
            'profile_id' => $business->id,
        ]);

        $community = Profile::factory()->community()->create();
        CommunityProfile::factory()->create([
            'profile_id' => $community->id,
            'name' => 'Community Applicant',
        ]);

        $opportunity = CollabOpportunity::factory()
            ->published()
            ->forCreator($business)
            ->create();

        $application = Application::factory()
            ->pending()
            ->forOpportunity($opportunity)
            ->forApplicant($community)
            ->create();

        $payload = [
            'scheduled_date' => now()->addDays(7)->toDateString(),
            'contact_methods' => [
                'email' => 'hello@example.com',
            ],
        ];

        $firstResponse = $this->actingAs($business)
            ->postJson("/api/v1/applications/{$application->id}/accept", $payload);

        $firstResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.application.id', $application->id)
            ->assertJsonPath('data.application.status', 'accepted')
            ->assertJsonPath('data.collaboration.status', 'scheduled');

        $collaborationId = $firstResponse->json('data.collaboration.id');

        $secondResponse = $this->actingAs($business)
            ->postJson("/api/v1/applications/{$application->id}/accept", $payload);

        $secondResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.application.id', $application->id)
            ->assertJsonPath('data.application.status', 'accepted')
            ->assertJsonPath('data.collaboration.id', $collaborationId)
            ->assertJsonPath('data.collaboration.status', 'scheduled');

        $this->assertDatabaseCount('collaborations', 1);

        $application->refresh();
        $this->assertTrue($application->isAccepted());

        $collaboration = Collaboration::query()->firstOrFail();
        $this->assertEquals($application->id, $collaboration->application_id);
    }
}
