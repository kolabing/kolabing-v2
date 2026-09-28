<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\CommunityPoints;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Every leaderboard row used the member's email as display_name, and the
 * community points board had no membership check.
 */
class CommunityLeaderboardPrivacyTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_community_points_leaderboard_is_members_only_and_never_shows_email(): void
    {
        $community = Community::factory()->create();
        $viewer = Profile::factory()->attendee()->create(['name' => 'Viewer']);
        CommunityMember::factory()->forCommunity($community)->create(['profile_id' => $viewer->id]);
        $hidden = Profile::factory()->attendee()->create(['name' => null, 'email' => 'hidden.name@example.com']);
        CommunityMember::factory()->forCommunity($community)->create(['profile_id' => $hidden->id]);
        CommunityPoints::query()->create(['community_id' => $community->id, 'profile_id' => $hidden->id, 'points' => 50]);

        $response = $this->actingAs($viewer)
            ->getJson("/api/v1/communities/{$community->id}/leaderboard")
            ->assertOk()
            ->assertJsonPath('data.leaderboard.0.display_name', 'hidden.name');
        $this->assertStringNotContainsString('@', $response->getContent());

        $this->actingAs(Profile::factory()->attendee()->create())
            ->getJson("/api/v1/communities/{$community->id}/leaderboard")
            ->assertForbidden();

        $this->actingAs($community->owner)
            ->getJson("/api/v1/communities/{$community->id}/leaderboard")
            ->assertOk();

        $manager = Profile::factory()->attendee()->create();
        CommunityMember::factory()->forCommunity($community)->manager()->create(['profile_id' => $manager->id]);
        $this->actingAs($manager)
            ->getJson("/api/v1/communities/{$community->id}/leaderboard")
            ->assertOk();
    }
}
