# My Test Plan — Gamification Focus

> Subset of [`testing-plan-full-app.md`](testing-plan-full-app.md). Covers only
> the prerequisites needed to exercise gamification, plus all gamification
> surfaces (community gamification, event gamification, gamification admin).
> Everything else is left for coworkers (see "Hand-off" at the bottom).

---

## Part 1 — Prerequisites (minimal setup to get gamification-ready data)

Goal: end up with one community (with a member), one event (with check-ins
from 2+ attendees), and correct `business_type`/`community_type` values, since
challenge defaults and badge criteria key off these.

1. **Accounts** (Phase 0):
   - **C1** — community leader
   - **C2** — community member
   - **A1** — attendee (will double as a second event participant)
   - **B1** — business (only needed if you also want to test collaboration
     challenges/bonuses — optional, see Part 2 note)
   - **Admin** — maintainer login for `/admin`

2. **Auth + onboarding** (Phase 1, steps 2-3, 8-9 only):
   - Register C1, C2, A1 (and B1 if used).
   - `PUT /api/v1/onboarding/community` for C1 and C2 — set a real
     `community_type` (this feeds **Challenge Defaults**).
   - `PUT /api/v1/onboarding/attendee` for A1.
   - If using B1: `PUT /api/v1/onboarding/business` with a real
     `business_type`.

3. **Community setup** (Phase 7, minimal):
   - `POST /api/v1/communities` as C1 → create the community.
   - `GET /api/v1/communities/{id}/invite` → get invite token.
   - `POST /api/v1/communities/join/{token}` as C2 → C2 joins.
   - `POST /api/v1/communities/{id}/tiers` as C1 → create at least one tier
     (needed for the per-community leaderboard's tier column).

4. **Event setup** (Phase 9, minimal):
   - `POST /api/v1/events` as C1 (or B1) → create one event.
   - `POST /api/v1/events/{id}/signup` as C2 and A1 → both RSVP.
   - `POST /api/v1/events/{id}/generate-qr` (organizer) →
     `POST /api/v1/checkin` for both C2 and A1 (need **2 checked-in
     attendees** to test peer-to-peer challenge completion in Part 2).

That's the minimum data set. Skip everything else from Phases 1/7/9 (chat,
photos, recurring series, public profile views, etc.) — coworkers will cover
those.

---

## Part 2 — Gamification Testing (your focus)

### A. Community Gamification (Phase 8, full)

1. **Goals** — `POST/GET/PUT/DELETE` on
   `/api/v1/communities/{id}/goals` and `/api/v1/goals/{id}` as C1; confirm
   C2 can read but not write.
2. **Rewards** — `POST/GET/PUT/DELETE` on
   `/api/v1/communities/{id}/rewards` and `/api/v1/rewards/{id}`.
3. **Badges** — `POST/GET/PUT/DELETE` on
   `/api/v1/communities/{id}/badges` and `/api/v1/badges/{id}`; validate
   `CommunityBadgeCriteriaType` enum values are accepted/rejected correctly.
4. **Points** — drive a `CommunityPointSource` event for C2 (e.g. the event
   check-in/RSVP from Part 1, or completing a challenge in section B below)
   and confirm `community_point_ledger` + `community_points.total` update.
5. **Badge auto-award** — once C2's points cross the threshold set in step 3,
   confirm `CommunityBadgeService` awards the badge automatically (check
   `community_badge_awards`, rewards-hub, and leaderboard).
6. **Rewards Hub & Redemption**:
   - `GET /api/v1/communities/{id}/rewards-hub` as C2 — verify balance, goal
     progress, available rewards.
   - `POST /api/v1/communities/{id}/rewards/{reward}/redeem` as C2 — success
     case (enough points), failure case (insufficient points → 422), and
     stock/limit-exhausted case if applicable.
7. **Leaderboards**:
   - `GET /api/v1/communities/{id}/leaderboard` — tier + badge_count + points
     per row.
   - `GET /api/v1/leaderboard/global` with and without `?community_id=`.
   - `GET /api/v1/me/rewards-overview` for C2 (and C1) — global XP + partner
     rewards + per-community summary.

### B. Event-Level Gamification (Phase 10, full)

1. **Challenges**:
   - `GET /api/v1/events/{id}/challenges` — system + custom list.
   - `POST /api/v1/events/{id}/challenges` — create a custom event challenge.
   - `PUT /DELETE /api/v1/challenges/{id}` on the custom one.
2. **Challenge completion (peer-to-peer)** — using checked-in C2 and A1 from
   Part 1:
   - `POST /api/v1/challenges/initiate` — C2 initiates against A1 →
     `pending`.
   - `POST /api/v1/challenge-completions/{id}/verify` — A1 verifies →
     `verified`, points awarded, attendee totals bump, badge check fires
     (ties back to section A.5).
   - `POST /api/v1/challenge-completions/{id}/reject` on a second pending one
     → `rejected`, no points.
   - Repeat the same `(challenge, event, challenger, verifier)` pairing →
     confirm rejected/no-op (uniqueness constraint).
   - Confirm `event.max_challenges_per_attendee` cap.
   - `GET /api/v1/me/challenge-completions`.
