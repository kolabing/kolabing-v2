# Kolabing — Pre-launch Backlog

**Last updated:** 2026-06-21
**Sync note:** This file is duplicated in both repos (`kolabing-app` and `kolabing-v2`). Keep the two copies identical.

A consolidated punch list of everything we've identified but haven't shipped. Items are tagged by **owner** (`backend` = kolabing-v2, `app` = kolabing-app, `cross` = both, `infra` = hosting) and **priority** (`P0` = blocker for launch, `P1` = needed soon after, `P2` = nice-to-have).

Each item lists what exists now, what's missing, and any open decisions. See referenced docs / commits for detail.

---

## 1. Email infrastructure (Postmark)

**Status:** Not wired. Only password reset uses Laravel's Password broker default.

**What exists:**
- `config/services.php:17-19` declares Postmark with `POSTMARK_API_KEY` env.
- `config/mail.php:17` default mailer is `'log'` (not Postmark).
- `AuthService:566` sends password reset via `Password::broker()->sendResetLink()`.

**What's missing:**
- No `app/Mail/` Mailables at all. No `app/Notifications/` mail-channel classes. No `resources/views/mail/` templates.
- No `POSTMARK_API_KEY` in `.env.example`.
- Default mailer never flipped to `postmark`.
- No `MustVerifyEmail` on the `Profile` model — email confirmation on signup is not enforced. (Google OAuth manually sets `email_verified_at = now()` per `AuthService:168` so OAuth users skip verification anyway.)

**P0 — must ship before launch:**
- [ ] `Mail/EmailVerification` Mailable + send on registration (email/password path). Owner: **backend**.
- [ ] `Mail/PasswordReset` Mailable to replace Laravel's default reset email (custom branding). Owner: **backend**.
- [ ] `Mail/WelcomeBusiness`, `Mail/WelcomeCommunity` post-onboarding. Owner: **backend**.
- [ ] Wire Postmark: `POSTMARK_API_KEY` to `.env.example`, `MAIL_MAILER=postmark` in production env. Owner: **infra**.
- [ ] Email-style branding template (`emails/layouts/master.blade.php`) so all subsequent Mailables share a header/footer. Owner: **backend**.

**P1 — customer success flows:**
- [ ] "Create your first kolab" nudge — business with no published kolab after N days. Trigger: scheduled command. Owner: **backend**.
- [ ] "Your first application is in" — business gets an email when they receive their first community application. Owner: **backend**.
- [ ] Collab-day + follow-up reminders also as email fallback (push exists already). Owner: **backend**.
- [ ] Subscription receipts on activation / cancellation / past_due (gated on §3 Stripe ship). Owner: **backend**.

**P2 — lifecycle marketing:**
- [ ] Re-engagement after N days inactive — email path complementing push. Owner: **backend**.
- [ ] Monthly "your Kolabing impact" summary for businesses (revenue from feedback, attendance, reviews). Owner: **backend**.

**Open decisions:**
- Single Postmark stream or one stream per type (transactional vs broadcast)?
- Do we mirror every push as an email, or push for time-sensitive + email for value-add?

---

## 2. Push notifications (OneSignal)

**Status:** OneSignal is the live provider since the 2026-05-22 migration (`47decb5`). Core transactional pushes work.

**What exists** (`NotificationService` + `PushNotificationService` + `OneSignalService`):
- `NewMessage`, `ApplicationReceived`, `ApplicationAccepted`, `ApplicationDeclined`, `ChallengeVerified`, `RewardWon`, `CollabDayReminder`, `CollabFollowUpReminder`, `KolabCreateIncomplete`, `ApplicationPending`, `UnreadMessage` — all dispatch correctly.
- Reminders cadence: 2h, 24h, 72h via `NotificationReminder` model + `notifications:send-reminders` command.
- `routes/console.php` schedules `app:send-collab-reminders` daily 08:00 and the new `app:auto-complete-stale-collaborations` daily 03:00.
- iOS/Android deep linking, thread IDs, interruption levels, badge counts all configured.

**P1 — gaps that block real-world use:**
- [ ] **`NotificationPreference` isn't respected.** `Profile` has the relation, the table exists, but no runtime filter inside `NotificationService` — all pushes go regardless of opt-out. Owner: **backend**.
- [ ] **Schedule the reminders cron.** Audit found `notifications:send-reminders` exists but isn't registered in `routes/console.php` (collab reminders are; this one isn't). Owner: **backend**.
- [ ] Trigger the four enum types currently defined but never dispatched: `BadgeAwarded`, `GamificationBadgeEarned`, `PointsEarned`, `WithdrawalProcessed`. Owner: **backend** (wire from `GamificationWalletService` + `WithdrawalService`).
- [ ] Subscription state pushes — renewal succeeded, payment failed, sub cancelled (depends on §3 Stripe ship; Apple IAP webhook should trigger these too). Owner: **backend**.

