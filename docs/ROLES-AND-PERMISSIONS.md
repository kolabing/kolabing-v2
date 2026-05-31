# Kolabing — Roles, Permissions & Features (Canonical Reference)

**Last updated:** 2026-06-01
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
| Business | Yes | Yes, €29/month or €96 per 4-month plan | A venue or a product/service sponsor that wants community foot traffic and exposure. |
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
Businesses are venues (café, restaurant, bar, bakery, coworking, coliving, gym, salon, retail, hotel) or product/service sponsors. They are the paying side. Price: €29/month, or €96 per 4-month plan. Registration and exploration are free; the subscription unlocks the two gated actions only.

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

### 2.9 Maintainer-granted access — added 2026-06-01
A Kolabing maintainer can grant a business **12 months of subscription access** from the admin panel (`/admin/users/{profile}/subscription/grant`). This produces a `business_subscriptions` row with `status = active` and **`source = maintainer`**. The grant bypasses Stripe/Apple IAP but is identical to a paid subscription as far as the paywall and re-gating logic are concerned — the business gets full subscribed-business capabilities until the period ends or a maintainer revokes it.

A revoke (`/subscription/revoke`) flips the row to `status = inactive` with `cancel_at_period_end = true`. After revoke, the standard subscription-lapse re-gate (§2.8) kicks in.

**Maintainer grants are auditable** via the `source = maintainer` value. There is no other way for an active subscription row to appear without payment.

### 2.10 Test users — back-channel
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

- **Applications.** Either role applies to the other's post. The applying side picks dates only from the dates the posting side marked available.
- **Chat.** Unlocked once an application is accepted. The other party's name is shown in chat.
- **Collaboration.** Created when an application is accepted. Either side can edit the date or time. Either side can mark it finished; it also closes when the date passes. Both sides confirm.
- **Two-way reviews.** After a collaboration, the business reviews the community and the community reviews the business. Ratings are visible on profiles and affect positioning.
- **Feedback.** A mini feedback modal on completion. Business feedback captures star rating, stories posted, posts/reels, revenue, expectation match, and "would you recommend this community." Community feedback captures star rating, benefits received, posts/reels, expectation match, and "would you recommend this business."

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

## 8. Maintaining this document

This file and `docs/ROLES-BACKEND-DB-MAP.md` are read by every Claude session that touches role-affecting code (see the project `CLAUDE.md`). They are also duplicated in the `kolabing-app` repo.

**When you change role behaviour, paywalling, or admin operator capabilities:**
1. Update this document — adjust the affected section, the permission matrix in §5, and the golden rules if they shift.
2. Update `docs/ROLES-BACKEND-DB-MAP.md` — update the line numbers, schema map, and mistakes-to-fix checklist.
3. Bump the **Last updated** date at the top of both files.
4. Mirror the change into the `kolabing-app` copy of both files.
5. If the change adds or removes a role surface entirely, update the project `CLAUDE.md` "MUST READ" block too.

Treat this maintenance as part of the change, not optional.
