<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Profile;
use App\Services\KolabInviteService;
use Illuminate\Console\Command;
use InvalidArgumentException;

/**
 * Command-line twin of Admin → Invite communities, for Laravel Cloud Commands.
 *
 *   php artisan kolabing:invite-communities hello@cafe.com
 *   php artisan kolabing:invite-communities hello@cafe.com --category=running --limit=30 --apply
 *
 * Dry run by default: lists who would be invited. Pass --apply to send.
 */
class InviteCommunitiesToKolab extends Command
{
    protected $signature = 'kolabing:invite-communities
        {kolab : Kolab id, or a business profile id / email (uses its open auto listing, else its newest open offer)}
        {--city= : City name (default: the Kolab\'s city)}
        {--category= : Community type slug, e.g. running (default: all)}
        {--limit=50 : Most communities to invite in this send (max 500)}
        {--weekly-cap=2 : Skip communities already invited this many times in the last 7 days (max 7)}
        {--apply : Send the invites (without this flag the command only reports)}';

    protected $description = 'Invite communities, by in-app + push notification, to apply to a Kolab.';

    public function handle(KolabInviteService $invites): int
    {
        try {
            $kolab = $invites->resolveKolab((string) $this->argument('kolab'));
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $filters = [
            'city' => $this->option('city'),
            'category' => $this->option('category'),
            'limit' => (int) $this->option('limit'),
            'weekly_cap' => (int) $this->option('weekly-cap'),
        ];

        $preview = $invites->preview($kolab, $filters);

        $this->line("  kolab      {$kolab->id}  \"{$kolab->title}\"");
        $this->line('  city       '.$preview['city'].'   category '.($preview['category'] ?? 'all'));
        $this->line("  limit      {$preview['limit']}   weekly cap {$preview['weekly_cap']}");

        if ($preview['problems'] !== []) {
            foreach ($preview['problems'] as $problem) {
                $this->error("  cannot invite: {$problem}");
            }

            return self::FAILURE;
        }

        $this->line("  eligible   {$preview['total_eligible']}   this send {$preview['recipients']->count()}");
        foreach ($preview['recipients'] as $community) {
            /** @var Profile $community */
            $size = $community->communityProfile?->community_size;
            $this->line("    {$community->id}  ".($community->communityProfile?->name ?? $community->email).($size ? "  ({$size})" : ''));
        }

        if (! $this->option('apply')) {
            $this->info('Dry run — nothing was sent. Add --apply to send.');

            return self::SUCCESS;
        }

        $result = $invites->send($kolab, $filters);

        $this->info("Sent {$result['invited']} invite(s); {$result['skipped']} already invited; {$result['failed']} failed.");

        return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
