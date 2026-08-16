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

- **Status:** Completed
- **Files created:**
  - `app/Services/MultiKolabRoleApplicationService.php` — `apply`, `shortlist`, `decline`, `withdraw` (all four plan interfaces).
  - `app/Policies/MultiKolabRoleApplicationPolicy.php` — `create` (any eligible profile except the event's own creator), `view`, `shortlist`/`decline` (organizer-only), `withdraw` (applicant-only).
  - `app/Exceptions/DuplicateRoleApplicationException.php` — deterministic pre-check for the unique `(role, applicant)` conflict, for the controller (Task 7) to map to HTTP 409 per contract §7. The DB unique constraint (Task 2) remains the concurrency backstop.
  - `app/Http/Requests/Api/V1/MultiKolab/{CreateMultiKolabRoleApplicationRequest,WithdrawMultiKolabRoleApplicationRequest}.php`.
  - `tests/Feature/MultiKolab/MultiKolabRoleApplicationTest.php` (19 tests).
- **Files modified:**
  - `app/Providers/AppServiceProvider.php` — registered `Gate::policy(MultiKolabRoleApplication::class, MultiKolabRoleApplicationPolicy::class)`.
- **Interpretation calls made (flagged, not literal plan text):**
  - Applications are only accepted while the parent event is `recruiting` (not `draft`/`confirmed`/`completed`/`cancelled`) and the role is `open` — the plan implies this ("Free Role Applications" against a published event with open roles) but doesn't spell out the exact status gate; tested explicitly (`test_cannot_apply_to_a_role_on_a_draft_event`, `test_cannot_apply_to_a_filled_role`).
  - Added "cannot apply to your own event" — not explicit in Task 5's checklist, but a direct carry-over of the identical rule already enforced in `ApplicationService::validateCanApply()` for ordinary Kolabs; kept consistent rather than silently allowing a hole.
- **Checklist coverage:** Business/Community/Either eligibility ✅ (all six combinations tested, including both directions of each restricted type). Applying never checks `hasActiveSubscription()` or `hasEventCreatorEntitlement()` ✅ — explicit test asserts both are `false` on the applicant and the apply still succeeds. Unique `(role, applicant)` ✅ — both the deterministic service-level exception and the DB-level `QueryException` backstop are tested. Owner-only shortlist/decline ✅, applicant-only withdrawal ✅ (both via `Profile::can()` against the registered policy, matching how `ApplicationPolicy` is tested elsewhere in the codebase). Pitch required ✅. Post-acceptance withdrawal reason required (pending/shortlisted withdrawal does not require one) ✅ — both branches tested. Transactional withdrawal decrements `positions_filled` and reopens the role to `open` ✅, with an explicit floor test (`test_positions_filled_never_drops_below_zero`) proving it never goes negative even from an already-inconsistent starting state.
- **Moderation:** no proactive filter added (same founder decision as Task 4) — `pitch`/`availability`/`withdrawal_reason` are stored as free text with no filtering, consistent with `test_free_text_fields_accept_arbitrary_content_with_no_proactive_filter` established in Task 4.
- **Focused tests:** red (missing `MultiKolabRoleApplicationService`) → 19 tests, 11 errors + 7 failures (expected). Green: **19/19, 31 assertions.** Run together with Tasks 3/4's Feature/MultiKolab tests: **50/50, 87 assertions — OK.**
- **Regression:** `vendor/bin/pint --dirty` → auto-fixed one unused import in the new test file (accepted, re-verified green after). Full suite `php -d memory_limit=2048M vendor/bin/phpunit` → **1487/1487 OK** (1468 baseline + 19 new), 0 failures.
- **Deviations from the frozen contract:** none in shape. The two interpretation calls above are additive.
- **Commit:** (recorded after this turn's commit — see chat report)
- **Next task:** Task 6 (Concurrency-Safe Acceptance and Child Kolab Creation) — **blocked on the outstanding PostgreSQL prerequisite** (see Task 2 entry); not started, awaiting both approval and a resolved Postgres environment.

## Task 6: Implement Concurrency-Safe Acceptance and Child Kolab Creation

- **Status:** Completed
- **PostgreSQL environment used:** the **shared Laravel Cloud `development` database** (managed `*.laravel.cloud` host — see `.env.pgsql.task6`, gitignored, never committed; PostgreSQL 18.4), NOT an isolated disposable instance. This is a deliberate, explicit deviation from the original Task 6 instructions ("do not use it for Task 6 migrations or concurrency tests... even if a shared development PostgreSQL database is available"). Docker and `psql` were confirmed unavailable on this machine (both `command not found`), so no isolated local/container Postgres could be provisioned. The user was shown this exact contradiction (their own earlier rule vs. this database's real identity) and explicitly confirmed proceeding on the shared dev DB, with these mitigations:
  - Migrations applied via `php artisan migrate` only (never `migrate:fresh`/`db:wipe`/rollback/truncate), after a read-only `migrate:status` confirmed **exactly** the 6 expected Multi-Kolab migrations were pending and nothing else — reported to the user for explicit approval before running.
  - The concurrency test creates uniquely tagged, disposable records (profile emails and event/role titles embedding a per-run ID) and deletes only those tagged records afterward, verified by a zero-remaining-records check baked into the harness itself.
  - No existing development data was read, modified, or deleted at any point.
  - Credentials lived only in the gitignored `.env.pgsql.task6` (added to `.gitignore` this task) and were never printed, logged, or committed.
- **Files modified:**
  - `app/Services/MultiKolabRoleApplicationService.php` — added `accept(MultiKolabRoleApplication $application, Profile $actor): Kolab`.
  - `.gitignore` — added `.env.pgsql.task6`.
- **Files created:**
  - `app/Exceptions/RoleCapacityExceededException.php` — maps to HTTP 409 in Task 7.
  - `tests/Feature/MultiKolab/MultiKolabAcceptanceTest.php` (9 tests, SQLite — functional correctness, not concurrency proof).
- **Canonical creation path (re-verified from Task 1, unchanged):** `accept()` does **not** call `ApplicationService::accept()` — that path's `validateCanAccept()` re-imposes the business-subscription paywall, which must never gate a free Multi-Kolab role application. Instead it mirrors `ApplicationService::createCollaboration()`'s field mapping directly via `Kolab::create()` / `Application::create()` / `Collaboration::create()`, inside its own `DB::transaction()` with `lockForUpdate()` on both the `MultiKolabRoleApplication` and `MultiKolabRole` rows (re-checking ownership, status, and capacity after the lock is acquired) — satisfying the 13-step transactional contract in the turn instructions.
- **Child Kolab field choices (not specified by the plan, documented here):** `intent_type` = `VenuePromotion` if the organizer is a business, else `CommunitySeeking` (mirrors the existing creator-type convention in `ROLES-BACKEND-DB-MAP.md §2`); `title` = `"{event title} — {role title}"`; `description` falls back through `role.need` → `role.details` → `event.description` → `''`; `preferred_city` = `event.city` (required NOT NULL column) or `''` if the event never set one; `status` = `Published` with `published_at = now()` (matches the accepted-Kolab shape in contract §8).
- **Idempotency:** a fast-path check before acquiring any lock returns the existing linked Kolab immediately if the application is already `accepted` with a `kolab_id`; the same check re-runs *inside* the lock in case of a race between the fast-path read and lock acquisition. Verified by `test_repeated_acceptance_returns_the_same_kolab_without_incrementing_capacity`.
- **Focused tests (SQLite):** red (missing `accept()` method) → 9 tests, 5 errors + 3 failures (expected; 1 unrelated regression test passed standalone as it doesn't touch the new method). Green: **9/9, 33 assertions.** Covers: happy path (exactly one Kolab/Application/Collaboration), never checks subscription/entitlement on the applicant, idempotent retry, owner-only, capacity-exceeded (both a pre-filled role and a genuine two-application sequential race), role marked `Filled` only once true capacity is reached (tested with `positions_needed = 2`), rejection of a non-pending/shortlisted application, and an explicit regression proving ordinary Kolab acceptance via `ApplicationService::accept()` is completely unaffected.
- **Genuine PostgreSQL concurrency test:** a standalone harness (not PHPUnit — PHPUnit can't easily spawn independent OS processes) at `/private/tmp/.../scratchpad/task6_concurrency/{run_concurrency_test.php,child_accept.php}` (not committed — throwaway tooling, kept out of the repo). Each of 30 iterations: creates a fresh one-position role with two pending applications tagged with a unique run ID, spawns **two independent `php` OS processes** (via `proc_open`, each with its own DB connection, released from a shared busy-wait barrier file to start as close to simultaneously as possible), and asserts exactly one `success` + one `RoleCapacityExceededException`, `positions_filled === 1`, role `status === Filled`, and exactly one `Kolab` row. **Result: 30/30 passed, 0 failures.** Cleanup deleted exactly what was created (30 Collaborations, 30 canonical Applications, 60 role applications, 30 Kolabs, 30 roles, 30 events, 90 profiles) and verified 0 tagged records remained. One real bug was caught and fixed during this process: the first cleanup attempt ordered `Kolab` deletion before `MultiKolabRoleApplication` deletion, which failed with a Postgres `RESTRICT` violation (`multi_kolab_role_applications.kolab_id` is also `restrictOnDelete`, not just `kolabs.multi_kolab_event_id/role_id`) — left two stray tagged iterations from the first (n=2) dry run, which were then cleaned up manually with the corrected order before the real n=30 run. Final state confirmed clean via `migrate:status` (still exactly the 6 Multi-Kolab migrations at batch `[60]`, nothing else changed) and a targeted zero-remaining-tagged-records check.
- **Regression:** `vendor/bin/pint --dirty` → pass. Full SQLite suite → **1496/1496 OK** (1487 baseline + 9 new), 0 failures.
- **Deviations from the frozen contract:** none in shape (child Kolab/Application/Collaboration linkage matches contract §8 exactly). The PostgreSQL-environment deviation is the significant one and is documented above and reported to the user in-session before being confirmed.
- **Commit:** `feat: create child kolab on role acceptance` (hash recorded after this turn's commit — see chat report).
- **Next task:** Task 7 (API Controllers, Resources, Dashboard, Explore Endpoint) — not started, awaiting approval. Per instruction, not begun this turn.

## Task 7: Add API Controllers, Resources, Dashboard, and Explore Endpoint

- **Status:** Completed
- **Environment:** SQLite/testing only, per this turn's constraints. No further writes against the shared Laravel Cloud dev DB.
- **Files created:**
  - `app/Http/Controllers/Api/V1/MultiKolabEventController.php` — `entitlement`, `index` (Explore), `myEvents`, `store`, `show`, `update`, `storeRole`, `updateRole`, `destroyRole`, `publish`, `confirm`, `complete`, `cancel`, `dashboard`.
  - `app/Http/Controllers/Api/V1/MultiKolabRoleApplicationController.php` — `forRole` (organizer review list), `store` (apply), `shortlist`, `decline`, `withdraw`, `accept`.
  - `app/Http/Controllers/Api/V1/Concerns/MapsMultiKolabExceptions.php` — shared exception→HTTP mapping trait (403/409/422 per contract §10), keeping both controllers thin (delegate to services, translate outcomes only).
  - `app/Http/Requests/Api/V1/MultiKolab/CancelMultiKolabEventRequest.php`.
  - `app/Http/Resources/Api/V1/MultiKolab/{MultiKolabCreatorSummaryResource,MultiKolabRoleResource,MultiKolabRoleApplicationResource,MultiKolabEventSummaryResource,MultiKolabEventResource}.php` — every response is a `JsonResource`, never a raw model; contract-exact field lists only.
  - `tests/Feature/MultiKolab/MultiKolabApiTest.php` (29 tests).
- **Files modified:**
  - `routes/api.php` — 20 new authenticated routes under `/api/v1/multi-kolab-events`, `/api/v1/multi-kolab-roles`, `/api/v1/multi-kolab-role-applications`, `/api/v1/me/organizer-entitlement` (full list below).
  - `app/Policies/MultiKolabRoleApplicationPolicy.php` — added `accept()` (didn't exist when the policy was first written in Task 5, before `accept()` existed on the service) and `viewAnyForRole()` (for the organizer's application-review list).
  - `app/Services/MultiKolabEventService.php` — **two real bugs fixed**, both caught by the failing-test-first API tests, neither visible in Task 4's own unit tests because those always passed explicit values or used the factory (which sets every column):
    1. `createDraft()` didn't set an in-memory default for `eligible_account_type` when the caller omits it — the DB column default (`'either'`) applies at the SQL level, but the in-memory Eloquent model right after `create()` has no value for that attribute, so the enum cast resolved to `null` and the resource crashed on `->value`. Fixed by setting the default explicitly in the `create()` call (mirrors the DB default, doesn't change behavior — just makes the in-memory model consistent with what's actually in the row).
    2. Same bug, `addRole()`: `status`/`positions_needed`/`positions_filled`/`required` weren't defaulted in-memory when omitted from the request.
- **Endpoints added (20, all `auth:sanctum`):**
  ```
  GET    /api/v1/me/organizer-entitlement
  GET    /api/v1/multi-kolab-events                (Explore — status=recruiting default; city/category/eligible_account_type filters)
  GET    /api/v1/multi-kolab-events/me              (owner's own, any status)
  POST   /api/v1/multi-kolab-events                 (createDraft)
  GET    /api/v1/multi-kolab-events/{event}         (detail + viewer_application)
  PATCH  /api/v1/multi-kolab-events/{event}
  POST   /api/v1/multi-kolab-events/{event}/roles
  POST   /api/v1/multi-kolab-events/{event}/publish
  POST   /api/v1/multi-kolab-events/{event}/confirm
  POST   /api/v1/multi-kolab-events/{event}/complete
  POST   /api/v1/multi-kolab-events/{event}/cancel
  GET    /api/v1/multi-kolab-events/{event}/dashboard
  PATCH  /api/v1/multi-kolab-roles/{role}
  DELETE /api/v1/multi-kolab-roles/{role}
  GET    /api/v1/multi-kolab-roles/{role}/applications   (organizer review list — not in the original frozen contract, added this task; see below)
  POST   /api/v1/multi-kolab-roles/{role}/applications   (apply)
  POST   /api/v1/multi-kolab-role-applications/{application}/shortlist
  POST   /api/v1/multi-kolab-role-applications/{application}/decline
  POST   /api/v1/multi-kolab-role-applications/{application}/withdraw
  POST   /api/v1/multi-kolab-role-applications/{application}/accept
  ```
- **One documented, minimal contract addition:** `GET /api/v1/multi-kolab-roles/{role}/applications` was not in the Task 1 frozen contract. Without it, the organizer dashboard (`/dashboard`) can show per-role application *counts* but there is no way to enumerate the actual applications to shortlist/decline/accept — a gap that would block the "applicant review" screen (Task 10) entirely. Added as an organizer-only paginated list using the existing `MultiKolabRoleApplicationResource` shape (no new resource, no schema change). Mirrors the existing `ApplicationController::forOpportunity` convention exactly. **Not yet reflected in the contract doc** — flagged here rather than silently expanding the frozen spec; will add to the contract doc in this same commit's diff review, or on request.
- **Authorization:** every mutating/ownership-sensitive endpoint checks a registered Policy (`MultiKolabEventPolicy`, `MultiKolabRoleApplicationPolicy`) via `$profile->cannot(...)`/`$profile->can(...)` — no ad hoc inline ownership logic duplicated across controllers beyond the two cases where a policy doesn't naturally apply (role mutation authorizes against `role->event`, and the `dashboard`/`storeRole`/`updateRole`/`destroyRole` all reuse the `MultiKolabEventPolicy::update` check since a role is entirely owned by its parent event).
- **N+1 prevention:** Explore/`me` listings use `withCount` (open/filled/total role counts) instead of loading every role per event; `creatorProfile.businessProfile`/`communityProfile` always eager-loaded before serialization; the dashboard's per-role `application_counts` come from **one** grouped `selectRaw`/`groupBy` query, not a query per role; the role-applications list never eager-loads the applicant's full profile since the frozen resource shape doesn't need it.
- **Stable resources, never raw models:** every JSON response wraps a `JsonResource` (or, for the two genuinely composite/non-model shapes — the entitlement status and the accept() 3-part envelope — a hand-built array with only the exact contract fields, never `$model->toArray()`).
- **Focused tests:** 29 tests covering every route, auth (401), ownership (403 `not_owner`), validation (422), the frozen response shapes, and every documented error code (`event_creator_required`, `role_has_accepted_application`, `invalid_transition`, `duplicate_application`, `role_capacity_exceeded`). Red→green caught 4 real bugs during implementation (the two in-memory-default bugs above, a `pluck()`-on-enum-key crash in the dashboard aggregation, and one test-authoring mistake of my own — the event factory always sets `description`, so the "missing description" publish-validation test needed an explicit override). All fixed; final run: **29/29, 127 assertions.**
- **Regression:** Full Feature/MultiKolab + Unit/MultiKolab suite → **112/112 OK** (83 baseline + 29 new). `vendor/bin/pint --dirty` → auto-fixed minor style in two files (brace position, import order), re-verified green after. Existing `GET /api/v1/kolabs` Explore endpoint re-tested directly (`test_existing_kolab_explore_endpoint_is_unaffected`) — unaffected.
- **Full backend suite:** **1525/1525 OK** (1496 baseline + 29 new), 0 failures.
- **Deviations from the frozen contract:** the one documented addition above (role-applications list endpoint). Everything else matches contract shapes/routes/error codes exactly.
- **Commit:** (recorded after this turn's commit — see chat report)
- **Next task:** Task 8 (Notifications, Reminders, Analytics) — explicitly **not started**, per this turn's instruction to keep Task 7 separate from notification/subscription work. Awaiting approval.

## Task 8: Add Notifications, Reminders, and Analytics

- **Status:** Completed
- **Environment:** SQLite/testing only. No Postgres, no writes to the shared dev DB.
- **Files created:** `tests/Feature/MultiKolab/MultiKolabNotificationTest.php` (16 tests).
- **Files modified:**
  - `app/Enums/NotificationType.php` — 8 new cases, all prefixed `MultiKolab*`, none reusing/overloading the attendee `EventCancelled` case (`MultiKolabApplicationReceived`, `MultiKolabApplicantAccepted`, `MultiKolabApplicantDeclined`, `MultiKolabPartnerWithdrew`, `MultiKolabRoleFilled`, `MultiKolabEventConfirmed`, `MultiKolabEventCancelled`, `MultiKolabEventDraftIncomplete`).
  - `app/Services/NotificationService.php` — 7 new `notifyMultiKolab*()` methods, following the existing `createLocalizedNotification()` pattern exactly (localized title/body, actor, target_id/target_type). `notifyMultiKolabEventConfirmed`/`Cancelled` fan out to every applicant with an **accepted** application on the event (via a private `acceptedApplicants()` helper — one query, no N+1).
  - `app/Services/NotificationReminderService.php` — `syncMultiKolabEventDraftReminder()`/`cancelMultiKolabEventDraftReminder()`, a new `MULTI_KOLAB_EVENT_DRAFT_CADENCE_HOURS = [24, 72]` constant, and the matching `refreshReminderState()`/`cadenceHoursFor()`/`buildPayload()` cases. Reused the existing generic reminder engine (`syncReminder`/`sendDueReminders`/`advanceReminder`) as-is — no new scheduling mechanism.
  - `app/Services/MultiKolabEventService.php` — constructor now takes `NotificationService`, `NotificationReminderService`, `PostHogService`; wired into `createDraft`/`update`/`addRole`/`publish`/`confirm`/`cancel`.
  - `app/Services/MultiKolabRoleApplicationService.php` — constructor now takes `NotificationService`, `PostHogService`; wired into `apply`/`shortlist`/`decline`/`accept`/`withdraw`.
  - `app/Observers/CollaborationObserver.php` — constructor now takes `PostHogService`; on a `Collaboration` transitioning to `completed`, fires `child_kolab_completed` **only** if its Kolab is a Multi-Kolab child (`kolabs.multi_kolab_event_id !== null`) — an ordinary Kolab's collaboration completing emits nothing new, verified by an explicit regression test.
  - `lang/{en,es,ca}/notifications.php` — full `multi_kolab.*` translation keys (application received/accepted/declined/withdrawn, role filled, event confirmed/cancelled) in all three locales, matching the existing `notifications.application.*` structure exactly — no English-only shortcuts.
- **"Exactly one notification, including on retry" — how it's guaranteed, not just tested:** every notify call sits on the actual state-changing branch of an already-idempotent method (`accept()`'s two early-return points for an already-accepted application skip the entire transactional closure — including the notify call — on retry; `withdraw()` only notifies inside the `$wasAccepted` branch; `shortlist`/`decline` throw before reaching their notify call if the status is already terminal). `Notification::create()` itself has no dedup logic — correctness lives entirely in the callers only ever reaching the notify call once. Verified directly: `test_accepting_notifies_the_applicant_exactly_once_even_on_retry` calls `accept()` twice and asserts exactly one notification + exactly one `applicant_accepted` PostHog event.
- **Reminder cap — same mechanism, no new capping logic:** the existing `advanceReminder()` already naturally stops once `next_sequence >= count($cadenceHours)` (sets `scheduled_for = null`, so `sendDueReminders()`'s `whereNotNull('scheduled_for')` filter excludes it forever). Passing a 2-element cadence array (`[24, 72]`) was sufficient — no new logic needed for "never a third reminder." "Never after publish/cancel" is enforced by `refreshMultiKolabEventDraftReminder()` re-checking `status === Draft` on every due-check and cancelling outright if not, mirroring `refreshKolabDraftReminder()` exactly.
- **PostHog — full minimal set emitted:** `draft_started` (createDraft), `role_added` (addRole), `event_published` (publish), `role_application_submitted` (apply), `applicant_shortlisted` (shortlist), `applicant_accepted` (accept, real acceptance only), `role_filled` (accept, only when capacity is reached), `event_confirmed` (confirm), `partner_withdrew` (withdraw, post-acceptance only), `event_cancelled` (cancel), `child_kolab_completed` (CollaborationObserver, scoped to Multi-Kolab children only) — all 11 names from the plan's list, verified individually by test.
- **Focused tests:** 16/16 green on first full implementation pass (no red→green cycle needed here — the existing reminder/notification/PostHog infrastructure generalized cleanly to the new type without surprises), 35 assertions. Covers: every notification transition + exactly-once-on-retry, role-filled threshold timing (not fired until true capacity), partner-withdrawal being post-acceptance-only, event confirm/cancel fan-out (including the empty-recipients edge case for a cancelled draft), draft/role/publish PostHog events, `child_kolab_completed` firing for a Multi-Kolab child and **not** firing for an ordinary Kolab (explicit regression), and the full reminder cadence lifecycle (20h → nothing, 25h → first, 72h → second, 172h → still only two) plus both "never after publish" and "never after cancel" cases.
- **Regression:** `vendor/bin/pint --dirty` → auto-fixed formatting in two files (import order, brace position), re-verified green. Full Feature/MultiKolab + Unit/MultiKolab suite → **128/128 OK** (112 baseline + 16 new). `--filter="Notification|Collaboration"` (broader existing regression net) → **238/238 OK**.
- **Full backend suite:** **1541/1541 OK** (1525 baseline + 16 new), 0 failures.
- **Deviations from the frozen contract:** none — this task doesn't touch the API contract (no new endpoints; notifications/reminders/analytics are internal side-effects of the existing service methods).
- **Commit:** (recorded after this turn's commit — see chat report)
- **Next task:** Task 9 (Flutter Data Layer and Applicant Experience) — not started. Backend Tasks 1–8 are now complete.

## Task 9: Build Flutter Data Layer and Applicant Experience
- **Status:** Not started

## Task 10: Build Flutter Organizer Experience
- **Status:** Not started

## Task 11: Localization, Accessibility, and UGC Safety Verification
- **Status:** Not started

## Task 12: End-to-End Validation and Release Gate
- **Status:** Not started

---

## Hardening checkpoint (post-Task-8, pre-Task-9): side-effect isolation + stable error codes

- **Status:** Completed
- **Trigger:** a read-only backend review of the full `3f039b5...feat/multi-kolab-event-mvp` diff (Tasks 1–8) found one P1 and confirmed two P2s; this checkpoint fixes the P1 and the two named P2s only, per explicit scope.
- **P1 verified:** `MultiKolabRoleApplicationService::accept()` and the accepted-withdrawal branch of `::withdraw()` called `NotificationService`/`PostHogService` **inside** their `DB::transaction()` closures — a delivery failure (e.g. `SendPushNotification::dispatch()` throwing under a sync queue) would have rolled back the just-committed child Kolab, canonical Application, Collaboration, and role capacity accounting. Confirmed by direct code read against `ApplicationService::accept()`'s existing post-commit `try/catch` precedent, then reproduced with a real failing test before any fix.
- **Files created:**
  - `app/Services/Concerns/RunsSideEffects.php` — shared `runSideEffect(\Closure)` trait: try/catch + `report($e)`, one call per side effect (never one wrapping several) so one failure never blocks the others. Explicitly documented as best-effort (no outbox/retry) — a deliberate scope boundary, not an oversight, consistent with `ApplicationService`'s existing trade-off.
  - `app/Exceptions/MultiKolabApplicationRejectedException.php` — typed exception carrying a stable `code()` + `field()`.
  - `tests/Feature/MultiKolab/MultiKolabSideEffectResilienceTest.php` (19 tests).
- **Files modified:**
  - `app/Services/MultiKolabRoleApplicationService.php` — `accept()` and `withdraw()` restructured so `DB::transaction()` returns a plain array (`newly_accepted`/`was_accepted` flag + the entities needed for side effects) instead of directly returning the domain result with side effects inline; all notify/analytics calls moved to after the transaction returns, each independently wrapped via `runSideEffect()`. `apply()`/`shortlist()`/`decline()` also wrapped (P2 #1). `validateCanApply()` now throws `MultiKolabApplicationRejectedException` with stable codes (P2 #2) instead of bare `InvalidArgumentException` for the three reachable rejection paths.
  - `app/Services/MultiKolabEventService.php` — `confirm()`/`cancel()` side effects wrapped via `runSideEffect()` (P2 #1). `publish()`/`createDraft()`/`addRole()` were **not** touched — out of the explicit scope for this checkpoint (not among the P2 items named), and flagged again below as a remaining, intentionally-deferred item.
  - `app/Http/Controllers/Api/V1/Concerns/MapsMultiKolabExceptions.php` — new `applicationRejectedResponse()` mapping `MultiKolabApplicationRejectedException` → `422 {errors: {field: [code]}}`.
  - `app/Http/Controllers/Api/V1/MultiKolabRoleApplicationController.php` — `store()` now catches `MultiKolabApplicationRejectedException` before the generic `InvalidArgumentException` fallback (catch-order matters: the new exception extends `InvalidArgumentException`).
  - `docs/superpowers/specs/2026-08-12-multi-kolab-event-api-contract.md` §10 — added the four new stable codes (`role_ineligible`, `event_not_recruiting`, `role_not_open`, `cannot_apply_to_own_event`), documented as genuinely missing (not a deviation from something previously specified).
  - `tests/Feature/MultiKolab/MultiKolabApiTest.php` — 3 new API-level tests asserting exact status/envelope/code for the three genuinely-reachable-via-API cases.
- **How post-commit side effects are gated against retries:** `accept()`'s transaction returns `newly_accepted: false` on both idempotency short-circuits (the outer pre-lock check and the inner post-lock recheck) — side effects only run when `newly_accepted === true`, so a retry of an already-accepted application (or the losing side of a concurrent accept race) never re-fires a notification or analytics event. `withdraw()` is symmetric via `was_accepted`. A genuine second `withdraw()` call on an already-`Withdrawn` application is rejected by the existing status guard before ever reaching the transaction — unchanged behavior, still correct.
- **Behavior when a notification fails:** the domain operation completes and returns normally to the caller (no exception surfaces to the controller/HTTP layer); the failure is captured via `report($e)` (verified with Laravel's `Exceptions::fake()`/`assertReported()` test helper); the specific failing notification is dropped (best-effort, no retry) but every other independent side effect for that same operation still fires (verified explicitly: a `notifyMultiKolabRoleFilled` failure does not suppress `notifyMultiKolabApplicantAccepted`).
- **Behavior when PostHog fails:** identical — reported, swallowed, domain result unaffected, other side effects unaffected.
- **Stable error codes added:** `role_ineligible`, `event_not_recruiting`, `role_not_open` (all genuinely reachable via `POST /multi-kolab-roles/{role}/applications` — previously generic `422 {errors: {base: [...]}}`), plus `cannot_apply_to_own_event` (defense-in-depth; the `create` policy already blocks this case with `403 not_owner` before the service runs, so this code is currently unreachable via the real API but exists for direct service callers/future paths).
- **Focused tests:** red (17 of 19 failed with the underlying `RuntimeException` propagating uncaught — proving the P1/P2 were real, not theoretical; the 2 that passed red were pre-existing-correct withdraw-retry-guard tests unaffected by the refactor) → green: **19/19, 52 assertions**, plus **3/3** new API stable-code tests.
- **Regression:** Full Feature/MultiKolab + Unit/MultiKolab suite → **150/150 OK** (128 Task-8 baseline + 19 resilience + 3 API). `--filter="Notification|Collaboration"` → **246/246 OK**. `vendor/bin/pint --dirty` → auto-fixed one unused import, re-verified green.
- **Full backend suite:** **1563/1563 OK** (1541 baseline + 22 new), 0 failures.
- **PostgreSQL concurrency re-verification:** **not required and not re-run.** The refactor changed only where side-effect calls occur (moved out of the transaction closures) and the closure's return shape; the `lockForUpdate()` order and every piece of critical domain work inside the transaction are byte-for-byte unchanged (verified by diff) — no transaction/locking behavior was touched, so Task 6's existing 30/30 real-Postgres concurrency result still stands. No writes were made to the shared Laravel Cloud dev DB this turn.
- **Diff review confirmed:** no notification/analytics call remains inside `accept()`/`withdraw()`'s transactions (verified via a scripted scan of the transaction closure bodies); zero changes to `KolabService.php`/`ApplicationService.php`/`CollaborationService.php`/`KolabController.php`/`ApplicationController.php` (ordinary Kolab/Application behavior untouched); no `.env`/credential file tracked or modified; diff is scoped to exactly the 4 modified + 3 created files listed above plus this progress doc, with `docs/audit-output/` and `docs/product/` left untouched/untracked.
- **P2 items intentionally deferred (not addressed this turn):** `positions_needed`/`positions_filled` DB-level CHECK constraint; organizer visibility into `withdrawal_reason`; the stale local `master` git ref; the `restrictOnDelete()` FK cycle between `kolabs` and `multi_kolab_role_applications`; and — newly identified during this checkpoint — `createDraft()`/`addRole()`/`publish()` in `MultiKolabEventService` still call `postHog->capture()` unwrapped (not inside a transaction, so no rollback risk, but the same "uncaught exception on an already-successful write" class of issue as the P2 fixed here for `apply`/`shortlist`/`decline`/`confirm`/`cancel` — out of this checkpoint's explicit scope, flagged for a future pass).
- **Commit:** (recorded after this turn's commit — see chat report)
- **Push:** `origin/feat/multi-kolab-event-mvp`, normal push (no force).
