<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Jobs\Notifications\SendGrowthCampaignNotificationsJob;
use App\Models\Application;
use App\Models\AttendeeProfile;
use App\Models\BusinessProfile;
use App\Models\City;
use App\Models\CollabOpportunity;
use App\Models\CommunityProfile;
use App\Models\DeviceToken;
use App\Models\Event;
use App\Models\Notification;
use App\Models\NotificationPreference;
use App\Models\Profile;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GrowthNotificationTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'notifications.enabled_types.pending_application_nudge' => true,
            'notifications.enabled_types.opportunity_match' => true,
            'notifications.enabled_types.nearby_event_match' => true,
            'notifications.enabled_types.wallet_threshold_reached' => true,
            'notifications.enabled_types.dormant_user_reactivation' => true,
        ]);
    }

    public function test_growth_job_creates_pending_application_nudge(): void
    {
        $city = City::factory()->create(['name' => 'Barcelona', 'country' => 'Spain']);
        $owner = $this->makeBusinessProfile($city, 'Owner');
        $applicant = $this->makeCommunityProfile($city, 'Applicant');
        $this->optInMarketing($owner);

        $opportunity = CollabOpportunity::factory()->published()->forCreator($owner)->create([
            'preferred_city' => $city->name,
            'categories' => ['run_club'],
            'published_at' => now()->subDays(2),
        ]);

        Application::factory()->pending()->forOpportunity($opportunity)->forApplicant($applicant)->create([
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ]);

        $this->runGrowthJob();

        $this->assertDatabaseHas('notifications', [
            'profile_id' => $owner->id,
            'type' => 'pending_application_nudge',
        ]);
    }

    public function test_growth_job_creates_opportunity_match_for_matching_profile(): void
    {
        $city = City::factory()->create(['name' => 'Barcelona', 'country' => 'Spain']);
        $creator = $this->makeBusinessProfile($city, 'Business Creator', ['cafe']);
        $recipient = $this->makeCommunityProfile($city, 'Run Club', 'run_club');
        $this->optInMarketing($recipient);

        CollabOpportunity::factory()->published()->forCreator($creator)->create([
            'title' => 'Morning Coffee Meetup',
            'preferred_city' => $city->name,
            'categories' => ['run_club', 'fitness_community'],
            'published_at' => now()->subHours(2),
        ]);

        $this->runGrowthJob();

        $this->assertDatabaseHas('notifications', [
            'profile_id' => $recipient->id,
            'type' => 'opportunity_match',
        ]);
    }

    public function test_growth_job_creates_nearby_event_match_for_location_opted_in_attendee(): void
    {
        $attendee = Profile::factory()->attendee()->create();
        AttendeeProfile::factory()->create(['profile_id' => $attendee->id]);
        $this->optInMarketing($attendee);

        DeviceToken::factory()->for($attendee, 'profile')->withLocation(41.3874, 2.1686)->create();

        $host = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $host->id, 'name' => 'Event Host']);

        Event::factory()->forProfile($host)->create([
            'name' => 'Barcelona Rooftop Session',
            'event_date' => now()->addDays(2)->toDateString(),
            'is_active' => true,
            'location_lat' => 41.3880,
            'location_lng' => 2.1700,
        ]);

        $this->runGrowthJob();

        $this->assertDatabaseHas('notifications', [
            'profile_id' => $attendee->id,
            'type' => 'nearby_event_match',
        ]);
    }

    public function test_growth_job_enforces_one_growth_notification_per_24_hours(): void
    {
        $city = City::factory()->create(['name' => 'Barcelona', 'country' => 'Spain']);
        $owner = $this->makeCommunityProfile($city, 'Community Owner', 'run_club');
        $applicant = $this->makeBusinessProfile($city, 'Business Applicant', ['run_club']);
        $this->optInMarketing($owner);

        Wallet::factory()->withdrawable()->create([
            'profile_id' => $owner->id,
        ]);

        $opportunity = CollabOpportunity::factory()->published()->forCreator($owner)->create([
            'preferred_city' => $city->name,
            'categories' => ['run_club'],
            'published_at' => now()->subDays(2),
        ]);

        Application::factory()->pending()->forOpportunity($opportunity)->forApplicant($applicant)->create([
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ]);

        $this->runGrowthJob();

        $this->assertSame(1, Notification::query()
            ->where('profile_id', $owner->id)
            ->whereIn('type', [
                'pending_application_nudge',
                'wallet_threshold_reached',
            ])
            ->count());
    }

    public function test_growth_job_creates_dormant_user_reactivation_notification(): void
    {
        $profile = Profile::factory()->community()->create();
        CommunityProfile::factory()->create([
            'profile_id' => $profile->id,
            'name' => 'Dormant Community',
        ]);
        $this->optInMarketing($profile);

        DB::table('personal_access_tokens')->insert([
            'tokenable_type' => Profile::class,
            'tokenable_id' => $profile->id,
            'name' => 'mobile-access',
            'token' => hash('sha256', 'dormant-token'),
            'abilities' => json_encode(['community'], JSON_THROW_ON_ERROR),
            'last_used_at' => now()->subDays(8),
            'expires_at' => now()->addDays(30),
            'created_at' => now()->subDays(8),
            'updated_at' => now()->subDays(8),
        ]);

        $this->runGrowthJob();

        $this->assertDatabaseHas('notifications', [
            'profile_id' => $profile->id,
            'type' => 'dormant_user_reactivation',
        ]);
    }

    private function optInMarketing(Profile $profile): void
    {
        NotificationPreference::factory()->for($profile, 'profile')->allEnabled()->create([
            'marketing_enabled' => true,
        ]);
    }

    private function runGrowthJob(): void
    {
        $job = new SendGrowthCampaignNotificationsJob;
        $job->handle(
            app(\App\Services\Notifications\GrowthAudienceService::class),
            app(\App\Services\Notifications\GrowthRateLimitService::class),
            app(\App\Services\NotificationService::class),
            app(\App\Support\Notifications\NotificationMetrics::class),
        );
    }

    /**
     * @param  array<int, string>  $categories
     */
    private function makeBusinessProfile(City $city, string $name, array $categories = ['cafe']): Profile
    {
        $profile = Profile::factory()->business()->create();
        BusinessProfile::factory()->create([
            'profile_id' => $profile->id,
            'name' => $name,
            'city_id' => $city->id,
            'city_name' => $city->name,
            'categories' => $categories,
            'business_type' => $categories[0],
        ]);

        return $profile;
    }

    private function makeCommunityProfile(City $city, string $name, string $communityType = 'run_club'): Profile
    {
        $profile = Profile::factory()->community()->create();
        CommunityProfile::factory()->create([
            'profile_id' => $profile->id,
            'name' => $name,
            'city_id' => $city->id,
            'community_type' => $communityType,
        ]);

        return $profile;
    }
}
