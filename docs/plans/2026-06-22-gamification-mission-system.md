# Gamification — Challenges → Mission System (branch `gamif-admin-fix`)

> Decision (Daniel, 2026-06-22): **replace the 49 peer-verified event icebreakers**
> with the onboarding/growth **missions** from the "Kolabing Admin Dashboard" doc.
> Scope = **full mission system** (not a data swap). **Wipe** the old challenges +
> their completions (pre-launch, acceptable). Seed the new set via a **data
> migration** so it lands on prod when the PR is merged (auto-deploy runs `migrate`,
> not `db:seed`).

## Why a mission system, not a seeder swap
The current `challenges` are peer-verified at events (two checked-in people verify
each other), audience `both`, categories ice_breaker/cultural_exchange/barcelona_vibe/
creative_fun. The doc's challenges are **self-tracked missions** ("Complete your
profile", "Publish your first Kolab", "Attend 5 Kolabs", "Refer a business") across
**attendee / business / community**. Missions need: an `attendee` audience, a
**trigger + target + repeat** model, and an **auto-award engine** that progresses a
mission when the matching platform action happens. None of that exists today.

## Ripple already mapped (read-only audit)
- 49 challenges in `SystemChallengeSeeder` (`updateOrCreate` keyed on `name`, no slug).
- Prod is **not** auto-seeded (auto-deploy = `migrate`); needs a data migration.
- FKs `cascadeOnDelete`: deleting `challenges` cascades `challenge_completions`,
  `collaboration_challenges`, `collaboration_challenge_bonuses`, `challenge_defaults`;
  `community_goals.challenge_id` → null. (Wipe is acceptable, pre-launch.)
- Admin forms read enum cases dynamically; tests use factories — adding enum cases /
  columns does **not** break them.

---

## Target schema

### Enums (additive — keep old cases so any legacy custom event challenges still cast)
- `ChallengeAudience`: add **`attendee`** (now community | business | both | attendee).
  `both` keeps meaning business+community. Attendee is its own audience.
- `ChallengeCategory`: **add** mission categories (keep the 4 legacy): `onboarding`,
  `attendance`, `engagement`, `content`, `referral`, `growth`, `social`, `milestone`.
- `ChallengeDifficulty`: unchanged (easy=5 / medium=15 / hard=30 default), but the
  seeder sets `points` explicitly per mission (see points rule).
- New enum `MissionTrigger` (string-backed) — the action that progresses a mission.
- New enum `MissionRepeat`: `once` (default) | `daily` | `weekly` | `monthly` | `seasonal`.

### `challenges` — new nullable columns (migration)
| Column | Type | Notes |
|---|---|---|
| `trigger_action` | string(60) nullable | a `MissionTrigger` value; null = legacy peer-verified challenge |
| `target_value` | unsigned int default 1 | how many times the trigger must fire (e.g. attend 5 → 5) |
| `repeat_interval` | string(20) default `once` | a `MissionRepeat` value |
| `starts_at` | timestamp nullable | campaign window start |
| `ends_at` | timestamp nullable | campaign window end |
| `slug` | string(120) unique nullable | **add stable slugs** so seeding is idempotent by slug, not name |

Index: `(audience, is_system)`, `(trigger_action)`.

### `challenge_progress` (new table — self-tracked mission progress)
| Column | Type | Notes |
|---|---|---|
| `id` | uuid PK | |
| `challenge_id` | uuid FK → challenges (cascade) | |
| `profile_id` | uuid FK → profiles (cascade) | the earner |
| `progress_count` | unsigned int default 0 | |
| `target_value` | unsigned int | snapshot of challenge target at start |
| `completed_at` | timestamp nullable | set when progress_count >= target |
| `period_key` | string(20) nullable | repeat bucket: `once` / `2026-06` (monthly) / `2026-W25` (weekly) / `2026-06-22` (daily) |
| unique | `(challenge_id, profile_id, period_key)` | one progress row per earner per period |

`challenge_completions` (peer-verified, event) stays for legacy/event challenges; it is
**not** the mission ledger.

---

## MissionTrigger vocabulary (✓ = already emitted today via `PointEventType`; ⧖ = source must be wired in Phase 2)

