<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Profile;
use App\Services\Suggestions\SuggestionGenerator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Score one profile's suggestions out of band, so a freshly-registered account
 * does not have to wait for the 04:00 pass to see a card.
 *
 * Carries the id rather than the model: the job is dispatched from the tail of
 * registration and a serialised `Profile` would be re-hydrated anyway. A profile
 * deleted between dispatch and execution is a no-op rather than a failure.
 *
 * Gated on the same feature flag as the command. The flag ships false so a batch
 * can be run and inspected on production data before anyone sees a card, and a
 * registration writing rows behind it would defeat that.
 */
class GenerateSuggestionsForProfile implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(public readonly string $profileId) {}

    /**
     * The single entry point for every trigger: registration and profile
     * completion both go through here, so the rule for *when* a pass is queued
     * has one definition.
     *
     * The rule is the crossing, not the state — a pass is queued the moment a
     * profile becomes complete and never again. That is the debounce: a business
     * that edits its categories five times queues nothing, because it was
     * already complete before each edit. The alternative (queue on every edit,
     * relying on the job being idempotent) is safe for the *data* — the upsert
     * cannot grow the row count — but it would put a full scoring pass on the
     * queue for every profile save on the platform, and the 04:00 pass picks
     * those edits up within a day anyway.
     *
     * Completeness itself is `Profile::onboardingCompleted()`, the predicate the
     * onboarding drip already uses to decide whether to send its
     * complete-profile nudge. Deliberately reused rather than restated: a second
     * definition of "complete" would drift from the drip's, and the two would
     * then disagree about the same profile.
     *
     * `$wasCompleteBefore` has to be sampled by the caller before it mutates the
     * profile; a registration passes `false`, because a profile that did not
     * exist was not complete. Before completion there is nothing to score — an
     * incomplete profile has no city, and `PairCandidateFinder` returns no
     * candidates without one — so an ungated dispatch at registration would
     * queue a pass that provably writes nothing.
     *
     * Fully isolated: suggestions are peripheral, and a queue that cannot be
     * reached must never propagate into (and roll back) onboarding.
     */
    public static function dispatchIfJustCompleted(Profile $profile, bool $wasCompleteBefore): void
    {
        if ($wasCompleteBefore || $profile->isAttendee() || ! $profile->onboardingCompleted()) {
            return;
        }

        try {
            self::dispatch((string) $profile->id);
        } catch (Throwable $e) {
            Log::warning('Failed to queue a suggestion pass for a newly completed profile', [
                'profile_id' => $profile->id,
                'exception' => $e->getMessage(),
            ]);
        }
    }

    public function handle(SuggestionGenerator $generator): void
    {
        if (! config('suggestions.enabled')) {
            return;
        }

        $profile = Profile::query()->find($this->profileId);

        if ($profile === null) {
            return;
        }

        $generator->generateFor($profile);
    }
}
