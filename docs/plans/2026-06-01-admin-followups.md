# Admin follow-ups — Lifecycle filter audit + Gamification gap

**Date:** 2026-06-01
**Status:** Plan (no code yet). Two distinct threads bundled because both surfaced together on a single review of `/admin`.

---

## Thread 1 — Lifecycle filter audit on `/admin/kolabs`

### What you observed
- Filter dropdown options like "Matched" don't surface kolabs you'd expect to see there. Specifically, a kolab that is already "Active" (live collaboration) does not appear under "Matched".
- "More errors" — i.e. the buckets don't feel right against the real data.

### What the code actually does (recap)
`App\Services\Admin\KolabLifecycleService::derive()` assigns **exactly one** lifecycle per kolab, in this priority order:

| Order | Lifecycle | Condition |
|---|---|---|
| 1 | Draft | `kolabs.status = draft` |
| 2 | Scheduled / Active / Completed / Cancelled | a `collaborations` row exists for this kolab.id → mirror its `status` |
| 3 | Matched | ≥1 accepted application AND **no** collaboration row yet |
| 4 | Closed | `kolabs.status = closed` AND no collaboration exists |
| 5 | Receiving applicants | published, ≥1 pending application, no accepted yet |
| 6 | Open | published, nothing yet |

`applyFilter()` mirrors this — each filter option matches **only** the kolabs whose `derive()` would return exactly that lifecycle.

### Root cause of the surprise
The taxonomy is correct, but the **labels and the filter granularity are misleading**.

- **"Matched" today means a transient edge state** — accepted application exists, but the collaboration row hasn't been created yet. In practice this is near-empty (acceptance creates the collaboration immediately), so the filter looks broken.
- What you actually want when you filter "Matched" is "a match was confirmed at some point" — which includes Scheduled, Active, Completed, and Cancelled. None of those show under the current "Matched" filter.
- There's no composite filter for "any kolab that has progressed past Receiving" — you have to click each lifecycle in turn.

### Proposed fix
A small, low-risk refactor of `applyFilter()` plus a label change. **No migration, no data change.**

1. Rename `Matched` → **`Pending match`** in the dropdown labels (the underlying constant stays `matched` so cross-references and tests don't break). This makes it clear it's the transient edge state.
2. Add a new composite filter option **`Has match`** that returns the union of Matched + Scheduled + Active + Completed (everything where an accepted application has produced a real match). Implemented as `whereExists(applications WHERE status = accepted)` — single subselect.
3. Add a second composite option **`Completed or done`** that returns Completed + Cancelled + Closed (post-mortem bucket).
4. Reorder the dropdown for legibility:
   ```
   All
   ──────────
   Draft
   Open
   Receiving applicants
   Pending match            ← was "Matched"
   ──────────
   Has match (any stage)   ← new composite
   Scheduled
   Active
   Completed
   ──────────
   Closed
   Cancelled
   Completed or done       ← new composite
   ```
5. **Verification step:** add a small admin diagnostic command `php artisan app:audit-kolab-lifecycles` that prints, for each lifecycle bucket, the count vs. what the live data implies. Run on prod once to confirm derive() matches your mental model before further changes. *(Optional but valuable; ~30 min.)*

### Secondary findings while auditing
- **System A opportunities are invisible on `/admin/kolabs`.** The admin Kolabs page lists only the `kolabs` table. Legacy opportunities created via the System A path (`OpportunityController` → `collab_opportunities` directly) don't appear, even if they have live applications. This is by design today, but if you see "missing kolabs" in the admin compared to what the mobile app shows, this is why. Resolving requires either listing both tables (messy) or completing the `LegacyOpportunityBridgeService` cleanup so System A is dead. **Not part of this PR.**
- **The bridge invariant (`kolab.id == collab_opportunity.id`) is required** for the filter to work. If you see a kolab in the admin list that should have a collab but doesn't, query `collab_opportunities` directly with that UUID to confirm the bridge ran. The bridge runs from `ApplicationController::store()` — application submission triggers it. A kolab that has somehow accumulated `applications` without ever going through `ApplicationController` (which shouldn't be possible via the public API) would have an inconsistent state. *Worth confirming on prod data.*

### Scope of the lifecycle fix
- `app/Services/Admin/KolabLifecycleService.php` — extend `applyFilter()` with two new lifecycle keys (`has_match`, `done`).
- `resources/views/admin/kolabs/index.blade.php` — relabel `Matched` and add the two composite options at sensible positions in the dropdown.
- `tests/Feature/Admin/KolabManagementTest.php` — add three tests (composite Has match, composite Done, Pending-match label).
- *(Optional)* `app/Console/Commands/AuditKolabLifecycles.php`.
- ROLES docs — none affected.

**Estimated effort:** ~1 hour with tests.

---

## Thread 2 — Gamification admin section is still missing

### Current state
- The admin sidebar has **CONTENT** (Kolabs) and **INSIGHTS** (Statistics) headers. There is no **GAMIFICATION** section.
- The gamification system is entirely driven by code-level constants + the seeded `challenges`, `badges`, and `earned_badges` tables. None of these are admin-editable.
- The mobile app hardcodes XP ladders, per-action XP amounts, badge requirements, referral milestone, and withdrawal economics. The backend awards points without exposing the rates → silent drift risk.

