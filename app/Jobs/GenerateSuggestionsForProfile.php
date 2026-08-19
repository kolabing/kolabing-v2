<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Profile;
use App\Services\Suggestions\SuggestionGenerator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

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
