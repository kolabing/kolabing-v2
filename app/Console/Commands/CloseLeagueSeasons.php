<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Incentives\CityLeagueService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Freeze last month's city league tables (incentives v1, A): final
 * standings, promotion/relegation once divisions exist, Champion / Top 3
 * badges, and "intro due" for division winners. Idempotent per city/month.
 */
class CloseLeagueSeasons extends Command
{
    protected $signature = 'app:close-league-seasons {--month= : YYYY-MM to close (default: last month, Europe/Madrid)}';

    protected $description = 'Close the monthly city league season and store final standings';

    public function handle(CityLeagueService $league): int
    {
        if (! $league->enabled()) {
            $this->info('City league is disabled.');

            return self::SUCCESS;
        }

        $month = (string) ($this->option('month') ?: $league->previousMonth($league->monthOf(now())));

        if (preg_match('/^\d{4}-\d{2}$/', $month) !== 1) {
            $this->error('--month must be YYYY-MM');

            return self::FAILURE;
        }

        $closed = 0;
        foreach ($league->citiesWithOrganisers() as $city) {
            try {
                $season = $league->closeSeason($city, $month);
                $closed++;
                $this->line(sprintf('%s %s: %d standings%s', $city->name, $month, $season->standings()->count(), $season->divisions_enabled ? ' (divisions)' : ''));
            } catch (Throwable $e) {
                Log::warning('League season close failed', ['city_id' => $city->id, 'month' => $month, 'error' => $e->getMessage()]);
                $this->error("{$city->name}: {$e->getMessage()}");
            }
        }

        $this->info("Closed {$closed} city seasons for {$month}.");

        return self::SUCCESS;
    }
}
