# Community Members & Tiers — Web Panel (Community Hub)

> Design spec. Date: 2026-08-19. Feature: **BE-NF-34**.
> Governed by `docs/ROLES-AND-PERMISSIONS.md` §8 and `docs/ROLES-BACKEND-DB-MAP.md` §12.
> **§8.4 is absolute: this surface is NEVER paywalled.** No code path in this feature
> may call `Profile::hasActiveSubscription()`.

---

## 1. Why this exists

NF-6 Phase 1 shipped the whole *mechanism* of community membership on the backend —
`communities`, `community_tiers`, `community_members`, `community_join_requests`,
`community_points` + `community_point_ledger`, `community_goals`, `community_rewards`,
`community_badges`, `event_checkins`, `events.community_id`, nightly tier evaluation.

None of it is reachable by a human. Mobile has not shipped the Community tab, and the
web app (`app.kolabing.com`) has **zero** community pages. A Community Leader today
cannot see who is in their community, cannot promote anyone, cannot approve a join
request, and cannot get a new member in.

Four concrete defects block "manage members and drive the data":

| # | Defect | Evidence |
|---|--------|----------|
| D1 | **Roster is a dumb list.** No search, no status filter, no tier filter, no sort. `CommunityMemberService::roster()` is `->orderBy('created_at')->paginate()`. A 120-member roster is unusable. | `app/Services/CommunityMemberService.php` |
| D2 | **Removed members leak into the roster.** `roster()` does not filter `status`, so soft-removed rows render as members. | same |
| D3 | **No way in for a non-user.** `POST /communities/{id}/members` returns `404 profile_not_found` unless the person already has a Kolabing account. A leader's real roster lives in a spreadsheet or WhatsApp group. | `CommunityMemberController@store` |
| D4 | **Every invite link ever shared is dead.** `config('communities.invite_base_url')` = `https://kolabing.com/c` and `Community::inviteUrl()` emits `/c/{slug}`, but **no `/c/{slug}` route exists in `routes/web.php`**. | verified — `grep` returns nothing |

And the roster payload carries no engagement signal at all (`CommunityMemberResource`
emits id/tier/can_manage/status/joined_at/name/avatar), so there is nothing to *drive*.

## 2. The thesis

**The roster is the product.** Everything else — tiers, points, goals, rewards, badges —
is decoration on a roster that must first be findable, sortable, and truthful.

So the design does four things, in this order of importance:

1. **Enrich** the roster with the engagement data the DB already holds (points, events
   attended, last activity, tenure) so a leader can see *who matters* at a glance.
2. **Open two inlets** so membership data can actually flow in: pending **email
   invitations** (converts on signup) and a live **`/c/{slug}` join landing page**.
3. **Aggregate** it into a community health header so the leader sees the trend, not
   just the list.
4. **Expose the leader's economy** (tiers, goals, rewards, badges, leaderboard) so the
   levers that move the data are one click from the data itself.

## 3. Scope decisions (locked)

| Decision | Choice | Note |
|---|---|---|
| Release scope | **Full Community Hub** | 7 pages, not roster-only |
| Non-user invites | **Both** | pending email invitations **and** the `/c/{slug}` link |
| CSV import/export | **Out of scope** | deferred to a follow-up |
| Member-side web | **Landing + join with an existing account** | **No attendee registration on web.** A visitor with no account sees the community, is offered sign-in, and is pointed at the app. An emailed invitation stays `pending` and is claimed when they eventually register (anywhere). |

**Access model (§8.1 / §8.3 D1):** the Hub is gated on **owner OR `can_manage`**, never on
`user_type`. `can_manage` holders are `attendee` accounts (D1 decouples tier from admin
power), and they must be able to run the panel. The sidebar entry appears when the
signed-in profile owns or can-manage at least one community.

**Multi-community:** the free cap is 1 (`communities.max_free_communities`), but a
`can_manage` grant can span other people's communities, so the Hub carries a **community
switcher**. Selection persists in `localStorage`.

