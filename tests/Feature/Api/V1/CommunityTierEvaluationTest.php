<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\AttendeeProfile;
use App\Models\BusinessProfile;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\CommunityTier;
use App\Models\Event;
use App\Models\Profile;
use App\Services\CheckinService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CommunityTierEvaluationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_command_promotes_eligible_members(): void
    {
        $community = Community::factory()->create();
        $default = CommunityTier::factory()->defaultTier()->forCommunity($community)->create();
        $events = CommunityTier::factory()->eventsAttended(1)->forCommunity($community)->create(['rank' => 2]);

        $profile = Profile::factory()->attendee()->create();
        AttendeeProfile::factory()->create(['profile_id' => $profile->id]);
        $member = CommunityMember::factory()->forCommunity($community)->create([
            'profile_id' => $profile->id,
            'tier_id' => $default->id,
        ]);

        $event = Event::factory()->create(['community_id' => $community->id]);
        \App\Models\EventCheckin::factory()->create([
            'event_id' => $event->id,
            'profile_id' => $profile->id,
            'checked_in_at' => now(),
        ]);

        $this->artisan('app:evaluate-community-tiers')->assertSuccessful();

        $this->assertSame($events->id, $member->fresh()->tier_id);
    }

    public function test_dry_run_writes_nothing(): void
    {
        $community = Community::factory()->create();
        $default = CommunityTier::factory()->defaultTier()->forCommunity($community)->create();
        CommunityTier::factory()->tenure(1)->forCommunity($community)->create(['rank' => 2]);

        $member = CommunityMember::factory()->forCommunity($community)->create([
            'tier_id' => $default->id,
            'joined_at' => now()->subDays(10),
        ]);

        $this->artisan('app:evaluate-community-tiers --dry-run')->assertSuccessful();

        $this->assertSame($default->id, $member->fresh()->tier_id);
    }

    public function test_checking_in_promotes_member_without_cron(): void
    {
        $community = Community::factory()->create();
        $default = CommunityTier::factory()->defaultTier()->forCommunity($community)->create();
        $regular = CommunityTier::factory()->eventsAttended(1)->forCommunity($community)->create(['rank' => 2]);

        $organizer = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $organizer->id]);
        $event = Event::factory()->forProfile($organizer)->create(['community_id' => $community->id]);

        $attendee = Profile::factory()->attendee()->create();
        AttendeeProfile::factory()->create(['profile_id' => $attendee->id]);
        $member = CommunityMember::factory()->forCommunity($community)->create([
            'profile_id' => $attendee->id,
            'tier_id' => $default->id,
        ]);

        $token = app(CheckinService::class)->generateCheckinToken($event);
        app(CheckinService::class)->checkin($attendee->fresh(), $token);

        $this->assertSame($regular->id, $member->fresh()->tier_id);
    }
}
