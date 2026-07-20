# Kolabing — Pre-launch Backlog

**Last updated:** 2026-07-20 (business gating audit fixes — no free kolab (autopost is the only free business post) + community identity withheld server-side from free businesses (feed mask + profile-endpoint 403); §8. Prior: legal: company/legal details + agreement version now admin-editable at `/admin/company-settings` (`company_settings` table); legal pages render live values — §4. Prior: bilingual Terms/Privacy (EN + `/es`) + mobile consent tracking — §4. Prior: query-audit N+1 sweep — §12)
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
- **Collaboration completion-flow notifications (shipped 2026-06-23):** five new dispatched types — `CollaborationCreated`, `CollaborationActivated`, `CollaborationFeedbackReceived`, `CollaborationCompleted`, `CollaborationCancelled`. Each notifies **both** parties (actor gets actor-aware copy, counterpart gets "{name}…" copy); `target_type='collaboration'`, `target_id=collaboration.id`. Wired from `CollaborationService` (createFromApplication/activate/complete/adminForceComplete/autoComplete/cancel — autoComplete uses the no-actor "automatically marked complete" copy), `ApplicationService::accept()` (Created), and `CollaborationFeedbackService::submit()` (FeedbackReceived). Each dispatch is wrapped in `try/catch + report($e)` so a push failure never breaks the state transition.
- **Deeplink fix (2026-06-23):** the five new types **and** the existing `CollabDayReminder` / `CollabFollowUpReminder` now resolve to `/collaboration/{id}` in `PushNotificationService::resolveDeeplink()` (the two reminders previously fell through to `/notifications`). Mirrored byte-identical in `kolabing-app` (`feat/collaboration-completion-notifications`). `resolveDeeplink()` now also has a `default => '/notifications'` arm so a future `NotificationType` can never throw `UnhandledMatchError`.
- **Notifications are now localized server-side per recipient (2026-06-23):** every dispatched notification title/body is resolved in the *recipient's* `profiles.preferred_locale` (en / es / ca, fallback en) via `NotificationService::createLocalizedNotification()` + `lang/{en,es,ca}/notifications.php`. `notifyBothParties` resolves copy per recipient inside its loop (each party may differ in locale and in actor-vs-counterpart copy). The mobile app sends `locale` on `POST /api/v1/device-token`, persisted to `profiles.preferred_locale` (validated `in:en,es,ca`). All call sites localized: every `notify*` in `NotificationService`, plus `EventSignupService` (waitlist promoted), `CommunityJoinRequestService`, `Admin\CommunityVerificationService`, `GamificationWalletService`, `BadgeService`, `ProfileService` (account-deletion collaboration-cancelled). English values are byte-identical to the previous hardcoded strings.
- **Withdraw notification added (2026-06-23):** new `NotificationType::ApplicationWithdrawn = 'application_withdrawn'`. `ApplicationService::withdraw()` now notifies the kolab creator/business (primary) and confirms to the withdrawing applicant (secondary), wrapped in `try/catch + report`. Deeplink resolves to `/application/{id}`. The mobile app is adding the same enum string in parallel.
- **Admin force-complete copy fix (2026-06-23):** `CollaborationService::adminForceComplete` now passes a `null` actor so both parties receive the actor-less "was automatically marked complete" copy (previously the business falsely saw "You marked … complete" for a maintainer action).
- Reminders cadence: 2h, 24h, 72h via `NotificationReminder` model + `notifications:send-reminders` command.
- `routes/console.php` schedules `app:send-collab-reminders` daily 08:00 and the new `app:auto-complete-stale-collaborations` daily 03:00.
- iOS/Android deep linking, thread IDs, interruption levels, badge counts all configured.