---

## 4. Backend

### 4.1 Roster query upgrade — `GET /communities/{community}/members`

Fixes D1 + D2. Additive query params; existing callers (mobile, when it ships) keep
working because every param is optional and the response keys are only *added* to.

```
?search=      name / email / @handle (case-insensitive partial)
?status=      active | inactive | removed | all      (default: active,inactive)
?tier_id=     uuid   (also accepts `none` for members with no tier)
?can_manage=  1 | 0
?sort=        joined_at | name | points | events_attended | last_active_at | tier
?direction=   asc | desc                              (default: desc for metrics, asc for name/joined_at)
?limit=       max 100 (unchanged)
```

**Default `status` deliberately excludes `removed`** — this is the D2 fix and is a
behaviour change to an existing endpoint. It is the *correct* behaviour (a removed
member is not a member) and `?status=all` restores the old set for anyone who wants it.
Documented in the backend map §12.

Response gains, per member:

```json
{
  "email": "…", "handle": "@…",
  "points": 340,
  "events_attended": 7,
  "last_active_at": "2026-08-14T…",
  "tenure_days": 96
}
```

**No N+1.** `BACKLOG.md` BE-NF-15 explicitly calls out the O(N)-per-row pattern in this
codebase. The four metrics are resolved with **grouped subqueries / left joins on the
paginated page's profile ids**, computed once per page, then stitched onto the models as
transient attributes — the same "preloaded-attribute fast path" `CommunityResource`
already uses (`hasPreloaded()` / `preloaded()`). `CommunityMemberResource` follows that
pattern exactly, falling back to `null` when a caller did not preload (so mobile's
existing calls are unchanged in cost).

Definitions:
- `points` — `community_points.points` for (community, profile), default 0.
- `events_attended` — `event_checkins` joined to `events` where `events.community_id = ?`.
  This is the same definition §8.6 gives the `events_attended` tier rule; it must not
  drift from `TierAssignmentService`.
- `last_active_at` — `MAX(created_at)` over `community_point_ledger` for the pair,
  coalesced with `joined_at`. Points are written on check-in, goal completion, challenge
  verification and redemption, so the ledger is the community's activity spine.
- `tenure_days` — `now()->diffInDays(joined_at)`.

`search` matches `profiles.email`, `profiles.handle`, and the display name on whichever
extended profile row exists (attendee / business / community) — mirroring
`CommunityMemberResource::profileDisplayName()`.

### 4.2 Community stats — `GET /communities/{community}/stats` (new)

Manage-gated. One request, one card strip. Not a general analytics engine.

```json
{ "data": {
  "members": { "total": 128, "active": 121, "inactive": 3, "removed": 4,
               "new_this_month": 9, "dormant_30d": 22 },
  "pending": { "join_requests": 3, "invitations": 11 },
  "tiers": [ { "tier_id": "…", "name": "Active", "color": "#…", "rank": 2, "member_count": 74 } ],
  "engagement": { "points_issued_30d": 4210, "checkins_30d": 96,
                  "events_30d": 4, "attendance_rate_30d": 0.31 },
  "top_members": [ { "profile_id": "…", "name": "…", "avatar_url": "…", "points": 980 } ]
} }
```

- `dormant_30d` = active members with no `community_point_ledger` row in 30 days.
- `attendance_rate_30d` = distinct check-in profiles ÷ active members, over the
  community's events in the window. `0` when there were no events (never divide by zero).
- `top_members` capped at 5, ordered by `community_points.points`.

Every figure is a single aggregate query. No per-member loop.

### 4.3 Pending email invitations (new)

Fixes D3. **`POST /communities/{id}/members` is left exactly as it is** — changing its
404 into a 201 would silently change the contract mobile is written against. Invitations
get their own resource.

New table `community_invitations`:

