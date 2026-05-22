<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\CollaborationStatus;
use App\Enums\KolabStatus;
use App\Enums\OfferStatus;
use App\Models\CollabOpportunity;
use App\Models\Collaboration;
use App\Models\Kolab;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * One-off data cleanup. DRY RUN by default — pass --force to apply.
 *
 *   php artisan kolabing:cleanup-data                       # dry run, lists candidates
 *   php artisan kolabing:cleanup-data --force               # close orphans + test kolabs, cancel orphan collabs
 *   php artisan kolabing:cleanup-data --force --delete-test # also HARD-DELETE the test kolabs
 *
 * "Orphan" = a post/collaboration whose creator/participant profile was deleted
 * (soft-deleted), which is what surfaces as "Unknown" in Explore. "Test" kolabs
 * are matched by conservative heuristics and ALWAYS listed for review first.
 */
class CleanupData extends Command
{
    protected $signature = 'kolabing:cleanup-data {--force} {--delete-test}';

    protected $description = 'One-off cleanup of orphaned posts/collaborations from deleted accounts and obvious test-data kolabs (dry-run unless --force).';

    public function handle(): int
    {
        $force = (bool) $this->option('force');
        $deleteTest = (bool) $this->option('delete-test');

        $this->warn($force
            ? '>>> APPLYING changes <<<'
            : 'DRY RUN — nothing will change. Review the lists, then re-run with --force.');

        // 1) Orphaned posts (creator profile deleted) -> close so they leave Explore.
        $orphanKolabs = Kolab::whereDoesntHave('creatorProfile')
            ->whereIn('status', [KolabStatus::Draft, KolabStatus::Published])
            ->get();
        $orphanOpps = CollabOpportunity::whereDoesntHave('creatorProfile')
            ->whereIn('status', [OfferStatus::Draft, OfferStatus::Published])
            ->get();
        // Orphaned active collaborations (a participant was deleted) -> cancel.
        $orphanCollabs = Collaboration::where(fn ($q) => $q
            ->whereDoesntHave('creatorProfile')
            ->orWhereDoesntHave('applicantProfile'))
            ->whereIn('status', [CollaborationStatus::Scheduled, CollaborationStatus::Active])
            ->get();

        $this->section('Orphaned kolabs (deleted creator)', $orphanKolabs, fn ($k) => "{$k->id} :: ".$this->trim($k->title));
        $this->section('Orphaned opportunities (deleted creator)', $orphanOpps, fn ($o) => "{$o->id} :: ".$this->trim($o->title));
        $this->section('Orphaned active collaborations', $orphanCollabs, fn ($c) => "{$c->id} (status {$c->status->value})");

        // 2) Test-data kolabs (heuristic) -> close (or hard-delete with --delete-test).
        $testKolabs = Kolab::whereIn('status', [KolabStatus::Draft, KolabStatus::Published])
            ->get()
            ->filter(fn (Kolab $k): bool => $this->looksLikeTestData($k));
        $this->section('Test-looking kolabs', $testKolabs, fn ($k) => "{$k->id} :: title=".$this->trim($k->title, 30).' :: desc='.$this->trim($k->description, 40));

        if (! $force) {
            $this->info('Dry run complete. Nothing changed.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($orphanKolabs, $orphanOpps, $orphanCollabs, $testKolabs, $deleteTest): void {
            foreach ($orphanKolabs as $k) {
                $k->update(['status' => KolabStatus::Closed]);
            }
            foreach ($orphanOpps as $o) {
                $o->update(['status' => OfferStatus::Closed]);
            }
            foreach ($orphanCollabs as $c) {
                $c->update(['status' => CollaborationStatus::Cancelled]);
            }
            foreach ($testKolabs as $k) {
                $deleteTest ? $k->delete() : $k->update(['status' => KolabStatus::Closed]);
            }
        });

        $this->info('Cleanup applied.');

        return self::SUCCESS;
    }

    /**
     * Conservative heuristics for obvious test data. Anything matched is listed
     * in the dry run for human review before --force.
     */
    private function looksLikeTestData(Kolab $kolab): bool
    {
        $title = mb_strtolower(trim((string) $kolab->title));
        $desc = trim((string) $kolab->description);

        // "test" as a standalone word in the title.
        if (preg_match('/\btest\b/i', $title) === 1) {
            return true;
        }

        // Very short nonsense title (e.g. "asd", "qq").
        if ($title !== '' && mb_strlen($title) <= 3 && preg_match('/^[a-z]+$/', $title) === 1) {
            return true;
        }

        // Description that is one long run of letters with no spaces (e.g. "asdkfjasldkfj").
        if ($desc !== '' && mb_strlen($desc) >= 12 && preg_match('/^[A-Za-z]+$/', $desc) === 1) {
            return true;
        }

        return false;
    }

    /**
     * @param  Collection<int, mixed>  $items
     * @param  callable(mixed): string  $format
     */
    private function section(string $label, Collection $items, callable $format): void
    {
        $this->line('');
        $this->info("{$label}: {$items->count()}");
        foreach ($items as $item) {
            $this->line('  - '.$format($item));
        }
    }

    private function trim(?string $value, int $width = 50): string
    {
        return mb_strimwidth((string) $value, 0, $width, '…');
    }
}
