<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\ChallengeCompletionStatus;
use App\Models\AttendeeProfile;
use App\Models\BusinessProfile;
use App\Models\Challenge;
use App\Models\ChallengeCompletion;
use App\Models\Event;
use App\Models\EventCheckin;
use App\Models\EventReward;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class GamificationNotificationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_challenge_verification_creates_notification(): void
    {
        $owner = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $owner->id]);
        $event = Event::factory()->forProfile($owner)->create([
            'is_active' => true,
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
            ->postJson("/api/v1/challenge-completions/{$completion->id}/verify")
            ->assertStatus(200);

        $this->assertDatabaseHas('notifications', [
            'profile_id' => $challenger->id,
            'type' => 'challenge_verified',
        ]);
    }

    public function test_reward_won_creates_notification(): void
    {
        $owner = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $owner->id]);
        $event = Event::factory()->forProfile($owner)->create([
            'is_active' => true,
            'max_challenges_per_attendee' => 10,
        ]);

        $challenger = Profile::factory()->attendee()->create();
        AttendeeProfile::factory()->create(['profile_id' => $challenger->id]);
        $verifier = Profile::factory()->attendee()->create();
        AttendeeProfile::factory()->create(['profile_id' => $verifier->id]);

        EventCheckin::factory()->forEvent($event)->forProfile($challenger)->create();
        EventCheckin::factory()->forEvent($event)->forProfile($verifier)->create();

        $challenge = Challenge::factory()->system()->easy()->create();
        $completion = ChallengeCompletion::factory()->verified()->create([
            'challenge_id' => $challenge->id,
            'event_id' => $event->id,
            'challenger_profile_id' => $challenger->id,
            'verifier_profile_id' => $verifier->id,
            'points_earned' => $challenge->points,
        ]);

        EventReward::factory()->forEvent($event)->highProbability(1.0)->create();

        $this->actingAs($challenger)
            ->postJson('/api/v1/rewards/spin', [
                'challenge_completion_id' => $completion->id,
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.won', true);

        $this->assertDatabaseHas('notifications', [
            'profile_id' => $challenger->id,
            'type' => 'reward_won',
        ]);
    }

    public function test_challenge_reject_does_not_create_notification(): void
    {
        $owner = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $owner->id]);
        $event = Event::factory()->forProfile($owner)->create([
            'is_active' => true,
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
            ->postJson("/api/v1/challenge-completions/{$completion->id}/reject")
            ->assertStatus(200);

        $this->assertDatabaseMissing('notifications', [
            'profile_id' => $challenger->id,
            'type' => 'challenge_verified',
        ]);
    }

    public function test_spin_no_win_does_not_create_reward_notification(): void
    {
        $owner = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $owner->id]);
        $event = Event::factory()->forProfile($owner)->create([
            'is_active' => true,
            'max_challenges_per_attendee' => 10,
        ]);

        $challenger = Profile::factory()->attendee()->create();
        AttendeeProfile::factory()->create(['profile_id' => $challenger->id]);
        $verifier = Profile::factory()->attendee()->create();
        AttendeeProfile::factory()->create(['profile_id' => $verifier->id]);

        EventCheckin::factory()->forEvent($event)->forProfile($challenger)->create();
        EventCheckin::factory()->forEvent($event)->forProfile($verifier)->create();

        $challenge = Challenge::factory()->system()->easy()->create();
        $completion = ChallengeCompletion::factory()->verified()->create([
            'challenge_id' => $challenge->id,
            'event_id' => $event->id,
            'challenger_profile_id' => $challenger->id,
            'verifier_profile_id' => $verifier->id,
            'points_earned' => $challenge->points,
        ]);

        // No rewards for this event -> spin will miss
        $this->actingAs($challenger)
            ->postJson('/api/v1/rewards/spin', [
                'challenge_completion_id' => $completion->id,
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.won', false);

        $this->assertDatabaseMissing('notifications', [
            'profile_id' => $challenger->id,
            'type' => 'reward_won',
        ]);
    }
}
