<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\BusinessProfile;
use App\Models\Challenge;
use App\Models\CommunityProfile;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class SystemChallengeTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function createBusinessProfile(): Profile
    {
        $profile = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $profile->id]);

        return $profile;
    }

    private function createCommunityProfile(): Profile
    {
        $profile = Profile::factory()->community()->create();
        CommunityProfile::factory()->create(['profile_id' => $profile->id]);

        return $profile;
    }

    /*
    |--------------------------------------------------------------------------
    | List System Challenges (GET /api/v1/challenges/system)
    |--------------------------------------------------------------------------
    */

    public function test_authenticated_user_can_list_system_challenges(): void
    {
        $profile = $this->createBusinessProfile();

        Challenge::factory()->system()->count(5)->create();
        // Non-system challenges should not appear
        Challenge::factory()->count(3)->create();

        $response = $this->actingAs($profile)
            ->getJson('/api/v1/challenges/system');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(5, 'data')
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'description',
                        'difficulty',
                        'points',
                        'is_system',
                        'category',
                        'event_id',
                        'created_at',
                        'updated_at',
                    ],
                ],
            ]);

        // All returned should be system challenges
        foreach ($response->json('data') as $challenge) {
            $this->assertTrue($challenge['is_system']);
        }
    }

    public function test_community_profile_can_list_system_challenges(): void
    {
        $profile = $this->createCommunityProfile();

        Challenge::factory()->system()->count(3)->create();

        $response = $this->actingAs($profile)
            ->getJson('/api/v1/challenges/system');

        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    public function test_system_challenges_ordered_by_category_then_difficulty(): void
    {
        $profile = $this->createBusinessProfile();

        Challenge::factory()->system()->create([
            'category' => 'ice_breaker',
            'difficulty' => 'hard',
        ]);
        Challenge::factory()->system()->create([
            'category' => 'ice_breaker',
            'difficulty' => 'easy',
        ]);
        Challenge::factory()->system()->create([
            'category' => 'cultural_exchange',
            'difficulty' => 'medium',
        ]);

        $response = $this->actingAs($profile)
            ->getJson('/api/v1/challenges/system');

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertCount(3, $data);

        // cultural_exchange comes before ice_breaker alphabetically
        $this->assertEquals('cultural_exchange', $data[0]['category']);
        $this->assertEquals('ice_breaker', $data[1]['category']);
        $this->assertEquals('ice_breaker', $data[2]['category']);

        // Within same category, easy comes before hard
        $this->assertEquals('easy', $data[1]['difficulty']);
        $this->assertEquals('hard', $data[2]['difficulty']);
    }

    public function test_returns_empty_when_no_system_challenges(): void
    {
        $profile = $this->createBusinessProfile();

        // Only create non-system challenges
        Challenge::factory()->count(3)->create();

        $response = $this->actingAs($profile)
            ->getJson('/api/v1/challenges/system');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(0, 'data');
    }

    public function test_unauthenticated_gets_401(): void
    {
        $this->getJson('/api/v1/challenges/system')
            ->assertStatus(401);
    }
}
