<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\SubscriptionSource;
use App\Enums\SubscriptionStatus;
use App\Enums\UserType;
use App\Models\Application;
use App\Models\BusinessSubscription;
use App\Models\CollabOpportunity;
use App\Models\Collaboration;
use App\Models\Kolab;
use App\Models\Profile;
use App\Services\ApplicationService;
use App\Services\CollaborationService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class LifecycleTimestampsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_accepting_an_application_stamps_accepted_at(): void
    {
        $kolab = $this->kolab();
        $application = Application::factory()->pending()->create([
            'collab_opportunity_id' => $kolab->id,
            'applicant_profile_id' => Profile::factory()->community(),
            'applicant_profile_type' => UserType::Community,
        ]);

        app(ApplicationService::class)->accept($application);

        $this->assertNotNull($application->fresh()->accepted_at);
        $this->assertNull($application->fresh()->declined_at);
    }

    public function test_declining_an_application_stamps_declined_at(): void
    {
        $kolab = $this->kolab();
        $application = Application::factory()->pending()->create([
            'collab_opportunity_id' => $kolab->id,
            'applicant_profile_id' => Profile::factory()->community(),
            'applicant_profile_type' => UserType::Community,
        ]);

        app(ApplicationService::class)->decline($application, 'not a fit');

        $this->assertNotNull($application->fresh()->declined_at);
    }

    public function test_withdrawing_an_application_stamps_withdrawn_at(): void
    {
        $kolab = $this->kolab();
        $application = Application::factory()->pending()->create([
            'collab_opportunity_id' => $kolab->id,
            'applicant_profile_id' => Profile::factory()->community(),
            'applicant_profile_type' => UserType::Community,
        ]);

        app(ApplicationService::class)->withdraw($application);

        $this->assertNotNull($application->fresh()->withdrawn_at);
    }

    public function test_cancelling_a_collaboration_persists_reason_and_timestamp(): void
    {
        $kolab = $this->kolab();
        $application = Application::factory()->accepted()->create([
            'collab_opportunity_id' => $kolab->id,
            'applicant_profile_id' => Profile::factory()->community(),
            'applicant_profile_type' => UserType::Community,
        ]);
        $collaboration = Collaboration::factory()->active()->create([
            'collab_opportunity_id' => $kolab->id,
            'application_id' => $application->id,
            'creator_profile_id' => $kolab->creator_profile_id,
        ]);

        app(CollaborationService::class)->cancel($collaboration, 'duplicate Kolab');

        $fresh = $collaboration->fresh();
        $this->assertNotNull($fresh->cancelled_at);
        $this->assertSame('duplicate Kolab', $fresh->cancellation_reason);
        $this->assertNull($fresh->cancelled_by_profile_id);
    }

    public function test_authenticated_api_request_touches_last_active_at(): void
    {
        $profile = Profile::factory()->community()->create(['last_active_at' => null]);

        $this->actingAs($profile)
            ->withHeaders(['Accept' => 'application/json'])
            ->get('/api/v1/auth/me');

        $this->assertNotNull($profile->fresh()->last_active_at);
    }

    private function kolab(): Kolab
    {
        $kolab = Kolab::factory()->published()->create();
        CollabOpportunity::factory()->create([
            'id' => $kolab->id,
            'creator_profile_id' => $kolab->creator_profile_id,
        ]);

        // The creator must have an active subscription to accept applications.
        BusinessSubscription::query()->updateOrCreate(
            ['profile_id' => $kolab->creator_profile_id],
            ['source' => SubscriptionSource::Maintainer, 'status' => SubscriptionStatus::Active],
        );

        return $kolab;
    }
}
