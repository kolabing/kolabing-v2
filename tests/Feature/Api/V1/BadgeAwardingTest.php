<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\BadgeMilestoneType;
use App\Enums\ChallengeCompletionStatus;
use App\Models\AttendeeProfile;
use App\Models\Badge;
use App\Models\BadgeAward;
use App\Models\BusinessProfile;
use App\Models\Challenge;
use App\Models\ChallengeCompletion;
use App\Models\Event;
use App\Models\EventCheckin;
use App\Models\Profile;
use Database\Seeders\BadgeSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class BadgeAwardingTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BadgeSeeder::class);
    }

    public function test_first_checkin_awards_badge(): void
    {
        $owner = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $owner->id]);
        $event = Event::factory()->forProfile($owner)->create([
            'is_active' => true,
            'checkin_token' => 'test-token-1',
        ]);

        $attendee = Profile::factory()->attendee()->create();
        AttendeeProfile::factory()->create(['profile_id' => $attendee->id]);

        $this->actingAs($attendee)
            ->postJson('/api/v1/checkin', ['token' => 'test-token-1']);

        $badge = Badge::where('milestone_type', BadgeMilestoneType::FirstCheckin->value)->first();
        $this->assertDatabaseHas('badge_awards', [
            'badge_id' => $badge->id,
            'profile_id' => $attendee->id,
        ]);
    }

    public function test_first_challenge_awards_badge(): void
    {
        $owner = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $owner->id]);
        $event = Event::factory()->forProfile($owner)->create([
            'is_active' => true,
            'checkin_token' => 'test-token-2',
            'max_challenges_per_attendee' => 10,
        ]);

        $challenger = Profile::factory()->attendee()->create();
        AttendeeProfile::factory()->create(['profile_id' => $challenger->id]);
        $verifier = Profile::factory()->attendee()->create();
        AttendeeProfile::factory()->create(['profile_id' => $verifier->id]);

        EventCheckin::factory()->forEvent($event)->forProfile($challenger)->create();
        EventCheckin::factory()->forEvent($event)->forProfile($verifier)->create();

        $challenge = Challenge::factory()->system()->easy()->create();
        $completion = ChallengeCompletion::factory()->create([
            'challenge_id' => $challenge->id,
            'event_id' => $event->id,
            'challenger_profile_id' => $challenger->id,
            'verifier_profile_id' => $verifier->id,
            'status' => ChallengeCompletionStatus::Pending,
            'points_earned' => 0,
        ]);

        $this->actingAs($verifier)
            ->postJson("/api/v1/challenge-completions/{$completion->id}/verify");

        $badge = Badge::where('milestone_type', BadgeMilestoneType::FirstChallenge->value)->first();
        $this->assertDatabaseHas('badge_awards', [
            'badge_id' => $badge->id,
            'profile_id' => $challenger->id,
        ]);
    }

    public function test_badge_not_awarded_twice(): void
    {
        $owner = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $owner->id]);
        $event = Event::factory()->forProfile($owner)->create([
            'is_active' => true,
            'checkin_token' => 'token-a',
        ]);
        $event2 = Event::factory()->forProfile($owner)->create([
            'is_active' => true,
            'checkin_token' => 'token-b',
        ]);

        $attendee = Profile::factory()->attendee()->create();
        AttendeeProfile::factory()->create(['profile_id' => $attendee->id]);

        // First checkin
        $this->actingAs($attendee)
            ->postJson('/api/v1/checkin', ['token' => 'token-a']);

        // Second checkin (different event)
        $this->actingAs($attendee)
            ->postJson('/api/v1/checkin', ['token' => 'token-b']);

        $badge = Badge::where('milestone_type', BadgeMilestoneType::FirstCheckin->value)->first();
        $this->assertEquals(1, BadgeAward::where('badge_id', $badge->id)->where('profile_id', $attendee->id)->count());
    }

    public function test_badge_award_creates_notification(): void
    {
        $owner = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $owner->id]);
        $event = Event::factory()->forProfile($owner)->create([
            'is_active' => true,
            'checkin_token' => 'notify-token',
        ]);

        $attendee = Profile::factory()->attendee()->create();
        AttendeeProfile::factory()->create(['profile_id' => $attendee->id]);

        $this->actingAs($attendee)
            ->postJson('/api/v1/checkin', ['token' => 'notify-token']);

        $this->assertDatabaseHas('notifications', [
            'profile_id' => $attendee->id,
            'type' => 'badge_awarded',
        ]);
    }

    public function test_non_attendee_does_not_get_badges(): void
    {
        $owner = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $owner->id]);
        $event = Event::factory()->forProfile($owner)->create([
            'is_active' => true,
            'checkin_token' => 'business-token',
        ]);

        // Business user checking in (unusual but testing the guard)
        $this->actingAs($owner)
            ->postJson('/api/v1/checkin', ['token' => 'business-token']);

        $this->assertEquals(0, BadgeAward::where('profile_id', $owner->id)->count());
    }

    public function test_events_attended_5_awards_loyal_attendee_badge(): void
    {
        $attendee = Profile::factory()->attendee()->create();
        AttendeeProfile::factory()->create([
            'profile_id' => $attendee->id,
            'total_events_attended' => 4,
        ]);

        $owner = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $owner->id]);
        $event = Event::factory()->forProfile($owner)->create([
            'is_active' => true,
            'checkin_token' => 'loyalty-token',
        ]);

        $this->actingAs($attendee)
            ->postJson('/api/v1/checkin', ['token' => 'loyalty-token']);

        $badge = Badge::where('milestone_type', BadgeMilestoneType::LoyalAttendee->value)->first();
        $this->assertDatabaseHas('badge_awards', [
            'badge_id' => $badge->id,
            'profile_id' => $attendee->id,
        ]);
    }

    public function test_points_500_awards_point_hunter_badge(): void
    {
        $owner = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $owner->id]);
        $event = Event::factory()->forProfile($owner)->create([
            'is_active' => true,
            'max_challenges_per_attendee' => 10,
        ]);

        $challenger = Profile::factory()->attendee()->create();
        AttendeeProfile::factory()->create([
            'profile_id' => $challenger->id,
            'total_points' => 495,
            'total_challenges_completed' => 30,
        ]);
        $verifier = Profile::factory()->attendee()->create();
        AttendeeProfile::factory()->create(['profile_id' => $verifier->id]);

        EventCheckin::factory()->forEvent($event)->forProfile($challenger)->create();
        EventCheckin::factory()->forEvent($event)->forProfile($verifier)->create();

        $challenge = Challenge::factory()->system()->easy()->create(); // 5 points
        $completion = ChallengeCompletion::factory()->create([
            'challenge_id' => $challenge->id,
            'event_id' => $event->id,
            'challenger_profile_id' => $challenger->id,
            'verifier_profile_id' => $verifier->id,
            'status' => ChallengeCompletionStatus::Pending,
            'points_earned' => 0,
        ]);

        $this->actingAs($verifier)
            ->postJson("/api/v1/challenge-completions/{$completion->id}/verify");

        $badge = Badge::where('milestone_type', BadgeMilestoneType::PointHunter->value)->first();
        $this->assertDatabaseHas('badge_awards', [
            'badge_id' => $badge->id,
            'profile_id' => $challenger->id,
        ]);
    }
}
