<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\InstagramAccount;
use App\Services\InstagramService;
use Illuminate\Console\Command;

/**
 * Nightly (BE-NF-75): Instagram long-lived tokens last 60 days and can be
 * refreshed once they are at least 24 hours old. Refresh every connected token
 * last refreshed `services.instagram.refresh_after_days` (50) days ago or more;
 * disconnect tokens that already expired or that Instagram refuses.
 */
class RefreshInstagramTokens extends Command
{
    protected $signature = 'instagram:refresh-tokens';

    protected $description = 'Refresh Instagram long-lived tokens older than 50 days and disconnect expired ones';

    public function handle(InstagramService $instagram): int
    {
        $expired = 0;
        $refreshed = 0;
        $failed = 0;

        InstagramAccount::query()
            ->whereNotNull('access_token')
            ->whereNotNull('token_expires_at')
            ->where('token_expires_at', '<=', now())
            ->each(function (InstagramAccount $account) use ($instagram, &$expired): void {
                $instagram->disconnect($account);
                $expired++;
            });

        $cutoff = now()->subDays(max(1, (int) config('services.instagram.refresh_after_days', 50)));

        InstagramAccount::query()
            ->whereNotNull('access_token')
            ->where(function ($q) use ($cutoff): void {
                $q->where('token_refreshed_at', '<=', $cutoff)
                    ->orWhere(fn ($q) => $q->whereNull('token_refreshed_at')->where('connected_at', '<=', $cutoff));
            })
            ->each(function (InstagramAccount $account) use ($instagram, &$refreshed, &$failed): void {
                $instagram->refreshToken($account) ? $refreshed++ : $failed++;
            });

        $this->info("Instagram tokens: {$refreshed} refreshed, {$failed} failed, {$expired} expired and disconnected.");

        return self::SUCCESS;
    }
}
