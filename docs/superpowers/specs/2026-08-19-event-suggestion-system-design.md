# Event suggestion system (two-sided Kolab suggestions)

> Design spec — 2026-08-19
> Backlog item: **BE-NF-28**. Builds on the existing role-aware discovery match
> engine (`DiscoveryOpportunityService`, spec
> `2026-05-09-role-aware-discovery-backend-contract.md`) and the web app
> (BE-NF-20…26, `app.kolabing.com`).

## 1. Problem

Kolabing already knows a lot about both sides of a collaboration, and uses
almost none of it proactively:

- **Communities** carry real history — `events` (date, `attendee_count`,
  capacity, city, lat/lng, photos, check-ins, signups), `event_series`
  (`frequency` + `byweekday` + `time_of_day` = a live cadence),
  `community_profiles` (`community_type`, `community_size`, verification), and
  crucially **outcome** data: `collaboration_feedback.posts_reels`,
  `stories_posted`, `revenue`, `expectation_match`, `would_collaborate_again`,
  plus five-dimension `collaboration_reviews`.
- **Businesses** carry capability data — `business_profiles.offering`,
  `product_type`, `business_type`, `has_venue`, `primary_venue`, `categories`,
  `target_city_ids`, plus per-Kolab `base_offer` / `offering` / `expects`, and
  a computed `business_partner_statuses` reliability record.

Today all of this only ever answers a question the user already asked: Explore
ranks Kolabs that *someone else already published*. Nobody is told **who to
partner with and what event to run**. The result:

| # | Gap | Consequence |
|---|-----|-------------|
| P1 | Discovery is pull-only. A business with no published Kolabs in its city sees an empty-ish feed and leaves. | The paywall is never reached, because the *value* of the paywall is never demonstrated. |
| P2 | Community history is write-only. 45-attendee monthly run club with 2 reels delivered looks identical, to a browsing business, to a dormant profile. | The platform's best sales asset — proven delivery — is invisible. |
| P3 | Creating a Kolab is a blank multi-step form. | High drop-off; `KolabCreateIncomplete` notifications exist precisely because of this. |
| P4 | Nothing brings a lapsed user back with a *specific* reason to return. | The only re-engagement today is a generic reactivation nudge. |

## 2. Non-goals

- **No new paywall.** The business paywall stays exactly the two actions in
  `docs/ROLES-AND-PERMISSIONS.md` §2.7 (create collaboration, apply). The
  suggestion surface is free to view for everyone; acting on a suggestion runs
  into the *existing* gate.
- **No community pricing, plan, or upsell of any kind** (§2.12). Communities get
  the feature free, permanently.
- **No new subscription tier.** Single plan (€49/mo, €129/3mo) stays.
- **No LLM.** Scoring and brief text are deterministic and templated. An LLM
  phrasing layer is a possible later addition, not part of this work.
- **No social-media metrics ingestion.** We hold Instagram/TikTok **handles
  only** — no follower or engagement data. Handles are rendered as evidence
  links, never scored. Real metrics (IG Graph API, or self-reported values
  verified through `community_profiles.verification_channels`) are a separate
  future phase.
- **No mobile UI.** The API is designed so `kolabing-app` can consume it
  unchanged; a cross-repo ticket is opened, but v1 does not wait for it.
- **No change to `DiscoveryOpportunityService` behaviour.** Shared scoring
  pieces are extracted; Explore's output stays identical.

## 3. Design

### 3.1 What a suggestion is

A suggestion is a **scored pair plus a proposed event format**, addressed to one
side:

- `audience = business` → "Run a Sunday-morning run + coffee with Run Club BCN
  (avg. 45 attendees, delivered 2 reels + 1 story, 92% would-collaborate-again).
  Suggested offer: coffee for 20 + 15% discount."
- `audience = community` → "Ask Café Sant Antoni for the venue for your Saturday
  yoga session. They have hosted 3 communities of your size and hold 40 people."

Accepting it opens the Kolab create form **pre-filled**. That is the conversion
mechanic; the card itself is only the pitch.

Naming: the table is `kolab_suggestions` — the artefact produced is a Kolab. The
`collab_` prefix is deliberately avoided (legacy `collab_opportunities` is being
retired, BE-IF-47).

