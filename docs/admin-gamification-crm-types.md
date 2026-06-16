# Admin Dashboard: Gamification, CRM, Tasks & Types

> Last updated: 2026-06-11
>
> Reference doc covering the existing implementation of: GAMIFICATION → Challenges,
> Challenge Defaults, XP Earn Rules, Levels & Economics; SALES & CRM → CRM and Tasks;
> PLATFORM → Types. All of these sections are **already implemented** — this doc
> exists to explain how they work, not to propose new ones.

---

## 1. GAMIFICATION → Challenges

### Schema

**`challenges`**
| column | type | notes |
|---|---|---|
| `id` | uuid PK | |
| `name` | string(150) | |
| `description` | text, nullable | |
| `difficulty` | string(10) | `ChallengeDifficulty`: `easy` (5pt), `medium` (15pt), `hard` (30pt) |
| `points` | unsigned int | actual XP awarded on completion |
| `is_system` | bool, default false | `true` = global catalogue challenge (admin-managed); `false` = organizer/collaboration-custom |
| `category` | string(30), nullable | `ChallengeCategory`: `ice_breaker`, `cultural_exchange`, `barcelona_vibe`, `creative_fun` |
| `event_id` | FK → events, nullable, cascade | set for event-scoped custom challenges |
| `audience` | string(20), default `both` | `ChallengeAudience`: `community`, `business`, `both` |

Indexes: `is_system`, `event_id`, `difficulty`, `category`, `audience`.

**`challenge_completions`**: `id`, `challenge_id`, `event_id`, `challenger_profile_id`, `verifier_profile_id` (FKs, cascade), `status` (`ChallengeCompletionStatus`: `pending`/`verified`/`rejected`), `points_earned` (default 0), `completed_at`. Unique on `(challenge_id, event_id, challenger_profile_id, verifier_profile_id)`.

**`collaboration_challenges`**: pivot, composite PK `(collaboration_id, challenge_id)`.

**`collaboration_challenge_bonuses`**: `id`, `collaboration_id`, `challenge_id`, `bonus_type` (`ChallengeBonusType`: `discount_percent`, `free_item`, `free_service`, `custom`), `bonus_value`, `bonus_description`, `set_by_profile_id`. Unique `(collaboration_id, challenge_id)` — one bonus per challenge per collaboration.

### Admin CRUD (`ChallengeController`, routes `gamification.challenges.*`)

- `index` — only `is_system=true AND event_id IS NULL` (event-scoped custom challenges are intentionally excluded). Filterable by category/difficulty/audience, paginated 50.
- `create`/`store`, `edit`/`update` — `Store/UpdateChallengeRequest`: `name` required ≤150, `description` ≤1000, `difficulty`/`category`/`audience` enum-validated, `points` 0–10000. Store always forces `is_system => true`.
- `destroy` — hard delete.
- Gated to maintainers via `ChallengePolicy`.

### Completion / award flow (not admin, for context)

- 35 system challenges seeded by `SystemChallengeSeeder` (4 categories, points from difficulty).
- `CollaborationChallengeService` syncs `collaboration_challenges` for a collaboration (manual selection, defaults matrix, or custom per-collaboration challenge with `is_system=false`).
- `ChallengeCompletionService`: peer-to-peer at events. A checked-in challenger initiates against a checked-in verifier → `pending` completion. `verify()` → `verified`, awards `points_earned = challenge.points`, bumps attendee `total_points`/`total_challenges_completed`, triggers badge checks. `reject()` → `rejected`, no points. One-time per `(challenge, event, challenger, verifier)` and capped by `event.max_challenges_per_attendee`.
- Bonuses are a separate concept: a business/community attaches a reward (discount/item/service/custom) to a specific challenge within a collaboration.

---

## 2. GAMIFICATION → Challenge Defaults

**Not** challenge templates and **not** global settings — it's a **role-based defaults matrix** (table `challenge_defaults`):

| column | type | notes |
|---|---|---|
| `id` | uuid PK | |
| `challenge_id` | FK → challenges, cascade | |
| `applies_to` | string(20) | `business_type` \| `community_type` |
| `type_value` | string(100) | underscored slug matching `business_profiles.business_type` / `community_profiles.community_type` |
| `position` | unsigned smallint, default 0 | display/seed order |

Unique `(challenge_id, applies_to, type_value)`.

Each row says "challenge X is a default for business/community type Y". When a collaboration is created, `ChallengeDefaultsService::seedForCollaboration()` resolves both parties' types, unions the matching challenge IDs, and `sync()`s them into `collaboration_challenges` — **only if the collaboration currently has zero challenges** (idempotent guard; re-running after a collaboration already has challenges has no effect).

### Admin flow (`ChallengeDefaultsController`, routes `gamification.challenges.defaults.*`)

- `index` — single page listing all `BusinessType`/`CommunityType` rows, all system challenges, and the current matrix (`ChallengeDefaultsService::matrix()`, keyed `"applies_to:type_value"` → ordered challenge IDs).
- `update` — one save per type row. Validates `applies_to` ∈ `{business_type, community_type}`, `type_value` required ≤100, `challenge_ids` nullable array of existing UUIDs. `syncForType()` deletes existing rows for that type and recreates in submitted order (deduped).

