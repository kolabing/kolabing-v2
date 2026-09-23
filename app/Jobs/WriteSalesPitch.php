<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\SalesOutreachDraft;
use App\Services\SalesOutreach\SalesOutreachService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Research the pair and write the pitch, off the request (BE-FX-63).
 *
 * Two `gpt-5.2` calls plus the website and weather lookups. From a laptop that is
 * about 23 seconds, which is why it originally shipped synchronously — but the same
 * calls run three to four times slower from production, and that is how a form POST
 * came to exceed Cloudflare's 100s edge timeout.
 *
 * `tries = 1`, for the same reason as the cover job: every attempt is billed, and a
 * retry of a draft whose generation already half-succeeded would hand the maintainer
 * a second set of ideas for the same pair. The HTTP client already retries once for a
 * genuine 429/5xx.
 */
class WriteSalesPitch implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    /**
     * Comfortably above the two text calls plus research at production's pace, and
     * above the client's own 90s per-call timeout, so a stalled HTTP call fails with
     * its real cause rather than as a killed job.
     */
    public int $timeout = 300;

    /**
     * @param  int|null  $ideaIndex  null = write the pitch from scratch; set = rewrite
     *                               the copy around an already-generated idea
     */
    public function __construct(
        public readonly string $draftId,
        public readonly ?int $ideaIndex = null,
    ) {}

    public function handle(SalesOutreachService $outreach): void
    {
        $draft = SalesOutreachDraft::query()->find($this->draftId);

        if ($draft === null) {
            return;
        }

        if ($this->ideaIndex === null) {
            $outreach->writePitch($draft);

            return;
        }

        $outreach->rewriteForIdea($draft, $this->ideaIndex);
    }

    /**
     * A draft stuck at "writing…" forever is worse than one that says why it failed,
     * because the page polls and the maintainer has no way to tell the difference
     * between slow and dead.
     */
    public function failed(?Throwable $e): void
    {
        Log::error('Sales pitch generation job failed', [
            'draft_id' => $this->draftId,
            'error' => $e?->getMessage(),
        ]);

        SalesOutreachDraft::query()
            ->where('id', $this->draftId)
            ->update([
                'generation_status' => SalesOutreachDraft::GENERATION_FAILED,
                'generation_error' => mb_substr($e?->getMessage() ?? 'Unknown error', 0, 500),
            ]);
    }
}