### 3.2 Data model — `kolab_suggestions`

| Column | Type | Notes |
|---|---|---|
| `id` | uuid | `HasUuids`, per repo convention |
| `audience` | string enum | `business` \| `community` — which side is being shown the card |
| `viewer_profile_id` | uuid FK `profiles` | who sees it; cascade on delete |
| `counterpart_profile_id` | uuid FK `profiles` | the proposed partner |
| `city_id` | uuid FK `cities` nullable | resolved city of the proposed event |
| `score` | smallint | 0–100, computed in PHP |
| `confidence` | string enum | `low` \| `medium` \| `high` — share of signal weight backed by real data |
| `signals` | jsonb | `[{key, reason_key, reason_params, weight, score}]` — **keys and params, never rendered text** (see below) |
| `suggested_format` | jsonb | `{title, intent_type, weekday, time_of_day, expected_attendance, offer[], expects[]}` |
| `evidence` | jsonb | ids + aggregates that produced it (`event_ids`, `collaboration_ids`, `posts_reels_total`, …) |
| `batch_key` | date | the date this pair was last scored (not a generation bucket — see below) |
| `expires_at` | timestamp | last score + 14 days |
| `shown_at` / `clicked_at` / `dismissed_at` | timestamp nullable | funnel |
| `converted_kolab_id` | uuid FK `kolabs` nullable | funnel close |

Constraints and indexes:

- `unique(viewer_profile_id, counterpart_profile_id)`
- index `(viewer_profile_id, score desc)` — the read path
- index `(audience, batch_key)` — the digest path

`signals` and `evidence` are **write-once, read-only** jsonb: never filtered or
aggregated in SQL. This is deliberate — see §3.8.

**Reasons are persisted as keys, rendered at read time.** An earlier draft stored
the finished sentence. That was wrong: generation runs in a nightly command, in the
app's default locale, so every Spanish and Catalan reader would have received
English reasons and the three locale files could never reach production. So a
signal persists `reason_key` plus `reason_params` (**raw slugs and raw numbers,
never localised labels**), and a shared `SignalReasonRenderer` turns them into a
sentence in the *reader's* locale — used by both `SuggestionResource` (§3.5) and
the digest (§3.7). `reason_key` is separate from `key` because one signal chooses
different sentences depending on its data (distance vs same-city vs other-city;
the business vs community phrasing of proven delivery; and the variants that name
only the non-zero half of a two-number claim). Number formatting is also a
render-time concern: `2,5 km` in Spanish, `2.5 km` in English.

**One row per pair, refreshed in place.** The unique key deliberately excludes
`batch_key`: the nightly pass `updateOrCreate`s the same row, so a pair is never
shown twice. An earlier draft of this spec keyed the constraint on
`(viewer, counterpart, batch_key)`, which would have written a fresh row every
night while the previous 13 were still inside their 14-day expiry — up to 14
near-identical cards per counterpart. Consequences of the corrected shape:

- `expires_at` moves forward while the pair keeps matching; a pair that stops
  matching (or drops below `min_score`) simply stops being refreshed and ages out.
- Funnel timestamps live on the one row, so `shown_at` / `clicked_at` survive
  every re-score. The update payload must therefore never include them.
- Dismissals persist without a second table: the generator skips a pair whose
  `dismissed_at` is inside the cooldown (60 days, configurable), and once the
  cooldown has passed it refreshes the row **and clears `dismissed_at`**, which
  is what makes the cooldown a cooldown rather than a permanent block.

### 3.3 The engine — `app/Services/Suggestions/`

Three collaborators, each independently testable:

**`PairCandidateFinder`** narrows the pool in SQL before any scoring:

- same city (`profiles.city_id`, widened by `business_profiles.target_city_ids`)
- both profiles active; counterpart not soft-deleted
- no `user_blocks` row in either direction
- no open application or active collaboration between the pair
- minimum profile completeness (a counterpart with no `categories` /
  `community_type` and no history is not proposable)

**`SignalScorer`** — six signals, each normalised to 0..1, weights in
`config/suggestions.php` (not hardcoded, so they are tunable and later
admin-editable):

