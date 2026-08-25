# How the suggestion engine works

> BE-NF-39, merged 2026-08-22 in [#199](https://github.com/kolabing/kolabing-v2/pull/199).
> Design spec: `docs/superpowers/specs/2026-08-19-event-suggestion-system-design.md`.
> Rules: `ROLES-AND-PERMISSIONS.md` §2.13 · Code map: `ROLES-BACKEND-DB-MAP.md` §19.
>
> **Currently off in production** (`SUGGESTIONS_ENABLED=false`). This document exists so
> we can decide what to change before turning it on.

## 1. What it produces

One card, addressed to one side of a pair. Not a list of profiles — a proposal:

> **Sunday morning run + coffee**
> Run Club BCN · match 82 · confidence high
> — Run clubs and café businesses collaborate often.
> — About 1.4 km apart.
> — Delivered 5 posts across past Kolabs, rated 4.6.
> Expect around 45 people · *From attendance at their past events*
> Sunday, 09:00 · *The day their recurring series runs*
> You'd offer: coffee, venue → You'd ask for: social media
> **[ Create this Kolab ]  [ Not interested ]**

The same engine runs in both directions. A business is shown communities; a community is
shown businesses. Everything below is symmetric unless it says otherwise.

"Create this Kolab" opens the normal create form, pre-filled. That is the whole
conversion mechanic — the card is only the pitch.

## 2. The pipeline

Five stages, run nightly at 04:00 by `app:generate-suggestions`, and once per profile
when someone registers or completes their profile.

| Stage | Class | Job |
|---|---|---|
| 1. Narrow | `PairCandidateFinder` | The **only** class that queries. 8–9 queries per profile, regardless of how many candidates come back. |
| 2. Score | `SignalScorer` | Pure function: context in, 0–100 out. No database, no clock, no randomness. |
| 3. Propose | `FormatSuggester` | Turns the same context into a weekday, a time, an expected headcount, an offer and an ask. |
| 4. Write | `SuggestionGenerator` | One row per pair, refreshed in place. Prunes dead rows in the same pass. |
| 5. Serve | `SuggestionReader` + `SuggestionResource` | Renders sentences in the reader's locale, applies the identity blur, stamps the funnel. |

Stage 1 excludes: a different city, blocked pairs (either direction), pairs with an open
application or a live collaboration, pairs dismissed inside the last 60 days, and
counterparts too empty to be proposable. Every exclusion has a test that fails if the
exclusion is removed — not merely a test that passes.

## 3. The six signals

Weights live in `config/suggestions.php`, so tuning is a config change, not a code change.

| Signal | Weight | Reads |
|---|---|---|
| `category_fit` | 0.25 | The community-type × business-category affinity matrix — the same one Explore ranks with |
| `offer_need_fit` | 0.20 | What one side offers against what the other asks for, aliased so `venue` and `venue_space` count as a match |
| `location_fit` | 0.15 | Same city, then real distance (Haversine, in PHP) |
| `scale_fit` | 0.15 | Expected headcount against the venue that would host it — under-filling *and* overflowing both lose points |
| `delivery_proof` | 0.15 | For a community: reels and stories it actually delivered, plus ratings received. For a business: its completed-Kolab record and reviews |
| `momentum` | 0.10 | Events in the last 90 days, plus whether a recurring series is live |

`delivery_proof` is the one worth dwelling on, because it is the platform's real asset.
A 45-attendee monthly run club that delivered two reels is, to a browsing business today,
indistinguishable from a dormant profile. This signal is where that becomes visible — and
it reads outcomes (`collaboration_feedback.posts_reels`, `stories_posted`,
`would_collaborate_again`), not follower counts.

**On social media:** we hold Instagram and TikTok *handles* and nothing else. No follower
count, no engagement. So social is rendered as an evidence link and **never scored**.
What we have instead is better: proof of what a community actually delivered.

## 4. The central mechanic: renormalisation

A signal with no data returns `null`. It is dropped from the weighted sum **and its weight
leaves the denominator**. Otherwise a new profile would be punished for data we never had.

Worked example — a business viewer, a community with a declared size but no event history:

| Signal | Value | Weight |
|---|---|---|
| `category_fit` | 0.90 | 0.25 |
| `location_fit` | 1.00 | 0.15 |
| `scale_fit` | 0.75 | 0.15 |
| `offer_need_fit` | 0.50 | 0.20 |
| `delivery_proof` | *no data* | — |
| `momentum` | *no data* | — |

Weighted sum `0.5875`, available weight `0.75` → **score 78**. Without renormalisation the
same pair scores **59**, and a profile that has simply not run an event yet looks like a
bad match rather than an unknown one.

`confidence` then reports how much of the total weight had real data behind it: `high` at
≥ 0.75, `medium` at ≥ 0.45, otherwise `low`. It is the card's own honesty marker.

## 5. Where the proposed event comes from

- **Weekday** — from `event_series.byweekday` if a series is running, else the most common
  weekday of past events, else nothing. (Two conventions collide here: `byweekday` is 0–6
  with Sunday 0; `kolabs.recurring_days` is ISO 1–7. They agree Monday–Saturday and differ
  only on Sunday, so the code converts and throws rather than guessing.)
- **Time** — the series' `time_of_day`, else nothing.
- **Headcount** — the median of real past attendance, **capped by the venue's capacity**.
  A 45-person community against a 40-person venue yields 40, and the card says so.
- **Offer and ask** — the intersection of what each side declares, in the vocabulary the
  create form validates, so a pre-filled chip is always one the user can un-pick.

Each number carries a *basis* caption: "From attendance at their past events" versus
"Estimated from their declared member count". The same figure deserves different trust
depending on where it came from, and the caption is how the card says which.

## 6. Three things it refuses to do

1. **Never invent a number.** With no history and no declared size, the headcount is
   omitted and the copy degrades. A test asserts the no-history card contains no digits.
2. **Never guess an unmapped pairing.** Explore maps an unknown community-type × business-
   category pair onto a mid-range 0.35–0.65 fallback, because a ranking feed must always
   order *something*. A suggestion must not: an unmapped pair is *no data*, the signal
   drops, and confidence falls. The two policies diverge on purpose.
3. **Never store rendered text.** Generation runs nightly in the app's default locale, so
   a persisted sentence would reach every Spanish and Catalan reader in English. Rows store
   keys and parameters; sentences are rendered per request in the reader's locale.

## 7. How it meets the paywall

**It adds no gate.** The business paywall stays exactly the two actions in ROLES §2.7.
The surface is free to look at for everyone; acting on a card runs into the existing gate.

For a business without an active subscription, the counterpart's `name`, `avatar_url` **and
`id`** come back null with `is_identity_blurred: true`. Everything else — score, confidence,
reasons, proposed event, headcount — stays fully visible. The card has to prove the value
of the thing being withheld, so blurring more would defeat it.

`id` is withheld because `GET /profiles/{id}` returns the identity to any caller
([BE-FX-22](../BACKLOG.md)), so a blurred card must not carry a lookup key.

**Communities are never masked and never gated**, in either direction. This is the most
common regression in this codebase: if you find yourself writing a branch that hides
something from a community, it is a bug.

## 8. One row per pair

The unique key is `(viewer, counterpart)` — deliberately **not** the batch. Keying on the
batch would write a fresh row every night while the previous thirteen were still inside
their 14-day expiry: up to fourteen near-identical cards per counterpart.

So the nightly pass refreshes the same row. Consequences:

- Funnel timestamps survive every re-score, which is why the update never touches them.
- A pair that stops matching stops being refreshed and ages out.
- A dismissal is suppressed for 60 days, then the row is refreshed *and* the dismissal
  cleared — that is what makes the cooldown a cooldown rather than a permanent block.
- The prune keeps every converted row forever (it is the funnel evidence) and every live
  dismissal, and collects the rest. Because a still-matching pair was refreshed earlier in
  the same pass, the prune can only ever reach pairs that already stopped matching.

## 9. What we can measure, and what we cannot

Instrumented per audience, so a business-side win and a community-side flop cannot average
into a meaningless number: `suggestion_shown` → `suggestion_clicked` → `suggestion_dismissed`
→ `suggestion_converted`. `shown` fires on first impression only, not per page load, so one
funnel step has one denominator.

**The chain stops there, and that is the gap.** The spec promised
`… → paywall_hit(reason=suggestion) → subscription_started`, and neither event exists
anywhere in the backend. So today we can prove suggestions produce Kolabs. We *cannot* prove
they produce subscriptions — which is the reason the feature was funded.

## 10. Open questions — this is the part to argue about

**1. `confidence: high` is reachable with zero evidence of past performance.**
`category_fit + offer_need_fit + location_fit + scale_fit` sum to exactly 0.75, the `high`
threshold. So a pair whose *only* missing signals are `delivery_proof` and `momentum` — the
two that carry actual track record — still shows as high confidence. The worked example in
§4 is exactly this case: no history at all, `high`. Either the threshold should move, or
those two signals should be weighted so their absence cannot leave a high band.

**2. The matrix has no row for about a quarter of live communities.**
Missing: `art_creative_community`, `sustainability_community`, `photography_community`,
`hobby_community`, `dance_community`, `other` — **10 of 43 community profiles** measured
read-only on 2026-08-19. Those cards lose the heaviest signal honestly rather than faking
it, but they systematically read as thinner. Extending the matrix is 6 rows × 16 columns of
product judgement about what pairs well with what.

**3. Category values are messy in production.** `community_type` carries hyphen/underscore
twins (`wellness-community` next to `wellness_community`, seven such pairs) and
`business_profiles.categories` carries Spanish slugs (`restaurante`, `cafeteria`,
`gimnasio`, `tienda-de-deportes`, `centro-de-belleza`). The engine normalises and aliases
both, but the underlying data should probably be cleaned at the source.

**4. Two divisors are guesses.** `active_cadence = 4` treats "four events in 90 days" and
"four Kolabs-plus-collaborations in 90 days" as the same bar, which is unlikely.
`community_size_attendance_fraction = 0.25` assumes a quarter of a community shows up. Both
are config, both should be tuned against a real batch rather than argued about in the
abstract.

**5. `min_score = 45` has never met real data.** It decides how many cards exist at all.

**6. The web surface has no behavioural test.** Route-render tests cannot execute Alpine, so
the page's JS is asserted at the source. A browser walkthrough should happen before the flag
goes on.

## 11. Turning it on

1. Deploy (the migration runs with `master`).
2. Leave `SUGGESTIONS_ENABLED` unset. Run `php artisan app:generate-suggestions --dry-run`,
   then for real, and **read the rows** — the reasons in particular. If they do not read like
   something you would send a paying customer, the copy or the weights are wrong.
3. Tune `weights` / `min_score` in config. No code change, no deploy of logic.
4. Enable the flag.

The follow-up work, in the order it matters: the two missing telemetry events (without them
we cannot evaluate the feature), the weekly digest (without it nothing brings people back),
and the browser walkthrough.
