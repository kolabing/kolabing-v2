<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ChallengeAudience;
use App\Enums\MissionRepeat;
use App\Enums\MissionTrigger;
use App\Enums\PointEventType;
use App\Models\Challenge;
use App\Models\ChallengeProgress;
use App\Models\Profile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

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
    /**
     * Guarded wrapper around record(): progresses missions but never lets a
     * mission-engine failure bubble into the host action. Every caller fires
     * missions as a side effect of a real platform action (check-in, review,
     * publish, …), so a mission error must be logged and swallowed, not
     * propagated. Use this from call sites; reserve record() for when the
     * caller genuinely needs the touched rows back.
     *
     * @param  array<string, mixed>  $context  Optional reference data (e.g. reference_id).
     */
    public function recordSafely(Profile $earner, MissionTrigger $trigger, int $increment = 1, array $context = []): void
    {
        try {
            $this->record($earner, $trigger, $increment, $context);
        } catch (\Throwable $e) {
            Log::warning('Mission record failed', [
                'trigger' => $trigger->value,
                'profile_id' => $earner->id,
                'context' => $context,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function record(Profile $earner, MissionTrigger $trigger, int $increment = 1, array $context = []): array
    {
        if ($increment < 1) {
            return [];
        }

        if (! $trigger->isLive()) {
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
            ->where('app_visible', true)
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
    public function audiencesFor(Profile $earner): array
    {
        $audiences = match (true) {
            $earner->isBusiness() => [ChallengeAudience::Business->value, ChallengeAudience::Both->value],
            $earner->isCommunity() => [ChallengeAudience::Community->value, ChallengeAudience::Both->value],
            $earner->isAttendee() => [ChallengeAudience::Attendee->value],
            default => [],
        };

        if ($audiences === []) {
            Log::warning('Mission record skipped: profile matches no mission audience', [
                'profile_id' => $earner->id,
                'user_type' => $earner->user_type?->value,
            ]);
        }

        return $audiences;
    }

    /**
     * Resolve the repeat bucket key for a mission given its repeat interval.
     * `once` shares a single lifetime row; the others bucket by calendar period.
     *
     * Public + static so the read endpoint (`GET /me/missions`) resolves the
     * current period's `period_key` identically to how `record()` writes it.
     */
    public static function periodKeyFor(?MissionRepeat $repeat, Carbon $now): string
    {
        // Calendar buckets must roll over at local midnight, not UTC. Timestamps
        // are stored in UTC but the product operates in a single local timezone,
        // so a check-in just after local midnight counts toward the correct
        // day/month. `once` is timezone-independent.
        $local = (clone $now)->setTimezone(config('gamification.local_timezone'));

        return match ($repeat ?? MissionRepeat::Once) {
            MissionRepeat::Once => 'once',
            MissionRepeat::Daily => $local->format('Y-m-d'),
            MissionRepeat::Weekly => $local->format('o-\WW'),
            MissionRepeat::Monthly => $local->format('Y-m'),
            MissionRepeat::Seasonal => $local->format('Y').'-Q'.$local->quarter,
        };
    }

    /**
     * Atomically find-or-create the period progress row, increment it, and
     * complete + award if the target is reached. Wrapped in a transaction so
     * the completion flag, the ledger credit and the wallet bump move
     * together.
     */
    private function progressMission(
        Profile $earner,
        Challenge $mission,
        int $increment,
        Carbon $now,
        array $context,
    ): ChallengeProgress {
        $periodKey = self::periodKeyFor($mission->repeat_interval, $now);

        return DB::transaction(function () use ($earner, $mission, $increment, $now, $periodKey, $context): ChallengeProgress {
            // Atomic find-or-create: ON CONFLICT DO NOTHING means two concurrent
            // requests for a brand-new (challenge, profile, period) row never race
            // on the unique index — the loser's upsert becomes a no-op instead of
            // a duplicate-key exception. The empty update list is deliberate:
            // `target_value` is frozen at row creation so an admin editing the
            // mission's target mid-period never retroactively reopens or
            // auto-completes an in-flight progress row.
            ChallengeProgress::query()->upsert(
                [[
                    'id' => (string) Str::uuid(),
                    'challenge_id' => $mission->id,
                    'profile_id' => $earner->id,
                    'period_key' => $periodKey,
                    'progress_count' => 0,
                    'target_value' => $mission->target_value,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]],
                ['challenge_id', 'profile_id', 'period_key'],
                [],
            );

            $progress = ChallengeProgress::query()
                ->where('challenge_id', $mission->id)
                ->where('profile_id', $earner->id)
                ->where('period_key', $periodKey)
                ->lockForUpdate()
                ->first();

            // Defensive: the row was just upserted, so this should always hit.
            // If a concurrent delete or unexpected DB state loses it, bail
            // rather than dereference null — callers swallow mission failures.
            if ($progress === null) {
                throw new \RuntimeException("Mission progress row vanished after upsert (challenge {$mission->id}, profile {$earner->id}, period {$periodKey}).");
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
        if ($mission->points > 0) {
            $this->walletService->creditPoints(
                $earner->id,
                PointEventType::MissionCompleted,
                $mission->points,
                $context['reference_id'] ?? $mission->id,
                'Mission completed: '.$mission->name,
                $mission->id,
            );

            return;
        }

        $this->walletService->evaluateBadges($earner->id);
    }
}