| Signal | Weight | business audience | community audience |
|---|---|---|---|
| `category_fit` | 0.25 | reuses the existing `COMMUNITY_BUSINESS_CATEGORY_SCORES` matrix | same matrix, reversed lookup |
| `location_fit` | 0.15 | city match, then Haversine distance | same |
| `scale_fit` | 0.15 | community's median `events.attendee_count` (fallback `community_size`) vs. own `primary_venue.capacity` | own typical attendance vs. business venue capacity |
| `offer_need_fit` | 0.20 | own `offering` / `base_offer` vs. community's `needs` / historical asks | own `needs` vs. business `offering` (existing `OFFER_TYPE_ALIASES` reused) |
| `delivery_proof` | 0.15 | community's `collaboration_feedback` (`posts_reels`, `stories_posted`, `would_collaborate_again`, `expectation_match`) + review dimensions | business's `business_partner_statuses` + reviews received |

`delivery_proof` keeps one shape across both audiences — `0.4 × rating + 0.3 × repeat + 0.3 × volume` — but **`volume` is audience-specific**, because the two sides prove delivery with different artefacts:

- **business audience** (counterpart is a community): volume is the reels and stories the community actually posted for past Kolabs (`collaboration_feedback.posts_reels + stories_posted`).
- **community audience** (counterpart is a business): volume is `business_partner_statuses.completed_kolabs_count`. A business does not deliver posts, so feeding content into this arm would both score and describe the wrong subject. `PairContext` carries both counts and the unused arm is zero.

This was caught in review one layer above, in the reason copy, and the scoring term had the same defect.
| `momentum` | 0.10 | events in the last 90 days + an active `event_series` cadence | business's recent collaborations / live Kolabs |

`score = round(Σ(weight × signal) / Σ(weight of signals with data) × 100)`.
Weights are **renormalised over available signals**, and `confidence` reports
what share of total weight had real data behind it (`high` ≥ 0.75, `medium`
≥ 0.45, else `low`). A cold-start profile therefore still gets an honest card:
scored on what we know, labelled `low` confidence, with a reason line that says
"no past events yet — matched on profile".

Rows below `config('suggestions.min_score')` (initial value **45**, to be tuned
against the first real batch) are not written at all. Better an empty state than
a bad suggestion.

**`FormatSuggester`** produces `suggested_format` from real history, no LLM:

- weekday/time from the community's `event_series.byweekday` + `time_of_day`,
  else the modal weekday of its past `events`
- `expected_attendance` = median past `attendee_count`, capped by the business's
  venue capacity (a 45-person community against a 40-person venue yields a
  capped number *and* a reason line naming the constraint)
- `offer[]` / `expects[]` from the business's declared `offering` intersected
  with the community's `needs`, using the existing offer-type aliases
- `intent_type` follows the audience: business → `product_promotion` (or venue
  promotion when `has_venue`), community → `community_seeking`
- title from a template per `community_type` (the 7 matrix types plus a `generic`
  fallback), carried as `title_key` + `title_params` and rendered at read time like
  the signal reasons. Keying it on `community_type` × `business_type` was considered
  and rejected: 7 × 16 templates in three locales buys little over naming the
  business in the params. When history is thin the copy degrades to a
  generic-but-true phrasing, never an invented claim

### 3.4 Generation

`php artisan app:generate-suggestions` — scheduled **daily at 04:00**
(02:00/03:00/08:00/09:00/14:20 are taken), `withoutOverlapping()`, chunked over
profiles, idempotent through the `(viewer, counterpart)` unique key. Writes at most
`config('suggestions.per_profile')` (default 5) rows per profile per audience.

A `GenerateSuggestionsForProfile` job runs on registration and on profile
completion, so a new account never sees an empty suggestions page while waiting
for the nightly pass.

Failure isolation: each profile is scored inside its own try/catch — a single bad
profile logs to Sentry and the batch continues.

### 3.5 API (all additive, `/api/v1`)

| Endpoint | Behaviour |
|---|---|
| `GET /suggestions` | Role-aware; returns only rows where `viewer_profile_id` is the caller, not expired, not dismissed, counterpart still active. `score desc`, paginated. Stamps `shown_at` on first serve. |
| `GET /suggestions/{id}` | Detail; stamps `clicked_at`. |
| `POST /suggestions/{id}/dismiss` | Stamps `dismissed_at`. Throttled. |
| `POST /kolabs` | Gains an optional `suggestion_id`; on success writes `converted_kolab_id`. Additive, no contract break. |

