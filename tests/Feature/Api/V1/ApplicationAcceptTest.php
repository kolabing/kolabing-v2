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
use Illuminate\Support\Carbon;
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

        $scheduledDate = $opportunity->availability_start?->copy();
        if ($scheduledDate !== null
            && $opportunity->availability_end !== null
            && $scheduledDate->lt($opportunity->availability_end)) {
            $scheduledDate = $scheduledDate->addDay();
        }
        $scheduledDate = $scheduledDate?->toDateString() ?? now()->addDays(7)->toDateString();

        $payload = [
            'scheduled_date' => $scheduledDate,
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

    public function test_accept_endpoint_allows_missing_contact_methods(): void
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

        $scheduledDate = $opportunity->availability_start?->copy();
        if ($scheduledDate !== null
            && $opportunity->availability_end !== null
            && $scheduledDate->lt($opportunity->availability_end)) {
            $scheduledDate = $scheduledDate->addDay();
        }
        $scheduledDate = $scheduledDate?->toDateString() ?? now()->addDays(7)->toDateString();

        $response = $this->actingAs($business)
            ->postJson("/api/v1/applications/{$application->id}/accept", [
                'scheduled_date' => $scheduledDate,
            ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.collaboration.status', 'scheduled')
            ->assertJsonPath('data.collaboration.contact_methods', null);
    }

    public function test_accept_endpoint_rejects_dates_outside_publisher_availability_window(): void
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

        $start = now()->addDays(7)->toDateString();
        $end = now()->addDays(10)->toDateString();

        $opportunity = CollabOpportunity::factory()
            ->published()
            ->forCreator($business)
            ->create([
                'availability_mode' => 'flexible',
                'availability_start' => $start,
                'availability_end' => $end,
            ]);

        $application = Application::factory()
            ->pending()
            ->forOpportunity($opportunity)
            ->forApplicant($community)
            ->create();

        $response = $this->actingAs($business)
            ->postJson("/api/v1/applications/{$application->id}/accept", [
                'scheduled_date' => now()->addDays(14)->toDateString(),
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['scheduled_date']);
    }

    public function test_accept_endpoint_rejects_dates_outside_publisher_recurring_days(): void
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
            ->recurring()
            ->forCreator($business)
            ->create([
                'recurring_days' => [1, 3],
            ]);

        $application = Application::factory()
            ->pending()
            ->forOpportunity($opportunity)
            ->forApplicant($community)
            ->create();

        $friday = Carbon::parse('next friday')->toDateString();

        $response = $this->actingAs($business)
            ->postJson("/api/v1/applications/{$application->id}/accept", [
                'scheduled_date' => $friday,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['scheduled_date']);
    }
}