| column | type | note |
|---|---|---|
| `id` | uuid pk | |
| `community_id` | uuid fk → communities, cascade | |
| `email` | string, lowercased | |
| `tier_id` | uuid fk → community_tiers, nullOnDelete, nullable | tier the member lands on |
| `token` | string(64) unique | URL-safe, minted on create |
| `invited_by_profile_id` | uuid fk → profiles, nullable on delete | audit |
| `status` | string(20) | `pending` \| `accepted` \| `revoked` \| `expired` |
| `expires_at` | timestamp | default now + 30 days (`config('communities.invitation_ttl_days')`) |
| `accepted_at` | timestamp nullable | |
| `accepted_profile_id` | uuid fk → profiles, nullable | who claimed it |
| timestamps | | |

Indexes: `unique(community_id, email)` **partial on pending** is not portable to SQLite
(tests) — instead a plain `index(community_id, status)` plus a **service-level upsert**:
re-inviting the same email re-uses the pending row and refreshes `expires_at` (idempotent,
mirrors `CommunityMemberService::upsertMember`). Also `index(email, status)` for the
claim-on-register lookup and `unique(token)`.

Endpoints (all manage-gated except `accept`):

```
GET    /communities/{community}/invitations          ?status=pending|all
POST   /communities/{community}/invitations          {email|emails[], tier_id?}   → 201
POST   /invitations/{invitation}/resend                                            → 200
DELETE /invitations/{invitation}                      revoke (status=revoked)      → 200
POST   /invitations/accept/{token}                    auth required                → 200
```

`POST …/invitations` accepts either `email` or `emails[]` (max 50) so the panel can paste
a list — the cheap half of bulk without the CSV parser. Per-row result array is returned
so the UI can show "8 sent, 2 already members".

