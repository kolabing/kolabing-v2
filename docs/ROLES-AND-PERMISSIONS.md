# Kolabing — Roles, Permissions & Features (Canonical Reference)

**Last updated:** 2026-06-30 (PR 4: public reputation summary — updated reviews note to mention new aggregate `reputation` block on `PublicProfileResource` — §4. Prior: 2026-06-28 gamification mission system v1: curated `app_visible` mission set + event/general mission separation — #49. Prior: PR #59 review fixes — completion-confirmation gate hardening — terminal-state guard, resource/gate agreement on `no`/`not_yet`, auto-complete grace anchored on the `yes` timestamp, **legacy feedback fallback + backfill removed (`/complete` gates purely on real completion confirmations)** — §2.9, §4)
**Status:** Authoritative. This document overrides assumptions.
**Sync note:** This file is duplicated in both repos (`kolabing-app` and `kolabing-v2`). Keep the two copies identical. When role behaviour changes, update both **and bump the Last updated date** in both.

> **Read this before touching any code that affects Explore, profiles, the paywall, permissions, onboarding, or the create/apply flows.**
>
> Kolabing has two live user roles with very different permissions. Most regressions in this app come from applying one role's rules to the other. If a fix seems to require changing what a role can see or do, STOP and re-read this document. If the fix still seems to contradict it, ask Daniel before changing role behaviour. Do not "improve" role logic on your own initiative.

---

## 0. User roles

Kolabing has three user types. Only two are in launch scope.

| Role | In launch scope | Pays? | One-line definition |
|---|---|---|---|
| Business | Yes | Yes, €39.99/month or a 3-month plan | A venue or a product/service sponsor that wants community foot traffic and exposure. |
| Community | Yes | No, free always | A real-life community (running club, yoga group, book club, and so on) that hosts events and needs venues or sponsors. |
| Attendee | **[VERIFY]** code-live but spec-unconfirmed | Free for now | An individual who attends events. Gamification track (check-ins, challenges, badges, leaderboards, reward wallet) is **shipped in the backend**; see §7 and the backend map's §11. Whether attendees are formally part of launch and what the pricing/withdrawal model is needs to be confirmed with Daniel before any client-facing changes. |

---

## 1. The golden rules (most violated, read twice)

1. **Communities are 100% free. They are NEVER paywalled, gated, or blocked from any feature.** If you see a community blocked from creating, applying, chatting, or anything else, that is a bug. The paywall belongs to the Business role only.
2. **The paywall applies ONLY to the Business role, and ONLY on two actions:** creating a collaboration, and applying to a Kolab. Nothing else is paywalled. Registration, onboarding, profile creation, and browsing Explore are always free, including for businesses.
3. **The marketplace is bidirectional. Both roles post, and both roles apply.** Communities post Kolabs and can apply to business offers. Businesses post offers and can apply to community Kolabs. Never remove either role's ability to post or to apply.
4. **A free (non-subscribed) business sees Explore with the community name and logo BLURRED — not hidden, not hard-blocked.** They see the Kolab and all its details; only the community's identity is blurred. Subscribing reveals it.
5. **Never hard-block or full-screen-overlay a screen the user is allowed to be on.** Gating means: blur the protected element, or disable the specific action button. It does not mean a full-screen block.
6. **"Opportunity" and "collaboration" both exist and are both valid.** The app uses "opportunity" for community-created posts and "collaboration" for business-created posts ("Kolab" is used loosely for either). Do not delete, merge, or rename one into the other.

---

## 2. Business role

### 2.1 Identity and pricing
Businesses are venues (café, restaurant, bar, bakery, coworking, coliving, gym, salon, retail, hotel) or product/service sponsors. They are the paying side. Price: €39.99/month, or a 3-month plan. Registration and exploration are free; the subscription unlocks the two gated actions only.

> **Backend note:** `coliving` is part of the spec list but is **not currently in `BusinessOnboardingRequest::BUSINESS_TYPES`**. Adding it is tracked in the backend map's mistakes-to-fix checklist. Until added, a `coliving` business onboarding payload will fail server-side validation.

### 2.2 Onboarding
- Path: "I'm a Business."
- Choose to promote a Venue or a Product/Service.
- Venue businesses use the Google Maps lookup: the first onboarding screen finds the venue on Google Maps and the API pre-populates name, photos, and details. The user must see a preview and be able to delete individual imported photos.
- A business profile can also be pre-created by the Kolabing team (the pre-launch catalogue) and activated by the owner via an emailed link (review, edit, set password).
- Onboarding must stay under roughly 5 minutes.

### 2.3 Explore — what a business sees
The business Explore feed shows COMMUNITY Kolabs (the posts communities created, that is, what communities are looking for). For each Kolab the business sees:
- The Kolab name (e.g. "Training & Brunch")
- Fit % and its breakdown
- What the community is looking for
- What the community offers, shown concretely (e.g. "Social Media, 30+ people"), never the abstract word "match"
- The community size / number of people expected at the event
- The available dates

Business Explore shows Kolabs, NOT community profiles. A community profile is reached by tapping into a Kolab (subscribed businesses only).

### 2.4 Profile — what a business has
- Logo, business name, venue or product type (formatted tag, e.g. "Coffee Shop", never "coffee_shop")
- Photo gallery
- Past events
- Contact info
- The offering the business makes to communities
- Home / Dashboard: performance statistics from past collaborations (revenue generated, Instagram followers gained)

### 2.5 Free (non-subscribed) business — exact capabilities
A free business CAN:
- Register, complete onboarding, and build its profile
- Browse the Explore feed
- See every Kolab's details: type, community size, what is needed, what is offered, available dates, Fit %

A free business CANNOT (the protected element is blurred, or the action is gated):
- See the community NAME — blurred
- See the community LOGO — blurred
- Open a community's full profile or contact
- Create a collaboration (offer) — gated, shows the paywall
- Apply to a Kolab — gated, shows the paywall
- Chat — not reachable (chat exists only after an accepted application)

The free state is BLUR, not block. The business stays on Explore and sees the marketplace; only the community identity is blurred and the two actions are gated. Never replace this with a full-screen block or overlay.

### 2.6 Subscribed business — exact capabilities
Everything a free business can do, plus:
- See community names and logos
- Open full community profiles, including the past events carousel
- Create collaborations (offers)
- Apply to community Kolabs, choosing only from the dates the community marked available
- Chat with a matched community
- Run collaborations, edit them, finish them, leave reviews, give feedback

### 2.7 What is paywalled for a business
ONLY these two actions. Nothing else:
- Creating a collaboration
- Applying to a Kolab

As a consequence of not subscribing, the community identity in Explore is blurred and chat is unreachable (because chat requires an accepted application). Those are downstream effects of the two gates, not separate paywalls. Do not add any other paywall, except the subscription-lapse re-gate in §2.8.

### 2.8 Subscription lapse (re-gating) — decided 2026-05-22
If a business's subscription lapses (expires or is cancelled), the business is **re-gated**: it loses access to its ongoing collaborations and chats until it resubscribes — in addition to the two create/apply gates. This is the one and only case where access beyond create/apply is withdrawn from a business.

The **community counterparty is NEVER affected**: communities keep full access to the shared collaboration and chat regardless of the business's subscription state. Re-gating is one-sided (business only). This refines §2.7: create/apply are the only first-contact paywalls, but a lapse additionally withdraws ongoing business-side access.

### 2.9 Maintainer admin actions — added 2026-06-01

The admin panel (`/admin/*`, `auth:admin + maintainer` guard) exposes these collaboration-level actions:
- **Force-cancel** (`POST /admin/kolabs/{kolab}/collaboration/cancel`): persists `cancellation_reason` and stamps `cancelled_at`. `cancelled_by_profile_id = null` indicates maintainer action.
- **Force-complete** (`POST /admin/kolabs/{kolab}/collaboration/complete`): bypasses the completion-confirmation gate (see §4). Persists `completion_reason` and stamps `completed_at`. `completed_by_profile_id = null` indicates maintainer action. No XP is awarded.
- **Auto-complete (system)**: a scheduled job (`app:auto-complete-stale-collaborations`, default `dailyAt('03:00')`) completes scheduled/active collaborations once a `yes` confirmation has stood for more than `config('collaborations.auto_complete_grace_days_after_first_completion_confirmation', 3)` days (measured from when the `yes` was set, so a `not_yet→yes` change restarts the window), **unless** any party explicitly answered `no` or `not_yet` (those signals are left for manual/admin resolution, never silently completed). Stamps `auto_completed_at`.

### 2.10 Maintainer-granted subscription access — added 2026-06-01
A Kolabing maintainer can grant a business **12 months of subscription access** from the admin panel (`/admin/users/{profile}/subscription/grant`). This produces a `business_subscriptions` row with `status = active` and **`source = maintainer`**. The grant bypasses Stripe/Apple IAP but is identical to a paid subscription as far as the paywall and re-gating logic are concerned — the business gets full subscribed-business capabilities until the period ends or a maintainer revokes it.

A revoke (`/subscription/revoke`) flips the row to `status = inactive` with `cancel_at_period_end = true`. After revoke, the standard subscription-lapse re-gate (§2.8) kicks in.

**Maintainer grants are auditable** via the `source = maintainer` value. There is no other way for an active subscription row to appear without payment.

### 2.11 Test users — back-channel
A profile with `profiles.is_test_user = true` is treated as having an active subscription regardless of whether a `business_subscriptions` row exists. This is reserved for Kolabing internal QA accounts. Never set this flag on real customer profiles.

---

## 3. Community role

### 3.1 Identity and pricing
Communities are real-life groups: running clubs, yoga groups, book clubs, cycling teams, creative collectives, social meetups, and so on. They are the free side. **Communities pay nothing and are never gated. Full stop.**

### 3.2 Onboarding
- Path: "I'm a Community."
- Community type, size, photos, description.
- Free and fast.

### 3.3 Explore — what a community sees
The community Explore feed shows BUSINESSES and business offers. For each, the community sees:
- The business name (never blurred; communities have full access)
- The neighbourhood / area the business is in
- What the business offers, shown concretely (e.g. "-10% discount", "Free space"), never the abstract word "match"
- Business details and photos

Communities see everything in Explore. No blurring, no gating, ever.

### 3.4 Profile — what a community has
- Logo, community name, community type (formatted tag, e.g. "Run Club", never "Run_Club")
- Community size
- Photo gallery
- Past events
- Contact info, Instagram link
- Home / Dashboard: gamified. Credits earned, a progress slider toward the withdrawal threshold, and a "Next goal" call-to-action block (e.g. "Post a Kolab, +5 points", "Give feedback, +10 points")

### 3.5 What a community can do — everything, free
- Register, onboard, build profile
- Create Kolabs (opportunities). This is their core action and is NEVER gated.
- Browse Explore and apply to business offers
- When applying, choose dates only from what the business marked available
- Chat with matched businesses
- Run collaborations, edit them, finish them
- Leave reviews, give feedback
- Earn credits, refer businesses and communities, withdraw earnings (€0.25 per point, €75 withdrawal threshold)

### 3.6 What is blocked for a community
Nothing. There is no paywall and no gated action on the community side. If code blocks a community from anything, it is a bug. The known current bug "create opportunity blocked for communities" must be fixed: communities must always be able to create.

---

## 4. Shared features (both roles, around a match)

> **Canonical create/apply API (as of 2026-06-19, #30).** The canonical create/apply surface is `/api/v1/kolabs/*`, including the new apply endpoints `GET` + `POST /api/v1/kolabs/{kolab}/applications`. The legacy `/api/v1/opportunities/*` routes remain as a **temporary compatibility shim** (a request-contract translation over `KolabService`) that the live mobile app still depends on; their removal is gated on the mobile migration (`kolabing-app` #20). **Caveat:** the freemium/paywall collaboration limit is currently enforced only on the legacy create path (`OpportunityService`), NOT on `/kolabs` create — do not assume `/kolabs` create enforces it yet. Porting it to `/kolabs` and retiring the shim is tracked in #31.

- **Applications.** Either role applies to the other's post. The applying side picks dates only from the dates the posting side marked available.
- **Chat.** Unlocked once an application is accepted. The other party's name is shown in chat.
- **Collaboration.** Created when an application is accepted. Either side can edit the date or time. Either side can mark it finished; it also closes when the date passes. Both sides confirm.
- **Two-way reviews (public).** After a collaboration, the business reviews the community and the community reviews the business via `POST /collaborations/{id}/review`. Ratings are visible on profiles via `PublicProfileReviewResource` (individual reviews) and a `reputation` summary block (`average_rating`, `review_count`, `unique_partner_count`, `breakdown`) on `PublicProfileResource`/`GET /profiles/{id}`, counting only reviews on completed collaborations.
- **Completion confirmation (required, lightweight, gates completion) — added 2026-06-26, PR 1.** Each participant answers a single yes/no/not_yet question via `POST /collaborations/{id}/completion` (`status`, optional `note` up to 500 chars). One row per `(collaboration_id, profile_id)` in `collaboration_completions`; resubmitting updates your own row (e.g. `not_yet` → `yes`) without re-awarding XP. **A collaboration only transitions to `completed` once both parties have confirmed AND both said `yes`** (server-enforced via `config('collaborations.complete_requires_completion_confirmation')`, default true). If either side hasn't responded at all, `/complete` returns `awaiting_own_completion_confirmation` / `awaiting_partner_completion_confirmation`; if both responded but at least one said `no`/`not_yet`, it returns `completion_not_confirmed` (with `own_status`/`partner_status` in the error body). XP (`CollaborationCompletionConfirmed`, 10 by default, admin-editable) fires once per party on first submission only.
- **Rich feedback (private, optional impact data) — feedback gate removed 2026-06-26, PR 1.** A feedback step via `POST /collaborations/{id}/feedback`. Required fields: rating (1–5), expectation match, would recommend, would collaborate again. Optional shared: posts/reels. Business-only: stories posted, revenue. Community-only: benefits. **No longer gates `/complete`** — that is now the completion-confirmation step above. XP (`CollaborationComplete`) still fires per party on feedback submission. Editing your row via `PUT /feedback` is allowed until the partner submits — after that, both lock.
  - Visibility (Q10 in the 2026-06-01 plan): rating + expectation_match + would_recommend are cross-visible to participants once both feedbacks land; revenue / stories_posted / posts_reels / benefits stay private to the submitter (and to maintainers).
  - Re-gate exemption: a lapsed business can still submit `/feedback` on a past collab — feedback is post-mortem and unblocked even when `/collaborations` index is re-gated.
  - **Backend mirror (rollout aid, still active post-PR-1):** while the legacy app still POSTs to `/review`, the server transparently writes a stub `/feedback` row (mirror) so feedback-dependent aggregates stay consistent. Mirrored rows carry `mirrored_from_review = true` and are excluded from the rich admin-stats aggregates. **This mirror no longer affects `/complete`** — it only ever wrote to `collaboration_feedback`, which the completion gate no longer reads.

---

## 5. Permission matrix

| Capability | Free Business | Subscribed Business | Community | Attendee |
|---|---|---|---|---|
| Register and onboard | Yes | Yes | Yes | Yes |
| Browse Explore (marketplace feed) | Yes | Yes | Yes | **No** — attendees do not use the marketplace |
| See the other side's post details | Yes | Yes | Yes | n/a |
| See the other side's name and logo | No, blurred | Yes | Yes | n/a |
| Open the other side's full profile | No | Yes | Yes | n/a |
| Create a post (collaboration / Kolab) | No, paywall | Yes | Yes, free | **No** |
| Apply to a post | No, paywall | Yes | Yes, free | **No** |
| Chat | No | Yes | Yes | **No** |
| Reviews and feedback | No | Yes | Yes | n/a |
| Check into events, complete challenges, earn badges | n/a | n/a | n/a | **Yes** — gamification track |
| Earn credits, refer, withdraw | n/a | Business referral perks exist, tracked separately | Yes (€0.25/pt, €75 threshold) | **[VERIFY]** whether attendee wallet redeems to cash |
| Run a member roster + custom tiers (NF-6, §8) | n/a | n/a | **Yes** — Community Leader owns communities, defines tiers, manages roster (free; capped at 1 community pending NF-7) | n/a |
| Be a member of a community + hold a tier (NF-6, §8) | n/a | n/a | n/a | **Yes** — as a "Community Member" (wire value stays `attendee`); one tier per community, chapter-scoped leaderboard |

---

## 6. Common mistakes to avoid

These are specific errors that have happened in past fixes. Do not repeat them.

- **Do not apply the business paywall to communities.** Communities create and apply for free. If a community hits a paywall or a block, the gate is the bug. Fix the gate; do not gate the community.
- **Do not block communities from creating opportunities.** Creating a Kolab is the community's core, free action.
- **Do not hard-block or full-screen-overlay a free business.** Blur the community name and logo; disable the create and apply buttons. The business stays on Explore.
- **Do not remove either role's ability to post.** Both businesses and communities post. Both apply.
- **Do not merge, delete, or rename "opportunity" versus "collaboration."** Both exist and are distinct.
- **Do not change what a free business sees in Explore beyond the blur.** They see all Kolab details; only the community identity is blurred.
- **Do not paywall registration, onboarding, or browsing.** Only creating and applying are paywalled, and only for the Business role.
- **When a fix touches Explore, profiles, the paywall, or onboarding, re-read sections 1, 2, and 3 of this document before writing code.**
- [ ] Port the freemium collab limit + portfolio-photo parity to `/kolabs`, then remove the `/opportunities` shim (#31). The limit lives only on the legacy `/opportunities` create path today; `/kolabs` create does not enforce it yet.

---

## 7. Attendee role — first pass (added 2026-06-01, scope [VERIFY] with Daniel)

The attendee role's backend track has shipped and the canonical position that attendees are "deferred / out of scope" is no longer accurate. This section captures what the code currently allows pending product confirmation.

### 7.1 What an attendee can do today (verified against `routes/api.php` and the gamification services)
- Register via email/password (`POST /api/v1/auth/register/attendee`) or Google / Apple OAuth.
- Be a member of an `attendee_profiles` row (`total_points`, `total_challenges_completed`, `total_events_attended`, `global_rank`).
- Check into events by scanning the organiser-generated QR (`POST /checkin`). Each check-in increments `total_events_attended`.
- Take part in challenges per event: list, initiate peer-to-peer, verify / reject, see own completion history.
- Earn points (`point_ledger` — append-only) and badges (`BadgeService` awards on milestones like `LoyalAttendee = total_events_attended >= N`).
- See per-event and global leaderboards.
- Hold a reward wallet and redeem rewards.
- Track self-completing "general missions" on the app Missions screen (added 2026-06-27, v1 curation — #49). See §7.4 below.

### 7.4 General missions vs. event challenges — two kinds, both stay (added 2026-06-27)

There are two distinct kinds of `challenges` row, distinguished by `trigger_action`:

- **General missions** (`trigger_action IS NOT NULL`) — self-tracked onboarding/growth
  goals such as "Complete your profile" or "Attend 3 events this month". These auto-progress
  via `MissionService::record()` whenever the matching platform action fires, and are
  surfaced to the attendee/business/community viewer on `GET /api/v1/me/missions`.
- **Event challenges** (`trigger_action IS NULL`) — peer-verified, in-event tasks attached
  to a specific kolab event (e.g. "Take a selfie together"). These power the kolab
  "GAMIFICATION SETUP" attendee picker and the admin challenge-defaults matrix. They are
  never auto-tracked and never appear on the Missions screen.

`GET /me/missions` returns a mission only when **all** of the following hold:
`is_system = true` AND `event_id IS NULL` AND `app_visible = true` AND
`trigger_action IS NOT NULL` AND `trigger_action` is one of the **live** triggers
(`MissionTrigger::isLive()` — the triggers actually wired to a source action today) AND
the mission's `audience` matches the viewer's profile type AND the current time falls
within `[starts_at, ends_at]` (nulls treated as open-ended).

`app_visible` is a separate v1-launch curation flag on top of that: of the 49 missions
seeded by `SystemChallengeSeeder`, exactly **18 are `app_visible = true`** today — 5
attendee, 7 business, 6 community — each independently verified to use a live trigger.
The remaining seeded missions exist in the database (manageable from admin, visible in
the defaults matrix where relevant) but are deliberately held back from the app surface
until their triggers are wired or product decides to launch them.

The event/general separation is enforced in **three** places, all filtering
`trigger_action IS NULL` to keep general missions out of event-scoped surfaces:
`SystemChallengeController` (`GET /api/v1/challenges/system`), `Admin\ChallengeDefaultsController`
(the admin defaults matrix), and `ChallengeService::listForEvent()` (`GET /api/v1/events/{event}/challenges`).

### 7.2 What an attendee CANNOT do (confirmed at the service layer)
- Create or publish kolabs / opportunities — neither service path accepts an attendee creator.
- Apply to kolabs — `applications.applicant_profile_type` enum is business / community only.
- Subscribe — the paywall and the admin grant route both reject non-business profiles.
- Chat — chat is bound to an accepted application between a business and a community.

### 7.3 Decisions still needed
- Is the attendee role part of launch, or held back?
- Do attendee points convert to real money through the wallet / withdrawal flow (the way §3.5 describes for communities — €0.25/point, €75 threshold) or is the wallet community-only?
- Should this section grow into a full §4-equivalent (Identity, Onboarding, Explore, Profile, Capabilities matrix) once those decisions land?

Until those are resolved, treat **§0 attendee row as the stale legacy** and this §7 as the working reference, in sync with the backend map's §11.

---

## 8. Community members & customisable tiers (NF-6 Phase 1, added 2026-06-03)

This is a NEW role surface. It gives a **Community Leader** (the `community` user type) the tooling to run a real membership organisation, and gives a **Community Member** (the `attendee` user type, relabelled in UI copy only) a place in one or more communities with a leader-defined status tier. It is community-agnostic (Greek life is the launch inspiration, but it serves fitness studios, running clubs, and business communities equally).

### 8.1 The two roles in this feature
- **Community Leader = the `community` user type.** Creates communities, defines tiers + rules, invites/approves members, assigns/auto-assigns tiers, views member activity. Surfaced in the app as a new "Community" tab.
- **Community Member = the `attendee` user type.** "Community Member" is an **app label only** — the wire value stays `attendee` (decision D4; do NOT add a `user_type` enum value). Members see which communities they belong to and their tier in each.

### 8.2 What Kolabing ships vs. what the leader supplies
Kolabing ships the **mechanism** (tiers + rules + roster). The leader supplies the **meaning** (tier names, colors, thresholds). The Greek "Exec / Active / Pledge" ladder is not special-cased — it is simply three tiers a leader configured. The app renders whatever tiers the leader defines, in rank order.

### 8.3 Locked product decisions
- **Tier ⟂ admin (D1):** a tier is the member-facing status ladder. "Can manage" is a **separate `can_manage` boolean** on the membership, granted independently. The top tier is NOT coupled to admin power.
- **Multi-community (D2):** a member belongs to many communities, **one tier per community** (tier lives on the membership row).
- **Tier payload (D3):** each tier carries a flexible `permissions` JSON (`{view, chat_channels, perks, capabilities}`). Phase 1 stores + returns it; gating enforcement comes later.
- **Free vs premium (D5):** **one community free per leader.** Creating a 2nd+ community returns `422 community_limit_reached` — a NEW gate reserved for the future **NF-7 Community Premium**. This is hard-capped at 1 for now via `config('communities.max_free_communities')`.
- **Join model:** a community has a `join_policy` of `open` (members may self-join AND the leader may invite) or `invite_only` (leader / `can_manage` add only). Default `open`.
- **Cash-out:** v1 tiers carry **status + non-cash perks only**. Tiers are NOT wired into wallets / withdrawal_requests.

### 8.4 NEVER paywall this surface
The community cap (§8.3, D5) is its own config-driven gate with its own error code. **It must never call `Profile::hasActiveSubscription()` and must never reuse the business paywall** (the business paywall is business-only, §6 and DB-MAP §3). A community or attendee path must never hit a subscription gate. The `CommunityPolicy` gates mutation on ownership / `can_manage` only.

### 8.5 Tier auto-assignment
Tiers can auto-assign by rule: `xp_threshold` (member XP from `point_ledger`), `tenure` (days since `joined_at`), `events_attended` (check-ins on the community's events). `manual` tiers are leader-only and never auto-touched, and a member a leader manually placed on a non-default manual tier is never auto-overwritten. A member is promoted to the **highest-rank** tier whose rule they satisfy; members are never auto-demoted. Runs nightly (`app:evaluate-community-tiers`) and immediately on check-in.

### 8.6 The community ↔ events linkage
"This community's events" is defined by a new nullable `events.community_id` FK. The `events_attended` rule counts check-ins on events with that `community_id`. The chapter-scoped leaderboard (`GET /leaderboard/global?community_id=`) is scoped by **active membership** (the global leaderboard filtered to one community's members). Organiser's events are NOT silently assumed to be the community's events.

Backend wiring (tables, endpoints, policy, command) is in `ROLES-BACKEND-DB-MAP.md §12`.

---

## 9. Maintaining this document

This file and `docs/ROLES-BACKEND-DB-MAP.md` are read by every Claude session that touches role-affecting code (see the project `CLAUDE.md`). They are also duplicated in the `kolabing-app` repo.

**When you change role behaviour, paywalling, or admin operator capabilities:**
1. Update this document — adjust the affected section, the permission matrix in §5, and the golden rules if they shift.
2. Update `docs/ROLES-BACKEND-DB-MAP.md` — update the line numbers, schema map, and mistakes-to-fix checklist.
3. Bump the **Last updated** date at the top of both files.
4. Mirror the change into the `kolabing-app` copy of both files.
5. If the change adds or removes a role surface entirely, update the project `CLAUDE.md` "MUST READ" block too.

Treat this maintenance as part of the change, not optional.