---

## 3. GAMIFICATION → XP Earn Rules

### Schema — `xp_earn_rules`

| column | type | notes |
|---|---|---|
| `id` | uuid PK | |
| `event_type` | string(60), unique | must match `App\Enums\PointEventType` |
| `points` | unsigned smallint | |
| `label` | string(120) | display text |
| `is_active` | bool, default true | |
| `position` | unsigned smallint, default 0 | admin ordering |

Index: `(is_active, position)`. **No** `audience` or `max_times_earnable` column — XP rules are global, not per-audience.

### Earnable action slugs (`PointEventType`, seeded by `XpEarnRuleSeeder`)

| `event_type` | default points | label |
|---|---|---|
| `collaboration_complete` | 10 | Complete a Kolab |
| `first_kolab_bonus` | 50 | First Kolab bonus |
| `review_posted` | 10 | Post a review |
| `ugc_posted` | 10 | Share content (UGC) |
| `referral_conversion` | 50 | Refer a partner |
| `withdrawal` | 0 | Withdrawal (deduction) |

### Relation to badges — separate, unrelated systems

`GamificationBadgeSlug` (badges admin) defines its own slugs (`first_kolab`, `content_creator`, `community_earner`, `referral_pioneer`, `power_partner`) plus the attendee `badges` table milestones (`first_checkin`, `events_attended_5`, etc., via `BadgeMilestoneType`). There is **no FK or shared table** between `xp_earn_rules` and badge triggers — badges are achievement/milestone unlocks (sometimes referencing XP thresholds), while `xp_earn_rules` are per-action point awards written to `point_ledger`.

### Admin flow — edit-only, no create/delete

`XpEarnRuleController` (routes `gamification.earn-rules.*`): `index`, `edit`, `update` only. One row per `PointEventType` case — the set is fixed by the enum, so `event_type` is read-only in the edit form. If a rule is deactivated, `pointsFor()` falls back to `PointEventType::defaultPoints()` rather than 0.

---

## 4. GAMIFICATION → Levels & Economics

These live alongside XP Earn Rules in the same gamification admin area.

### Levels — `xp_levels`

| column | type | notes |
|---|---|---|
| `id` | uuid PK | |
| `number` | unsigned tinyint, unique | immutable in admin |
| `title` | string(120) | |
| `min_xp` | unsigned int | |
| `max_xp` | unsigned int, nullable | null only on the open-ended top tier |
| `color` | string(9) | hex |
| `position` | unsigned smallint | |

