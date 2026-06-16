# Kolabing Admin Dashboard — Implementation + Roadmap (corrected)

> Corrected against the real `kolabing-v2` code on 2026-06-16 (migrations, models,
> enums, controllers, services, seeders, routes, Blade views). Where the original
> roadmap and the code disagreed, **the code wins** and the doc was fixed.
> Lines marked **[CHANGED 2026-06-16]** reflect fixes shipped the same day.

## Stack (verified)
- Laravel / PHP, Blade views, custom admin panel (no Nova / Filament).
- UUID primary keys on domain tables; admin `User` model uses a **bigint** id.
- Admin routes are namespaced **`admin.gamification.*`**, **`admin.crm.*`**, etc.
  (gated by `auth:admin` + `maintainer` middleware), NOT bare `gamification.*`.
- Three audiences: `attendee`, `business`, `community`.

## Important corrections vs the original roadmap

| # | Original claim | Reality in code |
|---|----------------|-----------------|
| 1 | "Types" admin module (`TypeController`, `{kind}` param, index/store/update/destroy/toggle/reorder, SortableJS UI, retire-vs-delete) | **Does not exist.** No `TypeController`, no admin routes for types. Types are not in the admin dashboard. |
| 2 | `/lookup` served from `business_types` / `community_types` tables | **Hardcoded.** `LookupController` returns hardcoded arrays validated against `BusinessOnboardingRequest::BUSINESS_TYPES` / `CommunityOnboardingRequest::COMMUNITY_TYPES`. The DB tables exist but were **unused by the API**. |
| 3 | `icon_url` column on type tables (uploaded SVG) | **No such column.** Only `icon` (a name like `coffee`). |
| 4 | Type slugs | Wire/validation standard is **underscore** (`run_club`, `cafe`). Seeders previously used **hyphens** and even Spanish names (`cafeteria`, `spa-y-bienestar`) — a real mismatch. **[CHANGED 2026-06-16]** seeders now mirror the canonical underscore constants and prune stale slugs. |
| 5 | "35 system challenges" | **48** (`SystemChallengeSeeder`: 14 ice_breaker + 12 cultural_exchange + 12 barcelona_vibe + 10 creative_fun). |
| 6 | Attendee badge "trigger" column | Column is **`milestone_type`** (enum `BadgeMilestoneType`); 9 attendee badges (`social_butterfly_10`, not `social_butterfly`). |
| 7 | Business/community badges "require deploy to change" | Slugs/triggers are enum-fixed (deploy), BUT a **`gamification_badge_overrides`** table lets admin edit name/description/icon/audiences without deploy. |
| 8 | XP = reputation only; point→money is a "potential future conflict" | The conversion was **live**: `euro_cents_per_point` powered real cash withdrawals. **[CHANGED 2026-06-16]** withdrawals are now gated to referral rewards only (see §3). |

---

## 1. Gamification → Challenges  *(implemented — accurate)*
- Table `challenges`: id(uuid), name(150), description(text,null), difficulty(10), points(uint),
  is_system(bool), category(30,null), event_id(FK,null), audience(20, default `both`), timestamps.
  Indexes: is_system, event_id, difficulty, category, audience. (`category`/`audience` added in
  later migrations.)
- Enums: `ChallengeDifficulty` easy=5/medium=15/hard=30; `ChallengeCategory`
  ice_breaker/cultural_exchange/barcelona_vibe/creative_fun; `ChallengeAudience` community/business/both.
- `challenge_completions`: status pending/verified/rejected, points_earned (default 0), unique
  (challenge_id, event_id, challenger_profile_id, verifier_profile_id), timestamps.
- `collaboration_challenges` (composite PK collaboration_id+challenge_id, timestamps);
  `collaboration_challenge_bonuses` (bonus_type via `ChallengeBonusType`; `bonus_value` is a
  **string(120)**, not numeric; unique collaboration_id+challenge_id).
- Admin: `Admin\ChallengeController` (index/create/store/edit/update/destroy), routes
  `admin.gamification.challenges.*`. Index shows **only** system challenges (is_system=true AND
  event_id IS NULL). `store()` forces is_system=true. Filters category/difficulty/audience,
  paginate(50), **hard delete**. `ChallengePolicy` exists but admin access is enforced by the
  `maintainer` middleware. Validation via `StoreChallengeRequest`/`UpdateChallengeRequest`.
- **48** system challenges via `SystemChallengeSeeder`. Assignment via
  `CollaborationChallengeService`; completion via `ChallengeCompletionService`
  (verify → award points, increment profile total_points + total_challenges_completed,
  badge checks; reject → nothing).

