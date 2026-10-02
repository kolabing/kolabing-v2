<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\AutoKolabService;
use Illuminate\Console\Command;
use InvalidArgumentException;

/**
 * Command-line twin of Admin → Auto-kolabs, for Laravel Cloud Commands.
 *
 *   php artisan kolabing:auto-kolab hello@cafe.com runners@club.com --date=2026-10-20
 *   php artisan kolabing:auto-kolab hello@cafe.com runners@club.com --date=2026-10-20 --apply
 *
 * Dry run by default: prints what would be created and why it would be
 * refused. Pass --apply to create it.
 */
class CreateAutoKolab extends Command
{
    protected $signature = 'kolabing:auto-kolab
        {business : Business profile id, email, or exact business name}
        {community : Community profile id, email, or exact community name}
        {--date= : Kolab date, YYYY-MM-DD (optional; today or later)}
        {--title= : Kolab title (default: "<business> x <community>")}
        {--silent : Create it without notifying either side}
        {--apply : Create it (without this flag the command only reports)}';

    protected $description = 'Set up a Kolab between a business and a community that is already matched (as if the community applied and the business accepted).';

    public function handle(AutoKolabService $autoKolabs): int
    {
        try {
            $business = $autoKolabs->resolveBusiness((string) $this->argument('business'));
            $community = $autoKolabs->resolveCommunity((string) $this->argument('community'));
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $options = [
            'date' => $this->option('date'),
            'title' => $this->option('title'),
            'notify' => ! $this->option('silent'),
        ];

        $plan = $autoKolabs->plan($business, $community, $options);

        $this->line("  business   {$business->id}  ".($plan['business_name'] ?? '—'));
        $this->line("  community  {$community->id}  ".($plan['community_name'] ?? '—'));
        $this->line('  title      '.($plan['title'] ?? '—'));
        $this->line('  city       '.($plan['city'] ?? '—').'   type '.($plan['intent_type'] ?? '—'));
        $this->line('  date       '.($plan['date'] ?? 'not set'));
        $this->line('  notify     '.($options['notify'] ? 'yes (in-app + push + email, per their settings)' : 'no (--silent)'));

        foreach ($plan['warnings'] as $warning) {
            $this->warn("  warning: {$warning}");
        }

        if ($plan['problems'] !== []) {
            foreach ($plan['problems'] as $problem) {
                $this->error("  cannot create: {$problem}");
            }

            return self::FAILURE;
        }

        if (! $this->option('apply')) {
            $this->info('Dry run — nothing was written. Add --apply to create it.');

            return self::SUCCESS;
        }

        try {
            $result = $autoKolabs->create($business, $community, $options);
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Auto-kolab created.');
        $this->line('  kolab_id          '.$result['kolab']->id);
        $this->line('  application_id    '.$result['application']->id);
        $this->line('  collaboration_id  '.$result['collaboration']->id.' ('.$result['collaboration']->status->value.')');

        return self::SUCCESS;
    }
}