`SuggestionPolicy` — a caller may only read/dismiss rows whose
`viewer_profile_id` is their own profile (IDOR guard, 403 otherwise). Attendee
profiles never receive suggestions and get an empty list.

**Blur, not block.** For a **non-subscribed business**, `SuggestionResource`
masks the counterpart identity — `name` and `avatar_url` null,
`is_identity_blurred: true` — while every other field (score, signals, format,
expected attendance) stays visible. This is the *same rule* Explore already
applies (§2.4): a downstream effect of the existing gates, **not a new paywall**.
Communities are never masked, in either direction.

`GET /suggestions` for a community always returns full business identities,
because communities are never gated (§3 rule 1).

### 3.6 Web surface (`app.kolabing.com`)

- New `resources/views/webapp/suggestions.blade.php` + `Route::view('/suggestions')`,
  registered in both the root and `{locale}` groups (`/es/suggestions`,
  `/ca/suggestions`), plus a sidebar entry. Blade + Alpine, reading through the
  existing `kb.rows()` helper.
- Card anatomy: score badge · **three "why this" lines carrying real numbers** ·
  proposed format with weekday/time · proposed offer · two actions —
  **"Create this Kolab"** → `/kolabs/create?suggestion={id}` (form pre-filled
  from `suggested_format`) and **"Not interested"** → dismiss.
- Free business: blurred counterpart name/logo with a "see who you are targeting"
  CTA → `/subscription?reason=suggestion`. The `?reason=` allowlist documented in
  ROLES §2.12 gains `suggestion`.
- Dashboard gains a "N suggestions this week" block linking to the page.
- `lang/{en,es,ca}/webapp.php` gains a `suggestions.*` block — the web app's
  100% es/ca coverage is preserved.
- Empty state is honest and actionable: "complete your profile / add your past
  events and we can suggest partners", never a fabricated card.

### 3.7 Weekly digest

`php artisan app:send-suggestion-digest` — **Mondays 09:30** (avoids the 09:00
reactivation pass). Per profile: top 3 live suggestions, skipped entirely when
there are none.

- Email via `EmailService::send()` with Postmark aliases
  `suggestion-digest-business` / `suggestion-digest-community`, category
  `CATEGORY_MARKETING` — which means the existing
  `notification_preferences.marketing_tips` opt-out (and the
  `email_notifications` master switch) governs it. **No new preference column.**
- Push via a new `NotificationType::SuggestionsReady`.
- Deduplication through the `notifications` table, the pattern every other
  scheduled reminder already uses, so re-runs are safe.
- **Ops dependency:** the two Postmark templates must be created in the Postmark
  dashboard before the command is enabled on prod.

### 3.8 Error handling and known traps

- **Postgres vs. SQLite (the BE-FX-12 lesson).** The suite runs on SQLite while
  production is Postgres, so SQL that only breaks on Postgres cannot be caught by
  CI. Mitigation baked into the design: all scoring happens **in PHP**; SQL is
  used only for candidate filtering; `signals`/`evidence` jsonb is never queried;
  no aggregate is ever applied to a uuid column.
- **Stale counterparts.** A suggestion whose counterpart was deleted or
  deactivated after generation is filtered at read time by joining on the
  counterpart's active state — stale rows are invisible, never a 500.
- **Expiry.** `expires_at` (last score + 14 days) keeps the page from showing a
  three-week-old "this Saturday" proposal once a pair stops being refreshed.
- **Idempotence.** The `(viewer, counterpart)` unique key makes every re-run —
  same day or a month later — an update of the one existing row, so re-runs never
  multiply cards. `updateOrCreate` must omit the funnel columns from its update
  payload so `shown_at` / `clicked_at` / `dismissed_at` survive a re-score.
- **Feature flag.** `config('suggestions.enabled')` gates the command, the
  endpoints, and the nav entry, so the backend can ship dark and be enabled after
  the first batch is inspected on real data.

### 3.9 Measurement

PostHog (`config/posthog.php`, `app/Services/PostHog` already wired), every event
tagged with `audience` so the two sides are separable — the reason the two-sided
launch stays measurable:

