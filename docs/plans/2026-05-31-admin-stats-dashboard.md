# Admin Stats Dashboard — Plan + Status

**Date:** 2026-05-31
**Status:** Phases 1 and 2 **shipped on backend**. Phase 3 (app-side) **not started** — that's the work this doc hands off.

**Goal:** A platform-wide admin analytics surface at `/admin/stats` that tells maintainers how Kolabing is actually being used, where the funnel leaks, and what to improve for businesses vs. communities.

This doc covers three phases — what's shippable from existing data (Phase 1, **done**), cheap backend additions to unlock richer signals (Phase 2, **done**), and what the mobile app needs to instrument so the picture is complete (Phase 3, **for the app-side agent**). The **app-side section is self-contained** so it can be handed straight to the mobile-app agent.

---

## Architecture of the admin dashboard (read this for context)

The Kolabing admin dashboard is a **server-rendered Blade app inside the existing Laravel 12 backend** — no separate SPA, no separate repo. It lives under `/admin/*` on the same domain as the marketing site and the mobile API. The stack is intentionally boring:

- **Auth:** a dedicated `admin` guard (`config/auth.php`) backed by the `users` table (separate from the customer-facing `profiles` table). A `maintainer` middleware (`App\Http\Middleware\EnsureAdminUserIsMaintainer`) further gates routes to users whose `is_maintainer = true`. Both gates are applied to the whole admin route group in [routes/web.php](../../routes/web.php).
- **UI framework:** [jeroennoten/laravel-adminlte](https://github.com/jeroennoten/Laravel-AdminLTE) — Bootstrap 4–based AdminLTE theme, configured via `config/adminlte.php`. The sidebar `menu` array there is the source of truth for nav entries (Dashboard / Users / Kolabs / **Statistics** / etc.). Every admin page extends `resources/views/admin/layout.blade.php`, which extends `adminlte::page` and exposes page_title / page_subtitle / page_actions / admin_content slots.
- **Page structure:** each admin feature is a `Controller` under `app/Http/Controllers/Admin/` + a Blade view tree under `resources/views/admin/<feature>/`. Heavy logic lives in **services** under `app/Services/Admin/` (`ManagedProfileService`, `KolabLifecycleService`, `PlatformStatsService`). FormRequests handle validation. No JS framework — interactivity is small vanilla blocks or HTML form posts.
- **CSP:** strict (see [`AddSecurityHeaders.php`](../../app/Http/Middleware/AddSecurityHeaders.php)). `script-src 'self' 'unsafe-inline'` plus the Tailwind CDN host. The stats dashboard intentionally **avoids Chart.js or any third-party JS lib** — every chart is rendered with Bootstrap progress bars, info-boxes, and HTML tables so no CSP carve-outs are needed.
- **Data source:** the same PostgreSQL/SQLite the rest of the app uses. The admin reads through Eloquent (with `selectSub` / `toBase()` for aggregations to avoid N+1) and reuses existing services (`CollaborationService::cancel`, `KolabLifecycleService::derive`, etc.) rather than reinventing business rules.
- **Existing admin features** (chronological): User Management (create/edit/delete/grant/revoke subscription) → Kolab Management (list/edit/delete + lifecycle panel + force-cancel) → **Statistics (this doc)**.
- **Route map under `/admin`**:
  - `GET /admin` → Dashboard
  - `GET /admin/users` + `users/create|edit|destroy` + `users/{p}/subscription/grant|revoke`
  - `GET /admin/kolabs` + `kolabs/{k}/edit|destroy` + `kolabs/{k}/collaboration/cancel`
  - **`GET /admin/stats`** ← new
- **What the dashboard isn't:** it isn't a customer surface, it isn't an API, and it does not push events out. It's a read-only operator console. The app-side agent's work (Phase 3) feeds **PostHog**, not this dashboard.

---

## 0. State of the system today

- **There is no platform-wide stats system.** `app/Services/DashboardService.php` is per-user, mobile-facing (a business sees their own opportunities; a community sees their own sends). It does not roll up across users.
- **No analytics SDK is wired anywhere** (no PostHog / Mixpanel / Amplitude in `composer.json` or app dependencies).
- **No login history, no `last_active_at`, no screen-view tracking, no impression/view counts on kolabs.**
- **No status-transition timestamps.** Application + Collaboration rows mutate `updated_at` in place — you can't reliably ask "how long does it take a creator to accept?" because the timestamp moves on every edit.
- What *does* exist that helps: `collaborations.completed_at`, `kolabs.published_at`, `point_ledger` (event-typed append-only log: collaboration_complete, first_kolab_bonus, review_posted, etc.), `chat_messages.created_at` as a conversation-volume proxy, `collaboration_reviews` for quality, and the `Kolab.id == CollabOpportunity.id` bridge so we can count applications per kolab cleanly.

The schema covers ~80% of v1; the missing 20% comes from cheap backend additions (Phase 2) plus app-side events (Phase 3).

---

## 1. Phase 1 — Stats Dashboard v1 ✅ Shipped

**Scope:** A single admin page that surfaces every stat we can compute from current data. Backend-only — no app changes required. Estimated **~½ day**.

### 1.1 Deliverables

- `app/Services/Admin/PlatformStatsService.php` — pure aggregator. All methods take an optional `\Carbon\CarbonInterval` (or date range) and return primitive arrays so the view stays dumb.
- `app/Http/Controllers/Admin/StatsController.php` — single `index(Request)` action; passes payload to view; accepts `?range=7d|30d|90d|all` query param.
- `resources/views/admin/stats/index.blade.php` — AdminLTE cards + small Chart.js charts (Chart.js via CDN, allowed by current CSP: `script-src 'self' 'unsafe-inline'` plus we'd add the cdn host).
- Sidebar entry in `config/adminlte.php` under a new **"INSIGHTS"** header, route `admin.stats.index`.
- Route: `GET /admin/stats` in the existing `auth:admin + maintainer` group.
- Feature tests: one happy-path test per stats card (~6 tests).

### 1.2 Metrics shipped in v1

#### A. Audience volume
- Total profiles, split by `user_type` (business / community / attendee)
- New profiles in selected range, weekly time-series
- Active business subscriptions (active count, by `source`: stripe / apple_iap / **maintainer**)
- Soft-deleted users in range

#### B. Kolab activity
- Total kolabs by `kolabs.status` (draft / published / closed)
- New kolabs published per week (time-series)
- Average time-to-publish (`published_at − created_at`) on kolabs that were ever published
- Lifecycle distribution today (reuse `KolabLifecycleService::derive` on the live set — buckets: draft / open / receiving / matched / scheduled / active / completed / cancelled / closed)
- Kolabs by intent type (venue_promotion / product_promotion / community_seeking)

#### C. Applications (the funnel)
- Total applications by status (pending / accepted / declined / withdrawn)
- Applications by `applicant_profile_type` (business vs community) — the count the user explicitly asked for
- Applications per week, split by applicant type
- **Acceptance rate** = `accepted / (accepted + declined + withdrawn)`
- **Decline rate** by reason category (only `status` is recorded today, so this is a placeholder until reasons are captured)
- Median applications per published kolab

#### D. Collaborations
- Total collaborations by `collaborations.status`
- Weekly completions and cancellations
- **Completion rate** = `completed / (completed + cancelled)`
- Average time-to-complete (`completed_at − created_at`)
- Median chat messages per collaboration (engagement proxy via `chat_messages`)

#### E. Quality & engagement
- Average `collaboration_reviews.rating`, plus per-side averages (business→community vs community→business)
- % of completed collabs with **both** reviews submitted
- Reviews per week (time-series)
- Points-ledger event mix (collaboration_complete vs review_posted vs ugc_posted vs referral_conversion) — gives a free engagement narrative since the table is already append-only

#### F. The percentage-of-users funnel (the user's primary ask)
For each user type:
1. **Created** — profile exists
2. **Onboarded** — has a populated `business_profile` / `community_profile`
3. **Published a kolab** *(business only)* — appears as `kolabs.creator_profile_id`
4. **Applied** — appears as `applications.applicant_profile_id`
5. **Accepted** — has at least one accepted application
6. **Collaborated** — appears in `collaborations`
7. **Completed** — has at least one `completed` collaboration
8. **Reviewed** — has at least one `collaboration_reviews` row

Each step shows count + % retained from the previous step. Same view, two columns (business / community).

#### G. Money
- Active subscriptions count, split by source (gives visibility into maintainer-granted volume — the lever you just added)
- New paid activations per week
- Churned subs per week (transitions to `cancelled` — best-effort using `updated_at` until Phase 2)

### 1.3 UX

```
ADMIN · INSIGHTS · /admin/stats          [ Last 7d | 30d | 90d | All ]
┌──────────────────────────────────────────────────────────────────────┐
│  AUDIENCE                                                            │
│  ╔═══════════╗  ╔═══════════╗  ╔═══════════╗  ╔═════════════════╗   │
│  ║ Total     ║  ║ Business  ║  ║ Community ║  ║ Active subs     ║   │
│  ║ profiles  ║  ║ profiles  ║  ║ profiles  ║  ║                 ║   │
│  ║  1,248    ║  ║   312     ║  ║   894     ║  ║ 47 (12 maint.)  ║   │
│  ╚═══════════╝  ╚═══════════╝  ╚═══════════╝  ╚═════════════════╝   │
│                                                                       │
│  Weekly new profiles  [line chart, business vs community]            │
│                                                                       │
│  FUNNEL                  Business         Community                   │
│  ──────────────────────────────────────────────────────              │
│  Created                 312 (100%)       894 (100%)                  │
│  Onboarded               240  (77%)       640  (72%)                  │
│  Published a kolab       142  (46%)       —                           │
│  Applied                  62  (20%)       411  (46%)                  │
│  Accepted                 48  (15%)       287  (32%)                  │
│  Collaborated             47  (15%)       284  (32%)                  │
│  Completed                31  (10%)       198  (22%)                  │
│  Reviewed                 22   (7%)       154  (17%)                  │
│                                                                       │
│  APPLICATIONS                                                         │
│  ╔════════════════╗  ╔════════════════╗  ╔═══════════════════╗      │
│  ║ Total          ║  ║ Acceptance     ║  ║ Median per Kolab  ║      │
│  ║ 1,432          ║  ║ rate  67%      ║  ║       3.1         ║      │
│  ╚════════════════╝  ╚════════════════╝  ╚═══════════════════╝      │
│  By applicant type:  Community 1,121 · Business 311                  │
│  Weekly applications  [stacked bar chart]                            │
│                                                                       │
│  COLLABORATIONS                                                       │
│  Completed: 198   Cancelled: 41   Completion rate: 83%               │
│  Avg time-to-complete: 11.4 days                                     │
│  Lifecycle distribution today  [donut chart]                         │
│                                                                       │
│  QUALITY                                                              │
│  Avg ★ 4.3  ·  Both-sides-reviewed: 62%                              │
│  Business → Community  ★ 4.5 · Community → Business  ★ 4.2          │
└──────────────────────────────────────────────────────────────────────┘
```

### 1.4 Out of scope for v1

Anything that needs data we don't capture yet — those land in Phase 2 / 3 below.

---

## 2. Phase 2 — Backend additions to unlock richer stats ✅ Shipped

**Scope:** One migration + a handful of service-line edits to stamp transition timestamps and a few activity fields. Estimated **~½ day**. Strictly additive; nothing breaking.

### 2.1 Migration

```php
Schema::table('applications', function (Blueprint $table) {
    $table->timestamp('accepted_at')->nullable()->after('status');
    $table->timestamp('declined_at')->nullable()->after('accepted_at');
    $table->timestamp('withdrawn_at')->nullable()->after('declined_at');
});

Schema::table('collaborations', function (Blueprint $table) {
    $table->timestamp('activated_at')->nullable()->after('scheduled_date');
    $table->timestamp('cancelled_at')->nullable()->after('completed_at');
    $table->string('cancellation_reason', 500)->nullable()->after('cancelled_at');
    $table->foreignUuid('cancelled_by_profile_id')->nullable()->after('cancellation_reason')
        ->constrained('profiles')->nullOnDelete();
});

Schema::table('profiles', function (Blueprint $table) {
    $table->timestamp('last_active_at')->nullable()->after('email_verified_at');
    $table->index('last_active_at');
});
```

### 2.2 Service edits

- `ApplicationService::accept|decline|withdraw` → stamp the matching timestamp.
- `CollaborationService::cancel` → stamp `cancelled_at`, persist `cancellation_reason` (the **force-cancel form already collects this** but currently throws it away), set `cancelled_by_profile_id`.
- `CollaborationService` (or wherever the scheduled→active transition lives) → stamp `activated_at`.
- New middleware `TouchProfileActivity` on the `auth:sanctum` group → `update last_active_at = now()` at most once per 5 minutes per profile (rate-limited to avoid hammering the table).
- Backfill: a one-off `BackfillApplicationTransitionsCommand` that copies `updated_at` to the timestamp matching the current `status`. Documented as approximate.

### 2.3 New stats this unlocks

- **DAU / WAU / MAU** (from `last_active_at`)
- **Time-to-accept** (creator responsiveness)
- **Time-to-decline / time-to-withdraw**
- **Time-from-scheduled-to-active**
- **Cancellation reasons report** — top reasons, % of collabs cancelled by maintainer vs by participants
- **Engagement cohort** — % of users active in last 7 / 30 days, by signup cohort

---

---

## 2.5 What was actually executed on the backend (as of 2026-05-31)

This section is the **definitive list** of what already exists; the app-side agent (Phase 3) can safely assume all of it.

**Migration:** `database/migrations/2026_05_31_212534_add_lifecycle_tracking_columns.php`
- `applications.accepted_at`, `applications.declined_at`, `applications.withdrawn_at` — all nullable timestamps
- `collaborations.activated_at`, `collaborations.cancelled_at`, `collaborations.cancellation_reason` (string 500), `collaborations.cancelled_by_profile_id` (FK to `profiles`, nullable; **null = cancelled by maintainer**)
- `profiles.last_active_at` (nullable, indexed)

**Service edits**
- `app/Services/ApplicationService.php` → `accept()` stamps `accepted_at`, `decline()` stamps `declined_at`, `withdraw()` stamps `withdrawn_at`.
- `app/Services/CollaborationService.php` → `activate()` stamps `activated_at`. `cancel()` now persists `cancelled_at`, `cancellation_reason`, and `cancelled_by_profile_id` (optional 3rd param `?Profile $cancelledBy`). The existing admin force-cancel form already collects the reason — it now actually lands in the DB.
- `app/Console/Commands/BackfillLifecycleTimestamps.php` (signature `app:backfill-lifecycle-timestamps`, supports `--dry-run`) — one-off backfill that copies `updated_at` into the matching transition column for legacy rows. Run once per environment after deploy.

**Activity middleware**
- `app/Http/Middleware/TouchProfileActivity.php` (alias `touch_profile_activity` registered in `bootstrap/app.php`).
- Attached to the API auth group in `routes/api.php` on the `auth:sanctum` middleware stack. Writes `profiles.last_active_at` at most once per **5 minutes** per profile to avoid hammering the table.

**Stats infrastructure**
- `app/Services/Admin/PlatformStatsService.php` — single entry point `summary(string $range)`. Range one of `7d|30d|90d|all`. Returns a structured array consumed by the view. Methods are tested directly so the controller stays a thin shell.
- `app/Http/Controllers/Admin/StatsController.php` — `index(Request)` only. Invalid ranges fall back to `30d`.
- `resources/views/admin/stats/index.blade.php` — server-rendered Bootstrap/AdminLTE markup. Info-boxes for headline numbers; progress bars for distributions; HTML tables for the funnel. **No JS chart lib.**
- Sidebar entry under a new **INSIGHTS** header in `config/adminlte.php`.
- Route: `GET /admin/stats` named `admin.stats.index` in the existing `auth:admin + maintainer` group.

**What the stats view actually shows today**

| Section | Metrics |
|---|---|
| Audience | Total profiles, split by user_type, new in range, soft-deleted |
| Activity | **DAU / WAU / MAU** (from `last_active_at`) |
| Funnel | Lifetime % of users at each step (Created → Onboarded → Published a kolab → Applied → Accepted → Collaborated → Completed → Reviewed), split Business vs Community |
| Kolabs | Total + by status, avg time-to-publish, by intent type, lifecycle distribution of the live set |
| Applications | Total, by status, by applicant type (Business vs Community), acceptance rate, median per kolab, avg time-to-accept |
| Collaborations | Completed / Cancelled / Completion rate, avg time-to-complete, median chat messages, **top cancellation reasons** |
| Quality | Avg ★ rating, per-side averages (Business → Community vs Community → Business), % of completed collabs with both reviews, point-ledger event mix |
| Subscriptions | Active total, by source (stripe / apple_iap / **maintainer**), paid penetration % |

**Tests** — added two suites:
- `tests/Feature/Admin/StatsDashboardTest.php` — 8 tests (route gating, audience split, applications by type + acceptance rate, funnel per type, money split by source, kolab lifecycle distribution, range fallback, DAU/WAU/MAU).
- `tests/Feature/Admin/LifecycleTimestampsTest.php` — 5 tests (accept stamps, decline stamps, withdraw stamps, cancel persists reason+timestamp, API auth touches `last_active_at`).
- **Final state: 682 tests, 3472 assertions, all green. Pint clean.**

**Known caveat about DAU/WAU/MAU** — these will read **0 immediately after deploy** because no users have hit the API after the middleware was wired. They'll fill in naturally as traffic flows. There is no backfill — by definition we don't know what "last active" was for legacy users.

**Caveat about `cancelled_by_profile_id`** — null indicates "cancelled by maintainer through the admin panel". When/if the mobile app ever exposes a participant-cancel flow, that controller must pass `$cancelledBy = $request->user()->profile`. Otherwise we lose the participant-vs-maintainer signal.

---

## 3. Phase 3 — App-side instrumentation (read this if you own the mobile app)

> **TL;DR for the app-side agent: integrate PostHog and emit a small, fixed set of events. The backend will consume some of those signals (or PostHog dashboards will, directly). Everything below is what's missing today and only the app can produce.**

The Kolabing backend currently has **zero visibility into what users do inside the app between API calls**. Database rows tell us a user created an application or completed a collaboration — they tell us nothing about whether the user saw 80 kolabs before applying, abandoned onboarding on step 2, or pressed Apply and then bailed at the confirm modal. Those answers come from app-side events.

### What we need from the app

1. **Integrate PostHog** (recommended) on the mobile app. Open-source, self-hostable, generous free tier, privacy-friendly. Alternative: Mixpanel or Amplitude — pick one and be consistent.
   - Identify users with their profile UUID (`profile.id`) so events join with backend data.
   - Set super-properties at identify time: `user_type` (business / community / attendee), `city`, `subscription_active` (for business), `signup_week`.

2. **Emit a small, fixed event catalog** — keep it disciplined; ~15–20 events total is plenty for v1.

   **Lifecycle / onboarding**
   - `signup_started` (carries provider — google / apple)
   - `signup_completed`
   - `onboarding_step_viewed` (`step: 1|2|3|...`, `flow: business|community`)
   - `onboarding_completed`
   - `onboarding_abandoned` (fired on app background after step view)

   **Discovery / browsing**
   - `feed_viewed` (`screen: explore|recommended|nearby`)
   - `feed_filter_applied` (`filters: {category, city, ...}`)
   - `feed_search_performed` (`query`)
   - `kolab_impression` (`kolab_id`, `position_in_feed`, `screen`) — fired when a card scrolls into view; debounce so each id only fires once per session per screen
   - `kolab_detail_viewed` (`kolab_id`, `time_on_screen_seconds`)

   **Funnel actions**
   - `apply_tapped` (`kolab_id`, `source_screen`)
   - `apply_submitted` (`kolab_id`, `application_id`)
   - `apply_abandoned` (`kolab_id`, `step: message|availability|review`) — if the user backed out
   - `application_decision_made` (`application_id`, `decision: accept|decline`, `time_since_received_seconds`) — fires when a creator acts on it
   - `paywall_hit` (`feature: publish_kolab|...`)
   - `paywall_dismissed` / `paywall_subscribed`

   **Collaboration usage**
   - `collaboration_chat_opened` (`collaboration_id`)
   - `collaboration_checked_in` (`collaboration_id`)
   - `collaboration_marked_complete` (`collaboration_id`, `by_side: business|community`)
   - `review_submitted` (`collaboration_id`, `rating`)

   **Session**
   - `app_opened` (Posthog handles session naturally; just ensure identify is called early)

3. **Backend-side hooks the app will indirectly benefit from**
   - The backend will stamp `last_active_at` on any authenticated API call (Phase 2). That gives us a server-side floor on DAU/WAU even if PostHog is down.
   - The backend will fire one synthetic PostHog event per significant state transition (`application_accepted_server_side`, `collaboration_cancelled_server_side`) so PostHog funnels can join app-side and server-side events without ambiguity.

4. **Privacy / consent**
   - PostHog event payloads must **not** include free-text user content (no chat messages, no review bodies, no email addresses) — only ids and enum-typed metadata.
   - Respect a `analytics_opt_out` flag (boolean) added to the profile — the app should read it from `/me`, persist locally, and skip emit when true. Backend will gate the synthetic events the same way.

5. **What "done" looks like for the app side**
   - PostHog SDK initialised; `identify` called on login with the profile UUID and super-properties.
   - All events above are emitted, with the exact event names and property keys spelled as listed (so dashboards work without translation).
   - A short doc in the app repo listing the catalog so future event additions follow the same conventions.

Once this is in place, the admin Stats dashboard can either embed PostHog charts (via signed iframes) or just keep its current backend-rendered charts while you do the deep funnel analysis in PostHog directly.

---

## 4. Order of work (status)

1. **Phase 1** — ✅ done on backend.
2. **Phase 2** — ✅ done on backend (one migration, service edits, middleware, backfill command).
3. **Phase 3** — ⏳ **pending, owned by the app-side agent**. PostHog integration + event catalog per §3.

---

## 5. Acceptance criteria for Phases 1 + 2

- [x] `/admin/stats` reachable by maintainers, 403 for everyone else, 302 to login when unauthenticated.
- [x] Sidebar shows an "Insights → Statistics" link under a new "INSIGHTS" header.
- [x] Date-range filter (7d / 30d / 90d / all) re-runs all range-sensitive aggregations; invalid ranges fall back to 30d.
- [x] Audience / Kolabs / Applications / Collaborations / Quality / Funnel / Subscriptions / Activity (DAU/WAU/MAU) sections all render.
- [x] Aggregations avoid N+1 (subselects + `toBase()`); pagination not needed at v1 scale.
- [x] Migration adds `accepted_at` / `declined_at` / `withdrawn_at` on `applications`, `activated_at` / `cancelled_at` / `cancellation_reason` / `cancelled_by_profile_id` on `collaborations`, `last_active_at` on `profiles`.
- [x] `ApplicationService::accept|decline|withdraw` stamp the matching column. `CollaborationService::activate` stamps `activated_at`. `CollaborationService::cancel` persists reason, `cancelled_at`, and optional `cancelled_by_profile_id`.
- [x] `TouchProfileActivity` middleware on the API `auth:sanctum` group; 5-minute cooldown per profile.
- [x] One-off backfill command `app:backfill-lifecycle-timestamps` (with `--dry-run`).
- [x] Feature tests: 8 stats + 5 timestamp = 13 new. Total project suite: **682 passed / 3472 assertions.**
- [x] `vendor/bin/pint --dirty` clean.

---

## 6. Operational checklist for deploy

1. Pull the merge into the deploy branch.
2. `php artisan migrate` — runs the new lifecycle-tracking migration. Strictly additive; safe to roll forward.
3. (Optional, recommended) `php artisan app:backfill-lifecycle-timestamps --dry-run` — print how many legacy rows would be updated, then re-run without `--dry-run` to apply.
4. Verify `/admin/stats` renders end-to-end. DAU/WAU/MAU will start at 0 and grow as the API receives traffic.
5. Tell the app-side agent: read §3 of this doc.

---

*The backend half is done. Hand §3 (or this whole doc) to whoever owns the mobile app to ship the PostHog instrumentation.*
