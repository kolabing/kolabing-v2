# Multi-Kolab Event MVP — Progress Log

Plan: `2026-08-12-multi-kolab-event-mvp.md`. One entry per task, in execution order.
Do not mark a task complete unless its focused tests + relevant regression tests pass.

---

## Task 1: Verify Prerequisites and Freeze the Contracts

- **Status:** Completed
- **Start:** 2026-08-13 (session start)
- **Completion:** 2026-08-13
- **Files changed:**
  - Created `docs/superpowers/specs/2026-08-12-multi-kolab-event-api-contract.md`
  - Created `docs/superpowers/progress/2026-08-12-multi-kolab-event-mvp-progress.md` (this file)
- **Tests run:**
  - Backend: `php -d memory_limit=2048M vendor/bin/phpunit` (full suite) → **1413 passed, 6554 assertions, 0 failures**. (`php artisan test` OOM'd at the default 128M CLI memory_limit on an unrelated pre-existing large-suite issue; running the same suite via `vendor/bin/phpunit` directly with a raised memory_limit is the working equivalent and reflects no code change.)
  - Flutter: `flutter analyze` → 0 errors, ~1071 pre-existing info/warning lint items (style only). `flutter test` → **278 passed / 9 failed** (baseline; failures pre-date this branch, unrelated to Multi-Kolab — see report below).
- **Commit hash:** (recorded after this turn's commit — see chat report)
- **Deviations from plan:**
  - `docs/BACKEND-SCHEMA.md` does not exist in this repo (confirmed via `docs/ROLES-BACKEND-DB-MAP.md:1-8`, which is the closest existing schema doc and was read in its place).
  - The "UGC moderation interface" the plan assumes (a proactive text filter) does not exist; the real `ModerationService` is reactive block/report only. Flagged in the contract doc §12 and in the chat report — needs a product decision before Task 4/5, not resolved here.
  - No local Postgres engine is configured in this dev environment (`.env` → `DB_CONNECTION=sqlite`, `APP_ENV=testing`, `psql` not installed). Task 6's requirement to test acceptance concurrency "against the production database engine, not SQLite only" is currently blocked — flagged for before Task 6 starts.
- **Unresolved risks:**
  - Moderation interface decision (above).
  - Postgres availability for Task 6 concurrency tests (above).
- **Next task:** Task 2 (Add Domain Enums, Tables, Models, and Factories) — not started, awaiting approval per the turn's instructions.

---

## Task 2: Add Domain Enums, Tables, Models, and Factories

- **Status:** Completed
- **Start / completion:** 2026-08-13 (single session)
- **Founder decisions applied:**
  1. **Moderation:** reactive block/report model only, no proactive filter —
     see contract doc §12 (updated this task). Task 2 itself adds no
     moderation code; it only leaves `MultiKolabEvent`/`MultiKolabRole`/
     `MultiKolabRoleApplication` as ordinary Eloquent models with UUID `id`s
     so `ContentReport.target_id` can reference them later (Tasks 4/5/7).
  2. **PostgreSQL:** not required for Task 2; all work done against SQLite
     `:memory:` (`.env` → `APP_ENV=testing`), migrations written portably (no
     raw SQL, no SQLite-only syntax). **Task 6 concurrency testing is blocked
     until an isolated Postgres dev/test instance is provisioned — see
     "PostgreSQL prerequisite" below.**
  3. **Flutter branch:** created — see below.
- **Flutter feature branch:** `feat/multi-kolab-event-mvp` in
  `/Users/macbook/Documents/kolabing-app`, branched from `origin/master` at
  `cf10f496` (NOT from local `fix/ux-audit-batch2`, which is 47 commits ahead
  of `origin/master` with unrelated, unmerged UX-fix work and is unsuitable
  as a feature base). Untracked `build/` preserved, not committed. No Flutter
  source files touched this task.
- **Files created:**
  - Enums: `app/Enums/MultiKolabEventStatus.php`,
    `app/Enums/MultiKolabRoleStatus.php`,
    `app/Enums/MultiKolabRoleApplicationStatus.php`,
    `app/Enums/MultiKolabEligibleAccountType.php`,
    `app/Enums/MultiKolabCompensationType.php`,
    `app/Enums/OrganizerCapability.php`
  - Migrations (all `2026_08_13_*`):
    `000001_create_multi_kolab_events_table`,
    `000002_create_multi_kolab_roles_table`,
    `000003_create_multi_kolab_role_applications_table`,
    `000004_create_multi_kolab_event_status_events_table`,
    `000005_create_organizer_entitlements_table`,
    `000006_add_multi_kolab_parent_columns_to_kolabs_table`
  - Models: `app/Models/MultiKolabEvent.php`, `MultiKolabRole.php`,
    `MultiKolabRoleApplication.php`, `MultiKolabEventStatusEvent.php`,
    `OrganizerEntitlement.php`
  - Factories: `database/factories/MultiKolabEventFactory.php`,
    `MultiKolabRoleFactory.php`, `MultiKolabRoleApplicationFactory.php`,
    `MultiKolabEventStatusEventFactory.php`, `OrganizerEntitlementFactory.php`
  - Test: `tests/Unit/MultiKolab/MultiKolabModelsTest.php` (24 tests)
- **Files modified:**
  - `app/Models/Kolab.php` — added nullable `multi_kolab_event_id`/
    `multi_kolab_role_id` to `$fillable`, `multiKolabEvent()`/
    `multiKolabRole()` `BelongsTo` relations, PHPDoc.
  - `app/Models/Profile.php` — added `organizerEntitlements(): HasMany`
    relation only (no `hasEventCreatorEntitlement()` logic — reserved for
    Task 3 per this turn's instruction).
  - `docs/superpowers/specs/2026-08-12-multi-kolab-event-api-contract.md` —
    §12 updated with the founder's moderation decision and the binding
    requirements for Tasks 4/5/7 (reportable surfaces, new
    `ContentReport.target_type` values, private-field exclusions).
- **Database constraints implemented:**
  - UUID primary keys (`$table->uuid('id')->primary()`, `HasUuids` on every
    model) — matches `kolabs`/`applications` convention.
  - `foreignUuid(...)->constrained(...)->cascadeOnDelete()` for
    event→creator, role→event, application→role, application→applicant,
    status-event→event.
  - `foreignUuid('actor_profile_id')->nullable()->nullOnDelete()` on the
    status-history table (null = system/maintainer, mirrors
    `collaborations.cancelled_by_profile_id`).
  - **Restrictive FK exactly as specified in the plan's Task 2 snippet:**
    `kolabs.multi_kolab_event_id` / `kolabs.multi_kolab_role_id` are
    `nullable()->constrained()->restrictOnDelete()` — a parent event/role
    cannot be hard-deleted while a child Kolab still references it.
    `multi_kolab_role_applications.kolab_id` is also
    `nullable()->constrained('kolabs')->restrictOnDelete()` for the same
    reason in the other direction.
  - `$table->unique(['multi_kolab_role_id', 'applicant_profile_id'])` on
    `multi_kolab_role_applications` — verified by
    `test_role_application_enforces_unique_role_and_applicant` (expects
    `QueryException` on the second insert).
  - `unsignedInteger('positions_needed')->default(1)` and
    `unsignedInteger('positions_filled')->default(0)` — DB-level guarantee of
    `>= 0` on both columns (SQLite/Postgres both support `UNSIGNED`-equivalent
    via Laravel's unsigned integer type).
- **Constraints deferred to transactional/service-layer enforcement (not DB
  CHECK):**
  - `positions_needed >= 1` — not enforced at the DB level. No existing
    migration in this codebase uses a raw `CHECK` constraint (confirmed by
    grep), so adding one here would be a first-of-its-kind, DB-engine-specific
    addition without a proven-safe project convention to follow, which the
    instructions for this task explicitly require justifying first. Deferred
    to Form Request validation in Task 4 (`positions_needed: ['required',
    'integer', 'min:1']`).
  - `positions_filled <= positions_needed` — same reasoning; this is exactly
    the invariant the plan's own Task 6 already assigns to a locked
    `DB::transaction()` (`SELECT ... FOR UPDATE` equivalent), so a DB CHECK
    would be redundant with, not a substitute for, that transactional
    enforcement. Deferred to Task 6.
  - Both are explicitly **not** claimed as validated by SQLite test runs —
    SQLite's write-locking model does not exercise the same concurrency path
    as Postgres `SELECT ... FOR UPDATE`, and no capacity-conflict test exists
    yet (that's Task 6).
- **Focused tests:** `php -d memory_limit=1024M vendor/bin/phpunit tests/Unit/MultiKolab/MultiKolabModelsTest.php`
  - Before implementation: **24 tests, 23 errors** (missing classes — expected TDD-red state; 1 assertion-only test passed trivially before its own class dependency was hit).
  - After implementation: **24 tests, 39 assertions — OK.**
- **Regression tests:**
  - `vendor/bin/phpunit --testsuite=Unit` → **104 tests, 182 assertions — OK.**
  - `vendor/bin/phpunit --filter=Kolab` → **157 tests, 597 assertions — OK.**
  - Full suite `php -d memory_limit=2048M vendor/bin/phpunit` → **1437 tests
    (1413 baseline + 24 new), 6593 assertions — OK. 0 failures.**
- **Migration cycle verification (SQLite in-memory test env only, target
  proven before running — see below):**
  - `php artisan migrate:fresh --env=testing --force` against `.env`'s
    `DB_CONNECTION=sqlite` / `DB_DATABASE=:memory:` (confirmed via `grep` on
    `.env` immediately before running) — all migrations including the 6 new
    ones ran clean.
  - Rollback: `:memory:` SQLite cannot be rolled back across separate
    `artisan` process invocations (each process gets a fresh empty
    in-memory DB), so rollback was verified against a throwaway persistent
    SQLite file at `/tmp/mk_rollback_test.sqlite` (`DB_CONNECTION`/
    `DB_DATABASE` overridden **only** via inline env vars on the command,
    `.env` untouched, file deleted after). `migrate` → `migrate:rollback
    --step=6` → `migrate` cycle: **first rollback attempt failed** —
    `add_multi_kolab_parent_columns_to_kolabs_table::down()` tried
    `dropConstrainedForeignId()` while a manually-added index on the same
    column still existed, which SQLite's drop-column table-rebuild rejected
    with "no such column" during index recreation. **Fixed** by explicitly
    `dropIndex()`-ing both columns in a separate `Schema::table()` call
    before `dropConstrainedForeignId()`. Re-ran the full cycle: clean
    rollback of all 6 migrations, then clean re-migrate. This is a real bug
    that TDD/rollback verification caught before commit.
- **Deviations from the frozen API contract:** none in shape/naming — table
  and column names match the contract's entity fields exactly (`title`,
  `description`, `positions_needed`, `positions_filled`, `pitch`,
  `availability`, `kolab_id`, etc.). The contract itself was amended (§12
  only) to record the founder's moderation decision — a documentation
  clarification, not a schema deviation.
- **PostgreSQL prerequisite for Task 6 (blocking, not resolved):** this dev
  environment has no local Postgres (`psql` not installed, no
  docker-compose/Sail Postgres service configured). Task 6's row-locking
  (`SELECT ... FOR UPDATE`) concurrency test **must** run against Postgres,
  not SQLite — SQLite's locking semantics differ enough that a passing SQLite
  result would not prove the production behavior. Before Task 6 starts, this
  needs either (a) a local Postgres install/container the user approves, or
  (b) a provisioned isolated Postgres dev/test instance. Not started; will
  propose an approach and stop for approval before Task 6, per instruction.
- **Commit:** `feat: add multi-kolab event domain model` (see chat report for
  hash — recorded after commit).
- **Next task:** Task 3 (Add Independent Event Creator Entitlement) — not
  started, awaiting approval.

## Task 3: Add Independent Event Creator Entitlement

- **Status:** Completed
- **Files created:**
  - `app/Services/OrganizerEntitlementService.php` — `grant(Profile, int $months = 12): OrganizerEntitlement`, `revoke(Profile): void`. Idempotent grant reuses the existing `(profile_id, capability)` row (mirrors `ManagedProfileService::grantSubscription()`'s `firstOrNew` pattern) rather than duplicating.
  - `tests/Feature/MultiKolab/OrganizerEntitlementTest.php` (13 tests)
- **Files modified:**
  - `app/Models/Profile.php` — added `hasEventCreatorEntitlement(): bool` (live-read `whereNull('revoked_at')` + not-expired query against `organizerEntitlements()`; never touches `business_subscriptions` or `hasActiveSubscription()`).
  - `app/Http/Controllers/Admin/ManagedUserController.php` — added `grantEventCreatorEntitlement()` / `revokeEventCreatorEntitlement()`, following the existing `grantSubscription()`/`revokeSubscription()` pattern exactly, but **without** the `abort_unless($profile->isBusiness())` guard — both Business and Community profiles are eligible for this capability.
  - `routes/web.php` — `POST /admin/users/{profile}/event-creator/grant` and `/revoke`, inside the existing `auth:admin + maintainer` group.
- **Focused tests:** red (missing `OrganizerEntitlementService` + routes) → **13 tests, 23 assertions, OK** after implementation. Covers: business/community default absence, business/community grant, revoke, expiry, idempotent re-grant (no duplicate row), the critical regression (community without entitlement can still `KolabService::create()` + `publish()` an ordinary `CommunitySeeking` Kolab end-to-end), maintainer grant/revoke for both profile types, non-maintainer 403, and unauthenticated redirect-to-login.
- **Regression:** `--filter="Admin|Kolab|Subscription"` → 323/323 OK. Full suite → **1450/1450 OK** (1437 + 13 new), 0 failures. `vendor/bin/pint --dirty` → pass.
- **Deviations:** none from the plan's interfaces. One test-authoring fix mid-task: the admin login route is named `login`, not `admin.login` (no `admin.` prefix on that specific route) — caught by the red run, not a source bug.
- **Commit:** (recorded after this turn's commit — see chat report)
- **Next task:** Task 4 (Implement Event Draft, Roles, Publish, and Lifecycle) — not started, awaiting approval.

## Task 4: Implement Event Draft, Roles, Publish, and Lifecycle

- **Status:** Completed
- **Files created:**
  - `app/Services/MultiKolabEventService.php` — `createDraft`, `update`, `addRole`, `updateRole`, `removeRole`, `publish`, `confirm`, `complete`, `cancel` (all nine interfaces from the plan).
  - `app/Policies/MultiKolabEventPolicy.php` — `create` (unrestricted — drafting is never gated), `view`, `update`/`cancel`/`confirm`/`complete` (owner-only), `publish` (owner **and** `hasEventCreatorEntitlement()`).
  - `app/Exceptions/EventCreatorEntitlementRequiredException.php`, `app/Exceptions/MultiKolabEventPublishValidationException.php` (carries a `field => messages` array for the controller to map to the contract's §5/§10 error shape in Task 7).
  - `app/Http/Requests/Api/V1/MultiKolab/{CreateMultiKolabEventRequest,UpdateMultiKolabEventRequest,AddMultiKolabRoleRequest,UpdateMultiKolabRoleRequest}.php` — shapes match the frozen contract; not yet wired to a controller (Task 7), so not exercised over HTTP this task — business-rule enforcement (the checklist items below) lives in the service, which **is** fully tested.
  - `tests/Feature/MultiKolab/MultiKolabEventLifecycleTest.php` (18 tests).
- **Files modified:**
  - `app/Models/MultiKolabEvent.php` — `statusEvents()` now `orderBy('id')`. UUIDv7 primary keys (`HasUuids::newUniqueId()` uses `Str::uuid7()`) are time-ordered, so this gives a reliable chronological read of the audit trail without depending on `created_at` second-level precision, which can collide across three same-test transitions.
  - `app/Providers/AppServiceProvider.php` — registered `Gate::policy(MultiKolabEvent::class, MultiKolabEventPolicy::class)`.
  - `app/Http/Requests/Api/V1/StoreReportRequest.php` — added `multi_kolab_event`, `multi_kolab_role`, `multi_kolab_role_application` to the `target_type` whitelist, per the founder's binding Task 4/5/7 moderation requirement (contract §12). `ReportController`/`ModerationService` are already generic over `target_type`, so this one-line whitelist change is sufficient to make events and roles reportable through the **existing** endpoint — no new controller/service code needed. Application-level report visibility restriction (organizer-only) is deferred to Task 7, where the resource that exposes an application's `pitch` to its organizer is actually built.
- **Interpretation calls made (documented, not in the plan's literal text):**
  - **"Venue-needed consistency"** (plan's exact phrase, not otherwise defined): implemented as — if `venue_needed = true`, `city` must be set, since a venue-seeking event needs to tell venue-role applicants where they'd be partnering. Tested by `test_publish_requires_venue_needed_consistency`.
  - **Terminal-state edit lock**: extended beyond the plan's literal "Cancelled cannot return to Recruiting" to also block *any* mutation (`update`/`addRole`/`updateRole`/`removeRole`) once an event is `cancelled` or `completed`, consistent with "cancelled events remain recorded" (Global Constraints) reading as "immutable," not just "non-reactivatable." Low-risk, additive; flagging in case product intent differs.
- **Moderation (per founder's decision, applied — not re-litigated this task):** no proactive filter added anywhere in the service; `test_free_text_fields_accept_arbitrary_content_with_no_proactive_filter` locks this in as a named regression test, and `test_multi_kolab_event_and_role_are_reportable_via_the_existing_report_endpoint` proves the reactive path works end-to-end over the real `/api/v1/reports` HTTP endpoint.
- **Focused tests:** red (missing `MultiKolabEventService`) → **17 tests / 14 errors + 3 failures** (expected TDD-red). After implementation: **18 tests** (added the reportability test after implementing the whitelist change), **28 tests including `ModerationTest.php` run together, 70 assertions — OK.**
- **Checklist coverage:** owner-only editing ✅ (policy test), draft creation without entitlement ✅, publish requires entitlement ✅ (both Business and Community entitled paths tested equally), HTTPS RSVP ✅, venue-needed consistency ✅, minimum one role ✅, required fields (description) ✅, moderation ✅ (reactive-only, per decision), transition-table (Cancelled terminal) ✅, one status-event per transition ✅ (publish/confirm/complete each write exactly one; cancel writes one with the reason), role removal blocked with an accepted application ✅, `positions_needed >= 1` enforced ✅.
- **Regression:** `vendor/bin/pint --dirty` → pass. Full suite `php -d memory_limit=2048M vendor/bin/phpunit` → **1468/1468 OK** (1450 baseline + 18 new), 0 failures.
- **Deviations from the frozen contract:** none in shape. The two interpretation calls above are additive business-rule choices the contract didn't pin down, not contradictions of it.
- **Commit:** (recorded after this turn's commit — see chat report)
- **Next task:** Task 5 (Implement Free Role Applications and Policies) — not started, awaiting approval.

## Task 5: Implement Free Role Applications and Policies
- **Status:** Not started

## Task 6: Implement Concurrency-Safe Acceptance and Child Kolab Creation
- **Status:** Not started

## Task 7: Add API Controllers, Resources, Dashboard, and Explore Endpoint
- **Status:** Not started

## Task 8: Add Notifications, Reminders, and Analytics
- **Status:** Not started

## Task 9: Build Flutter Data Layer and Applicant Experience
- **Status:** Not started

## Task 10: Build Flutter Organizer Experience
- **Status:** Not started

## Task 11: Localization, Accessibility, and UGC Safety Verification
- **Status:** Not started

## Task 12: End-to-End Validation and Release Gate
- **Status:** Not started
