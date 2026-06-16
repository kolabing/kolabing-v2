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

## Rollback
- Backend: migrations are additive through Phase 2 (no drops) — rollback = `migrate:rollback` the new batches; the inverse-bridge-created kolabs are harmless if left. App degrades to fallbacks if backend reverts.
- App: revert to prior build (no data migration in the app).

## Post-deploy smoke
Register a business (product + venue) and a community → auto-offer + auto-community; create/list/edit a kolab; apply → accept → collaboration → dashboard; rewards tab; admin assigns a personalised icon → shows in app.
