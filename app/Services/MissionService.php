<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ChallengeAudience;
use App\Enums\MissionRepeat;
use App\Enums\MissionTrigger;
use App\Enums\PointEventType;
use App\Models\Challenge;
use App\Models\ChallengeProgress;
use App\Models\PointLedger;
use App\Models\Profile;
use App\Models\Wallet;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Auto-award engine for self-tracked missions (Phase 2).
 *
 * `record()` is the single entry point: a platform action fires it with the
 * matching MissionTrigger and the earner Profile, and it progresses (and, when
 * the target is reached, completes + awards) every active system mission that
 * matches the trigger and the earner's audience.
 *
 * Recursion-safety: completing a mission credits the earner directly via a
 * dedicated `PointEventType::MissionCompleted` ledger entry. It never re-enters
 * `record()` and never maps back to one of the wired triggers, so there is no
 * loop and no double counting. No platform action calls `record(MissionCompleted)`.
 */
class MissionService
{
    public function __construct(
        private readonly GamificationWalletService $walletService,
    ) {}

    /**
     * Progress every active system mission matching `$trigger` for `$earner`.
     *
     * @param  array<string, mixed>  $context  Optional reference data (e.g. reference_id).
     * @return list<ChallengeProgress> The progress rows touched by this call.
     */
    public function record(Profile $earner, MissionTrigger $trigger, int $increment = 1, array $context = []): array
    {
        if ($increment < 1) {
            return [];
        }

        $now = Carbon::now();
        $missions = $this->activeMissionsFor($earner, $trigger, $now);

        $touched = [];

        foreach ($missions as $mission) {
            $touched[] = $this->progressMission($earner, $mission, $increment, $now, $context);
        }

        return $touched;
    }

    /**
     * Active system missions for this trigger whose audience matches the earner
     * and whose campaign window (when set) contains `$now`.
     *
     * @return \Illuminate\Support\Collection<int, Challenge>
     */
    private function activeMissionsFor(Profile $earner, MissionTrigger $trigger, Carbon $now): \Illuminate\Support\Collection
    {
        return Challenge::query()
            ->where('is_system', true)
            ->whereNull('event_id')
            ->where('trigger_action', $trigger)
            ->whereIn('audience', $this->audiencesFor($earner))
            ->where(function ($query) use ($now): void {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($query) use ($now): void {
                $query->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
            })
            ->get();
    }

    /**
     * The audience values a mission may carry to be earnable by this profile.
     * `both` means business + community (never attendee).
     *
     * @return list<string>
     */
    private function audiencesFor(Profile $earner): array
    {
        return match (true) {
            $earner->isBusiness() => [ChallengeAudience::Business->value, ChallengeAudience::Both->value],
            $earner->isCommunity() => [ChallengeAudience::Community->value, ChallengeAudience::Both->value],
            $earner->isAttendee() => [ChallengeAudience::Attendee->value],
            default => [],
        };
    }

    /**
     * Resolve the repeat bucket key for a mission given its repeat interval.
     * `once` shares a single lifetime row; the others bucket by calendar period.
     */
    private function periodKeyFor(?MissionRepeat $repeat, Carbon $now): string
    {
        return match ($repeat ?? MissionRepeat::Once) {
            MissionRepeat::Once => 'once',
            MissionRepeat::Daily => $now->format('Y-m-d'),
            MissionRepeat::Weekly => $now->format('o-\WW'),
            MissionRepeat::Monthly => $now->format('Y-m'),
            MissionRepeat::Seasonal => $now->format('Y').'-Q'.$now->quarter,
        };
    }

    /**
     * Find-or-create the period progress row, increment it, and complete +
     * award if the target is reached. Wrapped in a transaction so the
     * completion flag, the ledger credit and the wallet bump move together.
     */
    private function progressMission(
        Profile $earner,
        Challenge $mission,
        int $increment,
        Carbon $now,
        array $context,
    ): ChallengeProgress {
        $periodKey = $this->periodKeyFor($mission->repeat_interval, $now);

        return DB::transaction(function () use ($earner, $mission, $increment, $now, $periodKey, $context): ChallengeProgress {
            $progress = ChallengeProgress::query()
                ->where('challenge_id', $mission->id)
                ->where('profile_id', $earner->id)
                ->where('period_key', $periodKey)
                ->lockForUpdate()
                ->first();

            if ($progress === null) {
                $progress = ChallengeProgress::query()->create([
                    'challenge_id' => $mission->id,
                    'profile_id' => $earner->id,
                    'period_key' => $periodKey,
                    'progress_count' => 0,
                    'target_value' => $mission->target_value,
                ]);
            }

            if ($progress->completed_at !== null) {
                return $progress;
            }

            $progress->increment('progress_count', $increment);
            $progress->refresh();

            if ($progress->progress_count >= $progress->target_value) {
                $progress->completed_at = $now;
                $progress->save();

                $this->awardMission($earner, $mission, $context);
            }

            return $progress;
        });
    }

    /**
     * Credit the earner the mission's `points` and re-evaluate badges. Writes a
     * dedicated MissionCompleted ledger row directly (does NOT go through the
     * PointEventType→trigger award path), so it can never re-enter record().
     *
     * @param  array<string, mixed>  $context
     */
    private function awardMission(Profile $earner, Challenge $mission, array $context): void
    {
        $points = $mission->points;

        if ($points > 0) {
            PointLedger::query()->create([
                'profile_id' => $earner->id,
                'points' => $points,
                'event_type' => PointEventType::MissionCompleted,
                'reference_id' => $context['reference_id'] ?? $mission->id,
                'description' => 'Mission completed: '.$mission->name,
            ]);

            $wallet = Wallet::query()->firstOrCreate(
                ['profile_id' => $earner->id],
                ['points' => 0, 'redeemed_points' => 0, 'pending_withdrawal' => false],
            );
            $wallet->increment('points', $points);
        }

        $this->walletService->evaluateBadges($earner->id);
    }
}