## 2. Gamification → Challenge Defaults  *(implemented — accurate)*
- `challenge_defaults`: challenge_id(FK), applies_to(20, business_type|community_type),
  type_value(100), position, unique (challenge_id, applies_to, type_value).
- `Admin\ChallengeDefaultsService::seedForCollaboration()` is idempotent (no-op if the
  collaboration already has challenges); `syncForType()` deletes+recreates in order, already
  wrapped in `DB::transaction`. Routes `admin.gamification.challenges.defaults.*`.

## 3. Gamification → XP Earn Rules, Levels & Economics

### XP Earn Rules *(implemented — accurate)*
- `xp_earn_rules`: event_type(60,unique), points(usmallint), label(120), is_active, position,
  index (is_active, position). No audience / no earn limits / no create-delete in admin
  (`Admin\XpEarnRuleController` = index/edit/update only; event_type read-only).
- `PointEventType` seeded by `XpEarnRuleSeeder`: collaboration_complete=10, first_kolab_bonus=50,
  review_posted=10, ugc_posted=10, referral_conversion=50, withdrawal=0.
- Inactive rule → `XpEarnRuleService::pointsFor()` falls back to `PointEventType::defaultPoints()`
  (NOT 0). Used by `GamificationWalletService::awardPoints()`.

### XP Levels *(implemented — bug fixed)*
- `xp_levels`: number(utinyint), title(120), min_xp(uint), max_xp(uint,null), color(9), position.
- Seeded ladder (`XpLevelSeeder`): 1 New Community 0–99 `#BDBDBD`, 2 Active Community 100–249
  `#90CAF9`, 3 Trusted Community 250–499 `#FFD361`, 4 Local Favorite 500–999 `#FF9F43`,
  5 Local Legend 1000+ `#8E50F1`.
- `XpLevelService` enforces: lowest band at 0, exactly one open-ended top, contiguous bands.
  **[CHANGED 2026-06-16]** `update()` now wraps save + `validateLadder()` in `DB::transaction`,
  so a failing edit rolls back instead of persisting an invalid ladder (was: save-then-validate).

### Economics *(implemented — product decision applied)*
- `reward_economics` (single row): referral_goal (def 3), referral_cash_reward_cents (def 7500),
  euro_cents_per_point (def 20), withdrawal_threshold_cents (def 7500), currency (def EUR).
- `RewardEconomicsService` caches `current()` 1h, busts on save, provides a cost-impact preview.
- **[CHANGED 2026-06-16] XP is reputation only; cash withdrawals are backed by REFERRAL rewards
  only.** `GamificationController::withdrawal()` now computes available funds from the point
  ledger as `referral_conversion` credits minus `withdrawal` debits — XP from challenges, reviews,
  UGC and collaborations is no longer cash-convertible.
  - Follow-up (not yet done): the wallet display helpers (`Wallet::getEurValue()`,
    `getProgress()`, `canWithdraw()`) and `WalletResource` still derive from total points with
    hardcoded `0.20` / `375`. They should read referral-available points + economics so the app's
    "withdraw" affordance matches what the API now allows.

## 4. Platform → Types  *(NOT an admin module — see corrections #1–#4)*
- Two tables `business_types` / `community_types` (id, name(100,unique), slug(100,unique),
  icon(50,null), sort_order, is_active, timestamps; index is_active+sort_order). **No `icon_url`.**
- The mobile **lookup is hardcoded** in `LookupController` (`GET /api/v1/lookup/business-types`,
  `/api/v1/lookup/community-types`) against the onboarding request constants. The DB tables are
  not yet wired to the API.
- **[CHANGED 2026-06-16]** `BusinessTypeSeeder` / `CommunityTypeSeeder` now mirror
  `BusinessOnboardingRequest::BUSINESS_TYPES` / `CommunityOnboardingRequest::COMMUNITY_TYPES`
  (underscore slugs) and delete any non-canonical rows.
- **Roadmap (do before building any Types admin):** pick ONE source of truth — either point the
  lookup endpoints at the DB tables (then build the admin CRUD the original doc imagined) or keep
  the hardcoded lists and drop the tables. Do not build admin screens over tables nothing reads.
  The type models' `hasMany(profile)` relations are non-functional today (profiles store the
  type as a **string slug**, not a UUID FK).

## 5. Sales & CRM → CRM  *(implemented — docblock fixed)*
- `crm_accounts` and `crm_score_weights` schemas match the original doc; types business/community/
  ambassador via `CrmAccount::TYPES`. Indexes type/status/owner/(type,score).
