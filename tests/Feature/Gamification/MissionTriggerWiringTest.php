<?php

declare(strict_types=1);

namespace Tests\Feature\Gamification;

use App\Enums\ApplicationStatus;
use App\Enums\ChallengeAudience;
use App\Enums\IntentType;
use App\Enums\JoinPolicy;
use App\Enums\KolabStatus;
use App\Enums\MissionRepeat;
use App\Enums\MissionTrigger;
use App\Enums\PointEventType;
use App\Enums\SubscriptionSource;
use App\Enums\SubscriptionStatus;
use App\Enums\UserType;
use App\Models\Application;
use App\Models\BusinessSubscription;
use App\Models\Challenge;
use App\Models\ChallengeProgress;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Event;
use App\Models\Kolab;
use App\Models\PointLedger;
use App\Models\Profile;
use App\Services\AppleIAPService;
use App\Services\ApplicationService;
use App\Services\CheckinService;
use App\Services\CommunityMemberService;
use App\Services\KolabService;
use App\Services\OnboardingService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Phase 3: the ⧖ triggers wired at their source actions (profile completion,
 * profile photo, event/member check-in, kolab publish + by-type, applications,
 * subscription renewal, community join/invite) progress + complete + award
 * their matching missions, once, audience-correct.
 */
class MissionTriggerWiringTest extends TestCase
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
        MissionRepeat $repeat = MissionRepeat::Once,
    ): Challenge {
        return Challenge::factory()
            ->mission($trigger, $target, $repeat, $audience)
            ->create(['points' => $points]);
    }

    private function assertCompletedOnce(Challenge $mission, Profile $earner, int $points): void
    {
        $this->assertNotNull(
            ChallengeProgress::query()
                ->where('challenge_id', $mission->id)
                ->where('profile_id', $earner->id)
                ->value('completed_at'),
            'expected mission to be completed for the earner',
        );

        $this->assertSame(
            $points,
            (int) PointLedger::query()
                ->where('profile_id', $earner->id)
                ->where('event_type', PointEventType::MissionCompleted->value)
                ->where('description', 'Mission completed: '.$mission->name)
                ->sum('points'),
            'expected the mission to award its points exactly once',
        );
    }

    // --- Profile completion + photo (onboarding) ---

    public function test_attendee_onboarding_completes_profile_completed_mission(): void
    {
        $mission = $this->mission(MissionTrigger::ProfileCompleted, ChallengeAudience::Attendee, 1, 10);
        $business = $this->mission(MissionTrigger::BusinessProfileCompleted, ChallengeAudience::Business, 1, 10);

        $profile = Profile::factory()->attendee()->create();

        app(OnboardingService::class)->completeAttendeeOnboarding($profile, [
            'name' => 'Test Attendee',
            'handle' => 'test_attendee',
            'interests' => ['music'],
        ]);

        $this->assertCompletedOnce($mission, $profile, 10);

        // The business-audience completion mission must not progress for an attendee.
        $this->assertDatabaseMissing('challenge_progress', [
            'challenge_id' => $business->id,
            'profile_id' => $profile->id,
        ]);
    }

    public function test_business_onboarding_completes_profile_and_photo_missions(): void
    {
        $profileMission = $this->mission(MissionTrigger::BusinessProfileCompleted, ChallengeAudience::Business, 1, 10);
        $photoMission = $this->mission(MissionTrigger::BusinessPhotoUploaded, ChallengeAudience::Business, 1, 10);

        $profile = Profile::factory()->business()->create();
        \App\Models\BusinessProfile::factory()->create(['profile_id' => $profile->id]);

        app(OnboardingService::class)->completeBusinessOnboarding($profile, [
            'name' => 'Test Biz',
            'has_venue' => false,
            'city_name' => 'Barcelona',
            // An app-domain URL is returned as-is by handleProfilePhoto (no
            // external download), so a photo is resolved in the test env.
            'profile_photo' => config('app.url').'/storage/photo.jpg',
        ]);

        $this->assertCompletedOnce($profileMission, $profile, 10);
        $this->assertCompletedOnce($photoMission, $profile, 10);
    }

    public function test_business_onboarding_without_photo_does_not_fire_photo_mission(): void
    {
        $photoMission = $this->mission(MissionTrigger::BusinessPhotoUploaded, ChallengeAudience::Business, 1, 10);

        $profile = Profile::factory()->business()->create();
        \App\Models\BusinessProfile::factory()->create(['profile_id' => $profile->id]);

        app(OnboardingService::class)->completeBusinessOnboarding($profile, [
            'name' => 'No Photo Biz',
            'has_venue' => false,
            'city_name' => 'Barcelona',
        ]);

        $this->assertDatabaseMissing('challenge_progress', [
            'challenge_id' => $photoMission->id,
            'profile_id' => $profile->id,
        ]);
    }

    public function test_community_onboarding_completes_profile_and_photo_missions(): void
    {
        $profileMission = $this->mission(MissionTrigger::CommunityProfileCompleted, ChallengeAudience::Community, 1, 10);
        $photoMission = $this->mission(MissionTrigger::CommunityPhotoUploaded, ChallengeAudience::Community, 1, 10);

        $profile = Profile::factory()->community()->create();
        \App\Models\CommunityProfile::factory()->create(['profile_id' => $profile->id]);

        app(OnboardingService::class)->completeCommunityOnboarding($profile, [
            'name' => 'Test Community',
            'community_type' => 'running',
            'city_id' => \App\Models\City::factory()->create()->id,
            'profile_photo' => config('app.url').'/storage/logo.jpg',
        ]);

        $this->assertCompletedOnce($profileMission, $profile, 10);
        $this->assertCompletedOnce($photoMission, $profile, 10);
    }

    // --- Event check-in (attendee) + member check-in (community owner) ---

    public function test_event_checkin_progresses_attendee_event_checkin_mission(): void
    {
        $mission = $this->mission(MissionTrigger::EventCheckin, ChallengeAudience::Attendee, 1, 20);

        $attendee = Profile::factory()->attendee()->create();
        $host = Profile::factory()->create();
        $event = Event::factory()->forProfile($host)->create();
        $token = app(CheckinService::class)->generateCheckinToken($event);

        app(CheckinService::class)->checkin($attendee, $token);

        $this->assertCompletedOnce($mission, $attendee, 20);
    }

    public function test_member_checkin_progresses_community_owner_mission_for_active_members_only(): void
    {
        $mission = $this->mission(MissionTrigger::MemberCheckin, ChallengeAudience::Community, 1, 50);

        $owner = Profile::factory()->community()->create();
        $community = Community::factory()->forOwner($owner)->create();

        $member = Profile::factory()->attendee()->create();
        CommunityMember::factory()->forCommunity($community)->create(['profile_id' => $member->id]);

        $host = Profile::factory()->create();
        $event = Event::factory()->forProfile($host)->create(['community_id' => $community->id]);
        $token = app(CheckinService::class)->generateCheckinToken($event);

        app(CheckinService::class)->checkin($member, $token);

        $this->assertCompletedOnce($mission, $owner, 50);
    }

    public function test_member_checkin_does_not_fire_for_non_member_attendee(): void
    {
        $mission = $this->mission(MissionTrigger::MemberCheckin, ChallengeAudience::Community, 1, 50);

        $owner = Profile::factory()->community()->create();
        $community = Community::factory()->forOwner($owner)->create();

        // Attendee who is NOT a member of the community.
        $stranger = Profile::factory()->attendee()->create();
        $host = Profile::factory()->create();
        $event = Event::factory()->forProfile($host)->create(['community_id' => $community->id]);
        $token = app(CheckinService::class)->generateCheckinToken($event);

        app(CheckinService::class)->checkin($stranger, $token);

        $this->assertDatabaseMissing('challenge_progress', [
            'challenge_id' => $mission->id,
            'profile_id' => $owner->id,
        ]);
    }

    // --- Kolab publish + by-type ---

    private function subscribedBusiness(): Profile
    {
        $business = Profile::factory()->business()->create();
        BusinessSubscription::query()->updateOrCreate(
            ['profile_id' => $business->id],
            ['source' => SubscriptionSource::Maintainer, 'status' => SubscriptionStatus::Active],
        );

        return $business;
    }

    public function test_publishing_kolab_progresses_kolab_published_mission(): void
    {
        $mission = $this->mission(MissionTrigger::KolabPublished, ChallengeAudience::Business, 1, 20);

        $business = $this->subscribedBusiness();
        $kolab = Kolab::factory()->create([
            'creator_profile_id' => $business->id,
            'status' => KolabStatus::Draft,
            'intent_type' => IntentType::VenuePromotion,
        ]);

        app(KolabService::class)->publish($kolab);

        $this->assertCompletedOnce($mission, $business, 20);
    }

    public function test_publishing_product_kolab_progresses_product_type_mission(): void
    {
        $published = $this->mission(MissionTrigger::KolabPublished, ChallengeAudience::Business, 1, 20);
        $product = $this->mission(MissionTrigger::KolabCreatedProduct, ChallengeAudience::Business, 1, 20);

        $business = $this->subscribedBusiness();
        $kolab = Kolab::factory()->create([
            'creator_profile_id' => $business->id,
            'status' => KolabStatus::Draft,
            'intent_type' => IntentType::ProductPromotion,
        ]);

        app(KolabService::class)->publish($kolab);

        $this->assertCompletedOnce($published, $business, 20);
        $this->assertCompletedOnce($product, $business, 20);
    }

    public function test_publishing_recurring_kolab_progresses_recurring_created_mission(): void
    {
        $recurring = $this->mission(MissionTrigger::RecurringKolabCreated, ChallengeAudience::Business, 1, 20);

        $business = $this->subscribedBusiness();
        $kolab = Kolab::factory()->create([
            'creator_profile_id' => $business->id,
            'status' => KolabStatus::Draft,
            'intent_type' => IntentType::VenuePromotion,
            'availability_mode' => 'recurring',
            'recurring_days' => [1, 3, 5],
        ]);

        app(KolabService::class)->publish($kolab);

        $this->assertCompletedOnce($recurring, $business, 20);
    }

    // --- Applications: submitted / received / accepted ---

    public function test_apply_progresses_submitted_for_community_and_received_for_business(): void
    {
        $submitted = $this->mission(MissionTrigger::ApplicationSubmitted, ChallengeAudience::Community, 1, 20);
        $received = $this->mission(MissionTrigger::ApplicationReceived, ChallengeAudience::Business, 1, 20);

        $business = $this->subscribedBusiness();
        $community = Profile::factory()->community()->create();

        $kolab = Kolab::factory()->published()->create(['creator_profile_id' => $business->id]);

        app(ApplicationService::class)->apply($community, $kolab, ['message' => 'Hi']);

        $this->assertCompletedOnce($submitted, $community, 20);
        $this->assertCompletedOnce($received, $business, 20);
    }

    public function test_accept_progresses_application_accepted_for_both_parties(): void
    {
        $bizAccepted = $this->mission(MissionTrigger::ApplicationAccepted, ChallengeAudience::Business, 1, 20);
        $comAccepted = $this->mission(MissionTrigger::ApplicationAccepted, ChallengeAudience::Community, 1, 20);

        $business = $this->subscribedBusiness();
        $community = Profile::factory()->community()->create();

        $kolab = Kolab::factory()->published()->create(['creator_profile_id' => $business->id]);
        $application = Application::factory()->create([
            'kolab_id' => $kolab->id,
            'applicant_profile_id' => $community->id,
            'applicant_profile_type' => UserType::Community,
            'status' => ApplicationStatus::Pending,
        ]);

        app(ApplicationService::class)->accept($application);

        $this->assertCompletedOnce($bizAccepted, $business, 20);
        $this->assertCompletedOnce($comAccepted, $community, 20);
    }

    // --- Subscription renewal (Apple DID_RENEW) ---

    public function test_did_renew_progresses_subscription_renewed_mission(): void
    {
        $mission = $this->mission(MissionTrigger::SubscriptionRenewed, ChallengeAudience::Business, 1, 20);

        $business = Profile::factory()->business()->create();
        $subscription = BusinessSubscription::query()->create([
            'profile_id' => $business->id,
            'source' => SubscriptionSource::AppleIap,
            'status' => SubscriptionStatus::Active,
            'apple_original_transaction_id' => 'orig-renew-1',
        ]);

        app(AppleIAPService::class)->handleNotification('DID_RENEW', [
            'originalTransactionId' => 'orig-renew-1',
            'transactionId' => 'txn-renew-1',
            'productId' => 'business_monthly',
        ]);

        $this->assertCompletedOnce($mission, $business, 20);
    }

    public function test_initial_subscribed_does_not_fire_renewal_mission(): void
    {
        $mission = $this->mission(MissionTrigger::SubscriptionRenewed, ChallengeAudience::Business, 1, 20);

        $business = Profile::factory()->business()->create();
        BusinessSubscription::query()->create([
            'profile_id' => $business->id,
            'source' => SubscriptionSource::AppleIap,
            'status' => SubscriptionStatus::Active,
            'apple_original_transaction_id' => 'orig-sub-1',
        ]);

        app(AppleIAPService::class)->handleNotification('SUBSCRIBED', [
            'originalTransactionId' => 'orig-sub-1',
            'transactionId' => 'txn-sub-1',
            'productId' => 'business_monthly',
        ]);

        $this->assertDatabaseMissing('challenge_progress', [
            'challenge_id' => $mission->id,
            'profile_id' => $business->id,
        ]);
    }

    // --- Community join + invite ---

    public function test_joining_open_community_progresses_community_joined_mission(): void
    {
        $mission = $this->mission(MissionTrigger::CommunityJoined, ChallengeAudience::Attendee, 1, 20);

        $attendee = Profile::factory()->attendee()->create();
        $community = Community::factory()->create(['join_policy' => JoinPolicy::Open->value]);

        app(CommunityMemberService::class)->join($community, $attendee);

        $this->assertCompletedOnce($mission, $attendee, 20);
    }

    public function test_rejoining_does_not_double_fire_community_joined(): void
    {
        $mission = $this->mission(MissionTrigger::CommunityJoined, ChallengeAudience::Attendee, 1, 20);

        $attendee = Profile::factory()->attendee()->create();
        $community = Community::factory()->create(['join_policy' => JoinPolicy::Open->value]);

        $service = app(CommunityMemberService::class);
        $service->join($community, $attendee);
        $service->join($community, $attendee);

        $this->assertSame(
            1,
            PointLedger::query()
                ->where('profile_id', $attendee->id)
                ->where('event_type', PointEventType::MissionCompleted->value)
                ->where('description', 'Mission completed: '.$mission->name)
                ->count(),
        );
    }

    public function test_inviting_member_progresses_members_invited_for_owner(): void
    {
        $mission = $this->mission(MissionTrigger::MembersInvited, ChallengeAudience::Community, 1, 20);

        $owner = Profile::factory()->community()->create();
        $community = Community::factory()->forOwner($owner)->create();
        $invitee = Profile::factory()->attendee()->create();

        $this->actingAs($owner)
            ->postJson(route('api.v1.communities.members.store', $community), [
                'profile_id' => $invitee->id,
            ])->assertCreated();

        $this->assertCompletedOnce($mission, $owner, 20);
    }

    // --- Peer challenge verification → challenge_completed (attendee) ---

    public function test_verifying_peer_challenge_progresses_challenge_completed_mission_for_challenger(): void
    {
        $mission = $this->mission(MissionTrigger::ChallengeCompleted, ChallengeAudience::Attendee, 1, 20);

        $owner = Profile::factory()->business()->create();
        $event = Event::factory()->forProfile($owner)->create([
            'is_active' => true,
            'max_challenges_per_attendee' => 5,
        ]);

        $challenger = Profile::factory()->attendee()->create();
        \App\Models\AttendeeProfile::factory()->create(['profile_id' => $challenger->id]);
        $verifier = Profile::factory()->attendee()->create();
        \App\Models\AttendeeProfile::factory()->create(['profile_id' => $verifier->id]);

        \App\Models\EventCheckin::factory()->forEvent($event)->forProfile($challenger)->create();
        \App\Models\EventCheckin::factory()->forEvent($event)->forProfile($verifier)->create();

        // A peer-verified event challenge (NOT a mission itself).
        $peerChallenge = Challenge::factory()->system()->easy()->create();

        $service = app(\App\Services\ChallengeCompletionService::class);
        $completion = $service->initiate($challenger, [
            'challenge_id' => $peerChallenge->id,
            'event_id' => $event->id,
            'verifier_profile_id' => $verifier->id,
        ]);

        $service->verify($verifier, $completion);

        // The challenger (earner) progresses the challenge_completed mission.
        $this->assertCompletedOnce($mission, $challenger, 20);

        // The verifier does not earn the challenger's mission.
        $this->assertDatabaseMissing('challenge_progress', [
            'challenge_id' => $mission->id,
            'profile_id' => $verifier->id,
        ]);
    }
}