Seeded ladder (`XpLevelSeeder`, matches mobile app constants):
1. New Community: 0–99 (#BDBDBD)
2. Active Community: 100–249 (#90CAF9)
3. Trusted Community: 250–499 (#FFD361)
4. Local Favorite: 500–999 (#FF9F43)
5. Local Legend: 1000–null (#8E50F1)

`XpLevelService::validateLadder()` enforces: lowest band starts at 0, exactly one band has `max_xp = null`, bands are contiguous (`min_xp` = previous `max_xp + 1`).

Admin flow: `index`, `edit`, `update` only (`gamification.levels.*`) — `number` read-only, ladder must stay a fixed validated set so no create/delete.

> **Known quirk:** `XpLevelService::update()` saves first, then validates the ladder, throwing on violation. The controller catches it and redirects with errors, but the invalid row was already persisted before the exception — not wrapped in a DB transaction.

Both `XpEarnRuleService::update()` and `XpLevelService::update()` invalidate the same cache key (`gamification.config`).

### Economics — `reward_economics` (single row)

Edited via `gamification.economics.*`:
- `referral_goal` (1–100) — referrals needed to unlock cash reward
- `referral_cash_reward_cents` (0–1,000,000)
- `euro_cents_per_point` (1–500) — point→€ conversion rate
- `withdrawal_threshold_cents` (0–1,000,000)
- `currency` (3-letter ISO)

`RewardEconomicsService::costImpact()` shows a live cost-impact preview: `threshold_points`, count of wallets above threshold, total potential € liability. Cached 1h, busted on save.

---

## 5. PLATFORM → Types

One controller (`TypeController`) serves two `kind`s via `{kind}` route param: `community` and `business`, each backed by its own table with identical schema.

### Schema — `community_types` / `business_types`

| column | type | notes |
|---|---|---|
| `id` | uuid PK | |
| `name` | string(100), unique | |
| `slug` | string(100), unique | underscore-slug, auto-generated from name if not given |
| `icon` | string(50), nullable | Lucide/bundled icon key |
| `icon_url` | string, nullable | admin-uploaded SVG (added later) |
| `sort_order` | unsigned smallint, default 0 | |
| `is_active` | bool, default true | |

Index `(is_active, sort_order)`.

These tables are the **canonical source of truth** for the type vocabularies (community: 17 types e.g. `run_club`, `fitness_community`, ... `business_coworking`, `other`; business: 10 types e.g. `cafe`, `restaurant`, `bar`, ... `other`), exposed to the mobile app via `/lookup`. See `docs/plans/2026-06-10-type-source-of-truth-DECISION.md`.

### Admin CRUD/reorder/toggle flow

- `index` — tabbed (Community/Business), sortable table (SortableJS) with icon preview, name, slug, "in use" count, active toggle, Edit/Retire-or-Delete.
- `inUseCount()` checks `business_profiles.business_type` / `communities.type` for references.
- `destroy()` — if in use → soft "retire" (`is_active = false`); if unused → hard delete. **Never** hard-deletes an in-use type.
- `toggle()` — flips `is_active`.
- `reorder()` — accepts ordered ids, writes `sort_order = index + 1`.
- `store`/`update` — `name` required; `slug` auto-slugified (lowercase + underscores) if omitted; `icon` ≤50 chars; optional `icon_svg` upload (≤128KB → stored to `type-icons/`, saved as `icon_url`); `sort_order`; `is_active` checkbox.

---

## 6. SALES & CRM → CRM

### Subjects

`CrmAccount::TYPES` = `business`, `community`, `ambassador`.

### Schema — `crm_accounts`

| column | type | notes |
|---|---|---|
| `id` | uuid PK | |
| `type` | string(20) | business \| community \| ambassador |
| `name` | string | |
| `status` | string(30), nullable | pipeline stage, e.g. Target/Contacted/Interested/Active/Lost |
| `owner` | string(60) | sales owner |
| `email`, `phone`, `instagram_handle`, `whatsapp` | strings | contact info |
| `next_action` | string | free text |
| `notes` | text | |
| `score` | int, default 0 | computed by `CrmScoreService` |
| `metrics` | json | type-specific fields |
| `linked_profile_id` | FK → profiles, nullOnDelete | |
| `last_activity_at` | date | |

Indexes: `type`, `status`, `owner`, `(type, score)`.

### `crm_score_weights`

`id`, `applies_to` (business/ambassador/community), `key`, `label`, `points`, unique `(applies_to, key)`. Admin-adjustable; seeded from `CrmScoreService::DEFAULTS`:
- business: 6 yes/no fit factors summing to 100
- ambassador: 5 contribution-count metrics
- community: 5 yes/no factors summing to 100

`CrmScoreService::score()`:
- business/community → sum of points where `metrics[key] === true`, capped at 100
- ambassador → `Σ(count_in_metrics × points)`, uncapped
- `recalculate()` runs on every store/update.

> **Known doc/code mismatch:** an inline comment says "communities not scored (0)", but `community` is actually scored the same as `business` (0–100 from yes/no factors) — the comment is stale.

### Column preferences (`saveColumns`)

Per-admin, per-type visible-columns config stored in `admin_column_prefs` (`admin_id` bigint, `table_key` = `"crm.{type}"`, `visible_columns` json, unique `(admin_id, table_key)`). `columnsFor($type)` defines a column catalog (common + type-specific metrics); `name` is always forced visible. Note: `admin_id` was originally (incorrectly) a uuid and had to be dropped/recreated as bigint to match the bigint-id admin `User` model — this caused a 500 on `/admin/crm` in production before the fix.

### Admin flow

`CrmController` — index has tabs per type, filters (owner/status/name), paginated 50, ordered by score desc then name. Row indicators: 🔥 `isTrialCandidate()` (status Interested/Active and score > 80), ⚠️ `needsFollowUp()` (no activity ≥14 days). On store/update: `recalculate()` score, then `syncNextActionTask()` (see below).

---

## 7. SALES & CRM → Tasks

### Schema — `crm_tasks`

| column | type | notes |
|---|---|---|
| `id` | uuid PK | |
| `title` | string | |
| `crm_account_id` | FK → crm_accounts, nullOnDelete, nullable | tasks can be standalone |
| `assignee` | string(60), nullable | |
| `area` | string(20) | sales \| marketing \| dev |
| `subarea` | string(30) | business \| communities \| ambassadors \| community_members |
| `due_on` | date, nullable | |
| `status` | string(20), default `open` | open \| doing \| done |
| `notes` | text | |

Indexes: area, subarea, assignee, status. Validation rule (`CrmTask::SUBAREA_AREA_RULES`): `ambassadors` subarea is only valid under `sales`/`marketing`, not `dev`.

### Admin flow

`TaskController` — index defaults to `status=active` (open+doing), filterable by assignee/area/subarea/status, grouped by area then subarea (sales → marketing → dev), ordered by due date (nulls last). Overdue tasks (past `due_on`, not done) flagged with ‼️. Standard create/edit/destroy.

### Auto-sync from CRM

`CrmController::syncNextActionTask()` mirrors an account's `next_action` text into a single open `crm_tasks` row (area=sales, subarea derived from account type, assignee=owner). Updates the existing open task if one exists, else creates one — runs on every CRM account store/update.

---

## 8. Other notes

- Production seeding (`crm_accounts`/`crm_score_weights`) runs `CrmSeeder` via `updateOrCreate` only when `app.env === production` during `migrate --force` on Forge — local/test DBs stay clean.
- The type-tables "source of truth" migration (`2026_06_11_000002`) is non-destructive: matches existing rows by canonical slug, hyphen variant, or name, updates in place, never deletes.
