# Remove Legacy collab_opportunities Code Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Delete all backend code that reads/writes the legacy `collab_opportunities` table, remove the `/opportunities` API routes, and add `/kolabs/{kolab}/applications` apply endpoints — while keeping the table physically (archived).

**Architecture:** `kolabs` is already the source of truth; `/opportunities` routes are compatibility shims over `KolabService`. We add the missing kolab-bound apply routes, strip the dual-write, delete the legacy classes/routes, neutralize the one migration that instantiates a deleted service, and migrate tests to the Kolab factory. The table and `collab_opportunity_id` columns stay; a follow-up PR drops them.

**Tech Stack:** Laravel 12, PHP 8.4, PostgreSQL, PHPUnit. Tests use `LazilyRefreshDatabase`. Response envelope: `{"success": bool, "data": ..., "message": ...}`. Auth via `actingAs($profile)` (Profile is the authenticatable).

**Spec:** `docs/superpowers/specs/2026-06-18-remove-legacy-collab-opportunities-design.md`
**Tracking:** Issue #30 (Kolabing Engineering board).

---

## File Structure

- `routes/api.php` — remove 10 `/opportunities*` routes; add 2 `/kolabs/{kolab}/applications` routes.
- `app/Http/Controllers/Api/V1/ApplicationController.php` — unchanged logic (methods already take a string id + `Kolab::findOrFail`); only PHPDoc URL comments updated.
- `app/Services/ApplicationService.php` — remove `resolveCollabOpportunityId()` + the `collab_opportunity_id` insert key.
- `app/Http/Controllers/Api/V1/OpportunityController.php` — delete.
- `app/Models/CollabOpportunity.php`, `app/Services/InverseLegacyOpportunityBridgeService.php`, `app/Console/Commands/MigrateLegacyOpportunitiesToKolabs.php`, `app/Http/Resources/Api/V1/OpportunityResource.php`, `app/Http/Resources/Api/V1/OpportunityCollection.php`, `database/factories/CollabOpportunityFactory.php`, `database/seeders/CollabOpportunitySeeder.php` — delete.
- `app/Models/Application.php`, `app/Models/Collaboration.php` — remove `collabOpportunity()` relationship + any related cast.
- `app/Console/Commands/SeedTestCollaboration.php` — repoint off `CollabOpportunity` to `Kolab`.
- `database/migrations/2026_06_16_130000_inverse_bridge_backfill_kolabs_for_legacy_opportunities.php` — neutralize `up()` to a no-op.
- Tests — delete 3 legacy files; migrate the rest to `Kolab::factory()`.
- Docs — `docs/BACKEND-SCHEMA.md`, `docs/ROLES-AND-PERMISSIONS.md`, `docs/ROLES-BACKEND-DB-MAP.md`, `BACKLOG.md`.

---

## Task 1: Add `/kolabs/{kolab}/applications` routes (apply flow replacement)

