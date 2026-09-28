<?php

declare(strict_types=1);

namespace App\Services\Incentives;

use App\Enums\CollaborationStatus;
use App\Enums\CommunityMemberStatus;
use App\Enums\UserType;
use App\Models\City;
use App\Models\CollaborationReview;
use App\Models\LeagueSeason;
use App\Models\LeagueStanding;
use App\Models\Profile;
use App\Support\PublicDisplayName;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * City league (incentives v1, A). One season per city per calendar month in
 * Europe/Madrid. Organisers (community profiles) score from in-app, verified
 * actions only:
 *
 *   kolabs completed in the app × 40
 *   verified check-ins at their events × 2
 *   repeat attendees (checked in this month, and at least twice at their events) × 5
 *   5-star venue reviews × 20
 *
 * Divisions by member count (<50, 50-199, 200+) only exist when at least two
 * of them have ≥ N active organisers; otherwise the city is one division.
 * Organisers in a division below N join the nearest division that stands.
 * Once a city has divisions, last season's top 3 move up and bottom 3 move
 * down for the next season; newcomers are placed by member count.
 */
class CityLeagueService
{
    public const DIVISION_CITY = 'city';

    /** Lowest → highest. */
    public const DIVISIONS = ['small', 'medium', 'large'];

    /**
     * Memoised live tables for one run/request.
     *
     * @var array<string, array<string, mixed>>
     */
    private array $tables = [];

    /**
     * Memoised organisers and scores per city and month, shared by every
     * viewer's table.
     *
     * @var array<string, array{0: \Illuminate\Support\Collection<string, Profile>, 1: array<string, array<string, mixed>>}>
     */
    private array $tableInputs = [];

    public function enabled(): bool
    {
        return (bool) config('incentives.city_league.enabled', true);
    }

    public function timezone(): string
    {
        return (string) config('incentives.city_league.timezone', 'Europe/Madrid');
    }

    public function monthOf(CarbonInterface $at): string
    {
        return CarbonImmutable::instance($at)->setTimezone($this->timezone())->format('Y-m');
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable} local start/end of the month
     */
    public function monthBounds(string $month): array
    {
        $start = CarbonImmutable::createFromFormat('Y-m-d H:i:s', $month.'-01 00:00:00', $this->timezone());

        return [$start, $start->endOfMonth()];
    }

    public function previousMonth(string $month): string
    {
        return $this->monthBounds($month)[0]->subMonthNoOverflow()->format('Y-m');
    }

    public function cityIdFor(Profile $profile): ?string
    {
        $profile->loadMissing('communityProfile');

        return $profile->communityProfile?->city_id ?? $profile->city_id;
    }

