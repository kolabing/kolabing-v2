<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\UserType;
use App\Models\Profile;
use Illuminate\Console\Command;

/**
 * How many businesses and communities the in-app "Kolabing team" channel
 * reaches (SERR-658): the share that has ever opened the app. Read-only.
 * Agents cannot read the production database, so a maintainer runs it:
 *
 *   php artisan kolabing:team-reach [--json]
 *
 * "Opened the app" = `last_active_at` is set (lifecycle tracking) or a push
 * `device_token` is registered. Real accounts only: active, not deleted,
 * not test users.
 */
class KolabingTeamReach extends Command
{
    protected $signature = 'kolabing:team-reach {--json : Print JSON instead of a table}';

    protected $description = 'Count businesses and communities the in-app Kolabing team channel reaches (read-only)';

    public function handle(): int
    {
        $rows = [];

        foreach ([UserType::Business, UserType::Community] as $type) {
            $real = fn () => Profile::query()
                ->where('user_type', $type)
                ->where('is_active', true)
                ->where('is_test_user', false);

            $total = $real()->count();
            $opened = $real()->where(fn ($q) => $q->whereNotNull('last_active_at')->orWhereNotNull('device_token'))->count();
            $pushable = $real()->whereNotNull('device_token')->count();
            $active30 = $real()->where('last_active_at', '>=', now()->subDays(30))->count();

            $rows[] = [
                'type' => $type->value,
                'real_accounts' => $total,
                'opened_the_app' => $opened,
                'opened_pct' => $total > 0 ? round($opened / $total * 100, 1) : 0.0,
                'active_last_30_days' => $active30,
                'with_push_token' => $pushable,
                'email_fallback_only' => $total - $opened,
            ];
        }

        if ($this->option('json')) {
            $this->line((string) json_encode($rows, JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        $this->table(array_keys($rows[0]), $rows);

        return self::SUCCESS;
    }
}
