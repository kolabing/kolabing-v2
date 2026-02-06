<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Badge;
use App\Models\BadgeAward;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class BadgeTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\BadgeSeeder::class);
    }

    /*
    |--------------------------------------------------------------------------
    | List All Badges (GET /api/v1/badges)
    |--------------------------------------------------------------------------
    */

    public function test_list_all_badges_returns_system_badges(): void
    {
        $attendee = Profile::factory()->attendee()->create();

        $response = $this->actingAs($attendee)
            ->getJson('/api/v1/badges');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(9, 'data.badges');
    }

    public function test_badges_include_correct_structure(): void
    {
        $attendee = Profile::factory()->attendee()->create();

        $response = $this->actingAs($attendee)
            ->getJson('/api/v1/badges');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'badges' => [
                        '*' => ['id', 'name', 'description', 'icon', 'milestone_type', 'milestone_value'],
                    ],
                ],
            ]);
    }

    /*
    |--------------------------------------------------------------------------
    | My Badges (GET /api/v1/me/badges)
    |--------------------------------------------------------------------------
    */

    public function test_my_badges_returns_awarded_badges(): void
    {
        $attendee = Profile::factory()->attendee()->create();
        $badge = Badge::first();

        BadgeAward::factory()->create([
            'badge_id' => $badge->id,
            'profile_id' => $attendee->id,
        ]);

        $response = $this->actingAs($attendee)
            ->getJson('/api/v1/me/badges');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.badges')
            ->assertJsonStructure([
                'success',
                'data' => [
                    'badges' => [
                        '*' => ['id', 'badge', 'awarded_at'],
                    ],
                ],
            ]);
    }

    public function test_my_badges_includes_badge_data(): void
    {
        $attendee = Profile::factory()->attendee()->create();
        $badge = Badge::first();

        BadgeAward::factory()->create([
            'badge_id' => $badge->id,
            'profile_id' => $attendee->id,
        ]);

        $response = $this->actingAs($attendee)
            ->getJson('/api/v1/me/badges');

        $response->assertStatus(200)
            ->assertJsonPath('data.badges.0.badge.id', $badge->id)
            ->assertJsonPath('data.badges.0.badge.name', $badge->name);
    }

    public function test_my_badges_returns_empty_when_none(): void
    {
        $attendee = Profile::factory()->attendee()->create();

        $response = $this->actingAs($attendee)
            ->getJson('/api/v1/me/badges');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(0, 'data.badges');
    }

    public function test_my_badges_only_returns_own_badges(): void
    {
        $attendee1 = Profile::factory()->attendee()->create();
        $attendee2 = Profile::factory()->attendee()->create();
        $badge = Badge::first();

        // Award badge to attendee2 only
        BadgeAward::factory()->create([
            'badge_id' => $badge->id,
            'profile_id' => $attendee2->id,
        ]);

        $response = $this->actingAs($attendee1)
            ->getJson('/api/v1/me/badges');

        $response->assertStatus(200)
            ->assertJsonCount(0, 'data.badges');
    }

    public function test_my_badges_ordered_by_awarded_at_desc(): void
    {
        $attendee = Profile::factory()->attendee()->create();
        $badges = Badge::take(2)->get();

        $olderAward = BadgeAward::factory()->create([
            'badge_id' => $badges[0]->id,
            'profile_id' => $attendee->id,
            'awarded_at' => now()->subDays(5),
        ]);

        $newerAward = BadgeAward::factory()->create([
            'badge_id' => $badges[1]->id,
            'profile_id' => $attendee->id,
            'awarded_at' => now(),
        ]);

        $response = $this->actingAs($attendee)
            ->getJson('/api/v1/me/badges');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data.badges');

        $returnedBadges = $response->json('data.badges');
        $this->assertEquals($newerAward->id, $returnedBadges[0]['id']);
        $this->assertEquals($olderAward->id, $returnedBadges[1]['id']);
    }

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    */

    public function test_unauthenticated_returns_401_for_badges(): void
    {
        $this->getJson('/api/v1/badges')->assertStatus(401);
    }

    public function test_unauthenticated_returns_401_for_my_badges(): void
    {
        $this->getJson('/api/v1/me/badges')->assertStatus(401);
    }
}