### Two prompts already exist for this work
We reviewed and flagged both during the iteration earlier today:

1. **Admin challenge catalogue + role-based defaults + per-collaboration bonuses** — manages the `challenges` table (per-challenge `audience`, `points`, the matrix that maps business/community types to default challenges, business-defined real-world bonuses per collaboration).
2. **Admin XP / rewards economy + `/gamification/config` read endpoint** — manages XP levels, per-action XP awards (the single source of truth that both writes `point_ledger` and powers the app's labels), badge requirements, and the referral + withdrawal economics. Read endpoint replaces the app's hardcoded constants.

Both prompts have **non-trivial open questions** flagged during the review. Before building, those need answers (one chat round).

### Open decisions blocking the gamification build

**From the challenges prompt:**
1. **Bonus storage** — option A (pivot, preserved-sync) or option B (separate `collaboration_challenge_bonuses` table)? I recommended B.
2. **Defaults slug format** — underscored (matches `*_profiles` columns) or hyphenated (matches lookup-table seeders)? I recommended underscore + a same-PR fix of the lookup seeders.
3. **Defaults source on business side** — `business_type` only, or include `categories[]`?
4. **Audience enforcement** — 422 or silent filter on a violation?
5. **Empty-selection allowed via sync?** (Today `min:1`.)

**From the XP economy prompt:**
6. **Badge system disambiguation** — option A (new `badge_definitions` table keyed by `GamificationBadgeSlug`, attendee `badges` table untouched), option B (unify both systems), or option C (admin-manage `GamificationBadgeSlug` only and punt attendee). I recommended A.
7. **Withdrawal rate canonical value** — the spec says **€0.25/point**, the backend code currently uses **€0.20/point** (with a hardcoded `375 points = €75` threshold). Confirm which is correct; the prompt's defaults would silently bump payouts by 25 % if not addressed.

### Suggested sequencing (4 PRs, each merge-independent)

I'd ship gamification in this order, smallest blast-radius first:

| PR | Scope | Why this order | Effort |
|---|---|---|---|
| **PR-1: Lifecycle filter fix** | Thread 1 above. | Quick win, surfaces no decisions. | ~1 h |
| **PR-2: XP earn rules + levels + `/gamification/config`** | XP economy prompt §1, §2, §5. Single read endpoint kills the app↔server drift. Drops `awardPoints`'s `$points` parameter in favour of a rule-table lookup. Includes `PointEventType::defaultPoints()` fallback. | Highest-impact, lowest-question-count. Drift is silent and current. | ~1 day |
| **PR-3: Reward economics + withdrawal wiring** | XP economy prompt §4. Resolves the €0.20 vs €0.25 disagreement; removes the hardcoded `375` / `0.20` literals in `GamificationController::withdrawal()`. Includes the `currency` / `withdrawal_threshold_cents` / `referral_cash_reward_cents` settings. | Needs Q7 answered (rate). | ~½ day |
| **PR-4: Admin challenge catalogue + audience + defaults seeding** | Challenges prompt §1–§3. Per-challenge `audience` column. Default matrix per business/community type. Hooks into `CollaborationService::createFromApplication` for idempotent seeding. Includes the slug-format reconciliation. | Needs Q1–Q5 answered. | ~1 day |
| **PR-5: Business bonus rewards on a challenge** | Challenges prompt §4. Option B (separate `collaboration_challenge_bonuses` table). Business-only policy. Includes the attendee-facing claim/redeem follow-ups *only if* attendee scope is confirmed (still `[VERIFY]` per the ROLES doc §7). | Cleanest as a follow-up. | ~½ day |
| **PR-6: Badge admin** | XP economy prompt §3. Per option A (`badge_definitions` table keyed by `GamificationBadgeSlug`). | Last because of Q6. | ~½ day |

**Total:** ~3.5–4 days of focused work spread across 6 PRs.

A new sidebar header **GAMIFICATION** is added in PR-2 (it's the first one that touches `/admin/*`); subsequent PRs slot into it.

---

## What I'd like from you before starting

A single round of answers, ideally short:

- **Lifecycle filter:** OK with the relabel + composite-filter approach? (or push back on the label changes)
- **Withdrawal rate (Q7):** €0.20/point (current code) or €0.25/point (spec doc § 3.5)?
- **Badge dual-system (Q6):** A (new table for `GamificationBadgeSlug`) — or you want option B / C?
- **Bonus storage (Q1):** A (pivot) or B (separate table)?
- **Slug format (Q2):** underscored everywhere?
- **The other small ones (Q3 / Q4 / Q5):** I can default to my recommendations if you don't have strong opinions.

Once those are in, I start in the order above. PR-1 (lifecycle fix) is independent and can go in parallel.

---

*Sister doc to:*
- *2026-05-31-admin-stats-dashboard.md* — stats infra this builds on
- *2026-06-01 feedback-gate plan (in PR #9 description)*