    /**
     * The live (or, for a past closed month, frozen) league table.
     *
     * @return array{city: City, month: string, starts_at: CarbonImmutable, ends_at: CarbonImmutable, divisions_enabled: bool, rows: array<int, array<string, mixed>>}
     */
    public function table(City $city, string $month, ?Profile $viewer = null): array
    {
        [$start, $end] = $this->monthBounds($month);

        // The organisers and their scores do not depend on the viewer, and the
        // scores are the expensive part. Compute them once per city and month.
        $inputsKey = $city->id.'|'.$month;
        if (! isset($this->tableInputs[$inputsKey])) {
            $cityOrganisers = $this->organisersIn($city);
            $this->tableInputs[$inputsKey] = [
                $cityOrganisers,
                $this->scores($cityOrganisers->keys()->all(), $start, $end),
            ];
        }
        [$organisers, $scores] = $this->tableInputs[$inputsKey];

        // The viewer only changes the table when it adds a row that would not
        // otherwise be there: an organiser in this city with no points yet.
        // Everyone else shares one table, so the nightly level job builds it
        // once per city instead of once per organiser.
        $viewerAddsRow = $viewer !== null
            && $organisers->has($viewer->id)
            && ($scores[$viewer->id]['points'] ?? 0) <= 0;
        $key = $inputsKey.'|'.($viewerAddsRow ? $viewer->id : '-');
        if (isset($this->tables[$key])) {
            return $this->tables[$key];
        }

        $participants = $organisers->filter(
            fn (Profile $p): bool => ($scores[$p->id]['points'] ?? 0) > 0 || $p->id === $viewer?->id
        );

        $members = $this->memberCounts($participants->keys()->all());
        $previous = $this->closedSeason($city, $this->previousMonth($month));
        [$divisionsEnabled, $divisionOf] = $this->assignDivisions($participants, $scores, $members, $previous);

        $rows = [];
        foreach ($participants as $id => $profile) {
            $rows[] = [
                'profile_id' => $id,
                'display_name' => PublicDisplayName::forOrganiser($profile),
                'points' => (int) ($scores[$id]['points'] ?? 0),
                'score_breakdown' => $scores[$id]['breakdown'] ?? $this->emptyBreakdown(),
                'members' => (int) ($members[$id] ?? 0),
                'division' => $divisionOf[$id],
            ];
        }

        $rows = $this->rankWithinDivisions($rows, $divisionsEnabled);

        return $this->tables[$key] = [
            'city' => $city,
            'month' => $month,
            'starts_at' => $start,
            'ends_at' => $end,
            'divisions_enabled' => $divisionsEnabled,
            'rows' => $rows,
        ];
    }

    /**
     * The organiser's row in the current table of their city, or null.
     *
     * @return array<string, mixed>|null
     */
    public function rowFor(Profile $profile, ?CarbonInterface $now = null): ?array
    {
        $cityId = $this->cityIdFor($profile);
        $city = $cityId !== null ? City::query()->find($cityId) : null;

        if ($city === null) {
            return null;
        }

        $table = $this->table($city, $this->monthOf($now ?? now()), $profile);

        return collect($table['rows'])->firstWhere('profile_id', $profile->id);
    }

    /**
     * Rank a profile needs to be inside to count as "top" of its division:
     * the top 3, or the top 10% when the division is large.
     */
    public function topThreshold(int $divisionSize): int
    {
        $ranks = (int) config('incentives.city_league.top_ranks', 3);
        $percent = (float) config('incentives.city_league.top_percent', 0.10);

        return max($ranks, (int) ceil($divisionSize * $percent));
    }

    /**
     * Freeze the final table of one city for one month. Idempotent: a season
     * already closed is returned untouched.
     */
    public function closeSeason(City $city, string $month): LeagueSeason
    {
        $existing = LeagueSeason::query()->where('city_id', $city->id)->where('month', $month)->first();
        if ($existing?->closed_at !== null) {
            return $existing;
        }

        $table = $this->table($city, $month);
        $slots = (int) config('incentives.city_league.promotion_slots', 3);

        return DB::transaction(function () use ($city, $month, $table, $existing, $slots): LeagueSeason {
            $season = $existing ?? new LeagueSeason(['city_id' => $city->id, 'month' => $month]);
            $season->fill([
                'starts_at' => $table['starts_at']->utc(),
                'ends_at' => $table['ends_at']->utc(),
                'divisions_enabled' => $table['divisions_enabled'],
                'closed_at' => now(),
            ])->save();

            foreach ($table['rows'] as $row) {
                if ($row['points'] <= 0) {
                    continue;
                }

                LeagueStanding::query()->updateOrCreate(
                    ['season_id' => $season->id, 'profile_id' => $row['profile_id']],
                    [
                        'division' => $row['division'],
                        'rank' => $row['rank'],
                        'points' => $row['points'],
                        'score_breakdown' => $row['score_breakdown'],
                        'movement' => $row['promotion_zone'] ? 'promoted' : ($row['relegation_zone'] ? 'relegated' : null),
                        'badge' => match (true) {
                            $row['rank'] === 1 => LeagueStanding::BADGE_CHAMPION,
                            $row['rank'] <= $slots => LeagueStanding::BADGE_TOP3,
                            default => null,
                        },
                    ],
                );
            }

            return $season->refresh();
        });
    }