3. **Event leaderboard** — `GET /api/v1/events/{id}/leaderboard`.
4. **Event rewards (organizer-managed)** —
   `GET/POST /api/v1/events/{id}/rewards`,
   `PUT/DELETE /api/v1/event-rewards/{id}`.
5. **Spin the wheel** — `POST /api/v1/rewards/spin` after C2's verified
   completion; confirm rejected without one.
6. **Reward wallet** — `GET /api/v1/me/rewards`,
   `POST /api/v1/reward-claims/{claim}/generate-redeem-qr`,
   `POST /api/v1/reward-claims/confirm-redeem`.
7. **Stats & badges** — `GET /api/v1/me/gamification-stats`,
   `GET /api/v1/profiles/{profile}/game-card`, `GET /api/v1/badges`,
   `GET /api/v1/me/badges`.
8. **Wallet / ledger / config**:
   - `GET /api/v1/gamification/wallet`, `GET /api/v1/gamification/ledger` —
     confirm entries from challenge completions appear with correct
     `PointEventType`.
   - `GET /api/v1/gamification/badges`.
   - `GET /api/v1/gamification/config`.
   - `GET /api/v1/gamification/referral-code`,
     `POST /api/v1/referrals/validate`.
   - `POST /api/v1/gamification/withdrawal` — happy path and below-threshold
     failure.

### C. Gamification Admin Panel (Phase 13, gamification subset only)

1. `/admin/gamification/overview`, `/leaderboards/global`,
   `/leaderboards/communities[/{community}]` — cross-check against A.7/B.3
   data.
2. **Partner rewards** (`/partner-rewards`) — full CRUD on a global reward,
   then confirm it appears in `GET /api/v1/me/rewards-overview` (A.7).
3. **Challenge defaults** (`/challenges/defaults`) — edit the matrix row for
   C1's `community_type` (set in Part 1 step 2), then check that a *new*
   community/collaboration of that type seeds the expected default
   challenges (only fires when zero challenges exist yet).
4. **Challenges** (`/challenges`) — index, create/edit/destroy a system
   challenge (`is_system=true` forced on store).
5. **Badges** (`/badges`) — edit a regular badge and a `system-b/{slug}`
   badge.
6. **Earn rules** (`/earn-rules`) — edit `points`/`label`/`is_active`, then
   re-fetch `GET /api/v1/gamification/config` (B.8) to confirm the cache
   busts and the new value shows up.
7. **Levels** (`/levels`) — edit a level; try an edit that breaks
   `XpLevelService::validateLadder()` (non-contiguous bands or two
   open-ended tiers) and confirm it's rejected (note the known
   save-before-validate quirk).
8. **Economics** (`/economics`) — update `referral_goal`,
   `referral_cash_reward_cents`, `euro_cents_per_point`,
   `withdrawal_threshold_cents`, `currency`; confirm the cost-impact preview
   updates and `POST /api/v1/gamification/withdrawal` (B.8) reflects the new
   threshold.

### Optional — Collaboration Challenges/Bonuses (Phase 6, partial)

Only do this if a coworker has already gotten an **accepted application →
collaboration** ready on B1 (this requires the paywall/subscription flow from
Phase 3, which is out of scope for you). If available:

- `PUT /api/v1/collaborations/{id}/challenges` — sync challenges (verify
  `ChallengeDefaultsService` seeds defaults using the matrix from C.3).
- `POST /api/v1/collaborations/{id}/challenges` — custom collaboration
  challenge.
- `PUT/DELETE /api/v1/collaborations/{id}/challenges/{challenge}/bonus` —
  business-only bonus set/remove.

If no collaboration is available yet, skip this section entirely — it's
covered by the "Marketplace, Paywall & Collaborations" track.

---

## Hand-off — Everything else (for coworkers)

Not covered above, per the full plan's phase numbers:

- **Phase 1** — remaining auth/business onboarding steps (1, 4-7, 10)
- **Phase 2** — Profile, lookups, gallery, device token
- **Phase 3** — Subscription / Paywall
- **Phase 4** — Opportunities & Kolabs lifecycle
- **Phase 5** — Applications & Chat
- **Phase 6** — Collaborations (everything except the optional challenges/
  bonus section above)
- **Phase 7** — remaining community steps (discovery, public-profile,
  join-by-open-join, join-requests, member roster CRUD, community chat/bans)
- **Phase 9** — remaining event steps (recurring series scopes, photos, chat,
  public-event-signup regression)
- **Phase 11** — Friends
- **Phase 12** — Notifications & uploads
- **Phase 13** — remaining admin: Users/subscriptions, Kolabs force-complete,
  Stats, CRM, Tasks, Types
- **Phase 14** — cross-cutting role/permission regression pass
- **Phase 15** — final smoke pass (`php artisan test --compact`, Pint)