Attendee: `profile_completed` ⧖, `event_checkin` ⧖, `challenge_completed` ⧖,
`review_posted` ✓, `social_share` ✓(ugc_posted), `friend_invited` ⧖, `community_joined` ⧖.
Business: `business_profile_completed` ⧖, `business_photo_uploaded` ⧖,
`kolab_published` ⧖, `application_received` ⧖, `application_accepted` ⧖,
`collaboration_complete` ✓, `kolab_created_content`/`_review`/`_revenue`/`_product` ⧖,
`recurring_kolab_created` ⧖, `review_received` ✓(review_posted on counterparty),
`content_brief_uploaded` ⧖, `business_referred` ✓(referral_conversion),
`subscription_renewed` ⧖, `plan_upgraded` ⧖, `giveaway_kolab_created` ⧖.
Community: `community_profile_completed` ⧖, `community_photo_uploaded` ⧖,
`application_submitted` ⧖, `application_accepted` ⧖, `collaboration_complete` ✓,
`members_brought` ⧖, `member_checkin` ⧖, `ugc_created` ✓(ugc_posted),
`tagged_story_posted` ⧖, `business_referred` ✓, `business_review_received` ✓,
`recurring_kolab_hosted` ⧖, `members_invited` ⧖.

## Field-mapping rules (apply to every doc challenge → seeder row)
- **audience**: attendee / business / community per the doc's section. (No mission uses `both`.)
- **target_value**: parse the number in the text ("first/your" → 1, "3" → 3, "5" → 5, "10" → 10, "100" → 100).
- **repeat_interval**: "this month" / "top … of the month" → `monthly`; one-offs → `once`. Recurring engagement (member_checkin etc.) → `monthly` unless clearly one-off.
- **category**: profile/setup → `onboarding`; attend/check-in → `attendance`; reviews/UGC/content/stories → `content`; refer → `referral`; invite members/join community/bring members/social share → `social`; complete-N-kolabs / subscription / plan / recurring / collaborate-with-N → `growth`; "first X" headline achievements & top-of-month → `milestone`.
- **points**: profile/onboarding = 10; first-action (first kolab, first checkin, first review) = 20; N-times (3–5) = 30; 10+ / big milestones = 50; referral conversion & first-kolab bonus = 50; monthly "top" = 50.
- **difficulty**: target 1 → easy; 3–5 → medium; 10+ → hard (cosmetic; `points` is authoritative).
- **slug**: kebab-case of audience + short name, e.g. `attendee-complete-profile`, `business-publish-first-kolab`.
- **is_system** = true; `event_id` = null.

The full attendee / business / community challenge lists to seed are the "Future …
challenge ideas" sections of the source doc (Kolabing Admin Dashboard.md). Dedup the
overlapping ones; ~15 attendee + ~18 business + ~16 community ≈ 45–50 missions.

---

## Auto-award engine
`MissionService::record(Profile $earner, MissionTrigger $trigger, int $increment = 1, array $ctx = [])`:
1. Find active system missions where `trigger_action = $trigger` AND `audience` matches
   the earner's profile type (attendee/business/community), within `[starts_at, ends_at]`.
2. Resolve `period_key` from `repeat_interval` + now (passed in — **no `Date::now()` in
   migrations/seeders**, but services may use Carbon).
3. `firstOrCreate` the `challenge_progress` row for (challenge, earner, period_key),
   snapshot `target_value`.
4. Increment `progress_count`; if it reaches `target_value` and `completed_at` is null →
   set `completed_at`, **award `points` via the existing point-ledger path** (same one
   `PointEventType` uses) and run the existing **badge check** hook.
Idempotent + safe to call from anywhere a trigger fires.

### Integration (Phase 2)
- Hook `MissionService::record` at the **existing point-award sites** (where
  `PointEventType` is recorded) → instantly powers ✓ triggers (collaboration_complete,
  review_posted, ugc_posted, referral_conversion, first_kolab).
- Add hooks at ⧖ sources incrementally (profile completion, checkin, kolab publish,
  application accept, subscription/plan, member checkin). Each ⧖ mission is seeded +
  visible in admin but only fires once its source is wired. **`log()`/document which
  are live vs pending** so it's never silently dead.

---

## Admin
- `ChallengeController` create/edit + `_form.blade.php`: add `audience` (incl. attendee),
  the new categories, and **trigger_action / target_value / repeat_interval / starts_at /
  ends_at** fields (mission section). Index: allow filtering by audience incl. attendee.
- `ChallengeDefaultsController` matrix unaffected (still maps types → system challenges);
  note it's only meaningful for collaboration-attached challenges, not attendee missions.
- Short explanatory header per the doc's §9.3 ("Missions auto-complete when the member
  performs the action; peer-verified event challenges are separate").