**P2:**
- [ ] Dormancy re-engagement — push at day 7 / 14 / 30 of inactivity, gated by `profiles.last_active_at`. Owner: **backend**.
- [ ] Drop `kreait/laravel-firebase` once we confirm Apple JWT validation has migrated to a non-Firebase path (Firebase SDK is only used for JWT parsing inside `AppleIAPService` today). Owner: **backend**.

---

## 3. Subscription payments (Stripe + Apple IAP)

**Status:** Apple IAP is **fully shipped** (StoreKit2). Stripe is **stub-only**. Web checkout doesn't exist.

### 3.1 Apple IAP — `P0` confirmed shipped, P2 follow-ups only
**What exists:**
- `app/Services/AppleIAPService.php` — full StoreKit2 implementation: transaction verification, JWS/JWT signature validation, subscription state sync, grace period handling.
- `app/Http/Controllers/Api/V1/AppleIAPController.php` — `POST /me/subscription/apple-verify` + `apple-restore`.
- `app/Http/Controllers/Api/V1/AppleWebhookController.php` — `POST /webhooks/apple`.
- Status transitions for `SUBSCRIBED`, `DID_RENEW`, `DID_FAIL_TO_RENEW`, `EXPIRED`, `REVOKE` correctly mapped.

**Fixes:**
- ~~"Invalid transaction / Could not verify with Apple" on real sandbox purchases — `assertTransactionMatchesRequest` rejected when the client's `original_transaction_id` differed from Apple's verified value. In StoreKit2 every renewal keeps the same `originalTransactionId` (sandbox renews monthly subs in minutes), so the client's value is unreliable. Now only the `productId` is asserted; Apple's authoritative `originalTransactionId` is stored, which also fixes the `DID_CHANGE_RENEWAL_STATUS` "subscription not found" webhook warning. (2026-06-21, tested)~~
- ~~Sentry `file_get_contents(.../storage/app/apple/AuthKey.p8): Failed to open stream` — `AppleIAPService` read the `.p8` key with a raw `file_get_contents`, emitting a PHP warning when the on-disk key was absent on the container. Now resolves the key inline via `APPLE_PRIVATE_KEY` (preferred) with a guarded file fallback; no warning leaks to Sentry. (2026-06-21, tested)~~

**P2 follow-ups:**
- [ ] Fire push + email on subscription state changes (renewal, failure, refund) from `AppleIAPService::handleNotification()`. Owner: **backend**.
- [ ] Drop the `kreait/laravel-firebase` dependency once Apple JWT validation uses a smaller library or a hand-rolled verifier. Owner: **backend**.

### 3.2 Stripe — `P0` build for web checkout
**What exists:**
- `composer.json` has `stripe/stripe-php`.
- `.env.example:77-81` declares `STRIPE_PUBLISHABLE_KEY`, `STRIPE_SECRET_KEY`, `STRIPE_WEBHOOK_SECRET`, `STRIPE_MONTHLY_PRICE_ID`.
- `business_subscriptions` has `stripe_customer_id`, `stripe_subscription_id`, `current_period_*`, `cancel_at_period_end`.
- `SubscriptionSource::Stripe` enum value.

**What's missing — full Stripe build required for web payment:**
- [ ] `StripeService` — create/retrieve customer, create checkout session, sync subscription state from webhook events. Owner: **backend**.
- [ ] `StripeCheckoutController` — `POST /api/v1/me/subscription/create-checkout-session` returning session URL. Owner: **backend**.
- [ ] `StripeWebhookController` — `POST /api/v1/webhooks/stripe`, signature validation, handle `checkout.session.completed`, `customer.subscription.updated`, `invoice.payment_failed`, `customer.subscription.deleted`. Owner: **backend**.
- [ ] `Stripe` block in `config/services.php`. Owner: **backend**.

### 3.3 Web subscription checkout — `P0` for launch (§4 covers the landing-page UI)
Builds on §3.2. Add a public-facing flow:
- [ ] `/pricing` route + Blade page showing the monthly (€39.99) and 3-month plans. Owner: **backend**.
- [ ] "Subscribe" CTA on `/for-businesses` + footer + post-OAuth confirmation page that redirects to Stripe Checkout. Owner: **backend**.
- [ ] Success / cancel return URLs with appropriate user feedback. Owner: **backend**.
- [ ] Email receipt on subscription activation (joins §1.P1). Owner: **backend**.