**P1 — gaps that block real-world use:**
- [ ] **`NotificationPreference` isn't respected.** `Profile` has the relation, the table exists, but no runtime filter inside `NotificationService` — all pushes go regardless of opt-out. Owner: **backend**.
- [ ] **Schedule the reminders cron.** Audit found `notifications:send-reminders` exists but isn't registered in `routes/console.php` (collab reminders are; this one isn't). Owner: **backend**.
- [ ] Trigger the remaining enum types currently defined but never dispatched: `PointsEarned`, `WithdrawalProcessed`. Owner: **backend** (wire from `GamificationWalletService` + `WithdrawalService`). (`BadgeAwarded` + `GamificationBadgeEarned` are dispatched as of the gamification work and are now localized.)
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
- `routes/web.php`: `/`, `/for-businesses`, `/for-communities`, `/support`, `/careers`, `/privacy`, `/terms`, `/reset-password` (password reset form + handler), plus the `/sitemap.xml` + `/llms.txt` + `/.well-known/security.txt` helpers.
- `welcome.blade.php` — 1582-line custom-CSS hero (separate from the Tailwind-CDN layout used by other pages). 7 sections: hero, manifesto, reveal, how-it-works, examples, FAQ, final CTA, footer. Real case-study imagery. Mobile responsive at 900 / 540 breakpoints.
- `for-businesses` / `for-communities` use the Tailwind-CDN `marketing-page` layout.
- Legal pages (`/terms`, `/privacy`) now carry full GDPR/LOPDGDD copy in **English + Spanish** (`/es/terms`, `/es/privacy`) with `hreflang` alternates + per-page language toggle; the `welcome.blade.php` footer legal links were wired (were `href="#"`). Company/legal identity (name, address, reg number, refund policy, contact emails) **and the agreement version + effective date** are now **admin-editable** at `/admin/company-settings` (single-row `company_settings` table via `CompanySettingService`, injected into the pages by a view composer). Empty fields fall back to `[PLACEHOLDER]` text; bumping the version re-prompts app users for consent.

**Fixes:**
- ~~`/reset-password` 404'd — the password-reset email links to `{APP_URL}/reset-password?token=&email=` (`AppServiceProvider`) but no web route/page existed. Added GET form + POST handler (`PasswordResetPageController`) on the marketing layout, posting through `AuthService::resetPassword`. (2026-06-21, tested)~~
- [x] ~~**Legal agreements + mobile consent tracking** — `/terms` + `/privacy` rewritten with full GDPR/LOPDGDD copy (data-controller placeholders) + Spanish `/es/*` mirrors + `hreflang`; footer legal links wired. Consent recorded per profile (`profiles.terms_accepted_at`/`terms_version` vs `config('legal.terms_version')`); `accepted_terms` (`required|accepted`) enforced on all `register/*` endpoints; OAuth signups stamped in `AuthService::consentStamp()`; `GET /auth/me` returns a `terms` re-consent block; `POST /me/consent` (`ConsentController`) re-accepts. App flow: `docs/mobile-consent-flow.md`. Mirror the app-side flow in `kolabing-app`. (2026-07-12, tested)~~

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
- [x] ~~**Gamification mission system v1** — `challenges.app_visible` curation column + atomic `challenge_progress` upsert + wallet-service delegation + `isLive()` trigger guard + DTO refactor + the curated 18-mission v1 set (5 attendee / 7 business / 6 community), all on live triggers. Event challenges (peer-verified, `trigger_action IS NULL`) and general missions (auto-tracked, `trigger_action IS NOT NULL`) kept separate across three filter sites (`SystemChallengeController`, `Admin\ChallengeDefaultsController`, `ChallengeService::listForEvent()`). See `docs/plans/2026-06-22-gamification-mission-system.md` and `ROLES-BACKEND-DB-MAP.md §11.1`. Owner: **backend**. (2026-06-27)~~ Remaining ⧖ triggers (profile completion, kolab publish, application accept, etc.) and wider `app_visible` rollout stay tracked in the plan doc, not here.

---

## 8. Pre-existing role surface debt

From [docs/ROLES-BACKEND-DB-MAP.md](ROLES-BACKEND-DB-MAP.md) §8 — still-open items. Repeated here so they're visible from the launch backlog too.

- [x] ~~**Implement the Explore blur** for free businesses (golden rules 4 + 5).~~ **DONE 2026-07-20 (backend):** identity is now withheld **server-side** for a non-subscribed business viewing a community — feed nulls name/logo + emits `identity_locked`, and `GET /profiles/{id}` + `GET /communities/{id}/public-profile` return 403. Client already blurs + gates navigation over the stripped payload. Owner: **backend** (done) / app (optional: trust the server `identity_locked` flag).
- [x] ~~**Businesses get 1 free kolab (backend leak).**~~ **DONE 2026-07-20 (backend):** removed the free-kolab grant — every self-initiated business publish requires a subscription; the single onboarding auto-offer is the only free published business post. Brings the backend in line with §2.5 / the capability matrix. Owner: **backend**.
- [ ] **Add `coliving` to `BusinessOnboardingRequest::BUSINESS_TYPES`** — it's in the spec but rejected at validation today. Owner: **backend**.
- [ ] **Attendee role scope decision** — code is shipped (gamification, wallet, badges, checkin) but `ROLES-AND-PERMISSIONS.md §7` still marks it as `[VERIFY]`. Decide: in or out of launch; pricing; whether the attendee wallet redeems to cash. Owner: **product (Daniel)**, then **backend** / **app**.

