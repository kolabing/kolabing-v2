<?php

declare(strict_types=1);

namespace App\Services\Suggestions;

use App\Models\Kolab;
use App\Models\KolabSuggestion;
use App\Models\Profile;
use App\Services\PostHog\PostHogService;

/**
 * The suggestion funnel, as PostHog sees it (BE-NF-39 §3.9).
 *
 * `suggestion_shown → suggestion_clicked → suggestion_converted`, with
 * `suggestion_dismissed` as the negative branch. This exists as one class rather
 * than four `capture()` calls scattered across SuggestionReader and KolabService
 * because two invariants have to hold for *every* event, and an invariant spread
 * over four call sites is an invariant that will be broken by the fifth:
 *
 * **1. `audience` is always tagged.** BE-NF-39 launches on both sides at once. A
 * business-side win and a community-side flop average into one meaningless
 * number without it, so the tag is not a nice-to-have breakdown — it is the
 * reason the launch is measurable at all.
 *
 * **2. The counterpart's identity never ships.** A blurred card withholds the
 * counterpart's name, avatar *and* id from a non-subscribed business on purpose
 * (SuggestionResource::counterpart). An event payload is a second, durable copy
 * of the same data in a third-party processor, so `counterpart_profile_id`,
 * names, avatars and `evidence` (which carries other rows' ids, each resolving
 * to both parties) are deliberately absent. What ships is the *shape* of the
 * match — score, confidence, which signals fired — which is what tunes the
 * weights; who was matched is not needed to answer any product question here.
 *
 * The distinct id is always a Profile model, never an id string: PostHogService
 * only honours `analytics_opt_out` when it is handed a model, so capturing
 * against a bare id would silently route around a user's consent choice.
 */
class SuggestionTelemetry
{
    public function __construct(
        private readonly PostHogService $postHog,
    ) {}

    /**
     * One event per card, on its **first** impression only — the caller passes
     * exactly the rows whose `shown_at` it just stamped.
     *
     * That bound is deliberate and it is what keeps the volume sane. The feed is
     * capped at `suggestions.per_profile` live rows (5), so a first serve emits
     * at most five events and every re-serve of the same page emits none. It also
     * keeps the event's meaning identical to the column's: if the event fired per
     * page load while `shown_at` recorded the first impression, the same funnel
     * step would have two different denominators, and a card scrolled past ten
     * times would inflate the top of the funnel tenfold.
     *
     * @param  array<int, KolabSuggestion>  $suggestions
     */
    public function shown(Profile $viewer, array $suggestions): void
    {
        foreach ($suggestions as $suggestion) {
            $this->capture($viewer, 'suggestion_shown', $suggestion);
        }
    }

    /**
     * First open only, mirroring `clicked_at`. Time-to-click is deliberately not
     * a property — PostHog already has both events' timestamps, and a duplicated
     * derived value is one more thing that can disagree with the row.
     */
    public function clicked(KolabSuggestion $suggestion): void
    {
        $this->capture($this->viewerOf($suggestion), 'suggestion_clicked', $suggestion);
    }

    /**
     * `was_clicked` separates the two dismissals that mean different things: a
     * card rejected at a glance from the list (the match is wrong in an obvious
     * way) versus one opened, read and then rejected (wrong in a subtle way).
     * That distinction is the one the signal weights can actually be tuned
     * against; a single undifferentiated dismissal count cannot be.
     */
    public function dismissed(KolabSuggestion $suggestion): void
    {
        $this->capture($this->viewerOf($suggestion), 'suggestion_dismissed', $suggestion, [
            'was_clicked' => $suggestion->clicked_at !== null,
        ]);
    }

    /**
     * The step the business case rests on. `kolab_id` continues the chain into
     * the Kolab's own lifecycle, and `intent_type` is carried because PostHog
     * cannot join back to the database — without it the event cannot answer
     * "which kind of Kolab do suggestions actually produce" without an export.
     */
    public function converted(Profile $viewer, KolabSuggestion $suggestion, Kolab $kolab): void
    {
        $this->capture($viewer, 'suggestion_converted', $suggestion, [
            'kolab_id' => $kolab->id,
            'intent_type' => $kolab->intent_type->value,
        ]);
    }

    /**
     * The viewer is resolved from the row rather than from the caller, so an
     * event can never be attributed to the wrong profile. Null is possible and
     * not an error: `Profile` soft-deletes, and a viewer deactivated between the
     * read and the write should generate no telemetry at all.
     */
    private function viewerOf(KolabSuggestion $suggestion): ?Profile
    {
        $suggestion->loadMissing('viewerProfile');

        return $suggestion->viewerProfile;
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function capture(?Profile $viewer, string $event, KolabSuggestion $suggestion, array $extra = []): void
    {
        if ($viewer === null) {
            return;
        }

        $this->postHog->capture($viewer, $event, [
            ...$this->properties($suggestion),
            ...$extra,
        ]);
    }

    /**
     * The four properties every step of the funnel carries.
     *
     * - `audience` — invariant 1 above.
     * - `suggestion_id` — the join key. Without it the four steps can only be
     *   compared as aggregates; with it a PostHog funnel follows one card from
     *   impression to Kolab, which is the difference between "conversion is 4%"
     *   and "these cards converted".
     * - `score` and `confidence` — the two numbers the generator staked its
     *   guess on. Conversion rate broken down by them is the only feedback loop
     *   `config/suggestions.php` has; the weights cannot be tuned without it.
     * - `signal_keys` — *which* reasons fired, sorted so that one combination
     *   always renders as one value in a breakdown. Keys only, never
     *   `reason_params`: the params carry counterpart-derived detail (their
     *   category, their attendance) and would smuggle the match's subject into a
     *   payload that is supposed to describe only its shape.
     *
     * @return array<string, mixed>
     */
    private function properties(KolabSuggestion $suggestion): array
    {
        return [
            'audience' => $suggestion->audience->value,
            'suggestion_id' => (string) $suggestion->id,
            'score' => (int) $suggestion->score,
            'confidence' => $suggestion->confidence->value,
            'signal_keys' => $this->signalKeys($suggestion),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function signalKeys(KolabSuggestion $suggestion): array
    {
        $signals = is_array($suggestion->signals) ? $suggestion->signals : [];

        $keys = [];

        foreach ($signals as $signal) {
            if (is_array($signal) && is_string($signal['key'] ?? null) && $signal['key'] !== '') {
                $keys[] = $signal['key'];
            }
        }

        sort($keys);

        return array_values(array_unique($keys));
    }
}
