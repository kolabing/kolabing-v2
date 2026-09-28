<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Profile;
use App\Services\Incentives\CityLeagueService;
use App\Services\Incentives\OrganiserLevelService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * City league (incentives v1, A): the organiser's home preview and the full
 * table of their city. Organisers (community profiles) only; names only.
 */
class CityLeagueController extends Controller
{
    public function __construct(
        private readonly CityLeagueService $league,
        private readonly OrganiserLevelService $levels,
    ) {}

    /**
     * GET /api/v1/me/community-rank
     */
    public function me(Request $request): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if (! $profile->isCommunity()) {
            return $this->forbidden(__('The city league is only available to community profiles.'));
        }

        $now = now();
        $month = $this->league->monthOf($now);
        [, $end] = $this->league->monthBounds($month);
        $snapshot = $this->levels->current($profile, $now);
        $cityId = $this->league->cityIdFor($profile);
        $city = $cityId !== null && $this->league->enabled() ? City::query()->find($cityId) : null;

        $data = [
            'city' => $city?->name,
            'city_id' => $city?->id,
            'month' => $month,
            'season_ends_at' => $end->utc()->toIso8601ZuluString(),
            'division' => null,
            'division_label' => null,
            'rank' => null,
            'total' => 0,
            'points' => 0,
            'score_breakdown' => ['kolabs_completed' => 0, 'checkins' => 0, 'repeat_attendees' => 0, 'venue_reviews_5star' => 0],
            'preview' => [],
            'promotion_zone' => false,
            'relegation_zone' => false,
            'level' => $snapshot->level->value,
            'next_reward' => $this->levels->nextReward($snapshot, $now),
            'top_reward' => (string) config('incentives.city_league.top_reward'),
        ];

        if ($city === null) {
            return response()->json(['success' => true, 'data' => $data]);
        }

        $table = $this->league->table($city, $month, $profile);
        $me = collect($table['rows'])->firstWhere('profile_id', $profile->id);
        $division = collect($table['rows'])->where('division', $me['division'])->values();

        $topN = (int) config('incentives.city_league.preview_top', 3);
        $around = (int) config('incentives.city_league.preview_neighbours', 1);
        $position = $division->search(fn (array $r): bool => $r['profile_id'] === $profile->id);
        $keep = array_unique(array_merge(
            range(0, max(-1, min($topN, $division->count()) - 1)),
            range(max(0, $position - $around), min($division->count() - 1, $position + $around)),
        ));
        sort($keep);

        return response()->json(['success' => true, 'data' => array_merge($data, [
            'division' => $me['division'],
            'division_label' => $this->divisionLabel($me['division']),
            'rank' => $me['points'] > 0 ? $me['rank'] : null,
            'total' => $division->count(),
            'points' => $me['points'],
            'score_breakdown' => $me['score_breakdown'],
            'preview' => array_map(fn (int $i): array => $this->row($division[$i], $profile), $keep),
            'promotion_zone' => $me['promotion_zone'],
            'relegation_zone' => $me['relegation_zone'],
        ])]);
    }

    /**
     * GET /api/v1/leagues/{city}/current — full live table for organisers of
     * that city.
     */
    public function current(Request $request, City $city): JsonResponse
    {
        /** @var Profile $profile */
        $profile = $request->user();

        if (! $this->league->enabled()) {
            abort(404);
        }

        if (! $profile->isCommunity() || $this->league->cityIdFor($profile) !== $city->id) {
            return $this->forbidden(__('Only communities in this city can see its league.'));
        }

        $month = $this->league->monthOf(now());
        $table = $this->league->table($city, $month, $profile);

        $divisions = collect($table['rows'])
            ->groupBy('division')
            ->map(fn ($rows, string $key): array => [
                'key' => $key,
                'label' => $this->divisionLabel($key),
                'rows' => $rows->map(fn (array $r): array => $this->row($r, $profile))->values()->all(),
            ])
            ->values()
            ->all();

        return response()->json(['success' => true, 'data' => [
            'city' => $city->name,
            'city_id' => $city->id,
            'month' => $month,
            'season_ends_at' => CarbonImmutable::instance($table['ends_at'])->utc()->toIso8601ZuluString(),
            'divisions_enabled' => $table['divisions_enabled'],
            'divisions' => $divisions,
        ]]);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function row(array $row, Profile $viewer): array
    {
        return [
            'rank' => $row['rank'],
            'profile_id' => $row['profile_id'],
            'display_name' => $row['display_name'],
            'points' => $row['points'],
            'is_viewer' => $row['profile_id'] === $viewer->id,
            'promotion_zone' => $row['promotion_zone'],
            'relegation_zone' => $row['relegation_zone'],
        ];
    }

    private function divisionLabel(string $key): string
    {
        return (string) config("incentives.city_league.division_labels.{$key}", ucfirst($key));
    }

    private function forbidden(string $message): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message], 403);
    }
}
