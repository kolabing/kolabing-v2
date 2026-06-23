<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\ChallengeAudience;
use App\Enums\ChallengeCategory;
use App\Enums\MissionRepeat;
use App\Enums\MissionTrigger;
use App\Models\AttendeeProfile;
use App\Models\Challenge;
use App\Models\Event;
use App\Models\EventCheckin;
use App\Models\Profile;
use App\Services\MissionService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Phase 4a: GET /api/v1/me/missions returns the viewer's role-relevant LIVE
 * missions with the viewer's current-period progress.
 */
class MyMissionsTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\XpEarnRuleSeeder::class);
    }

    private function mission(
        MissionTrigger $trigger,
        ChallengeAudience $audience,
        int $target = 1,
        int $points = 20,
        MissionRepeat $repeat = MissionRepeat::Once,
        ?ChallengeCategory $category = null,
    ): Challenge {
        return Challenge::factory()
            ->mission($trigger, $target, $repeat, $audience)
            ->create([
                'points' => $points,
                'category' => $category ?? ChallengeCategory::Growth,
            ]);
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/v1/me/missions')->assertUnauthorized();
    }

    public function test_business_viewer_sees_only_business_and_both_live_missions(): void
    {
        $business = Profile::factory()->business()->create();

        $bizMission = $this->mission(MissionTrigger::KolabPublished, ChallengeAudience::Business);
        $bothMission = $this->mission(MissionTrigger::CollaborationComplete, ChallengeAudience::Both);

        // Excluded: attendee audience, community audience, and an inert trigger.
        $attendeeMission = $this->mission(MissionTrigger::EventCheckin, ChallengeAudience::Attendee);
        $communityMission = $this->mission(MissionTrigger::ApplicationSubmitted, ChallengeAudience::Community);
        $inertMission = $this->mission(MissionTrigger::PlanUpgraded, ChallengeAudience::Business);

        $response = $this->actingAs($business)->getJson('/api/v1/me/missions')->assertOk();

        $ids = collect($response->json('data.missions'))->pluck('id')->all();

        $this->assertContains($bizMission->id, $ids);
        $this->assertContains($bothMission->id, $ids);
        $this->assertNotContains($attendeeMission->id, $ids);
        $this->assertNotContains($communityMission->id, $ids);
        $this->assertNotContains($inertMission->id, $ids);
    }

    public function test_response_envelope_and_mission_shape(): void
    {
        $business = Profile::factory()->business()->create();
        $mission = $this->mission(MissionTrigger::KolabPublished, ChallengeAudience::Business, 3, 30);

        $response = $this->actingAs($business)->getJson('/api/v1/me/missions')->assertOk();

        $response->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'missions' => [
                        ['id', 'slug', 'name', 'description', 'category', 'points', 'difficulty',
                            'target_value', 'repeat_interval', 'progress_count', 'completed',
                            'completed_at', 'period_key'],
                    ],
                ],
            ]);

        $row = collect($response->json('data.missions'))->firstWhere('id', $mission->id);

        $this->assertSame(3, $row['target_value']);
        $this->assertSame(30, $row['points']);
        $this->assertSame(0, $row['progress_count']);
        $this->assertFalse($row['completed']);
        $this->assertNull($row['completed_at']);
        $this->assertSame('once', $row['period_key']);
    }

    public function test_progress_reflects_challenge_progress_for_once_mission(): void
    {
        $business = Profile::factory()->business()->create();
        $mission = $this->mission(MissionTrigger::KolabPublished, ChallengeAudience::Business, 2, 30);

        // No progress yet -> 0.
        $row = $this->fetchMission($business, $mission->id);
        $this->assertSame(0, $row['progress_count']);
        $this->assertFalse($row['completed']);

        // One increment -> 1, not yet complete.
        app(MissionService::class)->record($business, MissionTrigger::KolabPublished);
        $row = $this->fetchMission($business, $mission->id);
        $this->assertSame(1, $row['progress_count']);
        $this->assertFalse($row['completed']);

        // Second increment reaches the target -> complete.
        app(MissionService::class)->record($business, MissionTrigger::KolabPublished);
        $row = $this->fetchMission($business, $mission->id);
        $this->assertSame(2, $row['progress_count']);
        $this->assertTrue($row['completed']);
        $this->assertNotNull($row['completed_at']);
    }

    public function test_repeatable_mission_shows_current_period_progress(): void
    {
        $business = Profile::factory()->business()->create();
        $mission = $this->mission(
            MissionTrigger::KolabPublished,
            ChallengeAudience::Business,
            3,
            30,
            MissionRepeat::Monthly,
        );

        // Progress recorded last month must NOT appear in this month's window.
        $lastMonth = Carbon::now()->subMonthNoOverflow();
        \App\Models\ChallengeProgress::query()->create([
            'challenge_id' => $mission->id,
            'profile_id' => $business->id,
            'period_key' => MissionService::periodKeyFor(MissionRepeat::Monthly, $lastMonth),
            'progress_count' => 2,
            'target_value' => 3,
        ]);

        $row = $this->fetchMission($business, $mission->id);
        $this->assertSame(0, $row['progress_count']);
        $this->assertSame(MissionService::periodKeyFor(MissionRepeat::Monthly, Carbon::now()), $row['period_key']);

        // A record this month shows up.
        app(MissionService::class)->record($business, MissionTrigger::KolabPublished);
        $row = $this->fetchMission($business, $mission->id);
        $this->assertSame(1, $row['progress_count']);
    }

    public function test_challenge_completed_mission_progresses_after_verified_peer_challenge(): void
    {
        $mission = $this->mission(
            MissionTrigger::ChallengeCompleted,
            ChallengeAudience::Attendee,
            1,
            20,
            MissionRepeat::Once,
            ChallengeCategory::Engagement,
        );

        $owner = Profile::factory()->business()->create();
        $event = Event::factory()->forProfile($owner)->create([
            'is_active' => true,
            'max_challenges_per_attendee' => 5,
        ]);

        $challenger = Profile::factory()->attendee()->create();
        AttendeeProfile::factory()->create(['profile_id' => $challenger->id]);
        $verifier = Profile::factory()->attendee()->create();
        AttendeeProfile::factory()->create(['profile_id' => $verifier->id]);

        EventCheckin::factory()->forEvent($event)->forProfile($challenger)->create();
        EventCheckin::factory()->forEvent($event)->forProfile($verifier)->create();

        $peerChallenge = Challenge::factory()->system()->easy()->create();

        $service = app(\App\Services\ChallengeCompletionService::class);
        $completion = $service->initiate($challenger, [
            'challenge_id' => $peerChallenge->id,
            'event_id' => $event->id,
            'verifier_profile_id' => $verifier->id,
        ]);
        $service->verify($verifier, $completion);

        $row = $this->fetchMission($challenger, $mission->id);
        $this->assertSame(1, $row['progress_count']);
        $this->assertTrue($row['completed']);
    }

    /**
     * Fetch the named mission row for a viewer from the endpoint.
     *
     * @return array<string, mixed>
     */
    private function fetchMission(Profile $viewer, string $missionId): array
    {
        $response = $this->actingAs($viewer)->getJson('/api/v1/me/missions')->assertOk();
        $row = collect($response->json('data.missions'))->firstWhere('id', $missionId);

        $this->assertNotNull($row, "expected mission {$missionId} in the response");

        return $row;
    }
}
