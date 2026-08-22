<?php

declare(strict_types=1);

namespace App\Services\Suggestions;

use App\Enums\SuggestionAudience;
use App\Enums\UserType;
use App\Models\KolabSuggestion;
use App\Models\Profile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The write side of the suggestion engine: one profile in, rows in
 * `kolab_suggestions` out.
 *
 * Everything it does is a composition of the three classes above it — the
 * finder loads the candidates, the scorer ranks them, the format suggester
 * proposes an event — so the only policy that lives here is what gets persisted
 * and how: the `min_score` floor, the `per_profile` cap, the one-row-per-pair
 * refresh, and the two failure boundaries.
 *
 * **One row per pair, forever.** The upsert key is `(viewer, counterpart)` and
 * deliberately excludes `batch_key`, which is "the date this pair was last
 * scored" rather than a generation bucket. An earlier draft of the design keyed
 * on it, which would have written a fresh row every night while the previous
 * thirteen were still inside their 14-day expiry — up to fourteen
 * near-identical cards per counterpart.
 *
 * Two consequences the payload has to respect:
 *
 * - `shown_at`, `clicked_at` and `dismissed_at` are never written by a refresh.
 *   The funnel and the dismissal live on the same row that gets re-scored, so
 *   including them in the payload would reset them every night.
 * - A dismissal is a cooldown, not a block. The finder excludes a pair
 *   dismissed inside `dismissal_cooldown_days`; the other half is here — a pair
 *   that reaches the write with an *expired* dismissal has it cleared, or the
 *   row would be refreshed forever and never be readable (`scopeLive` filters
 *   on `dismissed_at`).
 *
 * **Two failure boundaries, because the nightly pass covers every profile.** A
 * pair that cannot be scored, formatted or written is dropped with a log line
 * and the profile keeps its other suggestions; a profile that cannot be
 * processed at all is the *command's* problem, and it reports to Sentry and
 * moves on. `PairContext`'s invariants and `FormatSuggester`'s weekday
 * convention check are both reachable from real rows, so neither boundary is
 * theoretical.
 */
class SuggestionGenerator
{
    public function __construct(
        private readonly PairCandidateFinder $finder,
        private readonly SignalScorer $scorer,
        private readonly FormatSuggester $formatSuggester,
    ) {}

    /**
     * Score every candidate for one viewer and persist the survivors.
     *
     * Returns both halves of the outcome, because `written: 0` alone cannot tell
     * an empty platform from a batch in which every single write failed — and one
     * of those is an incident. `skipped` counts every pair this profile lost to a
     * failure: an invariant violation while the finder built the context, a
     * format that could not be proposed, or a write that raised. The per-pair
     * `Log::warning` carries the detail; this count is the signal that someone
     * should go and read it.
     *
     * @return array{written: int, skipped: int}
     */
    public function generateFor(Profile $viewer, bool $dryRun = false): array
    {
        $audience = $this->audienceFor($viewer);

        if ($audience === null) {
            return ['written' => 0, 'skipped' => 0];
        }

        $skipped = 0;
        $scored = $this->scoreCandidates($viewer, $audience, $skipped);

        $written = 0;

        foreach (array_slice($scored, 0, max(0, (int) config('suggestions.per_profile'))) as $candidate) {
            if ($dryRun) {
                $written++;

                continue;
            }

            if ($this->persist($candidate)) {
                $written++;
            } else {
                $skipped++;
            }
        }

        return ['written' => $written, 'skipped' => $skipped];
    }

    /**
     * `kolab_suggestions.audience` mirrors the viewer's own `user_type`, and
     * attendees are never an audience — they neither create Kolabs nor apply to
     * them, so there is no pair to propose.
     */
    private function audienceFor(Profile $viewer): ?SuggestionAudience
    {
        return match ($viewer->user_type) {
            UserType::Business => SuggestionAudience::Business,
            UserType::Community => SuggestionAudience::Community,
            default => null,
        };
    }

    /**
     * Scores, formats and filters every candidate, then orders them.
     *
     * The format is built here rather than after the cap so that a pair whose
     * format cannot be built frees its slot for the next-best pair instead of
     * silently shortening the list.
     *
     * The sort is by score descending with the counterpart id as a tie-break.
     * Both halves matter: the same pair is re-scored in place every night, and a
     * tie resolved by whatever order Postgres returned the candidates in would
     * move cards on and off the cap from night to night with nothing behind it.
     *
     * `$skipped` is accumulated, not assigned: the finder reports the pairs it
     * dropped while building their contexts — failures this class never sees a
     * `PairContext` for — and the scoring failures below add to that same total.
     *
     * @param  int  $skipped  out-parameter, incremented by every pair lost to a failure
     * @return array<int, array{context: PairContext, score: int, confidence: string, signals: array<int, array<string, mixed>>, format: array<string, mixed>}>
     */
    private function scoreCandidates(Profile $viewer, SuggestionAudience $audience, int &$skipped): array
    {
        $minScore = (int) config('suggestions.min_score');
        $scored = [];
        $skippedContexts = 0;

        $candidates = $this->finder->candidatesFor($viewer, $audience, $skippedContexts);
        $skipped += $skippedContexts;

        foreach ($candidates as $context) {
            try {
                $result = $this->scorer->score($context);

                if ($result['score'] < $minScore) {
                    continue;
                }

                $scored[] = [
                    'context' => $context,
                    'score' => $result['score'],
                    'confidence' => $result['confidence'],
                    'signals' => $result['signals'],
                    'format' => $this->formatSuggester->suggest(
                        $context,
                        $this->intArray($context->evidence['series_weekdays'] ?? []),
                        is_string($context->evidence['series_time_of_day'] ?? null)
                            ? $context->evidence['series_time_of_day']
                            : null,
                        $this->intArray($context->evidence['past_event_weekdays'] ?? []),
                    ),
                ];
            } catch (Throwable $e) {
                $skipped++;
                $this->skip($context->viewerProfileId, $context->counterpartProfileId, 'score', $e);
            }
        }

        usort(
            $scored,
            static fn (array $a, array $b): int => $b['score'] <=> $a['score']
                ?: strcmp($a['context']->counterpartProfileId, $b['context']->counterpartProfileId)
        );

        return $scored;
    }

