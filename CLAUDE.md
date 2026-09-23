# CLAUDE.md

Guidance for Claude Code in this repository. Long examples live in [docs/CLAUDE-REFERENCE.md](docs/CLAUDE-REFERENCE.md) (not auto-loaded).

---

## CONTRIBUTOR ROUTING — Volkan → Reverb real-time ticket

Trigger: the user is *Volkan* (introduces himself, or git user / commit author is volkanoluc@gmail.com), OR the user asks about *Reverb, WebSockets, real-time chat, broadcasting, or live messages*.
- BEFORE anything else, read and surface [docs/tickets/2026-06-05-reverb-realtime-chat-VOLKAN.md](docs/tickets/2026-06-05-reverb-realtime-chat-VOLKAN.md). It is his assigned task (turn on real-time chat: ops daemons + Flutter Echo client).
- Lead with a short summary of where the ticket stands and what is next. Then help with it.

---

## MUST FOLLOW — Pull requests & branching (every change that ships)

`master` is protected: no direct pushes. Only `olucvolkan` can merge. All work lands through a PR.
- Never commit or push to `master`. Branch `feat/…`, `fix/…` or `chore/…` and open a PR into `master`.
- Every PR MUST use [`.github/pull_request_template.md`](.github/pull_request_template.md). Fill every mandatory section before review. REQUIRED sections are non-negotiable. If one does not apply, write `N/A` + a one-line reason. Never leave it blank. Never delete template headings.
- Mobile impact is mandatory. If the change touches the API contract (JSON payload shape, new/renamed endpoints, enum values, auth, error codes), say so in *Mobile impact (kolabing-app)*, describe the contract change, and link the `kolabing-app` ticket/PR. "No mobile changes required" must be an explicit choice, not an omission.
- Link the tracking item with a closing keyword (`Closes #123`). Order: Projects item → branch → PR.
- Before requesting review: `php artisan test` green and `vendor/bin/pint` clean. Paste the counts into *Testing*.
- Keep the docs-sync rules below satisfied (BACKLOG, ROLES docs, BACKEND-SCHEMA). Tick them in the template's *Docs & rules updated* section.

### Project tracking (GitHub Projects)
- Every feature, fix or chore is an item on the repo's GitHub Project board before/while it is built. Its PR links back to the item.
- The board is the live view of in-flight work. `BACKLOG.md` stays the narrative source of truth and must stay in sync with the board.
- Open items with [`.github/ISSUE_TEMPLATE/ticket.yml`](.github/ISSUE_TEMPLATE/ticket.yml) (blank issues are disabled). Fill Summary, Context, Acceptance criteria, Work Type, Priority, Area, **Mobile impact**, Definition of done.
- Add the issue to the **Kolabing Engineering** board and mirror those values into the board fields.

---

## MUST READ — Backlog (every session, before anything else)

At session START, read [BACKLOG.md](BACKLOG.md) and list its contents to the user (New Features, Incomplete Features, Fixes). It is the single source of truth for outstanding work. You MUST keep it in sync per its "Maintenance rules":
- New Feature you begin → move to *Incomplete Features*.
- Incomplete Feature verified working end-to-end → remove it.
- Bug you detect → add to *Fixes* immediately. Once the fix is *confirmed* (tested, not just written), strike it through with the date, then remove later.
- Update the `Last updated:` date on every edit.

---

## MUST READ — Backend schema (before any data/model/API/DB change)

Read [docs/BACKEND-SCHEMA.md](docs/BACKEND-SCHEMA.md) before changing data, models, API payloads, JSON keys or the database. It documents the *real production Postgres schema* (Laravel backend, db main). Hard rules:
- *Never invent columns, tables or enum values.* Not in that doc (or the live schema) = does not exist. Verify before relying on a field.
- *Never hardcode* IDs, emails, city/category names or sample records in app code; fetch from the API.
- Identity lives in profiles (+ business_profiles / community_profiles), NOT the users table.
- Lifecycle: collab_opportunities → applications → collaborations (+ reviews / feedback). GET /collaborations is viewer-scoped.
- The business paywall is backend-enforced. Never bypass it.

---

## MUST READ — Roles & Permissions (before planning OR executing changes)

Scope: user roles, permissions, the paywall, the Explore feed, profiles, onboarding, the create/apply flows, the admin operator surfaces, the attendee gamification track, the community-members & tiers surface, subscription state. Before planning or coding in scope, read BOTH:
1. [`docs/ROLES-AND-PERMISSIONS.md`](docs/ROLES-AND-PERMISSIONS.md) — authoritative *what* (Business, Community, attendee §7 first-pass, community-members & tiers §8).
2. [`docs/ROLES-BACKEND-DB-MAP.md`](docs/ROLES-BACKEND-DB-MAP.md) — authoritative *where* (rule → backend code + DB tables/columns, every known role-handling mistake; community-members & tiers backend map §12).

