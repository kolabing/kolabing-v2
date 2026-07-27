# Postmark Template Copy Export

**Purpose:** faithful, verbatim export of the transactional email copy in this repo, for Jace's
brand-voice audit. This is an **extraction**, not a rewrite — nothing below has been reworded.

**Source files:**
- [`resources/postmark/templates.php`](../resources/postmark/templates.php) — 22 template
  definitions (structured content blocks; this is the seed `email:sync-templates` pushes to Postmark).
- [`docs/plans/2026-06-04-transactional-email-system.md`](plans/2026-06-04-transactional-email-system.md)
  — the copy-slot catalog and gating rules for the full onboarding/lifecycle plan.

**Count note:** the repo now defines **22** template aliases in `templates.php`, all exported
below. `business-welcome-01` / `community-welcome-01` (the onboarding drip's T+0 welcome) were
previously authored only in the Postmark dashboard; they are now checked into `templates.php` so a
fresh environment is self-sufficient, and their copy is exported here (see the *Onboarding drip*
section). The wider plan doc catalogs up to ~30 email *concepts* across all phases (A–G), but the
remaining ones (billing/digest, Phase 3–4) have no template file yet — they're design notes, not copy.

**Rendering note:** every template shares one HTML scaffold (logo header, 600px content column,
footer) built by `SyncPostmarkTemplates::renderHtml()`. The `content` blocks below are rendered in
order into that scaffold. Block types: `para` (paragraph), `head` (bold subheading), `list`
(ordered/unordered), `button` (label + URL), `signature` (fixed "Maria, Co-founder, Kolabing"),
`ps` (postscript, rendered with a top border). All body text runs through `htmlspecialchars()` on
render; the copy below is the pre-escaped source text.

**Merge variable syntax:** Postmark mustache, `{{variable_name}}`. Every `{{...}}` token below is
**dynamic** (filled at send time from the `model` array); everything else is **static** copy.
Each template's dynamic variables are also listed in the index at the bottom of this doc.

---

## Account & security (always sends — never opt-outable)

### `attendee-welcome-01` — Welcome (attendee)
**Subject:** `Your first points are one event away`
**Sample model:** `{ first_name: "Daniel" }`

> Hi {{first_name}},
>
> Welcome to Kolabing. You're here for the events, the challenges, and the rewards you earn just for showing up.
>
> **How it works (2 min):**
> 1. Open the app and join a community you're into. Run clubs, art collectives, wellness groups, food crews. New ones are added every week in Barcelona.
> 2. Find an event or challenge, show up, and snap your proof.
> 3. Earn points. Every challenge and event earns points that unlock badges and move you up the community leaderboard.
>
> **This week:**
> Join one community and complete one challenge. That's it. The first one is the hardest; after that it's a habit.
>
> [Signature: Maria, Co-founder]
>
> P.S. Not sure which community fits you? Reply with what you're into (running, art, food, wellness...) and I'll point you to the most active ones tonight.

---

### `password-reset` — Password reset
**Subject:** `Reset your Kolabing password`
**Sample model:** `{ first_name: "Daniel", reset_url: "https://kolabing.com/reset-password?token=sample", expires_minutes: "60" }`

> Hi {{first_name}},
>
> We got a request to reset your Kolabing password. Tap the button below to choose a new one. This link expires in {{expires_minutes}} minutes.
>
> **[Button: Reset password → {{reset_url}}]**
>
> If you didn't request this, you can ignore this email, your password won't change.

---

### `password-changed` — Password changed
**Subject:** `Your Kolabing password was changed`
**Sample model:** `{ first_name: "Daniel", support_email: "info@kolabing.com" }`

> Hi {{first_name}},
>
> This is a confirmation that your Kolabing password was just changed. For security, you've been signed out on all devices.
>
> If this was you, you're all set. If it wasn't, reset your password right away and email us at {{support_email}}.

---

## Onboarding / activation nudges

### `complete-profile-business` — Complete profile (business)
**Subject:** `Your profile is almost ready`
**Sample model:** `{ first_name: "Joe's Cafe" }`

> Hi {{first_name}},
>
> You signed up but your business profile isn't finished yet, and communities can't find you until it is.
>
> **Two minutes to go live:**
> 1. Add 3 photos of your venue.
> 2. Pick your category and write one specific line about your place.
> 3. Add one thing you can offer a community.
>
> Done. You'll start showing up in front of communities looking for partners.
>
> [Signature: Maria, Co-founder]

---

### `complete-profile-community` — Complete profile (community)
**Subject:** `Your community profile is almost ready`
**Sample model:** `{ first_name: "Barcelona Run Club" }`

> Hi {{first_name}},
>
> You signed up but your community profile isn't finished, and businesses can't find you until it is.
>
> **Two minutes to go live:**
> 1. Add your logo and a couple of photos.
> 2. Pick your community type and write one line about who you are.
> 3. Add your city and socials.
>
> Done. You'll start showing up to businesses looking to collaborate.
>
> [Signature: Maria, Co-founder]

---

### `activation-business` — Activation nudge (business)
**Subject:** `Post your first Kolab`
**Sample model:** `{ first_name: "Joe's Cafe" }`

> Hi {{first_name}},
>
> Your profile's ready. Now post your first Kolab so communities can apply.
>
> A Kolab is just what you're offering and what you'd like back. A discount night for a shoutout. A private space for an event. It takes 3 minutes.
>
> **[Button: Post a Kolab → app]**
>
> [Signature: Maria, Co-founder]

---

### `activation-community` — Activation nudge (community)
**Subject:** `Apply to your first Kolab`
**Sample model:** `{ first_name: "Barcelona Run Club" }`

> Hi {{first_name}},
>
> Your profile's ready. Now browse what businesses are offering and apply to your first Kolab.
>
> Venues, discounts, private spaces, sponsored slots, there's a lot up for grabs for your members. It takes 2 minutes to apply.
>
> **[Button: Browse Kolabs → app]**
>
> [Signature: Maria, Co-founder]

---

### `attendee-activation-01` — Activation nudge (attendee)
**Subject:** `Your first challenge is waiting`
**Sample model:** `{ first_name: "Daniel" }`

> Hi {{first_name}},
>
> You're in, but you haven't joined a community yet. That's where the events, challenges, and points are.
>
> Pick one community, complete one challenge. The first one is the hardest; after that it's a habit.
>
> **[Button: Find a community → app]**
>
> [Signature: Maria, Co-founder]

---

## Collaboration lifecycle

### `application-received` — Application received
**Subject:** `New application for {{opportunity_title}}`
**Sample model:** `{ first_name: "Joe's Cafe", applicant_name: "Barcelona Run Club", opportunity_title: "Saturday morning coffee partner" }`

> Hi {{first_name}},
>
> {{applicant_name}} just applied to your Kolab "{{opportunity_title}}".
>
> Take a look and accept if it's a fit. The sooner you reply, the more likely it happens.
>
> **[Button: Review application → app]**

---

### `application-accepted` — Application accepted
**Subject:** `You're in. {{opportunity_title}} is a match`
**Sample model:** `{ first_name: "Barcelona Run Club", partner_name: "Joe's Cafe", opportunity_title: "Saturday morning coffee partner" }`

> Hi {{first_name}},
>
> Good news, {{partner_name}} accepted your application for "{{opportunity_title}}".
>
> Open the app to agree on a date and sort the details in chat.
>
> **[Button: Open the collaboration → app]**

---

### `application-declined` — Application declined
**Subject:** `About your application for {{opportunity_title}}`
**Sample model:** `{ first_name: "Barcelona Run Club", partner_name: "Joe's Cafe", opportunity_title: "Saturday morning coffee partner" }`

> Hi {{first_name}},
>
> {{partner_name}} won't be moving forward with your application for "{{opportunity_title}}" this time.
>
> It happens, fit matters. There are more Kolabs posted every week, and the next one might be a better match.
>
> **[Button: Browse Kolabs → app]**

---

### `first-message` — First message
**Subject:** `{{sender_name}} sent you a message`
**Sample model:** `{ first_name: "Joe's Cafe", sender_name: "Barcelona Run Club" }`
**Trigger note (from plan doc):** fires only on a thread's first message; every later message in the same thread is push-only, no email.

> Hi {{first_name}},
>
> {{sender_name}} started a conversation with you on Kolabing. Open the app to reply and keep things moving.
>
> **[Button: Open chat → app]**

---

### `collab-confirmed` — Collaboration confirmed
**Subject:** `Your collaboration with {{partner_name}} is confirmed`
**Sample model:** `{ first_name: "Joe's Cafe", partner_name: "Barcelona Run Club", scheduled_date: "Saturday, 14 June" }`

> Hi {{first_name}},
>
> It's official, your collaboration with {{partner_name}} is confirmed for {{scheduled_date}}.
>
> **Before the day:**
> - Agree on the final details in chat.
> - Make sure everyone knows where and when.
>
> We'll remind you the day it happens.
>
> **[Button: View collaboration → app]**

---

### `feedback-request` — Feedback request
**Subject:** `How did it go with {{partner_name}}?`
**Sample model:** `{ first_name: "Joe's Cafe", partner_name: "Barcelona Run Club" }`

> Hi {{first_name}},
>
> Your collaboration with {{partner_name}} should be done by now. Tell us how it went, it takes a minute and helps both sides build trust for the next one.
>
> **[Button: Give feedback → app]**
>
> Your feedback (including any revenue it drove) stays private and helps us match you better next time.

---

## Gamification

### `badge-earned` — Badge earned
**Subject:** `You earned the {{badge_name}} badge`
**Sample model:** `{ first_name: "Daniel", badge_name: "Early Bird" }`

> Hi {{first_name}},
>
> Nice work, you just earned the {{badge_name}} badge on Kolabing.
>
> Keep showing up to climb the leaderboard and unlock the next one.
>
> **[Button: See your badges → app]**

---

### `reward-won` — Reward won (community leader)
**Subject:** `You won {{reward_name}}`
**Sample model:** `{ first_name: "Barcelona Run Club", reward_name: "a 50 EUR bonus" }`

> Hi {{first_name}},
>
> Congratulations, you just won {{reward_name}}.
>
> Open the app to see the details and claim it.
>
> **[Button: Claim your reward → app]**

---

### `withdrawal-processed` — Withdrawal processed (community leader)
**Subject:** `Your cash-out of {{amount_eur}} is on its way`
**Sample model:** `{ first_name: "Barcelona Run Club", amount_eur: "€75.00" }`

> Hi {{first_name}},
>
> Your withdrawal of {{amount_eur}} has been processed and is on its way to your account.
>
> Thanks for being an active part of Kolabing.

---

### `tier-promotion` — Tier promotion
**Subject:** `You've reached {{tier_name}}`
**Sample model:** `{ first_name: "Barcelona Run Club", tier_name: "Gold" }`

> Hi {{first_name}},
>
> You've been promoted to {{tier_name}}. Your activity on Kolabing is paying off.
>
> Higher tiers mean more visibility and more perks. Keep it up.
>
> **[Button: See your status → app]**

---

## Attendee gamification

### `attendee-challenge-verified` — Challenge verified (attendee)
**Subject:** `Challenge complete, +{{points}} points`
**Sample model:** `{ first_name: "Daniel", challenge_name: "5K Morning Run", points: "50" }`

> Hi {{first_name}},
>
> Your "{{challenge_name}}" challenge was verified. You just earned {{points}} points.
>
> Keep going to unlock badges and climb your community leaderboard.
>
> **[Button: See your points → app]**

---

## Onboarding drip

The T+0/T+2/T+5/T+10 drip (`OnboardingDripService`). The welcome (T+0) fires for every new signup;
`inactive-nudge` (T+10) only if no first action was taken. `business-welcome-01` /
`community-welcome-01` were previously Postmark-only and are now checked into `templates.php`, so
their copy is exported here for the first time. (The T+2 complete-profile and T+5 activation
nudges are exported above under *Onboarding / activation nudges*.)

### `business-welcome-01` — Welcome (business)
**Subject:** `Welcome to Kolabing`
**Sample model:** `{ first_name: "Joe's Cafe" }` (= business name)

> Hi {{first_name}},
>
> Welcome to Kolabing. You're here to partner with local communities, run clubs, art collectives,
> wellness groups, food crews, and get your venue in front of the people who'll fill it.
>
> **How it works:**
> 1. Finish your profile so communities can find you.
> 2. Post a Kolab: what you're offering (a venue, a discount, a private space) and what you'd like back.
> 3. Review the applications and pick the communities that fit.
>
> You can post your first Kolab in about 3 minutes.
>
> **[Button: Open Kolabing → app]**
>
> *(signature)*

---

### `community-welcome-01` — Welcome (community)
**Subject:** `Welcome to Kolabing`
**Sample model:** `{ first_name: "Barcelona Run Club" }` (= community name)

> Hi {{first_name}},
>
> Welcome to Kolabing. You're here to find local businesses to partner with, venues, discounts,
> private spaces, and perks for your members.
>
> **How it works:**
> 1. Finish your community profile so businesses can find you.
> 2. Browse the open Kolabs and apply to the ones that fit your members.
> 3. Team up, run the event, and build your reputation for the next one.
>
> You can apply to your first Kolab in about 2 minutes.
>
> **[Button: Browse Kolabs → app]**
>
> *(signature)*

---

### `inactive-nudge` — Inactive nudge
**Subject:** `Still worth a look, new collabs near you`
**Sample model:** `{ first_name: "Daniel" }`

> Hi {{first_name}},
>
> You joined Kolabing but haven't started a collab yet. No pressure.
>
> There are new opportunities posted since you signed up that match what you're after. It takes
> two minutes to apply to the first one.
>
> **[Button: See what's open → app]**
>
> *(signature)*

Neither welcome has a `verify_url` slot today (see plan doc — deferred to Phase 5, email
verification is off).

---

## Dynamic variable index (all `{{...}}` merge tokens used above)

| Variable | Used in | Notes |
|---|---|---|
| `{{first_name}}` | nearly every template | Greeting name. **Caveat:** the schema has no personal contact-name field for business/community accounts — `first_name` is populated from `business_profiles.name` / `community_profiles.name` (i.e. the business/community's own name, not a person's), so copy reads "Hi Joe's Cafe," not "Hi Joe,". Attendees do have a real name. |
| `{{reset_url}}` | `password-reset` | Full password-reset link with token. |
| `{{expires_minutes}}` | `password-reset` | Link TTL in minutes (currently 60). |
| `{{support_email}}` | `password-changed` | Support contact address. |
| `{{opportunity_title}}` | `application-received`, `application-accepted`, `application-declined` | The Kolab/opportunity title, also appears in the subject line for `application-received`/`application-declined`. |
| `{{applicant_name}}` | `application-received` | Name of the applying community. |
| `{{partner_name}}` | `application-accepted`, `application-declined`, `collab-confirmed`, `feedback-request` | Name of the counterparty business/community. |
| `{{sender_name}}` | `first-message` | Name of whoever sent the first message; also in the subject line. |
| `{{scheduled_date}}` | `collab-confirmed` | Human-readable collab date. |
| `{{badge_name}}` | `badge-earned` | Also appears in the subject line. |
| `{{reward_name}}` | `reward-won` | Also appears in the subject line. |
| `{{amount_eur}}` | `withdrawal-processed` | Pre-formatted currency string (e.g. `€75.00`); also in the subject line. |
| `{{tier_name}}` | `tier-promotion` | Also appears in the subject line. |
| `{{challenge_name}}` | `attendee-challenge-verified` | Challenge name. |
| `{{points}}` | `attendee-challenge-verified` | Points earned; also appears in the subject line. |

Everything else in the copy above (headings, list items, button labels, the signature block,
the P.S., the footer, and all subject lines minus the tokens listed) is **static** text as
authored — safe to brand-voice-audit and rewrite directly; the merge tokens must stay as
Postmark mustache placeholders (`{{name}}`) with the same variable names if renamed, or the
`model` array passed from `EmailService::send()` in the calling code must be updated to match.

---

*Generated by Clark from `resources/postmark/templates.php` (commit `b50c378`) and
`docs/plans/2026-06-04-transactional-email-system.md`, 2026-07-21, for Jace's brand-voice audit
(handoff `15450189-3f75-48e5-94f7-d338c94574be`). No copy has been altered in this export.*

*Updated 2026-07-27: `business-welcome-01` / `community-welcome-01` were checked into
`templates.php` (previously Postmark-only) and their copy exported; the unused `first-collab-tips`
draft was removed. Counts and the onboarding-drip section reflect the 22 current aliases.*
