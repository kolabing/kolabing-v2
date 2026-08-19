<?php

declare(strict_types=1);

namespace App\Services\Suggestions;

use App\Models\KolabSuggestion;
use App\Models\Profile;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * The read side of `kolab_suggestions`: which rows a profile may see, in what
 * order, and the funnel timestamps a read leaves behind.
 *
 * Separate from SuggestionGenerator because the two have opposite concerns — the
 * generator decides what is *true* about a pair, this decides what is *still
 * worth showing* — and because the digest sender will want the same "live rows
 * for this viewer" definition without going through a controller.
 */
class SuggestionReader
{
    /**
     * Live suggestions addressed to one profile, best first, with `shown_at`
     * stamped on whatever this page actually served.
     *
     * Three filters compose here and all three matter:
     *
     * - `forViewer()` — the IDOR guard at the query level. The policy guards the
     *   detail and dismiss routes; a list has no single row to authorize, so
     *   scoping *is* the authorization.
     * - `live()` — not expired, not dismissed, not already converted.
     * - the counterpart still existing — `Profile` soft-deletes, so a counterpart
     *   deactivated after the batch ran makes `whereHas` fail and the row simply
     *   disappears. Without it the row would still be served and the card would
     *   render a nameless partner, which is the "stale row must be invisible, not
     *   fatal" rule.
     *
     * The `created_at` tie-break is not cosmetic: `score` is a small integer, so
     * ties are common, and a page boundary that fell inside an unordered tie
     * would drop or repeat rows between page 1 and page 2.
     *
     * @return LengthAwarePaginator<int, KolabSuggestion>
     */
    public function liveFor(Profile $viewer, int $perPage): LengthAwarePaginator
    {
        /** @var LengthAwarePaginator<int, KolabSuggestion> $paginator */
        $paginator = KolabSuggestion::query()
            ->live()
            ->forViewer($viewer)
            ->whereHas('counterpartProfile')
            ->with([
                'counterpartProfile',
                'counterpartProfile.businessProfile',
                'counterpartProfile.communityProfile',
            ])
            ->orderByDesc('score')
            ->orderByDesc('created_at')
            ->paginate($perPage);

        $this->markShown($paginator->getCollection()->all());

        return $paginator;
    }

    /**
     * Stamps `shown_at` on the rows this page served, in one statement.
     *
     * One `whereIn` update rather than a save per row: a page is up to a hundred
     * rows and this runs on every impression of the feed. `whereNull` keeps the
     * *first* impression's timestamp, which is what makes the column mean
     * "when did this card first reach a human" rather than "when was it last
     * scrolled past" — the funnel measurement the whole feature is judged on.
     *
     * The whole page is handed to `whereIn` and `whereNull` decides — rather than
     * the caller pre-filtering the already-stamped rows in PHP, which would make
     * the SQL guard unreachable and therefore untestable. It also means two
     * simultaneous serves of the same page cannot overwrite each other's first
     * impression, which a read-then-write in PHP would allow.
     *
     * The in-memory models are stamped with the same value so the response
     * carries a `shown_at` rather than a null the row no longer has. It is the
     * value *this* request proposed, not necessarily the one that won: if a
     * concurrent serve stamped first, the `whereNull` above matched nothing and
     * the stored timestamp is the other request's, a few milliseconds earlier.
     * The field is a funnel marker read to the second at best, so re-selecting
     * the page to reconcile it would buy nothing. Only rows that were null are
     * touched, so a re-served card keeps its real first impression.
     *
     * @param  array<int, KolabSuggestion>  $suggestions
     */
    private function markShown(array $suggestions): void
    {
        if ($suggestions === []) {
            return;
        }

        $now = now();

        KolabSuggestion::query()
            ->whereIn('id', array_map(static fn (KolabSuggestion $s): string => $s->id, $suggestions))
            ->whereNull('shown_at')
            ->update(['shown_at' => $now]);

        foreach ($suggestions as $suggestion) {
            if ($suggestion->shown_at !== null) {
                continue;
            }

            $suggestion->setAttribute('shown_at', $now);
            $suggestion->syncOriginalAttribute('shown_at');
        }
    }

    /**
     * Whether a row is still worth rendering, using the same `live()` scope the
     * list uses so the two definitions cannot drift. One indexed primary-key
     * lookup, deliberately rather than re-deriving "not expired, not dismissed,
     * not converted" in PHP: two implementations of that rule would eventually
     * let the detail serve a card the list had already retired.
     */
    public function isLive(KolabSuggestion $suggestion): bool
    {
        return $suggestion->newQuery()->live()->whereKey($suggestion->getKey())->exists();
    }

    /**
     * Detail view. `clicked_at` records the first open for the same reason
     * `shown_at` records the first impression: the funnel measures conversion
     * from a card to a Kolab, not how often a card was revisited.
     *
     * Reads are gated on isLive() by the controller; dismissal deliberately is
     * not. See dismiss() for why the two differ.
     */
    public function markClicked(KolabSuggestion $suggestion): KolabSuggestion
    {
        if ($suggestion->clicked_at === null) {
            $suggestion->forceFill(['clicked_at' => now()])->save();
        }

        return $suggestion->load([
            'counterpartProfile',
            'counterpartProfile.businessProfile',
            'counterpartProfile.communityProfile',
        ]);
    }

    /**
     * Idempotent: a second dismissal keeps the first timestamp, because
     * `dismissal_cooldown_days` is measured from it — re-stamping would silently
     * extend the suppression window every time a client retried.
     *
     * Deliberately **not** gated on isLive(), unlike the detail read, and the
     * asymmetry is the intended behaviour rather than an oversight. A read of an
     * expired row is noise: it renders a card the list has already retired and
     * stamps `clicked_at`, corrupting the funnel this feature is judged on. A
     * *write* to one is useful: a client holding a page fetched minutes ago
     * should be able to say "not interested" without an error, and the
     * `dismissed_at` it lands feeds the cooldown that keeps the pair from being
     * proposed again — worth strictly more than a 404 would be.
     */
    public function dismiss(KolabSuggestion $suggestion): void
    {
        if ($suggestion->dismissed_at !== null) {
            return;
        }

        $suggestion->forceFill(['dismissed_at' => now()])->save();
    }
}
