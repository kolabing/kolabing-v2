<?php

declare(strict_types=1);

namespace Tests\Feature\Gamification;

use App\Enums\ApplicationStatus;
use App\Enums\ChallengeAudience;
use App\Enums\MissionRepeat;
use App\Enums\MissionTrigger;
use App\Enums\PointEventType;
use App\Enums\SubscriptionSource;
use App\Enums\SubscriptionStatus;
use App\Enums\UserType;
use App\Models\Application;
use App\Models\BusinessProfile;
use App\Models\BusinessSubscription;
use App\Models\Challenge;
use App\Models\ChallengeProgress;
use App\Models\Collaboration;
use App\Models\CommunityProfile;
use App\Models\Kolab;
use App\Models\PointLedger;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Phase 2: the wired ✓ triggers (collaboration_complete, review_posted /
 * review_received, business_referred) progress + complete + award their
 * matching missions through the real platform endpoints/services.
 */
class MissionAutoAwardTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\XpEarnRuleSeeder::class);
    }

    private function mission(
        MissionTrigger $trigger,
        ChallengeAudience $audience,
        int $target = 1,
        int $points = 50,
    ): Challenge {
        return Challenge::factory()
            ->mission($trigger, $target, MissionRepeat::Once, $audience)
            ->create(['points' => $points]);
    }

    private function makeActiveCollab(): array
    {
        $business = Profile::factory()->business()->create();
        BusinessSubscription::query()->updateOrCreate(
            ['profile_id' => $business->id],
            ['source' => SubscriptionSource::Maintainer, 'status' => SubscriptionStatus::Active],
        );

        $community = Profile::factory()->community()->create();

        $opportunity = Kolab::factory()->published()->create([
            'creator_profile_id' => $business->id,
        ]);

        $application = Application::factory()->create([
            'kolab_id' => $opportunity->id,
            'applicant_profile_id' => $community->id,
            'applicant_profile_type' => UserType::Community,
            'status' => ApplicationStatus::Accepted,
        ]);

        $collab = Collaboration::factory()->active()->create([
            'kolab_id' => $opportunity->id,
            'application_id' => $application->id,
            'creator_profile_id' => $business->id,
            'applicant_profile_id' => $community->id,
            'business_profile_id' => $business->businessProfile?->id,
            'community_profile_id' => $community->communityProfile?->id,
        ]);

        return ['collab' => $collab, 'business' => $business, 'community' => $community];
    }

    public function test_feedback_submission_progresses_collaboration_complete_mission_for_that_party(): void
    {
        $bizMission = $this->mission(MissionTrigger::CollaborationComplete, ChallengeAudience::Business, 1, 50);
        $comMission = $this->mission(MissionTrigger::CollaborationComplete, ChallengeAudience::Community, 1, 50);

        ['collab' => $collab, 'business' => $business] = $this->makeActiveCollab();

        $this->actingAs($business)
            ->postJson(route('api.v1.collaborations.feedback.store', $collab), [
                'rating' => 5,
                'expectation_match' => true,
                'would_recommend' => true,
                'posts_reels' => 3,
                'stories_posted' => 12,
                'revenue' => '450.50',
            ])->assertCreated();

        // Business side completed + awarded once.
        $this->assertDatabaseHas('challenge_progress', [
            'challenge_id' => $bizMission->id,
            'profile_id' => $business->id,
            'progress_count' => 1,
        ]);
        $this->assertNotNull(ChallengeProgress::query()
            ->where('challenge_id', $bizMission->id)->value('completed_at'));
        $this->assertSame(1, PointLedger::query()
            ->where('profile_id', $business->id)
            ->where('event_type', PointEventType::MissionCompleted->value)
            ->count());

        // Community party did not submit feedback → no community progress.
        $this->assertDatabaseMissing('challenge_progress', [
            'challenge_id' => $comMission->id,
        ]);
    }

    public function test_review_progresses_reviewer_posted_and_reviewed_received_missions(): void
    {
        // Attendee-audience review_posted will NOT match a business reviewer, so
        // the business reviewer fires review_received on the community party,
        // which carries the business_review_received mission.
        $reviewReceivedBiz = $this->mission(MissionTrigger::ReviewReceived, ChallengeAudience::Business, 1, 30);
        $bizReviewForCommunity = $this->mission(MissionTrigger::BusinessReviewReceived, ChallengeAudience::Community, 1, 20);

        $business = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $business->id, 'name' => 'Reviewer Biz']);

        $community = Profile::factory()->community()->create();
        CommunityProfile::factory()->create(['profile_id' => $community->id, 'name' => 'Reviewed Com']);

        $collab = Collaboration::factory()
            ->completed()
            ->forCreator($business)
            ->forApplicant($community)
            ->create([
                'business_profile_id' => $business->businessProfile?->id,
                'community_profile_id' => $community->communityProfile?->id,
            ]);

        $this->actingAs($business)
            ->postJson("/api/v1/collaborations/{$collab->id}/review", [
                'rating' => 5,
                'body' => 'Great collaboration',
                'would_collaborate_again' => true,
            ])->assertCreated();

        // Community party received a business review → mission completed + awarded.
        $this->assertNotNull(ChallengeProgress::query()
            ->where('challenge_id', $bizReviewForCommunity->id)
            ->where('profile_id', $community->id)
            ->value('completed_at'));
        $this->assertSame(20, PointLedger::query()
            ->where('profile_id', $community->id)
            ->where('event_type', PointEventType::MissionCompleted->value)
            ->sum('points'));

        // The business is the reviewer here (not reviewed), so its
        // review_received mission must NOT progress.
        $this->assertDatabaseMissing('challenge_progress', [
            'challenge_id' => $reviewReceivedBiz->id,
            'profile_id' => $business->id,
        ]);
    }

    public function test_referral_conversion_progresses_business_referred_mission(): void
    {
        $referrer = Profile::factory()->business()->create();
        $referredBusiness = Profile::factory()->business()->create();

        $mission = $this->mission(MissionTrigger::BusinessReferred, ChallengeAudience::Business, 1, 50);

        $referralCode = \App\Models\ReferralCode::query()->create([
            'profile_id' => $referrer->id,
            'code' => 'REFTEST1',
        ]);

        $subscription = BusinessSubscription::query()->create([
            'profile_id' => $referredBusiness->id,
            'source' => SubscriptionSource::Stripe,
            'status' => SubscriptionStatus::Active,
        ]);

        app(\App\Services\ReferralService::class)
            ->rewardFirstPaidSubscription($referredBusiness, $subscription, $referralCode);

        $this->assertNotNull(ChallengeProgress::query()
            ->where('challenge_id', $mission->id)
            ->where('profile_id', $referrer->id)
            ->value('completed_at'));
        $this->assertSame(1, PointLedger::query()
            ->where('profile_id', $referrer->id)
            ->where('event_type', PointEventType::MissionCompleted->value)
            ->count());
    }
}