- `CrmScoreService`: business & community = Y/N fit factors capped at 100; ambassador =
  count×points, uncapped. **[CHANGED 2026-06-16]** stale "communities not scored" docblock/comment
  corrected — communities ARE scored (own weight set).
- `Admin\CrmController` index: tabs by type, filters owner/status/name, paginate(50),
  order score desc then name. Indicators: trial candidate (status Interested/Active && score>80),
  needs follow-up (≥14 days no activity). `syncNextActionTask()` mirrors next_action to one open task.
- `admin_column_prefs.admin_id` was created as uuid then fixed to bigint (migration
  `...030006_fix_admin_column_prefs_admin_id_type.php`) — accurate.

## 6. Sales & CRM → Tasks  *(implemented — accurate)*
- `crm_tasks`: title, crm_account_id(FK,null), assignee(60,null), area(20 sales/marketing/dev),
  subarea(30 business/communities/ambassadors/community_members), due_on(null), status(20
  open/doing/done), notes. Indexes area/subarea/assignee/status.
- `CrmTask::SUBAREA_AREA_RULES` = `['ambassadors' => ['sales','marketing']]` (not under dev).
- `Admin\TaskController` index defaults to active (open/doing), filter assignee/area/subarea,
  group by area→subarea, order due_on nulls-last, overdue flagged. CRM-synced defaults:
  area=sales, subarea from account type, assignee=owner.

## 7. Gamification → Badges  *(implemented — naming corrected)*
- Attendee badges are **DB-backed** (`badges` table: name, description, icon, `milestone_type`
  unique, milestone_value) and admin-editable; awards in `badge_awards`/`EarnedBadge`.
  Triggers (`BadgeMilestoneType`): first_checkin, first_challenge, events_attended_5,
  events_attended_10, rewards_won_10, social_butterfly_10, challenges_completed_50,
  points_500, points_2000.
- Business/community badges are enum-backed (`GamificationBadgeSlug`): first_kolab,
  content_creator, referral_pioneer, power_partner (+ community_earner, community-only).
  Slugs/triggers need a deploy; display copy is overridable via `gamification_badge_overrides`.

---

## Changes shipped 2026-06-16 (this pass)
1. `XpLevelService::update()` — wrapped save + validate in `DB::transaction` (rollback on invalid ladder).
2. `GamificationController::withdrawal()` — gated to referral-earned points only (XP not cash-convertible).
3. `BusinessTypeSeeder` / `CommunityTypeSeeder` — rewritten to mirror canonical underscore constants and prune stale slugs.
4. `CrmScoreService` — corrected stale "communities not scored" docblock/comment.

## Still open (recommended next)
- Wire wallet display/`WalletResource` to referral-available points + economics (UX match for #2).
- Decide the Types architecture (one source of truth) before any Types admin module.
- The "Future ideas" sections of the original roadmap remain valid as future-only; do not treat as built.
- **Category icon picker (gallery).** Admin assigns each business/community category an icon from a gallery picker (not free text). Backend foundation exists: `business_types.icon` column (and `applies_to`) is now seeded and exposed via `GET /lookup/business-types` (branch `feat/onboarding-backend-local`). The admin UI to browse/select icons + persist per category is the remaining work. Motivation: product-path category pills showed broken/missing icons in the app.
- **Remove ALL hard-wired pickable taxonomies → admin-managed.** Full inventory + per-item backend/app work + sequencing in [`ADMIN-TAXONOMIES-ROADMAP.md`](ADMIN-TAXONOMIES-ROADMAP.md). Headline gaps: `product_type` and `venue_type` have NO admin management (hardcoded enums + validation, both repos); `offering`/`needs`/`deliverables` are fixed on the unmerged `feat/admin-offer-taxonomy` branch (merge it); the app's kolab pickers are all hardcoded enums even where a dynamic endpoint exists; add a Cities admin module; retire the placeholder `CommunityType` enum in the app.
- **Separate venue vs non-venue business categories.** Categories must be flaggable as applicable to venue businesses, non-venue (product/service) businesses, or both. Backend foundation: `business_types.applies_to` (venue|product|both) added + seeded (hospitality = venue; retail/other = both). Remaining: admin UI to set `applies_to` per category, and the app filtering category pills by it (it currently shows the same set as venue businesses). Both items are admin-editable (no deploy), mirroring the Types module pattern. See `kolabing-app/docs/plans/2026-06-16-onboarding-update-plan.md` §7.