    /**
     * Cities with at least one active community profile.
     *
     * @return Collection<int, City>
     */
    public function citiesWithOrganisers(): Collection
    {
        $ids = DB::table('community_profiles')
            ->join('profiles', 'profiles.id', '=', 'community_profiles.profile_id')
            ->where('profiles.is_active', true)
            ->where('profiles.user_type', UserType::Community->value)
            ->whereNotNull('community_profiles.city_id')
            ->distinct()
            ->pluck('community_profiles.city_id');

        return City::query()->whereIn('id', $ids)->get();
    }

    public function closedSeason(City $city, string $month): ?LeagueSeason
    {
        return LeagueSeason::query()
            ->where('city_id', $city->id)
            ->where('month', $month)
            ->whereNotNull('closed_at')
            ->first();
    }

    /**
     * Final standing of a profile in its city's last closed season (month
     * before $month), or null.
     */
    public function lastStanding(Profile $profile, string $month): ?LeagueStanding
    {
        return LeagueStanding::query()
            ->where('profile_id', $profile->id)
            ->whereHas('season', fn ($q) => $q->where('month', $this->previousMonth($month))->whereNotNull('closed_at'))
            ->with('season')
            ->first();
    }

    /**
     * Score components per organiser for [start, end] (local month bounds).
     *
     * @param  array<int, string>  $profileIds
     * @return array<string, array{points: int, breakdown: array<string, int>}>
     */
    public function scores(array $profileIds, CarbonInterface $start, CarbonInterface $end): array
    {
        if ($profileIds === []) {
            return [];
        }

        $from = CarbonImmutable::instance($start)->utc();
        $to = CarbonImmutable::instance($end)->utc();
        $breakdown = array_fill_keys($profileIds, $this->emptyBreakdown());
        $inSet = array_flip($profileIds);

        // Kolabs completed in the app (a collab between two organisers counts for both).
        DB::table('collaborations')
            ->where('status', CollaborationStatus::Completed->value)
            ->whereBetween('completed_at', [$from, $to])
            ->where(fn ($q) => $q->whereIn('creator_profile_id', $profileIds)->orWhereIn('applicant_profile_id', $profileIds))
            ->get(['creator_profile_id', 'applicant_profile_id'])
            ->each(function ($row) use (&$breakdown, $inSet): void {
                foreach (array_unique([$row->creator_profile_id, $row->applicant_profile_id]) as $id) {
                    if (isset($inSet[$id])) {
                        $breakdown[$id]['kolabs_completed']++;
                    }
                }
            });

        // Verified check-ins at their events (events they created, or held
        // under a community they own) + repeat attendees.
        $ownerByCommunity = DB::table('communities')->whereIn('owner_profile_id', $profileIds)->pluck('owner_profile_id', 'id')->all();
        $events = DB::table('events')
            ->where(function ($q) use ($profileIds, $ownerByCommunity): void {
                $q->whereIn('profile_id', $profileIds);
                if ($ownerByCommunity !== []) {
                    $q->orWhereIn('community_id', array_keys($ownerByCommunity));
                }
            })
            ->get(['id', 'profile_id', 'community_id']);

        $ownerByEvent = [];
        foreach ($events as $event) {
            $ownerByEvent[$event->id] = isset($inSet[$event->profile_id])
                ? $event->profile_id
                : ($ownerByCommunity[$event->community_id] ?? null);
        }
        $ownerByEvent = array_filter($ownerByEvent);

        if ($ownerByEvent !== []) {
            $checkins = DB::table('event_checkins')
                ->whereIn('event_id', array_keys($ownerByEvent))
                ->where('checked_in_at', '<=', $to)
                ->get(['event_id', 'profile_id', 'checked_in_at']);

            $visits = [];
            foreach ($checkins as $checkin) {
                $owner = $ownerByEvent[$checkin->event_id];
                $at = CarbonImmutable::parse($checkin->checked_in_at, 'UTC');
                $inMonth = $at->greaterThanOrEqualTo($from);

                if ($inMonth) {
                    $breakdown[$owner]['checkins']++;
                }
                $visits[$owner][$checkin->profile_id]['total'] = ($visits[$owner][$checkin->profile_id]['total'] ?? 0) + 1;
                $visits[$owner][$checkin->profile_id]['month'] = ($visits[$owner][$checkin->profile_id]['month'] ?? false) || $inMonth;
            }

            foreach ($visits as $owner => $attendees) {
                foreach ($attendees as $visit) {
                    if ($visit['month'] && $visit['total'] >= 2) {
                        $breakdown[$owner]['repeat_attendees']++;
                    }
                }
            }
        }

        // 5-star reviews from venues.
        $overall = CollaborationReview::overallRatingSqlExpression();
        CollaborationReview::query()->toBase()
            ->join('profiles as reviewer', 'reviewer.id', '=', 'collaboration_reviews.reviewer_profile_id')
            ->whereIn('collaboration_reviews.reviewed_profile_id', $profileIds)
            ->where('reviewer.user_type', UserType::Business->value)
            ->whereBetween('collaboration_reviews.created_at', [$from, $to])
            ->whereRaw("{$overall} >= 5")
            ->selectRaw('collaboration_reviews.reviewed_profile_id as profile_id, COUNT(*) as total')
            ->groupBy('collaboration_reviews.reviewed_profile_id')
            ->get()
            ->each(function ($row) use (&$breakdown): void {
                $breakdown[$row->profile_id]['venue_reviews_5star'] = (int) $row->total;
            });

        $weights = (array) config('incentives.city_league.weights', []);
        $result = [];
        foreach ($breakdown as $id => $parts) {
            $points = 0;
            foreach ($parts as $key => $count) {
                $points += $count * (int) ($weights[$key] ?? 0);
            }
            $result[$id] = ['points' => $points, 'breakdown' => $parts];
        }

        return $result;
    }

