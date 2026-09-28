<?php

declare(strict_types=1);

namespace App\Services\Incentives;

use App\Enums\CollaborationStatus;
use App\Enums\OrganiserLevel;
use App\Models\Collaboration;
use App\Models\LeagueStanding;
use App\Models\OrganiserLevelSnapshot;
use App\Models\Profile;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Organiser levels (incentives v1, C) — the reward ladder. In-app, verified
 * actions only; months are calendar months in Europe/Madrid.
 *
 *  New     no kolab completed in the app yet
 *  Rising  ≥1 kolab completed in the app, ever
 *  Trusted ≥1 kolab completed in the app this month (or last month) with
 *          ≥20 verified check-ins at it. A month without one drops the
 *          organiser back to Rising at the start of the next month.
 *  Top     Trusted AND inside the top of their city league division (top 3,
 *          or top 10% when the division is large), live this month or in
 *          last month's final table.
 *
 * Thresholds, perks and copy live in config/incentives.php.
 */
class OrganiserLevelService
{
    public function __construct(private readonly CityLeagueService $league) {}

    /**
     * Evaluate one community profile now and persist a snapshot.
     */
    public function evaluate(Profile $profile, ?CarbonInterface $now = null): OrganiserLevelSnapshot
    {
        $now = CarbonImmutable::instance($now ?? now());
        $metrics = $this->metrics($profile, $now);
        $level = $this->resolveLevel($metrics);
        $previous = OrganiserLevelSnapshot::latestFor($profile->id);

        $introDoneAt = $previous !== null
            && $previous->level === OrganiserLevel::Top
            && $level === OrganiserLevel::Top
                ? $previous->intro_done_at
                : null;

        [$discoveryScore, $discovery] = $this->discoveryScore($level, $metrics);

        return OrganiserLevelSnapshot::query()->create([
            'profile_id' => $profile->id,
            'level' => $level,
            'previous_level' => $previous?->level,
            'criteria' => [
                'metrics' => $metrics,
                'criteria' => $this->criteriaRows($level, $metrics),
                'discovery' => $discovery,
            ],
            'evaluated_at' => $now,
            'discovery_score' => $discoveryScore,
            'intro_done_at' => $introDoneAt,
        ]);
    }

    /**
     * The newest snapshot for this month; evaluates on the spot when the
     * nightly run has not reached this profile (new signup, or a new month).
     */
    public function current(Profile $profile, ?CarbonInterface $now = null): OrganiserLevelSnapshot
    {
        $now = $now ?? now();
        $latest = OrganiserLevelSnapshot::latestFor($profile->id);

        if ($latest !== null && ($latest->criteria['metrics']['month'] ?? null) === $this->league->monthOf($now)) {
            return $latest;
        }

        return $this->evaluate($profile, $now);
    }

    /**
     * API payload for GET /api/v1/me/organiser-level.
     *
     * @return array<string, mixed>
     */
    public function payload(OrganiserLevelSnapshot $snapshot, ?CarbonInterface $now = null): array
    {
        $level = $snapshot->level;
        $next = $level->next();
        $perks = $this->perksFor($level);
        $metrics = $snapshot->criteria['metrics'] ?? [];
        $month = (string) ($metrics['month'] ?? $this->league->monthOf($now ?? now()));

        return [
            'level' => $level->value,
            'next_level' => $next?->value,
            'window' => 'calendar_month',
            'window_days' => $this->league->monthBounds($month)[0]->daysInMonth,
            'month' => $month,
            'days_left' => $this->daysLeft($month, $now),
            'criteria' => $snapshot->criteria['criteria'] ?? [],
            'perks' => $perks,
            'next_perks' => $next !== null ? array_values(array_diff($this->perksFor($next), $perks)) : [],
            'next_reward' => $this->nextReward($snapshot, $now),
            'evaluated_at' => $snapshot->evaluated_at->copy()->utc()->toIso8601ZuluString(),
        ];
    }

