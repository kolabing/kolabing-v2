<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Application;
use App\Models\BusinessProfile;
use App\Models\BusinessSubscription;
use App\Models\CollabOpportunity;
use App\Models\CommunityProfile;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ApplicationPolicyTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_community_user_can_apply_to_published_opportunity_owned_by_another_profile(): void
    {
        $business = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $business->id]);

        $community = Profile::factory()->community()->create();
        CommunityProfile::factory()->create(['profile_id' => $community->id]);

        $opportunity = CollabOpportunity::factory()
            ->published()
            ->forCreator($business)
            ->create();

        $this->assertTrue($community->can('create', [Application::class, $opportunity]));
    }

    public function test_business_creator_with_active_subscription_can_accept_pending_application(): void
    {
        $business = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $business->id]);
        BusinessSubscription::factory()->active()->create(['profile_id' => $business->id]);

        $community = Profile::factory()->community()->create();
        CommunityProfile::factory()->create(['profile_id' => $community->id]);

        $opportunity = CollabOpportunity::factory()
            ->published()
            ->forCreator($business)
            ->create();

        $application = Application::factory()
            ->pending()
            ->forOpportunity($opportunity)
            ->forApplicant($community)
            ->create();

        $this->assertTrue($business->can('accept', $application));
    }
}
