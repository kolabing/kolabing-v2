# App Missions UI + read endpoint (Phase 4)

> Follow-up to the mission system (Phases 1-3, branch `feat/gamif-mission-phase1`).
> Phases 1-3 SEED missions + AUTO-AWARD them, but there is no read API and no
> user-facing screen — XP accrues silently. This plan surfaces missions to users.

## Problem
- No `GET /me/missions` endpoint: the app can't fetch a viewer's missions/progress.
- ~19 of 49 missions are seeded but **inert** (their trigger has no source yet — see
  Phase 3 report). Showing a user a mission that can never complete is bad UX.

## Backend — Phase 4 (read endpoint)
1. **`GET /api/v1/me/missions`** — returns the authenticated viewer's role-relevant
   missions with progress. Viewer-scoped: audience matches profile type
   (attendee↔attendee, business↔business, community↔community, `both`↔biz+comm).
2. **Hide inert missions:** only return missions whose `trigger_action` is in the
   LIVE set (the triggers wired in Phase 2/3). Implement as a single source of truth —
   e.g. `MissionTrigger::isLive()` or a `config('missions.live_triggers')` list — used
   by BOTH the endpoint filter and (later) any admin "live/inert" badge. As Phase 3+
   wires more triggers, add them to that list and the missions light up automatically.
   (Alternative: an `is_active` column toggled per mission — heavier; prefer the
   trigger-set approach so it stays in lockstep with what actually fires.)
3. **Response shape (per mission):** `id, slug, name, description, audience, category,
   points, difficulty, target_value, repeat_interval`, and the viewer's progress for
   the current period: `progress_count, completed, completed_at, period_key`. Resolve
   progress from `challenge_progress` for the viewer + the relevant `period_key`
   (reuse `MissionService`'s period_key logic so repeatable missions show the current
   window). Group/sort by `category` then `points`.
4. Tests: viewer sees only their audience's LIVE missions; progress reflects
   `challenge_progress`; completed missions show completed; inert missions excluded.

## App — Missions screen
1. **Entry point:** from the Profile / gamification area (e.g. a "Missions" row or a
   card on the dashboard showing "N/M completed"). One screen per role (the endpoint is
   already role-scoped).
2. **Screen:** missions grouped by category headers; each row = personalised category
   icon + title + short description + **progress bar** (`progress_count/target_value`,
   e.g. "3/5") + points chip + a completed check when done. Completed missions sink to
   the bottom or show a green state.
3. **Model + service:** a `Mission` model (parse the endpoint shape) + a provider
   fetching `GET /me/missions` with loading/empty/error states. Reuse design tokens +
   `CategoryIcon`; i18n en/es/ca for all labels (mission `name`/`description` come
   localized-or-raw from backend — decide: seed copy is EN today, so either localize in
   ARBs by slug or display backend copy; recommend backend copy for now + a later i18n
   pass).
4. No business logic in the app — display only over the engine that already awards.

## Sequencing
- Phase 4a: `GET /me/missions` + live-trigger filter + tests (backend, ~small).
- Phase 4b: app Missions screen + provider + entry point + i18n.
- Can ship with the Phase 1-3 PR or as a fast-follow. Recommended: include 4a in the
  mission PR (so the data is queryable) and 4b as the app PR, cross-linked.

## Open product decisions
- Whether to keep seeding the ~19 inert missions at all, or only seed live ones until
  their sources exist. (Filtering at the endpoint keeps them hidden either way.)
- Mission copy localization (EN-only seed today).