    /**
     * `event_series.byweekday` and the past-event weekdays are read out of a
     * jsonb document, so nothing guarantees they arrive as a list of ints.
     * `FormatSuggester` is typed on `int` and would raise a TypeError on a
     * string — a per-pair failure where a per-pair *skip* is wanted, so the
     * coercion happens here.
     *
     * Everything numeric is coerced rather than dropped. A float `3.0` is
     * Thursday written by a JSON decoder that saw no decimal point, and dropping
     * it would silently cost the pair its cadence; `FormatSuggester` still range
     * checks the result, so a genuinely nonsensical value is rejected there
     * rather than passed off as a weekday. Non-numeric entries are dropped,
     * because there is nothing to coerce them to.
     *
     * @return array<int, int>
     */
    private function intArray(mixed $values): array
    {
        if (! is_array($values)) {
            return [];
        }

        return array_values(array_map(
            static fn (mixed $value): int => (int) $value,
            array_filter($values, static fn (mixed $value): bool => is_int($value)
                || is_float($value)
                || (is_string($value) && is_numeric($value)))
        ));
    }

    /**
     * Refresh the pair's one row, or create it.
     *
     * `firstOrNew` + `fill` + `save` rather than `updateOrCreate` for exactly one
     * reason: whether `dismissed_at` is cleared depends on the value already
     * stored, and `updateOrCreate` cannot see it. The key, the payload and the
     * single write are otherwise identical.
     *
     * @param  array{context: PairContext, score: int, confidence: string, signals: array<int, array<string, mixed>>, format: array<string, mixed>}  $candidate
     */
    private function persist(array $candidate): bool
    {
        $context = $candidate['context'];

        try {
            $suggestion = KolabSuggestion::query()->firstOrNew([
                'viewer_profile_id' => $context->viewerProfileId,
                'counterpart_profile_id' => $context->counterpartProfileId,
            ]);

            $payload = [
                'audience' => $context->audience,
                'city_id' => $this->cityIdFor($context),
                'score' => $candidate['score'],
                'confidence' => $candidate['confidence'],
                'signals' => $candidate['signals'],
                'suggested_format' => $candidate['format'],
                'evidence' => $context->evidence,
                'batch_key' => Carbon::today()->toDateString(),
                'expires_at' => Carbon::now()->addDays((int) config('suggestions.expires_after_days')),
            ];

            if ($this->dismissalHasExpired($suggestion)) {
                $payload['dismissed_at'] = null;
            }

            $suggestion->fill($payload)->save();

            return true;
        } catch (Throwable $e) {
            $this->skip($context->viewerProfileId, $context->counterpartProfileId, 'write', $e);

            return false;
        }
    }

    /**
     * The generator's half of the dismissal cooldown. The finder never hands
     * over a pair dismissed *inside* the window, so in production this is only
     * ever true — but it is written as a condition rather than an unconditional
     * `null` because the two halves live in different classes and only this one
     * can see the stored timestamp. Day granularity, matching every window in
     * the finder: a cooldown measured to the second would expire mid-batch
     * depending on how long the queue took to reach this profile.
     */
    private function dismissalHasExpired(KolabSuggestion $suggestion): bool
    {
        if ($suggestion->dismissed_at === null) {
            return false;
        }

        $cooldownSince = Carbon::today()->subDays((int) config('suggestions.dismissal_cooldown_days'));

        return $suggestion->dismissed_at->lessThan($cooldownSince);
    }

    /**
     * The city of the proposed event, resolved to the **business** side of the
     * pair with the community side as a fallback.
     *
     * **Do not "fix" this to viewer-relative.** Two rows describe every pair —
     * one addressed to each side — and they must agree about where the event
     * would happen, so the resolution cannot depend on which side is reading:
     * viewer-relative resolution would store two different cities for one
     * proposed event, and the digest groups on `(audience, batch_key)`. The
     * business side is the anchored one: a venue promotion happens at its venue
     * and a product promotion ships from its address, while the community's city
     * is only where it happens to be registered. A business viewer additionally
     * matches into its `target_city_ids`, so "the counterpart's city" would also
     * have moved as the viewer widened its reach.
     */
    private function cityIdFor(PairContext $context): ?string
    {
        return $context->audience === SuggestionAudience::Business
            ? ($context->viewerCityId ?? $context->counterpartCityId)
            : ($context->counterpartCityId ?? $context->viewerCityId);
    }

    private function skip(string $viewerId, string $counterpartId, string $stage, Throwable $e): void
    {
        Log::warning('Skipped a suggestion pair', [
            'stage' => $stage,
            'viewer_profile_id' => $viewerId,
            'counterpart_profile_id' => $counterpartId,
            'exception' => $e->getMessage(),
        ]);
    }
}