`suggestion_shown` → `suggestion_clicked` → `suggestion_converted`
(`converted_kolab_id` written) → for businesses, `paywall_hit(reason=suggestion)`
→ `subscription_started`.

That last chain is the sales argument for the feature, and it is instrumented
from day one rather than reconstructed later.

## 4. Phasing

| Phase | Content |
|---|---|
| 1 | Migration, `config/suggestions.php`, engine (`PairCandidateFinder`, `SignalScorer`, `FormatSuggester`), command + schedule, model/factory, API + policy + resource. Ships dark behind the flag. |
| 2 | Web page, sidebar, dashboard block, pre-filled create, i18n (en/es/ca), `?reason=suggestion`. |
| 3 | Digest command, `NotificationType::SuggestionsReady`, Postmark templates (ops). |
| 4 (out of scope here) | `kolabing-app` mobile surface, LLM phrasing layer, IG/TikTok metrics, admin-editable weights (folds into BE-NF-5's admin-owned economy work). |

## 5. Testing

PHPUnit, `LazilyRefreshDatabase`, factories for every new model.

**Unit**

- `SignalScorerTest` — each of the six signals in isolation; boundaries: no data
  (signal skipped, weight renormalised), scale mismatch, perfect fit; `confidence`
  thresholds; `min_score` rejection.
- `FormatSuggesterTest` — `byweekday` → proposed weekday; capacity cap applied
  and named; no history → honest fallback copy with no invented numbers.

**Feature**

- `SuggestionGenerationTest` — batch idempotence, 60-day dismissal exclusion,
  blocked pairs and pairs with an active collaboration excluded, per-profile cap,
  one failing profile does not abort the batch.
- `SuggestionApiTest` — IDOR 403 on someone else's row; non-subscribed business
  sees `is_identity_blurred: true` with the rest of the payload intact;
  subscribed business sees identity; community never blurred; dismiss;
  `shown_at` / `clicked_at` stamping; expired and stale-counterpart rows absent;
  attendee gets an empty list.
- `SuggestionConversionTest` — `POST /kolabs` with `suggestion_id` writes
  `converted_kolab_id`; a foreign `suggestion_id` is rejected.
- `SuggestionDigestTest` — respects `marketing_tips` and the master
  `email_notifications` switch, dedups via `notifications`, sends at most 3,
  skips profiles with no live suggestions.
- `WebAppRoutesTest` extension — `/suggestions` and `/es/suggestions` render.

Gate before review: `php artisan test` green, `vendor/bin/pint` clean, counts
pasted into the PR template's Testing section.

## 6. Docs and tracking (mandatory per `CLAUDE.md`)

- `docs/ROLES-AND-PERMISSIONS.md` — new **§2.13**: the suggestion surface, the
  blur rule (and that it is not a new paywall), the explicit restatement that
  communities see it free and unmasked, `?reason=suggestion`. Bump *Last updated*.
- `docs/ROLES-BACKEND-DB-MAP.md` — new **§15**: table/columns, services,
  endpoints, policy, command + schedule, and the config keys. Bump *Last updated*.
- `BACKLOG.md` — add **BE-NF-28**, move to *Incomplete Features* when started,
  update *Last updated*.
- GitHub Projects item on **Kolabing Engineering** using
  `.github/ISSUE_TEMPLATE/ticket.yml`; PR uses the repo template with every
  required section filled.
- **Mobile impact (kolabing-app):** additive only — three new endpoints and one
  new optional field on `POST /kolabs`. No existing payload changes. A
  `kolabing-app` ticket is opened for the mobile suggestion surface; v1 does not
  block on it.
- Mirror §2.13 / §15 into the `kolabing-app` copies of both role docs.

## 7. Assumptions to validate

1. `min_score = 45` and the §3.3 weights are initial guesses. Inspect the first
   real batch (a maintainer-only preview of generated rows) before enabling the
   flag, and tune in config — no code change required.
2. Digest cadence weekly. If open rates say otherwise, the schedule is one line.
3. `confidence = low` cards are shown rather than hidden, on the argument that an
   honest thin suggestion beats an empty page. Worth revisiting once dismissal
   rates per confidence band exist.
