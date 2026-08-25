<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Enums\UserType;
use App\Jobs\SendTransactionalEmail;
use App\Models\Application;
use App\Models\AttendeeProfile;
use App\Models\BusinessProfile;
use App\Models\Collaboration;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\CommunityProfile;
use App\Models\CommunityTier;
use App\Models\EventReward;
use App\Models\Kolab;
use App\Models\NotificationPreference;
use App\Models\PointLedger;
use App\Models\Profile;
use App\Models\RewardClaim;
use App\Services\BadgeService;
use App\Services\NotificationService;
use App\Services\TierAssignmentService;
use Database\Seeders\BadgeSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

/**
 * The transactional-email side-effect wired into the notification funnel
 * (NotificationService::createNotification -> EMAIL_MAP). Each lifecycle seam
 * should queue the right Postmark template to the right recipient with the
 * right merge vars, and respect the recipient's preferences.
 */
class LifecycleEmailTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function business(string $name): Profile
    {
        $profile = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $profile->id, 'name' => $name]);

        return $profile->refresh();
    }

    private function community(string $name): Profile
    {
        $profile = Profile::factory()->community()->create();
        CommunityProfile::factory()->create(['profile_id' => $profile->id, 'name' => $name]);

        return $profile->refresh();
    }

    /**
     * @param  array<string, mixed>  $model
     */
    private function assertEmailQueued(string $toEmail, string $alias, array $model): void
    {
        Bus::assertDispatched(SendTransactionalEmail::class, function (SendTransactionalEmail $job) use ($toEmail, $alias, $model): bool {
            if ($job->to !== $toEmail || $job->data['alias'] !== $alias) {
                return false;
            }

            foreach ($model as $key => $value) {
                if (($job->data['model'][$key] ?? null) !== $value) {
                    return false;
                }
            }

            return true;
        });
    }

    private function assertNoEmail(string $alias): void
    {
        Bus::assertNotDispatched(
            SendTransactionalEmail::class,
            fn (SendTransactionalEmail $job): bool => $job->data['alias'] === $alias,
        );
    }

    public function test_application_received_emails_the_business(): void
    {
        Bus::fake();

        $business = $this->business('Joe Cafe');
        $community = $this->community('Run Club');
        $kolab = Kolab::factory()->published()->create(['creator_profile_id' => $business->id, 'title' => 'Summer Popup']);
        $application = Application::factory()->create([
            'kolab_id' => $kolab->id,
            'applicant_profile_id' => $community->id,
            'applicant_profile_type' => UserType::Community,
        ]);

        app(NotificationService::class)->notifyApplicationReceived($application);

        $this->assertEmailQueued($business->email, 'application-received', [
            'first_name' => 'Joe Cafe',
            'applicant_name' => 'Run Club',
            'opportunity_title' => 'Summer Popup',
        ]);
    }

    public function test_application_accepted_emails_the_applicant(): void
    {
        Bus::fake();

        $business = $this->business('Joe Cafe');
        $community = $this->community('Run Club');
        $kolab = Kolab::factory()->published()->create(['creator_profile_id' => $business->id, 'title' => 'Summer Popup']);
        $application = Application::factory()->create([
            'kolab_id' => $kolab->id,
            'applicant_profile_id' => $community->id,
            'applicant_profile_type' => UserType::Community,
        ]);

        app(NotificationService::class)->notifyApplicationAccepted($application);

        $this->assertEmailQueued($community->email, 'application-accepted', [
            'first_name' => 'Run Club',
            'partner_name' => 'Joe Cafe',
            'opportunity_title' => 'Summer Popup',
        ]);
    }

    public function test_application_declined_emails_the_applicant(): void
    {
        Bus::fake();

        $business = $this->business('Joe Cafe');
        $community = $this->community('Run Club');
        $kolab = Kolab::factory()->published()->create(['creator_profile_id' => $business->id, 'title' => 'Winter Market']);
        $application = Application::factory()->create([
            'kolab_id' => $kolab->id,
            'applicant_profile_id' => $community->id,
            'applicant_profile_type' => UserType::Community,
        ]);

        app(NotificationService::class)->notifyApplicationDeclined($application);

        $this->assertEmailQueued($community->email, 'application-declined', [
            'first_name' => 'Run Club',
            'partner_name' => 'Joe Cafe',
            'opportunity_title' => 'Winter Market',
        ]);
    }

    public function test_collaboration_confirmed_emails_both_parties_with_counterpart_names(): void
    {
        Bus::fake();

        $business = $this->business('Joe Cafe');
        $community = $this->community('Run Club');
        $kolab = Kolab::factory()->published()->create(['creator_profile_id' => $business->id, 'title' => 'Summer Popup']);
        $date = Carbon::parse('2026-08-15');
        $collaboration = Collaboration::factory()->create([
            'kolab_id' => $kolab->id,
            'creator_profile_id' => $business->id,
            'applicant_profile_id' => $community->id,
            'scheduled_date' => $date,
        ]);

        app(NotificationService::class)->notifyCollaborationCreated($collaboration);

        $expectedDate = $date->format('l, j M Y');

        // Business hears their partner is the community.
        $this->assertEmailQueued($business->email, 'collab-confirmed', [
            'first_name' => 'Joe Cafe',
            'partner_name' => 'Run Club',
            'scheduled_date' => $expectedDate,
        ]);
        // Community hears their partner is the business.
        $this->assertEmailQueued($community->email, 'collab-confirmed', [
            'first_name' => 'Run Club',
            'partner_name' => 'Joe Cafe',
            'scheduled_date' => $expectedDate,
        ]);
    }

    public function test_feedback_request_emails_both_parties(): void
    {
        Bus::fake();

        $business = $this->business('Joe Cafe');
        $community = $this->community('Run Club');
        $collaboration = Collaboration::factory()->create([
            'creator_profile_id' => $business->id,
            'applicant_profile_id' => $community->id,
        ]);

        app(NotificationService::class)->notifyCollabFollowUpReminder($collaboration);

        $this->assertEmailQueued($business->email, 'feedback-request', ['partner_name' => 'Run Club']);
        $this->assertEmailQueued($community->email, 'feedback-request', ['partner_name' => 'Joe Cafe']);
    }

    public function test_reward_won_emails_the_winner(): void
    {
        Bus::fake();

        $attendee = Profile::factory()->attendee()->create(['name' => 'Dana']);
        $reward = EventReward::factory()->create(['name' => 'Free Coffee']);
        $claim = RewardClaim::factory()->create([
            'profile_id' => $attendee->id,
            'event_reward_id' => $reward->id,
        ]);

        app(NotificationService::class)->notifyRewardWon($claim);

        $this->assertEmailQueued($attendee->email, 'reward-won', [
            'first_name' => 'Dana',
            'reward_name' => 'Free Coffee',
        ]);
    }

    public function test_badge_earned_emails_the_attendee(): void
    {
        Bus::fake();
        $this->seed(BadgeSeeder::class);

        $attendee = Profile::factory()->attendee()->create(['name' => 'Dana']);
        AttendeeProfile::factory()->create(['profile_id' => $attendee->id, 'total_events_attended' => 1]);

        app(BadgeService::class)->checkAndAwardBadges($attendee->refresh());

        Bus::assertDispatched(SendTransactionalEmail::class, function (SendTransactionalEmail $job) use ($attendee): bool {
            return $job->to === $attendee->email
                && $job->data['alias'] === 'badge-earned'
                && $job->data['model']['first_name'] === 'Dana'
                && ! empty($job->data['model']['badge_name']);
        });
    }

    public function test_tier_promotion_emails_the_member(): void
    {
        Bus::fake();

        $community = Community::factory()->create();
        CommunityTier::factory()->defaultTier()->forCommunity($community)->create();
        CommunityTier::factory()->xpThreshold(500)->forCommunity($community)->create(['rank' => 2, 'name' => 'Gold']);

        $profile = Profile::factory()->attendee()->create(['name' => 'Dana']);
        PointLedger::factory()->create(['profile_id' => $profile->id, 'points' => 500]);
        $member = CommunityMember::factory()->forCommunity($community)->create(['profile_id' => $profile->id]);

        app(TierAssignmentService::class)->evaluateMember($member);

        $this->assertEmailQueued($profile->email, 'tier-promotion', [
            'first_name' => 'Dana',
            'tier_name' => 'Gold',
        ]);
    }

    public function test_lifecycle_email_respects_master_opt_out(): void
    {
        Bus::fake();

        $business = $this->business('Joe Cafe');
        $community = $this->community('Run Club');
        NotificationPreference::factory()->allDisabled()->create(['profile_id' => $community->id]);
        $kolab = Kolab::factory()->published()->create(['creator_profile_id' => $business->id, 'title' => 'Summer Popup']);
        $application = Application::factory()->create([
            'kolab_id' => $kolab->id,
            'applicant_profile_id' => $community->id,
            'applicant_profile_type' => UserType::Community,
        ]);

        app(NotificationService::class)->notifyApplicationAccepted($application);

        $this->assertNoEmail('application-accepted');
    }

    public function test_application_received_respects_the_application_alerts_flag(): void
    {
        Bus::fake();

        $business = $this->business('Joe Cafe');
        $community = $this->community('Run Club');
        NotificationPreference::factory()->create([
            'profile_id' => $business->id,
            'email_notifications' => true,
            'new_application_alerts' => false,
        ]);
        $kolab = Kolab::factory()->published()->create(['creator_profile_id' => $business->id, 'title' => 'Summer Popup']);
        $application = Application::factory()->create([
            'kolab_id' => $kolab->id,
            'applicant_profile_id' => $community->id,
            'applicant_profile_type' => UserType::Community,
        ]);

        app(NotificationService::class)->notifyApplicationReceived($application);

        $this->assertNoEmail('application-received');
    }
}