**Open decisions:**
- Pricing display: monthly only, or both monthly + 3-month plan with comparison?
- Trial period? (None today.)
- VAT handling at checkout for EU customers?

---

## 4. Public marketing site (landing + supporting pages)

**Status:** Polished MVP design quality. Critical conversion infrastructure missing.

**What exists:**
- `routes/web.php`: `/`, `/for-businesses`, `/for-communities`, `/support`, `/careers`, `/privacy`, `/terms`, plus the `/sitemap.xml` + `/llms.txt` + `/.well-known/security.txt` helpers.
- `welcome.blade.php` — 1582-line custom-CSS hero (separate from the Tailwind-CDN layout used by other pages). 7 sections: hero, manifesto, reveal, how-it-works, examples, FAQ, final CTA, footer. Real case-study imagery. Mobile responsive at 900 / 540 breakpoints.
- `for-businesses` / `for-communities` use the Tailwind-CDN `marketing-page` layout.
- Legal pages exist with proper copy.

**P0 — landing-page must-fixes before any traffic push:**
- [ ] **All primary CTAs are broken** (`href="#"`):
  - App Store + Google Play download buttons (hero).
  - "Join as a business" + "Join as a community" (final CTA).
  Owner: **backend** (wire to actual store URLs + the new `/pricing` flow from §3.3).
- [ ] **GDPR cookie banner.** Target market is EU (Barcelona). Operating without consent management is a hard legal risk. Owner: **backend**.
- [ ] **`/pricing` page** (covered in §3.3).
- [ ] Analytics instrumentation — at minimum GA4 or Plausible page-view tracking; conversion events on Subscribe + sign-up. Owner: **backend**.

**P1 — site polish:**
- [ ] Decide on the `welcome.blade.php` vs Tailwind-CDN duality. Either migrate the hand-CSS landing to Tailwind, or move all marketing pages to a single Vite-built Tailwind bundle. Today they coexist in two different tech stacks. Owner: **backend**.
- [ ] Waitlist email-capture form (Klaviyo / ConvertKit / a simple `waitlist_signups` table — the migration was scoped earlier on the `fixes-and-improvements` branch but was dropped with the stash). Useful for collecting interest pre-Stripe. Owner: **backend**.
- [ ] Real-user testimonials (currently using case studies only). Owner: **content**.
- [ ] FAQ extension to cover pricing + payment + cancellation (currently silent on these). Owner: **backend** (copy) once §3.3 ships.

**P2:**
- [ ] Localised landing pages (Spanish first, given Barcelona launch). Owner: **backend**.
- [ ] Open Graph / Twitter Card metadata for share previews. Owner: **backend**.
- [ ] `Organization` JSON-LD with `priceRange` once pricing is public. Owner: **backend**.

---

## 5. Database region — move to EU

**Status:** Currently US-east-1 by env default; production hosting location not auditable from the repo.

**What we know:**
- `.env.example` sets `AWS_DEFAULT_REGION=us-east-1`.
- Real production DB region depends on the hosting environment (Forge / Vapor / managed RDS / Supabase). Not in repo.
- Target market is EU (Barcelona) — latency and GDPR data-residency both push toward `eu-west-1` (Ireland) or `eu-west-3` (Paris).

**P1 (becomes P0 once we start collecting any real EU PII):**
- [ ] Confirm current production DB region with whoever manages the hosting account (Daniel). Owner: **infra**.
- [ ] If US, plan migration: snapshot → restore in EU region → swap DNS / connection string with brief read-only window. Estimate ~30 min downtime for a small DB. Owner: **infra**.
- [ ] Update `AWS_DEFAULT_REGION` in production env post-migration. Owner: **infra**.
- [ ] Verify backups remain in EU after the move. Owner: **infra**.
- [ ] Bake an EU-region constraint into the deployment runbook so future spin-ups don't drift back to US. Owner: **infra**.

**Code impact:** ~zero. This is an infrastructure change.

---

## 6. Profile picture zoom / scale

**Status:** No-op on backend. App-side feature.

**What exists:**
- Backend: `ProfileController:64` uploads via `FileUploadService` → stores `business_profiles.profile_photo` / `community_profiles.profile_photo`. `PublicProfileResource::absoluteUrl()` serves the URL.
- App: `lib/features/profile/screens/public_profile_screen.dart` + `profile_reviews_screen.dart` show profile photos via `CachedNetworkImage` / `CircleAvatar` + `NetworkImage`. No `InteractiveViewer` / `PhotoView` wrapping.

