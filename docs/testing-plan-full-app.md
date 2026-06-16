# Full App Manual / API Test Plan

> Created: 2026-06-13
>
> Step-by-step checklist for manually exercising every module of the Kolabing
> backend (mobile API `/api/v1/*` + admin panel `/admin/*`) via Postman/curl.
> Organized as phases that build on each other — later phases assume accounts
> and records created in earlier ones. Where a rule comes from
> [`docs/ROLES-AND-PERMISSIONS.md`](ROLES-AND-PERMISSIONS.md), the section is
> noted so a failure can be traced back to the spec.

---

## 0. Setup

1. Fresh DB: `php artisan migrate:fresh --seed`.
2. Confirm seeders ran: system challenges (35), `xp_earn_rules`, `xp_levels`,
   `reward_economics`, `community_types`/`business_types`, CRM (prod only —
   skip locally).
3. Create test accounts (use real Google/Apple test tokens, or the
   `auth/register/*` endpoints if email/password registration is supported in
   this env):
   - **B1** — Business, no subscription ("free business")
   - **B2** — Business, with active subscription (via admin grant in Phase 13,
     or Stripe test mode)
   - **C1** — Community leader (will create a community)
   - **C2** — Community member (will join C1's community)
   - **A1** — Attendee
   - **Admin** — maintainer admin user for `/admin/*` (seeded separately, not
     via the API)
4. For every protected request, set `Authorization: Bearer <sanctum_token>`
   obtained from `auth/google` (or `auth/login` if applicable) — store each
   account's token as you go.

---

## Phase 1 — Auth & Onboarding

1. `POST /api/v1/auth/register/business` → creates B1, returns token + profile
   with `user_type=business`.
2. `POST /api/v1/auth/register/community` → creates C1.
3. `POST /api/v1/auth/register/attendee` → creates A1.
4. `POST /api/v1/auth/google` with an existing linked account → logs in,
   returns same profile id (no duplicate created).
5. `POST /api/v1/auth/login`, `auth/refresh`, `auth/forgot-password`,
   `auth/reset-password` — happy path + invalid-credentials / expired-token
   failure cases.
6. `GET /api/v1/auth/me` (authed) → returns profile + `user_type`.
7. `PUT /api/v1/onboarding/business` as B1 → set business_type, city, etc.
   Confirm `user_type:business` middleware rejects this for C1/A1 (403).
8. `PUT /api/v1/onboarding/community` as C1 → set community_type, city.
   Confirm rejected for B1/A1.
9. `PUT /api/v1/onboarding/attendee` as A1 → confirm rejected for B1/C1.
10. `POST /api/v1/auth/logout` → token invalidated, subsequent authed call
    returns 401.

---

## Phase 2 — Profile, Lookups, Device Token

1. `GET /api/v1/me/profile` (each role) → includes subscription block for
   business, null/absent for community/attendee.
2. `PUT /api/v1/me/profile` → update display name, bio, handle, city,
   category. Verify `@handle` uniqueness validation.
3. `GET /api/v1/handle/available?handle=...` (public) — taken vs available.
4. `GET /api/v1/me/dashboard` — role-specific shape for B1, C1, A1.
5. `GET /api/v1/cities` (public), `POST /api/v1/cities/suggest` (authed).
6. `GET /api/v1/places/autocomplete`, `places/details`, `places/photo` —
   Google Places passthrough (per recent merge `e91dfc4`).
7. `GET /api/v1/lookup/business-types`, `lookup/community-types` (public) —
   confirm these now read from `business_types`/`community_types` admin
   tables (§5 of `admin-gamification-crm-types.md`), matching whatever is
   active/ordered in `/admin/types`.
8. `POST /api/v1/me/device-token` — register FCM token, idempotent on resend.
9. Gallery: `GET/POST me/gallery`, `DELETE me/gallery/{photo}`,
   `GET profiles/{profile}/gallery` (view another user's gallery).
10. Notification preferences: `GET/PUT me/notification-preferences`.
11. `DELETE /api/v1/me/account` — soft delete; confirm subsequent authed
    requests with the same token fail, and the row is soft-deleted not
    hard-deleted. **Do this on a throwaway account at the very end**, not on
    B1/C1/A1 you need for later phases.

---

## Phase 3 — Subscription / Paywall (critical — see `ROLES-AND-PERMISSIONS.md` §1–2, §8.4)

1. As **B1 (no subscription)**:
   - `GET /api/v1/me/subscription` → inactive/no subscription.
   - Browse `GET /api/v1/discovery/opportunities` and `GET /api/v1/opportunities`
     → **must work** (browsing is always free, §2 line 28).
   - `POST /api/v1/opportunities` (create) → **allowed** (creation by a
     business isn't the gate — only *collaboration creation* and *applying to
     a Kolab* are, confirm against current code which gate actually fires).
   - `POST /api/v1/opportunities/{opp}/applications` (apply to a Kolab as
     business) → **expect paywall/422 gate** for B1.
   - Attempt to create a collaboration as B1 → **expect paywall gate**.
   - Confirm B1 is NOT blocked from chat/notifications/profile/gallery/events
     — only the two gated actions.
2. Grant B2 a subscription (Phase 13 admin grant, or Stripe/Apple IAP test
   flow): `POST /api/v1/me/subscription/apple-verify` /
   `apple-restore` if testing iOS IAP.
3. As **B2 (subscribed)**:
   - Apply to a Kolab → succeeds.
   - Create a collaboration → succeeds.
4. Re-gate check: revoke B2's subscription via admin
   (`/admin/users/{id}/subscription/revoke`), then confirm:
   - B2 loses access to `GET /api/v1/collaborations` (ongoing access withdrawn,
     §2.8).
   - B2 can still `POST /collaborations/{id}/feedback` on a past collab
     (re-gate exemption, §2.8 line 175).
   - The **community counterparty is unaffected** — verify C1/C2 still see
     the shared collaboration/chat normally.
5. Confirm communities are **never** gated: as C1, create unlimited
   opportunities/Kolabs and apply freely — no paywall responses ever.

---

## Phase 4 — Opportunities & Kolabs Lifecycle

1. As B2 (subscribed) or C1 (always free): `POST /api/v1/opportunities`
   (draft) → `GET /api/v1/me/opportunities` shows it as `draft`.
2. `PUT /api/v1/opportunities/{id}` → edit while draft.
3. `POST /api/v1/opportunities/{id}/publish` → status `published`, now
   appears in `GET /api/v1/opportunities` (public list) and
   `GET /api/v1/discovery/opportunities`.
4. `GET /api/v1/opportunities/{id}` (public + owner view).
5. `POST /api/v1/opportunities/{id}/close` → status `closed`, drops from
   public listing.
6. `DELETE /api/v1/opportunities/{id}` on a draft → removed.
7. Repeat the analogous lifecycle for **Kolabs** (`/api/v1/kolabs*`):
   `store` → `GET kolabs/me` → `publish` → `GET kolabs` (public) →
   `GET kolabs/{id}` → `close` → `update` → `destroy`.
8. Cross-check: non-creator cannot `update`/`destroy`/`publish`/`close`
   someone else's opportunity/kolab (403 via policy).

---

## Phase 5 — Applications & Chat

1. As the counterpart role to the opportunity/kolab creator, apply:
   `POST /api/v1/opportunities/{opp}/applications` (paywall rules from
   Phase 3 apply if applicant is a business).
2. `GET /api/v1/opportunities/{opp}/applications` — only the creator can see
   the list (403 for others).
3. `GET /api/v1/me/applications` (applicant view) and
   `GET /api/v1/me/received-applications` (creator view).
4. `GET /api/v1/applications/{id}` — both parties can view, third parties
   cannot.
5. Chat on a pending application:
   - `POST /api/v1/applications/{id}/messages` from both sides.
   - `GET /api/v1/applications/{id}/messages` — ordering, pagination.
   - `POST /api/v1/applications/{id}/messages/read`,
     `GET /api/v1/me/unread-messages-count`.
   - `GET /api/v1/chats` (inbox) shows this thread;
     `GET /api/v1/chats/unread-count`.
6. `POST /api/v1/applications/{id}/decline` on a separate application →
   status `declined`, chat becomes read-only/unreachable for further
   messages (verify expected behavior).
7. `POST /api/v1/applications/{id}/withdraw` (by applicant) on another
   application.
8. `POST /api/v1/applications/{id}/accept` on the main one → creates a
   `collaboration` row (verify via `GET /api/v1/collaborations`).

---

## Phase 6 — Collaborations

Using the collaboration created by accepting an application in Phase 5:

1. `GET /api/v1/collaborations` (index for both parties) and
   `GET /api/v1/collaborations/{id}` (show).
2. `POST /api/v1/collaborations/{id}/activate` → status `active`.
3. `POST /api/v1/collaborations/{id}/qr-code` → generates a QR/token for
   in-person check-in.
4. Challenges on the collaboration:
   - `GET /api/v1/challenges/system` — list system challenges.
   - `PUT /api/v1/collaborations/{id}/challenges` — sync selected challenge
     ids (verify `ChallengeDefaultsService` seeded defaults if this is the
     first sync and zero challenges previously existed — §2 of
     `admin-gamification-crm-types.md`).
   - `POST /api/v1/collaborations/{id}/challenges` — create a custom
     (`is_system=false`) challenge scoped to this collaboration.
   - `PUT /api/v1/collaborations/{id}/challenges/{challenge}/bonus` —
     business sets a bonus (discount/free_item/free_service/custom); confirm
     only the **business participant** can set it, not the community.
   - `DELETE .../bonus` — remove it.
5. Completion gating (§ "Rich feedback" rule):
   - `POST /api/v1/collaborations/{id}/feedback` from **only one** party →
     `POST /api/v1/collaborations/{id}/complete` should **fail** (not both
     parties submitted feedback yet), unless
     `collaborations.complete_requires_feedback=false`.
   - `PUT /api/v1/collaborations/{id}/feedback` — edit before the partner
     submits; confirm it locks after the partner's row exists.
   - Submit feedback from the second party → XP awarded per party on
     submission (check `gamification/ledger` afterwards), then
     `POST /api/v1/collaborations/{id}/complete` succeeds → status
     `completed`.
6. Legacy `/review` mirror: `POST /api/v1/collaborations/{id}/review` on a
   *different* completed collaboration → confirm a stub `/feedback` row is
   written with `mirrored_from_review = true` and the collab can still reach
   `completed`.
7. `POST /api/v1/collaborations/{id}/cancel` on a third collaboration (still
   `scheduled`/`active`) → status `cancelled`.
8. Public profile views after completion:
   `GET /api/v1/profiles/{profile}/collaborations`,
   `GET /api/v1/profiles/{profile}/reviews`,
   `GET /api/v1/profiles/{profile}/game-card`.

---

## Phase 7 — Communities (members & tiers, NF-6)

1. As C1: `POST /api/v1/communities` → create a community. Confirm the
   **one-free-community-per-leader cap** (§8.4 line 258): a second
   `POST /api/v1/communities` by C1 returns `422 community_limit_reached`.
2. `GET /api/v1/me/communities`, `GET /api/v1/me/memberships`.
3. `GET /api/v1/communities/discover` (public-ish discovery list).
4. `GET /api/v1/communities/{id}` / `PATCH /api/v1/communities/{id}` (leader
   only — confirm non-leader gets 403).
5. Invite flow:
   - `GET /api/v1/communities/{id}/invite` → returns invite token/link
     (confirm `invite_token` column added in `2026_06_12_000001` migration is
     populated).
   - `POST /api/v1/communities/join/{token}` as C2 → joins via token.
6. Direct join + invite-only join requests:
   - `POST /api/v1/communities/{id}/join` as another test user (open join, if
     community is not invite-only).
   - For an invite-only community: `POST /api/v1/communities/{id}/join-requests`
     (C2 requests) → `GET .../join-requests` (leader sees pending) →
     `POST /api/v1/join-requests/{id}/approve` or `/decline`.
7. Tiers: `GET/POST /api/v1/communities/{id}/tiers`,
   `PATCH /api/v1/tiers/{id}`, `DELETE /api/v1/tiers/{id}` — leader-only CRUD,
   confirm member (C2) gets 403 on write but can read.
8. Members roster: `GET /api/v1/communities/{id}/members`,
   `POST .../members` (manual add), `PATCH .../members/{member}` (e.g.
   assign tier / role), `DELETE .../members/{member}` (remove). Confirm
   manage-gating (owner / `can_manage`) on writes.
9. Community chat: `POST /api/v1/communities/{id}/chats` (custom chat thread),
   `PATCH/DELETE /api/v1/chats/{thread}` (rename/delete, manager only),
   bans: `GET/POST /api/v1/chats/{thread}/bans`,
   `DELETE /api/v1/chats/{thread}/bans/{profile}`,
   `GET /api/v1/chats/{thread}/messages`,
   `POST /api/v1/chats/{thread}/messages`, `POST /api/v1/chats/{thread}/read`.
10. `GET /api/v1/communities/{id}/public-profile` — public view, no auth
    requirements beyond standard middleware.

---

## Phase 8 — Community Gamification: Goals, Rewards, Badges, Points (newly merged)

This is the bulk of the `e482195` merge — test thoroughly.

### Goals
1. As C1 (leader): `POST /api/v1/communities/{id}/goals` — create a goal
   (check `CommunityGoalEarnType` enum values accepted by
   `StoreCommunityGoalRequest`).
2. `GET /api/v1/communities/{id}/goals` — member (C2) can read; non-member
   cannot (or gets limited view — confirm intended visibility).
3. `PUT /api/v1/goals/{id}` — leader edits; C2 (member, non-manager) gets 403.
4. `DELETE /api/v1/goals/{id}` — leader only.

### Rewards (leader-defined, community-scoped — distinct from global partner rewards)
5. `POST /api/v1/communities/{id}/rewards` — create a reward (cost in points,
   stock/limits per `StoreCommunityRewardRequest`).
6. `GET /api/v1/communities/{id}/rewards` — list.
7. `PUT /api/v1/rewards/{id}` / `DELETE /api/v1/rewards/{id}` — leader-only.

### Badges
8. `POST /api/v1/communities/{id}/badges` — create, validate
   `CommunityBadgeCriteriaType` enum (e.g. points threshold, event count).
9. `GET /api/v1/communities/{id}/badges` — list.
10. `PUT /api/v1/badges/{id}` / `DELETE /api/v1/badges/{id}` — leader-only.
11. Trigger the criteria as C2 (e.g. earn enough community points — see
    Points below) and confirm `CommunityBadgeService` auto-awards the badge
    — check via `community_badge_awards` / rewards-hub / leaderboard.

### Points
12. Generate `CommunityPointSource` events for C2 (e.g. completing a kolab,
    attending an event, posting UGC — whatever sources exist per
    `CommunityPointsService`) and confirm a `community_point_ledger` entry is
    written and `community_points.total` increments for the
    `(community_id, profile_id)` pair.

### Rewards Hub & Redemption
13. `GET /api/v1/communities/{id}/rewards-hub` as C2 — shows available
    rewards, C2's current points balance, and progress toward goals/badges.
14. `POST /api/v1/communities/{id}/rewards/{reward}/redeem` as C2:
    - Success case: C2 has enough points → points debited, a
      `reward_redemptions` row created with appropriate
      `RewardRedemptionStatus`.
    - Failure case: insufficient points → 422, no row created, balance
      unchanged.
    - Stock/limit exhausted (if reward has a redemption cap) → 422.

### Leaderboards
15. `GET /api/v1/communities/{id}/leaderboard` — per-community leaderboard
    showing tier + badge_count + points per row, ordered correctly.
16. `GET /api/v1/leaderboard/global` (with and without `?community_id=`) —
    global vs chapter-scoped view.
17. `GET /api/v1/me/rewards-overview` — combines global XP + partner rewards
    + per-community summaries for the logged-in user (C2 and B2/C1 too).

---

## Phase 9 — Events (past events + discovery + RSVP)

1. `POST /api/v1/events` — create an event (one-off and a recurring series if
   supported).
2. `GET /api/v1/events` (own / `?profile_id=`), `GET /api/v1/events/{id}`.
3. `PUT /api/v1/events/{id}` — update; `DELETE /api/v1/events/{id}` with
   `scope=this|following|series` on a recurring event — verify each scope
   only deletes the intended occurrences.
4. `POST /api/v1/event-series/{series}/extend` — rolling-window extension
   (NF-16).
5. `GET /api/v1/events/discover` — discovery feed (route registered before
   `{event}` — confirm `discover` isn't swallowed as an id).
6. Photos: `POST /api/v1/events/{id}/photos`,
   `DELETE /api/v1/events/{id}/photos/{photo}` — creator/`can_manage` only.
   Cross-check `GalleryController`/`EventPhotoController` sync (recent
   `CommunityPhotoSyncTest`).
7. RSVP / sign-up:
   - `POST /api/v1/events/{id}/signup`, `GET /api/v1/events/{id}/signups`,
     `DELETE /api/v1/events/{id}/signup` (cancel RSVP).
   - Confirm a **non-member can sign up for a PUBLIC event** (regression from
     `eed7dd4`).
8. `POST /api/v1/events/{id}/chat` — event chat message.

---

## Phase 10 — Event-Level Gamification (check-in, challenges, badges, wallet)

1. **Check-in:** `POST /api/v1/events/{id}/generate-qr` (organizer) →
   `POST /api/v1/checkin` (attendee scans) → `GET /api/v1/events/{id}/checkins`
   shows the attendee.
2. **Challenges:**
   - `GET /api/v1/events/{id}/challenges` — system + custom for this event.
   - `POST /api/v1/events/{id}/challenges` — custom challenge for the event.
   - `PUT/DELETE /api/v1/challenges/{id}` — edit/remove custom challenge.
3. **Challenge completion (peer-to-peer):**
   - `POST /api/v1/challenges/initiate` — checked-in challenger initiates
     against a checked-in verifier → `pending` completion.
   - `POST /api/v1/challenge-completions/{id}/verify` — verifier confirms →
     `verified`, `points_earned = challenge.points`, attendee `total_points`
     / `total_challenges_completed` bump, badge check fires.
   - `POST /api/v1/challenge-completions/{id}/reject` on another pending one
     → `rejected`, no points.
   - Confirm one-time-per-(challenge, event, challenger, verifier) constraint
     — repeat the same pairing → expect rejection/no-op.
   - Confirm `event.max_challenges_per_attendee` cap is enforced.
   - `GET /api/v1/me/challenge-completions`.
4. **Event leaderboard:** `GET /api/v1/events/{id}/leaderboard`.
5. **Rewards (organizer-managed, event-scoped):**
   `GET/POST /api/v1/events/{id}/rewards`,
   `PUT/DELETE /api/v1/event-rewards/{id}`.
6. **Spin the wheel:** `POST /api/v1/rewards/spin` — only after a verified
   challenge completion; confirm rejected if no eligible completion exists.
7. **Reward wallet:** `GET /api/v1/me/rewards` (claims list),
   `POST /api/v1/reward-claims/{claim}/generate-redeem-qr`,
   `POST /api/v1/reward-claims/confirm-redeem` (organizer scans/confirms).
8. **Stats & badges:** `GET /api/v1/me/gamification-stats`,
   `GET /api/v1/profiles/{profile}/game-card` (public),
   `GET /api/v1/badges` (system catalogue), `GET /api/v1/me/badges` (mine).
9. **Wallet/ledger/config:**
   - `GET /api/v1/gamification/wallet`, `GET /api/v1/gamification/ledger` —
     confirm `point_ledger` entries from challenge completions, feedback XP,
     etc. all appear with correct `PointEventType`.
   - `GET /api/v1/gamification/badges`.
   - `GET /api/v1/gamification/config` — levels + earn-rule labels match
     `/admin/gamification/levels` and `/admin/gamification/earn-rules`
     (cache key `gamification.config`; bust by editing admin then re-fetch).
   - `GET /api/v1/gamification/referral-code`,
     `POST /api/v1/referrals/validate` (other account uses the code).
   - `POST /api/v1/gamification/withdrawal` — happy path (balance ≥
     `withdrawal_threshold_cents`) and failure (below threshold).

---

## Phase 11 — Friends (NF-17)

1. `POST /api/v1/friends/{profile}` (A1 → C2) → pending request.
2. `GET /api/v1/me/friend-requests` as C2 → sees A1's request.
3. `POST /api/v1/friends/{profile}/accept` as C2 → both now friends.
4. `GET /api/v1/me/friends` for both A1 and C2.
5. Reverse-pending auto-accept: A1 sends to a 3rd user who already sent A1 a
   pending request → auto-accepts instead of creating a duplicate.
6. `POST /api/v1/friends/{profile}/decline` on a separate pending request.
7. `DELETE /api/v1/friends/{profile}` — remove an accepted friend or cancel an
   outgoing request.

---

## Phase 12 — Notifications & Uploads

1. Trigger a few notification-producing actions from earlier phases (new
   application, accepted application, new chat message, badge awarded).
2. `GET /api/v1/me/notifications`, `GET /api/v1/me/notifications/unread-count`.
3. `POST /api/v1/me/notifications/{id}/read`, `POST /api/v1/me/notifications/read-all`.
4. `POST /api/v1/uploads` — generic file upload (image for profile/gallery)
   returns a usable URL; confirm size/type validation.

---

## Phase 13 — Admin Panel (`/admin/*`, maintainer-only)

1. `GET /admin/login` (guest) → `POST /admin/login` with maintainer creds →
   redirected to `/admin` dashboard. Confirm non-maintainer admin users (if
   any tier exists) are rejected by the `maintainer` middleware.
2. **Users:** `/admin/users` index, `create`/`store` a managed user,
   `edit`/`update`, `destroy`.
   - `/admin/users/{profile}/subscription/grant` — grants 12mo
     `source=maintainer` subscription to a business profile (B2). Confirm
     rejected for non-business profiles (§ line 231).
   - `/admin/users/{profile}/subscription/revoke` — flips to
     `status=inactive, cancel_at_period_end=true` (used in Phase 3 step 4).
3. **Kolabs:** `/admin/kolabs` index/edit/update/destroy.
   - `/admin/kolabs/{kolab}/collaboration/cancel` and `/complete` —
     **force-complete bypasses the feedback gate**, persists
     `completion_reason`, stamps `completed_at`,
     `completed_by_profile_id = null`, **no XP awarded** (§ line 110). Verify
     all of these on a collaboration that has only one-sided/no feedback.
4. **Stats:** `/admin/stats` — sanity-check aggregate numbers reflect data
   created in earlier phases (and that mirrored `/review` rows are excluded
   from rich aggregates, per §176).
5. **CRM:** `/admin/crm` (tabs: business/community/ambassador), create/edit/
   destroy a `crm_accounts` row, `POST /admin/crm/columns` (per-admin column
   prefs), confirm `recalculate()` score updates on save and
   `syncNextActionTask()` creates/updates a `crm_tasks` row.
6. **Tasks:** `/admin/tasks` index (default `status=active`), filters by
   assignee/area/subarea/status, create/edit/destroy, overdue flag (‼️) on a
   task with a past `due_on`.
7. **Types:** `/admin/types` (community/business tabs) — create, edit,
   reorder (drag/`reorder` endpoint), toggle `is_active`, `destroy`:
   - Unused type → hard delete.
   - In-use type (referenced by a `business_profiles.business_type` or
     `communities.type` from earlier phases) → soft "retire"
     (`is_active=false`), never hard-deleted.
   - After changing types here, re-check `GET /api/v1/lookup/*` reflects the
     change (Phase 2 step 7).
8. **Gamification admin (`/admin/gamification/*`):**
   - `/overview`, `/leaderboards/global`, `/leaderboards/communities` and
     `/leaderboards/communities/{community}` — read-only, cross-check against
     Phase 8/10 leaderboard data.
   - **Partner rewards** (`/partner-rewards`): full CRUD —
     create/edit/update/destroy a global partner reward, then confirm it
     shows up in `GET /api/v1/me/rewards-overview` (Phase 8 step 17) for
     eligible users.
   - **Challenge defaults** (`/challenges/defaults`): edit the matrix for one
     business_type and one community_type row, then create a *new*
     collaboration between profiles of those types and confirm
     `ChallengeDefaultsService::seedForCollaboration()` seeds the expected
     challenges (only when the collab starts with zero challenges).
   - **Challenges** (`/challenges`): index (only `is_system=true AND
     event_id IS NULL`), create/edit/destroy a system challenge —
     `store` always forces `is_system=true`.
   - **Badges** (`/badges`): edit a regular badge and a `system-b/{slug}`
     badge via the separate edit/update routes.
   - **Earn rules** (`/earn-rules`): edit-only — `event_type` read-only,
     change `points`/`label`/`is_active`/`position`, confirm
     `gamification.config` cache busts and `GET /api/v1/gamification/config`
     reflects the new value.
   - **Levels** (`/levels`): edit-only — `number` read-only, validate
     `XpLevelService::validateLadder()` rejects a non-contiguous or
     multiple-open-ended-tier edit (expect error + note the known quirk that
     the invalid row may persist before the exception, per the doc's "Known
     quirk").
   - **Economics** (`/economics`): update `referral_goal`,
     `referral_cash_reward_cents`, `euro_cents_per_point`,
     `withdrawal_threshold_cents`, `currency`; confirm the cost-impact preview
     updates and the 1h cache busts on save.

---

## Phase 14 — Cross-Cutting Role/Permission Regression Pass

Run through this checklist using accounts from earlier phases — these are the
"most regressions come from applying one role's rules to the other" cases
called out in `ROLES-AND-PERMISSIONS.md`:

1. A **free business (B1)** can: register, onboard, browse Explore/discovery,
   view profiles, create opportunities/kolabs, use chat on *existing*
   accepted applications, manage gallery/events/notifications — **cannot**:
   apply to a Kolab, create a collaboration.
2. A **community (C1/C2)** is never blocked from anything — re-run the
   create/apply/chat/community-gamification flows and confirm zero 402/422
   paywall-style responses anywhere.
3. A **lapsed business (B2 after revoke)**: loses `/collaborations` index +
   chat on those collabs, but keeps `/collaborations/{id}/feedback` access on
   already-existing collabs; community counterpart unaffected.
4. **Maintainer-granted subscription** behaves identically to a paid one for
   gating purposes (re-run Phase 3 step 3 with the granted B2).
5. **Second community creation** by the same leader → `422
   community_limit_reached` (Phase 7 step 1) — confirm the error code/shape
   matches what the mobile app expects.
6. Spot-check that none of the new community-gamification endpoints
   (Phase 8) accidentally apply business-paywall logic to a community leader
   or member — all should be free regardless of `user_type`.

---

## Phase 15 — Final Smoke Pass

1. Run the full automated suite: `php artisan test --compact` — should be
   green before/after this manual pass (catches anything the manual pass
   missed and confirms no regressions from data created during testing).
2. Re-run `vendor/bin/pint --dirty` if any code was touched while
   investigating failures.
3. File any bugs found as entries in `docs/BACKLOG.md` or
   `.agent/sop/` per project convention, referencing the phase/step number
   from this plan.