They cover: Business / Community / Attendee taxonomy; the two-action paywall; the subscription-lapse re-gate; **maintainer-granted subscriptions** (`source = maintainer`); **admin operator routes** under `/admin/*`; lifecycle-observability timestamps; **community members + customisable tiers** (NF-6: `communities` / `community_tiers` / `community_members`; the one-free-community cap is NOT the paywall); the open mistakes-to-fix checklist.

**Update rule (non-optional, every role-affecting PR):** when you change any surface above, update both docs in the same change: adjust the sections, bump *Last updated* at the top of each, tick/add mistakes-to-fix items. If the change adds or removes a role surface, also update this `CLAUDE.md` block and mirror everything into `kolabing-app`'s copy of the two files.

Most regressions come from applying one role's rules to the other (e.g. paywalling a community, which must never happen). Do not change role behaviour without checking both docs. If a fix seems to contradict them, ask before proceeding.

## Project Overview

Kolabing is a B2B/B2C collaboration platform connecting businesses with community organizers in Spain. This repo is the **Laravel 12 backend API** for the Mobile MVP. The mobile app is a separate repo.

Key constraints:
- Google OAuth only (no password auth).
- Monthly Stripe subscription only (no credit system).
- No database triggers. All logic in the Laravel service layer.
- Pure PostgreSQL (no Supabase dependency).
- Backend API only. Landing page uses Blade; mobile app is separate.

Stack: Laravel 12, PHP 8.3+, PostgreSQL 15+, Laravel Sanctum + Google OAuth, Stripe (monthly subscriptions), RESTful JSON API versioned under `/api/v1/`.

## Common Commands

```bash
php artisan serve | migrate | migrate:fresh --seed | db:seed
php artisan test [--filter=TestName | tests/Feature/Auth]
php artisan make:model ModelName -mfs          # model + migration + factory + seeder
php artisan make:controller Api/V1/ControllerName --api
php artisan make:request StoreModelRequest | make:resource ModelResource | make:policy ModelPolicy --model=Model
php artisan queue:work | cache:clear | config:clear
```

## Architecture

- **Dual-portal users:** single `profiles` table, `user_type` discriminator (`business` | `community`). Business users create opportunities and need an active subscription to publish. Community users browse and apply, free. 1:1 extension tables: `business_profiles` (details, type, city), `community_profiles` (details, type, featured flag).
- **Core workflow:** Opportunity (draft) → publish → Application → accept → Collaboration (application may be declined/withdrawn).
  - Opportunity: `draft` → `published` → `closed` → `completed`
  - Application: `pending` → `accepted` | `declined` | `withdrawn`
  - Collaboration: `scheduled` → `active` → `completed` | `cancelled`
- **MVP schema (8 tables):** `profiles` (main user, Google OAuth), `business_profiles`, `community_profiles`, `business_subscriptions` (Stripe monthly), `cities`, `collab_opportunities`, `applications`, `collaborations`.
- **Service layer:** all business logic in `app/Services/`, not controllers or models. Core: `GoogleAuthService` (token verify, user create), `ProfileService`, `OpportunityService`, `ApplicationService` (accept/decline/withdraw), `CollaborationService` (status transitions), `SubscriptionService` (Stripe).
- **API (`/api/v1/`):** `auth/google`, `auth/logout`, `auth/me`, `profiles/{id}`, `opportunities` (CRUD + publish/close), `applications` (accept/decline/withdraw), `collaborations`, `me/subscription`, `webhooks/stripe`, `cities`.
- **Authorization:** use Laravel Policies, not middleware-only checks (e.g. `OpportunityPolicy::publish` = owner AND `hasActiveSubscription()`; see reference).
- **JSONB fields** on opportunities: `business_offer`, `community_deliverables`, `categories` (shapes in reference).
- **PHP enums:** `UserType` (business, community); `OfferStatus` (draft, published, closed, completed); `ApplicationStatus` (pending, accepted, declined, withdrawn); `CollaborationStatus` (scheduled, active, completed, cancelled); `SubscriptionStatus` (active, cancelled, past_due, inactive).
- **Files:** `.agent/` = agent task management (`documentations/`, `todo/`, `inprogess/`, `done/`); `mobile_mvp_database.sql` = MVP Postgres schema; `README.MD` = full project docs.

## Testing Conventions

- Use the `DatabaseTransactions` trait (not `RefreshDatabase`).
- Create factories for all models.
- Feature tests in `tests/Feature/`, by domain. Service unit tests in `tests/Unit/Services/`.

## Key Business Rules

1. Business users need an active Stripe subscription to publish opportunities.
2. One application per user per opportunity (unique constraint).
3. Accepting an application creates a collaboration; the opportunity may close.
4. Google OAuth only; no password auth in MVP.

===

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

Curated by Laravel maintainers for this app. Follow them closely.

## Foundational Context
Abide by these package versions: php 8.4.15; laravel/framework v12; laravel/prompts v0; laravel/sanctum v4; laravel/mcp v0; laravel/pint v1; laravel/sail v1; phpunit/phpunit v11.

