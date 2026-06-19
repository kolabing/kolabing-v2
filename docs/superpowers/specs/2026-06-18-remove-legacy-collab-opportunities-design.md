# Remove legacy `collab_opportunities` code (archive the table)

**Date:** 2026-06-18
**Branch:** `chore/remove-legacy-collab-opportunities`
**Type:** Chore / cleanup — **breaking API change** (mobile must migrate)

---

## Background

`kolabs` is the source of truth. The legacy `collab_opportunities` table is fully
mirrored: every legacy row has a matching `kolab` (verified: 0 legacy rows without a
kolab), every `applications`/`collaborations` row has a complete `kolab_id` (0 nulls),
and the remaining `collab_opportunity_id` values (30 applications, 12 collaborations)
are redundant. The legacy `/opportunities` API routes were already rewritten to
read/write `kolabs` via `KolabService`, so they are pure compatibility shims.

Goal: **never read or write the legacy table or its support code again.** Delete all
code that references it; physically keep the table and `collab_opportunity_id` columns
for one release as a rollback safety net (dropped in a follow-up PR).

## Decisions (confirmed with the user)

1. **Remove the `/opportunities` routes entirely** — breaking change; mobile migrates to `/kolabs/*`.
2. **Archive the table** — remove all code references now; drop table + columns in a follow-up PR.
3. **Add `/kolabs/{kolab}/applications` (GET + POST)** as the apply-flow replacement.
4. **Delete** the three legacy-behavior test files (listed below); migrate the rest to the Kolab factory.

## Scope IN — delete

| Kind | Item |
|---|---|
| Model | `app/Models/CollabOpportunity.php` |
| Service | `app/Services/InverseLegacyOpportunityBridgeService.php` |
| Service edit | `app/Services/ApplicationService.php` — remove `resolveCollabOpportunityId()`; stop writing `collab_opportunity_id` (always null / drop the key from the insert) |
| Command | `app/Console/Commands/MigrateLegacyOpportunitiesToKolabs.php` |
| Command edit | `app/Console/Commands/SeedTestCollaboration.php` — repoint off `CollabOpportunity` to `Kolab` |
| Resources | `app/Http/Resources/Api/V1/OpportunityResource.php`, `OpportunityCollection.php` (confirmed dead) |
| Factory | `database/factories/CollabOpportunityFactory.php` |
| Seeder | `database/seeders/CollabOpportunitySeeder.php` (+ remove any DatabaseSeeder call) |
| Controller | `app/Http/Controllers/Api/V1/OpportunityController.php` |
| Routes | All 8 `/opportunities` CRUD/publish/close routes + 2 nested `/opportunities/{id}/applications` routes in `routes/api.php` |
| Model relations | `collabOpportunity()` relationship + related casts on `app/Models/Application.php` and `app/Models/Collaboration.php`; update `ApplicationResource`/`CollaborationResource` if they reference it |

## Scope IN — add

- **`GET /api/v1/kolabs/{kolab}/applications`** → `ApplicationController@forOpportunity` (kolab-bound), name `api.v1.kolabs.applications.index`.
- **`POST /api/v1/kolabs/{kolab}/applications`** → `ApplicationController@store` (kolab-bound), name `api.v1.kolabs.applications.store`.
- Confirm `ApplicationController` methods + `ApplicationPolicy` resolve the route param as a `Kolab` (they already operate on `kolab_id` internally; the binding param just changes name from `{opportunity}` to `{kolab}`).

## Scope OUT — leave untouched

- **`discovery/opportunities` Explore feed** (`DiscoveryOpportunityController`/`Service`/`Collection`/`Resource`) — queries `kolabs` only, never the legacy table. Renaming is separate naming debt and another mobile break. Not in this change.
- **Table + `collab_opportunity_id` columns** stay physically (archive). Follow-up PR drops them.
- **All historical migrations** stay (the table must still exist), except the one edit below.

## The backfill migration (the one fork)

`database/migrations/2026_06_16_130000_inverse_bridge_backfill_kolabs_for_legacy_opportunities.php`
calls `app(InverseLegacyOpportunityBridgeService::class)->backfillAll()` at runtime.
Deleting the service breaks `migrate:fresh` (tests use a fresh DB).

**Resolution:** rewrite that migration's `up()` to a safe no-op and remove the
`use App\Services\InverseLegacyOpportunityBridgeService;` import. It already ran in
production; on a fresh DB `collab_opportunities` is empty so there is nothing to
backfill. Leave a short docblock explaining why it is now inert. Do not delete the
migration file (keeps the ledger intact for already-migrated environments).

## Tests

**Delete** (test the removed behavior; approved):
- `tests/Feature/Api/V1/KolabSourceOfTruthDualWriteTest.php`
- `tests/Feature/Api/V1/OpportunityCompatibilityTest.php`
- `tests/Feature/Console/MigrateLegacyOpportunitiesToKolabsTest.php`

**Migrate to the Kolab factory / `/kolabs` routes** (keep coverage):
- `tests/Feature/Api/V1/OpportunityListingTest.php`
- `tests/Feature/Api/V1/OpportunityCreationLimitTest.php`
- `tests/Feature/Api/V1/KolabSourceOfTruthPhase2Test.php` (drop any legacy-table assertions; keep kolab assertions)
- Application/Collaboration/Chat/Dashboard/Admin tests that build a `CollabOpportunity::factory()` → switch to `Kolab::factory()`; any that assert on `collab_opportunity_id` → assert on `kolab_id`.
- Update apply-flow tests to call `POST /kolabs/{kolab}/applications`.

**Audit step:** after edits, `grep -r "CollabOpportunity\|collab_opportunit\|/opportunities" tests/` must return only intentional matters (ideally nothing). Full suite green + `vendor/bin/pint` clean before PR.

## Docs to update (same PR)

- `docs/BACKEND-SCHEMA.md` — mark `collab_opportunities` + `collab_opportunity_id` columns as **archived/deprecated, scheduled for drop**; note `kolabs` is sole source of truth.
- `docs/ROLES-AND-PERMISSIONS.md` + `docs/ROLES-BACKEND-DB-MAP.md` — update the create/apply-flow surface to reference `/kolabs/*` (incl. new apply routes); bump *Last updated*; tick mistakes-to-fix.
- `BACKLOG.md` — add this cleanup to *Incomplete Features* (and a follow-up *drop table* item); bump *Last updated*.

## Process / tracking

- **Mobile impact (REQUIRED):** breaking. `kolabing-app` ticket with the full URL mapping (`/opportunities/*` → `/kolabs/*`, plus the two new apply endpoints). PR links it.
- GitHub Projects item (standard ticket template) before merge; PR uses the repo PR template with every required section; `Closes #<id>`.
- Branch `chore/remove-legacy-collab-opportunities` → PR into `master` (protected; only `olucvolkan` merges).

## Follow-up PR (not this one)

Drop `collab_opportunities` table + `collab_opportunity_id` columns (with FK drops) once one release has shipped on `kolabs`-only code and mobile is confirmed migrated.

## Risks

- **Mobile breakage** if shipped before the app migrates — mitigated by the mobile ticket gating release.
- **Route param rename** `{opportunity}` → `{kolab}` must be reflected in `ApplicationController` signatures, policy resolution, and any route-model binding.
- **Fresh-migration integrity** — covered by the no-op migration edit; verify `php artisan migrate:fresh` runs clean.
