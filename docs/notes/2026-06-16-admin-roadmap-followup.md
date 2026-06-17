# Admin Dashboard Roadmap — follow-up work (2026-06-16)

**Initial ticket:** "Kolabing Admin Dashboard — Current Implementation + Future Roadmap"
(the gamification / CRM / types / badges spec doc).

**Ask:** review the roadmap against the real `kolabing-v2` code, pitch the changes,
detect mistakes/gaps in the DB + admin dashboard, then apply the agreed fixes.

This note records what was verified, what was wrong, and what was changed.

---

## 1. Audit outcome (doc vs real code)

The roadmap was **largely accurate** for Gamification (Challenges, Defaults, XP rules,
Levels, Economics) and CRM/Tasks/Badges — schemas, enums, services and behaviours
matched the code. The exceptions found:

### Factual mistakes in the doc
- **Types module is mostly fictional.** No `TypeController`, no admin routes for types,
  no SortableJS UI, no retire-vs-delete, no `icon_url` column. The mobile lookup
  (`/api/v1/lookup/business-types`, `/community-types`) returns **hardcoded arrays**
  from `LookupController`; the `business_types` / `community_types` tables existed but
  were **unused by the API**.
- **Slug mismatch.** Wire/validation standard is **underscore** (`run_club`, `cafe`);
  the type seeders used hyphens and even Spanish names (`cafeteria`, `spa-y-bienestar`)
  that matched nothing.
- **"35 system challenges" → actually 48** (`SystemChallengeSeeder`).
- **Badges:** attendee trigger column is `milestone_type` (not `trigger`); 9 attendee
  badges; business/community badge display copy is editable without deploy via
  `gamification_badge_overrides` (only slugs/triggers need a deploy).
- **Namespacing:** routes are `admin.gamification.*`, not bare `gamification.*`.
- Minor: challenge `bonus_value` is `string(120)`; `ChallengePolicy` exists but admin
  access is enforced by `maintainer` middleware.

### Real bugs / contradictions confirmed in code
- **XP Levels save-then-validate bug** — `XpLevelService::update()` saved before
  validating the ladder, with no transaction, so an invalid edit could persist.
- **XP → money was live, not "future"** — `euro_cents_per_point` powered real cash
  withdrawals via `GamificationController::withdrawal()`, contradicting the stated
  "XP = reputation only" principle.
- **Stale CRM comment** — `CrmScoreService` docblock said "communities not scored"
  but the code scores them like businesses.
- `admin_column_prefs.admin_id` uuid→bigint history — confirmed accurate (already fixed
  by an earlier migration).

---

## 2. Changes applied (this pass)

Branch: `crm-admin-fix` (changes currently uncommitted).

1. **Xp Levels transaction fix** — `app/Services/Admin/XpLevelService.php`
   `update()` now wraps save + `validateLadder()` in `DB::transaction`; a failing
   validation rolls the save back instead of leaving an invalid ladder in the DB.

2. **Withdrawals gated to referral rewards only** — XP is reputation, never cash.
   - `app/Http/Controllers/Api/V1/GamificationController.php` — `withdrawal()` now
     checks `Wallet::getReferralAvailablePoints()` (referral ledger credits minus
     amounts already withdrawn) instead of the full XP balance. Rejection message:
     "Insufficient referral rewards. Need X, have Y."
   - `app/Models/Wallet.php` — added `getReferralAvailablePoints()`; removed the
     hardcoded `getEurValue()/getProgress()/canWithdraw()` (`0.20` / `375` magic
     numbers) in favour of economics-driven values in the resource.

3. **Wallet display shows the real referral value** —
   `app/Http/Resources/Api/V1/WalletResource.php`:
   - `points` / `available_points` = XP reputation (unchanged).
   - **new** `referral_available_points` = withdrawable referral points.
   - `eur_value` = `economics.payoutFor(referralAvailable)` (true cash value).
   - `progress`, `can_withdraw`, `withdrawal_threshold` now derive from
     referral-available + `RewardEconomicsService` (no hardcoded numbers).

4. **Type slugs standardized to underscore + tables made canonical** —
   `database/seeders/BusinessTypeSeeder.php`, `CommunityTypeSeeder.php` now mirror
   `BusinessOnboardingRequest::BUSINESS_TYPES` / `CommunityOnboardingRequest::COMMUNITY_TYPES`
   (the validation source of truth) and **delete** any non-canonical rows, so the
   tables can no longer drift from the wire format. Result after seeding:
   - business: `bakery, bar, cafe, coworking, gym, hotel, other, restaurant, retail, salon`
   - community: `art_creative_community, book_club, business_coworking, dance_community,
     fitness_community, food_community, hobby_community, music_community, other,
     photography_community, professional_networking_community, run_club,
     student_community, sustainability_community, tech_startup_community,
     travel_community, wellness_community`

5. **Stale CRM docblock fixed** — `app/Services/CrmScoreService.php` now documents
   that communities ARE scored (Y/N factors capped at 100, own weight set).

6. **Corrected roadmap doc** — `docs/ADMIN-DASHBOARD-ROADMAP.md` (full corrected
   version of the original ticket, every error fixed, changes logged).

---

## 3. Verification

- `vendor/bin/pint` — pass on all changed files. `php -l` — clean.
- Test suites run, all green (65 tests):
  - `Tests\Feature\Api\V1\GamificationWalletTest` (19) — incl. new
    `wallet_xp_points_are_not_cash_convertible`, `wallet_eur_value_reflects_referral_rewards`,
    `withdrawal_fails_when_only_xp_no_referrals`.
  - `Tests\Feature\Admin\RewardEconomicsAdminTest` (8),
    `Tests\Feature\Admin\GamificationAdminTest` (7, incl. level-validation rollback path),
    `Tests\Feature\Admin\CrmScoreTest` + `CrmAdminTest` (7),
    `Tests\Feature\Api\V1\GamificationConfigTest` (5),
    `Tests\Feature\Api\V1\LookupControllerTest` (19).
- Type seeders executed live; tables verified to hold exactly the canonical slug sets.

---

## 4. Still open (recommended next)

- **Types architecture decision (blocker for any Types admin):** pick ONE source of
  truth — either point the lookup endpoints at the now-canonical DB tables and build
  the admin CRUD the original doc imagined, or keep the hardcoded lists and drop the
  tables. The type models' `hasMany(profile)` relations are non-functional today
  (profiles store the type as a string slug, not a UUID FK).
- The "Future ideas" sections of the original roadmap remain future-only — not built.

---

## 5. Files touched
```
app/Services/Admin/XpLevelService.php
app/Http/Controllers/Api/V1/GamificationController.php
app/Models/Wallet.php
app/Http/Resources/Api/V1/WalletResource.php
app/Services/CrmScoreService.php
database/seeders/BusinessTypeSeeder.php
database/seeders/CommunityTypeSeeder.php
tests/Feature/Api/V1/GamificationWalletTest.php
tests/Feature/Admin/RewardEconomicsAdminTest.php
docs/ADMIN-DASHBOARD-ROADMAP.md  (corrected roadmap)
docs/notes/2026-06-16-admin-roadmap-followup.md  (this note)
```