**Files:**
- Modify: `routes/api.php` (kolab routes block, ~line 708 after the close route)
- Test: `tests/Feature/Api/V1/KolabApplicationsRouteTest.php` (create)

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Kolab;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class KolabApplicationsRouteTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_community_can_apply_to_a_kolab_via_kolabs_route(): void
    {
        $business = Profile::factory()->business()->withSubscription()->create();
        $kolab = Kolab::factory()->published()->forCreator($business)->create();
        $community = Profile::factory()->community()->create();

        $response = $this->actingAs($community)
            ->postJson("/api/v1/kolabs/{$kolab->id}/applications", [
                'message' => 'We would love to collaborate.',
            ]);

        $response->assertCreated()->assertJsonPath('success', true);

        $this->assertDatabaseHas('applications', [
            'kolab_id' => $kolab->id,
            'applicant_profile_id' => $community->id,
            'collab_opportunity_id' => null,
        ]);
    }

    public function test_creator_can_list_applications_via_kolabs_route(): void
    {
        $business = Profile::factory()->business()->withSubscription()->create();
        $kolab = Kolab::factory()->published()->forCreator($business)->create();

        $this->actingAs($business)
            ->getJson("/api/v1/kolabs/{$kolab->id}/applications")
            ->assertOk()
            ->assertJsonPath('success', true);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --compact --filter=KolabApplicationsRouteTest`
Expected: FAIL — 404 (routes not defined).

- [ ] **Step 3: Add the routes**

In `routes/api.php`, immediately after the kolab close route (`Route::post('kolabs/{kolab}/close', ...)`), add:

```php
        // List applications for a kolab (creator only)
        Route::get('kolabs/{kolab}/applications', [ApplicationController::class, 'forOpportunity'])
            ->name('api.v1.kolabs.applications.index');

        // Apply to a kolab
        Route::post('kolabs/{kolab}/applications', [ApplicationController::class, 'store'])
            ->name('api.v1.kolabs.applications.store');
```

(`ApplicationController` is already imported in this file via the existing `/opportunities/{opportunity}/applications` routes; its `forOpportunity`/`store` methods take a `string $opportunity` and call `Kolab::query()->findOrFail(...)`, so the kolab id binds directly.)

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --compact --filter=KolabApplicationsRouteTest`
Expected: PASS (2 tests).

- [ ] **Step 5: Commit**

```bash
git add routes/api.php tests/Feature/Api/V1/KolabApplicationsRouteTest.php
git commit -m "feat: add /kolabs/{kolab}/applications apply routes"
```

---

## Task 2: Stop writing `collab_opportunity_id` (remove dual-write)

**Files:**
- Modify: `app/Services/ApplicationService.php` (the `apply()` insert ~line 41-50, the second usage ~line 385, and `resolveCollabOpportunityId()` ~line 402-411)
- Test: reuse `KolabApplicationsRouteTest` (already asserts `collab_opportunity_id => null`)

- [ ] **Step 1: Confirm the guard test exists**

`test_community_can_apply_to_a_kolab_via_kolabs_route` already asserts `'collab_opportunity_id' => null`. Run it to confirm current state:

Run: `php artisan test --compact --filter=test_community_can_apply_to_a_kolab_via_kolabs_route`
Expected: PASS (today the dual-write resolves null because the kolab has no legacy row).

- [ ] **Step 2: Remove the dual-write from `apply()`**

In `app/Services/ApplicationService.php`, delete the `'collab_opportunity_id' => $this->resolveCollabOpportunityId($opportunity->id),` line from the `Application::create([...])` array in `apply()`.

- [ ] **Step 3: Remove the second usage**

Find the other `'collab_opportunity_id' => $this->resolveCollabOpportunityId(...)` (~line 385, in the accept/collaboration-creation path) and delete that array key as well.

- [ ] **Step 4: Delete the helper method**

Remove the entire `resolveCollabOpportunityId(?string $kolabId): ?string` method (~lines 397-411) and the now-unused `use App\Models\CollabOpportunity;` import at the top of the file.

- [ ] **Step 5: Run tests**

Run: `php artisan test --compact --filter=KolabApplicationsRouteTest`
Expected: PASS — `collab_opportunity_id` stays null because the column defaults to null and is no longer written.

- [ ] **Step 6: Commit**

```bash
git add app/Services/ApplicationService.php
git commit -m "refactor: stop dual-writing collab_opportunity_id on applications"
```

---

## Task 3: Neutralize the bridge-backfill migration

**Files:**
- Modify: `database/migrations/2026_06_16_130000_inverse_bridge_backfill_kolabs_for_legacy_opportunities.php`

- [ ] **Step 1: Rewrite the migration to a no-op**

Replace the entire file contents with:

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;

/**
 * Historically backfilled canonical kolabs from legacy collab_opportunities via
 * InverseLegacyOpportunityBridgeService. That service has been removed; the legacy
 * table is archived and already fully mirrored into kolabs. This migration is now
 * inert (it already ran in every live environment, and a fresh database has no
 * legacy rows to backfill). Kept as a no-op to preserve the migration ledger.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Intentionally a no-op. See class docblock.
    }

    public function down(): void
    {
        // Intentionally a no-op.
    }
};
```

- [ ] **Step 2: Verify a fresh migration runs clean**

Run: `php artisan migrate:fresh --env=testing`
Expected: all migrations run with no "class not found" error.

- [ ] **Step 3: Commit**

```bash
git add database/migrations/2026_06_16_130000_inverse_bridge_backfill_kolabs_for_legacy_opportunities.php
git commit -m "refactor: neutralize legacy bridge-backfill migration to no-op"
```

---

## Task 4: Delete legacy-behavior test files

**Files:**
- Delete: `tests/Feature/Api/V1/KolabSourceOfTruthDualWriteTest.php`
- Delete: `tests/Feature/Api/V1/OpportunityCompatibilityTest.php`
- Delete: `tests/Feature/Console/MigrateLegacyOpportunitiesToKolabsTest.php`

- [ ] **Step 1: Delete the files**

```bash
git rm tests/Feature/Api/V1/KolabSourceOfTruthDualWriteTest.php \
       tests/Feature/Api/V1/OpportunityCompatibilityTest.php \
       tests/Feature/Console/MigrateLegacyOpportunitiesToKolabsTest.php
```

- [ ] **Step 2: Commit**

```bash
git commit -m "test: remove legacy collab_opportunities behavior tests"
```

---

## Task 5: Migrate remaining tests off the CollabOpportunity factory

**Files (audit + edit each):**
- `tests/Feature/Api/V1/OpportunityListingTest.php`
- `tests/Feature/Api/V1/OpportunityCreationLimitTest.php`
- `tests/Feature/Api/V1/KolabSourceOfTruthPhase2Test.php`
- `tests/Feature/Api/V1/ApplicationAcceptTest.php`, `ApplicationDeclineWithdrawTest.php`, `ApplicationListsTest.php`, `ApplicationDetailTest.php`
- `tests/Feature/Api/V1/ChatTest.php`, `ChatActiveListTest.php`, `DashboardTest.php`, `CollaborationFeedbackTest.php`, `KolabCrudTest.php`
- `tests/Feature/Admin/KolabManagementTest.php`, `LifecycleTimestampsTest.php`, `ChallengesAdminTest.php`, `StatsDashboardTest.php`, `CollaborationCompletionTest.php`

- [ ] **Step 1: List every test still touching legacy symbols**

Run: `grep -rn "CollabOpportunity\|collab_opportunit\|/opportunities" tests/`
Expected: a list of files/lines to fix. Work through each.

- [ ] **Step 2: Apply the mechanical substitutions per file**

For each match, apply:
- `use App\Models\CollabOpportunity;` → `use App\Models\Kolab;`
- `CollabOpportunity::factory()` → `Kolab::factory()` (keep `->published()`, add `->forCreator($business)` where the legacy `->for($creator)` set the creator)
- `assertDatabaseHas('collab_opportunities', ...)` → `assertDatabaseHas('kolabs', ...)`
- assertions on `collab_opportunity_id` → `kolab_id`
- request paths `"/api/v1/opportunities/..."` → `"/api/v1/kolabs/..."`; apply paths `"/api/v1/opportunities/{id}/applications"` → `"/api/v1/kolabs/{id}/applications"`

Note Kolab requires non-null `title`, `description`, `preferred_city` (the factory `definition()` already supplies these). Use `venuePromotion()`/`productPromotion()`/`forRecipientCommunity()` states to match the intent each old test relied on.

- [ ] **Step 3: Run the affected tests file-by-file**

For each edited file:
Run: `php artisan test --compact tests/Feature/Api/V1/<File>.php`
Expected: PASS.

- [ ] **Step 4: Confirm tests no longer reference legacy symbols**

Run: `grep -rn "CollabOpportunity\|collab_opportunit" tests/`
Expected: no output.

- [ ] **Step 5: Commit**

```bash
git add tests/
git commit -m "test: migrate remaining tests to Kolab factory and /kolabs routes"
```

---

## Task 6: Remove the OpportunityController and `/opportunities` routes

**Files:**
- Modify: `routes/api.php` (remove routes ~638-723 that use `OpportunityController`; KEEP `discovery/opportunities`)
- Delete: `app/Http/Controllers/Api/V1/OpportunityController.php`

- [ ] **Step 1: Remove the legacy routes**

In `routes/api.php`, delete these route registrations (keep `Route::get('discovery/opportunities', ...)`):
- `opportunities` index, `me/opportunities`, `opportunities/{opportunity}` show, `opportunities` store, `opportunities/{opportunity}` update, `opportunities/{opportunity}` destroy, `opportunities/{opportunity}/publish`, `opportunities/{opportunity}/close`
- `opportunities/{opportunity}/applications` index + store

Then remove the now-unused `use App\Http\Controllers\Api\V1\OpportunityController;` import.

- [ ] **Step 2: Delete the controller**

```bash
git rm app/Http/Controllers/Api/V1/OpportunityController.php
```

- [ ] **Step 3: Verify routes resolve and the apply flow still works**

Run: `php artisan route:list --path=kolabs`
Expected: lists kolabs CRUD + `kolabs/{kolab}/applications` GET/POST. No `opportunities` CRUD remain (only `discovery/opportunities`).

Run: `php artisan test --compact --filter=KolabApplicationsRouteTest`
Expected: PASS.

- [ ] **Step 4: Commit**

```bash
git add routes/api.php
git commit -m "refactor: remove legacy /opportunities routes and controller"
```

---

## Task 7: Delete the legacy classes and clean model relations

**Files:**
- Delete: `app/Models/CollabOpportunity.php`, `app/Services/InverseLegacyOpportunityBridgeService.php`, `app/Console/Commands/MigrateLegacyOpportunitiesToKolabs.php`, `app/Http/Resources/Api/V1/OpportunityResource.php`, `app/Http/Resources/Api/V1/OpportunityCollection.php`, `database/factories/CollabOpportunityFactory.php`, `database/seeders/CollabOpportunitySeeder.php`
- Modify: `app/Models/Application.php`, `app/Models/Collaboration.php` (remove `collabOpportunity()` relationship + related cast/import)
- Modify: `app/Console/Commands/SeedTestCollaboration.php` (repoint to `Kolab`)
- Modify: `database/seeders/DatabaseSeeder.php` if it calls `CollabOpportunitySeeder`
- Modify: `app/Http/Resources/Api/V1/ApplicationResource.php`, `CollaborationResource.php` if they reference `collabOpportunity`

- [ ] **Step 1: Repoint `SeedTestCollaboration` to Kolab**

Open `app/Console/Commands/SeedTestCollaboration.php`. Replace `use App\Models\CollabOpportunity;` with `use App\Models\Kolab;` and any `CollabOpportunity::...`/`CollabOpportunity::factory()` with the `Kolab` equivalent (`Kolab::factory()->published()->forCreator($business)`). Remove any line that sets `collab_opportunity_id`.

- [ ] **Step 2: Remove relationships on Application & Collaboration**

In `app/Models/Application.php` and `app/Models/Collaboration.php`, delete the `collabOpportunity()` relationship method, the `use App\Models\CollabOpportunity;` import, and any `collab_opportunity_id` entry in a `casts()`/`$fillable` that exists only for that relationship (leave the column out of code; the DB column itself stays). Keep the `kolab()` relationship.

- [ ] **Step 3: Fix resources if they reference the relationship**

Run: `grep -n "collabOpportunity\|CollabOpportunity" app/Http/Resources/Api/V1/ApplicationResource.php app/Http/Resources/Api/V1/CollaborationResource.php`
For any hit, remove that branch (the resources already prefer `KolabResource($this->kolab)`).

- [ ] **Step 4: Remove the seeder call**

Run: `grep -n "CollabOpportunitySeeder" database/seeders/DatabaseSeeder.php`
If present, delete that `$this->call(...)` line and its import.

- [ ] **Step 5: Delete the legacy classes**

```bash
git rm app/Models/CollabOpportunity.php \
       app/Services/InverseLegacyOpportunityBridgeService.php \
       app/Console/Commands/MigrateLegacyOpportunitiesToKolabs.php \
       app/Http/Resources/Api/V1/OpportunityResource.php \
       app/Http/Resources/Api/V1/OpportunityCollection.php \
       database/factories/CollabOpportunityFactory.php \
       database/seeders/CollabOpportunitySeeder.php
```

- [ ] **Step 6: Verify nothing references the deleted symbols**

Run: `grep -rn "CollabOpportunity\|InverseLegacyOpportunityBridge\|MigrateLegacyOpportunities\|OpportunityResource\|OpportunityCollection" app/ database/ routes/`
Expected: no output.

- [ ] **Step 7: Run the full suite + a fresh migrate**

Run: `php artisan migrate:fresh --env=testing && php artisan test --compact`
Expected: migrations clean; suite green.

- [ ] **Step 8: Commit**

```bash
git add app/ database/
git commit -m "refactor: delete legacy collab_opportunities model, service, command, resources, factory, seeder"
```

---

## Task 8: Update documentation

**Files:**
- Modify: `docs/BACKEND-SCHEMA.md`, `docs/ROLES-AND-PERMISSIONS.md`, `docs/ROLES-BACKEND-DB-MAP.md`, `BACKLOG.md`

- [ ] **Step 1: BACKEND-SCHEMA.md**

Mark `collab_opportunities` and the `collab_opportunity_id` columns on `applications`/`collaborations` as **ARCHIVED — no longer read/written by application code; scheduled for drop in a follow-up**. State `kolabs` is the sole source of truth for the opportunity lifecycle. Bump any "Last updated" date.

- [ ] **Step 2: ROLES docs**

In `docs/ROLES-AND-PERMISSIONS.md` and `docs/ROLES-BACKEND-DB-MAP.md`, update the create/apply-flow surface to reference `/kolabs/*` and the new `/kolabs/{kolab}/applications` routes (the `/opportunities` routes no longer exist). Bump the *Last updated* date at the top of each file and tick/add the relevant item in the mistakes-to-fix checklist.

- [ ] **Step 3: BACKLOG.md**

Add to *Incomplete Features*: "Remove legacy collab_opportunities code (archive table) — #30" and a follow-up *New Features*/*Fixes* note: "Drop collab_opportunities table + collab_opportunity_id columns (after one release on kolabs-only)." Bump *Last updated*.

- [ ] **Step 4: Commit**

```bash
git add docs/ BACKLOG.md
git commit -m "docs: mark collab_opportunities archived; update roles + backlog for /kolabs"
```

---

## Task 9: Final verification

- [ ] **Step 1: Global audit**

Run: `grep -rn "CollabOpportunity\|collab_opportunit" app/ routes/ database/factories/ database/seeders/`
Expected: no output (the create-table + alter migrations and the column definitions remain by design — restrict the grep to the dirs above so they aren't flagged).

- [ ] **Step 2: Full suite + fresh migration**

Run: `php artisan migrate:fresh --env=testing && php artisan test --compact`
Expected: all green. Record the test count.

- [ ] **Step 3: Pint**

Run: `vendor/bin/pint`
Expected: clean (no style violations introduced).

- [ ] **Step 4: Route sanity**

Run: `php artisan route:list --path=opportunities`
Expected: only `discovery/opportunities` remains.

- [ ] **Step 5: Open the PR**

Push the branch and open a PR into `master` using the repo PR template. Fill every required section; in *Mobile impact (kolabing-app)* paste the `/opportunities/*` → `/kolabs/*` mapping and link the (to-be-created) `kolabing-app` ticket. `Closes #30`. Paste the test count + `pint` result into *Testing*.

---

## Self-Review notes

- **Spec coverage:** every Scope IN/OUT item maps to a task (routes→T1/T6, dual-write→T2, migration→T3, tests→T4/T5, classes→T7, docs→T8, audit/PR→T9). Discovery feed explicitly left untouched (T6 step 1). Table/columns kept physically (no drop migration in this plan — follow-up only).
- **No placeholders:** every code step shows the code; every command shows expected output.
- **Type consistency:** controller methods keep names `forOpportunity`/`store` with `string $opportunity` param (verified in source); routes bind `{kolab}` to that string param. `Kolab::factory()` states used are real (`published`, `forCreator`, `venuePromotion`, `productPromotion`, `forRecipientCommunity`).