    /**
     * @return array<string, int>
     */
    private function emptyBreakdown(): array
    {
        return ['kolabs_completed' => 0, 'checkins' => 0, 'repeat_attendees' => 0, 'venue_reviews_5star' => 0];
    }

    /**
     * Active community profiles located in the city, keyed by id.
     *
     * @return Collection<string, Profile>
     */
    private function organisersIn(City $city): Collection
    {
        return Profile::query()
            ->active()
            ->where('user_type', UserType::Community->value)
            ->where(function ($q) use ($city): void {
                $q->whereHas('communityProfile', fn ($cp) => $cp->where('city_id', $city->id))
                    ->orWhere(fn ($p) => $p->where('city_id', $city->id)->whereDoesntHave('communityProfile'));
            })
            ->with('communityProfile:id,profile_id,name')
            ->get()
            ->keyBy('id');
    }

    /**
     * Distinct active members across the communities each organiser owns.
     *
     * @param  array<int, string>  $profileIds
     * @return array<string, int>
     */
    private function memberCounts(array $profileIds): array
    {
        if ($profileIds === []) {
            return [];
        }

        return DB::table('community_members')
            ->join('communities', 'communities.id', '=', 'community_members.community_id')
            ->whereIn('communities.owner_profile_id', $profileIds)
            ->where('community_members.status', CommunityMemberStatus::Active->value)
            ->groupBy('communities.owner_profile_id')
            ->selectRaw('communities.owner_profile_id as owner, COUNT(DISTINCT community_members.profile_id) as total')
            ->pluck('total', 'owner')
            ->map(fn ($n): int => (int) $n)
            ->all();
    }

    private function divisionBySize(int $members): string
    {
        [$small, $large] = [
            (int) config('incentives.city_league.divisions.medium_from', 50),
            (int) config('incentives.city_league.divisions.large_from', 200),
        ];

        return match (true) {
            $members >= $large => 'large',
            $members >= $small => 'medium',
            default => 'small',
        };
    }