---

## Phases (each independently committable on `gamif-admin-fix`)
1. **Foundation** — enums (attendee audience, mission categories, MissionTrigger,
   MissionRepeat) + migration (new `challenges` columns + slug + `challenge_progress`) +
   rewrite `SystemChallengeSeeder` to the mapped mission set + **data migration** that
   wipes old system challenges and (re)seeds the new set idempotently by slug + admin
   form/index updates + factory/tests. No behaviour wired yet (missions exist, manageable, seed to prod).
2. **Engine** — `MissionService` + `challenge_progress` model + award/badge wiring at the
   existing point-event sites (powers the ✓ triggers) + tests.
3. **Trigger wiring** — add ⧖ hooks at their sources (profile, checkin, kolab publish,
   application, subscription, member checkin), one logical group per commit, with tests.

## Out of scope (explicitly deferred)
- Converting legacy custom **event** challenges to missions (they stay peer-verified).
- Reward_type/value/badge_slug columns (points + existing bonus/badge systems cover it).
- XP↔money changes (separate Economics concern).

---

## ADDENDUM (Daniel, 2026-06-23): EVENT missions vs GENERAL missions — TWO kinds, coexist

There are **two distinct kinds of mission** and both stay in the architecture:

- **Event missions** — in-event, peer-style tasks done AT a kolab event, e.g.
  *"Take a story with a friend in the cafe"*, *"Get a coffee"*, *"Take a selfie together"*.
  Peer-verified; power the kolab **"GAMIFICATION SETUP"** attendee picker. **Architecture
  stays.** The ones currently SEEDED (the old icebreakers) were **DEMO data — remove them**;
  real event missions get added later (curated and/or business-authored per event).
- **General missions** — auto-tracked onboarding/growth goals (the 49 seeded here), e.g.
  *"Complete your profile"*, *"Attend 5 Kolabs"*, *"Refer a business"*. Shown on the app
  Missions screen; fired by `MissionService` triggers.

**So:** wiping the demo event challenges was fine. The missing piece is **separating the
two so each surface shows the right kind:**
1. Distinguish by `trigger_action`: **event mission = no `trigger_action`** (peer-verified),
   **general mission = `trigger_action` set** (auto-tracked).
2. The kolab attendee-challenge picker (`ChallengeController` / challenge defaults) must
   filter to **event** missions (`trigger_action` null) — NOT show the general missions.
   (Today it'd show the 49 general missions — that's the bug Daniel saw in a seeded kolab.)
3. `GET /me/missions` already filters to **general** (live `trigger_action`), so it
   correctly excludes event missions. No change there.
4. Don't re-seed demo event missions; the picker is simply empty until real ones exist.

---

## RESOLVED (2026-06-27, tasks A1–A7, PR #49)

The items below were open questions/risks when this plan was written; they are now
resolved in code. Cross-reference: `ROLES-AND-PERMISSIONS.md §7.4`, `ROLES-BACKEND-DB-MAP.md §11.1`.

- **Event/general mission coexistence (the addendum above).** Resolved exactly as
  specced: `trigger_action IS NULL` = event challenge, `trigger_action IS NOT NULL` =
  general mission. The separation is enforced in **three** places, not just the picker
  called out above — `SystemChallengeController`, `Admin\ChallengeDefaultsController`,
  and (new) `ChallengeService::listForEvent()` all filter `trigger_action IS NULL`.
- **The curated v1 app-visible set.** Rather than surfacing all ~45 seeded missions,
  a new `challenges.app_visible` boolean (default `false`) gates what `/me/missions`
  returns. Exactly 18 missions are `app_visible = true` for v1 launch — 5 attendee,
  7 business, 6 community — each verified to use a trigger in `MissionTrigger::isLive()`'s
  true set. The rest of the seeded missions stay in the DB (admin-manageable) but are
  held back from the app until their trigger source is wired or product flips the flag.
- **Concurrency / wallet / isLive fixes (A3, A3b, A4, A5).** The event-picker filters
  (A3) and the `listForEvent()` leak fix (A3b) closed the gap where event surfaces could
  show general missions. `MissionService`'s progress upsert is now atomic (A4, avoids a
  race on concurrent trigger fires for the same period_key). `/me/missions` now delegates
  through the wallet service and gates on `MissionTrigger::isLive()` (A5) so a mission
  whose trigger isn't wired yet can never appear half-progressed or stuck.
