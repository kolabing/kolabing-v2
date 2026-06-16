# Kolab Source-of-Truth — Phase 2 (re-point reads)

Branch: `feat/kolab-sot-phase2` (local only, not pushed)
Date: 2026-06-16
Plan: `kolabing-app/docs/plans/2026-06-16-kolab-source-of-truth-migration.md`
Builds on: Phase 1 (`feat/kolab-sot-phase1`, commit 9da1ad1) — see
`docs/notes/2026-06-16-kolab-sot-phase1.md`.

Phase 2 re-points the **read** paths to `kolabs` while keeping every API response
shape identical, so the app needs **no change yet**. It is still
backward-compatible: `collab_opportunities` and `collab_opportunity_id` are kept,
the dual-write stays on, nothing is dropped, and `kolab_id` is still nullable.

---

## A. Inverse-bridge backfill (run FIRST, before reads re-point)

New service `app/Services/InverseLegacyOpportunityBridgeService.php` — the faithful
inverse of `LegacyOpportunityBridgeService::fillFromKolab()`. For every
`collab_opportunities` row that has **no** backing `kolabs` row (pre-bridge "true
legacy"), it creates a `kolabs` row **reusing the same id**, so existing
`collab_opportunity_id` values keep resolving and can be re-pointed to `kolab_id`.

Inverse field mapping (mirror of the forward bridge):

| collab_opportunities                | kolabs                                              |
|-------------------------------------|-----------------------------------------------------|
| `status` (draft/published/closed)   | `status` 1:1; `completed` → `closed` (no kolab enum) |
| `creator_profile_type`              | drives the offer mapping + intent                   |
| `business_offer` (business creator) | `offering`                                          |
| `business_offer` (community creator)| `needs`                                             |
| `community_deliverables` (business) | `expects`                                           |
| `community_deliverables` (community)| `offers_in_return`                                  |
| `categories`                        | `community_types` (community_seeking) / `seeking_communities` |
| `venue_mode` + creator type         | `intent_type` + `venue_preference` (reverse of `mapVenueMode`) |
| `address`                           | `venue_address`                                     |
| `offer_photo` (URL)                 | `media[] = [{url}]`                                 |
| headline / base_offer / triggers / recipient_community_id / availability* / preferred_city / published_at | copied 1:1 |

NOT-NULL guards (kolabs requires `title`, `description`, `preferred_city`): empty
legacy values fall back to `title`/`Untitled`/`Unknown`. `created_at`/`updated_at`
are preserved from the source so list ordering stays faithful. Intent derivation is
necessarily lossy (the forward bridge collapses 3 intents into one `venue_mode`):
community creator → `community_seeking`; business + `business_venue` →
`venue_promotion`; business otherwise → `product_promotion`. Service is
**idempotent** — re-running is a no-op for opportunities that already have a kolab.

Driven by migration
`database/migrations/2026_06_16_130000_inverse_bridge_backfill_kolabs_for_legacy_opportunities.php`,
which calls `backfillAll()` and echoes/logs before/after counts. After it runs:
1. a kolab exists for every legacy opportunity, and
2. `applications.kolab_id` / `collaborations.kolab_id` are set = `collab_opportunity_id`
   for the now-backfilled rows → **zero rows left with NULL kolab_id**.

### Backfill counts

- **Local / test DB (in-memory SQLite):** no production data, so the migration logs
  `opportunities_without_kolab 0 -> 0 (created 0 kolabs); applications NULL kolab_id
  0 -> 0; collaborations NULL kolab_id 0 -> 0`. The feature test
  `test_inverse_bridge_backfill_leaves_zero_null_kolab_id` seeds the exact
  true-legacy shape and asserts the 1→0 transition on both tables.
- **Production `main` (expected, from Phase 1's read-only count):** Phase 1 left
  **20 applications + 9 collaborations** with `kolab_id = NULL`, backed by the
  pre-bridge legacy opportunities. Running this migration in production should
  create one kolab per distinct legacy opportunity and drive both NULL counts to
  **0**. **Validate against Postgres on deploy** by reading the echoed
  `[kolab-sot-phase2]` line; the numbers must end at `... NULL kolab_id 20 -> 0`
  (applications) and `... 9 -> 0` (collaborations). The migration uses only
  `DB::table()->whereIn(subquery)->update()` + Eloquent inserts (no aggregates /
  RETURNING / ILIKE), so the SQLite↔Postgres divergence rules in BACKEND-SCHEMA.md
  do not bite.

> NOTE: this agent did **not** run any write against production. The production
> numbers above are the Phase 1 measured baseline; confirm the actual 20→0 / 9→0 by
> reading the migration output on the real deploy.

---

## B. Reads re-pointed to kolabs (response shapes unchanged)

### `/me/opportunities` (`OpportunityController::myOpportunities`)
`OpportunityService::getMyOpportunities()` now queries the viewer's **`kolabs`**
(`Profile::kolabs()`), `withCount('applications')`, paginates, then maps each Kolab
to an in-memory compatibility `CollabOpportunity` via
`LegacyOpportunityBridgeService::makeCompatibilityOpportunity()` and re-wraps them in
a `LengthAwarePaginator` (so the controller's `meta` block is unchanged). The status
filter now maps to `KolabStatus` (draft/published/closed map 1:1). Freshly created
kolabs list **immediately** — no apply needed to materialize a row. The
`OpportunityResource` field set is preserved (incl. `offer_photo`, `is_own`,
`applications_count`, timestamps).
`makeCompatibilityOpportunity()` was made public + DB-light (no longer round-trips
`collab_opportunities`); it builds the compat object purely in memory.

### `/me/dashboard` (`DashboardService` + `DashboardController`)
- `getOpportunityStats()` counts off `Profile::kolabs()` grouped by status (was
  `createdOpportunities()`). KolabStatus values match OfferStatus 1:1 → JSON shape
  identical.
- `getReceivedApplicationStats()` scopes via the **`kolab_id`** FK
  (`whereHas('kolab', creator_profile_id = me)`), with a fallback to the legacy
  `collabOpportunity` whereHas for any row still `kolab_id IS NULL` during the
  transition — so no application is miscounted.
- Upcoming-collaborations eager-load now includes `kolab` (+ `kolab.creatorProfile`)
  alongside `collabOpportunity`.
- `DashboardController` sources each upcoming collaboration's embedded `opportunity`
  from the related **kolab** (mapped through the bridge so `categories` keeps its
  shape), falling back to `collabOpportunity`, and is **null-safe** (see C).

### Application / Collaboration resources
`ApplicationResource` and `CollaborationResource` now source the embedded opportunity
object from the related **`kolab`** when that relation is loaded (mapped to the same
`OpportunitySummaryResource` shape), falling back to `collabOpportunity` otherwise.
To feed them, `kolab.creatorProfile` was added to the eager-load lists everywhere
`collabOpportunity[.creatorProfile]` was already loaded in `ApplicationController`,
`ApplicationService`, and `CollaborationService` (extra eager-load only; never
changes output). Query **filters** (e.g. `whereHas('collabOpportunity', ...)`,
`where('collab_opportunity_id', ...)`) were intentionally left on the legacy FK —
they are correct because every row has a compat opportunity until Phase 4, and
changing them is Phase 4 cleanup.

---

## C. Dashboard parse bug ("Unable to load dashboard data")

### Finding
The pre-Phase-2 `DashboardController` built each upcoming-collaboration opportunity
with **unconditional** property access:
```php
'opportunity' => [
    'id'         => $collaboration->collabOpportunity->id,      // ← fatals if null
    'title'      => $collaboration->collabOpportunity->title,
    'categories' => $collaboration->collabOpportunity->categories, // can be null
],
```
If `collabOpportunity` is ever null (relation not loaded, or row missing), the
endpoint 500s. Even when it loads, `categories` could serialize as `null`, which a
strict client model (expecting `List<String>`) can reject → the app's "Unable to
load dashboard data" parse failure on an otherwise-200 response.

### Fix
`DashboardController::resolveOpportunitySummary()` now:
- prefers the loaded **kolab** (bridge-mapped, so `categories` is derived
  consistently), falling back to `collabOpportunity`;
- is fully null-safe (`?->`), and always returns `categories` as an **array**
  (`?? []`), never null. `id`/`title` may still be null only if BOTH relations are
  absent (shouldn't happen in production), and that no longer fatals.

### Exact emitted payload (captured from a real request, Phase 2)

Business viewer (`GET /me/dashboard`):
```json
{
  "success": true,
  "data": {
    "opportunities":         { "total": 1, "published": 1, "draft": 0, "closed": 0 },
    "applications_received": { "total": 1, "pending": 0, "accepted": 1, "declined": 0 },
    "collaborations":        { "total": 1, "active": 0, "upcoming": 1, "completed": 0 },
    "upcoming_collaborations": [
      {
        "id": "<uuid>",
        "status": "scheduled",
        "scheduled_date": "2026-06-21",
        "opportunity": { "id": "<uuid>", "title": "Kolab X", "categories": ["food","wellness"] },
        "partner":     { "id": "<uuid>", "name": "Comm", "user_type": "community" }
      }
    ]
  }
}
```
Community viewer is identical except the top-level block is `applications_sent`
(`{ total, pending, accepted, declined, withdrawn }`) instead of `opportunities` +
`applications_received`, and `partner` points at the business.

### Residual shape risk to verify app-side (NOT changed here)
`partner` is built from nullable lookups: `partner.id`, `partner.name`, and
`partner.user_type` can each be **null** (e.g. a partner profile without a
business/community profile name). If the app's dashboard model declares any of these
non-nullable, that is a second parse trigger independent of the opportunity fix
above. Recommend the app treat `partner.*` as nullable. Backend left as-is to avoid
shape changes in Phase 2; flag for Phase 3 if the app cannot tolerate nulls.

---

## D. Tests

New: `tests/Feature/Api/V1/KolabSourceOfTruthPhase2Test.php` (8 tests, 56
assertions) — `/me/opportunities` returns freshly created kolabs with no compat row
+ preserves the resource shape; status filter maps to KolabStatus; dashboard
opportunity counts come from kolabs; received-application counts come via `kolab_id`;
upcoming-collaboration opportunity is sourced from the kolab (proven by a stale
legacy title); inverse-bridge backfill drives NULL kolab_id to 0; inverse bridge is
idempotent; community-creator offer mapping is faithful.

Updated to reflect the re-pointed reads (now seed `kolabs`, not
`collab_opportunities`): `OpportunityListingTest` (the four `my_opportunities` data
tests) and `DashboardTest::test_business_dashboard_returns_opportunity_stats`. The
`browse` tests are untouched — `GET /opportunities` still reads `collab_opportunities`
(re-pointing Explore/browse is out of Phase 2 scope; Discovery already uses kolabs).

### Results
- `php artisan test --filter "Application|Collaboration|Dashboard|Opportunity|Kolab"`
  → **270 passed (1061 assertions)**.
- Resource-adjacent set `--filter "Discovery|Feedback|Review|Chat|MyKolabs|Apply|Accept"`
  → 118 passed, 1 pre-existing unrelated landing-page asset failure
  (`/brand/logo-mark.svg` content assertion — independent of this work).
- `php -l` clean on every changed/added PHP file. `vendor/bin/pint` applied.
- The **full** suite (`php artisan test`) OOMs at 128M in `routes/api.php` route
  registration — this is **pre-existing**: it reproduces identically on the clean
  `feat/kolab-sot-phase1` baseline, so it is a harness/memory limitation, not a
  regression from Phase 2. Run targeted filters (above) or raise the child PHP
  `memory_limit`.

---

## Files changed

New:
- `app/Services/InverseLegacyOpportunityBridgeService.php`
- `database/migrations/2026_06_16_130000_inverse_bridge_backfill_kolabs_for_legacy_opportunities.php`
- `tests/Feature/Api/V1/KolabSourceOfTruthPhase2Test.php`

Modified:
- `app/Services/LegacyOpportunityBridgeService.php` (public + DB-light `makeCompatibilityOpportunity`)
- `app/Services/OpportunityService.php` (`/me/opportunities` reads kolabs + paginator remap)
- `app/Services/DashboardService.php` (opportunity stats off kolabs; received-apps via `kolab_id` + fallback; eager-load kolab)
- `app/Http/Controllers/Api/V1/DashboardController.php` (kolab-sourced, null-safe opportunity summary)
- `app/Http/Resources/Api/V1/ApplicationResource.php` (prefer kolab for embedded opportunity)
- `app/Http/Resources/Api/V1/CollaborationResource.php` (prefer kolab for embedded opportunity)
- `app/Http/Controllers/Api/V1/ApplicationController.php`, `app/Services/ApplicationService.php`,
  `app/Services/CollaborationService.php` (eager-load `kolab.creatorProfile` alongside collabOpportunity)
- `tests/Feature/Api/V1/OpportunityListingTest.php`, `tests/Feature/Api/V1/DashboardTest.php` (seed kolabs)

---

## What Phase 3 (app) must do next

1. Community + business Offers list/edit → `/kolabs/me` + `/kolabs/{id}` (business
   already does). After Phase 2, `/me/opportunities` already returns kolab-backed
   data in the legacy shape, so the app can migrate incrementally.
2. `create_opportunity_screen` → kolab create model (retire the legacy Opportunity
   boolean model: discount %, products[], "other" text → map into kolab
   needs/offering/deliverables). Unblocks the deferred admin-taxonomy wiring.
3. Make the dashboard model tolerate **nullable `partner.id/name/user_type`** (see
   C "Residual shape risk"). Confirm `categories` is read as a possibly-empty list.

## What Phase 4 (backend cleanup) must do next

1. Remove the dual-write in `ApplicationService::apply()` / `createCollaboration()`.
2. Make `applications.kolab_id` / `collaborations.kolab_id` **non-null**; drop the
   `collab_opportunity_id` columns + their FKs; drop the `collab_opportunities`
   table; remove `LegacyOpportunityBridgeService` +
   `InverseLegacyOpportunityBridgeService` once no longer referenced.
3. Re-point the remaining legacy query **filters** (`whereHas('collabOpportunity')`,
   `where('collab_opportunity_id', ...)`) and `GET /opportunities` (browse) to
   kolabs, or convert `/opportunities*` to thin aliases over kolabs / remove.
4. Update `docs/BACKEND-SCHEMA.md` to drop `collab_opportunities` and document
   `kolab_id` as the canonical FK.
