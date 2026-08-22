# Kolabing — Roles → Backend → Database Map (Ground-Truth)

> Note: `kolabing-v2/CLAUDE.md` references `docs/BACKEND-SCHEMA.md` as a
> MUST-READ schema doc; that file does not currently exist in this repo.
> This file (`ROLES-BACKEND-DB-MAP.md`) is the closest existing
> schema/reference doc and is kept up to date for role-affecting tables.
> Restoring/creating `BACKEND-SCHEMA.md` is tracked as backlog, not part of
> this PR.

**Last updated:** 2026-08-20 (**Invite page moved to the app host** — §12.6. It had never worked: the marketing layout loads no Alpine and the CSP grants 'unsafe-eval' only to the app host, so the CTA rendered empty. Moving it was preferred to weakening a security header. `invite_base_url` now defaults to https://app.kolabing.com/c, with a 301 from the old path. The page also gained Google sign-up + a name/phone/photo form using only existing endpoints. Prior: 2026-08-20 (**Phone preview — new §17.5.** Web-app only: a read-only replica of the app's public profile screen beside every Profile-section tab, refreshed after each edit. It mirrors three named Dart files in kolabing-app and must move with them. No API, schema, role or gate change. Prior: **Last updated:** 2026-08-20 (**Public portfolio — new §17.** Three new endpoints (gallery caption + gallery order + event photo order) sharing one `PhotoOrderingService` rule; `buildCommunityPastEvents()` merges the `events` table with `kolabs.past_events` (additive `source`/`source_event_id`/`attendee_count`, newest-first, undated last, deduped on name+date, two queries at any size); `GET /profiles/{id}` gains the portfolio via the narrower `hydratePublicPortfolio()` — the full detail hydration would have added count queries this endpoint does not emit and would have 404'd every attendee profile. No new table, no migration. Prior: 2026-08-19 (**Public-profile indexation bar revised — §16.6b.** The sitemap bar moved from "has a completed collaboration" to "has a review or three photos", because the first bar published a seeded test account and would have published hundreds of empty near-duplicate profiles; the same predicate now drives `noindex` on the page, and `/blog` + `/communities` use the same empty-hub rule through the layout's new `noindex` prop. Added `Profile::receivedReviews()`. Part of the SEO audit remediation, BACKLOG BE-FX-19. Prior same day: (**Public profile pages — new §16.** No schema change. One additive endpoint `GET /profiles/{id}/public-profile` serves the rich profile (gallery, aggregated photos, past events, past collaborations, stats) for **either** role: `past_events` lives on `kolabs.past_events` and was always written by whoever creates the Kolab, so only the community-scoped route's `isCommunity()` guard made it look community-only — that route is unchanged and still 404s for a business (pinned by a test). `buildCommunityPhotos()` stopped assuming `communityProfile`. Three additive fields for linking: `public_url` on the rich resource, `actor_profile_id` on notifications, `id` on chat participants. The public teaser at `kolabing.com/p/{slug}` reads models directly rather than opening any unauthenticated API, and the sign-up wall is enforced by never rendering the withheld fields (§16.4, ROLES §4.2). Slugs are `name-<uuid tail>` because `profiles.handle` is attendee-only in practice — 5 of 94 production profiles have one (§16.3). Prior: 2026-08-18 (**Chat on the web panel — new §15.** No endpoint, column, or gate added: the panel is a second client over the existing chat API. Documented what it calls and why, including two things that are easy to get wrong — `GET /chats/{thread}/messages` paginates **oldest-first** (page 1 is the start of the conversation; the newest messages are on `last_page`), and `POST /chats/{thread}/messages` on a **collaboration** thread notifies nobody because `threadRecipientIds()` returns `[]` for that type, so Kolab sends must use `POST /applications/{id}/messages` (§15.4, BE-FX-13). Real-time: `NewChatMessage` + `chat.thread.{id}` channel auth already existed; the panel subscribes with a self-hosted Pusher-protocol client and signs private channels at `POST /broadcasting/auth` (app root, Sanctum, bearer token). `config('webapp.realtime')` exposes only the four public Reverb values — never the app secret — and with the key unset the page polls, so this ships safely ahead of BE-IF-18. `AddSecurityHeaders` adds `wss:` to `connect-src` on the web-app host only. Two further gaps filed: BE-FX-14 (§2.8 lapse re-gate unenforced for chat — doc/code drift) and BE-FX-15 (`GET /me/communities` hides `can_manage` delegates' communities). Prior: 2026-08-17 (**Web payment flow — new §14.** No gate changed. `POST /me/subscription/checkout/confirm` added: the return-from-Stripe page activates the subscription synchronously (ownership-checked against `client_reference_id`, 403 otherwise; 409 `pending` while unpaid; same idempotent `activateFromStripeSession` the webhook runs — which now returns the upserted row). Checkout Session gains `customer` **or** `customer_email` (never both — duplicate customers orphan the Billing Portal), `allow_promotion_codes` (buyer discount — NOT the referrer-rewarding referral code), and `locale` (`ca → es`, Stripe has no Catalan); payload building split into `StripeService::checkoutSessionParams()` so it is testable without the SDK. `UserResource` gains additive business-only `subscription_status` + `subscription_cancel_at_period_end` (warning copy only — `has_active_subscription` is still the only field the paywall reads; `past_due` is NOT active). Paywall redirects carry `?reason=…` (§3). Public `/pricing` + `/es/pricing` added. Prior: Web app redesign wiring: documented the **paywall HTTP-status asymmetry** — apply/publish surface as 402 but **accept surfaces as 403** via `ApplicationPolicy::accept()`, so clients must treat `403 + business-without-sub` as the paywall too; also documented the `scheduled_date` recurring-day/window rule on accept — §3. No role rule changed. Prior: 2026-08-15 Stripe web checkout now implements `source=stripe` — `POST /me/subscription/checkout` + public signature-verified `/webhooks/stripe`, business-only, server-side price ids, return-URL allowlist, referral-on-first-paid; the `source`-invariant in §9 still holds — see §9. Prior: 2026-07-28 Explore browse feed now hides date-exhausted kolabs — `KolabService::browse()` applies `Kolab::scopeWithSelectableDates()` (serves `/kolabs` + `/opportunities` shim), mirroring the apply-time guard; `?saved=1` left unfiltered — §4. Prior: 2026-07-15 admin company/legal settings: single-row `company_settings` (name/address/NIF/refund/emails + `terms_version`/`terms_effective_date`), maintainer CRUD at `/admin/company-settings`, `CompanySettingService::termsVersion()` is now the consent-version source (config = fallback), a view composer injects the values into the four legal pages — §0 item 12. Prior: legal consent gate: `accepted_terms` (`required|accepted`) on all `register/*` endpoints, OAuth signups stamped in `AuthService::consentStamp()`, consent on `profiles.terms_accepted_at`/`terms_version` vs `config('legal.terms_version')`, `GET /auth/me` `terms` block, `POST /me/consent` (`ConsentController`), `Profile::needsTermsAcceptance()` — §0 item 12. Prior: profile reputation cache (#76): `getReputationSummary()` cached per profile with observer-based invalidation (`CollaborationReviewObserver` + `CollaborationObserver`) and the duplicate `completed_kolabs_count` COUNT removed — §13. Prior: DB scalability indexes (#72): 37 previously-unindexed FKs + hot-path composite/partial indexes added; `ProfileService::getReputationSummary()` window function gained a deterministic `id ASC` tiebreaker so the per-pair cap is stable across index/scan order — §13. Prior: PR 5: reputation shape — `unique_partner_count` removed from public reputation block; per-pair fairness cap added (max 2 reviews per reviewer→reviewed pair via SQL window function, no schema change); `recent_reviews` items serialise `is_verified_kolab_review: true` — §13. Prior: PR 4: public reputation summary — `collaboration_reviews` schema expansion with five category star ratings + `public_comment` + `public_comment_visible` gate, `ProfileService::getReputationSummary()` aggregation, and new `reputation` block on `PublicProfileResource` — §13. Prior: 2026-06-28 gamification mission system v1 curation: `challenges.app_visible` column + the three event/general mission filter sites — #49. Prior same day: #61 Saved Kolabs — new `saved_kolabs` pivot + save/unsave endpoints + `?saved=1` list + viewer-scoped `is_saved` flag — §7, §7.1. PR #59 review fixes: completion-confirmation gate hardening — terminal-state guard, `pending = not-yes` resource/gate alignment, auto-complete grace anchored on `updated_at`, `Collaboration::roleFor()`, **legacy feedback fallback + backfill removed (`/complete` now gates purely on real completion confirmations)**, dead-code removal — §0 item 10, §3, §8, §10. Prior: 2026-06-26 PR 1 moved the `/complete` gate to `collaboration_completions`) Prior: ** 2026-08-19 (**Community members web panel — new §12.6.** New table `community_invitations` + its endpoints, `GET /communities/{c}/stats`, member-detail and bulk-update endpoints, and the public `/c/{slug}` join route. `GET /communities/{c}/members` gains filters/sorts and per-row engagement metrics (resolved with left-joined grouped aggregates — no N+1, locked by a query-count test), is **now manage-gated**, and **defaults to excluding `status = removed`**. `AuthService::startOnboardingDrip` renamed to `afterRegistration` and now also claims pending invitations for the new profile's email (guarded). `CommunityMemberResource` fixed: it read the extended profile for a display name, but `attendee_profiles` has no `name` column, so every community member rendered as their email prefix — `profiles.name` is read first now. No gate changed; §12.5 is now enforced by `CommunityNeverPaywalledTest`. Prior: 2026-08-19 (**Public profile pages — new §16.** No schema change. One additive endpoint `GET /profiles/{id}/public-profile` serves the rich profile (gallery, aggregated photos, past events, past collaborations, stats) for **either** role: `past_events` lives on `kolabs.past_events` and was always written by whoever creates the Kolab, so only the community-scoped route's `isCommunity()` guard made it look community-only — that route is unchanged and still 404s for a business (pinned by a test). `buildCommunityPhotos()` stopped assuming `communityProfile`. Three additive fields for linking: `public_url` on the rich resource, `actor_profile_id` on notifications, `id` on chat participants. The public teaser at `kolabing.com/p/{slug}` reads models directly rather than opening any unauthenticated API, and the sign-up wall is enforced by never rendering the withheld fields (§16.4, ROLES §4.2). Slugs are `name-<uuid tail>` because `profiles.handle` is attendee-only in practice — 5 of 94 production profiles have one (§16.3). Prior: 2026-08-18 (**Chat on the web panel — new §15.** No endpoint, column, or gate added: the panel is a second client over the existing chat API. Documented what it calls and why, including two things that are easy to get wrong — `GET /chats/{thread}/messages` paginates **oldest-first** (page 1 is the start of the conversation; the newest messages are on `last_page`), and `POST /chats/{thread}/messages` on a **collaboration** thread notifies nobody because `threadRecipientIds()` returns `[]` for that type, so Kolab sends must use `POST /applications/{id}/messages` (§15.4, BE-FX-13). Real-time: `NewChatMessage` + `chat.thread.{id}` channel auth already existed; the panel subscribes with a self-hosted Pusher-protocol client and signs private channels at `POST /broadcasting/auth` (app root, Sanctum, bearer token). `config('webapp.realtime')` exposes only the four public Reverb values — never the app secret — and with the key unset the page polls, so this ships safely ahead of BE-IF-18. `AddSecurityHeaders` adds `wss:` to `connect-src` on the web-app host only. Two further gaps filed: BE-FX-14 (§2.8 lapse re-gate unenforced for chat — doc/code drift) and BE-FX-15 (`GET /me/communities` hides `can_manage` delegates' communities). Prior: 2026-08-17 (**Web payment flow — new §14.** No gate changed. `POST /me/subscription/checkout/confirm` added: the return-from-Stripe page activates the subscription synchronously (ownership-checked against `client_reference_id`, 403 otherwise; 409 `pending` while unpaid; same idempotent `activateFromStripeSession` the webhook runs — which now returns the upserted row). Checkout Session gains `customer` **or** `customer_email` (never both — duplicate customers orphan the Billing Portal), `allow_promotion_codes` (buyer discount — NOT the referrer-rewarding referral code), and `locale` (`ca → es`, Stripe has no Catalan); payload building split into `StripeService::checkoutSessionParams()` so it is testable without the SDK. `UserResource` gains additive business-only `subscription_status` + `subscription_cancel_at_period_end` (warning copy only — `has_active_subscription` is still the only field the paywall reads; `past_due` is NOT active). Paywall redirects carry `?reason=…` (§3). Public `/pricing` + `/es/pricing` added. Prior: Web app redesign wiring: documented the **paywall HTTP-status asymmetry** — apply/publish surface as 402 but **accept surfaces as 403** via `ApplicationPolicy::accept()`, so clients must treat `403 + business-without-sub` as the paywall too; also documented the `scheduled_date` recurring-day/window rule on accept — §3. No role rule changed. Prior: 2026-08-15 Stripe web checkout now implements `source=stripe` — `POST /me/subscription/checkout` + public signature-verified `/webhooks/stripe`, business-only, server-side price ids, return-URL allowlist, referral-on-first-paid; the `source`-invariant in §9 still holds — see §9. Prior: 2026-07-28 Explore browse feed now hides date-exhausted kolabs — `KolabService::browse()` applies `Kolab::scopeWithSelectableDates()` (serves `/kolabs` + `/opportunities` shim), mirroring the apply-time guard; `?saved=1` left unfiltered — §4. Prior: 2026-07-15 admin company/legal settings: single-row `company_settings` (name/address/NIF/refund/emails + `terms_version`/`terms_effective_date`), maintainer CRUD at `/admin/company-settings`, `CompanySettingService::termsVersion()` is now the consent-version source (config = fallback), a view composer injects the values into the four legal pages — §0 item 12. Prior: legal consent gate: `accepted_terms` (`required|accepted`) on all `register/*` endpoints, OAuth signups stamped in `AuthService::consentStamp()`, consent on `profiles.terms_accepted_at`/`terms_version` vs `config('legal.terms_version')`, `GET /auth/me` `terms` block, `POST /me/consent` (`ConsentController`), `Profile::needsTermsAcceptance()` — §0 item 12. Prior: profile reputation cache (#76): `getReputationSummary()` cached per profile with observer-based invalidation (`CollaborationReviewObserver` + `CollaborationObserver`) and the duplicate `completed_kolabs_count` COUNT removed — §13. Prior: DB scalability indexes (#72): 37 previously-unindexed FKs + hot-path composite/partial indexes added; `ProfileService::getReputationSummary()` window function gained a deterministic `id ASC` tiebreaker so the per-pair cap is stable across index/scan order — §13. Prior: PR 5: reputation shape — `unique_partner_count` removed from public reputation block; per-pair fairness cap added (max 2 reviews per reviewer→reviewed pair via SQL window function, no schema change); `recent_reviews` items serialise `is_verified_kolab_review: true` — §13. Prior: PR 4: public reputation summary — `collaboration_reviews` schema expansion with five category star ratings + `public_comment` + `public_comment_visible` gate, `ProfileService::getReputationSummary()` aggregation, and new `reputation` block on `PublicProfileResource` — §13. Prior: 2026-06-28 gamification mission system v1 curation: `challenges.app_visible` column + the three event/general mission filter sites — #49. Prior same day: #61 Saved Kolabs — new `saved_kolabs` pivot + save/unsave endpoints + `?saved=1` list + viewer-scoped `is_saved` flag — §7, §7.1. PR #59 review fixes: completion-confirmation gate hardening — terminal-state guard, `pending = not-yes` resource/gate alignment, auto-complete grace anchored on `updated_at`, `Collaboration::roleFor()`, **legacy feedback fallback + backfill removed (`/complete` now gates purely on real completion confirmations)**, dead-code removal — §0 item 10, §3, §8, §10. Prior: 2026-06-26 PR 1 moved the `/complete` gate to `collaboration_completions`)
**Status:** Authoritative companion to [`ROLES-AND-PERMISSIONS.md`](./ROLES-AND-PERMISSIONS.md). Read that first (the *what*), then this (the *where*).
**Sync note:** Duplicated in both repos (`kolabing-app`, `kolabing-v2`). Keep identical, and **bump the Last updated date in both** when role behaviour or backend wiring changes.

> This document maps each role rule from `ROLES-AND-PERMISSIONS.md` to the exact backend code and database tables/columns that implement it, and flags every place the current code mis-handles roles. Cite this map (file:line, table.column) before changing anything that touches Explore, profiles, the paywall, permissions, onboarding, or create/apply. Items marked **[VERIFY]** still need a live confirmation.

---

## 0. TL;DR — the role-confusion hot spots

> Fixed-since-last-update marked ✅; still-open marked ⚠️.

1. ⚠️ **`kolabs` is now the sole source of truth for the opportunity/kolab lifecycle (as of 2026-06-19, #30).** The legacy `collab_opportunities` **table-level code is archived** — `CollabOpportunity` model, `LegacyOpportunityBridgeService` / `InverseLegacyOpportunityBridgeService`, the migrate command, factory, seeder, and the application dual-write are all deleted. `kolab_id` is the canonical FK on `applications`/`collaborations`. The `collab_opportunities` table and the `collab_opportunity_id` columns **remain physically but are no longer read or written by app code**; they are scheduled for drop in #31. See §2 and §7.
2. ✅ **`KolabService::publish` now has the `isBusiness()` guard** — `app/Services/KolabService.php:190`. A community publishing a non-`CommunitySeeking` kolab no longer hits the freemium gate.
3. ⚠️ **The blur still does not exist.** `app/Http/Resources/Api/V1/DiscoveryOpportunityResource.php` returns full `creator_profile.display_name` + `avatar_url` to every viewer; no `identity_locked` / `hide_creator_identity` flag is emitted. Violates golden rules 4 & 5. See §4.
4. ✅ **Account deletion now frees the email, closes posts on both systems, cancels active collaborations, and revokes tokens.** `ProfileService::deleteProfile()` (`app/Services/ProfileService.php:111`) renames the soft-deleted profile's email to `deleted+{id}@kolabing.invalid` so the unique index releases the original address. See §6.
5. ✅ **Profile logos serialize as absolute URLs from the correct column.** `PublicProfileResource::absoluteUrl()` resolves the URL from the extended profile's `profile_photo` first, falling back to `profiles.avatar_url`. See §5.
6. ⚠️ **NEW — attendee gamification track has shipped** but the canonical permissions doc still describes attendees as "deferred / out of scope". `AttendeeProfile`, `Wallet`, `EarnedBadge`, `EventCheckin`, `ChallengeCompletion`, and ~40 gamification endpoints are live. See §11.
7. ⚠️ **NEW — `coliving` is in the canonical role spec (`ROLES-AND-PERMISSIONS.md` §2.1) but missing from `BusinessOnboardingRequest::BUSINESS_TYPES`.** A `coliving` onboarding payload is rejected server-side. Trivial fix; see §8 checklist.
8. ⚠️ **NEW — admin operator surfaces.** Maintainers can grant a 12-month subscription with `source = maintainer`, force-cancel collaborations, and (since 2026-06-01) **force-complete** collaborations from `/admin/*`. Make sure new gate code accounts for `source = maintainer` (still an `active` row; behaves identically to a Stripe-paid sub). See §9.
9. ⚠️ **NEW — community members + tiers surface (NF-6).** Three new tables (`communities`, `community_tiers`, `community_members`) + a nullable `events.community_id`. The "one free community" cap is a **NEW config-driven gate** (`config('communities.max_free_communities')` → `CommunityLimitReachedException` → 422 `community_limit_reached`). It is NOT the business paywall — do NOT add `hasActiveSubscription()` anywhere on this surface. See §12.
10. ✅ **Completion-confirmation gate on `/complete` is live** (2026-06-26, PR 1 — replaces the 2026-06-01 feedback gate). `CollaborationService::complete()` now refuses until both participants have a `collaboration_completions` row AND both said `status = 'yes'`, via `CollaborationCompletionService::enforceGate()`. Rich `/feedback` is now optional impact data and no longer gates completion — its own XP and the legacy `/review` mirror are unchanged. Per-party `CollaborationCompletionConfirmed` XP fires on `/completion`, once, on first submission. Soft-rollout knob: `config('collaborations.complete_requires_completion_confirmation')`. See §3 and §10.
11. ✅ **NEW — gamification mission system v1 shipped (2026-06-28, #49).** `challenges` now has `trigger_action`/`target_value`/`repeat_interval`/`starts_at`/`ends_at`/`slug`/`app_visible`; a new `challenge_progress` table backs self-tracked mission progress; `MissionService` does atomic upsert + award. `GET /me/missions` filters to `is_system=true AND event_id IS NULL AND app_visible=true AND trigger_action IS NOT NULL AND trigger_action IN (live triggers) AND audience matches AND within [starts_at, ends_at]` — exactly 18 of the 49 seeded missions are `app_visible=true` (5 attendee / 7 business / 6 community), all on live triggers. Event challenges (`trigger_action IS NULL`) stay peer-verified and are excluded from `/me/missions`; general missions (`trigger_action IS NOT NULL`) are excluded from the event-scoped surfaces — enforced in **three** places: `SystemChallengeController`, `Admin\ChallengeDefaultsController`, `ChallengeService::listForEvent()`. **PR #49 review fixes (2026-06-28):** the earning path (`MissionService::activeMissionsFor`) now also gates on `app_visible=true`, so a hidden mission no longer accrues silent progress — earning and visibility are aligned; recurring `period_key` buckets are derived in local time (`config('gamification.local_timezone')`, default `Europe/Madrid`) so daily/monthly missions roll over at local midnight; `MissionCompleted` ledger rows carry `point_ledger.challenge_id` for attribution; `target_value` is frozen at progress-row creation (upsert `DO NOTHING`); and the live-trigger set moved to `config('gamification.live_triggers')` (`MissionTrigger::isLive()` reads it). See §11.
12. ✅ **NEW — legal consent gate (all roles).** Registration stays free, but every account must accept the Terms + Privacy. `accepted_terms` (`required|accepted`) is enforced on `register/{business,community,attendee}`; `auth/google` + `auth/apple` **create** accounts too and are stamped server-side in `AuthService::consentStamp()`. Consent lives on `profiles.terms_accepted_at` (timestamptz) + `profiles.terms_version` (string) vs `config('legal.terms_version')`. `GET /auth/me` returns `terms { current_version, accepted_version, accepted_at, needs_acceptance }`; `POST /me/consent` (`ConsentController`) re-accepts; `Profile::needsTermsAcceptance()` is the re-gate. The version + the company/legal identity that fills the pages live in a single-row `company_settings` table (`CompanySetting` + `CompanySettingService`), edited by maintainers at `/admin/company-settings`; `CompanySettingService::termsVersion()` is the version source (`config('legal.terms_version')` is the fallback), and a view composer injects `$company` into `pages.terms`/`pages.privacy`/`pages.es.*`. This is a **consent gate, NOT the paywall** — never add `hasActiveSubscription()` here. App flow: `docs/mobile-consent-flow.md`.

---

## 1. Role identity & subscription (where the role lives)

| Concept | Table.Column | Notes |
|---|---|---|
| **User role** | `profiles.user_type` (varchar 20) | Stored as enum value `business` / `community` / `attendee`. Read via `Profile::isBusiness()` (`app/Models/Profile.php:357`), `isCommunity()` (`:365`), `isAttendee()` (`:373`). |
| **Subscription state** | `business_subscriptions.status` (varchar 20) | Real values: `active` / `cancelled` / `past_due` / `inactive` (default `inactive`). **There is no `trial` state.** 1:1 to `profiles` via `profile_id` (unique). |
| **Subscription source** | `business_subscriptions.source` (varchar) | `stripe` / `apple_iap` / **`maintainer`**. The `maintainer` value indicates an admin-granted sub (see §9). Same gating logic — same paywall result. |
| **Active-sub check** | `Profile::hasActiveSubscription()` (`:382`) | Correctly returns `false` for any non-business. **Backdoor:** returns `true` when `profiles.is_test_user = true` *regardless* of any subscription row — reserved for internal QA accounts, must never be set on real users. |
| **Free-kolab-used check** | `Profile::hasUsedFreeKolab()` (`:402`) | True if the profile has a **published** kolab with `intent_type` in (VenuePromotion, ProductPromotion). Role-agnostic — but the caller is now properly role-guarded (see §3). |

Role helpers are consistent; the remaining bugs are in *where they are (not) applied* (Explore blur, §4), not in the helpers themselves.

---

## 2. The two parallel post systems (READ THIS)

There are **two** "post" tables plus the accepted-match table. Per `ROLES-AND-PERMISSIONS.md` golden rule 6, "opportunity" and "collaboration" are both valid and distinct — but the code splits them differently than the doc's wording, and runs two creation stacks:

| System | Table | Model | Created via (backend) | Created via (client) | Gating |
|---|---|---|---|---|---|
| **A. Opportunities (shim, archived table)** | `collab_opportunities` (ARCHIVED) | ~~`CollabOpportunity`~~ (deleted) | `OpportunityController` → `KolabService` (request-contract shim; `OpportunityService` still owns the freemium limit) | **Legacy** screens (`create_opportunity_screen` community, `create_collab_request_screen` business) via `opportunityFormProvider` | `isBusiness()`-guarded freemium limit ✅ |
| **B. Kolabs (canonical)** | `kolabs` | `Kolab` | `KolabController` → `KolabService` (+ new `/kolabs/{kolab}/applications` apply via `ApplicationController@store`/`@forOpportunity`) | **New** `/kolab/flow` via `kolab_form_provider` | intent-type only — **no `isBusiness()` freemium-limit guard yet** ⚠️ (#31) |
| **C. Collaborations** | `collaborations` | `Collaboration` | created when an application is accepted | — | none (lifecycle only) |
| Applications | `applications` | `Application` | `ApplicationController` | apply modal | — |

- `collab_opportunities.creator_profile_type` (enum Business|Community) encodes authorship in System A.
- `kolabs.intent_type` (CommunitySeeking | VenuePromotion | ProductPromotion) encodes authorship in System B: **CommunitySeeking = community-authored; Venue/ProductPromotion = business-authored.**
- **The bridge — removed (2026-06-19, #30).** `LegacyOpportunityBridgeService` / `InverseLegacyOpportunityBridgeService` and the `collab_opportunities` dual-write in `ApplicationService` have been **deleted**. Applications/collaborations now reference the canonical `kolab_id` directly; the `collab_opportunity_id` columns are no longer populated by app code. `CollabOpportunity` model, factory, seeder, the migrate command, and the dead `OpportunityResource`/`OpportunityCollection` are all gone. The `collab_opportunities` table + `collab_opportunity_id` columns are **archived** (physically present, no longer read/written) and scheduled for drop in #31.
- **Practical implication:** "which model is the post?" → always the **Kolab**. Query applications/collaborations by `kolab_id` (canonical FK). The `/api/v1/opportunities/*` routes survive only as a request-contract shim over `KolabService` (mobile compat, removal gated on `kolabing-app` #20). Do **not** reintroduce reads/writes against `collab_opportunities`.

---

## 3. Paywall enforcement — every gate, classified

Spec: paywall is **Business-only**, on **exactly two actions** (create a collaboration, apply to a Kolab). Communities are never gated.

| Action | Code | Gates whom | Verdict |
|---|---|---|---|
| Create opportunity (legacy shim path) | `OpportunityService::hasReachedFreemiumCollabLimit()`; early-out `if (!$creator->isBusiness()) return false;` | Business w/o sub, >1 collab | ✅ correct — but lives **only** on the legacy `/opportunities` create path today; NOT yet enforced on `/kolabs` create (port + portfolio-photo parity tracked in #31) |
| Publish opportunity (System A) | `OpportunityService::publish()` `if ($creator->isBusiness() && !$creator->hasActiveSubscription())` | Business only | ✅ correct |
| Create kolab (System B) | `KolabService::create()` — no sub check | nobody | ✅ correct |
| Publish kolab (System B) | `KolabService::publish()` `:190` — `$creator->isBusiness() && intent_type !== CommunitySeeking && !hasActiveSubscription() && hasUsedFreeKolab()` | Business only | ✅ correct (fixed since 2026-05-22) |
| Accept application | `ApplicationService::accept()` `:86` — `$opportunity->creatorProfile->isBusiness() && ! hasActiveSubscription()` throws `RuntimeException('An active subscription is required to accept applications.')` | Business creator without sub | ✅ correct |
| Apply to a post | `ApplicationController::store()` `:78` → catches `SubscriptionRequiredException` and returns HTTP **402** so the client renders the paywall. The community side throws no such exception. | Business applier without sub | ✅ correct |
| Client: create entry (intent) | `intent_selection_screen.dart` shows the paywall for unsubscribed business; communities only see the FREE CommunitySeeking option | Business only | ✅ correct |

All four backend gates now follow the same pattern: `if ($profile->isBusiness() && ! $profile->hasActiveSubscription())`. **Never copy this gate into community or attendee paths.**

**HTTP status the paywall actually surfaces as (verified 2026-08-17, matters for every client):** the gate is *not* uniformly 402.

| Gate | Status | Why |
|---|---|---|
| Apply to a post | **402** | `ApplicationController::store()` catches `SubscriptionRequiredException` and maps it |
| Publish kolab | **402** | same mapping on the publish path |
| **Accept application** | **403** | `ApplicationPolicy::accept()` returns `false` for a business without an active sub, so `ApplicationController::accept()` short-circuits on `cannot('accept', …)` and returns 403 *before* `ApplicationService::accept()`'s `RuntimeException` can be mapped. The 403 body is the generic "not authorized" message — it carries no paywall signal. |

A client must therefore treat **402 OR (403 while the viewer is a business without an active subscription)** as "show the plan", otherwise accepting an application dead-ends with an unexplained authorization error. The web app does this via `kbShell().needsPlan` (`resources/views/webapp/layout.blade.php`). Consider normalising accept to 402 backend-side; until then this asymmetry is load-bearing for clients.

**Where the paywall sends people (added 2026-08-17):** every web paywall hop now appends `?reason=publish|accept|apply|create|welcome` to `/subscription` so the plan page can name the blocked action. Presentation only — it changes no gate. Purchase/confirmation mechanics: §14.

**`scheduled_date` on accept (not a paywall, same flow):** `AcceptApplicationRequest` requires `after:today` **and** the date must fall on one of the Kolab's `recurring_days`, inside `availability_start`…`availability_end`. Clients should bound their date picker to that window and pre-check the weekday — the server error is otherwise the first place a user learns the rule.

**Completion-confirmation gate on `/complete` (PR 1, 2026-06-26, supersedes the 2026-06-01 feedback gate; not a paywall):** `CollaborationService::complete()` now calls `CollaborationCompletionService::enforceGate()`, which throws `awaiting_own_completion_confirmation` (422, caller hasn't responded), `awaiting_partner_completion_confirmation` (422, partner hasn't responded — context carries `pending_completion_from: ['business'|'community']`), or `completion_not_confirmed` (422, both responded but at least one said `no`/`not_yet` — context carries `own_status`/`partner_status`). Subject to `config('collaborations.complete_requires_completion_confirmation', true)` so the gate can be soft-rolled. This is a UX gate, **not role-discriminatory** — both business and community must confirm. Rich `/feedback` no longer participates in this gate at all (the old `awaiting_own_feedback`/`awaiting_partner_feedback` exception factories were removed in PR #59). `submit()` rejects confirmations on terminal (completed/cancelled) collaborations so no XP is paid on a settled Kolab.

---

## 4. Explore / discovery — role logic & the missing blur

**Endpoint:** `GET /api/v1/discovery/opportunities` → `DiscoveryOpportunityController` → `DiscoveryOpportunityService`. Queries the **`kolabs`** table.

- Viewer role resolved from `profiles.user_type` (`DiscoveryOpportunityService.php:123`).
- **Feed scoping (correct ✅):** business viewer → `intent_type = CommunitySeeking` (`:202`); community viewer → `intent_type in (VenuePromotion, ProductPromotion)` (`:205-209`). So business sees community posts, community sees business posts. ✅
- **Type tags formatted server-side** in discovery: `normalizeTagLabel()` (`:1094`) → `{key, label}` objects (`run_club` → `Run Club`). ✅ (but profile resources may not — see §5/ticket).

**Blur — does not exist (VIOLATION of golden rules 4 & 5):**
- Server: `DiscoveryOpportunityResource.php:43-47` serializes full `creator_profile.display_name` + `avatar_url` to **every** viewer. No `identity_locked`/blur flag.
- Client: `explore_screen.dart:71` computes `hideCreatorIdentity = !_isCommunityViewer && item.isCommunityRequest`, passes it to `ExploreSwipeCard` (`:317`), **but no blur is rendered**. `community_offer_detail_screen.dart:243-275` shows the name openly.
- Free-business hard-block: `explore_screen.dart:96-108` (`SubscriptionPaywall.checkAndShow` + `canApply` gate). The 2026-05-22 review reports a hard-block overlay where there should be a **blur of name+logo only**.

**Required:** decide where blur lives. Recommended = **client-side blur** of name+logo for a free business (`hideCreatorIdentity` already computed) + keep all Kolab details visible; gate only the apply/create buttons. (Server could also null identity for free businesses, but that breaks reveal-on-subscribe without a re-fetch.) **Do not** hard-block the Explore screen.

**Date-exhausted kolabs excluded from the browse feed (2026-07-28).** `KolabService::browse()` — which serves both `GET /kolabs` (`KolabController@index`) and the `GET /opportunities` shim (`OpportunityController@index`) — now applies `Kolab::scopeWithSelectableDates()` on the discovery (non-`saved`) path: `availability_end IS NULL OR DATE(availability_end) >= today`. This is the SQL-expressible form of `Kolab::hasSelectableDatesFrom()` and mirrors the apply-time guard in `ApplicationService` (#107), so the feed never surfaces a Kolab whose application dates have all passed (the "No available dates for this kolab" dead-end). The `?saved=1` list is intentionally left unfiltered so a user still sees a saved kolab that later expired. The separate role-aware `DiscoveryOpportunityService::applyActiveAvailabilityFilter()` (`:569`) already excludes `COALESCE(availability_end, availability_start) < today`; the recurring-mode weekday edge remains SQL-inexpressible in both and is caught only at apply time.

---

## 5. Profiles — logo, past events, "view profile", type tags

| Item | Backend | Likely cause of breakage |
|---|---|---|
| **Logo / avatar** | base `profiles.avatar_url` **and** `business_profiles.profile_photo` / `community_profiles.profile_photo` (two columns). Serialized in `PublicProfileResource`. | (a) resource maps the wrong column, and/or (b) URL returned non-absolute (relative path) so `Image.network` fails. Confirm `PublicProfileResource` returns an **absolute** logo URL from the correct column. |
| **Past events** | `events` table (`profile_id` FK) + `collaborations.event_id`. `ProfileController::profileCollaborations()` (`:114`) returns completed collaborations. | Past events unlinked from collaborations: ensure completed collaborations populate `completed_at` and the profile endpoint eager-loads `event`. |
| **View business/creator profile** | route `GET /api/v1/profiles/{profile}` → `ProfileController::publicProfile()` (`:99`), implicit binding on **`Profile`**. | "Opens wrong / doesn't open": the client likely passes a **creator/collaboration id or business_profile id** where a **`profiles.id`** is expected. **[VERIFY]** which id the client sends from the collaboration → profile link. |
| **Type tag formatting** | `business_profiles.type` / `community_profiles.community_type` stored as raw slug. Client formats via `lib/utils/profile_type_formatter.dart` (`formatProfileTypeLabel`). | "Run_Club" shows when a code path renders the raw slug without `formatProfileTypeLabel` (or the backend pre-formats inconsistently). Pick **one** side to format; discovery already formats server-side, profiles format client-side — align them. |
| **Home/Dashboard widget** | `DashboardController` → `DashboardService` (`getBusinessDashboard` / `getCommunityDashboard`), `GET /api/v1/me/dashboard`. | "Home widget broken both sides": likely a field-name / enum / `scheduled_date` ISO mismatch between `DashboardService` output and the client `BusinessDashboard`/`CommunityDashboard` models, or empty stats. Diff the JSON keys against the client models. |

---

## 6. Account deletion & data integrity ✅ FIXED

- **Endpoint:** `ProfileController::destroy()` → `ProfileService::deleteProfile()` (`app/Services/ProfileService.php:111`). All actions run in one DB transaction.
- **What now happens, in order:**
  1. The profile's `email` is force-filled to `deleted+{profile.id}@kolabing.invalid` so the unique-index lock is released and the original address can re-register.
  2. The user's open **kolabs** (draft / published) are closed (`kolabs.status → closed`).
  3. The user's open **collab_opportunities** (System A — draft / published) are closed (`status → closed`).
  4. Scheduled / active **collaborations** are cancelled via `cancelActiveCollaborations()`, and the counterparty receives an in-app notification.
  5. All Sanctum tokens are revoked.
  6. The profile is soft-deleted (`Profile` still uses `SoftDeletes`).
- **What still uses soft-delete:** the profile row itself remains for audit/recovery. Related rows are *closed*, not destroyed, so collaboration history stays intact on the counterparty's side.
- **Admin equivalent:** `Admin\ManagedUserController::destroy()` → `ManagedProfileService::delete()` just calls `$profile->delete()` without the cleanup transaction above. **[VERIFY]** whether the admin delete path should also run the full cleanup — currently it bypasses the email-free + collaboration-cancel side effects.

---

## 7. Database schema map (roles & marketplace core)

```
profiles (id uuid PK, email UNIQUE, user_type[business|community|attendee], avatar_url,
          is_test_user, last_active_at, deleted_at?)
 ├─1:1─ business_profiles      (profile_id FK, name, business_type slug, profile_photo, …)
 ├─1:1─ community_profiles     (profile_id FK, name, community_type slug, profile_photo, instagram, …)
 ├─1:1─ attendee_profiles      (profile_id FK, total_points, total_challenges_completed,
 │                              total_events_attended, global_rank)                ← gamification (§11)
 ├─1:1─ business_subscriptions (profile_id FK UNIQUE,
 │                              status[active|cancelled|past_due|inactive],
 │                              source[stripe|apple_iap|maintainer],
 │                              stripe_customer_id, stripe_subscription_id,
 │                              current_period_start, current_period_end, cancel_at_period_end,
 │                              apple_original_transaction_id, apple_transaction_id, apple_product_id)
 ├─1:N─ collab_opportunities   (creator_profile_id FK, creator_profile_type[business|community],
 │                              status[draft|published|closed|completed], business_offer json,
 │                              community_deliverables json, venue_mode, preferred_city)         ← System A (ARCHIVED #30 → drop #31)
 ├─1:N─ kolabs                 (creator_profile_id FK, intent_type[community_seeking|venue_promotion|
 │                              product_promotion], status[draft|published|closed], media json,
 │                              needs json, community_types json, venue_preference, offering json,
 │                              published_at)                                                    ← System B (canonical)
 │        └─N:M─ saved_kolabs   (profile_id FK, kolab_id FK, timestamps; PK(profile_id,kolab_id)) ← Saved/bookmarked kolabs (§7.1)
 ├─1:N─ events                 (profile_id FK, partner_id FK, event_date, attendee_count)        ← past events
 └─1:N─ applications           (applicant_profile_id FK, applicant_profile_type[business|community],
                                kolab_id FK ← canonical, collab_opportunity_id FK ARCHIVED (no longer written, drop #31),
                                status[pending|accepted|declined|withdrawn],
                                accepted_at?, declined_at?, withdrawn_at?)                       ← (§10)
            └─1:1─ collaborations (application_id FK UNIQUE, kolab_id FK ← canonical,
                                   collab_opportunity_id FK ARCHIVED (no longer written, drop #31),
                                   creator_profile_id, applicant_profile_id,
                                   business_profile_id nullOnDelete, community_profile_id nullOnDelete,
                                   status[scheduled|active|completed|cancelled],
                                   scheduled_date, activated_at?, completed_at?,
                                   cancelled_at?, cancellation_reason?, cancelled_by_profile_id? nullOnDelete,
                                   event_id, qr_code_url)                                        ← (§10)
                       ├─1:N─ collaboration_reviews (collaboration_id FK, reviewer_profile_id,
                       │                              reviewed_profile_id, reviewer_role, rating,
                       │                              communication_rating, reliability_rating,
                       │                              fit_rating, value_rating, repeat_rating,
                       │                              body, note, public_comment,
                       │                              public_comment_visible[bool, default true],
                       │                              would_collaborate_again)               ← (§13)
                       └─1:N─ chat_messages         (application_id FK, sender_profile_id, …)

Gamification / wallets (§11):
 ├─ wallets, withdrawal_requests, point_ledger
 ├─ badges, badge_awards, earned_badges
 ├─ event_checkins, event_photos, event_rewards
 ├─ challenges (trigger_action? string(60), target_value uint default 1,
 │              repeat_interval string(20) default 'once', starts_at?, ends_at?,
 │              slug string(120) UNIQUE?, app_visible bool default false)   ← mission columns (#49)
 ├─ challenge_progress (challenge_id FK, profile_id FK, progress_count, target_value,
 │                       completed_at?, period_key?; UNIQUE(challenge_id, profile_id, period_key))
 └─ collaboration_challenges, challenge_completions, referral_codes, referral_redemptions

Lookups / admin:
 ├─ cities, city_suggestions
 └─ users (admin/maintainer auth — separate from profiles, see §9)
```

**Role lives in `profiles.user_type`. Subscription lives in `business_subscriptions.status` (with `source` as audit trail).** Everything else branches off those two.

### 7.1 Saved Kolabs (bookmarks) — added 2026-06-27 (#61)

Viewer-scoped bookmarks of published kolabs. Pivot `saved_kolabs` (`profile_id` FK→profiles cascade, `kolab_id` FK→kolabs cascade, timestamps, composite PK — no UUID surrogate, so `belongsToMany` attach/sync is safe). Relations: `Profile::savedKolabs()` / `Kolab::savedByProfiles()`; service `KolabService::save()/unsave()` (idempotent via `syncWithoutDetaching`/`detach`).

- `POST /api/v1/kolabs/{kolab}/save` → 200 (idempotent); requires `can('view', $kolab)` so visibility/paywall rules are respected (you can only save what you can see).
- `DELETE /api/v1/kolabs/{kolab}/save` → 204 (idempotent).
- **List:** `GET /api/v1/kolabs?saved=1` reuses `KolabService::browse()` (identical resource shape + paging). The saved list keeps the published + recipient-visibility scope but, unlike the normal feed, does **not** hide kolabs the viewer already applied to.
- **Flag:** `is_saved` (bool, viewer-scoped) on `KolabResource` and `OpportunitySummaryResource`. N+1-free in list/detail via `withExists`/`loadExists` annotation (`ResolvesSavedFlag` trait), with a single-existence-query fallback for nested resources. Not role-discriminatory — any authenticated profile may save any visible kolab.

> **ARCHIVED (2026-06-19, #30):** `collab_opportunities` and the `collab_opportunity_id` columns on `applications`/`collaborations` are no longer read or written by application code. They are retained physically and scheduled for drop in #31. `kolabs` is the sole source of truth for the opportunity/kolab lifecycle; `kolab_id` is the canonical FK on applications/collaborations.

---

## 8. Mistakes-to-fix checklist (role-handling)

Fixed since the last revision:

- [x] `KolabService::publish` gated by `isBusiness()` (`:190`). (§3)
- [x] Account deletion frees the email + closes posts on both systems + cancels active collaborations. (§6)
- [x] Profile logo returns an absolute URL via `PublicProfileResource::absoluteUrl()` from the correct column. (§5)
- [x] Collaboration cancellation now persists `cancellation_reason`, `cancelled_at`, and `cancelled_by_profile_id` (§10).
- [x] **Feedback gate on `/complete` shipped** with admin force-complete, auto-timeout scheduler, and a `/review`→`/feedback` mirror for legacy clients (§3, §9, §10). XP moved from `/complete` to `/feedback` per Q7. PR #9, 2026-06-01.
- [x] **NF-6 community members + tiers, Phase 1 shipped** (2026-06-03): `communities` / `community_tiers` / `community_members` tables, `events.community_id`, `CommunityPolicy`, the cap gate (NOT the paywall), the auto-assignment command + on-check-in hook, and the chapter-scoped leaderboard. See §12.
- [x] **Completion-confirmation gate hardening (PR #59 review fixes, 2026-06-27):** `CollaborationCompletionService::submit()` now refuses confirmations on terminal collaborations (no XP on completed/cancelled Kolabs); `pendingConfirmationFrom()` (and the resource's `pending_completion_from` / `viewer_must_confirm_completion`) now mean "has not answered `yes`" so the resource and the gate agree when a party said `no`/`not_yet`; the auto-complete scheduler anchors the grace window on the confirmation's `updated_at` (a `not_yet→yes` change gets a fresh window); role resolution is unified on `Collaboration::roleFor()`; the dead `complete_requires_feedback` config and `awaiting*Feedback` exception factories were removed. **Legacy support dropped:** the feedback→implicit-yes runtime fallback and the one-time backfill migration were removed — `/complete` and the auto-complete scheduler now gate purely on real `collaboration_completions` rows. Each party must confirm via `POST /completion`; `/feedback` and `/review` are impact data only and no longer satisfy the gate. See §3, §10.
- [x] **Gamification mission system v1 shipped** (2026-06-28, #49): `challenges.app_visible` curation column, atomic `challenge_progress` upsert, wallet-service delegation + `isLive()` guard on `/me/missions`, the DTO refactor, and the curated 18-mission v1 set (5 attendee / 7 business / 6 community). Event/general mission separation enforced in three places. See §11.1.

Still open:

- [ ] **BE-NF-30 — decide the public-profile opt-out:** `/p/{slug}` publishes every business/community with a completed collaboration and lists them in `sitemap.xml`, with no way to decline (§16, ROLES §4.2).
- [ ] **BE-FX-13 — converge the two chat send paths:** `sendThreadMessage()` must notify the counterpart when the thread has an `application_id`, so `POST /chats/{thread}/messages` stops delivering silent Kolab messages (§15.4).
- [ ] **BE-FX-14 — decide §2.8 vs. the code for chat:** either enforce the lapse re-gate in `canParticipate()` / on the chat routes (community side unaffected), or correct §2.8. Today the doc and the code disagree (§15.5).
- [ ] **BE-FX-15 — let `can_manage` delegates find their communities:** include managed communities in `GET /me/communities` and emit `my_can_manage` on `CommunityResource` (§15.5).

- [ ] Implement the **blur** (name + logo) for free businesses on Explore. Server should emit an `identity_locked` flag (or null the identity for free businesses) and the client should render an actual blur. **No hard block on Explore.** (§4)
- [ ] Past events linked to collaborations and `completed_at` populated.
- [ ] "View profile" deep-link confirmed to pass a `profiles.id` (not a `business_profile.id` or `collaboration.id`).
- [ ] Type tags formatted on exactly one side (discovery formats server-side; profiles format client-side — pick one).
- [x] **Archive the legacy `collab_opportunities` table-level code** (2026-06-19, #30) — deleted `CollabOpportunity`, both bridge services, the migrate command, factory, seeder, dead resources; removed the `collab_opportunity_id` dual-write; `kolab_id` is now canonical. The `/opportunities` API shim over `KolabService` is intentionally retained pending mobile migration (`kolabing-app` #20). (§2)
- [ ] **Finish System A removal (#31):** port the freemium collab limit + portfolio-photo parity into `/kolabs`, then remove the `/opportunities` shim + `OpportunityService`/`OpportunityController`, and drop the archived `collab_opportunities` table + `collab_opportunity_id` columns. Gated on mobile `kolabing-app` #20. (§2, §3, §7)
- [ ] **Add `coliving` to `BusinessOnboardingRequest::BUSINESS_TYPES`** so the spec list in `ROLES-AND-PERMISSIONS.md` §2.1 actually validates. (Hot spot #7)
- [ ] **`Admin\ManagedUserController::destroy()` should run the full `ProfileService::deleteProfile()` cleanup**, not a bare soft-delete (§6).
- [ ] **Document the attendee role in `ROLES-AND-PERMISSIONS.md`** — track is shipped (§11) but the canonical doc still says "deferred / out of scope". A first-pass §7 has been added; confirm scope, pricing (free?), and gating with Daniel.
- [ ] Never paywall communities anywhere; never remove either role's ability to post/apply (golden rules).

---

## 9. Admin operator surfaces — added 2026-06-01

The admin panel is a **server-rendered Blade surface inside this Laravel backend**, mounted under `/admin/*` and gated by two layers:

1. `auth:admin` — a dedicated `admin` guard (`config/auth.php:44`) backed by the `users` table (separate from `profiles`). Login at `GET /admin/login`.
2. `maintainer` middleware (`App\Http\Middleware\EnsureAdminUserIsMaintainer`) — requires `users.is_maintainer = true`.

| Action | Route | Backend | Effect |
|---|---|---|---|
| Soft-delete user | `DELETE /admin/users/{profile}` → `Admin\ManagedUserController::destroy` | `ManagedProfileService::delete()` → `$profile->delete()` | Bare soft-delete. **Does not run the full cleanup** that the user-facing endpoint does (see §6 — open checklist item). |
| Grant subscription | `POST /admin/users/{profile}/subscription/grant` → `::grantSubscription` | `ManagedProfileService::grantSubscription($profile, $months = 12)` | `updateOrCreate` `business_subscriptions` with `status = active`, `source = maintainer`, `current_period_start = now()`, `current_period_end = now()+12mo`. Aborts 422 if profile is not `business`. |
| Revoke subscription | `POST /admin/users/{profile}/subscription/revoke` → `::revokeSubscription` | `ManagedProfileService::revokeSubscription` | Sets `status = inactive`, `cancel_at_period_end = true`. Standard re-gate (`ROLES-AND-PERMISSIONS.md §2.8`) then applies. |
| Force-cancel collaboration | `POST /admin/kolabs/{kolab}/collaboration/cancel` → `Admin\KolabController::cancelCollaboration` | `CollaborationService::cancel($collab, $reason, $cancelledBy = null)` | Requires a reason (min 3 chars). Persists `cancellation_reason`, `cancelled_at`, leaves `cancelled_by_profile_id` null — **`null` = cancelled by maintainer**. |
| Force-complete collaboration | `POST /admin/kolabs/{kolab}/collaboration/complete` → `Admin\KolabController::completeCollaboration` | `CollaborationService::adminForceComplete($collab, $reason)` | Bypasses the completion-confirmation gate (§3, §10). Requires a reason. Persists `completion_reason`, stamps `completed_at`, leaves `completed_by_profile_id` null. No XP awarded. |

**Key invariant for the role logic:** an admin-granted subscription is indistinguishable from a paid sub at the gate level. `Profile::hasActiveSubscription()` checks `status == active`; it does not branch on `source`. The `source` column is purely for audit and analytics. **Do not** add `source == stripe` checks anywhere — that would silently lock out maintainer-granted users.

**Stripe web checkout (`source=stripe`), added 2026-08-15 (BE-NF-17, branch `feat/stripe-web-checkout`).** The `stripe` source value is now actually produced in code (previously only `maintainer` + `apple_iap` had a write path). Flow: an authenticated **business** profile calls `POST /api/v1/me/subscription/checkout` (`SubscriptionController::createCheckoutSession`, business-only 403 gate mirroring the paywall) → `StripeService::createCheckoutSession` opens a Stripe Checkout Session in `subscription` mode, with the price id resolved **server-side** from `config('subscriptions.business.stripe.{plan}.stripe_price_id')` (never client input) and `client_reference_id`/metadata carrying `profile_id` (+ optional `referral_code`). Stripe then calls the **public** `POST /api/v1/webhooks/stripe` (`StripeWebhookController`, signature-verified against `config('services.stripe.webhook_secret')`, no auth by design): `checkout.session.completed` → `SubscriptionService::activateFromStripeSession` `updateOrCreate`s the `business_subscriptions` row (keyed on `profile_id`, so redelivered events converge — idempotent) with `status=active`, `source=stripe`, `stripe_customer_id`/`stripe_subscription_id`, and rewards the referral on first paid sub; `customer.subscription.updated`/`deleted` → `syncFromStripeSubscription` (keyed on `stripe_subscription_id`). The resulting row is a normal `active` sub and hits the same gate as `maintainer`/`apple_iap` — **the invariant above still holds**. Return URLs are allow-listed (`kolabing://` scheme or `config('services.stripe.allowed_return_hosts')`, via the shared `ValidatesReturnUrl` trait) to prevent open-redirect.

**Manage / cancel (Stripe Billing Portal), same branch.** `POST /api/v1/me/subscription/portal` (`SubscriptionController::billingPortal`, business-only 403 gate) → `StripeService::createBillingPortalSession` returns a hosted portal URL where the customer manages or cancels. The Stripe **customer id is read server-side from the caller's own `business_subscriptions` row** (no client input → no IDOR); a profile with no Stripe subscription (Apple/maintainer sub or none → blank `stripe_customer_id`) gets **409**. A subsequent cancel arrives back as a `customer.subscription.updated`/`deleted` webhook and syncs via `syncFromStripeSubscription`. `return_url` uses the same allowlist. There is intentionally **no bespoke cancel/reactivate API route** — cancellation happens inside the portal.

---

## 10. Lifecycle observability columns — added 2026-06-01

Added by the 2026-05-31 admin stats sprint. These columns are **observability-only** — no role logic reads them, and no gate behaviour depends on them. They exist so the admin stats dashboard can compute time-to-accept, time-to-complete, and cancellation reports.

| Column | Type | Stamped by |
|---|---|---|
| `applications.accepted_at` | timestamp nullable | `ApplicationService::accept()` |
| `applications.declined_at` | timestamp nullable | `ApplicationService::decline()` |
| `applications.withdrawn_at` | timestamp nullable | `ApplicationService::withdraw()` |
| `collaborations.activated_at` | timestamp nullable | `CollaborationService::activate()` |
| `collaborations.cancelled_at` | timestamp nullable | `CollaborationService::cancel()` |
| `collaborations.cancellation_reason` | varchar 500 nullable | `CollaborationService::cancel()` |
| `collaborations.cancelled_by_profile_id` | foreignUuid nullable | `CollaborationService::cancel()` — **null = maintainer-cancelled** |
| `profiles.last_active_at` | timestamp nullable (indexed) | `TouchProfileActivity` middleware on the API `auth:sanctum` group; throttled to once per 5 minutes per profile |
| `collaborations.completion_reason` | varchar 500 nullable | `CollaborationService::adminForceComplete()` |
| `collaborations.completed_by_profile_id` | foreignUuid nullable | reserved for participant-completion paths; admin force-complete leaves it null (= maintainer) |
| `collaborations.auto_completed_at` | timestamp nullable | `CollaborationService::autoComplete()` (called by `app:auto-complete-stale-collaborations`) |
| `collaboration_feedback.mirrored_from_review` | boolean default false | `CollaborationFeedbackService::mirrorFromReview()` (stub rows created when a legacy client POSTs `/review`) |
| `collaboration_feedback.expectation_match` / `would_recommend` | bool **nullable** (was NOT NULL) | relaxed in the same migration so mirrored stubs sit alongside rich rows |
| `collaboration_completions` (new table, PR 1 2026-06-26) | `id`, `collaboration_id`, `profile_id`, `role` (`creator`\|`applicant`), `status` (`yes`\|`no`\|`not_yet`), `note` varchar 500 nullable, timestamps; unique on `(collaboration_id, profile_id)` | `CollaborationCompletionService::submit()`. Gates `/complete` — see §0 item 10, §3. |

Backfill for legacy rows: `php artisan app:backfill-lifecycle-timestamps [--dry-run]` copies `updated_at` into the matching transition column. Run once per environment after deploy.

**Auto-completion scheduler:** `app:auto-complete-stale-collaborations` runs `dailyAt('03:00')` per `routes/console.php`. It gates purely on `collaboration_completions` (no feedback fallback). Configurable thresholds in `config/collaborations.php`:
- `auto_complete_grace_days_after_first_completion_confirmation` (default 3): days since a `yes` confirmation was set (measured from the row's `updated_at`, so a `not_yet→yes` change gets a fresh window) before a stale collab is eligible. Never fires if any completion row has `status = 'no'` OR `'not_yet'` (an explicit refusal/defer is left for manual/admin resolution).
- `complete_requires_completion_confirmation` (default true): the `/complete` completion-confirmation gate. Soft-rollout knob if a mobile cutover regresses.
- `complete_requires_feedback` (deprecated, no longer read by `CollaborationService::complete()`): kept only so a pre-existing `.env` setting doesn't silently affect anything else; safe to delete once confirmed unused everywhere.

---

## 11. Attendee role — first-pass map (added 2026-06-01, [VERIFY] scope with Daniel)

The attendee track ships substantial code despite `ROLES-AND-PERMISSIONS.md §0` still labelling attendees "deferred". Audit summary:

**Identity & registration**
- `profiles.user_type = 'attendee'`, 1:1 to `attendee_profiles` (`total_points`, `total_challenges_completed`, `total_events_attended`, `global_rank`).
- Email/password registration: `POST /api/v1/auth/register/attendee` → `AuthController::registerAttendee` → `AuthService::registerAttendee` (`:443`).
- Google / Apple OAuth path: `AuthService` handles `user_type = attendee` alongside business and community (`:223`, `:253`).

**Gates already applied (audited)**
- `Profile::hasActiveSubscription()` returns `false` for any non-business — so an attendee can never satisfy the business paywall, by design.
- `Application` and `Kolab` policies / services do not allow attendees to apply or publish (verify per-policy: **[VERIFY]** confirm `ApplicationPolicy` / `KolabPolicy` deny attendees explicitly rather than relying on user_type discrimination upstream).

**Endpoints attendees can hit (live in `routes/api.php`)**
- **Check-in:** `POST /events/{event}/generate-qr` (organizer), `POST /checkin` (attendee scans), `GET /events/{event}/checkins`. Handled by `CheckinController` + `CheckinService` — `CheckinService::__invoke()` bumps `attendee_profiles.total_events_attended`.
- **Challenges:** system + custom challenges per event (`ChallengeController`), peer-to-peer initiation, verify / reject (`ChallengeCompletionController`), per-attendee history `GET /me/challenge-completions`. `ChallengeCompletionService::verify()` writes to `point_ledger` and bumps `attendee_profiles.total_challenges_completed`.
- **Leaderboards:** per-event and global (`LeaderboardController`).
- **Badges:** system badge list + my badges (`BadgeController`). Awarded by `BadgeService` on milestone thresholds (e.g. `BadgeMilestoneType::LoyalAttendee` triggers at `total_events_attended >= milestone_value`).
- **Reward wallet:** `GET /me/rewards`, redemption flow (`RewardWalletController`).

**Points & money**
- `point_ledger` is append-only with `event_type` enum (`CollaborationComplete`, `FirstKolabBonus`, `ReviewPosted`, `UgcPosted`, `ReferralConversion`, `Withdrawal`). Attendees accrue points via `CheckinService`, `ChallengeCompletionService`, and `BadgeService`.
- `wallets` and `withdrawal_requests` exist with `profile_id` FK — **[VERIFY] with Daniel** whether attendees can actually withdraw cash, or whether withdrawals are community-only (the `ROLES-AND-PERMISSIONS.md §3.5` mentions €0.25/point with €75 threshold for the community side; the attendee equivalent is unspecified).

**What attendees CANNOT do (confirmed by code paths)**
- Create kolabs or opportunities (creator-resolution paths route to business / community profiles only).
- Apply to kolabs (no `applicant_profile_type = attendee` exists in `applications.applicant_profile_type` enum).
- Subscribe (attendees are not businesses; the paywall and grant routes both reject non-business profiles).
- Chat (chat is between matched business and community on an accepted application).

**Outstanding decisions for the user**
- Are attendees in launch scope? (Code says yes — endpoints are live. Spec says no.)
- Pricing model? (Code currently treats attendees as free — no paywall, no subscription path. Confirm intentional.)
- Should attendee gamification credits convert to real money via `wallets` + `withdrawal_requests`, or is that community-only?
- Should the canonical permissions doc grow a full §4 covering attendees, replacing the "deferred" stub?

Until those are resolved, treat this section as the source of truth for what attendees can do, and treat `ROLES-AND-PERMISSIONS.md §0` (attendee = deferred) as **stale**.

### 11.1 General missions vs. event challenges (added 2026-06-27, #49)

`MissionController::index()` (`GET /api/v1/me/missions`, `app/Http/Controllers/Api/V1/MissionController.php:29`)
returns only missions matching every one of: `is_system=true`, `event_id IS NULL`,
`app_visible=true`, `trigger_action IS NOT NULL`, `trigger_action` in `MissionTrigger::isLive()`'s
true set, `audience` in `MissionService::audiencesFor($viewer)`, and now within
`[starts_at, ends_at]`. Of the 49 missions `SystemChallengeSeeder` seeds, exactly
**18 have `app_visible=true`** (5 attendee, 7 business, 6 community) — see
`database/seeders/SystemChallengeSeeder.php` (`row()` helper, `$appVisible` 11th arg,
default `false`). All 18 use a live trigger; the rest of the seeded set sits inert
pending Phase 2/3 trigger wiring or a future product decision to flip `app_visible`.

The **earning path** (`MissionService::activeMissionsFor`) applies the same
`app_visible=true` gate as the read path (PR #49 review fix, 2026-06-28), so a
hidden mission accrues no silent progress — a profile only earns missions it can
also see. `MissionTrigger::isLive()` reads the live set from
`config('gamification.live_triggers')`; recurring `period_key`s are bucketed in
`config('gamification.local_timezone')` (default `Europe/Madrid`); and completed
mission credits set `point_ledger.challenge_id` for attribution.

Event challenges (`trigger_action IS NULL`, peer-verified, attached to a kolab event)
are excluded from `/me/missions` by the `whereNotNull('trigger_action')` clause, and
general missions are symmetrically excluded from every event-scoped surface. That
exclusion is enforced independently in **three** places (all filter
`whereNull('trigger_action')` / `whereNull('trigger_action')`-equivalent):

| Endpoint | Code |
|---|---|
| `GET /api/v1/challenges/system` | `SystemChallengeController` (`app/Http/Controllers/Api/V1/SystemChallengeController.php:23`) |
| Admin defaults matrix | `Admin\ChallengeDefaultsController` (`app/Http/Controllers/Admin/ChallengeDefaultsController.php:33`) |
| `GET /api/v1/events/{event}/challenges` | `ChallengeService::listForEvent()` (`app/Services/ChallengeService.php:23`) |

`challenges.app_visible` (migration `2026_06_22_100150_add_app_visible_to_challenges_table.php`,
boolean default `false`) is the v1-launch curation gate layered on top of the
trigger-null/not-null split — it does not affect event-challenge visibility at all,
only which *general* missions the app surfaces today.

---

## 12. Community members & customisable tiers — backend map (added 2026-06-03)

Implements `ROLES-AND-PERMISSIONS.md §8`. Service-layer only (no DB triggers), Sanctum-authed under `/api/v1`.

### 12.1 New tables

```
communities (id uuid PK, owner_profile_id FK->profiles cascade,
             community_profile_id FK->community_profiles nullOnDelete null,
             name, slug UNIQUE, type[greek|fitness|running|business|other],
             description?, avatar_url?, is_primary bool default true,
             join_policy[open|invite_only] default open, timestamps, softDeletes)
 ├─1:N─ community_tiers   (id uuid PK, community_id FK->communities cascade,
 │                         name, rank int (ascending = higher), color?,
 │                         assignment_rule[manual|xp_threshold|tenure|events_attended],
 │                         threshold int? (XP / days / event count; null for manual),
 │                         permissions json? ({view,chat_channels,perks,capabilities}),
 │                         is_default bool default false (exactly one per community), timestamps)
 └─1:N─ community_members (id uuid PK, community_id FK->communities cascade,
                           profile_id FK->profiles cascade (an attendee account),
                           tier_id FK->community_tiers nullOnDelete null,
                           can_manage bool default false (ORTHOGONAL to tier — D1),
                           status[active|inactive|removed] default active,
                           joined_at, tier_assigned_at?, timestamps,
                           UNIQUE(community_id, profile_id))

events.community_id  FK->communities nullOnDelete null   ← the §8.6 linkage
```

Enums (`app/Enums`): `CommunityType`, `TierAssignmentRule`, `JoinPolicy`, `CommunityMemberStatus`. Models: `Community`, `CommunityTier`, `CommunityMember` (+ `Profile::ownedCommunities()` / `communityMemberships()`, `Event::community()`).

### 12.2 The cap gate — NOT the paywall

| Concept | Code | Notes |
|---|---|---|
| Free community cap | `CommunityService::create()` → `config('communities.max_free_communities', 1)` → `CommunityLimitReachedException` | Controller catches → **HTTP 422** `{error: community_limit_reached}`. **Never** calls `hasActiveSubscription()`. Reserved for NF-7 Community Premium. |
| Default tier | `CommunityService::create()` auto-creates one `is_default` manual tier (`Member`, rank 1) | New joiners land here; it is the floor auto-rules promote away from. |
| Authorization | `CommunityPolicy::manage()` = owner OR active member with `can_manage` | Registered in `AppServiceProvider`. Mutating tiers/roster/community requires it. No subscription check. |

### 12.3 Endpoints (all `auth:sanctum`, `routes/api.php`)

| Method + path | Controller | Gate |
|---|---|---|
| `GET /me/communities` | `CommunityController@index` | auth (owned only) |
| `GET /me/memberships` | `CommunityController@myMemberships` | auth (own memberships + tier) |
| `POST /communities` | `CommunityController@store` | cap gate; 201 / 422 |
| `GET /communities/{community}` | `@show` | auth |
| `PATCH /communities/{community}` | `@update` | `manage` |
| `POST /communities/{community}/join` | `@join` | open only, else **403** `invite_only` |
| `GET /communities/{community}/tiers` | `CommunityTierController@index` | auth |
| `POST /communities/{community}/tiers` | `@store` | `manage` |
| `PATCH /tiers/{tier}` | `@update` | `manage` (on `tier->community`) |
| `DELETE /tiers/{tier}` | `@destroy` | `manage`; **422** `cannot_delete_default_tier` |
| `GET /communities/{community}/members` | `CommunityMemberController@index` | auth (paginated, nested tier+profile) |
| `POST /communities/{community}/members` | `@store` | `manage` (invite/add, any join policy) |
| `PATCH /communities/{community}/members/{member}` | `@update` | `manage` (tier_id / can_manage / status; 422 if tier not in community) |
| `DELETE /communities/{community}/members/{member}` | `@destroy` | `manage` (soft → status removed) |
| `GET /leaderboard/global?community_id=` | `LeaderboardController@globalLeaderboard` | chapter scope (404 if community unknown) |

Resources (`app/Http/Resources/Api/V1`): `CommunityResource`, `CommunityTierResource` (always returns the four permission buckets), `CommunityMemberResource` (nested `tier` + `profile{name,avatar_url}` plus flat `tier_id`). Shapes match the app's Dart models in `kolabing-app/lib/features/community/models/`.

### 12.4 Auto-assignment

- `TierAssignmentService::evaluateMember()` / `evaluateCommunity()`: promotes to the **highest-rank** non-manual tier satisfied; never demotes; never overwrites a leader-set non-default manual tier; skips non-active members.
  - `xp_threshold` → `SUM(point_ledger.points where profile_id = member)` ≥ threshold (append-only ledger is the XP source of truth, swap to NF-5 `GET /gamification/config` when it ships).
  - `tenure` → `joined_at` diff in days ≥ threshold.
  - `events_attended` → count of `event_checkins` for the member on events where `events.community_id = {community}` ≥ threshold.
- `app:evaluate-community-tiers [--dry-run]` (`routes/console.php`, `dailyAt('02:00')`).
- On-check-in hook: `CheckinService::checkin()` re-evaluates the member's active memberships immediately (wrapped in try/catch + `Log::warning` so a hook failure never breaks check-in).

### 12.5 What this surface must never do
- Never call `Profile::hasActiveSubscription()` or throw `SubscriptionRequiredException`. The cap is the only gate and it is config-driven.
- Never add a `user_type` enum value for "community member" — the wire value stays `attendee` (D4).
- Never couple `can_manage` to the top tier (D1).


### 12.6 Web panel wiring (BE-NF-34, added 2026-08-19)

**New table `community_invitations`** — `id` (uuid pk), `community_id` (fk
communities, cascade), `email`, `tier_id` (fk community_tiers, nullOnDelete),
`token` (string 64, unique), `invited_by_profile_id` (fk profiles, nullOnDelete),
`status` (`pending|accepted|revoked|expired`), `expires_at`, `accepted_at`,
`accepted_profile_id` (fk profiles, nullOnDelete), timestamps. Indexes:
`(community_id, status)`, `(email, status)`, unique `token`. A partial unique
index on `(community_id, email) WHERE pending` is **not** portable to SQLite (the
test driver), so per-email uniqueness is enforced in
`CommunityInvitationService::invite()` by upserting the pending row — the same
shape as `CommunityMemberService::upsertMember`. TTL from
`config('communities.invitation_ttl_days')` (default 30).

**New / changed endpoints** (all manage-gated via `CommunityPolicy@manage` except
`accept`, which is authorized by the token, and the public `/c/{slug}` page):

| Method | Path | Notes |
|---|---|---|
| GET | `/communities/{c}/members` | **now manage-gated** (carries emails); `?search`, `?status`, `?tier_id` (accepts `none`), `?can_manage`, `?sort`, `?direction`, `?limit`≤100. **Default excludes `removed`**; `?status=all` opts back in. |
| GET | `/communities/{c}/members/{member}` | drawer: member + metrics + last 25 `community_point_ledger` rows |
| PATCH | `/communities/{c}/members` | bulk (≤100 ids) tier / `can_manage` / status; ids outside `{c}` are counted `skipped`, never written |
| GET | `/communities/{c}/stats` | member counts by status, `new_this_month`, `dormant_30d`, tier distribution, pending join-requests + invitations, 30-day points/check-ins/attendance, top 5 |
| GET | `/communities/{c}/invitations` | `?status=pending` (default) \| `all` |
| POST | `/communities/{c}/invitations` | `email` or `emails[]` (≤50) + optional `tier_id`; per-row results; `422 tier_not_in_community` |
| POST | `/invitations/{invitation}/resend` | refreshes `expires_at`, re-queues the mail |
| DELETE | `/invitations/{invitation}` | revoke (`status = revoked`) |
| POST | `/invitations/accept/{token}` | auth required; `404 invitation_not_found`, `422 invitation_not_claimable` |
| GET | `/c/{slug}` (web) | public join landing; `noindex` when invite-only |

**`POST /communities/{c}/members` is unchanged.** It still returns
`404 profile_not_found` for an address with no Kolabing account. Turning that 404
into a 201 would silently change the contract the mobile client is written
against, so invitations are a separate resource; the web panel catches the 404 and
offers the email invitation instead.

**The invite page lives on the APP host (BE-NF-38, corrected 2026-08-20).**
`config('communities.invite_base_url')` now defaults to
`https://app.kolabing.com/c`, and `kolabing.com/c/{slug}` 301-redirects there
preserving the query string. The reason is the CSP: `AddSecurityHeaders` grants
`'unsafe-eval'` and `accounts.google.com` only to `config('webapp.host')`. Alpine
compiles every `x-*` expression with `new Function`, so on the marketing host the
page's CTA could not run — it rendered an **empty** call-to-action, since the
buttons lived inside `<template x-if>` blocks that never evaluated. Moving the page
was chosen over relaxing the header.

A signed-out visitor now signs up in place: `POST /auth/google`
(`user_type: attendee` — community members are attendees on the wire, §8.1 D4),
then `PUT /me/profile` (multipart `name`, `phone_number`, `profile_photo`), then the
join/accept call. No new endpoint; every field was already accepted. The route is
registered twice (`/c/{slug}` and `/{locale}/c/{slug}`), so the controller reads the
slug **by name** from the route — a positional argument picks up the locale on the
prefixed one.

**Claim-on-register.** `AuthService::afterRegistration()` (was
`startOnboardingDrip`, called from all five registration paths) now also calls
`CommunityInvitationService::claimForSafely($profile)`, which accepts every
`pending`, unexpired invitation matching the new profile's email. Guarded with
try/catch + `Log::warning` — the same never-break-signup contract as
`OnboardingService::autoJoinCommunities` and the mission hooks.

**No N+1 on the roster.** `CommunityRosterQuery` LEFT-JOINs three grouped
aggregate subqueries (`community_points`, `event_checkins` ⋈ `events` on
`community_id`, `MAX(created_at)` over `community_point_ledger`) plus `profiles`
and the extended-profile tables, so a page costs a fixed number of queries at any
member count. `tests/Feature/Api/V1/CommunityRosterMetricsTest.php` asserts 3
members and 30 members cost the same number of queries (BE-NF-15's O(N)-per-row
pattern is the thing being prevented). `CommunityMemberResource` emits the metrics
only when the caller preloaded them, via the same transient-attribute fast path
`CommunityResource` uses — so mobile's existing calls keep the lean payload and
its cost.

**Resource fix:** `CommunityMemberResource::profileDisplayName()` read the
extended profile first, but `attendee_profiles` has **no `name` column** and every
community member is an attendee — so the roster rendered email prefixes instead of
names. It now reads `profiles.name` first, and the payload gained
`profile.handle` + `profile.email`.

**Layout note:** `marketing-page.blade.php` gained an overridable `robots` prop so
an invite-only `/c/{slug}` can emit `noindex` without a second layout.

**This surface must never** (restating §12.5, now covered by a test):
`CommunityNeverPaywalledTest` exercises every endpoint above as an unsubscribed
owner and an unsubscribed `can_manage` attendee.

---

## 13. Public reputation aggregates (added 2026-06-30, PR 4)

The `collaboration_reviews` table now carries five category-specific star ratings (`communication_rating`, `reliability_rating`, `fit_rating`, `value_rating`, `repeat_rating`), a `public_comment` (user-authored text), and a `public_comment_visible` boolean gate (default true). The computed accessor `overall_rating` averages the five stars, falling back to the legacy `rating` column if a reviewer has not set the new ratings yet.

`public_comment_visible` gates only the `public_comment` field (not the legacy `body` / `note`). `ProfileService::getReputationSummary()` aggregates these fields into a public-facing reputation block (`average_rating`, `review_count`, and a `breakdown` object with category averages — **`unique_partner_count` was removed in PR 5**), counting only reviews on `status = completed` collaborations. A per-pair fairness cap limits each (reviewer_profile_id, reviewed_profile_id) pair to at most 2 reviews contributing to the aggregate; this is enforced at query time via a SQL window function (`ROW_NUMBER() OVER (PARTITION BY reviewer_profile_id ORDER BY created_at ASC, id ASC)`; `reviewed_profile_id` is fixed by the outer `WHERE`) with no schema change. The `id ASC` tiebreaker keeps the ranking deterministic when two reviews share a `created_at` (uuid7 ids are time-ordered), so the per-pair cap always keeps the genuinely earliest reviews regardless of index/scan order. The block is serialized as the `reputation` key on `PublicProfileResource` (sibling to `recent_reviews`), and is accessible to any authenticated viewer (same public-profile visibility rules apply). Each object in `recent_reviews` includes `is_verified_kolab_review: true` on every review item.

**Caching (#76):** `getReputationSummary()` is cached per profile under `profile:reputation:{id}` (24h backstop TTL) and its `completed_kolabs_count` is the single source for the resource's top-level field too (the previously-duplicated COUNT was removed). The cache is invalidated by model observers — `CollaborationReviewObserver` (any review received created/updated/deleted) and `CollaborationObserver` (a collaboration created / status-changed / deleted, busting both parties) — so the returned values are always fresh regardless of the write path (API, admin moderation, seeders). Values and shape are unchanged; only the read cost is.

---

## 14. Web payment flow — buy, confirm, manage (added 2026-08-17)

The commercial half of the Business paywall. This section changes **no gate** — see §3 for enforcement. It documents how a `business_subscriptions` row with `source = stripe` comes into existence from the web, and how failure states surface.

### 14.1 Endpoints

| Route | Controller | Notes |
|---|---|---|
| `POST /api/v1/me/subscription/checkout` | `SubscriptionController::createCheckoutSession` | business-only (403). `success_url`/`cancel_url` validated by `ValidatesReturnUrl` (deep-link scheme OR `config('services.stripe.allowed_return_hosts')`). Price id resolved **server-side** from `config/subscriptions.business.stripe.{plan}.stripe_price_id` — never client input. |
| `POST /api/v1/me/subscription/checkout/confirm` | `SubscriptionController::confirmCheckout` | **new.** body `{session_id}` matching `/^cs_[A-Za-z0-9_]+$/` (422 otherwise). business-only (403). |
| `POST /api/v1/me/subscription/portal` | `SubscriptionController::billingPortal` | Stripe customer id read from the caller's own row (no IDOR); 409 when there is no Stripe sub (Apple/maintainer). |
| `POST /api/v1/webhooks/stripe` | `StripeWebhookController` | public, signature-verified. `checkout.session.completed` → activate; `customer.subscription.updated/deleted` → sync. |

### 14.2 Why `confirm` exists (do not remove it)

Activation used to happen **only** on `checkout.session.completed`. That made a completed sale invisible to the buyer until the webhook landed — and on any environment where the webhook is not yet registered in the Stripe dashboard, never. A business would pay and still be paywalled.

`confirmCheckout` closes that hole:

1. `StripeService::retrieveCheckoutSession($sessionId)`.
2. **Ownership check** — `StripeService::sessionProfileId()` (`client_reference_id`, falling back to `metadata.profile_id`) must equal the caller's `profiles.id`, else **403**. A Checkout Session id is not a bearer token; without this check anyone holding a session id could attach someone else's payment to their own account.
3. `StripeService::sessionIsPaid()` — `payment_status ∈ {paid, no_payment_required}` OR `status = complete`. `no_payment_required` is what a 100%-off promotion code produces, so it must count as paid. Otherwise **409** with `{"status": "pending"}`.
4. `SubscriptionService::activateFromStripeSession($session)` — the **same** method the webhook calls.
5. Stripe API error → **502**.

Idempotency is structural: `activateFromStripeSession` does `BusinessSubscription::updateOrCreate(['profile_id' => …], …)` against a unique `profile_id`, and the referral reward is already first-paid-guarded in `ReferralService`. Confirm-then-webhook and webhook-then-confirm converge on one row. `activateFromStripeSession` now **returns** the upserted `?BusinessSubscription` (the webhook ignores it) so `confirm` can answer with a `SubscriptionResource`.

**The webhook is still required** — it is the only source for renewal, cancellation and dunning (`past_due`). `confirm` only removes it from the critical path of the first activation.

### 14.3 Checkout Session payload

`StripeService::checkoutSessionParams()` is split out of the SDK call so the commercial shape is testable without mocking `StripeClient` (`CheckoutSessionParamsTest`). It adds, on top of the pre-existing mode/line_items/metadata:

- `customer` when the profile already has `business_subscriptions.stripe_customer_id`, else `customer_email` from `profiles.email`. **Never both — Stripe rejects that.** Without this a repeat buyer gets a second Stripe customer, which orphans the Billing Portal lookup in §14.1.
- `allow_promotion_codes: true` — Stripe-dashboard-managed discount campaigns. **This is not the referral code.** Promotion code = buyer discount, redeemed on Stripe's screen. Referral code = rides on session metadata, rewards the referrer via `ReferralService::rewardFirstPaidSubscription`. Two independent mechanisms; do not merge them.
- `locale` — `en → en`, `es → es`, **`ca → es`** (Stripe Checkout has no Catalan locale), anything else `auto`.

### 14.4 Client-visible lifecycle fields

`UserResource` gains, **for business profiles only** (additive — no existing key changed shape):

| Key | Value |
|---|---|
| `subscription_status` | `business_subscriptions.status` enum value, or `null` when the business never subscribed |
| `subscription_cancel_at_period_end` | bool, `false` when there is no row |

`has_active_subscription` is unchanged and remains the only field the paywall reads. **`past_due` is not active** — the two fields are for *warning* copy, never for gating.

Web surfaces: `resources/views/webapp/partials/billing-banner.blade.php` (included from the sidebar partial, so every authed page gets it) shows the failed-payment alert and opens the Billing Portal via `kbShell().openBillingPortal()`. `kbStatus()` in `layout.blade.php` maps `past_due` to the danger pill; `lang/{en,es,ca}/webapp.php` `status.past_due` supplies the label (an unmapped enum would render as `STATUS.PAST_DUE`).

### 14.5 Public pricing page

`GET /pricing` (`pages.pricing`) and `GET /es/pricing` (`pages.es.pricing`) on `x-layouts.marketing-page`, markup shared via `pages/partials/pricing-content.blade.php`. Prices and the derived per-month/saving figures come from `config/subscriptions.php` — the same config the checkout bills. `Product` + `Offer` + `FAQPage` JSON-LD, hreflang pair, listed in `/sitemap.xml` and `/llms.txt`. CTA → `{webapp.url}/register?type=business&plan={plan}`; `/register` stashes `plan` and `postAuthPath()` forwards it to `/subscription?reason=welcome&plan=…`.

### 14.6 Required prod configuration

`STRIPE_SECRET_KEY`, `STRIPE_WEBHOOK_SECRET`, `STRIPE_MONTHLY_PRICE_ID`, `STRIPE_THREE_MONTHS_PRICE_ID`; the webhook endpoint registered in the Stripe dashboard for the three event types in §14.1; Billing Portal + cancellation enabled there. A plan with no configured `stripe_price_id` is hidden on `/subscription` rather than offered as a button that 502s (unless *no* plan is configured, in which case both render — local/CI preview).

---

## 15. Chat on the web panel — backend map (added 2026-08-18)

The web panel (`app.kolabing.com/chats`) adds **no endpoints and no columns**. It is a second client over the chat API that
mobile already uses, so everything below is a map of existing code — with three real gaps called out at the end.

### 15.1 What the page calls

| Purpose | Endpoint | Notes |
|---|---|---|
| Inbox | `GET /chats` | `ChatService::visibleThreads()` — collaboration + community + event threads merged, sorted by `last_message_at`, each carrying transient `unread_count` and a `last_message` preview. **Unpaginated** (see BE-NF-15 scale audit). |
| Badge | `GET /chats/unread-count` | `data.total`. Loaded in `kbShell()` next to the notification count; they are two different numbers. |
| Read a thread | `GET /chats/{thread}/messages` | Works for **every** thread type, collaboration included — `canAccessThread()` routes collaboration through `canParticipate()`, and messages are found by `thread_id` (populated by `sendMessage()` and backfilled by `2026_06_04_000004_backfill_chat_threads`). Oldest-first under `data.messages`; also moves the viewer's read pointer. |
| Send (Kolab) | `POST /applications/{application}/messages` | **Required for collaboration threads** — see §15.4. |
| Send (group) | `POST /chats/{thread}/messages` | Community main / custom channel / event. |
| Read pointer | `POST /chats/{thread}/read` | `markThreadRead()` handles both read models: per-message `read_at` for collaboration, `chat_thread_reads` for group threads. |
| Channel CRUD | `POST /communities/{community}/chats`, `PATCH`/`DELETE /chats/{thread}` | `CommunityPolicy@manage` on create; `canManageCustomChat()` (custom threads only) on rename/delete. ≤5 cap raises `DomainException` → 422. |
| Blocking | `GET`/`POST /chats/{thread}/bans`, `DELETE /chats/{thread}/bans/{profile}` | `canManageThread()` — any thread with a `community_id` the viewer manages, main chat included. Returns `banned_profile_ids` only, so the panel joins names from `GET /communities/{community}/members`. |
| Manageable communities | `GET /me/communities` | Owned communities only — see §15.5. |

Pagination note: `getThreadMessages()` paginates **oldest-first**, so page 1 is the beginning of the conversation and the
newest messages live on `last_page`. The panel therefore reads page 1, and re-reads `meta.last_page` when there is more than
one page; "load older" walks the page number **down**. Do not "fix" this by reversing the order — the mobile client reads the
same shape.

### 15.2 Real-time

`NewChatMessage` (`ShouldBroadcast`) already broadcasts `message.sent` on `PrivateChannel('chat.thread.{id}')` (plus the
legacy `chat.application.{id}`), and `routes/channels.php` authorizes it with the same `canAccessThread()` used by REST —
that is the security boundary; never authorize a chat channel on community membership alone.

The panel subscribes with `public/webapp-assets/kb-realtime.js`, a hand-written Pusher-protocol client (connect → auth →
subscribe → `message.sent`, with ping/pong and backoff). It is not laravel-echo + pusher-js because the web app ships static
self-hosted assets with no bundler and the CSP forbids third-party origins. Private-channel signing goes to
`POST /broadcasting/auth`, which is **at the app root, not under `/api/v1`**, and is Sanctum-guarded — hence a bearer token
plus the same one-shot refresh the REST client uses.

Config: `config('webapp.realtime')` exposes `REVERB_APP_KEY` / `REVERB_HOST` / `REVERB_PORT` / `REVERB_SCHEME` to the browser.
The **app secret is never exposed** (pinned by a test). With `key` unset the socket is disabled and a 4s ticker polls the open
thread (8s) and the inbox (20s) instead, so chat is functional either way.

**Production already runs Reverb** (verified 2026-08-18). Laravel Cloud hosts the managed instance and the client trio is set:
`REVERB_HOST=ws-a0f4ad70-…-reverb.laravel.cloud`, `REVERB_PORT=443`, `REVERB_SCHEME=https`, with `BROADCAST_CONNECTION=reverb`
and `QUEUE_CONNECTION=database`. The handshake was confirmed against that endpoint with `Origin: https://app.kolabing.com`
(and `https://kolabing.com`) → `pusher:connection_established`, `activity_timeout: 30`, so `REVERB_ALLOWED_ORIGINS` already
covers the web panel. A connection with **no** Origin is rejected `4009 Origin not allowed` — expected, and irrelevant to
browsers, which always send one. The client treats 4000–4099 as fatal and stops reconnecting (falling back to polling), so a
future origin/key misconfiguration surfaces as chat that never goes live rather than a reconnect storm.

The full loop was then exercised against a local `reverb:start` + `queue:work` on an isolated SQLite database: handshake →
`POST /broadcasting/auth` (200, `key:signature`) → `pusher:subscribe` → `POST /applications/{id}/messages` → `message.sent`
received on `private-chat.chat.thread.{id}` carrying `{message: {...}}` with the right `thread_id` and `is_own: false`.
One consequence worth remembering: `NewChatMessage` broadcasts with `->toOthers()`, so the sender's own socket never receives
its own message — that is why the composer appends the API's returned message locally instead of waiting for the event.

**`REVERB_PORT` vs `REVERB_SERVER_PORT`.** `config/reverb.php` maps the first to `apps.apps.*.options` (what clients dial) and
the second to `servers.reverb` (what a self-hosted daemon binds — `REVERB_SERVER_HOST` defaults to `0.0.0.0` and is not set in
`.env`; `REVERB_SERVER_PORT` is `6001`). With Laravel Cloud's managed Reverb that server pair is unused — do not "align" them.

`AddSecurityHeaders` adds `wss:` to `connect-src` **for the web-app host only**. CSP treats `ws:`/`wss:` as their own
schemes, so the existing `connect-src 'self' https:` does not cover the socket.

### 15.3 Role behaviour (unchanged, just now visible on web)

Business viewers see only collaboration threads with `last_message_at != null` (`visibleCollaborationThreads()`); custom
channels are gated by tier (`community_tiers.permissions.chat_channels` → `canAccessCustomChat()`); event chats need a
`going` sign-up or leader rights. Communities are never paywalled in any of this (ROLES §4.1, §8.4).

### 15.4 Trap: `sendThreadMessage()` does not notify for collaboration threads

`threadRecipientIds()` returns `[]` for `ChatThreadType::Collaboration`, and `sendThreadMessage()` calls neither
`notifyNewMessage()` nor `syncUnreadMessageReminder()`. Posting a Kolab message to `POST /chats/{thread}/messages` therefore
delivers **no notification, no push, no reminder**. Both clients avoid it by using the application endpoint for Kolab chats
(the panel's split is pinned by `WebAppChatPageTest::test_kolab_chats_send_through_the_application_endpoint`). Converging the
two paths is BACKLOG **BE-FX-13**.

### 15.5 Two more open gaps

- **BE-FX-14 — §2.8 re-gate not enforced for chat.** `canParticipate()` never reads subscription state and the chat routes
  have no subscription middleware, so a lapsed business keeps full chat access even though ROLES §2.8 says it should not.
  Doc/code drift; needs a product decision, not a drive-by patch.
- **BE-FX-15 — `GET /me/communities` returns `ownedCommunities()` only.** A `community_members.can_manage = true` delegate is
  authorized by `canManageCommunity()` for channels and bans, but cannot discover the community id in any client. Fix is
  additive: include managed communities and emit `my_can_manage` on `CommunityResource`.

### 15.6 Tests

`tests/Feature/WebApp/WebAppChatPageTest.php` (12) pins the shell: routes in all three locales, the endpoints the page calls,
the send-path split, the deep links, channel management, CSP `wss:`, the self-hosted client, secret non-exposure, the
no-Reverb fallback, and es/ca copy parity. `tests/Feature/Api/V1/ChatCollaborationThreadEndpointTest.php` (5) covers the
previously untested assumption the panel depends on: the generic thread endpoints against a **collaboration** thread —
oldest-first read, read pointer clearing the badge, outsider 403, and that a Kolab thread cannot be renamed or deleted as a
channel.

---

## 16. Public profile pages — backend map (added 2026-08-19)

Two surfaces over one dataset: the authenticated panel page and the unauthenticated marketing teaser. **No schema change** —
everything below reads existing columns.

### 16.1 What each surface calls

| Surface | Source | Notes |
|---|---|---|
| Panel `app.kolabing.com/profiles/{id}` | `GET /profiles/{id}/public-profile` **(new, additive)** | Rich shape for **either** role: `gallery`, `photos`, `past_events`, `past_collaborations`, `public_stats`, `public_url`. `ProfileService::getPublicProfileDetail()`; attendees throw `ModelNotFoundException` → 404. |
| Panel (reputation) | `GET /profiles/{id}` | The rich endpoint has no reputation block; this one carries `reputation` = `{average_rating, review_count, completed_kolabs_count, breakdown}` where `breakdown` is the five category averages (communication / reliability / fit / value / repeat). |
| Panel (reviews) | `GET /profiles/{id}/reviews?per_page=10&page=N` | `PublicProfileReviewResource` — includes the reviewer, which the public page must not. |
| Panel (history) | `GET /profiles/{id}/collaborations?per_page=10&page=N` | Completed only. |
| Public `kolabing.com/p/{slug}` | **No API.** `PublicProfilePageController` reads models directly | Keeps `/api/v1` authenticated; nothing here may become a way to enumerate the database. |

`GET /communities/{community}/public-profile` is unchanged and still 404s for a business — existing clients depend on that, and
a test pins it. It now delegates to `getPublicProfileDetail()` after its own `isCommunity()` guard.

### 16.2 Why businesses get past events now

`past_events` is a column on **`kolabs`**, written by `CreateKolabRequest` for whoever creates the Kolab — it was never
community-specific. Only `getCommunityPublicProfile()`'s `isCommunity()` guard made it look that way, so a business profile
could never show its own history. `ProfileService::buildCommunityPhotos()` also assumed `communityProfile->profile_photo`; it
now reads `getExtendedProfile()?->profile_photo` so the aggregated photo list works for both roles.

### 16.3 Slugs: `App\Support\PublicProfileLink`

`/p/barcelona-runners-1dd66a` = `Str::slug(display name)` + the last **6** characters of the UUID.

`profiles.handle` would have been the natural key — it is unique and nullable — but it is only required during **attendee**
onboarding, so **5 of 94 production profiles have one**. Keying the public page on it would 404 for almost every business and
community. The suffix form needs no column and no backfill, survives a rename (the tail still resolves; `<link rel="canonical">`
points at the current slug), and `resolve()` also accepts a bare handle or a full UUID so links shared in any shape keep
working. Lookup is `where('id', 'like', '%'.$suffix)` — `right()`/`substr()` disagree on negative offsets between Postgres and
SQLite, and this reads the same on both. It is a sequential scan bounded by the profile count; if profiles grow into the tens
of thousands, add a functional index on the id tail (see BE-NF-15, the scale audit).

### 16.4 The wall (see ROLES §4.2)

`PublicProfilePageController` computes *only* what may be public and passes that to the view; the Blade template is told not to
reach for more off `$profile`. Concretely: about is truncated to 320 chars, photos are sliced to 3 with the remainder shown as a
count, and the single quote comes from the newest `collaboration_reviews` row with `public_comment_visible = true` and a
non-empty `public_comment`, rendered without the reviewer. Contact fields (`instagram`, `tiktok`, `website`) are never passed
in. Tests in `tests/Feature/Marketing/PublicProfilePageTest.php` assert **absence** for each of those, which is the only way a
leak gets caught — a leak does not break the page.

### 16.5 Additive fields added for linking

- `CommunityPublicProfileResource.public_url` — the shareable marketing URL, so no client re-implements the slug rule.
- `NotificationResource.actor_profile_id` — notifications previously carried the actor's name and avatar but no id, so nothing
  could link to them.
- `ChatThreadResource.participant_summary[].id` — same reason, for the chat header.

All three are additive; no existing key changed shape.

### 16.6 SEO

`sitemap.xml` lists profiles that have at least one **completed** collaboration as creator or applicant (capped at 500) — an
empty profile is a thin page. JSON-LD is `LocalBusiness` for a business and `Organization` for a community, with
`aggregateRating` emitted **only** when `review_count > 0`.

### 16.6a Trap: never build JSON-LD inline in a Blade echo (fixed 2026-08-19, BE-FX-17)

Laravel 12 registers an **`@context` Blade directive**, and Blade compiles directives inside `{{ }}` / `{!! !!}` expressions.
So this — which four marketing templates used — is broken:

```blade
{!! json_encode(['@context' => 'https://schema.org', '@type' => 'Organization', …]) !!}
```

The `'@context'` key is replaced by compiled PHP, and the page emits
`{"<?php $__contextArgs = []; … ?>":"https://schema.org","@type":"Organization",…}`. It still parses as JSON, so there is no
error and no visible symptom — the structured data simply has no vocabulary and search engines ignore it. It affected
`Organization` on every marketing page (shared layout), the homepage `@graph`, and `Product` + `FAQPage` on both pricing pages.

Build the array inside a **`@php` block** and echo the encoded string, as `blog/show.blade.php` and `pages/public-profile.blade.php`
always did — `@php` content is stored raw before directive compilation, so the key survives. `tests/Feature/Marketing/StructuredDataTest.php`
now parses every ld+json block on ten marketing URLs and fails if `@context` is missing or a key contains raw PHP.

### 16.6b Indexation bar for public profiles (revised 2026-08-19, BE-FX-19)

The first cut listed any profile with a **completed collaboration** in `sitemap.xml`. Two problems showed up on production: a seeded
`test` account met that bar and was published to the open web, and a profile with no review and no photos is a near-duplicate of
every other empty profile — the thin-page cluster that drags a domain down once there are hundreds.

The bar is now **at least one review, or at least three gallery photos** (`Profile::receivedReviews()` — added for this — or
`galleryPhotos`). The same predicate drives `noindex` on the page itself, so a profile below the bar stays reachable (people share
these links) but asks not to be indexed, and starts being indexed by itself once it has something to show. `/blog` and
`/communities` follow the identical rule via the layout's `noindex` prop: an empty hub is not indexable and not in the sitemap.

Keep the two in step. If the sitemap query and the controller's `noindex` ever disagree, the sitemap advertises pages that tell
Google to go away.

### 16.7 Tests

`tests/Feature/Marketing/PublicProfilePageTest.php` (14) — renders for both roles, the four withheld categories, the 3-photo
cap, private comments never quoted, canonical + JSON-LD + `og:type`, no faked rating, attendee 404, unknown slug 404, renamed
profile still resolves, full UUID resolves, sitemap inclusion/exclusion.
`tests/Feature/Api/V1/ProfilePublicDetailTest.php` (6) — business past events, community parity, `public_url`, attendee 404,
auth required, and that the community route still refuses a business.
`tests/Feature/WebApp/WebAppProfilePageTest.php` (9) — sections, the four endpoints, reviewer identity present *inside* the app,
contact links present, own-profile edit/share, all three locales, every entry point, copy parity, and no leak onto the
marketing host.

---

## 17. Public portfolio — past events, photos, gallery (BE-NF-36, added 2026-08-20)

**No new table, no migration.** Everything below is endpoints, a service change and a
resource change.

### 17.1 New endpoints

| Method | Path | Notes |
|---|---|---|
| PATCH | `/me/gallery/{photo}` | edit `caption` (`present\|nullable\|string\|max:500`); 403 unless the row is the caller's |
| PUT | `/me/gallery/order` | `{ids: [...]}` → writes `sort_order`; returns the caller's FULL ordered gallery |
| PUT | `/events/{event}/photos/order` | same shape; guarded by `EventPhotoController::canManageEvent()` (creator, or `can_manage` on the event's community) |

Both reorder endpoints run through `App\Services\PhotoOrderingService::resolve()`, which
holds the rule in one place: **ids the caller does not own are ignored and never
written**, and **owned ids omitted from the request keep their relative order after the
supplied ones**, so a partial or hostile list can neither hide a photo nor touch another
profile's row. `profile_gallery_photos.caption` and `.sort_order` had existed since the
table was created with no endpoint writing either; `event_photos.sort_order` was only
ever set at insert.

### 17.2 The two past-event stores, merged

`ProfileService::buildCommunityPastEvents()` now returns the union of:

- `pastEventsFromKolabs()` — `kolabs.past_events` JSON on the caller's published/closed
  Kolabs (unchanged source, unchanged keys);
- `pastEventsFromEvents()` — `events` rows where `profile_id = <profile>` and
  `event_date < today`, with `photos` eager-loaded in `sort_order`.

Item shape (every pre-existing key preserved; the rest are **additive**):

```
source          'kolab' | 'event'
source_kolab_id string|null      (null for event-sourced)
source_event_id string|null      (NEW; null for kolab-sourced)
name            string|null
date            string|null
partner_name    string|null
attendee_count  int|null         (NEW; only ever set for event-sourced)
media           array
```

Ordering: `date` descending, **undated entries last** so a malformed Kolab entry cannot
take the top slot. Dedup: same case-insensitive `name` + same `date` collapses to one,
keeping the **event-sourced** copy. `community_public_stats.past_events_count` follows
the merged list, and `buildCommunityPhotos()` picks the newly surfaced media up with no
change.

Cost: **two queries regardless of event count** (the rows + the eager-loaded photos),
locked by a query-count assertion in `PastEventsMergeTest`.

### 17.3 The light public profile

`GET /profiles/{profile}` (`PublicProfileResource`) gains `gallery`, `past_events` and
`past_events_count`.

Hydration goes through the **narrower** `ProfileService::hydratePublicPortfolio()`, not
`getPublicProfileDetail()`. Two reasons, both load-bearing:

- `getPublicProfileDetail()` also runs collaboration and kolab count queries for
  `public_stats`, which this endpoint neither emits nor should pay for — reusing it
  tripped `ProfileReputationCacheTest`'s "completed_kolabs_count computed once" guard.
- `getPublicProfileDetail()` **throws `ModelNotFoundException` for attendees**, so
  calling it unguarded would have turned every attendee profile into a 404.

`hydratePublicPortfolio()` is a no-op for attendees, and `portfolioFields()` emits
nothing unless the hydrator ran — an attendee's payload is byte-identical to before.

`PublicProfileResource` is instantiated in exactly one place, for a single profile,
never in a collection, so there is no list-payload cost.

### 17.5 Phone preview (BE-NF-37, added 2026-08-20)

`resources/views/webapp/partials/phone-preview.blade.php` renders a phone frame beside
every Profile-section tab containing a **read-only replica** of the mobile app's public
profile screen, driven by the same `GET /profiles/{me}/public-profile` payload. Alpine
state (`kbPhonePreview()` in the webapp layout) exposes `refreshPreview()`, which every
gallery and past-event mutation calls on success so the frame is never stale.

**It mirrors these files and must change with them:**

- `kolabing-app/lib/features/profile/screens/public_profile_screen.dart`
- `kolabing-app/lib/widgets/gallery/public_gallery_section.dart`
- `kolabing-app/lib/features/event/widgets/past_events_section.dart`

This is a second rendering of the profile UI, which §17.3's web Preview deliberately
avoids by rendering the real page. It is unavoidable here — the target is a Flutter
screen and cannot be embedded — so the cost is bounded instead: read-only (no lightbox,
no pagination, no tap targets), confined to one file, gated to `xl` and up, and not
shown for attendees (their app layout is the member social hub, a different screen).

No API, schema, role or gate change.

### 17.4 Caps (unchanged, enforced by the existing endpoints)

Gallery: 20 photos, 5 per request. Event photos: 20 per event, 5 per request. A past
event created via `POST /events` requires 1–5 photo **files**. The web uploader chunks
larger selections into sequential 5-file requests rather than truncating them.
