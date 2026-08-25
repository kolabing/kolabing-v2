<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ChallengeCompletionService;
use Illuminate\Console\Command;

/**
 * Close pending challenge requests whose event has ended (kolabing-app#154).
 *
 * The read paths already refuse an expired request, so this is about the data
 * telling the truth rather than about correctness: a table slowly filling with
 * rows that say "pending" about last month is how a status stops meaning
 * anything, and every count over `pending` becomes a lie.
 */
class ExpirePendingChallenges extends Command
{
    protected $signature = 'app:expire-pending-challenges';

    protected $description = 'Mark pending challenge requests whose event has ended as expired.';

    public function handle(ChallengeCompletionService $service): int
    {
        $count = $service->expireStale();

        $this->info("Expired {$count} pending challenge request(s).");

        return self::SUCCESS;
    }
}
