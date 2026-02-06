<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\ChallengeCompletionStatus;
use App\Models\AttendeeProfile;
use App\Models\BusinessProfile;
use App\Models\Challenge;
use App\Models\ChallengeCompletion;
use App\Models\Event;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class LeaderboardTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Create an event with a business owner.
     */
    private function createEvent(): Event
    {
        $owner = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $owner->id]);

        return Event::factory()->forProfile($owner)->create([
            'is_active' => true,
            'max_challenges_per_attendee' => 10,
        ]);
    }

    /**
     * Create an attendee profile with an associated AttendeeProfile record.
     */
    private function createAttendee(int $totalPoints = 0): Profile
    {
        $profile = Profile::factory()->attendee()->create();
        AttendeeProfile::factory()->create([
            'profile_id' => $profile->id,
            'total_points' => $totalPoints,
        ]);

        return $profile;
    }

    /**
     * Create a verified challenge completion for a profile on an event.
     */
    private function createVerifiedCompletion(Profile $profile, Event $event, int $points): ChallengeCompletion
    {
        $challenge = Challenge::factory()->system()->create(['points' => $points]);

        return ChallengeCompletion::factory()->verified()->create([
            'challenge_id' => $challenge->id,
            'event_id' => $event->id,
            'challenger_profile_id' => $profile->id,
            'points_earned' => $points,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Event Leaderboard (GET /api/v1/events/{event}/leaderboard)
    |--------------------------------------------------------------------------
    */

    public function test_event_leaderboard_returns_ranked_profiles_by_points(): void
    {
        $event = $this->createEvent();

        $attendee1 = $this->createAttendee();
        $attendee2 = $this->createAttendee();
        $attendee3 = $this->createAttendee();

        // Attendee 1: 30 points (2 completions)
        $this->createVerifiedCompletion($attendee1, $event, 20);
        $this->createVerifiedCompletion($attendee1, $event, 10);

        // Attendee 2: 50 points
        $this->createVerifiedCompletion($attendee2, $event, 50);

        // Attendee 3: 15 points
        $this->createVerifiedCompletion($attendee3, $event, 15);

        $response = $this->actingAs($attendee1)
            ->getJson("/api/v1/events/{$event->id}/leaderboard");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(3, 'data.leaderboard');

        $leaderboard = $response->json('data.leaderboard');

        // Verify ordering: attendee2 (50), attendee1 (30), attendee3 (15)
        $this->assertEquals($attendee2->id, $leaderboard[0]['profile_id']);
        $this->assertEquals(50, $leaderboard[0]['total_points']);
        $this->assertEquals(1, $leaderboard[0]['rank']);

        $this->assertEquals($attendee1->id, $leaderboard[1]['profile_id']);
        $this->assertEquals(30, $leaderboard[1]['total_points']);
        $this->assertEquals(2, $leaderboard[1]['rank']);

        $this->assertEquals($attendee3->id, $leaderboard[2]['profile_id']);
        $this->assertEquals(15, $leaderboard[2]['total_points']);
        $this->assertEquals(3, $leaderboard[2]['rank']);
    }

    public function test_event_leaderboard_returns_empty_when_no_verified_completions(): void
    {
        $event = $this->createEvent();
        $attendee = $this->createAttendee();

        // Create a pending completion (not verified)
        $challenge = Challenge::factory()->system()->create(['points' => 10]);
        ChallengeCompletion::factory()->create([
            'challenge_id' => $challenge->id,
            'event_id' => $event->id,
            'challenger_profile_id' => $attendee->id,
            'status' => ChallengeCompletionStatus::Pending,
            'points_earned' => 0,
        ]);

        $response = $this->actingAs($attendee)
            ->getJson("/api/v1/events/{$event->id}/leaderboard");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(0, 'data.leaderboard')
            ->assertJsonPath('data.my_rank', null);
    }

    public function test_event_leaderboard_excludes_other_events(): void
    {
        $event1 = $this->createEvent();
        $event2 = $this->createEvent();

        $attendee = $this->createAttendee();

        // Points in event2 should not appear in event1 leaderboard
        $this->createVerifiedCompletion($attendee, $event2, 100);

        $response = $this->actingAs($attendee)
            ->getJson("/api/v1/events/{$event1->id}/leaderboard");

        $response->assertStatus(200)
            ->assertJsonCount(0, 'data.leaderboard');
    }

    /*
    |--------------------------------------------------------------------------
    | Global Leaderboard (GET /api/v1/leaderboard/global)
    |--------------------------------------------------------------------------
    */

    public function test_global_leaderboard_returns_ranked_attendees_by_total_points(): void
    {
        $attendee1 = $this->createAttendee(100);
        $attendee2 = $this->createAttendee(200);
        $attendee3 = $this->createAttendee(50);

        $response = $this->actingAs($attendee1)
            ->getJson('/api/v1/leaderboard/global');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(3, 'data.leaderboard');

        $leaderboard = $response->json('data.leaderboard');

        // Verify ordering: attendee2 (200), attendee1 (100), attendee3 (50)
        $this->assertEquals($attendee2->id, $leaderboard[0]['profile_id']);
        $this->assertEquals(200, $leaderboard[0]['total_points']);
        $this->assertEquals(1, $leaderboard[0]['rank']);

        $this->assertEquals($attendee1->id, $leaderboard[1]['profile_id']);
        $this->assertEquals(100, $leaderboard[1]['total_points']);
        $this->assertEquals(2, $leaderboard[1]['rank']);

        $this->assertEquals($attendee3->id, $leaderboard[2]['profile_id']);
        $this->assertEquals(50, $leaderboard[2]['total_points']);
        $this->assertEquals(3, $leaderboard[2]['rank']);
    }

    public function test_global_leaderboard_excludes_profiles_with_zero_points(): void
    {
        $attendeeWithPoints = $this->createAttendee(100);
        $this->createAttendee(0); // zero points

        $response = $this->actingAs($attendeeWithPoints)
            ->getJson('/api/v1/leaderboard/global');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.leaderboard')
            ->assertJsonPath('data.leaderboard.0.profile_id', $attendeeWithPoints->id);
    }

    /*
    |--------------------------------------------------------------------------
    | My Event Rank
    |--------------------------------------------------------------------------
    */

    public function test_my_event_rank_returns_correct_rank(): void
    {
        $event = $this->createEvent();

        $topAttendee = $this->createAttendee();
        $middleAttendee = $this->createAttendee();
        $bottomAttendee = $this->createAttendee();

        $this->createVerifiedCompletion($topAttendee, $event, 50);
        $this->createVerifiedCompletion($middleAttendee, $event, 30);
        $this->createVerifiedCompletion($bottomAttendee, $event, 10);

        // Check rank for middle attendee
        $response = $this->actingAs($middleAttendee)
            ->getJson("/api/v1/events/{$event->id}/leaderboard");

        $response->assertStatus(200);

        $myRank = $response->json('data.my_rank');
        $this->assertNotNull($myRank);
        $this->assertEquals($middleAttendee->id, $myRank['profile_id']);
        $this->assertEquals(30, $myRank['total_points']);
        $this->assertEquals(2, $myRank['rank']);
    }

    public function test_my_event_rank_returns_null_when_user_has_no_points(): void
    {
        $event = $this->createEvent();
        $attendee = $this->createAttendee();

        // No completions for this user on this event
        $response = $this->actingAs($attendee)
            ->getJson("/api/v1/events/{$event->id}/leaderboard");

        $response->assertStatus(200)
            ->assertJsonPath('data.my_rank', null);
    }

    /*
    |--------------------------------------------------------------------------
    | My Global Rank
    |--------------------------------------------------------------------------
    */

    public function test_my_global_rank_returns_correct_rank(): void
    {
        $this->createAttendee(200);
        $middleAttendee = $this->createAttendee(100);
        $this->createAttendee(50);

        $response = $this->actingAs($middleAttendee)
            ->getJson('/api/v1/leaderboard/global');

        $response->assertStatus(200);

        $myRank = $response->json('data.my_rank');
        $this->assertNotNull($myRank);
        $this->assertEquals($middleAttendee->id, $myRank['profile_id']);
        $this->assertEquals(100, $myRank['total_points']);
        $this->assertEquals(2, $myRank['rank']);
    }

    public function test_my_global_rank_returns_null_for_zero_point_attendee(): void
    {
        $attendee = $this->createAttendee(0);

        $response = $this->actingAs($attendee)
            ->getJson('/api/v1/leaderboard/global');

        $response->assertStatus(200)
            ->assertJsonPath('data.my_rank', null);
    }

    public function test_my_global_rank_returns_null_for_non_attendee(): void
    {
        $businessProfile = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $businessProfile->id]);

        $response = $this->actingAs($businessProfile)
            ->getJson('/api/v1/leaderboard/global');

        $response->assertStatus(200)
            ->assertJsonPath('data.my_rank', null);
    }

    /*
    |--------------------------------------------------------------------------
    | Limit Parameter
    |--------------------------------------------------------------------------
    */

    public function test_leaderboard_respects_limit_parameter(): void
    {
        $event = $this->createEvent();

        // Create 5 attendees with verified completions
        for ($i = 0; $i < 5; $i++) {
            $attendee = $this->createAttendee();
            $this->createVerifiedCompletion($attendee, $event, ($i + 1) * 10);
        }

        $requestor = $this->createAttendee();

        $response = $this->actingAs($requestor)
            ->getJson("/api/v1/events/{$event->id}/leaderboard?limit=3");

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data.leaderboard');
    }

    public function test_global_leaderboard_respects_limit_parameter(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->createAttendee(($i + 1) * 10);
        }

        $requestor = $this->createAttendee(1);

        $response = $this->actingAs($requestor)
            ->getJson('/api/v1/leaderboard/global?limit=2');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data.leaderboard');
    }

    /*
    |--------------------------------------------------------------------------
    | Response Structure
    |--------------------------------------------------------------------------
    */

    public function test_event_leaderboard_returns_correct_structure(): void
    {
        $event = $this->createEvent();
        $attendee = $this->createAttendee();
        $this->createVerifiedCompletion($attendee, $event, 25);

        $response = $this->actingAs($attendee)
            ->getJson("/api/v1/events/{$event->id}/leaderboard");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'leaderboard' => [
                        '*' => [
                            'profile_id',
                            'display_name',
                            'profile_photo',
                            'total_points',
                            'rank',
                        ],
                    ],
                    'my_rank' => [
                        'profile_id',
                        'total_points',
                        'rank',
                    ],
                ],
            ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    */

    public function test_unauthenticated_gets_401(): void
    {
        $event = $this->createEvent();

        $this->getJson("/api/v1/events/{$event->id}/leaderboard")
            ->assertStatus(401);

        $this->getJson('/api/v1/leaderboard/global')
            ->assertStatus(401);
    }
}
