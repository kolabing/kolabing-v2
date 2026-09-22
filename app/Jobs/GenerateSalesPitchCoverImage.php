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
 * Draw a pitch's cover out of band (BE-FX-61).
 *
 * This used to run inside the admin request and produced a Cloudflare 504 in
 * production. Image generation measured ~25s on its own and the upload to R2
 * follows it; no gateway is willing to hold a connection open for that, and
 * raising a timeout somewhere only moves the failure.
 *
 * Carries the id rather than the model, like {@see GenerateSuggestionsForProfile}:
 * a draft deleted between dispatch and execution is a no-op, not a failure.
 *
 * `tries = 1` deliberately. Every attempt is billed by OpenAI, and the common
 * failures here — a content-policy refusal on a generated prompt, a missing key —
 * are not transient, so retrying spends money to fail again. The client already
 * retries once at the HTTP layer for a genuine 429/5xx.
 */
class GenerateSalesPitchCoverImage implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    /**
     * Generous, because the work genuinely is slow — but finite, so a hung
     * request cannot occupy a worker indefinitely. Comfortably above the
     * client's own `image_timeout`, so the HTTP call gives up first and the
     * failure is reported with its real cause rather than as a timed-out job.
     */
    public int $timeout = 300;

    public function __construct(public readonly string $draftId) {}

    public function handle(SalesOutreachService $outreach): void
    {
        $draft = SalesOutreachDraft::query()->find($this->draftId);

        if ($draft === null) {
            return;
        }

        $outreach->drawCoverImage($draft);
    }

    /**
     * Record why, on the draft itself. The maintainer is not watching a response
     * any more, so a failure that only reaches the log is a failure they will
     * experience as a spinner that never resolves.
     */
    public function failed(?Throwable $e): void
    {
        Log::error('Sales pitch cover image job failed', [
            'draft_id' => $this->draftId,
            'error' => $e?->getMessage(),
        ]);

        SalesOutreachDraft::query()
            ->where('id', $this->draftId)
            ->update([
                'cover_image_status' => SalesOutreachDraft::IMAGE_FAILED,
                'cover_image_error' => mb_substr($e?->getMessage() ?? 'Unknown error', 0, 500),
            ]);
    }
}
