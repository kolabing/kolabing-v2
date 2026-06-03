<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\AttendeeProfile;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CommunityLeaderboardTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function attendee(int $points): Profile
    {
        $profile = Profile::factory()->attendee()->create();
        AttendeeProfile::factory()->create(['profile_id' => $profile->id, 'total_points' => $points]);

        return $profile;
    }

    public function test_community_scope_ranks_only_active_members(): void
    {
        $community = Community::factory()->create();

        $memberA = $this->attendee(300);
        $memberB = $this->attendee(100);
        $nonMember = $this->attendee(9999); // high points but not in the chapter

        foreach ([$memberA, $memberB] as $p) {
            CommunityMember::factory()->forCommunity($community)->create(['profile_id' => $p->id]);
        }

        $response = $this->actingAs($memberA)
            ->getJson("/api/v1/leaderboard/global?community_id={$community->id}");

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data.leaderboard')
            ->assertJsonPath('data.leaderboard.0.profile_id', $memberA->id)
            ->assertJsonPath('data.leaderboard.0.rank', 1)
            ->assertJsonPath('data.leaderboard.1.profile_id', $memberB->id);

        $ids = array_column($response->json('data.leaderboard'), 'profile_id');
        $this->assertNotContains($nonMember->id, $ids);
    }

    public function test_my_rank_is_null_for_non_member(): void
    {
        $community = Community::factory()->create();
        $member = $this->attendee(300);
        CommunityMember::factory()->forCommunity($community)->create(['profile_id' => $member->id]);

        $outsider = $this->attendee(500);

        $response = $this->actingAs($outsider)
            ->getJson("/api/v1/leaderboard/global?community_id={$community->id}");

        $response->assertStatus(200)->assertJsonPath('data.my_rank', null);
    }

    public function test_unknown_community_id_returns_404(): void
    {
        $viewer = $this->attendee(10);

        $this->actingAs($viewer)
            ->getJson('/api/v1/leaderboard/global?community_id='.\Illuminate\Support\Str::uuid())
            ->assertStatus(404);
    }

    public function test_global_leaderboard_still_works_without_community_id(): void
    {
        $a = $this->attendee(50);

        $this->actingAs($a)->getJson('/api/v1/leaderboard/global')
            ->assertStatus(200)
            ->assertJsonPath('data.leaderboard.0.profile_id', $a->id);
    }
}
