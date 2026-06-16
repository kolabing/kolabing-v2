<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\ChallengeCompletionStatus;
use App\Enums\CommunityBadgeCriteriaType;
use App\Enums\CommunityGoalEarnType;
use App\Models\AttendeeProfile;
use App\Models\Challenge;
use App\Models\ChallengeCompletion;
use App\Models\Community;
use App\Models\CommunityBadge;
use App\Models\CommunityGoal;
use App\Models\CommunityMember;
use App\Models\CommunityPoints;
use App\Models\CommunityReward;
use App\Models\CommunityTier;
use App\Models\Event;
use App\Models\EventCheckin;
use App\Models\PartnerReward;
use App\Models\Profile;
use App\Services\ChallengeCompletionService;
use App\Services\CheckinService;
use App\Services\CommunityPointsService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CommunityGamificationTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function attendee(): Profile
    {
        $profile = Profile::factory()->attendee()->create();
        AttendeeProfile::factory()->create(['profile_id' => $profile->id]);

        return $profile;
    }

    private function communityWithManager(Profile $manager): Community
    {
        $community = Community::factory()->create(['owner_profile_id' => $manager->id]);

        return $community;
    }

    private function joinMember(Community $community, Profile $profile, bool $canManage = false): CommunityMember
    {
        return CommunityMember::factory()->forCommunity($community)->create([
            'profile_id' => $profile->id,
            'can_manage' => $canManage,
            'joined_at' => now()->subDays(30),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Leader CRUD gating
    |--------------------------------------------------------------------------
    */

    public function test_manager_can_create_goal_but_member_cannot(): void
    {
        $owner = $this->attendee();
        $community = $this->communityWithManager($owner);

        $payload = [
            'title' => 'Attend 3 events',
            'earn_type' => CommunityGoalEarnType::EventCheckIns->value,
            'target' => 3,
            'reward_points' => 50,
        ];

        $this->actingAs($owner)
            ->postJson("/api/v1/communities/{$community->id}/goals", $payload)
            ->assertStatus(201)
            ->assertJsonPath('data.earn_type', 'event_check_ins')
            ->assertJsonPath('data.reward_points', 50);

        $member = $this->attendee();
        $this->joinMember($community, $member);

        $this->actingAs($member)
            ->postJson("/api/v1/communities/{$community->id}/goals", $payload)
            ->assertStatus(403);
    }

    public function test_manager_can_crud_reward_and_badge(): void
    {
        $owner = $this->attendee();
        $community = $this->communityWithManager($owner);

        $reward = $this->actingAs($owner)
            ->postJson("/api/v1/communities/{$community->id}/rewards", [
                'title' => 'Free coffee', 'cost_points' => 100, 'stock' => 5,
            ])->assertStatus(201)->json('data.id');

        $this->actingAs($owner)
            ->putJson("/api/v1/rewards/{$reward}", ['cost_points' => 80])
            ->assertStatus(200)->assertJsonPath('data.cost_points', 80);

        $badge = $this->actingAs($owner)
            ->postJson("/api/v1/communities/{$community->id}/badges", [
                'title' => 'Centurion',
                'criteria_type' => CommunityBadgeCriteriaType::PointsThreshold->value,
                'criteria_value' => 100,
            ])->assertStatus(201)->json('data.id');

        $this->actingAs($owner)->deleteJson("/api/v1/badges/{$badge}")->assertStatus(200);
        $this->assertDatabaseMissing('community_badges', ['id' => $badge]);
    }

    /*
    |--------------------------------------------------------------------------
    | Earning points (check-in / goal / challenge) + global XP mirror
    |--------------------------------------------------------------------------
    */

    public function test_event_checkin_awards_community_points_and_global_xp(): void
    {
        $member = $this->attendee();
        $community = Community::factory()->create();
        $this->joinMember($community, $member);

        $event = Event::factory()->forProfile($member)->create([
            'community_id' => $community->id,
            'is_active' => true,
            'checkin_token' => 'tok_'.uniqid(),
        ]);

        app(CheckinService::class)->checkin($member, $event->checkin_token);

        $this->assertSame(
            \App\Enums\PointEventType::EventCheckin->defaultPoints(),
            app(CommunityPointsService::class)->balance($community, $member->id)
        );

        // Global XP mirrored into the wallet.
        $this->assertDatabaseHas('wallets', ['profile_id' => $member->id]);
        $this->assertGreaterThan(0, (int) \App\Models\Wallet::query()->where('profile_id', $member->id)->value('points'));

        // Ledger entry recorded.
        $this->assertDatabaseHas('community_point_ledger', [
            'community_id' => $community->id,
            'profile_id' => $member->id,
            'source' => 'event_check_in',
        ]);
    }

    public function test_goal_completion_awards_points_once(): void
    {
        $member = $this->attendee();
        $community = Community::factory()->create();
        $this->joinMember($community, $member);

        CommunityGoal::factory()->forCommunity($community)->create([
            'earn_type' => CommunityGoalEarnType::EventCheckIns->value,
            'target' => 1,
            'reward_points' => 40,
        ]);

        $event = Event::factory()->forProfile($member)->create([
            'community_id' => $community->id,
            'is_active' => true,
            'checkin_token' => 'tok_'.uniqid(),
        ]);

        app(CheckinService::class)->checkin($member, $event->checkin_token);

        // check-in (10) + goal (40) = 50
        $balance = app(CommunityPointsService::class)->balance($community, $member->id);
        $this->assertSame(50, $balance);

        // Re-running goal completion must not double-pay.
        app(CommunityPointsService::class)->completeGoals($community, $member->id);
        $this->assertSame(50, app(CommunityPointsService::class)->balance($community, $member->id));
    }

    public function test_challenge_verify_awards_community_points(): void
    {
        $challenger = $this->attendee();
        $verifier = $this->attendee();
        $community = Community::factory()->create();
        $this->joinMember($community, $challenger);

        $event = Event::factory()->forProfile($verifier)->create([
            'community_id' => $community->id,
            'max_challenges_per_attendee' => 5,
        ]);
        $challenge = Challenge::factory()->create(['points' => 30]);

        foreach ([$challenger, $verifier] as $p) {
            EventCheckin::factory()->create(['event_id' => $event->id, 'profile_id' => $p->id]);
        }

        $completion = ChallengeCompletion::query()->create([
            'challenge_id' => $challenge->id,
            'event_id' => $event->id,
            'challenger_profile_id' => $challenger->id,
            'verifier_profile_id' => $verifier->id,
            'status' => ChallengeCompletionStatus::Pending,
            'points_earned' => 0,
        ]);

        app(ChallengeCompletionService::class)->verify($verifier, $completion->fresh());

        $this->assertSame(30, app(CommunityPointsService::class)->balance($community, $challenger->id));
    }

    /*
    |--------------------------------------------------------------------------
    | Redeem
    |--------------------------------------------------------------------------
    */

    public function test_redeem_deducts_points_and_guards_balance_and_stock(): void
    {
        $member = $this->attendee();
        $community = Community::factory()->create();
        $this->joinMember($community, $member);

        $reward = CommunityReward::factory()->forCommunity($community)->create([
            'cost_points' => 100, 'stock' => 1,
        ]);

        // Not enough points -> 422
        $this->actingAs($member)
            ->postJson("/api/v1/communities/{$community->id}/rewards/{$reward->id}/redeem")
            ->assertStatus(422)
            ->assertJsonPath('error', 'insufficient_points');

        // Give the member points, then redeem succeeds and deducts.
        CommunityPoints::query()->create([
            'community_id' => $community->id, 'profile_id' => $member->id, 'points' => 150,
        ]);

        $this->actingAs($member)
            ->postJson("/api/v1/communities/{$community->id}/rewards/{$reward->id}/redeem")
            ->assertStatus(201)
            ->assertJsonPath('data.my_points', 50);

        $this->assertDatabaseHas('reward_redemptions', [
            'reward_id' => $reward->id, 'profile_id' => $member->id, 'points_spent' => 100,
        ]);
        $this->assertSame(0, (int) $reward->fresh()->stock);

        // Top the member back up so the next failure is the stock guard, not balance.
        CommunityPoints::query()->where('community_id', $community->id)
            ->where('profile_id', $member->id)->update(['points' => 200]);

        // Out of stock now -> 422
        $this->actingAs($member)
            ->postJson("/api/v1/communities/{$community->id}/rewards/{$reward->id}/redeem")
            ->assertStatus(422)
            ->assertJsonPath('error', 'out_of_stock');
    }

    /*
    |--------------------------------------------------------------------------
    | Badge auto-award
    |--------------------------------------------------------------------------
    */

    public function test_badge_auto_awards_on_points_threshold(): void
    {
        $member = $this->attendee();
        $community = Community::factory()->create();
        $this->joinMember($community, $member);

        CommunityBadge::factory()->forCommunity($community)->create([
            'criteria_type' => CommunityBadgeCriteriaType::PointsThreshold->value,
            'criteria_value' => 10,
        ]);

        $event = Event::factory()->forProfile($member)->create([
            'community_id' => $community->id,
            'is_active' => true,
            'checkin_token' => 'tok_'.uniqid(),
        ]);

        app(CheckinService::class)->checkin($member, $event->checkin_token);

        $this->assertDatabaseHas('community_badge_awards', [
            'community_id' => $community->id,
            'profile_id' => $member->id,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Leaderboard + rewards-hub + me/rewards-overview shapes
    |--------------------------------------------------------------------------
    */

    public function test_community_leaderboard_row_includes_tier_badges_points(): void
    {
        $member = $this->attendee();
        $community = Community::factory()->create();
        $tier = CommunityTier::factory()->forCommunity($community)->create(['name' => 'Gold', 'rank' => 2]);
        $cm = $this->joinMember($community, $member);
        $cm->update(['tier_id' => $tier->id]);

        CommunityPoints::query()->create([
            'community_id' => $community->id, 'profile_id' => $member->id, 'points' => 120,
        ]);

        $this->actingAs($member)
            ->getJson("/api/v1/communities/{$community->id}/leaderboard")
            ->assertStatus(200)
            ->assertJsonPath('data.leaderboard.0.profile_id', $member->id)
            ->assertJsonPath('data.leaderboard.0.points', 120)
            ->assertJsonPath('data.leaderboard.0.tier.name', 'Gold')
            ->assertJsonPath('data.leaderboard.0.badge_count', 0)
            ->assertJsonPath('data.my_rank.rank', 1);
    }

    public function test_rewards_hub_shape(): void
    {
        $member = $this->attendee();
        $community = Community::factory()->create();
        $this->joinMember($community, $member);

        CommunityGoal::factory()->forCommunity($community)->create(['target' => 5, 'reward_points' => 10]);
        CommunityBadge::factory()->forCommunity($community)->create();
        CommunityReward::factory()->forCommunity($community)->create(['cost_points' => 10]);

        $this->actingAs($member)
            ->getJson("/api/v1/communities/{$community->id}/rewards-hub")
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'my_points',
                    'my_tier',
                    'goals' => [['id', 'progress', 'completed']],
                    'badges' => [['id', 'earned']],
                    'rewards' => [['id', 'affordable']],
                ],
            ]);
    }

    public function test_me_rewards_overview_shape(): void
    {
        $member = $this->attendee();
        $community = Community::factory()->create();
        $this->joinMember($community, $member);
        CommunityReward::factory()->forCommunity($community)->create();
        PartnerReward::factory()->create();

        $this->actingAs($member)
            ->getJson('/api/v1/me/rewards-overview')
            ->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'xp',
                    'partner_rewards' => [['id', 'title', 'cost_xp']],
                    'communities' => [['community', 'my_points', 'rewards']],
                ],
            ]);
    }

    public function test_community_show_exposes_my_points_and_my_tier(): void
    {
        $member = $this->attendee();
        $community = Community::factory()->create();
        $tier = CommunityTier::factory()->forCommunity($community)->create(['name' => 'Silver', 'rank' => 1]);
        $cm = $this->joinMember($community, $member);
        $cm->update(['tier_id' => $tier->id]);
        CommunityPoints::query()->create([
            'community_id' => $community->id, 'profile_id' => $member->id, 'points' => 77,
        ]);

        $this->actingAs($member)
            ->getJson("/api/v1/communities/{$community->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.my_points', 77)
            ->assertJsonPath('data.my_tier.name', 'Silver');
    }
}
