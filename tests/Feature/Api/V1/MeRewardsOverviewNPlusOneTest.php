<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\AttendeeProfile;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\CommunityPoints;
use App\Models\CommunityReward;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MeRewardsOverviewNPlusOneTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function attendee(): Profile
    {
        $profile = Profile::factory()->attendee()->create();
        AttendeeProfile::factory()->create(['profile_id' => $profile->id]);

        return $profile;
    }

    /**
     * @return array{0: Community, 1: CommunityReward}
     */
    private function joinedCommunityWithReward(Profile $member, int $points, int $rewardCost): array
    {
        $community = Community::factory()->create();
        CommunityMember::factory()->forCommunity($community)->create([
            'profile_id' => $member->id,
            'joined_at' => now()->subDays(10),
        ]);
        CommunityPoints::query()->create([
            'community_id' => $community->id,
            'profile_id' => $member->id,
            'points' => $points,
        ]);
        $reward = CommunityReward::factory()->forCommunity($community)->create([
            'is_active' => true,
            'cost_points' => $rewardCost,
        ]);

        return [$community, $reward];
    }

    public function test_rewards_overview_query_count_does_not_grow_with_community_count(): void
    {
        // Baseline: a member in a single community.
        $member1 = $this->attendee();
        $this->joinedCommunityWithReward($member1, 100, 50);

        // Scaled: a member in four communities, each with points + a reward.
        $member2 = $this->attendee();
        for ($i = 0; $i < 4; $i++) {
            $this->joinedCommunityWithReward($member2, 100, 50);
        }

        DB::enableQueryLog();

        DB::flushQueryLog();
        $this->actingAs($member1)->getJson('/api/v1/me/rewards-overview')->assertOk();
        $baseline = count(DB::getQueryLog());

        DB::flushQueryLog();
        $this->actingAs($member2)->getJson('/api/v1/me/rewards-overview')->assertOk();
        $scaled = count(DB::getQueryLog());

        DB::disableQueryLog();

        $this->assertSame(
            $baseline,
            $scaled,
            "Query count must be constant regardless of community count (1 community: {$baseline}, 4 communities: {$scaled}) — no per-community N+1."
        );
    }

    public function test_rewards_overview_returns_correct_points_and_affordability_per_community(): void
    {
        $member = $this->attendee();
        [$richCommunity, $affordableReward] = $this->joinedCommunityWithReward($member, 100, 50);
        [$poorCommunity, $tooExpensiveReward] = $this->joinedCommunityWithReward($member, 10, 500);

        $response = $this->actingAs($member)
            ->getJson('/api/v1/me/rewards-overview')
            ->assertOk();

        $communities = collect($response->json('data.communities'));
        $this->assertCount(2, $communities);

        $rich = $communities->firstWhere('community.id', $richCommunity->id);
        $poor = $communities->firstWhere('community.id', $poorCommunity->id);

        $this->assertSame(100, $rich['my_points']);
        $this->assertSame(10, $poor['my_points']);

        $this->assertTrue($rich['rewards'][0]['affordable'], 'Reward within balance must be affordable.');
        $this->assertFalse($poor['rewards'][0]['affordable'], 'Reward above balance must not be affordable.');
    }
}