    /**
     * @param  Collection<string, Profile>  $participants
     * @param  array<string, array{points: int}>  $scores
     * @param  array<string, int>  $members
     * @return array{0: bool, 1: array<string, string>}
     */
    private function assignDivisions(Collection $participants, array $scores, array $members, ?LeagueSeason $previous): array
    {
        $carried = [];
        if ($previous !== null && $previous->divisions_enabled) {
            foreach ($previous->standings()->get(['profile_id', 'division', 'movement']) as $standing) {
                $index = array_search($standing->division, self::DIVISIONS, true);
                if ($index === false) {
                    continue;
                }
                $index += match ($standing->movement) {
                    'promoted' => 1,
                    'relegated' => -1,
                    default => 0,
                };
                $carried[$standing->profile_id] = self::DIVISIONS[max(0, min(count(self::DIVISIONS) - 1, $index))];
            }
        }

        $base = [];
        foreach ($participants as $id => $profile) {
            $base[$id] = $carried[$id] ?? $this->divisionBySize((int) ($members[$id] ?? 0));
        }

        $minimum = (int) config('incentives.city_league.divisions.min_active_per_division', 8);
        $activeCounts = array_fill_keys(self::DIVISIONS, 0);
        foreach ($base as $id => $division) {
            if (($scores[$id]['points'] ?? 0) > 0) {
                $activeCounts[$division]++;
            }
        }

        $standing = array_values(array_filter(self::DIVISIONS, fn (string $d): bool => $activeCounts[$d] >= $minimum));

        if (count($standing) < 2) {
            return [false, array_fill_keys(array_keys($base), self::DIVISION_CITY)];
        }

        $order = array_flip(self::DIVISIONS);
        foreach ($base as $id => $division) {
            if (in_array($division, $standing, true)) {
                continue;
            }
            // Nearest standing division; ties go to the lower one.
            usort($standing, fn (string $a, string $b): int => [abs($order[$a] - $order[$division]), $order[$a]] <=> [abs($order[$b] - $order[$division]), $order[$b]]);
            $base[$id] = $standing[0];
        }

        return [true, $base];
    }

    /**
     * Competition rank (1, 1, 3) inside each division, points desc then name.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function rankWithinDivisions(array $rows, bool $divisionsEnabled): array
    {
        $slots = (int) config('incentives.city_league.promotion_slots', 3);
        $present = array_values(array_unique(array_column($rows, 'division')));
        $order = array_flip(self::DIVISIONS);
        usort($present, fn (string $a, string $b): int => ($order[$a] ?? 0) <=> ($order[$b] ?? 0));
        $lowest = $present[0] ?? null;
        $highest = $present === [] ? null : $present[count($present) - 1];

        $out = [];
        foreach (collect($rows)->groupBy('division') as $division => $group) {
            $sorted = $group->sort(fn (array $a, array $b): int => [$b['points'], mb_strtolower($a['display_name'])] <=> [$a['points'], mb_strtolower($b['display_name'])])->values();
            $size = $sorted->count();
            $rank = 0;
            $previousPoints = null;

            foreach ($sorted as $i => $row) {
                if ($row['points'] !== $previousPoints) {
                    $rank = $i + 1;
                    $previousPoints = $row['points'];
                }

                $out[] = $row + [
                    'rank' => $rank,
                    'division_size' => $size,
                    'promotion_zone' => $divisionsEnabled && $division !== $highest && $row['points'] > 0 && $rank <= $slots,
                    'relegation_zone' => $divisionsEnabled && $division !== $lowest && $rank > max($slots, $size - $slots),
                ];
            }
        }

        usort($out, fn (array $a, array $b): int => [($order[$b['division']] ?? 0), $a['rank'], $a['display_name']] <=> [($order[$a['division']] ?? 0), $b['rank'], $b['display_name']]);

        return $out;
    }
}
