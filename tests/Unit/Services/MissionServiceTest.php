<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\ChallengeAudience;
use App\Enums\MissionRepeat;
use App\Enums\MissionTrigger;
use App\Enums\PointEventType;
use App\Models\Challenge;
use App\Models\ChallengeProgress;
use App\Models\PointLedger;
use App\Models\Profile;
use App\Models\Wallet;
use App\Services\MissionService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MissionServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private MissionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(MissionService::class);
    }

    private function mission(
        MissionTrigger $trigger,
        ChallengeAudience $audience,
        int $target = 1,
        MissionRepeat $repeat = MissionRepeat::Once,
        int $points = 20,
    ): Challenge {
        return Challenge::factory()
            ->mission($trigger, $target, $repeat, $audience)
            ->create(['points' => $points]);
    }

    public function test_single_event_completes_a_target_one_mission_and_awards_points(): void
    {
        $business = Profile::factory()->business()->create();
        $mission = $this->mission(MissionTrigger::CollaborationComplete, ChallengeAudience::Business, 1, MissionRepeat::Once, 50);

        $this->service->record($business, MissionTrigger::CollaborationComplete);

        $progress = ChallengeProgress::query()
            ->where('challenge_id', $mission->id)
            ->where('profile_id', $business->id)
            ->first();

        $this->assertNotNull($progress);
        $this->assertSame(1, $progress->progress_count);
        $this->assertNotNull($progress->completed_at);

        $this->assertDatabaseHas('point_ledger', [
            'profile_id' => $business->id,
            'event_type' => PointEventType::MissionCompleted->value,
            'points' => 50,
        ]);
        $this->assertSame(50, Wallet::query()->where('profile_id', $business->id)->value('points'));
    }

    public function test_multi_step_mission_needs_n_events(): void
    {
        $community = Profile::factory()->community()->create();
        $mission = $this->mission(MissionTrigger::CollaborationComplete, ChallengeAudience::Community, 3, MissionRepeat::Once, 30);

        $this->service->record($community, MissionTrigger::CollaborationComplete);
        $this->service->record($community, MissionTrigger::CollaborationComplete);

        $progress = ChallengeProgress::query()->where('challenge_id', $mission->id)->first();
        $this->assertSame(2, $progress->progress_count);
        $this->assertNull($progress->completed_at);
        $this->assertSame(0, Wallet::query()->where('profile_id', $community->id)->count());

        $this->service->record($community, MissionTrigger::CollaborationComplete);
        $progress->refresh();
        $this->assertSame(3, $progress->progress_count);
        $this->assertNotNull($progress->completed_at);
        $this->assertSame(30, Wallet::query()->where('profile_id', $community->id)->value('points'));
    }

    public function test_increment_greater_than_one_can_complete_in_one_call(): void
    {
        $business = Profile::factory()->business()->create();
        $mission = $this->mission(MissionTrigger::ReviewReceived, ChallengeAudience::Business, 5, MissionRepeat::Once, 30);

        $this->service->record($business, MissionTrigger::ReviewReceived, 5);

        $progress = ChallengeProgress::query()->where('challenge_id', $mission->id)->first();
        $this->assertSame(5, $progress->progress_count);
        $this->assertNotNull($progress->completed_at);
    }

    public function test_audience_scoping_business_event_does_not_progress_attendee_mission(): void
    {
        $business = Profile::factory()->business()->create();
        $attendeeMission = $this->mission(MissionTrigger::ReviewPosted, ChallengeAudience::Attendee, 1);

        $touched = $this->service->record($business, MissionTrigger::ReviewPosted);

        $this->assertCount(0, $touched);
        $this->assertDatabaseMissing('challenge_progress', [
            'challenge_id' => $attendeeMission->id,
        ]);
    }

    public function test_both_audience_matches_business_and_community_but_not_attendee(): void
    {
        $bothMission = $this->mission(MissionTrigger::CollaborationComplete, ChallengeAudience::Both, 1);

        $business = Profile::factory()->business()->create();
        $community = Profile::factory()->community()->create();
        $attendee = Profile::factory()->attendee()->create();

        $this->assertCount(1, $this->service->record($business, MissionTrigger::CollaborationComplete));
        $this->assertCount(1, $this->service->record($community, MissionTrigger::CollaborationComplete));
        $this->assertCount(0, $this->service->record($attendee, MissionTrigger::CollaborationComplete));
    }

    public function test_no_double_award_when_recording_after_completion(): void
    {
        $business = Profile::factory()->business()->create();
        $this->mission(MissionTrigger::CollaborationComplete, ChallengeAudience::Business, 1, MissionRepeat::Once, 50);

        $this->service->record($business, MissionTrigger::CollaborationComplete);
        $this->service->record($business, MissionTrigger::CollaborationComplete);
        $this->service->record($business, MissionTrigger::CollaborationComplete);

        $this->assertSame(1, PointLedger::query()
            ->where('profile_id', $business->id)
            ->where('event_type', PointEventType::MissionCompleted->value)
            ->count());
        $this->assertSame(50, Wallet::query()->where('profile_id', $business->id)->value('points'));
    }

    public function test_concurrent_progress_creation_does_not_throw(): void
    {
        $earner = Profile::factory()->business()->create();
        $mission = $this->mission(MissionTrigger::KolabPublished, ChallengeAudience::Business, 5, MissionRepeat::Once, 20);

        // Simulate two concurrent requests progressing the same brand-new mission
        // by calling record() twice back-to-back before either "wins" the row.
        $first = $this->service->record($earner, MissionTrigger::KolabPublished);
        $second = $this->service->record($earner, MissionTrigger::KolabPublished);

        $this->assertCount(1, $first);
        $this->assertCount(1, $second);
        $this->assertSame(2, $first[0]->fresh()->progress_count);
        $this->assertSame($mission->id, $first[0]->challenge_id);
    }

    public function test_monthly_repeat_missions_bucket_by_period_key(): void
    {
        $community = Profile::factory()->community()->create();
        $mission = $this->mission(MissionTrigger::MemberCheckin, ChallengeAudience::Community, 1, MissionRepeat::Monthly, 50);

        Carbon::setTestNow(Carbon::create(2026, 6, 15, 12));
        $this->service->record($community, MissionTrigger::MemberCheckin);

        Carbon::setTestNow(Carbon::create(2026, 7, 15, 12));
        $this->service->record($community, MissionTrigger::MemberCheckin);

        Carbon::setTestNow();

        $rows = ChallengeProgress::query()
            ->where('challenge_id', $mission->id)
            ->where('profile_id', $community->id)
            ->get();

        $this->assertCount(2, $rows);
        $this->assertEqualsCanonicalizing(['2026-06', '2026-07'], $rows->pluck('period_key')->all());
        // Two separate periods both completed → two awards.
        $this->assertSame(2, PointLedger::query()
            ->where('profile_id', $community->id)
            ->where('event_type', PointEventType::MissionCompleted->value)
            ->count());
        $this->assertSame(100, Wallet::query()->where('profile_id', $community->id)->value('points'));
    }

    public function test_mission_award_does_not_emit_a_recordable_trigger(): void
    {
        $business = Profile::factory()->business()->create();
        $this->mission(MissionTrigger::CollaborationComplete, ChallengeAudience::Business, 1, MissionRepeat::Once, 50);

        $this->service->record($business, MissionTrigger::CollaborationComplete);

        // Only the MissionCompleted ledger row exists; no further mission rows
        // (the award did not re-enter record() with a mapped trigger).
        $this->assertSame(1, PointLedger::query()->where('profile_id', $business->id)->count());
        $this->assertSame(1, ChallengeProgress::query()->where('profile_id', $business->id)->count());
    }

    public function test_non_live_trigger_never_progresses_a_mission(): void
    {
        $business = Profile::factory()->business()->create();
        $mission = $this->mission(MissionTrigger::SocialShare, ChallengeAudience::Business, 1, MissionRepeat::Once, 50);

        $this->assertFalse(MissionTrigger::SocialShare->isLive());

        $touched = $this->service->record($business, MissionTrigger::SocialShare);

        $this->assertCount(0, $touched);
        $this->assertDatabaseMissing('challenge_progress', ['challenge_id' => $mission->id]);
    }

    public function test_mission_outside_campaign_window_is_not_progressed(): void
    {
        $business = Profile::factory()->business()->create();
        $future = Challenge::factory()
            ->mission(MissionTrigger::CollaborationComplete, 1, MissionRepeat::Once, ChallengeAudience::Business)
            ->create(['starts_at' => Carbon::now()->addDays(5)]);

        $touched = $this->service->record($business, MissionTrigger::CollaborationComplete);

        $this->assertCount(0, $touched);
        $this->assertDatabaseMissing('challenge_progress', ['challenge_id' => $future->id]);
    }
}