---

## 9. Legacy `collab_opportunities` removal (canonical `/kolabs`)

**Status:** Table-level code archived (#30, in flight). Table drop + shim removal deferred to #31.

**Incomplete (in flight):**
- [ ] **Remove legacy `collab_opportunities` table-level code (archive table)** — `kolabing-v2` #30 (this PR). Deletes `CollabOpportunity`, the bridge services, migrate command, factory, seeder, dead resources, and the `collab_opportunity_id` dual-write; adds `GET`/`POST /api/v1/kolabs/{kolab}/applications`. The `collab_opportunities` table + `collab_opportunity_id` columns stay physically (archived). The `/opportunities` API shim is intentionally retained. Owner: **backend**.

**Follow-up (new):**
- [ ] **Remove `/opportunities` API shim + port freemium limit & portfolio photos to `/kolabs` + drop `collab_opportunities` table** — #31 (gated on mobile `kolabing-app` #20). The freemium collab limit + portfolio-photo handling currently live only on the legacy `OpportunityService` path; port them onto `/kolabs` create, then retire `OpportunityController`/`OpportunityService` and drop the archived table + `collab_opportunity_id` columns. Owner: **backend** (after mobile migrates off `/opportunities`).

## 10. Restore/create BACKEND-SCHEMA.md

**Status:** Missing. Referenced by `kolabing-v2/CLAUDE.md` as a MUST-READ schema doc. Currently absent from the repo.

**What exists:**
- `ROLES-BACKEND-DB-MAP.md` serves as the closest interim schema/reference doc and is kept up to date for role-affecting tables.

**What's missing:**
- The `docs/BACKEND-SCHEMA.md` file itself (likely existed earlier, was deleted or archived).

**P2 (out of scope for this PR):**
- [ ] Restore from git history or create a new `docs/BACKEND-SCHEMA.md` that documents the full production Postgres schema, replacing the interim reference in `ROLES-BACKEND-DB-MAP.md` with the authoritative schema doc. Owner: **backend**.

---

## 11. Business Kolab creation flow redesign

**Status:** Backend half done (this PR, `feat/business-kolab-flow-backend`): `goal`/`highlights` columns on `kolabs`, 4 new admin-managed `OfferOption` taxonomy kinds (goal/product_interaction/venue_fit/kolab_highlight) + lookup endpoints, expanded `deliverable` options, immediate-availability validation fix. Frontend half (8 screen reworks in `kolabing-app`) not started yet — plan at `docs/superpowers/plans/2026-06-24-business-kolab-flow-frontend.md` in `kolabing-app`. Design spec: `docs/superpowers/specs/2026-06-24-business-kolab-creation-flow-redesign.md`.

**Incomplete (in flight):**
- [ ] `kolabing-app`: implement the 8 frontend tasks (Goal step, offer-first Offering reorder, dynamic best-fit-community/venue-fit/product-interaction chips, Past Events relabel + highlights, immediate availability mode, media defaulting, review restyle). Owner: **app**.

---

## 12. Database performance & indexing

**Status:** Indexing shipped (#72, `perf/db-scalability-indexes`) — 37 previously-unindexed foreign keys + hot-path composite/partial indexes (`kolabs(status,published_at)`, `attendee_profiles(total_points)`, `community_members(community_id,status)`, `community_points(community_id,points)`, `challenge_completions(event_id,status)`, `collaboration_reviews(reviewed_profile_id,rating,created_at)`, partial `chat_messages(...) WHERE read_at IS NULL`). Live catalog shows 0 unindexed FKs remaining. Sufficient for the ~5k-user target at the DB layer. The items below are **algorithmic** issues an index cannot fix, surfaced by the same audit.

**P1 — needed before ~50k users:**
- [x] `KolabResource::negotiation_triggers` fires a per-row `Application::exists()` on the browse feed → pre-annotate via `withExists` in `KolabService::browse()`. **Shipped 2026-07-12 (#74):** `has_applied` annotated via `withExists` in `browse()` + `loadExists` in `show()`; resource prefers the annotation, single-query fallback only for unannotated nested resources (mirrors `ResolvesSavedFlag`). Owner: **backend**.
- [ ] `ChatService::visibleThreads` is unpaginated (fetches all threads, sorts in PHP) → paginate / order in SQL. Owner: **backend**.
- [ ] Leaderboards (`getCommunityPointsLeaderboard`, global) hydrate all members then sort in PHP → move ranking + LIMIT into SQL. Owner: **backend**.
- [x] `PublicProfileResource` runs ~7 uncached queries per profile view (reputation window fn + double `completed_kolabs_count`) → cache or denormalise `reputation` / `completed_kolabs_count` onto `profiles`. **Shipped 2026-07-12 (#76):** `ProfileService::getReputationSummary()` cached per profile (`profile:reputation:{id}`, 24h backstop TTL) and busted by `CollaborationReviewObserver` (reviews received) + `CollaborationObserver` (completed-status changes) so it is always fresh; the duplicate `completed_kolabs_count` COUNT removed (resource reads it from the single cached summary). Owner: **backend**.
- [ ] `/me/dashboard` fires 4–5 live aggregate queries uncached → short-TTL cache. Owner: **backend**.
- [x] `/me/rewards-overview` N+1 (2 queries per active membership) → batch with `whereIn`. **Shipped 2026-07-12 (#78):** extracted `CommunityMembershipHydrator` (shared with `/me/memberships`) to bulk-resolve the viewer-scoped `CommunityResource` fields, and eager-loaded `community.rewards` + `community.communityProfile`; reward affordability reuses the hydrator's points map. Query count is now constant regardless of membership count. Owner: **backend**.

**P2 — 100k+ scale:**
- [ ] Event discovery Haversine (Postgres path) full-scans and recomputes trig per row with no bounding box → add a bounding-box prefilter or PostGIS / `cube`+GiST index. Owner: **backend**.
- [ ] Move cache / queue / session drivers to Redis; enable Reverb so chat unread polling is replaced by push; add a read replica for read-heavy aggregates. Owner: **infra**.

**Query-audit sweep (2026-07-12, #80) — additional N+1s found & fixed:**
- [x] `CollaborationResource::has_reviewed` fired `CollaborationReview::exists()` per row on the collaborations list → now derives from the already eager-loaded `reviews` relation (single-query fallback only when unloaded).
- [x] `CommunityRewardsHubController` goal progress ran per-goal count queries in BOTH the response map AND `CommunityPointsService::completeGoals()` → new `goalProgressForMany()` batches all earn-types (one grouped query for challenge goals); `completeGoals` bulk-loads already-paid goal ids.
- [x] `NotificationService::getNotifications` missed `actorProfile.attendeeProfile` → attendee-actor rows lazy-loaded per row; added to eager loads.
- [x] `FriendshipService::friendsOf` + `incomingRequests` loaded `Profile`s with no `with()` → `FriendResource` hit extended profiles per row; added `attendeeProfile`/`businessProfile`/`communityProfile` eager loads.
- [x] The collaborations list nests `KolabResource` + `OpportunitySummaryResource`, which fired a per-row `saved_kolabs` `is_saved` check (both resources), a `has_applied` check (KolabResource), and lazy-loaded `kolab.creatorProfile.businessProfile`/`communityProfile` per row. **Shipped 2026-07-12 (#82):** `CollaborationService::getForProfile` now annotates `is_saved` + `has_applied` on the nested kolab via a viewer-scoped `withExists`, and eager-loads the kolab creator's extended profiles. Query count is constant across rows. Owner: **backend**.

---

## How to use this file

- One canonical list across both repos. Mirror any edit in both — keep the files byte-identical (`diff -q` should be empty).
- When something ships, move the item to a `## Shipped` section at the bottom rather than deleting it, so the launch history stays visible.
- When picking up an item, open a PR with a description that links here so the backlog is self-documenting.

*Owner labels: `backend` = `kolabing-v2`, `app` = `kolabing-app`, `cross` = both, `infra` = hosting account, `product` = Daniel.*
