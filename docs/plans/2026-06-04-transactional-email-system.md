# Transactional Email System — Plan (Phase 1 + 2)

**Created:** 2026-06-04
**Repo:** `kolabing-v2` (Laravel backend) — email is server-side; the Flutter app is not involved.
**Scope this doc (first build):** Phase 0 (activate Postmark) + Phase 1 (infrastructure spine) +
Phase 2 (core transactional emails — catalog A/B/C/D per Daniel's 2026-06-04 selection).
Phases 3 (billing E), 4 (digests F), 5 (turn verification on) are planned follow-ups, **out of scope** for the first build.

---

## Decisions locked (2026-06-04)

| Decision | Choice |
|----------|--------|
| Email verification | Build the welcome email to **support a verify link**, but keep verification **OFF** (no gating) until Daniel validates the visual template. Flip on later. |
| Template location | **Postmark templates** (dashboard). Backend sends `template alias + merge vars`; copy/design lives in Postmark. |
| "Revenue" in digests | Comes from the **self-reported `collaboration_feedback.revenue`** field (`decimal:2`). Not Stripe. (Digest is Phase 3.) |
| First scope | **Phase 1 + 2** (infra spine + welcome, branded password reset, lifecycle/gamification mirror emails). |

---

## Build status — Phase 0 + 1 (2026-06-04)

**Code complete and verified** (lint clean, container resolves, command registered):
- `config/services.php` — `postmark.{key,from,from_name,message_stream}`.
- `app/Services/PostmarkClient.php` — Postmark HTTP client (`sendTemplate` + `sendRaw`).
- `app/Services/EmailService.php` — preference-gated dispatch (categories → flags).
- `app/Jobs/SendTransactionalEmail.php` — queued delivery (mirrors `SendPushNotification`).
- `app/Providers/AppServiceProvider.php` — `PostmarkClient` singleton binding.
- `app/Console/Commands/SendTestEmail.php` — `php artisan email:test <addr...> [--queue]`.
- `.env` / `.env.example` — `POSTMARK_API_KEY`, `POSTMARK_MESSAGE_STREAM_ID` documented.

**Live send verified (2026-06-04):** `POSTMARK_API_KEY` set, `MAIL_FROM_ADDRESS=info@kolabing.com`,
stream `outbound`. `php artisan email:test` delivered a raw test through the real pipeline to
daniel@serrawealth.com, info@kolabing.com, mariapd98@gmail.com, volkanoluc@gmail.com — all accepted
by Postmark (2xx). Token lives only in gitignored `.env`.

**Templates created (2026-06-04):** all 19 Phase 2 transactional templates authored (Maria voice,
`business-welcome-01` scaffold) and pushed to Postmark via `php artisan email:sync-templates`. Seed
lives at `resources/postmark/templates.php` + command `app/Console/Commands/SyncPostmarkTemplates.php`
(create-missing by default; `--force` to overwrite; `--test=<addr>` to send samples). Postmark is the
source of truth for copy going forward. Full sample set test-sent to daniel@serrawealth.com — pending
Daniel's copy feedback.

Aliases live in Postmark: attendee-welcome-01, password-reset, password-changed,
complete-profile-{business,community}, activation-{business,community}, attendee-activation-01,
application-{received,accepted,declined}, first-message, collab-confirmed, feedback-request,
badge-earned, reward-won, withdrawal-processed, tier-promotion, attendee-challenge-verified
(+ existing business-welcome-01, community-welcome-01).

**Still deferred to the wiring step (Phase 2 code):** the hook into
`NotificationService::createNotification()` (step 1.4) + new `NotificationType` values
(`collab_confirmed`, `feedback_request`, `tier_promotion`) + `EmailService::send` calls at each seam.
Billing (E) + digests (F) remain later phases (data-dependent).

## Current state (verified)

- `MAIL_MAILER=log`; `POSTMARK_API_KEY` not in repo env. Postmark transport defined in
  `config/mail.php` + `config/services.php:17` but inactive.
- No `app/Mail/`, no `app/Notifications/`, no `toMail`, no listeners.
- Only live email: Laravel's stock `ResetPassword` notification; URL customized in
  `app/Providers/AppServiceProvider.php:45` → `{APP_URL}/reset-password?token=..&email=..`.
- Foundations to reuse:
  - `Profile` holds `email` and uses `Notifiable`.
  - `NotificationService::createNotification()` (`app/Services/NotificationService.php:70`)
    is the single funnel for in-app + push; it dispatches `SendPushNotification` (queued, `database`).
  - `NotificationPreference` flags (defaults): `email_notifications`=true,
    `new_application_alerts`=true, `collaboration_updates`=true, `marketing_tips`=false,
    `whatsapp_notifications`=true.
  - Scheduler live in `routes/console.php` (4 commands).
  - `NotificationType` enum has 15 values already.

---

## Phase 0 — Activate Postmark (config only, on server; no code)

1. Set on production/staging env (NOT committed):
   - `MAIL_MAILER=postmark`
   - `POSTMARK_API_KEY=<server token>`
   - `MAIL_FROM_ADDRESS=hello@kolabing.com`, `MAIL_FROM_NAME=Kolabing`
   - Optional: `POSTMARK_MESSAGE_STREAM_ID=outbound` (uncomment in `config/mail.php:58`).
2. Verify Postmark sender signature / DKIM for `kolabing.com` is confirmed.
3. Smoke test: trigger `forgot-password` → confirm delivery via Postmark Activity.

---

## Phase 1 — Infrastructure spine

Goal: a reusable, preference-gated, queued path to send a Postmark **template** email to a `Profile`.

### 1.1 Postmark templated-email transport helper
- Add a thin `PostmarkTemplateMailer` (Support or Services) that calls Postmark's
  **`/email/withTemplate`** API with `TemplateAlias` + `TemplateModel` (merge vars).
  - Use the Postmark PHP SDK (`wildbit/postmark-php`) OR a direct `Http::withToken` POST to
    `https://api.postmarkapp.com/email/withTemplate` with header `X-Postmark-Server-Token`.
  - Decision note: Laravel's `postmark` mail transport (Symfony) sends *rendered* mail, not
    template-alias mail. Since copy lives in Postmark, we call the Postmark template API
    directly rather than through `Mail::send`. Keep this isolated in one class.
- Config: read token from `config('services.postmark.key')`. Add `from`/stream from config.

### 1.2 `EmailService` (gating + dispatch)
`app/Services/EmailService.php`:
```
send(Profile $recipient, string $templateAlias, array $model, string $category): void
```
- **Preference gate** (`shouldSend`):
  - If `email_notifications` is false → skip (master switch).
  - Map `$category` → specific flag:
    - `application` → `new_application_alerts`
    - `collaboration` → `collaboration_updates`
    - `marketing`/`digest`/`nudge` → `marketing_tips`
    - `account`/`security` (welcome, password reset/changed, verify) → **always send**,
      ignore prefs (transactional/security; never opt-outable).
  - Missing `NotificationPreference` row → treat as defaults (send).
- Dispatch async via a new `SendTransactionalEmail` job.

### 1.3 `SendTransactionalEmail` job
- Mirror `app/Jobs/SendPushNotification.php`: `ShouldQueue`, `$tries=3`, `$backoff=10`,
  same `database`-on-sync fallback (`SendPushNotification.php:32`).
- `handle()` calls `PostmarkTemplateMailer`. Swallow+log Postmark failures (don't break the
  request that triggered it).

### 1.4 Wire into the notification funnel
- In `NotificationService::createNotification()`, after `SendPushNotification::dispatch(...)`,
  add an **optional** email side-effect driven by a `NotificationType → (templateAlias, category)`
  map. Only types in the map get emails (so chat messages, etc., can be excluded/batched later).
- Keep email failures isolated from push (separate try/catch / separate job).

### 1.5 Ensure preferences exist
- Confirm a `NotificationPreference` row is created at registration (business/community/attendee);
  if not, create-on-demand with defaults inside the gate. (Check `AuthService::register*`.)

### Phase 1 acceptance
- Calling `EmailService::send(...)` with a real Postmark alias delivers, respects the master
  switch + category flag, runs on the queue, and never throws into the caller.

---

## Phase 2 — Core transactional emails (FINAL selection, 2026-06-04)

Selection confirmed by Daniel against the pitch catalog (A–G). Numbers below are the catalog #s.
For each: **trigger seam**, **recipient**, **gate**, **Postmark alias**, key vars.
Aliases are proposals; final names set when templates are created in Postmark.

### A. Account & security — gate: ALWAYS send (transactional/security, no opt-out)

**Existing Postmark templates (verified live 2026-06-04):**
- `business-welcome-01` — subj "Your first match is 10 minutes away" — model: `{ first_name }`
- `community-welcome-01` — subj "Your first Kolab is 10 minutes away" — model: `{ first_name }`
- (No attendee welcome, no password-reset/changed templates yet — create in Postmark for Phase 2.)
- **Neither welcome template has a `verify_url` slot.** When verification is turned on (Phase 5),
  add the slot to both templates.

| # | Email | Trigger | Recipient | Alias | Model |
|---|-------|---------|-----------|-------|-------|
| 1 | Welcome (business) | `AuthService::registerBusiness()` end | new business | `business-welcome-01` | `first_name` (= business name, no personal name in schema) |
| 1 | Welcome (community) | `AuthService::registerCommunity()` end | new community | `community-welcome-01` | `first_name` (= community name) |
| 1 | Welcome (attendee) | `AuthService::registerAttendee()` end | new attendee | `attendee-welcome-01` (TBD — create) | `first_name` |
| 1 | Welcome (OAuth new user) | `AuthService::registerNewUser()` when `is_new_user` | new user | reuse by type | `first_name` |
| 3 | Password reset | already fires; **swap to Postmark template** | any | `password-reset` (TBD — create) | reset_url, first_name, expires_minutes (60) |
| 4 | Password changed | `AuthService::resetPassword()` success callback | any | `password-changed` (TBD — create) | first_name, time, support_url |

> **`first_name` caveat:** schema has no personal contact name — only `business_profiles.name` /
> `community_profiles.name`. Pass that as `first_name` (e.g. "Hi Joe's Cafe"), or add a contact-name
> field later if a personal greeting is wanted.
> **#2 Verify email (deferred):** add a `verify_url` slot to the welcome templates when turning it on
> (Phase 5). **#5 login/device alert: OUT** (needs device tracking that doesn't exist).

### B. Onboarding / activation nudges — gate: `email_notifications` master only (NOT `marketing_tips`)

> Rationale: these are activation, not marketing. Gating under `marketing_tips` (default **false**)
> would silence them for most users. Confirm this gating choice.

| # | Email | Trigger | Recipient | Alias | Notes |
|---|-------|---------|-----------|-------|-------|
| 6 | Finish your profile | scheduled command; profile incomplete N days after signup | business/community | `complete-profile` | No `profile_completed` flag — infer from null name/key fields. Reuse existing `kolab_create_incomplete` pattern. Send once (track sent). |
| 7 | Activation nudge | scheduled; onboarded but no opportunity (biz) / no application (community) after N days | business/community | `activation-business` / `activation-community` | Send once. |

### C. Collaboration lifecycle — gate: `collaboration_updates` (except #8 = `new_application_alerts`)

| # | Email | Trigger (seam) | Recipient | Push today? | Alias |
|---|-------|----------------|-----------|-------------|-------|
| 8 | New application received | `ApplicationService::apply()` ~:49 | opportunity creator | yes | `application-received` |
| 9 | Application accepted | `ApplicationService::accept()` ~:97 | applicant | yes | `application-accepted` |
| 10 | Application declined | `ApplicationService::decline()` ~:128 | applicant | yes | `application-declined` |
| 11 | New message — **FIRST message only** | `ChatService::sendMessage()` ~:61, **only if no prior `chat_message` exists for that application** | other party | yes (every msg) | `first-message` |
| 12+13 | **Collaboration confirmed** (MERGED) | when collaboration created/scheduled on accept | both parties | NO → **add push too** | `collab-confirmed` |
| 14 | Day-of reminder | `SendCollabReminders` | both | yes | **NO email** (push only) |
| 15 | **Give us feedback** (reframed follow-up; absorbs #18) | `SendCollabReminders` follow-up / after scheduled_date passes | both (if not yet given feedback) | yes (followup push) | `feedback-request` — CTA: open app → add feedback |

> **#11 first-message gate:** email fires only on the conversation's first message; all later messages
> are push only. Need a "first message in thread?" check (no prior `chat_message` for the application).
> **#12+#13 MERGED** per Daniel: one "your collaboration is confirmed" notification (email + push),
> fired once when the collab is created. (Was: separate "scheduled" + "now live".) — CONFIRM merge.
> **#14 day-of: no email.** **#16 completed: no. #17 cancelled: no. #18 review: folded into #15.**

### D. Gamification — gate: `email_notifications` master only (no dedicated flag)

> **Recipient correction (Daniel, 2026-06-04):** the reward/wallet/withdrawal economy is
> **community-leader only — NOT attendees.** Attendees earn points/badges; they do not have a
> wallet or cash-out. So #20 reward-won and #21 withdrawal-processed go to **community leaders**.

| # | Email | Trigger | Recipient | Push today? | Alias |
|---|-------|---------|-----------|-------------|-------|
| 19 | Badge earned | `GamificationWalletService` badge-earned | attendee / community | yes | `badge-earned` |
| 20 | Reward won | reward win (spin) | **community leader** | yes | `reward-won` |
| 21 | Withdrawal/cash-out processed | withdrawal processed | **community leader** | yes | `withdrawal-processed` |
| 22 | Tier promotion | `EvaluateCommunityTiers` promotion | promoted member | partial | `tier-promotion` |

### D2. Attendee emails (final, Daniel 2026-06-04)

Attendees = community members (gamification loop: join → events/challenges → points/badges/rank).
**No wallet/withdrawal/reward emails for attendees** (those are community-leader, see above).

| # | Email | Trigger | Notif type | Alias | Model | Gate |
|---|-------|---------|-----------|-------|-------|------|
| A1 | Welcome (attendee) | `AuthService::registerAttendee()` | — | `attendee-welcome-01` | `first_name` | always |
| A2 | Activation nudge | scheduled; no community joined / no challenge after N days | — | `attendee-activation-01` | `first_name` | marketing |
| A3 | Challenge verified (points earned) | challenge approved | `challenge_verified` | `attendee-challenge-verified` | `first_name`, `challenge_name`, `points` | gamification |
| A4 | Badge earned | badge awarded | `gamification_badge_earned` | `attendee-badge-earned` | `first_name`, `badge_name` | gamification |

Welcome copy drafted (Maria voice, mirrors `business-welcome-01` scaffold), subject
"Your first points are one event away". Pending: create template in Postmark + sign-off.

### New `NotificationType` values to add
- `collab_confirmed` (the merged #12+13; also needs **push** added) — fired in collaboration-create seam.
- `feedback_request` (#15) — fired in `SendCollabReminders` follow-up path.
- `tier_promotion` (#22) — if not already emitted by `EvaluateCommunityTiers`.

(Existing types already cover #8–#11, #19–#21: `application_received`, `application_accepted`,
`application_declined`, `new_message`, `reward_won`, `withdrawal_processed`, badge type.
Add to `app/Enums/NotificationType.php`; map each emailing type → (alias, category) in step 1.4.)

### Phase 2 acceptance
- Each trigger sends the right template to the right recipient(s), gated by the right flag.
- A/account + security emails ignore prefs; C lifecycle respects `collaboration_updates`
  (#8 respects `new_application_alerts`); B nudges + D gated by `email_notifications` master.
- #11 sends an email on a thread's **first** message only; later messages = push only.
- #12+13 fires **one** confirmed notification (email + push) on collab creation.
- #15 follow-up is a "give feedback" CTA email; #14/#16/#17 send **no** email.

---

## Follow-up phases (planned next, after the first build ships)

### Phase 3 — Subscription / billing emails (catalog E: 23, 25, 26; NOT 24)
Business-only. Depends on **Stripe-webhook ingestion** (no price/invoice/amount data exists today —
`BusinessSubscription` stores status + Stripe/Apple IDs only).
- **23** Subscription activated / receipt — on activation.
- **25** Payment failed / past due (dunning) — on `past_due`.
- **26** Subscription cancelled / expired — on cancel/expiry.
- (**24** renewal reminder — explicitly OUT.)
- Gate: account/billing (always send — transactional). Requires a Stripe webhook controller +
  an invoices/payments table (new). Sketch as its own sub-plan.

### Phase 4 — Periodic digests (catalog F: 27–30) — gate: `marketing_tips` (27–29)
New scheduled commands reusing `DashboardService` + `Admin/PlatformStatsService`.
- **27** Business monthly summary — opportunities, applications, collabs, avg rating, upcoming,
  **+ `SUM(collaboration_feedback.revenue)` for the window** (self-reported; label "revenue you reported").
- **28** Community monthly summary — applications, collabs, ratings, points/badges/tier.
- **29** Win-back / re-engagement — inactive users via `profiles.last_active_at`.
- **30** Internal platform report (to Daniel/admin) — `PlatformStatsService`; no user gating.

### Phase 5 — Turn email verification ON (catalog #2)
Once Daniel approves the welcome visual: enable `MustVerifyEmail`, add verify route/notification,
flip the `verify_url` slot on.

---

## Risk / sequencing notes

- Keep Postmark template-API calls in **one** class; do not scatter HTTP calls.
- Email must **never** break the triggering request — always queued, failures logged only.
- #11 first-message detection must be cheap (exists-query on `chat_messages` by application).
- `revenue` (Phase 4) is **business-self-reported per collaboration** (`decimal:2`); `SUM` feedback rows
  the business authored in the window. Label "revenue you reported", not platform revenue.
- Confirm two gating choices flagged above: (a) B nudges under `email_notifications`, not `marketing_tips`;
  (b) the #12+#13 merge into one `collab_confirmed`.
- Verify each `file:line` seam before editing — line numbers are from a 2026-06-04 read and may drift.
