# Kolab Source-of-Truth — Phase 1 (additive foundation)

Branch: `feat/kolab-sot-phase1` (local only, not pushed)
Date: 2026-06-16
Plan: `kolabing-app/docs/plans/2026-06-16-kolab-source-of-truth-migration.md`

Phase 1 is **additive and backward-compatible**. No reads were re-pointed, no
columns or tables were dropped, and the API response shapes are unchanged. The app
keeps working with zero changes.

## What changed

### Migrations
1. `database/migrations/2026_06_16_120000_add_kolab_id_to_applications_and_collaborations.php`
   - Adds nullable `kolab_id` (uuid) to `applications` and `collaborations`.
   - FK → `kolabs.id`, `nullOnDelete`, indexed. `collab_opportunity_id` is kept
     untouched.
2. `database/migrations/2026_06_16_120100_backfill_kolab_id_on_applications_and_collaborations.php`
   - Data step. Sets `kolab_id = collab_opportunity_id` for every row whose
     `collab_opportunity_id` is already a real kolab id.
   - Safe because `LegacyOpportunityBridgeService` persists the compatibility
     `collab_opportunities` row with `id = kolab.id`
     (`persistCompatibilityOpportunity` → `fillFromKolab`: `$opportunity->id = $kolab->id`).
   - Counts and logs (migration output + Laravel log) the rows it could NOT backfill
     (true legacy: `collab_opportunity_id` not in `kolabs`). It does **not** delete or
     force those — their `kolab_id` stays NULL and they are Phase 2 work.

### Eloquent
- `app/Models/Application.php`: `kolab_id` added to `$fillable`; new `kolab()`
  BelongsTo (on `kolab_id`). `collabOpportunity()` kept intact.
- `app/Models/Collaboration.php`: `kolab_id` added to `$fillable`; new `kolab()`
  BelongsTo (on `kolab_id`). `collabOpportunity()` kept intact.
- `app/Models/Kolab.php`: new `applications()` and `collaborations()` HasMany (foreign
  key `kolab_id`).

### Dual-write
- `app/Services/ApplicationService.php`:
  - `apply()` now sets BOTH `collab_opportunity_id` and `kolab_id`.
  - `createCollaboration()` now sets BOTH; it prefers `application->kolab_id` and
    falls back to resolving from the opportunity id for pre-dual-write applications.
  - New private helper `resolveKolabId(string $opportunityId)`: returns the id when a
    kolab with that id exists, else `null`. Because the bridge persists with
    `id = kolab.id`, `kolab_id == collab_opportunity_id` for every kolab-originated row.

### Tests
- `tests/Feature/Api/V1/KolabSourceOfTruthDualWriteTest.php` (new): drives a real
  apply → accept flow through the API and asserts `kolab_id` is populated and equals
  `collab_opportunity_id` on BOTH `applications` and `collaborations`, plus that the
  new relations resolve.

## Legacy-row count (production `main`, read-only, 2026-06-16)

Counted with a read-only query against the production Postgres `main` db
(`collab_opportunity_id NOT IN (SELECT id FROM kolabs)` = true legacy):

| table          | total | will backfill (matched) | true legacy (left NULL) |
|----------------|-------|-------------------------|--------------------------|
| applications   | 29    | 9                       | **20**                   |
| collaborations | 12    | 3                       | **9**                    |

**This matters.** Most existing production rows are pre-bridge opportunities that
never had a kolab created. After the Phase 1 backfill, 20 applications and 9
collaborations will still have `kolab_id = NULL`. They are NOT broken (legacy reads
still use `collab_opportunity_id`), but they cannot move onto kolabs until an
inverse-bridge kolab is created for each legacy `collab_opportunity`.

(On a fresh test DB the backfill logs `0 row(s)` for both, as expected — there is no
production data in the in-memory SQLite test database.)

## How to run the migration

```bash
cd kolabing-v2
php artisan migrate          # runs both Phase 1 migrations; backfill echoes counts
# rollback (reverses both, additive only): php artisan migrate:rollback
```

Production note: validate against Postgres, not just SQLite. Both migrations use
plain `DB::table()->whereIn(subquery)->update()` and `foreignUuid(...)->constrained`
— no aggregates, no `lockForUpdate`, no JSON/`ILIKE`/`RETURNING` — so the
SQLite ↔ Postgres divergence rules in BACKEND-SCHEMA.md do not bite here. Run
`php artisan migrate` during a normal deploy; read the echoed counts to confirm the
production matched/legacy split matches the table above.

## Tests run

`php artisan test --filter "Application|Collaboration"` → **109 passed (380 assertions)**.
New dual-write test → **1 passed (12 assertions)**.

## What Phase 2 must do next (do NOT do in Phase 1)

1. **Inverse bridge for true-legacy rows.** Before re-pointing reads, create a
   `kolabs` row from each legacy `collab_opportunity` (the 20 + 9 rows above), then
   set their `kolab_id`. Without this, re-pointed reads would drop those rows.
2. **Re-point reads** (`OpportunityController::myOpportunities` /me/opportunities,
   `DashboardService`, Application/Collaboration resources) to source from `kolabs`
   via the new `kolab_id` / `Kolab::applications()` / `Kolab::collaborations()`
   relations, while keeping the response shapes identical so the app needs no change.
3. Keep the dual-write until Phase 4; do not make `kolab_id` non-null and do not drop
   `collab_opportunity_id` yet.