Guards on create:
- already an **active member** → `422 already_member` for that row.
- already has a Kolabing account → still allowed; the invite email deep-links them to
  join, and `accept` makes them a member. (Simpler than branching, and it means "invite
  by email" always works from the leader's point of view.)

`accept` rules: token must be `pending` and unexpired; the caller's email need **not**
match (the token is the authorization — same model as `Community::inviteUrlWithToken()`),
but a mismatch is recorded in `accepted_profile_id`. On success →
`CommunityMemberService::addMember(community, profile, tier_id)` (idempotent) and the
invitation flips to `accepted`.

**Claim on register.** In `OnboardingService` / the auth path that creates a profile, a
guarded hook accepts every `pending`, unexpired invitation matching the new profile's
email. Guarded exactly like `autoJoinCommunities()` and the missions hooks — a failure
here must never break signup. This is what makes the "no attendee web registration"
choice safe: the invitation waits.

**Mail.** A queued `CommunityInvitationMail` (Markdown mailable) with the community name,
inviter name, and the `/c/{slug}?i={token}` link. Queued (`ShouldQueue`) so a slow SMTP
never blocks the request.

### 4.4 Member detail — `GET /communities/{community}/members/{member}` (new)

Manage-gated. Powers the roster drawer. Member + tier + the enriched metrics of §4.1 +
`activity`: the last 25 `community_point_ledger` rows (points, source, description,
created_at), badges earned, goals completed. Capped, no pagination — a drawer, not a page.

### 4.5 Bulk roster actions — `PATCH /communities/{community}/members` (new)

`{ "member_ids": [...max 100], "tier_id"?, "can_manage"?, "status"? }` → applies
`CommunityMemberService::updateMember` per row in a transaction, returns per-row results.
Every id is verified to belong to `{community}` first (no cross-community writes).

### 4.6 Public join landing — `GET /c/{slug}` (marketing host)

Fixes D4. Server-rendered Blade on the marketing layout, **public, no auth**.

- Resolves `communities.slug`; 404 page if unknown.
- Renders: avatar (falling back to `communityProfile->profile_photo`, as
  `CommunityResource` does), name, type label, description, active member count, the
  tier ladder (name + colour, rank order), and upcoming public events with
  `events.community_id = ?`.
- `?invite=<community token>` pre-authorises an `invite_only` join;
  `?i=<invitation token>` carries an email invitation.
- CTA logic:
  - signed in (token in localStorage) → `Join` (open) / `Request to join` (invite_only) /
    `Accept invitation` (when `?i=`), calling the existing API and then routing to
    `app.kolabing.com/community`;
  - not signed in → `Sign in to join` (carries the return path) + an app handoff block.
    **No attendee registration** — per the locked scope decision.
- `noindex` when the community is `invite_only`; indexable + canonical otherwise, with
  `Organization` JSON-LD (consistent with the marketing site's SEO conventions).

Also fix the source of D4's silence: `config('communities.invite_base_url')` stays as-is
now that the route exists.

### 4.7 Authorization

Everything new goes through the existing `CommunityPolicy@manage` (owner or `can_manage`),
mirroring `CommunityMemberController`'s `$profile->cannot('manage', $community)` guard and
its 403 shape. **No subscription check anywhere in this feature (§8.4).**

---

## 5. Frontend — `app.kolabing.com`

Blade + Alpine + the existing inline `window.kb` client. No npm/Vite change. Reuses the
264px sidebar shell, cream/`#FFE28C` palette, Anton/Inter.

**One gotcha, already verified:** the roster endpoint returns rows at
`data.members` (not `data` or `data.data`), so `kb.rows()` returns `[]` for it — the exact
class of bug BE-NF-21 hit. `kb.rows()` gains a third fallback (`data.members`) *or* the
page reads `json.data.members` explicitly. **We extend `kb.rows()`** so every future
`data.<key>` list endpoint is covered, and add a regression test.

### 5.1 Navigation

A new sidebar entry **Community**, shown via `x-show="canManageCommunity"` (a new
`kbShell()` getter, true when `/me/communities` returns a row **or** `/me/memberships`
returns one with `can_manage`). Mirrors the existing `x-show="isBusiness"` Plan entry.
Pending-work badge = `join_requests + invitations` from `/stats`.

Sub-navigation is a tab strip inside the Hub (not seven sidebar rows).

### 5.2 Pages

| Route | Page | Contents |
|---|---|---|
| `/community` | **Overview** | community switcher; health strip (total / active / new this month / dormant / pending); tier distribution bar; top-5 members; recent activity; quick actions (Invite, Add member, New tier) |
| `/community/members` | **Roster** — the workspace | search box (debounced 300ms), status + tier + can_manage filters, sortable columns (name, tier, points, events, last active, joined), pagination, row menu (change tier / toggle manager / remove), checkbox multi-select → bulk tier + bulk remove, member detail drawer, "Add member" and "Invite by email" modals |
| `/community/requests` | **Requests & invites** | pending join requests (approve / decline) and pending invitations (resend / revoke), two tabs |
| `/community/tiers` | **Tiers** | rank-ordered list with colour swatch + member count; create/edit/delete; name, colour, `assignment_rule`, `threshold`, `is_default`, and the `permissions` JSON as four tag inputs (view / chat_channels / perks / capabilities) |
| `/community/rewards` | **Economy** | three tabs — Goals, Rewards, Badges — each a list + create/edit/delete form against the existing endpoints |
| `/community/leaderboard` | **Leaderboard** | `GET /communities/{id}/leaderboard`, rank / avatar / name / tier chip / badges / points |
| `/community/settings` | **Settings** | name, type (17-slug `community_types` lookup), description, avatar upload (`kb.uploadFile(file,'communities')`), `join_policy`; invite link + copy button; token link for invite-only |

Empty states matter more than usual here (a brand-new community has zero of everything):
each page ships a purposeful empty state whose primary CTA is the inlet — "Invite your
first members".

### 5.3 i18n

`lang/{en,es,ca}/webapp.php` gain a `community.*` block. The repo standard is 100% es/ca
coverage across the web app (BE-NF-25) and this feature must not regress it.

---

## 6. What we deliberately do NOT build

- **CSV import/export** — user-deferred. The `emails[]` paste-list covers the urgent half.
- **Attendee registration on web** — user-deferred; invitations wait via claim-on-register.
- **Tier permission *enforcement*** — §8.3 D3 says Phase 1 stores and returns the
  `permissions` JSON; gating comes later. The panel edits it, nothing reads it yet.
- **Member↔leader messaging from the roster** — chat exists (`POST /communities/{id}/chats`)
  but wiring a compose flow is its own feature.
- **Cash-out / wallet ties** — §8.3 forbids it in v1.
- **A second community** — the free cap stays 1; the panel surfaces
  `422 community_limit_reached` honestly rather than hiding the button.

---

## 7. Testing

`LazilyRefreshDatabase`, PHPUnit, factories, per project convention.

| File | Covers |
|---|---|
| `tests/Feature/Communities/RosterFilterTest.php` | search, status default excludes removed, `?status=all`, tier filter, can_manage filter, each sort key, limit cap |
| `tests/Feature/Communities/RosterMetricsTest.php` | points / events_attended / last_active_at / tenure_days correctness; **a query-count assertion** proving the page is O(1) in members, not O(N) |
| `tests/Feature/Communities/CommunityStatsTest.php` | every figure incl. zero-events attendance rate (no division by zero) and dormancy boundary |
| `tests/Feature/Communities/CommunityInvitationTest.php` | create (single + `emails[]`), idempotent re-invite, already-member 422, resend, revoke, accept (valid / expired / revoked / already-member), manage gate 403 |
| `tests/Feature/Communities/InvitationClaimOnRegisterTest.php` | pending invite → register with that email → active member; failure inside the hook does not break signup |
| `tests/Feature/Communities/MemberDetailTest.php` | drawer payload, activity cap, manage gate |
| `tests/Feature/Communities/BulkMemberUpdateTest.php` | bulk tier / status, cross-community ids rejected, cap |
| `tests/Feature/Communities/CommunityJoinLandingTest.php` | `/c/{slug}` 200, unknown slug 404, invite-only `noindex`, tier ladder + upcoming events render |
| `tests/Feature/Webapp/WebAppRoutesTest.php` (extend) | the 7 `/community/*` routes render under `/`, `/es`, `/ca` |
| `tests/Feature/Communities/NeverPaywalledTest.php` | **§8.4 guard** — a community leader and a `can_manage` attendee, both with no subscription, get 200 on every endpoint in this feature |

`NeverPaywalledTest` is not optional. §6 of ROLES names paywalling a community as the
single most repeated regression in this codebase.

## 8. Docs (mandatory per CLAUDE.md)

- `docs/ROLES-AND-PERMISSIONS.md` §8 — add §8.7 "Managing members on the web", bump
  *Last updated*.
- `docs/ROLES-BACKEND-DB-MAP.md` §12 — new table, new endpoints, the roster default-status
  behaviour change, the claim-on-register hook; bump *Last updated*.
- `BACKLOG.md` — BE-NF-34 entry, bump *Last updated*.
- PR uses `.github/pull_request_template.md`. **Mobile impact:** additive query params +
  additive response fields on `GET /communities/{id}/members`, plus one behaviour change
  (default now excludes `removed` — mobile currently has no Community tab, so nothing
  breaks today, but the kolabing-app ticket must record it) and a new invitations
  resource mobile may adopt later.

## 9. Build order

1. Roster upgrade + metrics + tests *(D1, D2 — unblocks everything visual)*
2. Stats endpoint + tests
3. Invitations: migration, model, service, endpoints, mail, claim-on-register + tests *(D3)*
4. Member detail + bulk actions + tests
5. `/c/{slug}` landing + tests *(D4)*
6. Web app: shell wiring (`canManageCommunity`, `kb.rows()` fix), Overview, Roster
7. Web app: Requests, Tiers, Economy, Leaderboard, Settings
8. i18n es/ca, docs, pint, full suite