**P1:**
- [ ] Wrap the profile-photo display widgets with a tap-to-open full-screen `PhotoView` (or `InteractiveViewer`) showing the high-res image with pinch-zoom + pan. Owner: **app**.
- [ ] Apply the same pattern to `public_profile_screen.dart`, `profile_reviews_screen.dart`, and `past_collaboration_card.dart`. Owner: **app**.

**P2 (backend follow-up if needed for performance):**
- [ ] Multi-size serving — when the photo's pixel dimensions are large, serve a thumb + a full version. Likely not needed in v1 since `FileUploadService` already caps sizes; verify. Owner: **backend**.

**Open decisions:**
- Use the existing `cached_network_image` instance for the zoomed view too, or load a separate full-res variant?
- Show a download / share affordance on the zoom modal?

---

## 7. Admin dashboard gaps

Cross-references to PRs and plans already on file — listed here so they don't get lost.

- [ ] **Gamification admin section** (entire `/admin/gamification/*` subtree) — see [docs/plans/2026-06-01-admin-followups.md](plans/2026-06-01-admin-followups.md). Five PRs planned (XP rules + config endpoint, economics, challenges, bonus rewards, badges) with several open decisions still to land. Owner: **backend**.
- [ ] **Lifecycle filter UX fix** on `/admin/kolabs` — rename "Matched" → "Pending match", add composite "Has match" + "Completed or done" filters. Same plan doc. Owner: **backend**.
- [ ] **Admin stats — feedback metrics** — `PlatformStatsService::quality()` currently reads `collaboration_reviews`. Extend to surface the new `collaboration_feedback` columns (revenue, expectation_match %, would_recommend %) with the `mirrored_from_review` filter for clean rich aggregates. Tied to PR #9. Owner: **backend**.
- [ ] **`Admin\ManagedUserController::destroy()`** should run the full `ProfileService::deleteProfile()` cleanup transaction, not a bare soft-delete. Currently bypasses email-free + collab-cancel side effects. Owner: **backend**.

---

## 8. Pre-existing role surface debt

From [docs/ROLES-BACKEND-DB-MAP.md](ROLES-BACKEND-DB-MAP.md) §8 — still-open items. Repeated here so they're visible from the launch backlog too.

- [ ] **Implement the Explore blur** for free businesses (golden rules 4 + 5). Server should emit an `identity_locked` flag; client renders an actual blur, not a hard block. Owner: **cross**.
- [ ] **Add `coliving` to `BusinessOnboardingRequest::BUSINESS_TYPES`** — it's in the spec but rejected at validation today. Owner: **backend**.
- [ ] **Attendee role scope decision** — code is shipped (gamification, wallet, badges, checkin) but `ROLES-AND-PERMISSIONS.md §7` still marks it as `[VERIFY]`. Decide: in or out of launch; pricing; whether the attendee wallet redeems to cash. Owner: **product (Daniel)**, then **backend** / **app**.

---

## 9. Legacy `collab_opportunities` removal (canonical `/kolabs`)

**Status:** Table-level code archived (#30, in flight). Table drop + shim removal deferred to #31.

**Incomplete (in flight):**
- [ ] **Remove legacy `collab_opportunities` table-level code (archive table)** — `kolabing-v2` #30 (this PR). Deletes `CollabOpportunity`, the bridge services, migrate command, factory, seeder, dead resources, and the `collab_opportunity_id` dual-write; adds `GET`/`POST /api/v1/kolabs/{kolab}/applications`. The `collab_opportunities` table + `collab_opportunity_id` columns stay physically (archived). The `/opportunities` API shim is intentionally retained. Owner: **backend**.

**Follow-up (new):**
- [ ] **Remove `/opportunities` API shim + port freemium limit & portfolio photos to `/kolabs` + drop `collab_opportunities` table** — #31 (gated on mobile `kolabing-app` #20). The freemium collab limit + portfolio-photo handling currently live only on the legacy `OpportunityService` path; port them onto `/kolabs` create, then retire `OpportunityController`/`OpportunityService` and drop the archived table + `collab_opportunity_id` columns. Owner: **backend** (after mobile migrates off `/opportunities`).

---

## How to use this file

- One canonical list across both repos. Mirror any edit in both — keep the files byte-identical (`diff -q` should be empty).
- When something ships, move the item to a `## Shipped` section at the bottom rather than deleting it, so the launch history stays visible.
- When picking up an item, open a PR with a description that links here so the backlog is self-documenting.

*Owner labels: `backend` = `kolabing-v2`, `app` = `kolabing-app`, `cross` = both, `infra` = hosting account, `product` = Daniel.*
