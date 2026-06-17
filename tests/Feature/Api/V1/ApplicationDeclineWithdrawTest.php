<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Application;
use App\Models\BusinessSubscription;
use App\Models\CollabOpportunity;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ApplicationDeclineWithdrawTest extends TestCase
{
    use LazilyRefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | Decline (POST /api/v1/applications/{id}/decline)
    |--------------------------------------------------------------------------
    */

    public function test_creator_can_decline_pending_application(): void
    {
        $business = Profile::factory()->business()->create();
        BusinessSubscription::factory()->active()->create(['profile_id' => $business->id]);

        $community = Profile::factory()->community()->create();

        $opportunity = CollabOpportunity::factory()->published()->forCreator($business)->create();
        $application = Application::factory()->pending()->forOpportunity($opportunity)->forApplicant($community)->create();

        $this->actingAs($business)
            ->postJson("/api/v1/applications/{$application->id}/decline")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'declined');

        $this->assertTrue($application->fresh()->isDeclined());
    }

    public function test_applicant_cannot_decline_their_own_application(): void
    {
        $business = Profile::factory()->business()->create();
        $community = Profile::factory()->community()->create();

        $opportunity = CollabOpportunity::factory()->published()->forCreator($business)->create();
        $application = Application::factory()->pending()->forOpportunity($opportunity)->forApplicant($community)->create();

        $this->actingAs($community)
            ->postJson("/api/v1/applications/{$application->id}/decline")
            ->assertStatus(403);

        $this->assertTrue($application->fresh()->isPending());
    }

    public function test_third_party_cannot_decline_application(): void
    {
        $business = Profile::factory()->business()->create();
        $community = Profile::factory()->community()->create();
        $outsider = Profile::factory()->business()->create();

        $opportunity = CollabOpportunity::factory()->published()->forCreator($business)->create();
        $application = Application::factory()->pending()->forOpportunity($opportunity)->forApplicant($community)->create();

        $this->actingAs($outsider)
            ->postJson("/api/v1/applications/{$application->id}/decline")
            ->assertStatus(403);
    }

    public function test_cannot_decline_already_accepted_application(): void
    {
        $business = Profile::factory()->business()->create();
        BusinessSubscription::factory()->active()->create(['profile_id' => $business->id]);

        $community = Profile::factory()->community()->create();

        $opportunity = CollabOpportunity::factory()->published()->forCreator($business)->create();
        $application = Application::factory()->accepted()->forOpportunity($opportunity)->forApplicant($community)->create();

        $this->actingAs($business)
            ->postJson("/api/v1/applications/{$application->id}/decline")
            ->assertStatus(403);
    }

    /*
    |--------------------------------------------------------------------------
    | Withdraw (POST /api/v1/applications/{id}/withdraw)
    |--------------------------------------------------------------------------
    */

    public function test_applicant_can_withdraw_pending_application(): void
    {
        $business = Profile::factory()->business()->create();
        $community = Profile::factory()->community()->create();

        $opportunity = CollabOpportunity::factory()->published()->forCreator($business)->create();
        $application = Application::factory()->pending()->forOpportunity($opportunity)->forApplicant($community)->create();

        $this->actingAs($community)
            ->postJson("/api/v1/applications/{$application->id}/withdraw")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'withdrawn');

        $this->assertTrue($application->fresh()->isWithdrawn());
    }

    public function test_creator_cannot_withdraw_application(): void
    {
        $business = Profile::factory()->business()->create();
        BusinessSubscription::factory()->active()->create(['profile_id' => $business->id]);

        $community = Profile::factory()->community()->create();

        $opportunity = CollabOpportunity::factory()->published()->forCreator($business)->create();
        $application = Application::factory()->pending()->forOpportunity($opportunity)->forApplicant($community)->create();

        $this->actingAs($business)
            ->postJson("/api/v1/applications/{$application->id}/withdraw")
            ->assertStatus(403);
    }

    public function test_cannot_withdraw_already_declined_application(): void
    {
        $business = Profile::factory()->business()->create();
        $community = Profile::factory()->community()->create();

        $opportunity = CollabOpportunity::factory()->published()->forCreator($business)->create();
        $application = Application::factory()->declined()->forOpportunity($opportunity)->forApplicant($community)->create();

        $this->actingAs($community)
            ->postJson("/api/v1/applications/{$application->id}/withdraw")
            ->assertStatus(403);
    }

    /*
    |--------------------------------------------------------------------------
    | Chat read-only after decline / withdraw
    |--------------------------------------------------------------------------
    */

    public function test_sending_message_on_declined_application_is_blocked(): void
    {
        $business = Profile::factory()->business()->create();
        $community = Profile::factory()->community()->create();

        $opportunity = CollabOpportunity::factory()->published()->forCreator($business)->create();
        $application = Application::factory()->declined()->forOpportunity($opportunity)->forApplicant($community)->create();

        $this->actingAs($community)
            ->postJson("/api/v1/applications/{$application->id}/messages", [
                'content' => 'Can you reconsider?',
            ])
            ->assertStatus(403);
    }

    public function test_sending_message_on_withdrawn_application_is_blocked(): void
    {
        $business = Profile::factory()->business()->create();
        $community = Profile::factory()->community()->create();

        $opportunity = CollabOpportunity::factory()->published()->forCreator($business)->create();
        $application = Application::factory()->withdrawn()->forOpportunity($opportunity)->forApplicant($community)->create();

        $this->actingAs($business)
            ->postJson("/api/v1/applications/{$application->id}/messages", [
                'content' => 'Are you still interested?',
            ])
            ->assertStatus(403);
    }
}
