<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\ChallengeCompletionStatus;
use App\Enums\CommunityGoalEarnType;
use App\Models\AttendeeProfile;
use App\Models\Challenge;
use App\Models\ChallengeCompletion;
use App\Models\Collaboration;
use App\Models\Community;
use App\Models\CommunityGoal;
use App\Models\CommunityMember;
use App\Models\Event;
use App\Models\Friendship;
use App\Models\Notification;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Regression guards for the four N+1 hotspots found by the query audit:
 * collaborations `has_reviewed`, rewards-hub goal progress, notification
 * actor extended profiles, and friend extended profiles. Each asserts the
 * query count stays constant regardless of row count.
 */
class RemainingNPlusOneTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function attendee(): Profile
    {
        $profile = Profile::factory()->attendee()->create();
        AttendeeProfile::factory()->create(['profile_id' => $profile->id]);

        return $profile;
    }

    private function countQueries(\Closure $fn): int
    {
        DB::enableQueryLog();
        DB::flushQueryLog();
        $fn();
        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    public function test_notifications_index_query_count_is_constant_across_attendee_actors(): void
    {
        $makeViewerWith = function (int $n): Profile {
            $viewer = $this->attendee();
            for ($i = 0; $i < $n; $i++) {
                $actor = $this->attendee();
                Notification::factory()->create([
                    'profile_id' => $viewer->id,
                    'actor_profile_id' => $actor->id,
                ]);
            }

            return $viewer;
        };

        $one = $makeViewerWith(1);
        $many = $makeViewerWith(3);

        $baseline = $this->countQueries(fn () => $this->actingAs($one)
            ->getJson('/api/v1/me/notifications')->assertOk());
        $scaled = $this->countQueries(fn () => $this->actingAs($many)
            ->getJson('/api/v1/me/notifications')->assertOk());

        $this->assertSame($baseline, $scaled,
            "Notifications index must not lazy-load the actor's extended profile per row (1 actor: {$baseline}, 3 actors: {$scaled}).");
    }

    public function test_friends_index_query_count_is_constant_across_friends(): void
    {
        $makeViewerWith = function (int $n): Profile {
            $viewer = $this->attendee();
            for ($i = 0; $i < $n; $i++) {
                $friend = $this->attendee();
                Friendship::factory()->accepted()->create([
                    'requester_profile_id' => $viewer->id,
                    'addressee_profile_id' => $friend->id,
                ]);
            }

            return $viewer;
        };

        $one = $makeViewerWith(1);
        $many = $makeViewerWith(3);

        $baseline = $this->countQueries(fn () => $this->actingAs($one)
            ->getJson('/api/v1/me/friends')->assertOk());
        $scaled = $this->countQueries(fn () => $this->actingAs($many)
            ->getJson('/api/v1/me/friends')->assertOk());

        $this->assertSame($baseline, $scaled,
            "Friends index must eager-load friend extended profiles (1 friend: {$baseline}, 3 friends: {$scaled}).");
    }

    public function test_collaborations_index_does_not_query_reviews_per_row(): void
    {
        $countReviewQueries = function (Profile $viewer): int {
            $n = 0;
            DB::listen(function ($q) use (&$n): void {
                if (str_contains($q->sql, 'from "collaboration_reviews"')) {
                    $n++;
                }
            });
            $this->actingAs($viewer)->getJson('/api/v1/collaborations')->assertOk();

            return $n;
        };

        $makeViewerWith = function (int $n): Profile {
            $viewer = Profile::factory()->business()->create();
            for ($i = 0; $i < $n; $i++) {
                $partner = Profile::factory()->community()->create();
                Collaboration::factory()->completed()
                    ->forCreator($viewer)
                    ->forApplicant($partner)
                    ->create();
            }

            return $viewer;
        };

        // has_reviewed must read the eager-loaded `reviews` relation, so the
        // number of collaboration_reviews queries stays flat as rows grow (only
        // the single eager-load whereIn), not one exists() per row.
        $baseline = $countReviewQueries($makeViewerWith(1));
        $scaled = $countReviewQueries($makeViewerWith(3));

        $this->assertSame($baseline, $scaled,
            "has_reviewed must derive from the loaded reviews relation, not query per row (1 collab: {$baseline}, 3 collabs: {$scaled}).");
    }

    public function test_rewards_hub_query_count_is_constant_across_goals(): void
    {
        $makeMemberInCommunityWith = function (int $goals): array {
            $member = $this->attendee();
            $community = Community::factory()->create();
            CommunityMember::factory()->forCommunity($community)->create([
                'profile_id' => $member->id,
                'joined_at' => now()->subDays(5),
            ]);
            for ($i = 0; $i < $goals; $i++) {
                CommunityGoal::factory()->forCommunity($community)->create([
                    'earn_type' => CommunityGoalEarnType::DaysInCommunity->value,
                    'target' => 9999, // never completed → pure read path, no awards fire
                    'is_active' => true,
                ]);
            }

            return [$member, $community];
        };

        [$member1, $communityA] = $makeMemberInCommunityWith(1);
        [$member2, $communityB] = $makeMemberInCommunityWith(3);

        $baseline = $this->countQueries(fn () => $this->actingAs($member1)
            ->getJson("/api/v1/communities/{$communityA->id}/rewards-hub")->assertOk());
        $scaled = $this->countQueries(fn () => $this->actingAs($member2)
            ->getJson("/api/v1/communities/{$communityB->id}/rewards-hub")->assertOk());

        $this->assertSame($baseline, $scaled,
            "Rewards-hub goal progress must be batched, not queried per goal (1 goal: {$baseline}, 3 goals: {$scaled}).");
    }

    public function test_rewards_hub_challenge_goal_progress_is_correct_when_batched(): void
    {
        $member = $this->attendee();
        $verifier = $this->attendee();
        $community = Community::factory()->create();
        CommunityMember::factory()->forCommunity($community)->create([
            'profile_id' => $member->id,
            'joined_at' => now()->subDays(5),
        ]);

        $event = Event::factory()->forProfile($verifier)->create(['community_id' => $community->id]);
        $challengeA = Challenge::factory()->create();
        $challengeB = Challenge::factory()->create();

        // One verified completion for challenge A only.
        ChallengeCompletion::query()->create([
            'challenge_id' => $challengeA->id,
            'event_id' => $event->id,
            'challenger_profile_id' => $member->id,
            'verifier_profile_id' => $verifier->id,
            'status' => ChallengeCompletionStatus::Verified->value,
            'points_earned' => 10,
        ]);

        $goalA = CommunityGoal::factory()->forCommunity($community)->create([
            'earn_type' => CommunityGoalEarnType::Challenge->value,
            'challenge_id' => $challengeA->id,
            'target' => 1,
        ]);
        $goalB = CommunityGoal::factory()->forCommunity($community)->create([
            'earn_type' => CommunityGoalEarnType::Challenge->value,
            'challenge_id' => $challengeB->id,
            'target' => 1,
        ]);

        $goals = collect(
            $this->actingAs($member)
                ->getJson("/api/v1/communities/{$community->id}/rewards-hub")
                ->assertOk()
                ->json('data.goals')
        );

        $a = $goals->firstWhere('id', $goalA->id);
        $b = $goals->firstWhere('id', $goalB->id);

        $this->assertSame(1, $a['progress'], 'Goal tied to the completed challenge must show progress 1.');
        $this->assertTrue($a['completed']);
        $this->assertSame(0, $b['progress'], 'Goal tied to the other challenge must show progress 0.');
        $this->assertFalse($b['completed']);
    }
}
