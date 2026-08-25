# Deploy runbook — onboarding update + kolab SoT + admin taxonomies/icons

> Prepared 2026-06-17. Everything below is LOCAL/undeployed on two branches.
> **Do not push to master / deploy to production without Daniel's explicit go.**
> Deploy ORDER: backend first, then app (the app has fallbacks/guards so it is
> safe either way, but backend-first avoids the app rendering fallbacks).

## Branches to ship
- **Backend** `feat/onboarding-backend-local` (kolabing-v2) — contains kolab SoT Phase 1+2, onboarding backend support, auto-create community + auto-offer, admin offer_options taxonomy (+product_type/venue_type), personalised icon library, community type/size inheritance, community_size profile-edit.
- **App** `feat/onboarding-update` (kolabing-app) — kolab SoT Phase 3 (`/kolabs`), dynamic pickers + personalised `CategoryIcon`, goal-based onboarding + product_type picker, rewards spinner + session-reset fixes, dashboard null-tolerance, community_size profile edit.

## Pre-deploy (both repos)
- Backend: `php -d memory_limit=1G artisan test` per-area (full suite OOMs at 128M — known/pre-existing; run filtered: `--filter "Kolab|Onboarding|Auth|Community|Dashboard|Application|Collaboration|Lookup|OfferOption|Icon|Profile"`). Pint clean.
- App: `flutter analyze lib` (baseline infos only), `flutter test`.
- Merge each branch to `master` (PR review), deploy from master.

## 1. Backend deploy (kolabing-v2) — FIRST
```
composer install --no-dev --optimize-autoloader      # pulls blade-lucide-icons + offer_options/icons code
php artisan migrate                                   # see migrations below
php artisan db:seed --class=Database\Seeders\OfferOptionSeeder   # offering/need/deliverable/product_type/venue_type
php artisan db:seed --class=Database\Seeders\IconSeeder          # 24 personalised SVGs -> storage/app/public/category-icons
php artisan db:seed --class=Database\Seeders\BusinessTypeSeeder  # applies_to + icon facets (idempotent upsert — confirm it upserts, not duplicates)
php artisan db:seed --class=Database\Seeders\BlogPostSeeder      # Community-Commerce blog articles (idempotent upsert by slug; re-run to ship edits)
php artisan storage:link                             # public symlink for icon SVGs
php artisan config:cache && php artisan route:cache && php artisan view:cache
```
**Migrations that will run (verify echoed output):**
- kolab SoT Phase 1: add nullable `kolab_id` to applications + collaborations; backfill `kolab_id = collab_opportunity_id` where it's a real kolab.
- kolab SoT Phase 2: **inverse-bridge backfill** — creates a `kolabs` row for each legacy `collab_opportunity` lacking one, then sets `kolab_id`. PROD baseline measured **20 applications + 9 collaborations** as true-legacy → expect those to reach **0 NULL kolab_id**. CONFIRM the `[kolab-sot-phase2]` migration log.
- onboarding: `business_profiles.has_venue/target_city_ids/offering/offer_photos/product_type`, `community_profiles.community_size`, `business_types.applies_to` (+ `icon` already existed).
- taxonomy: `offer_options` (+ `icon_url`), `icons` table.
**Verify after:** `GET /api/v1/lookup/{offerings,needs,deliverables,product-types,venue-types}` return `{value,label,icon,icon_url,is_active,sort_order}`; a seeded icon SVG loads (`/storage/category-icons/category-running.svg` → 200 image/svg+xml); `/me/dashboard` parses; admin `/admin/offer-options` + `/admin/icons` render with icons.
**Env:** ensure `GOOGLE_PLACES_API_KEY` is set in prod `.env` (venue path / `/places/*`). It was empty locally.

## 2. App deploy (kolabing-app) — AFTER backend
- Build with NO `--dart-define` → base URL uses the prod default (`https://kolabing.com/api/v1`). Confirm `ApiConfig.baseUrl` default.
- iOS: `ios/Runner/Info.plist` has `NSAllowsLocalNetworking` (loopback-only, App-Store-safe). Optional: remove for a pristine prod build; not required.
- Bump version/build number; `flutter build ipa` / `flutter build appbundle`.
- The app self-gates: `/lookup/*` 404 → bundled fallback; unknown onboarding fields → 422-strip-retry. So a brief backend/app version skew degrades gracefully.

## 3. Phase 4 — HELD (separate deploy, after prod soak)
Only after the above is verified healthy in prod: drop the legacy layer — remove dual-write, make `kolab_id` NOT NULL, drop `collab_opportunity_id` + the `collab_opportunities` table, retire both bridge services, re-point/remove `/opportunities*` browse, migrate `create_opportunity_screen` (app) to the kolab model, update BACKEND-SCHEMA.md. See the interim audit's Phase-4 checklist. Run the final "zero collab_opportunities references" audit.

## Post-merge actions (master → prod)
Run in this order once PR is merged to `master`:
1. **Set prod env FIRST** (before the deploy build): `GOOGLE_PLACES_API_KEY`, Sentry DSN, Apple IAP, OneSignal, Firebase, R2 — and **Stripe live keys** (`STRIPE_*`) if the paywall/subscription must work (they were commented in the dev env).
2. **Deploy backend** (Laravel Cloud auto-builds on master push). Confirm the deploy command runs: `composer install --no-dev --optimize-autoloader` → `php artisan migrate --force` → `php artisan storage:link` → `config/route/view cache`. The auto-seed migration provisions taxonomies/icons.
3. **Verify migration output:** `[kolab-sot-phase2]` line must end `… NULL kolab_id 20 -> 0` (applications) and `… 9 -> 0` (collaborations) on the real Postgres data.
4. **Smoke test** (see Post-deploy smoke below), watch Sentry.
5. **Deploy the mobile app AFTER** the backend is verified.
6. Sync any other open branches/PRs onto the new `master`.

## Rollback
Migrations are **additive** (no drops through Phase 2), so the schema is forward-compatible — old code runs fine against the new schema. Prefer a **code-level rollback**, not a DB rollback.

- **Code rollback (preferred, fast):** redeploy the previous Laravel Cloud build (or `git revert` the merge commit + push). Leave the DB as-is.
- **Do NOT run `migrate:rollback`:** the `add_kolab_links` migration's `down()` restores `collab_opportunity_id` to `NOT NULL`, which **fails** once any app-native row has a null `collab_opportunity_id`. The added columns/tables are harmless left in place.
- **⚠️ Data visibility gap:** content created during the new-code window lives in `kolabs`; pre-SoT code reads `collab_opportunities`. After a code rollback, kolab-native rows created in the window become invisible (orphaned, not corrupted). Conditional dual-write keeps legacy-backed rows readable, but brand-new kolabs won't be. → Keep the rollback decision window short; once real user data accumulates, **fix-forward** (migrations are idempotent) instead of rolling back.
- **App:** revert to the prior build (no data migration in the app; it degrades to bundled fallbacks if the backend reverts).

## Post-deploy smoke
Register a business (product + venue) and a community → auto-offer + auto-community; create/list/edit a kolab; apply → accept → collaboration → dashboard; rewards tab; admin assigns a personalised icon → shows in app.
