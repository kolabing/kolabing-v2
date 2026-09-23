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

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to enhance the user's satisfaction building Laravel applications.

## Foundational Context
This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.4.15
- laravel/framework (LARAVEL) - v12
- laravel/prompts (PROMPTS) - v0
- laravel/sanctum (SANCTUM) - v4
- laravel/mcp (MCP) - v0
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- phpunit/phpunit (PHPUNIT) - v11

## Conventions
- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts
- Do not create verification scripts or tinker when tests cover that functionality and prove it works. Unit and feature tests are more important.

## Application Structure & Architecture
- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling
- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Replies
- Be concise in your explanations - focus on what's important rather than explaining obvious details.

## Documentation Files
- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

## Laravel Boost
- Laravel Boost is an MCP server that comes with powerful tools designed specifically for this application. Use them.

## Artisan
- Use the `list-artisan-commands` tool when you need to call an Artisan command to double-check the available parameters.

## URLs
- Whenever you share a project URL with the user, you should use the `get-absolute-url` tool to ensure you're using the correct scheme, domain/IP, and port.

## Tinker / Debugging
- You should use the `tinker` tool when you need to execute PHP to debug code or query Eloquent models directly.
- Use the `database-query` tool when you only need to read from the database.

## Reading Browser Logs With the `browser-logs` Tool
- You can read browser logs, errors, and exceptions using the `browser-logs` tool from Boost.
- Only recent browser logs will be useful - ignore old logs.

## Searching Documentation (Critically Important)
- Boost comes with a powerful `search-docs` tool you should use before any other approaches when dealing with Laravel or Laravel ecosystem packages. This tool automatically passes a list of installed packages and their versions to the remote Boost API, so it returns only version-specific documentation for the user's circumstance. You should pass an array of packages to filter on if you know you need docs for particular packages.
- The `search-docs` tool is perfect for all Laravel-related packages, including Laravel, Inertia, Livewire, Filament, Tailwind, Pest, Nova, Nightwatch, etc.
- You must use this tool to search for Laravel ecosystem documentation before falling back to other approaches.
- Search the documentation before making code changes to ensure we are taking the correct approach.
- Use multiple, broad, simple, topic-based queries to start. For example: `['rate limiting', 'routing rate limiting', 'routing']`.
- Do not add package names to queries; package information is already shared. For example, use `test resource table`, not `filament 4 test resource table`.

### Available Search Syntax
- You can and should pass multiple queries at once. The most relevant results will be returned first.

1. Simple Word Searches with auto-stemming - query=authentication - finds 'authenticate' and 'auth'.
2. Multiple Words (AND Logic) - query=rate limit - finds knowledge containing both "rate" AND "limit".
3. Quoted Phrases (Exact Position) - query="infinite scroll" - words must be adjacent and in that order.
4. Mixed Queries - query=middleware "rate limit" - "middleware" AND exact phrase "rate limit".
5. Multiple Queries - queries=["authentication", "middleware"] - ANY of these terms.

=== php rules ===

## PHP

- Always use curly braces for control structures, even if it has one line.

### Constructors
- Use PHP 8 constructor property promotion in `__construct()`.
    - <code-snippet>public function __construct(public GitHub $github) { }</code-snippet>
- Do not allow empty `__construct()` methods with zero parameters unless the constructor is private.

### Type Declarations
- Always use explicit return type declarations for methods and functions.
- Use appropriate PHP type hints for method parameters.

<code-snippet name="Explicit Return Types and Method Params" lang="php">
protected function isAccessible(User $user, ?string $path = null): bool
{
    ...
}
</code-snippet>

## Comments
- Prefer PHPDoc blocks over inline comments. Never use comments within the code itself unless there is something very complex going on.

## PHPDoc Blocks
- Add useful array shape type definitions for arrays when appropriate.

## Enums
- Typically, keys in an Enum should be TitleCase. For example: `FavoritePerson`, `BestLake`, `Monthly`.

=== tests rules ===

## Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== laravel/core rules ===

## Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using the `list-artisan-commands` tool.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Database
- Always use proper Eloquent relationship methods with return type hints. Prefer relationship methods over raw queries or manual joins.
- Use Eloquent models and relationships before suggesting raw database queries.
- Avoid `DB::`; prefer `Model::query()`. Generate code that leverages Laravel's ORM capabilities rather than bypassing them.
- Generate code that prevents N+1 query problems by using eager loading.
- Use Laravel's query builder for very complex database operations.

### Model Creation
- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `list-artisan-commands` to check the available options to `php artisan make:model`.

### APIs & Eloquent Resources
- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

### Controllers & Validation
- Always create Form Request classes for validation rather than inline validation in controllers. Include both validation rules and custom error messages.
- Check sibling Form Requests to see if the application uses array or string based validation rules.

### Queues
- Use queued jobs for time-consuming operations with the `ShouldQueue` interface.

### Authentication & Authorization
- Use Laravel's built-in authentication and authorization features (gates, policies, Sanctum, etc.).

### URL Generation
- When generating links to other pages, prefer named routes and the `route()` function.

### Configuration
- Use environment variables only in configuration files - never use the `env()` function directly outside of config files. Always use `config('app.name')`, not `env('APP_NAME')`.

### Testing
- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

### Vite Error
- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== laravel/v12 rules ===

## Laravel 12

- Use the `search-docs` tool to get version-specific documentation.
- Since Laravel 11, Laravel has a new streamlined file structure which this project uses.

### Laravel 12 Structure
- In Laravel 12, middleware are no longer registered in `app/Http/Kernel.php`.
- Middleware are configured declaratively in `bootstrap/app.php` using `Application::configure()->withMiddleware()`.
- `bootstrap/app.php` is the file to register middleware, exceptions, and routing files.
- `bootstrap/providers.php` contains application specific service providers.
- The `app\Console\Kernel.php` file no longer exists; use `bootstrap/app.php` or `routes/console.php` for console configuration.
- Console commands in `app/Console/Commands/` are automatically available and do not require manual registration.

### Database
- When modifying a column, the migration must include all of the attributes that were previously defined on the column. Otherwise, they will be dropped and lost.
- Laravel 12 allows limiting eagerly loaded records natively, without external packages: `$query->latest()->limit(10);`.

### Models
- Casts can and likely should be set in a `casts()` method on a model rather than the `$casts` property. Follow existing conventions from other models.

=== pint/core rules ===

## Laravel Pint Code Formatter

- You must run `vendor/bin/pint --dirty` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test`, simply run `vendor/bin/pint` to fix any formatting issues.

=== phpunit/core rules ===

## PHPUnit

- This application uses PHPUnit for testing. All tests must be written as PHPUnit classes. Use `php artisan make:test --phpunit {name}` to create a new test.
- If you see a test using "Pest", convert it to PHPUnit.
- Every time a test has been updated, run that singular test.
- When the tests relating to your feature are passing, ask the user if they would like to also run the entire test suite to make sure everything is still passing.
- Tests should test all of the happy paths, failure paths, and weird paths.
- You must not remove any tests or test files from the tests directory without approval. These are not temporary or helper files; these are core to the application.

### Running Tests
- Run the minimal number of tests, using an appropriate filter, before finalizing.
- To run all tests: `php artisan test --compact`.
- To run all tests in a file: `php artisan test --compact tests/Feature/ExampleTest.php`.
- To filter on a particular test name: `php artisan test --compact --filter=testName` (recommended after making a change to a related file).
</laravel-boost-guidelines>