    /**
     * The one next step, in plain words, from the level gap.
     *
     * @return array{type: string, level: string, message: string, remaining: array<string, int>, days_left: int}|null
     */
    public function nextReward(OrganiserLevelSnapshot $snapshot, ?CarbonInterface $now = null): ?array
    {
        $metrics = $snapshot->criteria['metrics'] ?? [];
        $month = (string) ($metrics['month'] ?? $this->league->monthOf($now ?? now()));
        $days = $this->daysLeft($month, $now);
        $daysText = $days === 1 ? '1 day left' : "{$days} days left";
        $minCheckins = (int) config('incentives.organiser_levels.trusted.min_checkins', 20);
        $copy = (array) config('incentives.organiser_levels.reward_copy', []);
        $label = fn (OrganiserLevel $l): string => (string) config("incentives.organiser_levels.levels.{$l->value}.label", ucfirst($l->value));
        $qualifiedThisMonth = ($metrics['qualifying_kolabs_this_month'] ?? 0) >= $this->kolabsPerMonth();
        $rank = $metrics['league_rank'] ?? null;
        $threshold = (int) ($metrics['league_threshold'] ?? config('incentives.city_league.top_ranks', 3));

        $reward = fn (string $type, OrganiserLevel $target, string $message, array $remaining): array => [
            'type' => $type,
            'level' => $target->value,
            'message' => $message,
            'remaining' => $remaining,
            'days_left' => $days,
        ];

        return match (true) {
            $snapshot->level === OrganiserLevel::New => $reward('level', OrganiserLevel::Rising,
                "Complete your first kolab in the app to reach Rising: {$copy['rising']}",
                ['kolabs_completed' => 1]),

            $snapshot->level === OrganiserLevel::Rising => $reward('level', OrganiserLevel::Trusted,
                "Complete 1 kolab this month with {$minCheckins}+ attendees to reach Trusted: {$copy['trusted']}. {$daysText}",
                ['kolabs_completed' => 1, 'checkins' => $minCheckins]),

            ! $qualifiedThisMonth => $reward('keep', $snapshot->level,
                "Complete 1 kolab this month with {$minCheckins}+ attendees to stay {$label($snapshot->level)}. {$daysText}",
                ['kolabs_completed' => 1, 'checkins' => $minCheckins]),

            $snapshot->level === OrganiserLevel::Trusted && $rank !== null => $reward('level', OrganiserLevel::Top,
                "You're #{$rank}. Reach the top {$threshold} of your city league for Top: {$copy['top']}",
                ['ranks' => max(1, $rank - $threshold)]),

            $snapshot->level === OrganiserLevel::Trusted => $reward('level', OrganiserLevel::Top,
                "Earn city league points this month to reach the top {$threshold} for Top: {$copy['top']}",
                []),

            default => $reward('keep', OrganiserLevel::Top,
                $rank !== null
                    ? "You're #{$rank}. Stay in the top {$threshold} of your city league to keep Top: {$copy['top']}"
                    : "Stay in the top {$threshold} of your city league to keep Top: {$copy['top']}",
                []),
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function metrics(Profile $profile, CarbonInterface $now): array
    {
        $month = $this->league->monthOf($now);
        $previousMonth = $this->league->previousMonth($month);

        $total = Collaboration::query()
            ->where('status', CollaborationStatus::Completed->value)
            ->where(fn ($q) => $q->where('creator_profile_id', $profile->id)->orWhere('applicant_profile_id', $profile->id))
            ->count();

        $thisMonth = $this->kolabCheckins($profile, $month);
        $lastMonth = $this->kolabCheckins($profile, $previousMonth);
        $minCheckins = (int) config('incentives.organiser_levels.trusted.min_checkins', 20);
        $qualifying = fn (array $counts): int => count(array_filter($counts, fn (int $n): bool => $n >= $minCheckins));

        $row = $this->league->enabled() ? $this->league->rowFor($profile, $now) : null;
        $liveRank = $row !== null && $row['points'] > 0 ? (int) $row['rank'] : null;
        $liveThreshold = $row !== null ? $this->league->topThreshold((int) $row['division_size']) : null;

        $last = $this->league->enabled() ? $this->league->lastStanding($profile, $month) : null;
        $lastThreshold = null;
        if ($last !== null) {
            $lastSize = LeagueStanding::query()->where('season_id', $last->season_id)->where('division', $last->division)->count();
            $lastThreshold = $this->league->topThreshold($lastSize);
        }

        $leagueTop = ($liveRank !== null && $liveRank <= $liveThreshold)
            || ($last !== null && $last->rank <= $lastThreshold);

        return [
            'month' => $month,
            'kolabs_completed' => $total,
            'kolabs_this_month' => count($thisMonth),
            'best_kolab_checkins_this_month' => $thisMonth === [] ? 0 : max($thisMonth),
            'qualifying_kolabs_this_month' => $qualifying($thisMonth),
            'qualifying_kolabs_last_month' => $qualifying($lastMonth),
            'league_city_id' => $this->league->cityIdFor($profile),
            'league_division' => $row['division'] ?? null,
            'league_rank' => $liveRank,
            'league_division_size' => $row['division_size'] ?? null,
            'league_threshold' => $liveThreshold ?? (int) config('incentives.city_league.top_ranks', 3),
            'league_points' => (int) ($row['points'] ?? 0),
            'league_last_rank' => $last?->rank,
            'league_last_points' => (int) ($last?->points ?? 0),
            'league_last_badge' => $last?->badge,
            'league_top' => $leagueTop,
        ];
    }

    /**
     * @param  array<string, mixed>  $metrics
     */
    public function resolveLevel(array $metrics): OrganiserLevel
    {
        $perMonth = $this->kolabsPerMonth();
        $trusted = $metrics['qualifying_kolabs_this_month'] >= $perMonth
            || $metrics['qualifying_kolabs_last_month'] >= $perMonth;

        return match (true) {
            $trusted && $metrics['league_top'] => OrganiserLevel::Top,
            $trusted => OrganiserLevel::Trusted,
            $metrics['kolabs_completed'] >= 1 => OrganiserLevel::Rising,
            default => OrganiserLevel::New,
        };
    }

    /**
     * @return array<int, string>
     */
    public function perksFor(OrganiserLevel $level): array
    {
        return array_values((array) config("incentives.organiser_levels.levels.{$level->value}.perks", []));
    }

    /**
     * Verified check-ins (distinct attendees) at each kolab the organiser
     * completed in the app during $month, keyed by collaboration id.
     *
     * @return array<string, int>
     */
    private function kolabCheckins(Profile $profile, string $month): array
    {
        [$start, $end] = $this->league->monthBounds($month);

        $collaborations = Collaboration::query()
            ->where('status', CollaborationStatus::Completed->value)
            ->whereBetween('completed_at', [$start->utc(), $end->utc()])
            ->where(fn ($q) => $q->where('creator_profile_id', $profile->id)->orWhere('applicant_profile_id', $profile->id))
            ->get(['id', 'event_id']);

        if ($collaborations->isEmpty()) {
            return [];
        }

        $ids = $collaborations->pluck('id')->all();
        $eventToCollab = DB::table('events')->whereIn('collaboration_id', $ids)->pluck('collaboration_id', 'id')->all();
        foreach ($collaborations as $collaboration) {
            if ($collaboration->event_id !== null) {
                $eventToCollab[$collaboration->event_id] = $collaboration->id;
            }
        }

        $counts = array_fill_keys($ids, 0);
        if ($eventToCollab !== []) {
            $seen = [];
            DB::table('event_checkins')
                ->whereIn('event_id', array_keys($eventToCollab))
                ->get(['event_id', 'profile_id'])
                ->each(function ($row) use ($eventToCollab, &$counts, &$seen): void {
                    $collabId = $eventToCollab[$row->event_id];
                    if (! isset($seen[$collabId][$row->profile_id])) {
                        $seen[$collabId][$row->profile_id] = true;
                        $counts[$collabId]++;
                    }
                });
        }

        return $counts;
    }

    /**
     * @param  array<string, mixed>  $metrics
     * @return array<int, array<string, mixed>>
     */
    private function criteriaRows(OrganiserLevel $level, array $metrics): array
    {
        $labels = (array) config('incentives.organiser_levels.criteria_labels', []);
        $minCheckins = (int) config('incentives.organiser_levels.trusted.min_checkins', 20);
        $perMonth = $this->kolabsPerMonth();
        $goal = $level->next() ?? $level;
        $rank = $metrics['league_rank'];
        $threshold = (int) $metrics['league_threshold'];

        return [
            [
                'key' => 'kolabs_completed',
                'label' => $labels['kolabs_completed'] ?? 'kolabs_completed',
                'value' => $metrics['kolabs_completed'],
                'target' => 1,
                'met' => $metrics['kolabs_completed'] >= 1,
                'required' => $goal === OrganiserLevel::Rising,
            ],
            [
                'key' => 'monthly_kolab',
                'label' => $labels['monthly_kolab'] ?? 'monthly_kolab',
                'value' => $metrics['qualifying_kolabs_this_month'],
                'target' => $perMonth,
                'met' => $metrics['qualifying_kolabs_this_month'] >= $perMonth,
                'required' => $goal->rank() >= OrganiserLevel::Trusted->rank(),
            ],
            [
                'key' => 'kolab_checkins',
                'label' => $labels['kolab_checkins'] ?? 'kolab_checkins',
                'value' => $metrics['best_kolab_checkins_this_month'],
                'target' => $minCheckins,
                'met' => $metrics['best_kolab_checkins_this_month'] >= $minCheckins,
                'required' => $goal->rank() >= OrganiserLevel::Trusted->rank(),
                'unit' => 'checkins',
            ],
            [
                'key' => 'league_rank',
                'label' => $labels['league_rank'] ?? 'league_rank',
                'value' => $rank,
                'target' => $threshold,
                'met' => (bool) $metrics['league_top'],
                'required' => $goal === OrganiserLevel::Top,
                'unit' => 'rank',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $metrics
     * @return array{0: int, 1: array<string, int>}
     */
    private function discoveryScore(OrganiserLevel $level, array $metrics): array
    {
        $levelPoints = (int) config("incentives.organiser_levels.discovery.level_points.{$level->value}", 0);
        $weight = (float) config('incentives.organiser_levels.discovery.league_weight', 0.02);
        $decay = (float) config('incentives.organiser_levels.discovery.league_previous_month_decay', 0.5);
        $cap = (int) config('incentives.organiser_levels.discovery.league_cap', 10);

        $league = (int) min($cap, round($weight * ($metrics['league_points'] + $decay * $metrics['league_last_points'])));
        $champion = $metrics['league_last_badge'] === LeagueStanding::BADGE_CHAMPION
            ? (int) config('incentives.city_league.champion_discovery_points', 5)
            : 0;

        $breakdown = ['level' => $levelPoints, 'league' => $league, 'champion' => $champion];

        return [array_sum($breakdown), $breakdown];
    }

    private function daysLeft(string $month, ?CarbonInterface $now = null): int
    {
        $today = CarbonImmutable::instance($now ?? now())->setTimezone($this->league->timezone())->startOfDay();
        $end = $this->league->monthBounds($month)[1]->startOfDay();

        return max(0, (int) $today->diffInDays($end) + 1);
    }

    private function kolabsPerMonth(): int
    {
        return max(1, (int) config('incentives.organiser_levels.trusted.kolabs_per_month', 1));
    }
}