## Conventions
- You must follow existing code conventions. When creating/editing a file, check sibling files for structure, approach and naming.
- Use descriptive names (`isRegisteredForDiscounts`, not `discount()`).
- Check for existing components to reuse before writing a new one.
- Do not create verification scripts or tinker when tests already prove the functionality.
- Stick to the existing directory structure. No new base folders without approval.
- Do not change dependencies without approval.
- If a frontend change does not show in the UI, the user may need `npm run build`, `npm run dev` or `composer run dev`. Ask them.
- Be concise in explanations.
- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

## Laravel Boost (MCP server — use its tools)
- `list-artisan-commands`: double-check Artisan parameters before calling a command.
- `get-absolute-url`: use whenever you share a project URL (correct scheme, domain/IP, port).
- `tinker`: execute PHP to debug or query Eloquent models. `database-query`: read-only DB reads.
- `browser-logs`: read browser logs/errors/exceptions. Only recent logs are useful.
- `search-docs` (critical): you must use it before any other approach for Laravel-ecosystem docs (Laravel, Inertia, Livewire, Filament, Tailwind, Pest, Nova, Nightwatch, …); it returns version-specific docs for installed packages. Search before making code changes. Pass multiple broad, simple, topic-based queries (e.g. `['rate limiting', 'routing rate limiting', 'routing']`). Do not put package names in queries. Optionally pass a packages array to filter. Syntax: words auto-stem and AND together; quoted phrases match exactly; `queries=[...]` = ANY.

=== php rules ===

## PHP
- Always use curly braces for control structures, even one-liners.
- Use PHP 8 constructor property promotion. No empty zero-parameter `__construct()` unless private.
- Always use explicit return types and parameter type hints.
- Prefer PHPDoc blocks over inline comments. No comments in code unless something is very complex. Add array-shape types in PHPDoc when appropriate.
- Enum keys are typically TitleCase (`FavoritePerson`, `Monthly`).

=== tests rules ===

## Test Enforcement
- Every change must be programmatically tested: write or update a test, then run the affected tests.
- Run the minimum tests needed: `php artisan test --compact` with a filename or filter.

=== laravel/core rules ===

## Do Things the Laravel Way
- Create files with `php artisan make:` (generic class: `make:class`). Pass `--no-interaction` and the correct `--options` to all Artisan commands.
- Database: always use Eloquent relationship methods with return types; prefer relationships and `Model::query()` over raw queries, joins and `DB::`. Prevent N+1 with eager loading. Use the query builder for very complex operations.
- New models: also create useful factories and seeders. Ask the user if they need other things (check `make:model` options via `list-artisan-commands`).
- APIs: default to Eloquent API Resources and API versioning, unless existing routes do not; then follow existing convention.
- Validation: always use Form Request classes (rules + custom messages), not inline controller validation. Check siblings for array vs string rules.
- Use queued jobs (`ShouldQueue`) for time-consuming operations.
- Use built-in auth/authz (gates, policies, Sanctum).
- Links: prefer named routes and `route()`.
- Never call `env()` outside config files; use `config('app.name')`, not `env('APP_NAME')`.
- Tests: build models with factories and their custom states. Faker via `$this->faker->word()` or `fake()->randomDigit()`, following existing convention. Create tests with `php artisan make:test [options] {name}` (`--unit` for unit); most tests should be feature tests.
- Vite error "Unable to locate file in Vite manifest": run `npm run build` or ask the user to run `npm run dev` / `composer run dev`.

=== laravel/v12 rules ===

## Laravel 12
- Use `search-docs` for version-specific docs. This project uses the streamlined (Laravel 11+) structure.
- No `app/Http/Kernel.php`: register middleware, exceptions and routing in `bootstrap/app.php` (`Application::configure()->withMiddleware()`). App service providers: `bootstrap/providers.php`.
- No `app/Console/Kernel.php`: use `bootstrap/app.php` or `routes/console.php`. Commands in `app/Console/Commands/` auto-register.
- Modifying a column: the migration must include all previously defined attributes, or they are dropped.
- Native eager-load limits: `$query->latest()->limit(10);`.
- Prefer a `casts()` method over the `$casts` property; follow existing models.

=== pint/core rules ===

## Laravel Pint
- You must run `vendor/bin/pint --dirty` before finalizing changes. Do not run `vendor/bin/pint --test`; run `vendor/bin/pint` to fix formatting.

=== phpunit/core rules ===

## PHPUnit
- All tests must be PHPUnit classes (`php artisan make:test --phpunit {name}`). Convert any Pest test to PHPUnit.
- Every time a test is updated, run that single test.
- When your feature's tests pass, ask the user if they also want the full suite run.
- Test happy, failure and weird paths.
- You must not remove tests or test files from `tests/` without approval.
- Run the minimal tests with a filter before finalizing. All: `php artisan test --compact`. One file: `php artisan test --compact tests/Feature/ExampleTest.php`. One test: `php artisan test --compact --filter=testName` (recommended after changing a related file).
</laravel-boost-guidelines>
