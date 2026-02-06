<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\AttendeeProfile;
use App\Models\BusinessProfile;
use App\Models\Event;
use App\Models\EventReward;
use App\Models\Profile;
use App\Models\RewardClaim;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class GamificationStatsTest extends TestCase
{
    use LazilyRefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | My Stats (GET /api/v1/me/gamification-stats)
    |--------------------------------------------------------------------------
    */

    public function test_my_stats_returns_attendee_stats(): void
    {
        $attendee = Profile::factory()->attendee()->create();
        AttendeeProfile::factory()->create([
            'profile_id' => $attendee->id,
            'total_points' => 150,
            'total_challenges_completed' => 10,
            'total_events_attended' => 3,
            'global_rank' => 5,
        ]);

        $response = $this->actingAs($attendee)
            ->getJson('/api/v1/me/gamification-stats');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total_points', 150)
            ->assertJsonPath('data.total_challenges_completed', 10)
            ->assertJsonPath('data.total_events_attended', 3)
            ->assertJsonPath('data.global_rank', 5);
    }

    public function test_my_stats_returns_zero_for_new_attendee(): void
    {
        $attendee = Profile::factory()->attendee()->create();
        AttendeeProfile::factory()->create(['profile_id' => $attendee->id]);

        $response = $this->actingAs($attendee)
            ->getJson('/api/v1/me/gamification-stats');

        $response->assertStatus(200)
            ->assertJsonPath('data.total_points', 0)
            ->assertJsonPath('data.total_challenges_completed', 0)
            ->assertJsonPath('data.total_events_attended', 0)
            ->assertJsonPath('data.global_rank', null);
    }

    public function test_my_stats_returns_zero_for_non_attendee(): void
    {
        $business = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $business->id]);

        $response = $this->actingAs($business)
            ->getJson('/api/v1/me/gamification-stats');

        $response->assertStatus(200)
            ->assertJsonPath('data.total_points', 0)
            ->assertJsonPath('data.badges_count', 0)
            ->assertJsonPath('data.rewards_count', 0);
    }

    public function test_my_stats_includes_rewards_count(): void
    {
        $attendee = Profile::factory()->attendee()->create();
        AttendeeProfile::factory()->create(['profile_id' => $attendee->id]);

        $owner = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $owner->id]);
        $event = Event::factory()->forProfile($owner)->create();
        $reward = EventReward::factory()->forEvent($event)->create();

        RewardClaim::factory()->count(3)->create([
            'event_reward_id' => $reward->id,
            'profile_id' => $attendee->id,
        ]);

        $response = $this->actingAs($attendee)
            ->getJson('/api/v1/me/gamification-stats');

        $response->assertStatus(200)
            ->assertJsonPath('data.rewards_count', 3);
    }

    public function test_my_stats_returns_correct_structure(): void
    {
        $attendee = Profile::factory()->attendee()->create();
        AttendeeProfile::factory()->create(['profile_id' => $attendee->id]);

        $response = $this->actingAs($attendee)
            ->getJson('/api/v1/me/gamification-stats');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'total_points',
                    'total_challenges_completed',
                    'total_events_attended',
                    'global_rank',
                    'badges_count',
                    'rewards_count',
                ],
            ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Game Card (GET /api/v1/profiles/{profile}/game-card)
    |--------------------------------------------------------------------------
    */

    public function test_game_card_returns_public_profile_data(): void
    {
        $viewer = Profile::factory()->attendee()->create();
        AttendeeProfile::factory()->create(['profile_id' => $viewer->id]);

        $target = Profile::factory()->attendee()->create();
        AttendeeProfile::factory()->create([
            'profile_id' => $target->id,
            'total_points' => 200,
        ]);

        $response = $this->actingAs($viewer)
            ->getJson("/api/v1/profiles/{$target->id}/game-card");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'profile' => ['id', 'email', 'avatar_url', 'user_type'],
                    'stats' => [
                        'total_points',
                        'total_challenges_completed',
                        'total_events_attended',
                        'global_rank',
                        'badges_count',
                        'rewards_count',
                    ],
                    'recent_badges',
                ],
            ]);
    }

    public function test_game_card_returns_correct_profile_fields(): void
    {
        $viewer = Profile::factory()->attendee()->create();
        AttendeeProfile::factory()->create(['profile_id' => $viewer->id]);

        $target = Profile::factory()->attendee()->create([
            'email' => 'target@example.com',
            'avatar_url' => 'https://example.com/avatar.jpg',
        ]);
        AttendeeProfile::factory()->create([
            'profile_id' => $target->id,
            'total_points' => 300,
            'total_challenges_completed' => 5,
            'total_events_attended' => 2,
            'global_rank' => 1,
        ]);

        $response = $this->actingAs($viewer)
            ->getJson("/api/v1/profiles/{$target->id}/game-card");

        $response->assertStatus(200)
            ->assertJsonPath('data.profile.id', $target->id)
            ->assertJsonPath('data.profile.email', 'target@example.com')
            ->assertJsonPath('data.profile.avatar_url', 'https://example.com/avatar.jpg')
            ->assertJsonPath('data.profile.user_type', 'attendee')
            ->assertJsonPath('data.stats.total_points', 300)
            ->assertJsonPath('data.stats.total_challenges_completed', 5)
            ->assertJsonPath('data.stats.total_events_attended', 2)
            ->assertJsonPath('data.stats.global_rank', 1);
    }

    public function test_game_card_returns_zero_stats_for_profile_without_attendee_profile(): void
    {
        $viewer = Profile::factory()->attendee()->create();
        AttendeeProfile::factory()->create(['profile_id' => $viewer->id]);

        $business = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $business->id]);

        $response = $this->actingAs($viewer)
            ->getJson("/api/v1/profiles/{$business->id}/game-card");

        $response->assertStatus(200)
            ->assertJsonPath('data.stats.total_points', 0)
            ->assertJsonPath('data.stats.badges_count', 0)
            ->assertJsonPath('data.stats.rewards_count', 0)
            ->assertJsonPath('data.recent_badges', []);
    }

    public function test_game_card_returns_404_for_non_existent_profile(): void
    {
        $viewer = Profile::factory()->attendee()->create();
        AttendeeProfile::factory()->create(['profile_id' => $viewer->id]);

        $response = $this->actingAs($viewer)
            ->getJson('/api/v1/profiles/00000000-0000-0000-0000-000000000000/game-card');

        $response->assertStatus(404);
    }

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    */

    public function test_unauthenticated_returns_401_for_my_stats(): void
    {
        $this->getJson('/api/v1/me/gamification-stats')
            ->assertStatus(401);
    }

    public function test_unauthenticated_returns_401_for_game_card(): void
    {
        $profile = Profile::factory()->attendee()->create();

        $this->getJson("/api/v1/profiles/{$profile->id}/game-card")
            ->assertStatus(401);
    }
}
