<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\ChallengeDifficulty;
use App\Models\BusinessProfile;
use App\Models\Challenge;
use App\Models\CommunityProfile;
use App\Models\Event;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ChallengeTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Create a business profile with its extended profile record.
     */
    private function createBusinessProfile(): Profile
    {
        $profile = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $profile->id]);

        return $profile;
    }

    /**
     * Create a community profile with its extended profile record.
     */
    private function createCommunityProfile(): Profile
    {
        $profile = Profile::factory()->community()->create();
        CommunityProfile::factory()->create(['profile_id' => $profile->id]);

        return $profile;
    }

    /*
    |--------------------------------------------------------------------------
    | List Challenges (GET /api/v1/events/{event}/challenges)
    |--------------------------------------------------------------------------
    */

    public function test_list_challenges_returns_system_and_event_challenges(): void
    {
        $owner = $this->createBusinessProfile();
        $event = Event::factory()->forProfile($owner)->create();

        // Create system challenges
        Challenge::factory()->system()->count(2)->create();

        // Create custom challenges for this event
        Challenge::factory()->forEvent($event)->count(3)->create();

        // Create custom challenges for another event (should NOT appear)
        $otherEvent = Event::factory()->create();
        Challenge::factory()->forEvent($otherEvent)->count(2)->create();

        $response = $this->actingAs($owner)
            ->getJson("/api/v1/events/{$event->id}/challenges");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(5, 'data.challenges')
            ->assertJsonStructure([
                'success',
                'data' => [
                    'challenges' => [
                        '*' => [
                            'id',
                            'name',
                            'description',
                            'difficulty',
                            'points',
                            'is_system',
                            'event_id',
                            'created_at',
                            'updated_at',
                        ],
                    ],
                    'pagination' => [
                        'current_page',
                        'total_pages',
                        'total_count',
                        'per_page',
                    ],
                ],
            ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Create Challenge (POST /api/v1/events/{event}/challenges)
    |--------------------------------------------------------------------------
    */

    public function test_event_owner_can_create_custom_challenge(): void
    {
        $owner = $this->createBusinessProfile();
        $event = Event::factory()->forProfile($owner)->create();

        $response = $this->actingAs($owner)
            ->postJson("/api/v1/events/{$event->id}/challenges", [
                'name' => 'Custom Test Challenge',
                'description' => 'A fun custom challenge for this event.',
                'difficulty' => 'medium',
                'points' => 20,
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Custom Test Challenge')
            ->assertJsonPath('data.description', 'A fun custom challenge for this event.')
            ->assertJsonPath('data.difficulty', 'medium')
            ->assertJsonPath('data.points', 20)
            ->assertJsonPath('data.is_system', false)
            ->assertJsonPath('data.event_id', $event->id);

        $this->assertDatabaseHas('challenges', [
            'name' => 'Custom Test Challenge',
            'event_id' => $event->id,
            'is_system' => false,
            'points' => 20,
        ]);
    }

    public function test_non_owner_cannot_create_challenge(): void
    {
        $owner = $this->createBusinessProfile();
        $nonOwner = $this->createCommunityProfile();
        $event = Event::factory()->forProfile($owner)->create();

        $response = $this->actingAs($nonOwner)
            ->postJson("/api/v1/events/{$event->id}/challenges", [
                'name' => 'Unauthorized Challenge',
                'difficulty' => 'easy',
            ]);

        $response->assertStatus(403);
    }

    /*
    |--------------------------------------------------------------------------
    | Update Challenge (PUT /api/v1/challenges/{challenge})
    |--------------------------------------------------------------------------
    */

    public function test_event_owner_can_update_custom_challenge(): void
    {
        $owner = $this->createBusinessProfile();
        $event = Event::factory()->forProfile($owner)->create();
        $challenge = Challenge::factory()->forEvent($event)->create([
            'name' => 'Original Name',
            'points' => 10,
        ]);

        $response = $this->actingAs($owner)
            ->putJson("/api/v1/challenges/{$challenge->id}", [
                'name' => 'Updated Challenge Name',
                'points' => 25,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'Updated Challenge Name')
            ->assertJsonPath('data.points', 25);

        $this->assertDatabaseHas('challenges', [
            'id' => $challenge->id,
            'name' => 'Updated Challenge Name',
            'points' => 25,
        ]);
    }

    public function test_cannot_update_system_challenge(): void
    {
        $owner = $this->createBusinessProfile();
        $systemChallenge = Challenge::factory()->system()->create();

        $response = $this->actingAs($owner)
            ->putJson("/api/v1/challenges/{$systemChallenge->id}", [
                'name' => 'Hacked System Challenge',
            ]);

        $response->assertStatus(403);
    }

    /*
    |--------------------------------------------------------------------------
    | Delete Challenge (DELETE /api/v1/challenges/{challenge})
    |--------------------------------------------------------------------------
    */

    public function test_event_owner_can_delete_custom_challenge(): void
    {
        $owner = $this->createBusinessProfile();
        $event = Event::factory()->forProfile($owner)->create();
        $challenge = Challenge::factory()->forEvent($event)->create();

        $response = $this->actingAs($owner)
            ->deleteJson("/api/v1/challenges/{$challenge->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('challenges', ['id' => $challenge->id]);
    }

    public function test_cannot_delete_system_challenge(): void
    {
        $owner = $this->createBusinessProfile();
        $systemChallenge = Challenge::factory()->system()->create();

        $response = $this->actingAs($owner)
            ->deleteJson("/api/v1/challenges/{$systemChallenge->id}");

        $response->assertStatus(403);

        $this->assertDatabaseHas('challenges', ['id' => $systemChallenge->id]);
    }

    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    public function test_create_challenge_validation_errors(): void
    {
        $owner = $this->createBusinessProfile();
        $event = Event::factory()->forProfile($owner)->create();

        $response = $this->actingAs($owner)
            ->postJson("/api/v1/events/{$event->id}/challenges", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'difficulty']);
    }

    public function test_default_points_from_difficulty(): void
    {
        $owner = $this->createBusinessProfile();
        $event = Event::factory()->forProfile($owner)->create();

        // Create challenge without specifying points - should default to difficulty's points
        $response = $this->actingAs($owner)
            ->postJson("/api/v1/events/{$event->id}/challenges", [
                'name' => 'Easy Default Points',
                'difficulty' => 'easy',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.points', ChallengeDifficulty::Easy->points());

        // Also test medium
        $response = $this->actingAs($owner)
            ->postJson("/api/v1/events/{$event->id}/challenges", [
                'name' => 'Hard Default Points',
                'difficulty' => 'hard',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.points', ChallengeDifficulty::Hard->points());
    }

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    */

    public function test_unauthenticated_gets_401(): void
    {
        $event = Event::factory()->create();

        $this->getJson("/api/v1/events/{$event->id}/challenges")
            ->assertStatus(401);

        $this->postJson("/api/v1/events/{$event->id}/challenges", [
            'name' => 'Test',
            'difficulty' => 'easy',
        ])->assertStatus(401);

        $challenge = Challenge::factory()->create();

        $this->putJson("/api/v1/challenges/{$challenge->id}", [
            'name' => 'Test',
        ])->assertStatus(401);

        $this->deleteJson("/api/v1/challenges/{$challenge->id}")
            ->assertStatus(401);
    }
}
