# Kolabing — Roles → Backend → Database Map (Ground-Truth)

> Note: `kolabing-v2/CLAUDE.md` references `docs/BACKEND-SCHEMA.md` as a
> MUST-READ schema doc; that file does not currently exist in this repo.
> This file (`ROLES-BACKEND-DB-MAP.md`) is the closest existing
> schema/reference doc and is kept up to date for role-affecting tables.
> Restoring/creating `BACKEND-SCHEMA.md` is tracked as backlog, not part of
> this PR.

**Last updated: 2026-08-25 (§24 — the community dashboard payload (BE-NF-46 / BE-FX-29): `getCommunityDashboard()` returned 3 keys to `getBusinessDashboard()`'s 7. `getReceivedApplicationStats()` and `getOpportunityStats()` were always role-neutral — both scope by `creator_profile_id` — they were simply never called for a community, and the Flutter client had been parsing `applications_received` into zeros the whole time. Adds a community-shaped `next_action` chain whose first step is *apply*. Additive only: no key renamed, no key removed, no schema change. Prior: 2026-08-22 (§21 — Explore as a date-grouped agenda plus the Kolab drawer: one shared definition of a bookable date, the two payload shapes it accepts, and the removal of the Events nav entry (routes and the check-in door untouched). Kolabs on the open web landed alongside it as §20 (master claimed §19 while this branch was open). Also renamed the duplicate second "§17" to §17b so existing §18/§19 references stay true. Prior: 2026-08-22 (§19 — Kolabs on the open web: one gate shared by list/detail/sitemap, the poster-identity asymmetry, and the requireAuth `?next=` repair. Prior: 2026-08-22 (**The attendee's web surface — new §18.** No new endpoint and no schema change; the section exists to record where the rules already lived so the next change does not reimplement them. `kolabing.com/events` + `/events/{slug}` are public and indexable, gated on `EventVisibility::Public` — enforced in the resolver as well as the listing, because not-listed and not-readable are different properties and only the second stops a private community's calendar being read by guessing a slug. Listing delegates to `EventDiscoveryService` rather than re-querying, which is what keeps the **effective city** rule (events.city_id else the host community's) from being reimplemented and silently dropping events that inherit their city. RSVP hands off to the panel with `?rsvp=1` because the public origin cannot read the bearer token; eligibility itself was already correct and untouched. Attendee registration on the web is two calls (register, then onboarding for name + handle). Prior: 2026-08-22 (**Two clients, one door — new §17.8.** Mobile is getting the same check-in surface, so the sync rules are now written down and enforced. Fixed a footgun that was live for a day: `generate-qr` always minted a fresh token, so a host opening the door on a second device silently killed the QR still on the first — opening is now idempotent and rotation is explicit (`rotate: true`), with the web panel confirming first. Added `AttendeeCheckedIn` broadcasting `checkin.recorded` on `private-event.{id}.door` (arrival **plus** running total, so a dropped message cannot desync a count), authorised by the new `Event::isHostedBy()` — one predicate now behind the host-only payload, the door-opening 403 and the channel, where the ownership check had been written out three times. Added Universal Links / App Links files on the app host so one QR reaches the app when installed and the browser when not; both 404 until the mobile identifiers are set, because Apple caches the association file. Open decision recorded: offline check-in needs a client-supplied `checked_in_at` or the timestamp stops meaning what it says. Prior: 2026-08-21 (**Event check-in — new §17.** The door is now reachable: the check-in API had thirteen endpoints and no client (mobile only, and neither app is published), and the one place that built a QR URL pointed at a route that does not exist, in GET form for a POST endpoint, with the secret in a query string — a scanning phone got a 404 (BE-FX-20). Panel pages added for events, the door and the attendee scan; `App\Support\CheckinLink` is now the only thing that builds a check-in URL. Security: `checkin_token` gained an expiry tied to the event window (a permanent token could manufacture attendance months later, which is the one claim this data has to support), a typable `checkin_code` twin, and the whole `checkin` block on `EventResource` is host-only because holding the code *is* the permission to be counted present. QR is encoded in-process (`App\Support\QrCode`, no dependency) and verified against fixtures an independent decoder read back. Prior: 2026-08-20 (**Invite page moved to the app host** — §12.6. It had never worked: the marketing layout loads no Alpine and the CSP grants 'unsafe-eval' only to the app host, so the CTA rendered empty. Moving it was preferred to weakening a security header. `invite_base_url` now defaults to https://app.kolabing.com/c, with a 301 from the old path. The page also gained Google sign-up + a name/phone/photo form using only existing endpoints. Prior: 2026-08-20 (**Phone preview — new §17.5.** Web-app only: a read-only replica of the app's public profile screen beside every Profile-section tab, refreshed after each edit. It mirrors three named Dart files in kolabing-app and must move with them. No API, schema, role or gate change. Prior: **Last updated:** 2026-08-20 (**Public portfolio — new §17.** Three new endpoints (gallery caption + gallery order + event photo order) sharing one `PhotoOrderingService` rule; `buildCommunityPastEvents()` merges the `events` table with `kolabs.past_events` (additive `source`/`source_event_id`/`attendee_count`, newest-first, undated last, deduped on name+date, two queries at any size); `GET /profiles/{id}` gains the portfolio via the narrower `hydratePublicPortfolio()` — the full detail hydration would have added count queries this endpoint does not emit and would have 404'd every attendee profile. No new table, no migration. Prior: 2026-08-19 (**Public-profile indexation bar revised — §16.6b.** The sitemap bar moved from "has a completed collaboration" to "has a review or three photos", because the first bar published a seeded test account and would have published hundreds of empty near-duplicate profiles; the same predicate now drives `noindex` on the page, and `/blog` + `/communities` use the same empty-hub rule through the layout's new `noindex` prop. Added `Profile::receivedReviews()`. Part of the SEO audit remediation, BACKLOG BE-FX-19. Prior same day: (**Public profile pages — new §16.** No schema change. One additive endpoint `GET /profiles/{id}/public-profile` serves the rich profile (gallery, aggregated photos, past events, past collaborations, stats) for **either** role: `past_events` lives on `kolabs.past_events` and was always written by whoever creates the Kolab, so only the community-scoped route's `isCommunity()` guard made it look community-only — that route is unchanged and still 404s for a business (pinned by a test). `buildCommunityPhotos()` stopped assuming `communityProfile`. Three additive fields for linking: `public_url` on the rich resource, `actor_profile_id` on notifications, `id` on chat participants. The public teaser at `kolabing.com/p/{slug}` reads models directly rather than opening any unauthenticated API, and the sign-up wall is enforced by never rendering the withheld fields (§16.4, ROLES §4.2). Slugs are `name-<uuid tail>` because `profiles.handle` is attendee-only in practice — 5 of 94 production profiles have one (§16.3). Prior: 2026-08-18 (**Chat on the web panel — new §15.** No endpoint, column, or gate added: the panel is a second client over the existing chat API. Documented what it calls and why, including two things that are easy to get wrong — `GET /chats/{thread}/messages` paginates **oldest-first** (page 1 is the start of the conversation; the newest messages are on `last_page`), and `POST /chats/{thread}/messages` on a **collaboration** thread notifies nobody because `threadRecipientIds()` returns `[]` for that type, so Kolab sends must use `POST /applications/{id}/messages` (§15.4, BE-FX-13). Real-time: `NewChatMessage` + `chat.thread.{id}` channel auth already existed; the panel subscribes with a self-hosted Pusher-protocol client and signs private channels at `POST /broadcasting/auth` (app root, Sanctum, bearer token). `config('webapp.realtime')` exposes only the four public Reverb values — never the app secret — and with the key unset the page polls, so this ships safely ahead of BE-IF-18. `AddSecurityHeaders` adds `wss:` to `connect-src` on the web-app host only. Two further gaps filed: BE-FX-14 (§2.8 lapse re-gate unenforced for chat — doc/code drift) and BE-FX-15 (`GET /me/communities` hides `can_manage` delegates' communities). Prior: 2026-08-17 (**Web payment flow — new §14.** No gate changed. `POST /me/subscription/checkout/confirm` added: the return-from-Stripe page activates the subscription synchronously (ownership-checked against `client_reference_id`, 403 otherwise; 409 `pending` while unpaid; same idempotent `activateFromStripeSession` the webhook runs — which now returns the upserted row). Checkout Session gains `customer` **or** `customer_email` (never both — duplicate customers orphan the Billing Portal), `allow_promotion_codes` (buyer discount — NOT the referrer-rewarding referral code), and `locale` (`ca → es`, Stripe has no Catalan); payload building split into `StripeService::checkoutSessionParams()` so it is testable without the SDK. `UserResource` gains additive business-only `subscription_status` + `subscription_cancel_at_period_end` (warning copy only — `has_active_subscription` is still the only field the paywall reads; `past_due` is NOT active). Paywall redirects carry `?reason=…` (§3). Public `/pricing` + `/es/pricing` added. Prior: Web app redesign wiring: documented the **paywall HTTP-status asymmetry** — apply/publish surface as 402 but **accept surfaces as 403** via `ApplicationPolicy::accept()`, so clients must treat `403 + business-without-sub` as the paywall too; also documented the `scheduled_date` recurring-day/window rule on accept — §3. No role rule changed. Prior: 2026-08-15 Stripe web checkout now implements `source=stripe` — `POST /me/subscription/checkout` + public signature-verified `/webhooks/stripe`, business-only, server-side price ids, return-URL allowlist, referral-on-first-paid; the `source`-invariant in §9 still holds — see §9. Prior: 2026-07-28 Explore browse feed now hides date-exhausted kolabs — `KolabService::browse()` applies `Kolab::scopeWithSelectableDates()` (serves `/kolabs` + `/opportunities` shim), mirroring the apply-time guard; `?saved=1` left unfiltered — §4. Prior: 2026-07-15 admin company/legal settings: single-row `company_settings` (name/address/NIF/refund/emails + `terms_version`/`terms_effective_date`), maintainer CRUD at `/admin/company-settings`, `CompanySettingService::termsVersion()` is now the consent-version source (config = fallback), a view composer injects the values into the four legal pages — §0 item 12. Prior: legal consent gate: `accepted_terms` (`required|accepted`) on all `register/*` endpoints, OAuth signups stamped in `AuthService::consentStamp()`, consent on `profiles.terms_accepted_at`/`terms_version` vs `config('legal.terms_version')`, `GET /auth/me` `terms` block, `POST /me/consent` (`ConsentController`), `Profile::needsTermsAcceptance()` — §0 item 12. Prior: profile reputation cache (#76): `getReputationSummary()` cached per profile with observer-based invalidation (`CollaborationReviewObserver` + `CollaborationObserver`) and the duplicate `completed_kolabs_count` COUNT removed — §13. Prior: DB scalability indexes (#72): 37 previously-unindexed FKs + hot-path composite/partial indexes added; `ProfileService::getReputationSummary()` window function gained a deterministic `id ASC` tiebreaker so the per-pair cap is stable across index/scan order — §13. Prior: PR 5: reputation shape — `unique_partner_count` removed from public reputation block; per-pair fairness cap added (max 2 reviews per reviewer→reviewed pair via SQL window function, no schema change); `recent_reviews` items serialise `is_verified_kolab_review: true` — §13. Prior: PR 4: public reputation summary — `collaboration_reviews` schema expansion with five category star ratings + `public_comment` + `public_comment_visible` gate, `ProfileService::getReputationSummary()` aggregation, and new `reputation` block on `PublicProfileResource` — §13. Prior: 2026-06-28 gamification mission system v1 curation: `challenges.app_visible` column + the three event/general mission filter sites — #49. Prior same day: #61 Saved Kolabs — new `saved_kolabs` pivot + save/unsave endpoints + `?saved=1` list + viewer-scoped `is_saved` flag — §7, §7.1. PR #59 review fixes: completion-confirmation gate hardening — terminal-state guard, `pending = not-yes` resource/gate alignment, auto-complete grace anchored on `updated_at`, `Collaboration::roleFor()`, **legacy feedback fallback + backfill removed (`/complete` now gates purely on real completion confirmations)**, dead-code removal — §0 item 10, §3, §8, §10. Prior: 2026-06-26 PR 1 moved the `/complete` gate to `collaboration_completions`) Prior: ** 2026-08-19 (**Community members web panel — new §12.6.** New table `community_invitations` + its endpoints, `GET /communities/{c}/stats`, member-detail and bulk-update endpoints, and the public `/c/{slug}` join route. `GET /communities/{c}/members` gains filters/sorts and per-row engagement metrics (resolved with left-joined grouped aggregates — no N+1, locked by a query-count test), is **now manage-gated**, and **defaults to excluding `status = removed`**. `AuthService::startOnboardingDrip` renamed to `afterRegistration` and now also claims pending invitations for the new profile's email (guarded). `CommunityMemberResource` fixed: it read the extended profile for a display name, but `attendee_profiles` has no `name` column, so every community member rendered as their email prefix — `profiles.name` is read first now. No gate changed; §12.5 is now enforced by `CommunityNeverPaywalledTest`. Prior: 2026-08-19 (**Public profile pages — new §16.** No schema change. One additive endpoint `GET /profiles/{id}/public-profile` serves the rich profile (gallery, aggregated photos, past events, past collaborations, stats) for **either** role: `past_events` lives on `kolabs.past_events` and was always written by whoever creates the Kolab, so only the community-scoped route's `isCommunity()` guard made it look community-only — that route is unchanged and still 404s for a business (pinned by a test). `buildCommunityPhotos()` stopped assuming `communityProfile`. Three additive fields for linking: `public_url` on the rich resource, `actor_profile_id` on notifications, `id` on chat participants. The public teaser at `kolabing.com/p/{slug}` reads models directly rather than opening any unauthenticated API, and the sign-up wall is enforced by never rendering the withheld fields (§16.4, ROLES §4.2). Slugs are `name-<uuid tail>` because `profiles.handle` is attendee-only in practice — 5 of 94 production profiles have one (§16.3). Prior: 2026-08-18 (**Chat on the web panel — new §15.** No endpoint, column, or gate added: the panel is a second client over the existing chat API. Documented what it calls and why, including two things that are easy to get wrong — `GET /chats/{thread}/messages` paginates **oldest-first** (page 1 is the start of the conversation; the newest messages are on `last_page`), and `POST /chats/{thread}/messages` on a **collaboration** thread notifies nobody because `threadRecipientIds()` returns `[]` for that type, so Kolab sends must use `POST /applications/{id}/messages` (§15.4, BE-FX-13). Real-time: `NewChatMessage` + `chat.thread.{id}` channel auth already existed; the panel subscribes with a self-hosted Pusher-protocol client and signs private channels at `POST /broadcasting/auth` (app root, Sanctum, bearer token). `config('webapp.realtime')` exposes only the four public Reverb values — never the app secret — and with the key unset the page polls, so this ships safely ahead of BE-IF-18. `AddSecurityHeaders` adds `wss:` to `connect-src` on the web-app host only. Two further gaps filed: BE-FX-14 (§2.8 lapse re-gate unenforced for chat — doc/code drift) and BE-FX-15 (`GET /me/communities` hides `can_manage` delegates' communities). Prior: 2026-08-17 (**Web payment flow — new §14.** No gate changed. `POST /me/subscription/checkout/confirm` added: the return-from-Stripe page activates the subscription synchronously (ownership-checked against `client_reference_id`, 403 otherwise; 409 `pending` while unpaid; same idempotent `activateFromStripeSession` the webhook runs — which now returns the upserted row). Checkout Session gains `customer` **or** `customer_email` (never both — duplicate customers orphan the Billing Portal), `allow_promotion_codes` (buyer discount — NOT the referrer-rewarding referral code), and `locale` (`ca → es`, Stripe has no Catalan); payload building split into `StripeService::checkoutSessionParams()` so it is testable without the SDK. `UserResource` gains additive business-only `subscription_status` + `subscription_cancel_at_period_end` (warning copy only — `has_active_subscription` is still the only field the paywall reads; `past_due` is NOT active). Paywall redirects carry `?reason=…` (§3). Public `/pricing` + `/es/pricing` added. Prior: Web app redesign wiring: documented the **paywall HTTP-status asymmetry** — apply/publish surface as 402 but **accept surfaces as 403** via `ApplicationPolicy::accept()`, so clients must treat `403 + business-without-sub` as the paywall too; also documented the `scheduled_date` recurring-day/window rule on accept — §3. No role rule changed. Prior: 2026-08-15 Stripe web checkout now implements `source=stripe` — `POST /me/subscription/checkout` + public signature-verified `/webhooks/stripe`, business-only, server-side price ids, return-URL allowlist, referral-on-first-paid; the `source`-invariant in §9 still holds — see §9. Prior: 2026-07-28 Explore browse feed now hides date-exhausted kolabs — `KolabService::browse()` applies `Kolab::scopeWithSelectableDates()` (serves `/kolabs` + `/opportunities` shim), mirroring the apply-time guard; `?saved=1` left unfiltered — §4. Prior: 2026-07-15 admin company/legal settings: single-row `company_settings` (name/address/NIF/refund/emails + `terms_version`/`terms_effective_date`), maintainer CRUD at `/admin/company-settings`, `CompanySettingService::termsVersion()` is now the consent-version source (config = fallback), a view composer injects the values into the four legal pages — §0 item 12. Prior: legal consent gate: `accepted_terms` (`required|accepted`) on all `register/*` endpoints, OAuth signups stamped in `AuthService::consentStamp()`, consent on `profiles.terms_accepted_at`/`terms_version` vs `config('legal.terms_version')`, `GET /auth/me` `terms` block, `POST /me/consent` (`ConsentController`), `Profile::needsTermsAcceptance()` — §0 item 12. Prior: profile reputation cache (#76): `getReputationSummary()` cached per profile with observer-based invalidation (`CollaborationReviewObserver` + `CollaborationObserver`) and the duplicate `completed_kolabs_count` COUNT removed — §13. Prior: DB scalability indexes (#72): 37 previously-unindexed FKs + hot-path composite/partial indexes added; `ProfileService::getReputationSummary()` window function gained a deterministic `id ASC` tiebreaker so the per-pair cap is stable across index/scan order — §13. Prior: PR 5: reputation shape — `unique_partner_count` removed from public reputation block; per-pair fairness cap added (max 2 reviews per reviewer→reviewed pair via SQL window function, no schema change); `recent_reviews` items serialise `is_verified_kolab_review: true` — §13. Prior: PR 4: public reputation summary — `collaboration_reviews` schema expansion with five category star ratings + `public_comment` + `public_comment_visible` gate, `ProfileService::getReputationSummary()` aggregation, and new `reputation` block on `PublicProfileResource` — §13. Prior: 2026-06-28 gamification mission system v1 curation: `challenges.app_visible` column + the three event/general mission filter sites — #49. Prior same day: #61 Saved Kolabs — new `saved_kolabs` pivot + save/unsave endpoints + `?saved=1` list + viewer-scoped `is_saved` flag — §7, §7.1. PR #59 review fixes: completion-confirmation gate hardening — terminal-state guard, `pending = not-yes` resource/gate alignment, auto-complete grace anchored on `updated_at`, `Collaboration::roleFor()`, **legacy feedback fallback + backfill removed (`/complete` now gates purely on real completion confirmations)**, dead-code removal — §0 item 10, §3, §8, §10. Prior: **Last updated:** 2026-08-22 (**The attendee's web surface — new §18.** No new endpoint and no schema change; the section exists to record where the rules already lived so the next change does not reimplement them. `kolabing.com/events` + `/events/{slug}` are public and indexable, gated on `EventVisibility::Public` — enforced in the resolver as well as the listing, because not-listed and not-readable are different properties and only the second stops a private community's calendar being read by guessing a slug. Listing delegates to `EventDiscoveryService` rather than re-querying, which is what keeps the **effective city** rule (events.city_id else the host community's) from being reimplemented and silently dropping events that inherit their city. RSVP hands off to the panel with `?rsvp=1` because the public origin cannot read the bearer token; eligibility itself was already correct and untouched. Attendee registration on the web is two calls (register, then onboarding for name + handle). Prior: 2026-08-22 (**Two clients, one door — new §17.8.** Mobile is getting the same check-in surface, so the sync rules are now written down and enforced. Fixed a footgun that was live for a day: `generate-qr` always minted a fresh token, so a host opening the door on a second device silently killed the QR still on the first — opening is now idempotent and rotation is explicit (`rotate: true`), with the web panel confirming first. Added `AttendeeCheckedIn` broadcasting `checkin.recorded` on `private-event.{id}.door` (arrival **plus** running total, so a dropped message cannot desync a count), authorised by the new `Event::isHostedBy()` — one predicate now behind the host-only payload, the door-opening 403 and the channel, where the ownership check had been written out three times. Added Universal Links / App Links files on the app host so one QR reaches the app when installed and the browser when not; both 404 until the mobile identifiers are set, because Apple caches the association file. Open decision recorded: offline check-in needs a client-supplied `checked_in_at` or the timestamp stops meaning what it says. Prior: 2026-08-21 (**Event check-in — new §17.** The door is now reachable: the check-in API had thirteen endpoints and no client (mobile only, and neither app is published), and the one place that built a QR URL pointed at a route that does not exist, in GET form for a POST endpoint, with the secret in a query string — a scanning phone got a 404 (BE-FX-20). Panel pages added for events, the door and the attendee scan; `App\Support\CheckinLink` is now the only thing that builds a check-in URL. Security: `checkin_token` gained an expiry tied to the event window (a permanent token could manufacture attendance months later, which is the one claim this data has to support), a typable `checkin_code` twin, and the whole `checkin` block on `EventResource` is host-only because holding the code *is* the permission to be counted present. QR is encoded in-process (`App\Support\QrCode`, no dependency) and verified against fixtures an independent decoder read back. Prior: 2026-08-20 (**Invite page moved to the app host** — §12.6. It had never worked: the marketing layout loads no Alpine and the CSP grants 'unsafe-eval' only to the app host, so the CTA rendered empty. Moving it was preferred to weakening a security header. `invite_base_url` now defaults to https://app.kolabing.com/c, with a 301 from the old path. The page also gained Google sign-up + a name/phone/photo form using only existing endpoints. Prior: 2026-08-20 (**Phone preview — new §17.5.** Web-app only: a read-only replica of the app's public profile screen beside every Profile-section tab, refreshed after each edit. It mirrors three named Dart files in kolabing-app and must move with them. No API, schema, role or gate change. Prior: **Last updated:** 2026-08-20 (**Public portfolio — new §17.** Three new endpoints (gallery caption + gallery order + event photo order) sharing one `PhotoOrderingService` rule; `buildCommunityPastEvents()` merges the `events` table with `kolabs.past_events` (additive `source`/`source_event_id`/`attendee_count`, newest-first, undated last, deduped on name+date, two queries at any size); `GET /profiles/{id}` gains the portfolio via the narrower `hydratePublicPortfolio()` — the full detail hydration would have added count queries this endpoint does not emit and would have 404'd every attendee profile. No new table, no migration. Prior: 2026-08-19 (**Public-profile indexation bar revised — §16.6b.** The sitemap bar moved from "has a completed collaboration" to "has a review or three photos", because the first bar published a seeded test account and would have published hundreds of empty near-duplicate profiles; the same predicate now drives `noindex` on the page, and `/blog` + `/communities` use the same empty-hub rule through the layout's new `noindex` prop. Added `Profile::receivedReviews()`. Part of the SEO audit remediation, BACKLOG BE-FX-19. Prior same day: (**Public profile pages — new §16.** No schema change. One additive endpoint `GET /profiles/{id}/public-profile` serves the rich profile (gallery, aggregated photos, past events, past collaborations, stats) for **either** role: `past_events` lives on `kolabs.past_events` and was always written by whoever creates the Kolab, so only the community-scoped route's `isCommunity()` guard made it look community-only — that route is unchanged and still 404s for a business (pinned by a test). `buildCommunityPhotos()` stopped assuming `communityProfile`. Three additive fields for linking: `public_url` on the rich resource, `actor_profile_id` on notifications, `id` on chat participants. The public teaser at `kolabing.com/p/{slug}` reads models directly rather than opening any unauthenticated API, and the sign-up wall is enforced by never rendering the withheld fields (§16.4, ROLES §4.2). Slugs are `name-<uuid tail>` because `profiles.handle` is attendee-only in practice — 5 of 94 production profiles have one (§16.3). Prior: 2026-08-18 (**Chat on the web panel — new §15.** No endpoint, column, or gate added: the panel is a second client over the existing chat API. Documented what it calls and why, including two things that are easy to get wrong — `GET /chats/{thread}/messages` paginates **oldest-first** (page 1 is the start of the conversation; the newest messages are on `last_page`), and `POST /chats/{thread}/messages` on a **collaboration** thread notifies nobody because `threadRecipientIds()` returns `[]` for that type, so Kolab sends must use `POST /applications/{id}/messages` (§15.4, BE-FX-13). Real-time: `NewChatMessage` + `chat.thread.{id}` channel auth already existed; the panel subscribes with a self-hosted Pusher-protocol client and signs private channels at `POST /broadcasting/auth` (app root, Sanctum, bearer token). `config('webapp.realtime')` exposes only the four public Reverb values — never the app secret — and with the key unset the page polls, so this ships safely ahead of BE-IF-18. `AddSecurityHeaders` adds `wss:` to `connect-src` on the web-app host only. Two further gaps filed: BE-FX-14 (§2.8 lapse re-gate unenforced for chat — doc/code drift) and BE-FX-15 (`GET /me/communities` hides `can_manage` delegates' communities). Prior: 2026-08-17 (**Web payment flow — new §14.** No gate changed. `POST /me/subscription/checkout/confirm` added: the return-from-Stripe page activates the subscription synchronously (ownership-checked against `client_reference_id`, 403 otherwise; 409 `pending` while unpaid; same idempotent `activateFromStripeSession` the webhook runs — which now returns the upserted row). Checkout Session gains `customer` **or** `customer_email` (never both — duplicate customers orphan the Billing Portal), `allow_promotion_codes` (buyer discount — NOT the referrer-rewarding referral code), and `locale` (`ca → es`, Stripe has no Catalan); payload building split into `StripeService::checkoutSessionParams()` so it is testable without the SDK. `UserResource` gains additive business-only `subscription_status` + `subscription_cancel_at_period_end` (warning copy only — `has_active_subscription` is still the only field the paywall reads; `past_due` is NOT active). Paywall redirects carry `?reason=…` (§3). Public `/pricing` + `/es/pricing` added. Prior: Web app redesign wiring: documented the **paywall HTTP-status asymmetry** — apply/publish surface as 402 but **accept surfaces as 403** via `ApplicationPolicy::accept()`, so clients must treat `403 + business-without-sub` as the paywall too; also documented the `scheduled_date` recurring-day/window rule on accept — §3. No role rule changed. Prior: 2026-08-15 Stripe web checkout now implements `source=stripe` — `POST /me/subscription/checkout` + public signature-verified `/webhooks/stripe`, business-only, server-side price ids, return-URL allowlist, referral-on-first-paid; the `source`-invariant in §9 still holds — see §9. Prior: 2026-07-28 Explore browse feed now hides date-exhausted kolabs — `KolabService::browse()` applies `Kolab::scopeWithSelectableDates()` (serves `/kolabs` + `/opportunities` shim), mirroring the apply-time guard; `?saved=1` left unfiltered — §4. Prior: 2026-07-15 admin company/legal settings: single-row `company_settings` (name/address/NIF/refund/emails + `terms_version`/`terms_effective_date`), maintainer CRUD at `/admin/company-settings`, `CompanySettingService::termsVersion()` is now the consent-version source (config = fallback), a view composer injects the values into the four legal pages — §0 item 12. Prior: legal consent gate: `accepted_terms` (`required|accepted`) on all `register/*` endpoints, OAuth signups stamped in `AuthService::consentStamp()`, consent on `profiles.terms_accepted_at`/`terms_version` vs `config('legal.terms_version')`, `GET /auth/me` `terms` block, `POST /me/consent` (`ConsentController`), `Profile::needsTermsAcceptance()` — §0 item 12. Prior: profile reputation cache (#76): `getReputationSummary()` cached per profile with observer-based invalidation (`CollaborationReviewObserver` + `CollaborationObserver`) and the duplicate `completed_kolabs_count` COUNT removed — §13. Prior: DB scalability indexes (#72): 37 previously-unindexed FKs + hot-path composite/partial indexes added; `ProfileService::getReputationSummary()` window function gained a deterministic `id ASC` tiebreaker so the per-pair cap is stable across index/scan order — §13. Prior: PR 5: reputation shape — `unique_partner_count` removed from public reputation block; per-pair fairness cap added (max 2 reviews per reviewer→reviewed pair via SQL window function, no schema change); `recent_reviews` items serialise `is_verified_kolab_review: true` — §13. Prior: PR 4: public reputation summary — `collaboration_reviews` schema expansion with five category star ratings + `public_comment` + `public_comment_visible` gate, `ProfileService::getReputationSummary()` aggregation, and new `reputation` block on `PublicProfileResource` — §13. Prior: 2026-06-28 gamification mission system v1 curation: `challenges.app_visible` column + the three event/general mission filter sites — #49. Prior same day: #61 Saved Kolabs — new `saved_kolabs` pivot + save/unsave endpoints + `?saved=1` list + viewer-scoped `is_saved` flag — §7, §7.1. PR #59 review fixes: completion-confirmation gate hardening — terminal-state guard, `pending = not-yes` resource/gate alignment, auto-complete grace anchored on `updated_at`, `Collaboration::roleFor()`, **legacy feedback fallback + backfill removed (`/complete` now gates purely on real completion confirmations)**, dead-code removal — §0 item 10, §3, §8, §10. Prior: 2026-06-26 PR 1 moved the `/complete` gate to `collaboration_completions`) Prior: ** 2026-08-19 (**Community members web panel — new §12.6.** New table `community_invitations` + its endpoints, `GET /communities/{c}/stats`, member-detail and bulk-update endpoints, and the public `/c/{slug}` join route. `GET /communities/{c}/members` gains filters/sorts and per-row engagement metrics (resolved with left-joined grouped aggregates — no N+1, locked by a query-count test), is **now manage-gated**, and **defaults to excluding `status = removed`**. `AuthService::startOnboardingDrip` renamed to `afterRegistration` and now also claims pending invitations for the new profile's email (guarded). `CommunityMemberResource` fixed: it read the extended profile for a display name, but `attendee_profiles` has no `name` column, so every community member rendered as their email prefix — `profiles.name` is read first now. No gate changed; §12.5 is now enforced by `CommunityNeverPaywalledTest`. Prior: 2026-08-19 (**Public profile pages — new §16.** No schema change. One additive endpoint `GET /profiles/{id}/public-profile` serves the rich profile (gallery, aggregated photos, past events, past collaborations, stats) for **either** role: `past_events` lives on `kolabs.past_events` and was always written by whoever creates the Kolab, so only the community-scoped route's `isCommunity()` guard made it look community-only — that route is unchanged and still 404s for a business (pinned by a test). `buildCommunityPhotos()` stopped assuming `communityProfile`. Three additive fields for linking: `public_url` on the rich resource, `actor_profile_id` on notifications, `id` on chat participants. The public teaser at `kolabing.com/p/{slug}` reads models directly rather than opening any unauthenticated API, and the sign-up wall is enforced by never rendering the withheld fields (§16.4, ROLES §4.2). Slugs are `name-<uuid tail>` because `profiles.handle` is attendee-only in practice — 5 of 94 production profiles have one (§16.3). Prior: 2026-08-18 (**Chat on the web panel — new §15.** No endpoint, column, or gate added: the panel is a second client over the existing chat API. Documented what it calls and why, including two things that are easy to get wrong — `GET /chats/{thread}/messages` paginates **oldest-first** (page 1 is the start of the conversation; the newest messages are on `last_page`), and `POST /chats/{thread}/messages` on a **collaboration** thread notifies nobody because `threadRecipientIds()` returns `[]` for that type, so Kolab sends must use `POST /applications/{id}/messages` (§15.4, BE-FX-13). Real-time: `NewChatMessage` + `chat.thread.{id}` channel auth already existed; the panel subscribes with a self-hosted Pusher-protocol client and signs private channels at `POST /broadcasting/auth` (app root, Sanctum, bearer token). `config('webapp.realtime')` exposes only the four public Reverb values — never the app secret — and with the key unset the page polls, so this ships safely ahead of BE-IF-18. `AddSecurityHeaders` adds `wss:` to `connect-src` on the web-app host only. Two further gaps filed: BE-FX-14 (§2.8 lapse re-gate unenforced for chat — doc/code drift) and BE-FX-15 (`GET /me/communities` hides `can_manage` delegates' communities). Prior: 2026-08-17 (**Web payment flow — new §14.** No gate changed. `POST /me/subscription/checkout/confirm` added: the return-from-Stripe page activates the subscription synchronously (ownership-checked against `client_reference_id`, 403 otherwise; 409 `pending` while unpaid; same idempotent `activateFromStripeSession` the webhook runs — which now returns the upserted row). Checkout Session gains `customer` **or** `customer_email` (never both — duplicate customers orphan the Billing Portal), `allow_promotion_codes` (buyer discount — NOT the referrer-rewarding referral code), and `locale` (`ca → es`, Stripe has no Catalan); payload building split into `StripeService::checkoutSessionParams()` so it is testable without the SDK. `UserResource` gains additive business-only `subscription_status` + `subscription_cancel_at_period_end` (warning copy only — `has_active_subscription` is still the only field the paywall reads; `past_due` is NOT active). Paywall redirects carry `?reason=…` (§3). Public `/pricing` + `/es/pricing` added. Prior: Web app redesign wiring: documented the **paywall HTTP-status asymmetry** — apply/publish surface as 402 but **accept surfaces as 403** via `ApplicationPolicy::accept()`, so clients must treat `403 + business-without-sub` as the paywall too; also documented the `scheduled_date` recurring-day/window rule on accept — §3. No role rule changed. Prior: 2026-08-15 Stripe web checkout now implements `source=stripe` — `POST /me/subscription/checkout` + public signature-verified `/webhooks/stripe`, business-only, server-side price ids, return-URL allowlist, referral-on-first-paid; the `source`-invariant in §9 still holds — see §9. Prior: 2026-07-28 Explore browse feed now hides date-exhausted kolabs — `KolabService::browse()` applies `Kolab::scopeWithSelectableDates()` (serves `/kolabs` + `/opportunities` shim), mirroring the apply-time guard; `?saved=1` left unfiltered — §4. Prior: 2026-07-15 admin company/legal settings: single-row `company_settings` (name/address/NIF/refund/emails + `terms_version`/`terms_effective_date`), maintainer CRUD at `/admin/company-settings`, `CompanySettingService::termsVersion()` is now the consent-version source (config = fallback), a view composer injects the values into the four legal pages — §0 item 12. Prior: legal consent gate: `accepted_terms` (`required|accepted`) on all `register/*` endpoints, OAuth signups stamped in `AuthService::consentStamp()`, consent on `profiles.terms_accepted_at`/`terms_version` vs `config('legal.terms_version')`, `GET /auth/me` `terms` block, `POST /me/consent` (`ConsentController`), `Profile::needsTermsAcceptance()` — §0 item 12. Prior: profile reputation cache (#76): `getReputationSummary()` cached per profile with observer-based invalidation (`CollaborationReviewObserver` + `CollaborationObserver`) and the duplicate `completed_kolabs_count` COUNT removed — §13. Prior: DB scalability indexes (#72): 37 previously-unindexed FKs + hot-path composite/partial indexes added; `ProfileService::getReputationSummary()` window function gained a deterministic `id ASC` tiebreaker so the per-pair cap is stable across index/scan order — §13. Prior: PR 5: reputation shape — `unique_partner_count` removed from public reputation block; per-pair fairness cap added (max 2 reviews per reviewer→reviewed pair via SQL window function, no schema change); `recent_reviews` items serialise `is_verified_kolab_review: true` — §13. Prior: PR 4: public reputation summary — `collaboration_reviews` schema expansion with five category star ratings + `public_comment` + `public_comment_visible` gate, `ProfileService::getReputationSummary()` aggregation, and new `reputation` block on `PublicProfileResource` — §13. Prior: 2026-06-28 gamification mission system v1 curation: `challenges.app_visible` column + the three event/general mission filter sites — #49. Prior same day: #61 Saved Kolabs — new `saved_kolabs` pivot + save/unsave endpoints + `?saved=1` list + viewer-scoped `is_saved` flag — §7, §7.1. PR #59 review fixes: completion-confirmation gate hardening — terminal-state guard, `pending = not-yes` resource/gate alignment, auto-complete grace anchored on `updated_at`, `Collaboration::roleFor()`, **legacy feedback fallback + backfill removed (`/complete` now gates purely on real completion confirmations)**, dead-code removal — §0 item 10, §3, §8, §10. Prior: 2026-06-26 PR 1 moved the `/complete` gate to `collaboration_completions`) — Also 2026-08-22 (**Last updated:** 2026-08-22 (**`GET /me/communities` is the owned + MANAGED set** — new §12.7 (BE-FX-15). A `can_manage` member could not list the community they co-run, so every management action they were authorised for was unreachable: no client could discover the id. New canonical query `Community::scopeManageableBy()`, additive `CommunityResource.my_can_manage`, no N+1, no gate or paywall change. Prior: 2026-08-20 (**Invite page moved to the app host** — §12.6. It had never worked: the marketing layout loads no Alpine and the CSP grants 'unsafe-eval' only to the app host, so the CTA rendered empty. Moving it was preferred to weakening a security header. `invite_base_url` now defaults to https://app.kolabing.com/c, with a 301 from the old path. The page also gained Google sign-up + a name/phone/photo form using only existing endpoints. Prior: 2026-08-20 (**Phone preview — new §17.5.** Web-app only: a read-only replica of the app's public profile screen beside every Profile-section tab, refreshed after each edit. It mirrors three named Dart files in kolabing-app and must move with them. No API, schema, role or gate change. Prior: **Last updated:** 2026-08-20 (**Public portfolio — new §17.** Three new endpoints (gallery caption + gallery order + event photo order) sharing one `PhotoOrderingService` rule; `buildCommunityPastEvents()` merges the `events` table with `kolabs.past_events` (additive `source`/`source_event_id`/`attendee_count`, newest-first, undated last, deduped on name+date, two queries at any size); `GET /profiles/{id}` gains the portfolio via the narrower `hydratePublicPortfolio()` — the full detail hydration would have added count queries this endpoint does not emit and would have 404'd every attendee profile. No new table, no migration. Prior: 2026-08-19 (**Public-profile indexation bar revised — §16.6b.** The sitemap bar moved from "has a completed collaboration" to "has a review or three photos", because the first bar published a seeded test account and would have published hundreds of empty near-duplicate profiles; the same predicate now drives `noindex` on the page, and `/blog` + `/communities` use the same empty-hub rule through the layout's new `noindex` prop. Added `Profile::receivedReviews()`. Part of the SEO audit remediation, BACKLOG BE-FX-19. Prior same day: (**Public profile pages — new §16.** No schema change. One additive endpoint `GET /profiles/{id}/public-profile` serves the rich profile (gallery, aggregated photos, past events, past collaborations, stats) for **either** role: `past_events` lives on `kolabs.past_events` and was always written by whoever creates the Kolab, so only the community-scoped route's `isCommunity()` guard made it look community-only — that route is unchanged and still 404s for a business (pinned by a test). `buildCommunityPhotos()` stopped assuming `communityProfile`. Three additive fields for linking: `public_url` on the rich resource, `actor_profile_id` on notifications, `id` on chat participants. The public teaser at `kolabing.com/p/{slug}` reads models directly rather than opening any unauthenticated API, and the sign-up wall is enforced by never rendering the withheld fields (§16.4, ROLES §4.2). Slugs are `name-<uuid tail>` because `profiles.handle` is attendee-only in practice — 5 of 94 production profiles have one (§16.3). Prior: 2026-08-18 (**Chat on the web panel — new §15.** No endpoint, column, or gate added: the panel is a second client over the existing chat API. Documented what it calls and why, including two things that are easy to get wrong — `GET /chats/{thread}/messages` paginates **oldest-first** (page 1 is the start of the conversation; the newest messages are on `last_page`), and `POST /chats/{thread}/messages` on a **collaboration** thread notifies nobody because `threadRecipientIds()` returns `[]` for that type, so Kolab sends must use `POST /applications/{id}/messages` (§15.4, BE-FX-13). Real-time: `NewChatMessage` + `chat.thread.{id}` channel auth already existed; the panel subscribes with a self-hosted Pusher-protocol client and signs private channels at `POST /broadcasting/auth` (app root, Sanctum, bearer token). `config('webapp.realtime')` exposes only the four public Reverb values — never the app secret — and with the key unset the page polls, so this ships safely ahead of BE-IF-18. `AddSecurityHeaders` adds `wss:` to `connect-src` on the web-app host only. Two further gaps filed: BE-FX-14 (§2.8 lapse re-gate unenforced for chat — doc/code drift) and BE-FX-15 (`GET /me/communities` hides `can_manage` delegates' communities). Prior: 2026-08-17 (**Web payment flow — new §14.** No gate changed. `POST /me/subscription/checkout/confirm` added: the return-from-Stripe page activates the subscription synchronously (ownership-checked against `client_reference_id`, 403 otherwise; 409 `pending` while unpaid; same idempotent `activateFromStripeSession` the webhook runs — which now returns the upserted row). Checkout Session gains `customer` **or** `customer_email` (never both — duplicate customers orphan the Billing Portal), `allow_promotion_codes` (buyer discount — NOT the referrer-rewarding referral code), and `locale` (`ca → es`, Stripe has no Catalan); payload building split into `StripeService::checkoutSessionParams()` so it is testable without the SDK. `UserResource` gains additive business-only `subscription_status` + `subscription_cancel_at_period_end` (warning copy only — `has_active_subscription` is still the only field the paywall reads; `past_due` is NOT active). Paywall redirects carry `?reason=…` (§3). Public `/pricing` + `/es/pricing` added. Prior: Web app redesign wiring: documented the **paywall HTTP-status asymmetry** — apply/publish surface as 402 but **accept surfaces as 403** via `ApplicationPolicy::accept()`, so clients must treat `403 + business-without-sub` as the paywall too; also documented the `scheduled_date` recurring-day/window rule on accept — §3. No role rule changed. Prior: 2026-08-15 Stripe web checkout now implements `source=stripe` — `POST /me/subscription/checkout` + public signature-verified `/webhooks/stripe`, business-only, server-side price ids, return-URL allowlist, referral-on-first-paid; the `source`-invariant in §9 still holds — see §9. Prior: 2026-07-28 Explore browse feed now hides date-exhausted kolabs — `KolabService::browse()` applies `Kolab::scopeWithSelectableDates()` (serves `/kolabs` + `/opportunities` shim), mirroring the apply-time guard; `?saved=1` left unfiltered — §4. Prior: 2026-07-15 admin company/legal settings: single-row `company_settings` (name/address/NIF/refund/emails + `terms_version`/`terms_effective_date`), maintainer CRUD at `/admin/company-settings`, `CompanySettingService::termsVersion()` is now the consent-version source (config = fallback), a view composer injects the values into the four legal pages — §0 item 12. Prior: legal consent gate: `accepted_terms` (`required|accepted`) on all `register/*` endpoints, OAuth signups stamped in `AuthService::consentStamp()`, consent on `profiles.terms_accepted_at`/`terms_version` vs `config('legal.terms_version')`, `GET /auth/me` `terms` block, `POST /me/consent` (`ConsentController`), `Profile::needsTermsAcceptance()` — §0 item 12. Prior: profile reputation cache (#76): `getReputationSummary()` cached per profile with observer-based invalidation (`CollaborationReviewObserver` + `CollaborationObserver`) and the duplicate `completed_kolabs_count` COUNT removed — §13. Prior: DB scalability indexes (#72): 37 previously-unindexed FKs + hot-path composite/partial indexes added; `ProfileService::getReputationSummary()` window function gained a deterministic `id ASC` tiebreaker so the per-pair cap is stable across index/scan order — §13. Prior: PR 5: reputation shape — `unique_partner_count` removed from public reputation block; per-pair fairness cap added (max 2 reviews per reviewer→reviewed pair via SQL window function, no schema change); `recent_reviews` items serialise `is_verified_kolab_review: true` — §13. Prior: PR 4: public reputation summary — `collaboration_reviews` schema expansion with five category star ratings + `public_comment` + `public_comment_visible` gate, `ProfileService::getReputationSummary()` aggregation, and new `reputation` block on `PublicProfileResource` — §13. Prior: 2026-06-28 gamification mission system v1 curation: `challenges.app_visible` column + the three event/general mission filter sites — #49. Prior same day: #61 Saved Kolabs — new `saved_kolabs` pivot + save/unsave endpoints + `?saved=1` list + viewer-scoped `is_saved` flag — §7, §7.1. PR #59 review fixes: completion-confirmation gate hardening — terminal-state guard, `pending = not-yes` resource/gate alignment, auto-complete grace anchored on `updated_at`, `Collaboration::roleFor()`, **legacy feedback fallback + backfill removed (`/complete` now gates purely on real completion confirmations)**, dead-code removal — §0 item 10, §3, §8, §10. Prior: 2026-06-26 PR 1 moved the `/complete` gate to `collaboration_completions`) Prior: ** 2026-08-19 (**Community members web panel — new §12.6.** New table `community_invitations` + its endpoints, `GET /communities/{c}/stats`, member-detail and bulk-update endpoints, and the public `/c/{slug}` join route. `GET /communities/{c}/members` gains filters/sorts and per-row engagement metrics (resolved with left-joined grouped aggregates — no N+1, locked by a query-count test), is **now manage-gated**, and **defaults to excluding `status = removed`**. `AuthService::startOnboardingDrip` renamed to `afterRegistration` and now also claims pending invitations for the new profile's email (guarded). `CommunityMemberResource` fixed: it read the extended profile for a display name, but `attendee_profiles` has no `name` column, so every community member rendered as their email prefix — `profiles.name` is read first now. No gate changed; §12.5 is now enforced by `CommunityNeverPaywalledTest`. Prior: 2026-08-19 (**Public profile pages — new §16.** No schema change. One additive endpoint `GET /profiles/{id}/public-profile` serves the rich profile (gallery, aggregated photos, past events, past collaborations, stats) for **either** role: `past_events` lives on `kolabs.past_events` and was always written by whoever creates the Kolab, so only the community-scoped route's `isCommunity()` guard made it look community-only — that route is unchanged and still 404s for a business (pinned by a test). `buildCommunityPhotos()` stopped assuming `communityProfile`. Three additive fields for linking: `public_url` on the rich resource, `actor_profile_id` on notifications, `id` on chat participants. The public teaser at `kolabing.com/p/{slug}` reads models directly rather than opening any unauthenticated API, and the sign-up wall is enforced by never rendering the withheld fields (§16.4, ROLES §4.2). Slugs are `name-<uuid tail>` because `profiles.handle` is attendee-only in practice — 5 of 94 production profiles have one (§16.3). Prior: 2026-08-18 (**Chat on the web panel — new §15.** No endpoint, column, or gate added: the panel is a second client over the existing chat API. Documented what it calls and why, including two things that are easy to get wrong — `GET /chats/{thread}/messages` paginates **oldest-first** (page 1 is the start of the conversation; the newest messages are on `last_page`), and `POST /chats/{thread}/messages` on a **collaboration** thread notifies nobody because `threadRecipientIds()` returns `[]` for that type, so Kolab sends must use `POST /applications/{id}/messages` (§15.4, BE-FX-13). Real-time: `NewChatMessage` + `chat.thread.{id}` channel auth already existed; the panel subscribes with a self-hosted Pusher-protocol client and signs private channels at `POST /broadcasting/auth` (app root, Sanctum, bearer token). `config('webapp.realtime')` exposes only the four public Reverb values — never the app secret — and with the key unset the page polls, so this ships safely ahead of BE-IF-18. `AddSecurityHeaders` adds `wss:` to `connect-src` on the web-app host only. Two further gaps filed: BE-FX-14 (§2.8 lapse re-gate unenforced for chat — doc/code drift) and BE-FX-15 (`GET /me/communities` hides `can_manage` delegates' communities). Prior: 2026-08-17 (**Web payment flow — new §14.** No gate changed. `POST /me/subscription/checkout/confirm` added: the return-from-Stripe page activates the subscription synchronously (ownership-checked against `client_reference_id`, 403 otherwise; 409 `pending` while unpaid; same idempotent `activateFromStripeSession` the webhook runs — which now returns the upserted row). Checkout Session gains `customer` **or** `customer_email` (never both — duplicate customers orphan the Billing Portal), `allow_promotion_codes` (buyer discount — NOT the referrer-rewarding referral code), and `locale` (`ca → es`, Stripe has no Catalan); payload building split into `StripeService::checkoutSessionParams()` so it is testable without the SDK. `UserResource` gains additive business-only `subscription_status` + `subscription_cancel_at_period_end` (warning copy only — `has_active_subscription` is still the only field the paywall reads; `past_due` is NOT active). Paywall redirects carry `?reason=…` (§3). Public `/pricing` + `/es/pricing` added. Prior: Web app redesign wiring: documented the **paywall HTTP-status asymmetry** — apply/publish surface as 402 but **accept surfaces as 403** via `ApplicationPolicy::accept()`, so clients must treat `403 + business-without-sub` as the paywall too; also documented the `scheduled_date` recurring-day/window rule on accept — §3. No role rule changed. Prior: 2026-08-15 Stripe web checkout now implements `source=stripe` — `POST /me/subscription/checkout` + public signature-verified `/webhooks/stripe`, business-only, server-side price ids, return-URL allowlist, referral-on-first-paid; the `source`-invariant in §9 still holds — see §9. Prior: 2026-07-28 Explore browse feed now hides date-exhausted kolabs — `KolabService::browse()` applies `Kolab::scopeWithSelectableDates()` (serves `/kolabs` + `/opportunities` shim), mirroring the apply-time guard; `?saved=1` left unfiltered — §4. Prior: 2026-07-15 admin company/legal settings: single-row `company_settings` (name/address/NIF/refund/emails + `terms_version`/`terms_effective_date`), maintainer CRUD at `/admin/company-settings`, `CompanySettingService::termsVersion()` is now the consent-version source (config = fallback), a view composer injects the values into the four legal pages — §0 item 12. Prior: legal consent gate: `accepted_terms` (`required|accepted`) on all `register/*` endpoints, OAuth signups stamped in `AuthService::consentStamp()`, consent on `profiles.terms_accepted_at`/`terms_version` vs `config('legal.terms_version')`, `GET /auth/me` `terms` block, `POST /me/consent` (`ConsentController`), `Profile::needsTermsAcceptance()` — §0 item 12. Prior: profile reputation cache (#76): `getReputationSummary()` cached per profile with observer-based invalidation (`CollaborationReviewObserver` + `CollaborationObserver`) and the duplicate `completed_kolabs_count` COUNT removed — §13. Prior: DB scalability indexes (#72): 37 previously-unindexed FKs + hot-path composite/partial indexes added; `ProfileService::getReputationSummary()` window function gained a deterministic `id ASC` tiebreaker so the per-pair cap is stable across index/scan order — §13. Prior: PR 5: reputation shape — `unique_partner_count` removed from public reputation block; per-pair fairness cap added (max 2 reviews per reviewer→reviewed pair via SQL window function, no schema change); `recent_reviews` items serialise `is_verified_kolab_review: true` — §13. Prior: PR 4: public reputation summary — `collaboration_reviews` schema expansion with five category star ratings + `public_comment` + `public_comment_visible` gate, `ProfileService::getReputationSummary()` aggregation, and new `reputation` block on `PublicProfileResource` — §13. Prior: 2026-06-28 gamification mission system v1 curation: `challenges.app_visible` column + the three event/general mission filter sites — #49. Prior same day: #61 Saved Kolabs — new `saved_kolabs` pivot + save/unsave endpoints + `?saved=1` list + viewer-scoped `is_saved` flag — §7, §7.1. PR #59 review fixes: completion-confirmation gate hardening — terminal-state guard, `pending = not-yes` resource/gate alignment, auto-complete grace anchored on `updated_at`, `Collaboration::roleFor()`, **legacy feedback fallback + backfill removed (`/complete` now gates purely on real completion confirmations)**, dead-code removal — §0 item 10, §3, §8, §10. Prior: 2026-06-26 PR 1 moved the `/complete` gate to `collaboration_completions`)) — Also 2026-08-22 (**Two-sided suggestions — new §18.** BE-NF-39, shipped dark behind `config('suggestions.enabled')`. No gate and no role rule changed: one new table `kolab_suggestions`, three additive endpoints, one optional `suggestion_id` on `POST /kolabs`, one nightly command at the only free slot (04:00) and one on-registration job. Things that will be got wrong without reading §18: the unique key is **`(viewer, counterpart)` and deliberately excludes `batch_key`** — one row per pair refreshed in place, so a refresh payload must never contain the funnel columns and a dismissal is cleared only once its cooldown expired; `signals`/`suggested_format`/`evidence` are **write-once jsonb never touched in SQL** (the BE-FX-12 / `max(uuid)` lesson made structural); reasons and titles persist as **keys + raw params and are rendered at read time in the reader's locale**, because generation runs nightly in the app's default locale; the existing-pair exclusion matches `collaborations.creator_profile_id`/`applicant_profile_id` and **never** the nullable extended-profile FKs, or it never fires; and `city_id` resolves to the **business** side of the pair, never the viewer's, so the two mirrored rows agree. The blur is enforced in exactly one place, `SuggestionResource::shouldBlurIdentity()`, which **early-returns on `! isBusiness()`** — written subscription-first it masks every community viewer — and withholds `counterpart.id` alongside the name and avatar because `GET /profiles/{id}` would otherwise resolve the identity (**BE-FX-22**, filed). That per-request mask also answers §4's objection to server-side blurring: nothing is stored, so reveal-on-subscribe needs no re-fetch (**BE-FX-21**). `CategoryFitMatrix` / `OfferTypeAliases` / `OfferVocabulary` extracted to `app/Support/Matching` and shared with Explore — **aggregation policy stays per-caller on purpose** (Explore maps an unmapped pair onto a fallback so it can always rank; the scorer drops the signal and renormalises), and the matrix has **no row for six live community types (~23% of community profiles)**, an open product decision. Prune runs unconditionally inside the same nightly pass. Digest, push and any mobile surface are **not** shipped (§18.9). §0 gains hot spot 13; §8 gains four checklist items; §3's `?reason=` list gains `suggestion`. ROLES §2.13. Prior: 2026-08-20 (**Invite page moved to the app host** — §12.6. It had never worked: the marketing layout loads no Alpine and the CSP grants 'unsafe-eval' only to the app host, so the CTA rendered empty. Moving it was preferred to weakening a security header. `invite_base_url` now defaults to https://app.kolabing.com/c, with a 301 from the old path. The page also gained Google sign-up + a name/phone/photo form using only existing endpoints. Prior: 2026-08-20 (**Phone preview — new §17.5.** Web-app only: a read-only replica of the app's public profile screen beside every Profile-section tab, refreshed after each edit. It mirrors three named Dart files in kolabing-app and must move with them. No API, schema, role or gate change. Prior: **Last updated:** 2026-08-20 (**Public portfolio — new §17.** Three new endpoints (gallery caption + gallery order + event photo order) sharing one `PhotoOrderingService` rule; `buildCommunityPastEvents()` merges the `events` table with `kolabs.past_events` (additive `source`/`source_event_id`/`attendee_count`, newest-first, undated last, deduped on name+date, two queries at any size); `GET /profiles/{id}` gains the portfolio via the narrower `hydratePublicPortfolio()` — the full detail hydration would have added count queries this endpoint does not emit and would have 404'd every attendee profile. No new table, no migration. Prior: 2026-08-19 (**Public-profile indexation bar revised — §16.6b.** The sitemap bar moved from "has a completed collaboration" to "has a review or three photos", because the first bar published a seeded test account and would have published hundreds of empty near-duplicate profiles; the same predicate now drives `noindex` on the page, and `/blog` + `/communities` use the same empty-hub rule through the layout's new `noindex` prop. Added `Profile::receivedReviews()`. Part of the SEO audit remediation, BACKLOG BE-FX-19. Prior same day: (**Public profile pages — new §16.** No schema change. One additive endpoint `GET /profiles/{id}/public-profile` serves the rich profile (gallery, aggregated photos, past events, past collaborations, stats) for **either** role: `past_events` lives on `kolabs.past_events` and was always written by whoever creates the Kolab, so only the community-scoped route's `isCommunity()` guard made it look community-only — that route is unchanged and still 404s for a business (pinned by a test). `buildCommunityPhotos()` stopped assuming `communityProfile`. Three additive fields for linking: `public_url` on the rich resource, `actor_profile_id` on notifications, `id` on chat participants. The public teaser at `kolabing.com/p/{slug}` reads models directly rather than opening any unauthenticated API, and the sign-up wall is enforced by never rendering the withheld fields (§16.4, ROLES §4.2). Slugs are `name-<uuid tail>` because `profiles.handle` is attendee-only in practice — 5 of 94 production profiles have one (§16.3). Prior: 2026-08-18 (**Chat on the web panel — new §15.** No endpoint, column, or gate added: the panel is a second client over the existing chat API. Documented what it calls and why, including two things that are easy to get wrong — `GET /chats/{thread}/messages` paginates **oldest-first** (page 1 is the start of the conversation; the newest messages are on `last_page`), and `POST /chats/{thread}/messages` on a **collaboration** thread notifies nobody because `threadRecipientIds()` returns `[]` for that type, so Kolab sends must use `POST /applications/{id}/messages` (§15.4, BE-FX-13). Real-time: `NewChatMessage` + `chat.thread.{id}` channel auth already existed; the panel subscribes with a self-hosted Pusher-protocol client and signs private channels at `POST /broadcasting/auth` (app root, Sanctum, bearer token). `config('webapp.realtime')` exposes only the four public Reverb values — never the app secret — and with the key unset the page polls, so this ships safely ahead of BE-IF-18. `AddSecurityHeaders` adds `wss:` to `connect-src` on the web-app host only. Two further gaps filed: BE-FX-14 (§2.8 lapse re-gate unenforced for chat — doc/code drift) and BE-FX-15 (`GET /me/communities` hides `can_manage` delegates' communities). Prior: 2026-08-17 (**Web payment flow — new §14.** No gate changed. `POST /me/subscription/checkout/confirm` added: the return-from-Stripe page activates the subscription synchronously (ownership-checked against `client_reference_id`, 403 otherwise; 409 `pending` while unpaid; same idempotent `activateFromStripeSession` the webhook runs — which now returns the upserted row). Checkout Session gains `customer` **or** `customer_email` (never both — duplicate customers orphan the Billing Portal), `allow_promotion_codes` (buyer discount — NOT the referrer-rewarding referral code), and `locale` (`ca → es`, Stripe has no Catalan); payload building split into `StripeService::checkoutSessionParams()` so it is testable without the SDK. `UserResource` gains additive business-only `subscription_status` + `subscription_cancel_at_period_end` (warning copy only — `has_active_subscription` is still the only field the paywall reads; `past_due` is NOT active). Paywall redirects carry `?reason=…` (§3). Public `/pricing` + `/es/pricing` added. Prior: Web app redesign wiring: documented the **paywall HTTP-status asymmetry** — apply/publish surface as 402 but **accept surfaces as 403** via `ApplicationPolicy::accept()`, so clients must treat `403 + business-without-sub` as the paywall too; also documented the `scheduled_date` recurring-day/window rule on accept — §3. No role rule changed. Prior: 2026-08-15 Stripe web checkout now implements `source=stripe` — `POST /me/subscription/checkout` + public signature-verified `/webhooks/stripe`, business-only, server-side price ids, return-URL allowlist, referral-on-first-paid; the `source`-invariant in §9 still holds — see §9. Prior: 2026-07-28 Explore browse feed now hides date-exhausted kolabs — `KolabService::browse()` applies `Kolab::scopeWithSelectableDates()` (serves `/kolabs` + `/opportunities` shim), mirroring the apply-time guard; `?saved=1` left unfiltered — §4. Prior: 2026-07-15 admin company/legal settings: single-row `company_settings` (name/address/NIF/refund/emails + `terms_version`/`terms_effective_date`), maintainer CRUD at `/admin/company-settings`, `CompanySettingService::termsVersion()` is now the consent-version source (config = fallback), a view composer injects the values into the four legal pages — §0 item 12. Prior: legal consent gate: `accepted_terms` (`required|accepted`) on all `register/*` endpoints, OAuth signups stamped in `AuthService::consentStamp()`, consent on `profiles.terms_accepted_at`/`terms_version` vs `config('legal.terms_version')`, `GET /auth/me` `terms` block, `POST /me/consent` (`ConsentController`), `Profile::needsTermsAcceptance()` — §0 item 12. Prior: profile reputation cache (#76): `getReputationSummary()` cached per profile with observer-based invalidation (`CollaborationReviewObserver` + `CollaborationObserver`) and the duplicate `completed_kolabs_count` COUNT removed — §13. Prior: DB scalability indexes (#72): 37 previously-unindexed FKs + hot-path composite/partial indexes added; `ProfileService::getReputationSummary()` window function gained a deterministic `id ASC` tiebreaker so the per-pair cap is stable across index/scan order — §13. Prior: PR 5: reputation shape — `unique_partner_count` removed from public reputation block; per-pair fairness cap added (max 2 reviews per reviewer→reviewed pair via SQL window function, no schema change); `recent_reviews` items serialise `is_verified_kolab_review: true` — §13. Prior: PR 4: public reputation summary — `collaboration_reviews` schema expansion with five category star ratings + `public_comment` + `public_comment_visible` gate, `ProfileService::getReputationSummary()` aggregation, and new `reputation` block on `PublicProfileResource` — §13. Prior: 2026-06-28 gamification mission system v1 curation: `challenges.app_visible` column + the three event/general mission filter sites — #49. Prior same day: #61 Saved Kolabs — new `saved_kolabs` pivot + save/unsave endpoints + `?saved=1` list + viewer-scoped `is_saved` flag — §7, §7.1. PR #59 review fixes: completion-confirmation gate hardening — terminal-state guard, `pending = not-yes` resource/gate alignment, auto-complete grace anchored on `updated_at`, `Collaboration::roleFor()`, **legacy feedback fallback + backfill removed (`/complete` now gates purely on real completion confirmations)**, dead-code removal — §0 item 10, §3, §8, §10. Prior: 2026-06-26 PR 1 moved the `/complete` gate to `collaboration_completions`) Prior: ** 2026-08-19 (**Community members web panel — new §12.6.** New table `community_invitations` + its endpoints, `GET /communities/{c}/stats`, member-detail and bulk-update endpoints, and the public `/c/{slug}` join route. `GET /communities/{c}/members` gains filters/sorts and per-row engagement metrics (resolved with left-joined grouped aggregates — no N+1, locked by a query-count test), is **now manage-gated**, and **defaults to excluding `status = removed`**. `AuthService::startOnboardingDrip` renamed to `afterRegistration` and now also claims pending invitations for the new profile's email (guarded). `CommunityMemberResource` fixed: it read the extended profile for a display name, but `attendee_profiles` has no `name` column, so every community member rendered as their email prefix — `profiles.name` is read first now. No gate changed; §12.5 is now enforced by `CommunityNeverPaywalledTest`. Prior: 2026-08-19 (**Public profile pages — new §16.** No schema change. One additive endpoint `GET /profiles/{id}/public-profile` serves the rich profile (gallery, aggregated photos, past events, past collaborations, stats) for **either** role: `past_events` lives on `kolabs.past_events` and was always written by whoever creates the Kolab, so only the community-scoped route's `isCommunity()` guard made it look community-only — that route is unchanged and still 404s for a business (pinned by a test). `buildCommunityPhotos()` stopped assuming `communityProfile`. Three additive fields for linking: `public_url` on the rich resource, `actor_profile_id` on notifications, `id` on chat participants. The public teaser at `kolabing.com/p/{slug}` reads models directly rather than opening any unauthenticated API, and the sign-up wall is enforced by never rendering the withheld fields (§16.4, ROLES §4.2). Slugs are `name-<uuid tail>` because `profiles.handle` is attendee-only in practice — 5 of 94 production profiles have one (§16.3). Prior: 2026-08-18 (**Chat on the web panel — new §15.** No endpoint, column, or gate added: the panel is a second client over the existing chat API. Documented what it calls and why, including two things that are easy to get wrong — `GET /chats/{thread}/messages` paginates **oldest-first** (page 1 is the start of the conversation; the newest messages are on `last_page`), and `POST /chats/{thread}/messages` on a **collaboration** thread notifies nobody because `threadRecipientIds()` returns `[]` for that type, so Kolab sends must use `POST /applications/{id}/messages` (§15.4, BE-FX-13). Real-time: `NewChatMessage` + `chat.thread.{id}` channel auth already existed; the panel subscribes with a self-hosted Pusher-protocol client and signs private channels at `POST /broadcasting/auth` (app root, Sanctum, bearer token). `config('webapp.realtime')` exposes only the four public Reverb values — never the app secret — and with the key unset the page polls, so this ships safely ahead of BE-IF-18. `AddSecurityHeaders` adds `wss:` to `connect-src` on the web-app host only. Two further gaps filed: BE-FX-14 (§2.8 lapse re-gate unenforced for chat — doc/code drift) and BE-FX-15 (`GET /me/communities` hides `can_manage` delegates' communities). Prior: 2026-08-17 (**Web payment flow — new §14.** No gate changed. `POST /me/subscription/checkout/confirm` added: the return-from-Stripe page activates the subscription synchronously (ownership-checked against `client_reference_id`, 403 otherwise; 409 `pending` while unpaid; same idempotent `activateFromStripeSession` the webhook runs — which now returns the upserted row). Checkout Session gains `customer` **or** `customer_email` (never both — duplicate customers orphan the Billing Portal), `allow_promotion_codes` (buyer discount — NOT the referrer-rewarding referral code), and `locale` (`ca → es`, Stripe has no Catalan); payload building split into `StripeService::checkoutSessionParams()` so it is testable without the SDK. `UserResource` gains additive business-only `subscription_status` + `subscription_cancel_at_period_end` (warning copy only — `has_active_subscription` is still the only field the paywall reads; `past_due` is NOT active). Paywall redirects carry `?reason=…` (§3). Public `/pricing` + `/es/pricing` added. Prior: Web app redesign wiring: documented the **paywall HTTP-status asymmetry** — apply/publish surface as 402 but **accept surfaces as 403** via `ApplicationPolicy::accept()`, so clients must treat `403 + business-without-sub` as the paywall too; also documented the `scheduled_date` recurring-day/window rule on accept — §3. No role rule changed. Prior: 2026-08-15 Stripe web checkout now implements `source=stripe` — `POST /me/subscription/checkout` + public signature-verified `/webhooks/stripe`, business-only, server-side price ids, return-URL allowlist, referral-on-first-paid; the `source`-invariant in §9 still holds — see §9. Prior: 2026-07-28 Explore browse feed now hides date-exhausted kolabs — `KolabService::browse()` applies `Kolab::scopeWithSelectableDates()` (serves `/kolabs` + `/opportunities` shim), mirroring the apply-time guard; `?saved=1` left unfiltered — §4. Prior: 2026-07-15 admin company/legal settings: single-row `company_settings` (name/address/NIF/refund/emails + `terms_version`/`terms_effective_date`), maintainer CRUD at `/admin/company-settings`, `CompanySettingService::termsVersion()` is now the consent-version source (config = fallback), a view composer injects the values into the four legal pages — §0 item 12. Prior: legal consent gate: `accepted_terms` (`required|accepted`) on all `register/*` endpoints, OAuth signups stamped in `AuthService::consentStamp()`, consent on `profiles.terms_accepted_at`/`terms_version` vs `config('legal.terms_version')`, `GET /auth/me` `terms` block, `POST /me/consent` (`ConsentController`), `Profile::needsTermsAcceptance()` — §0 item 12. Prior: profile reputation cache (#76): `getReputationSummary()` cached per profile with observer-based invalidation (`CollaborationReviewObserver` + `CollaborationObserver`) and the duplicate `completed_kolabs_count` COUNT removed — §13. Prior: DB scalability indexes (#72): 37 previously-unindexed FKs + hot-path composite/partial indexes added; `ProfileService::getReputationSummary()` window function gained a deterministic `id ASC` tiebreaker so the per-pair cap is stable across index/scan order — §13. Prior: PR 5: reputation shape — `unique_partner_count` removed from public reputation block; per-pair fairness cap added (max 2 reviews per reviewer→reviewed pair via SQL window function, no schema change); `recent_reviews` items serialise `is_verified_kolab_review: true` — §13. Prior: PR 4: public reputation summary — `collaboration_reviews` schema expansion with five category star ratings + `public_comment` + `public_comment_visible` gate, `ProfileService::getReputationSummary()` aggregation, and new `reputation` block on `PublicProfileResource` — §13. Prior: 2026-06-28 gamification mission system v1 curation: `challenges.app_visible` column + the three event/general mission filter sites — #49. Prior same day: #61 Saved Kolabs — new `saved_kolabs` pivot + save/unsave endpoints + `?saved=1` list + viewer-scoped `is_saved` flag — §7, §7.1. PR #59 review fixes: completion-confirmation gate hardening — terminal-state guard, `pending = not-yes` resource/gate alignment, auto-complete grace anchored on `updated_at`, `Collaboration::roleFor()`, **legacy feedback fallback + backfill removed (`/complete` now gates purely on real completion confirmations)**, dead-code removal — §0 item 10, §3, §8, §10. Prior: 2026-06-26 PR 1 moved the `/complete` gate to `collaboration_completions`))
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
13. ⚠️ **NEW — two-sided suggestions (BE-NF-39, §18).** One new table (`kolab_suggestions`), three additive endpoints and one optional `suggestion_id` on `POST /kolabs`, all behind `config('suggestions.enabled')` (404 when off, never 403). **No new gate**: the surface is free to view for every role, and acting on a card walks into the existing §3 gates. It is, however, the **first place a blur is enforced server-side** — `SuggestionResource` nulls the counterpart's `name`, `avatar_url` *and* `id` for a business with no active subscription. Two ways to break it: masking a community (the condition must test `isBusiness()` **first**, because `hasActiveSubscription()` returns false for every non-business), and re-adding `counterpart.id` to a blurred card, which resolves the identity in one more request via `GET /profiles/{id}` (BE-FX-22). See §18.6.

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

**Where the paywall sends people (added 2026-08-17; `suggestion` added 2026-08-22):** every web paywall hop now appends `?reason=publish|accept|apply|create|welcome|suggestion` to `/subscription` so the plan page can name the blocked action. Presentation only — it changes no gate, and `suggestion` in particular is **not** a new gate: it is the blurred-counterpart CTA on a suggestion card (§18.6, ROLES §2.13). Purchase/confirmation mechanics: §14.

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

- [ ] **BE-FX-22 — `GET /profiles/{id}` hands a non-subscribed business the identity ROLES §2.5 says it cannot open.** `PublicProfileResource` contains zero subscription checks, so the identity a business is meant to pay for is one request away for anyone holding a profile id. This is why a blurred suggestion card withholds `counterpart.id` as well as the name and avatar (§18.6) — that masking is the pattern to copy here. Detected during BE-NF-39's security review.
- [ ] **Never mask a community on the suggestion surface, and never write the blur condition subscription-first (§18.6).** `Profile::hasActiveSubscription()` returns `false` for *every* non-business, so `! $viewer->hasActiveSubscription()` on its own — or tested before the role — masks every community viewer on the platform. `SuggestionResource::shouldBlurIdentity()` early-returns on `! isBusiness()` for exactly that reason; a "simplification" that removes the short-circuit is the most damaging regression this feature makes available. Pinned by `SuggestionApiTest::test_a_community_viewer_is_not_masked_even_though_it_has_no_subscription`.
- [ ] **Never add a subscription check to the suggestion list, detail or dismissal (§18.4).** The surface is free to view for every role; the paywall stays the two §2.7 actions the card's create step already walks into. Same rule as §12.5 for the community-members surface: no `hasActiveSubscription()` anywhere on this surface except inside the resource's blur.
- [ ] **Decide whether to extend `CategoryFitMatrix` to the six unmapped community types** (`art_creative_community`, `sustainability_community`, `photography_community`, `hobby_community`, `dance_community`, `other` — ~23% of live community profiles on 2026-08-19). Today those pairs lose `category_fit` honestly; the alternative must not be an invented mid-range score. Open **product** decision, not a bug (§18.3).
- [ ] Implement the **blur** (name + logo) for free businesses on Explore. Server should emit an `identity_locked` flag (or null the identity for free businesses) and the client should render an actual blur. **No hard block on Explore.** (§4) — BE-NF-39 shows the server-side half is cheap and does *not* break reveal-on-subscribe: `SuggestionResource` masks per request at serialisation time and stores nothing (§18.6). Filed as **BE-FX-21** for Explore specifically.
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

 ├─1:N─ community_followers (kolabing-app#138 — id uuid PK,
 │                         community_id FK->communities cascade,
 │                         profile_id FK->profiles cascade,
 │                         followed_at, timestamps,
 │                         UNIQUE(community_id, profile_id))
 └─1:N─ community_join_questions (kolabing-app#138 — id uuid PK,
                           community_id FK->communities cascade,
                           position smallint (1..5), prompt string(280),
                           required bool default true,
                           is_active bool default true (RETIRE, never delete),
                           timestamps)
                             └─1:N─ community_join_answers (id uuid PK,
                                     join_request_id FK->community_join_requests cascade,
                                     question_id FK->community_join_questions cascade,
                                     answer text, timestamps,
                                     UNIQUE(join_request_id, question_id))

events.community_id  FK->communities nullOnDelete null   ← the §8.6 linkage
```

### 12.1a Follower vs member (added 2026-08-22, kolabing-app#138)

**Two relationships, two tables.** `community_followers` is interest;
`community_members` is belonging. A follower may see the community and sign up
to its **public** events — and so play the QR check-in / challenge loop, whose
XP is global and not community-scoped. Membership is what gates the chat,
member/tier events, community points, badges, the leaderboard and tiers.

**Why not one table with a `kind` column.** Every member gate reads
`community_members`. Keeping followers out of it means none of those queries can
begin matching a follower by accident; a discriminator column fails the other
way — the unfiltered query would include followers, so missing one call site
would leak privilege silently. `CommunityMemberAccessRegressionTest` locks both
directions.

**Membership gate.** `community_join_questions` (max 5 active, enforced in
`CommunityJoinQuestionService`) + `community_join_answers` on the existing
`CommunityJoinRequest`. `join_policy` now reads as: `invite_only` → a leader
decides; `open` → refuses the request path **unless** the community has active
questions, in which case the application is accepted and auto-approved in one
transaction. No community has questions until a leader creates one, so this
changed nothing on deploy.

**Follower state is not on `CommunityResource`** — it is serialized in lists and
a per-row count is an N+1 (`MeRewardsOverviewNPlusOneTest` caught exactly that).
It is served by `GET /me/community-follows` and by the follow/unfollow responses
instead.

Enums (`app/Enums`): `CommunityType`, `TierAssignmentRule`, `JoinPolicy`, `CommunityMemberStatus`. Models: `Community`, `CommunityTier`, `CommunityMember`, `CommunityFollower`, `CommunityJoinQuestion`, `CommunityJoinAnswer` (+ `Profile::ownedCommunities()` / `communityMemberships()` / `communityFollows()`, `Community::followers()` / `joinQuestions()`, `Event::community()`).

### 12.2 The cap gate — NOT the paywall

| Concept | Code | Notes |
|---|---|---|
| Free community cap | `CommunityService::create()` → `config('communities.max_free_communities', 1)` → `CommunityLimitReachedException` | Controller catches → **HTTP 422** `{error: community_limit_reached}`. **Never** calls `hasActiveSubscription()`. Reserved for NF-7 Community Premium. |
| Default tier | `CommunityService::create()` auto-creates one `is_default` manual tier (`Member`, rank 1) | New joiners land here; it is the floor auto-rules promote away from. |
| Authorization | `CommunityPolicy::manage()` = owner OR active member with `can_manage` | Registered in `AppServiceProvider`. Mutating tiers/roster/community requires it. No subscription check. |

### 12.3 Endpoints (all `auth:sanctum`, `routes/api.php`)

| Method + path | Controller | Gate |
|---|---|---|
| `GET /me/communities` | `CommunityController@index` | auth (**owned + managed**, see 12.7) |
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
- **Never scope a "communities I administer" list to ownership.** `can_manage`
  members are authorised by `CommunityPolicy::manage()` everywhere else, so an
  owned-only list silently strips them of every action they hold (BE-FX-15, 12.7).

### 12.7 `GET /me/communities` is the OWNED + MANAGED set (BE-FX-15, fixed 2026-08-22)

`CommunityController@index` returned `$profile->ownedCommunities()` only. A member
with `community_members.can_manage = true` therefore could not list the community
they co-run — and because **no client could discover the community id**, every
management action they were authorised for (`CommunityPolicy::manage`, the custom
chat channel + ban endpoints, `ChatService::canManageCommunity()`) was unreachable.
The web panel's channel management was scoped to owned communities for exactly
this reason, and `webapp/layout.blade.php` merged `/me/memberships` client-side as
a workaround.

- **Canonical query:** `Community::scopeManageableBy(Profile)` — `owner_profile_id
  = viewer` **OR** an `active` `community_members` row for the viewer with
  `can_manage = true`. This is the query form of `CommunityPolicy::manage()`;
  `ChatService::managedCommunityIds()` is the collection form. Keep the three in
  agreement — `status = active` is part of the rule, not an optimisation.
- **Additive wire field:** `CommunityResource.my_can_manage` (bool) — owner, or an
  active `can_manage` member. Null-safe `false` for an unauthenticated viewer. No
  existing key changed shape or meaning; `CommunityResourceShapeTest` pins the
  full key list.
- **No N+1.** `@index` stamps the transient `viewer_can_manage` attribute (always
  `true` — `manageableBy` *is* the can_manage set), and
  `CommunityMembershipHydrator` stamps it from the membership row it already
  loaded. Only `GET /communities/{id}` takes the resource's per-viewer lazy path,
  where one row means one query.
- Still free, still never paywalled — this changes discoverability only, not who
  may do what.


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

---

## 17b. Event check-in — the door (added 2026-08-21)

> Numbered 17b, not 18: this section and §17 above were both written as "17" by
> concurrent work, and §18 / §19 were then referenced from ROLES and from code
> comments. Renaming rather than renumbering keeps every one of those references true.

The one place the platform records **ground truth**: not who said they were coming, but who was in the room. Everything the
product wants to sell on top of attendance data depends on this being trustworthy, so the rules below are security rules, not
conveniences.

### 17.1 The chain

| Step | Where |
|---|---|
| Host opens the door | `POST /api/v1/events/{event}/generate-qr` — host only (`profile_id` must match, else 403). Mints a 64-char token, an 8-char typable code, an expiry, and sets `is_active`. |
| QR carries | `{webapp.url}/checkin/{code}` — built **only** by `App\Support\CheckinLink`. |
| Attendee lands | Panel route `/checkin/{token}` (`webapp.checkin`). Not signed in → `/login?next=/checkin/{token}`, and login returns them. |
| Check-in | `POST /api/v1/checkin` with `{token}` — accepts the long token **or** the short code, case-insensitively. |
| Host watches | `GET /api/v1/events/{event}/checkins`, polled every 6s while the door is open. |

`event_checkins` is `(event_id, profile_id)` unique, so a second scan is a **409**, which the page shows as "already checked
in" rather than an error. Joinable to `events.community_id`, `collaboration_id`, `starts_at`, `location_lat/lng` and
`tier_gate` — which is what turns a scan into "this community delivered N people to this venue at this time".

### 17.2 What must never leak

The token and the code are **the permission to be recorded as present**. Anyone holding either can check themselves in, so
`EventResource` emits the `checkin` block (code, url, `qr_svg`, expiry, count) **only when the viewer is the host**
(`$viewer->id === $this->profile_id`). A test asserts the code does not appear anywhere in another viewer's payload. Do not
move these fields out of that guard, and do not add them to any list endpoint that a non-host can read.

### 17.3 Why the token expires (BE-FX-20)

`checkin_token` used to be a permanent random string. A QR photographed once could manufacture attendance months later from
anywhere — destroying the only claim that makes this data worth anything. `checkin_token_expires_at` closes the door an hour
after `ends_at` (six hours after `starts_at` when no end is set; end of day for legacy date-only events). An expired token is
a **422**. Re-opening mints a fresh token *and* a fresh code, which is also how a host invalidates a leaked one.

### 17.4 The short code is not a convenience

`checkin_code` is 8 characters from `ABCDEFGHJKMNPQRSTUVWXYZ23456789` — no O/0, no I/1/L, because it gets read out across a
room. It exists because a door has to keep working when a camera will not focus, a screen glares, or a phone cannot decode a
QR. It also keeps the QR at version 3 (29×29 modules) instead of version 6 (41×41) — the difference between scanning from
across a room and having to walk up to the screen.

### 17.5 The QR is generated here, not fetched

`App\Support\QrCode` encodes byte mode, level M, versions 1–10 and renders SVG. No dependency was added: the panel ships
static assets with no bundler and its CSP forbids third-party origins. It is **inlined into the JSON** rather than served as
an image URL because the panel authenticates with a bearer token in `localStorage`, which an `<img src>` cannot send.

Correctness is verified, not assumed: `QrCodeTest` compares against frozen matrices that an independent decoder (jsQR, the
ZXing-derived reader) read back to the exact payload, at v1, v3 and v7 — the last of which exercises the version-information
blocks. If the Reed–Solomon arithmetic, interleaving, placement walk or mask choice ever drifts, the suite fails instead of a
square silently refusing to scan. After an intentional change, regenerate the fixture and decode it with an outside reader
before committing.

### 17.6 Was broken before this (BE-FX-20)

`CollaborationQrCodeController` built `url("/api/v1/events/{id}/checkin?token=…")`: a route that does not exist, in GET form
for a POST endpoint, with the secret in a query string. Any phone scanning that QR got a 404. The same method also created the
event with an inline `Str::random(64)` token, which skipped `generateCheckinToken()` and made the following
`if (! $event->checkin_token)` guard unreachable — so those events never got a code or an expiry. Token minting now lives in
one place.

### 17.8 Two clients, one door (added 2026-08-22)

Mobile will open the same surface, so the contract — not either client — is the source of truth. Four rules keep them in step.

**1. Nobody builds a check-in URL.** `App\Support\CheckinLink` is the only thing that does, and `POST /events/{event}/generate-qr`
returns it as `checkin_url`. A client renders **that string** into a QR; it must not concatenate its own, or a code produced on one
client stops being scannable into the other. A scanner must accept both a bare token/code and a `/checkin/{token}` URL, because
both are in circulation.

**2. Opening the door is idempotent; rotating is explicit.** `generate-qr` returns the *existing* code when the door is still
open, and only extends the window. This is the two-client footgun that was live for a day: a host opens the door on a laptop,
then opens it again on a phone, and the QR people are queuing in front of dies with nothing on screen to say so. Pass
`rotate: true` to deliberately retire a leaked code — the web panel confirms before doing it, because it invalidates every other
screen.

**3. Arrivals are broadcast, and polling is the fallback.** `AttendeeCheckedIn` broadcasts `checkin.recorded` on
`private-event.{id}.door` with the arrival **and the running total**, so a screen that missed a message still lands on the right
number instead of counting what it happened to receive. Clients keep polling `GET /events/{event}/checkins` — slower while the
socket is live — so real-time is an improvement, never a dependency. Channel authorisation is `Event::isHostedBy()`, the same
predicate behind the host-only `checkin` payload and the door-opening 403; the stream names who walked in, so it never widens to
attendees.

**4. One URL reaches whichever client is installed.** `/.well-known/apple-app-site-association` and
`/.well-known/assetlinks.json` are served on the **app host** (the host in the QR) from `config('webapp.app_links')`, claiming
`/checkin/*` and `/c/*`. With them published, iOS and Android hand the URL to the app; without them the browser takes it. Same
QR either way — a printed code cannot know whether the person scanning it has the app. **Both routes 404 until the mobile
identifiers are configured**, deliberately: Apple's CDN caches the association file, so a placeholder would be cached too and
would have to be waited out rather than fixed. `APPLE_APP_ID` is `TEAMID.bundleId`; the Android fingerprints are the release
signing certificate's SHA-256, comma-separated so Play App Signing and a local release key can coexist.

**Still to decide, and it changes data semantics — do not let it be decided by accident.** Offline check-in. A venue with no
signal means a client queueing scans locally, and then `checked_in_at` must be **client-supplied** (clamped server-side to the
door window, future times rejected) or the timestamp records when the phone reconnected rather than when the person walked in —
which corrupts the one number this whole surface exists to produce. Today `checked_in_at` is server-side `now()` only. Adding it
later is a migration; changing the meaning of rows already collected is not something a migration can fix.

**Test-suite caveat worth knowing.** `phpunit.xml` sets `BROADCAST_CONNECTION=null`, and `NullBroadcaster::auth()` is an empty
method — it authorises every channel. Asserting channel rules through `POST /broadcasting/auth` on the default driver therefore
passes no matter what the callback returns, which is why the chat channel has never had a test of its own.
`EventCheckinSyncTest` swaps in a Pusher-protocol driver *and* re-requires `routes/channels.php`, because channel callbacks
attach to whichever broadcaster was current when that file ran.

### 17.7 Tests

`tests/Feature/Api/V1/EventCheckinDoorTest.php` (8) — the payload a screen needs, the window closing after the event, check-in
by token/code/lowercase code, the double-scan 409, the expired-token 422, host-only opening, the token never reaching a
non-host, and the collaboration QR pointing at a real page.
`tests/Feature/WebApp/WebAppEventDoorTest.php` (9) — the three panel pages, the self-refreshing count, the login round-trip,
the open-redirect guard on `?next=`, three locales, copy parity.
`tests/Unit/Support/QrCodeTest.php` (5) — the encoder against decoder-verified fixtures.

---

## 18. The attendee's web surface — backend map (added 2026-08-22)

Public event pages plus attendee registration and RSVP. **No new API endpoint and no schema change**: everything needed already
existed, which is the point of this section — it records where the rules live so the next change does not reimplement them.

### 18.1 What is public, and how it is enforced twice

`EventVisibility::Public` is the gate, and its docblock always said so: *"anyone; surfaces in city discover."*

| Rule | Where |
|---|---|
| Listing | `PublicEventPageController::index()` delegates to `EventDiscoveryService::discover()` |
| Resolving one by URL | `App\Support\PublicEventLink::resolve()` filters `visibility = public` itself |
| Sitemap | `routes/web.php` filters `visibility = public` + upcoming |

Enforcing it in the resolver **as well as** the listing is deliberate. Not-listed and not-readable are different properties, and
only the second one stops a private community's calendar being read by guessing a slug. A test asserts both.

### 18.2 Discovery is not reimplemented

`EventDiscoveryService` already owned public-visibility filtering, the `COALESCE(starts_at, event_date)` ordering, and the
**effective city** rule — an event's city is `events.city_id` when set, else the host community's
`community_profiles.city_id`. Filtering the public feed on `events.city_id` alone would silently drop every event that inherits
its city, so the controller calls the service with `city_id` + `date: upcoming` and no coordinates (the documented non-geo path).
Do not add a second city derivation here.

### 18.3 The wall is the action, and why the hand-off exists

Details are open; RSVP needs an account. The public page **cannot** perform the sign-up: the panel authenticates with a bearer
token in `localStorage` on `app.kolabing.com`, and `kolabing.com` is a different origin that cannot read it. So "I'm going"
links to `{webapp.url}/events/{id}?rsvp=1`; the panel picks the intent up after auth (`?next=` carries it through login) and
calls `POST /events/{event}/signup` once. Same pattern as the check-in QR landing on `/checkin/{token}`.

### 18.4 RSVP eligibility was already right

`EventSignupService::assertEligible()` returns early for public events — *"Public events are open to everyone — no community
membership required"* — so an attendee joins without belonging to the host community, while members-only and tier-gated events
keep their `not_a_member` / `tier_not_permitted` rules. **Nothing was widened for this feature.** Capacity, the waitlist and the
auto-promotion on cancel are untouched.

### 18.5 Attendee registration on the web

Two calls, because the API is shaped that way: `POST /auth/register/attendee` accepts only email, password and
`accepted_terms`; identity lives behind `PUT /onboarding/attendee` (name + handle, `HandleService::FORMAT` — 3–20 lowercase
letters, digits or underscores). The panel does both in sequence and, if onboarding fails, leaves the user signed in with an
error rather than dead-ended — the account exists at that point and the handle can be set later.

No city, categories or venue question: those gate a seller's Explore presence, and an attendee is not selling.

### 18.6 URLs

`App\Support\PublicEventLink` mirrors `PublicProfileLink`: readable name + the last six characters of the UUID, so a link
survives a rename and a full UUID still resolves. Two classes rather than one abstraction because they resolve against
different tables under different visibility rules; the six-character convention is all they share.

### 18.7 Tests

`tests/Feature/Marketing/PublicEventPageTest.php` (10) — the feed and the detail page, **members-only and tier-gated events
neither listed nor reachable by URL**, past events dropping off, the RSVP hand-off URL, `Event` JSON-LD with
`isAccessibleForFree`, an old slug still resolving after a rename, the city filter offering only cities with something on, and
the sitemap including public events while excluding private ones.
`tests/Feature/WebApp/WebAppAttendeeRsvpTest.php` (6) — the third role card and the two-call registration, no seller details
asked of an attendee, `?type=attendee` prefill, the non-host RSVP view, the `?rsvp=1` hand-off, and copy parity in three
locales.

---

## 19. Two-sided suggestions — backend map (BE-NF-39, added 2026-08-22)

Implements `ROLES-AND-PERMISSIONS.md` §2.13. **No gate, no paywall and no role rule changed** — one
new table, one new command, one job, three additive endpoints and one additive optional field on
`POST /kolabs`. The whole surface is behind `config('suggestions.enabled')`.

> **Naming.** The backlog item is **BE-NF-39**. It was numbered **BE-NF-28** against a three-day-old
> BACKLOG, and every id it used had been claimed by a parallel session in the meantime. Docs, code
> comments and BACKLOG were corrected (`884543a`, `8d0866e`); only the branch's 47 commit messages
> still carry the old number, which was judged not worth a rebase.

### 19.1 The table — `kolab_suggestions`

`database/migrations/2026_08_19_120000_create_kolab_suggestions_table.php`, model
`app/Models/KolabSuggestion.php` (`HasUuids`, `HasFactory`).

| Column | Type | Notes |
|---|---|---|
| `id` | uuid PK | `HasUuids` |
| `audience` | string | `business` \| `community` (`SuggestionAudience`). Mirrors the **viewer's** own `user_type` — it says which side is being shown the card, not which side the counterpart is |
| `viewer_profile_id` | uuid FK `profiles` | cascade on delete. The only profile allowed to read the row |
| `counterpart_profile_id` | uuid FK `profiles` | cascade on delete. The proposed partner |
| `city_id` | uuid FK `cities` nullable | null on delete. City of the *proposed event*, resolved to the **business** side with the community as fallback — see 18.2 |
| `score` | unsignedSmallInteger | 0–100, computed in PHP |
| `confidence` | string | `low` \| `medium` \| `high` (`SuggestionConfidence`) — the share of total signal weight that had real data behind it |
| `signals` | jsonb | `[{key, reason_key, reason_params, weight, score}]` — **keys and raw params, never rendered text** |
| `suggested_format` | jsonb | `{title_key, title_params, intent_type, weekday (ISO 1–7), weekday_basis, time_of_day (H:i), expected_attendance, attendance_basis, notes, offer[], expects[]}` |
| `evidence` | jsonb | the ids and aggregates that produced the row (`event_ids`, `series_weekdays`, `posts_reels_total`, …) |
| `batch_key` | date | **the date this pair was last scored**, not a generation bucket |
| `expires_at` | timestamp | last score + `expires_after_days` |
| `shown_at` / `clicked_at` / `dismissed_at` | timestamp nullable | funnel; first value wins on all three |
| `converted_kolab_id` | uuid FK `kolabs` nullable | null on delete. Funnel close |

Indexes: `unique (viewer_profile_id, counterpart_profile_id)` as `kolab_suggestions_pair_unique`,
`(viewer_profile_id, score)` (the read path), `(audience, batch_key)` (the digest path, which has no
consumer yet — 18.9).

**Why the unique key excludes `batch_key`.** One row per pair, refreshed in place. An earlier draft
keyed on `(viewer, counterpart, batch_key)`, which writes a fresh row every night while the previous
thirteen are still inside their 14-day expiry — up to fourteen near-identical cards for one
counterpart. Three consequences the code depends on: `expires_at` rolls forward while a pair keeps
matching (so a pair that stops matching simply ages out); the funnel timestamps live on the row that
gets re-scored, so the refresh payload must never contain them; and a dismissal persists without a
second table.

**`signals`, `suggested_format` and `evidence` are write-once documents and are never filtered,
aggregated or ordered in SQL.** Read them in PHP through the model's `array` casts only. This is the
BE-FX-12 rule made structural: the suite runs on SQLite, production is Postgres, jsonb degrades to
`text` on SQLite, and the `GET /chats` outage (`max(uuid)` — legal on SQLite, no such function on
Postgres) is what a green suite proves about that divergence, which is nothing.

Model scopes, and both read paths go through them:

- `scopeLive()` — `dismissed_at IS NULL AND converted_kolab_id IS NULL AND expires_at > now()`. Note
  the middle clause: a **converted** suggestion leaves the feed. That is not in the design spec; it is
  the code.
- `scopeForViewer(Profile)` — the IDOR guard at query level. A list has no single row to authorize, so
  the scope *is* the authorization for `index`.

### 19.2 The engine — `app/Services/Suggestions/`

| Class | Owns | Does not |
|---|---|---|
| `PairCandidateFinder` | **The only class in the engine that touches the database.** Narrows the pool in SQL, then loads every aggregate the other two need in a fixed handful of batched queries keyed on the surviving counterpart ids, and assembles `PairContext` objects in PHP | Score, format, or run one query per pair — the query count is a function of the *audience*, not the candidate count, and a test pins the ceiling |
| `SignalScorer` | The six signals, the renormalisation, the score and the confidence band. Pure: no DB, no clock, no randomness, no locale | Persist anything, or render a sentence |
| `FormatSuggester` | `suggested_format` from real history. Pure; consults the lang catalogue only to assert a title key *exists* | Invent a weekday, round an attendance up, or render the title |
| `SignalReasonRenderer` | Read-time rendering of `reason_key`/`reason_params` and `title_key`/`title_params` into the reader's locale | Persist |
| `SuggestionGenerator` | Persistence policy only: the `min_score` floor, the `per_profile` cap, the one-row-per-pair refresh, the dismissal clear, and the two failure boundaries | Compute a signal |
| `SuggestionReader` | The read side: which rows are live for a viewer, their order, and the funnel stamps a read leaves | Authorize the per-row routes (the policy does) |
| `SuggestionTelemetry` | The four PostHog events and the two invariants every one of them carries (18.10) | Ship the counterpart's identity |
| `PairContext` | A value object whose constructor asserts every range invariant the scorer relies on — several same-typed adjacencies would otherwise swap silently | Query |

**Candidate filtering (`PairCandidateFinder::counterpartQuery()`)** — every predicate here is load-bearing:

- **City** comes from the **extended** profile (`business_profiles.city_id` /
  `community_profiles.city_id`) **or** `profiles.city_id`. Scoping on `profiles.city_id` alone matches
  almost nothing: that column is attendee-only in practice and has no backfill. A business *viewer*
  additionally matches into its `business_profiles.target_city_ids`. Widening for a business
  *counterpart* would need a JSON predicate in SQL and is deliberately not done. No city ⇒ no
  candidates, which is why the on-registration job waits for onboarding completion.
- **Counterpart type** is the opposite `user_type`; self is excluded by `whereKeyNot`.
- **Completeness**, in two halves. SQL: extended `business_type`/`community_type` not null, **or** (a
  business) `categories` not null, **or** the profile has events. PHP (`isProposable()`): a business
  whose `categories` is a non-null but *empty* jsonb array with a null `business_type` is rejected —
  `[]` cannot be told from `['cafe']` in SQL without engine-specific syntax. `events_count` comes from
  the candidate query's `withCount`, so this costs no extra query.
- **`user_blocks` in either direction** ⇒ excluded.
- **A pending `applications` row in either direction** ⇒ excluded. Joined **through `kolabs`**, because
  `applications` carries no counterpart column at all.
- **An active `collaborations` row** ⇒ excluded, matched on `creator_profile_id` /
  `applicant_profile_id` and **never** on `business_profile_id` / `community_profile_id`. Those two are
  FKs to the *extended* profile tables and are nullable, so comparing them against `profiles.id` means
  the exclusion never fires and the platform keeps introducing pairs that are already working together.
- **A dismissal inside `dismissal_cooldown_days`** ⇒ excluded. Day granularity, like every other
  window in this feature: a cooldown measured to the second would expire mid-batch depending on how
  long the queue took to reach the profile.

Attendance history is read over a 24-month window (`ATTENDANCE_WINDOW_MONTHS`) — two passes of a
seasonal cadence, without letting a 2019 turnout set next month's expectation.

**Scoring (`SignalScorer::score()`).** Six signals — `category_fit`, `location_fit`, `scale_fit`,
`offer_need_fit`, `delivery_proof`, `momentum`. Each returns `null` when it has no data, is dropped
from the weighted sum, **and its weight is removed from the denominator**:
`score = clamp(round(Σ(w·v) / Σ(w with data) × 100), 0, 100)`. `confidence` reports the share of total
weight that had data (accumulated in basis points to avoid float comparison at the threshold), against
`config('suggestions.confidence_thresholds')`. A cold-start pair is therefore scored on what is known
and labelled `low`, not penalised. The class **validates its own config key set** rather than trusting
it: a missing weight key would read as `0.0` and silently drop the signal out of both numerator and
denominator, and a missing `confidence_thresholds` key would read as `0.0` and label the entire batch
`high`.

Two saturation constants live in the class, not in config, on purpose: `FULL_CONTENT_SET = 6.0` (one
Kolab's worth of posts + stories, the business audience's volume divisor) and
`FULL_COLLABORATION_RECORD = 8.0` (the community audience's), the latter anchored on
`gamification_business.tiers.community_favourite.min_completed_kolabs` so the score and the partner
badge tell the reader the same story — but **copied rather than read**, so retuning a gamification tier
cannot silently move every suggestion score.

`delivery_proof` keeps one shape across both audiences (`0.4·rating + 0.3·repeat + 0.3·volume`) but
**`volume` is audience-specific** and `PairContext` carries both counts with the unused arm at zero: a
community proves delivery with `collaboration_feedback.posts_reels + stories_posted`, a business with
`business_partner_statuses.completed_kolabs_count`. Same hazard one signal over in `momentum`
(`recentEventCount` vs `recentActivityCount`). Both selections are pinned by their own tests, because
no range assertion can separate two non-negative counts.

**Persistence (`SuggestionGenerator::persist()`)** uses `firstOrNew` + `fill` + `save`, **not**
`updateOrCreate`, for exactly one reason: whether `dismissed_at` is cleared depends on the value
already stored, and `updateOrCreate` cannot see it. The payload writes `audience`, `city_id`, `score`,
`confidence`, `signals`, `suggested_format`, `evidence`, `batch_key` and `expires_at` — and **never**
`shown_at`, `clicked_at` or `dismissed_at`, except to clear an *expired* dismissal, which is what makes
the cooldown a cooldown. Candidates are ordered by score desc with the counterpart id as tie-break, so
a tie does not move cards on and off the cap from night to night.

**`city_id` is resolved to the business side of the pair, not to the viewer.** Two rows describe every
pair, one per audience, and they must agree about where the event would happen — viewer-relative
resolution would store two different cities for one proposed event, and the `(audience, batch_key)`
index exists to group them. The business side is the anchored one (a venue promotion happens at the
venue; a product promotion ships from its address), and a business viewer's `target_city_ids` mean
"the counterpart's city" would move as the viewer widened its reach. Do not "fix" this.

### 19.3 Shared matching support — `app/Support/Matching/`

Extracted so Explore and the nightly scorer read one copy of each table and cannot drift.

- **`CategoryFitMatrix`** — the community-type × business-category affinity table, lifted out of
  `DiscoveryOpportunityService::COMMUNITY_BUSINESS_CATEGORY_SCORES` (which is now deleted; the service
  calls `CategoryFitMatrix::score()` and `::normalize()`). A missing pairing returns **null** — "no
  data" — never `0.0`, which would assert the pair is a *bad* match. Lookups are exact-match, so every
  caller must go through `normalize()` (case/separator folding) or better `canonicalise()`, which adds
  the alias map for the Spanish slugs live in `business_profiles.categories` (`restaurante`,
  `cafeteria`, `gimnasio`, `tienda-de-deportes`, `centro-de-belleza` — verified read-only against
  production on 2026-08-19). Without those aliases each of those businesses loses `category_fit`
  permanently.
- **`OfferTypeAliases`** — which offer slugs mean the same thing across `OfferOption`'s three
  vocabularies (`offering` gives `venue_space`/`free_drinks`/`sponsorship` where `need` has
  `venue`/`food_drink`/`sponsor`). A raw `array_intersect` across the two reports "no overlap" for a
  business offering precisely what was asked for — a **false 0.0**, not a missing signal. Comparison
  only: `intersect()` returns the caller's own spellings, because the slugs that reach
  `suggested_format.offer`/`expects` must stay in the vocabulary the Kolab form validates. **Explore is
  deliberately not repointed at this class** — its suite is the evidence its ranking was left alone,
  and adopting this table there is a ranking change for its own commit.
- **`OfferVocabulary`** — reads `kolabs.needs` / `offers_in_return` / `offering` / `expects` into a plain
  list of slugs. Production holds **two incompatible shapes in the same columns** (verified read-only
  2026-08-19: roughly 45% of rows are the legacy keyed-boolean object form,
  `{"venue":true,"food_drink":false,…}`, the rest are lists). An `array_intersect` against an object row
  matches nothing, so `offer_need_fit` would return a false 0.0 for half the corpus.

Three things about this directory that a reader cannot infer from the code and will otherwise get wrong:

1. **Reasons and titles are persisted as keys and params, never as rendered text**, and are rendered at
   read time in the *reader's* locale. Generation runs in a nightly command under the app's default
   locale, so anything the generator rendered would reach every Spanish and Catalan reader in English
   and the three `lang/*/suggestions.php` files could never ship. `reason_params` carry **raw slugs and
   raw numbers only** — never localised labels — because number formatting is a render-time concern too
   (`2,5 km` vs `2.5 km`). `reason_key` is separate from `key` because one signal picks different
   sentences depending on its data (distance vs same-city vs other-city; the business vs the community
   phrasing of proven delivery; the variants that name only the non-zero half of a two-number claim).
   `SignalReasonRenderer` is the single read-time half, shared by `SuggestionResource` and, when it
   exists, the digest. A key an older deploy wrote that this code no longer has renders blank and the
   whole line is **dropped**, not shipped.
2. **`CategoryFitMatrix` holds only the lookup table; aggregation policy belongs to each caller and
   differs on purpose.** Explore takes `max()` across a Kolab's categories, floors at 0.25 when it has
   none, adds a seeking bonus, and maps an **unmapped** pair onto a per-business-category fallback
   (0.65 café/restaurant/bar/bakery … down to 0.35 coworking, 0.4 default) so a ranking feed can always
   order something. The suggestion scorer treats an unmapped pair as **no data**: it drops the signal
   and renormalises the remaining weights, because a card must say "we don't know" rather than invent a
   mid-range score a user then reads as a claim. **Do not unify them.** (The matrix's own docblock
   summarises Explore's fallback as "0.4–0.65"; the code's `match` bottoms at 0.35 for `coworking`.)
3. **The matrix has no row for six community types that are live in production** —
   `art_creative_community`, `sustainability_community`, `photography_community`, `hobby_community`,
   `dance_community`, `other` — about **23% of live community profiles** as measured on 2026-08-19.
   They are not aliases of anything, and inventing a mapping would score a pair on a resemblance nobody
   checked, so those pairings keep returning null: the card loses `category_fit` honestly, the weights
   renormalise and the confidence band drops. Extending the matrix is 6 rows × ~16 columns of product
   judgement and is an **open product decision**, not a bug to be patched by a plausible-looking guess.

### 19.4 Endpoints, policy and the flag middleware

All three routes sit inside the `auth:sanctum` group in `routes/api.php` (`~:741`), wrapped in
`Route::middleware('feature:suggestions')`.

| Endpoint | Controller | Behaviour |
|---|---|---|
| `GET /api/v1/suggestions` | `SuggestionController@index` | `ListSuggestionsRequest` (`page` ≥ 1, `per_page` 1–50, default 15). Viewer-scoped in SQL — that scoping **is** the authorization. `score desc, created_at desc`, paginated. Stamps `shown_at` on whatever this page served |
| `GET /api/v1/suggestions/{suggestion}` | `@show` | **Ownership first, liveness second** — an intruder must learn nothing from the difference between "expired" and "not yours", so the 403 is decided before the row's state is looked at. Non-live ⇒ 404. Stamps `clicked_at` |
| `POST /api/v1/suggestions/{suggestion}/dismiss` | `@dismiss` | `throttle:30,1`. 403 on someone else's row, otherwise `204`. **Not** liveness-gated — see 18.6 |
| `POST /api/v1/kolabs` | `KolabController` → `KolabService::create()` | Gains optional `suggestion_id`. Additive; no existing payload changed |

`KolabSuggestionPolicy` (auto-discovered, Laravel 12): `view()` is `profile->id === suggestion->viewer_profile_id`,
and `dismiss()` **delegates to `view()`** rather than repeating the comparison, so the two cannot drift.
Ownership is the only rule: a suggestion names *who the viewer was matched with*, so serving one to
anybody else leaks the pairing itself. The controller spells the refusal out with `cannot()` plus an
explicit 403 body rather than `authorize()`, matching every other `Api/V1` controller's
`{success, message}` envelope.

**No `whereUuid` on the route bindings, deliberately.** `kolab_suggestions.id` is a uuid column and a
non-uuid comparison raises `22P02` on Postgres — but `KolabSuggestion` uses `HasUuids`, and
`HasUniqueStringIds::resolveRouteBindingQuery()` throws `ModelNotFoundException` for a malformed key
*before* it builds a query. A constraint on top would guard nothing; `SuggestionApiTest` pins the
contract instead.

**`App\Http\Middleware\EnsureFeatureEnabled`**, aliased as `feature` in `bootstrap/app.php`:
`feature:suggestions` reads `config('suggestions.enabled')`. It answers **404, not 403** — a 403 tells a
caller the endpoint exists and they merely lack access, which is the exact fact a flag shipping `false`
is hiding. An unknown feature name resolves to null and **closes** the route. JSON/`api/*` requests get
the standard not-found envelope; anything else gets `abort(404)`, which is what makes the web route
404 rather than render an empty state over an API returning 404. The flag is a **staged-rollout gate,
not a secret**: on a route that also has `auth:sanctum` the group's middleware runs first, so an
unauthenticated probe gets 401 and thereby learns the path is routed. That is accepted — reordering
would mean lifting the routes out of the authenticated group and repeating its middleware for a
disclosure worth nothing. Never use this middleware to hide a route's existence.

**Conversion hook.** `CreateKolabRequest` validates `suggestion_id` as `sometimes|nullable|uuid` plus
`Rule::exists('kolab_suggestions','id')->where('viewer_profile_id', $this->user()?->id)`. Ownership is
checked *there* so a stranger's id becomes a clean 422 instead of a silent no-op that marks someone
else's row converted; a null viewer scopes to `viewer_profile_id IS NULL`, which the NOT NULL column can
never match, so it fails closed. `KolabService::markSuggestionConverted()` **repeats** the viewer
predicate, because the `exists` rule is one edit away from being weakened by someone who does not know
it is load-bearing. `whereNull('converted_kolab_id')` makes the **first** conversion win, like every
other funnel marker. **Liveness is deliberately not required**: an expired or dismissed row of the
caller's own still converts, because a stale card must never block Kolab creation.

### 19.5 Command, job and schedule

`app:generate-suggestions` (`app/Console/Commands/GenerateSuggestions.php`), scheduled in
`routes/console.php` at **`dailyAt('04:00')->withoutOverlapping()`** — the only free nightly slot
(02:00 community tiers, 03:00 auto-complete stale collaborations, 08:00/09:00 reminders and business
reactivation, 14:20 partner statuses).

- Returns success with a message and writes nothing when the flag is off.
- `chunkById(200)` over `profiles` filtered to `business` + `community`. **Attendees are excluded in the
  query**, not left to the generator: they are never an audience and on production they are the largest
  of the three types, so scoring them would be the bulk of the batch producing nothing.
- `--profile=` is validated with `Str::isUuid()` **in PHP** before it reaches SQL: `whereKey()` on a
  non-uuid string is an uncatchable Postgres `22P02` that kills the command, while SQLite compares it as
  text and matches nothing — the BE-FX-12 divergence again, invisible to the suite.
- `--dry-run` scores and counts without writing.
- **Two failure boundaries.** Per *profile*: its own try/catch, `report()` to Sentry, batch continues.
  Per *pair*: `SuggestionGenerator` / `PairCandidateFinder` log a warning and drop the pair. The dropped
  count is carried back up and printed, because `written: 0` alone cannot distinguish an empty platform
  from a batch that lost every pair to a failure — and one of those is an incident.

`app/Jobs/GenerateSuggestionsForProfile` gives a new account a card without waiting for 04:00. Entry
point is the static `dispatchIfJustCompleted($profile, $wasCompleteBefore)`, called from
`AuthService` (registration, `$wasCompleteBefore = false`), `ProfileService::update`, and two paths in
`OnboardingService`. Notes that matter:

- **The trigger is the crossing, not the state.** A pass is queued the moment a profile becomes
  complete and never again, so a business that edits its categories five times queues nothing. The
  caller must sample `onboardingCompleted()` *before* it mutates the profile.
- Completeness is `Profile::onboardingCompleted()`, reused from the onboarding drip rather than
  restated — a second definition would drift and the two would disagree about the same profile.
- Attendees return early. An incomplete profile has no city and `PairCandidateFinder` returns nothing
  without one, so an ungated dispatch at registration would queue a pass that provably writes nothing.
- Dispatch is wrapped in try/catch and only logs: suggestions are peripheral and an unreachable queue
  must never roll back onboarding. `handle()` re-checks the flag, carries the profile **id** not the
  model, and no-ops on a deleted profile. `tries = 3`, `backoff = 30`.

**Retention lives in the same nightly pass**, unconditionally rather than behind a `--prune` flag — a
prune that has to be remembered is a prune that never runs, and this command is the table's only
scheduled writer (`expires_at` merely *hides* a dead row). The rule deletes only rows where
`converted_kolab_id IS NULL` **and** `expires_at < now() - dismissal_cooldown_days` **and**
(`dismissed_at IS NULL` **or** `dismissed_at < today - dismissal_cooldown_days`). So: a converted row is
never deleted at any age (it is the entire measurement story), a row dismissed inside the cooldown is
never deleted (deleting it would drop the suppression and re-suggest the pair the next night), and a
pair that still matches was already refreshed earlier in the same pass — its `expires_at` has moved
forward and the prune can never reach it. Only pairs that stopped matching or fell below `min_score`
are ever collected.

### 19.6 Where the blur is enforced, and where the funnel is written

**The blur is enforced in exactly one place: `SuggestionResource::shouldBlurIdentity()` +
`::counterpart()`.** It is computed per request at serialisation time and **nothing is stored**, so a
business that subscribes sees identities on its next request with no re-generation and no re-fetch of
the row. (§4 of this map argues against server-side nulling on the grounds that it "breaks
reveal-on-subscribe without a re-fetch" — that objection does not apply to a mask applied in the
resource. It is the pattern BE-FX-21/BE-FX-22 want.)

```php
if ($viewer === null || ! $viewer->isBusiness()) { return false; }   // early return, load-bearing
return ! $viewer->hasActiveSubscription();
```

**The ordering is the trap.** `Profile::hasActiveSubscription()` returns `false` for *every*
non-business (§1), so the condition written the other way round — or written as the subscription test
alone — masks **every community viewer on the platform**, which is the most damaging regression this
feature makes available. The `isBusiness()` short-circuit exists for that reason and must not be
"simplified". A blurred payload returns `id`, `name` and `avatar_url` as null with
`is_identity_blurred: true` and `user_type` intact; `score`, `confidence`, every rendered signal and the
whole `suggested_format` stay. `id` is withheld because it is a lookup key —
`GET /api/v1/profiles/{id}` returns the identity to any authenticated caller (**BE-FX-22**), so a
blurred card carrying it would resolve the identity in one extra request.

Client side: `resources/views/webapp/suggestions.blade.php` and
`resources/views/webapp/partials/dashboard-widgets.blade.php` branch on `is_identity_blurred` and never
read `counterpart.id`; the blurred branch renders the CTA to `/subscription?reason=suggestion`.
`resources/views/webapp/subscription.blade.php` accepts `suggestion` in its reason allowlist
(`publish|accept|apply|create|welcome|suggestion`).

**Funnel writes, one owner each:**

| Column | Written by | Rule |
|---|---|---|
| `shown_at` | `SuggestionReader::markShown()` | One `whereIn(...)->whereNull('shown_at')->update(...)` for the whole page — not a save per row, and the SQL `whereNull` (rather than pre-filtering in PHP) is what makes two simultaneous serves unable to overwrite each other's first impression. The in-memory models are stamped with the value *this* request proposed so the response is not null; a concurrent serve may have won by milliseconds, and re-selecting to reconcile a funnel marker would buy nothing. Returns the rows it actually stamped — exactly the set `suggestion_shown` fires for, which the caller cannot recompute afterwards |
| `clicked_at` | `SuggestionReader::markClicked()` | First open only |
| `dismissed_at` | `SuggestionReader::dismiss()` | Idempotent — a second dismissal keeps the first timestamp, because the cooldown is measured from it and re-stamping would silently extend the suppression on every client retry |
| `converted_kolab_id` | `KolabService::markSuggestionConverted()` | First conversion wins |

**Reads are liveness-gated; the dismissal write deliberately is not.** Reading an expired row is noise
that stamps `clicked_at` and corrupts the funnel; *writing* a dismissal to one is useful — a client
holding a page fetched minutes ago should be able to say "not interested" without an error, and the
timestamp feeds the cooldown. `SuggestionReader::isLive()` re-queries through the same `live()` scope the
list uses rather than re-deriving the rule in PHP, so the detail can never serve a card the list retired.

Stale counterparts are handled by scoping, not by exception: the list adds
`whereHas('counterpartProfile')`, and `Profile` soft-deletes, so a counterpart deactivated after the
batch ran makes the row disappear. A row already in a client's hands still renders — `counterpart()`
tolerates a null profile rather than 500ing.

### 19.7 Config — `config/suggestions.php`

| Key | Ships as | Status |
|---|---|---|
| `enabled` | `env('SUGGESTIONS_ENABLED', false)` | The rollout gate (18.4) |
| `weights.{category_fit, location_fit, scale_fit, offer_need_fit, delivery_proof, momentum}` | 0.25 / 0.15 / 0.15 / 0.20 / 0.15 / 0.10 | **Deliberate first guesses.** Sum to 1.0 but are renormalised over the signals that have data, so the sum is a convention not a requirement. The key *set* is validated by `SignalScorer` |
| `min_score` | 45 | **Guess.** Floor below which a pair is not written at all — better an empty state than a bad suggestion |
| `confidence_thresholds.high` / `.medium` | 0.75 / 0.45 | **Guesses** |
| `momentum_window_days` | 90 | **Guess**, and it is printed on the card |
| `max_distance_km` | 60 | **Guess** — the distance at which `location_fit` reaches zero |
| `active_cadence` | 4 | **Guess.** How many things inside the momentum window read as an active partner. One shared threshold rather than one per side (no data yet justifies different bars, and two knobs would drift). Zero or below drops the momentum signal instead of dividing by it |
| `community_size_attendance_fraction` | 0.25 | **Guess**, and it sets a number a user reads ("expect around 30 people"). Used only when there are no reported attendance figures to take a median of. Too low to round to a whole person ⇒ no number at all, which is the correct degradation |
| `per_profile` | 5 | Cap per profile per pass |
| `expires_after_days` | 14 | |
| `dismissal_cooldown_days` | 60 | Also the prune horizon (18.5) |
| `digest.*` | `per_email` 3, `resend_after_days` 6, `templates` keyed by audience | **Declared, no consumer** — see 18.9 |

Tuning any of the guesses is a **config change, not a code change**; that is the point of them being
here. Inspect the first real batch (`--dry-run`, or a generated batch read directly) before enabling
the flag.

### 19.8 Telemetry — `SuggestionTelemetry`

`suggestion_shown → suggestion_clicked → suggestion_converted`, with `suggestion_dismissed` as the
negative branch, through the already-wired `App\Services\PostHog\PostHogService`. One class rather than
four scattered `capture()` calls because two invariants must hold for every event, and an invariant
spread over four call sites is one the fifth will break:

1. **`audience` is always tagged.** The feature launches on both sides at once; without the tag a
   business-side win and a community-side flop average into one meaningless number. This is the reason
   the launch is measurable at all, not a nice-to-have breakdown.
2. **The counterpart's identity never ships.** No `counterpart_profile_id`, no names, no avatars, and
   no `evidence` (which carries other rows' ids, each resolving to both parties) — a blurred card
   withholds that data on purpose, and an event payload is a second durable copy of it in a third-party
   processor. `reason_params` are excluded for the same reason; only `signal_keys` (sorted, deduped) go.

Every event carries `audience`, `suggestion_id` (the join key — without it the four steps can only be
compared as aggregates), `score`, `confidence` and `signal_keys`. `suggestion_shown` fires **once per
card, on first impression only** — the caller passes exactly the rows whose `shown_at` it just
stamped, so the event and the column share one denominator. `suggestion_dismissed` adds `was_clicked`,
which separates a card rejected at a glance (wrong in an obvious way) from one opened, read and then
rejected (wrong in a subtle way) — the distinction the weights can actually be tuned against.
`suggestion_converted` adds `kolab_id` and `intent_type`, because PostHog cannot join back to the
database. The distinct id is always a `Profile` **model**, never an id string: `PostHogService` only
honours `analytics_opt_out` when handed a model, so capturing against a bare id would route around a
user's consent choice.

The `paywall_hit(reason=suggestion) → subscription_started` tail of the design's funnel is **not yet
instrumented**.

### 19.9 Not shipped (do not document it as shipped)

- **The weekly digest.** `app:send-suggestion-digest`, `NotificationType::SuggestionsReady` and the two
  Postmark templates do not exist in this branch. `config('suggestions.digest')` and the
  `(audience, batch_key)` index are the hooks left for it, and `SuggestionReader` was split from the
  generator partly so the sender can reuse one "live rows for this viewer" definition. When it lands it
  must ride the existing `notification_preferences.marketing_tips` opt-out and the `email_notifications`
  master switch — **no new preference column** — and dedupe through the `notifications` table like every
  other scheduled reminder.
- **Any mobile surface.** The three endpoints and the optional `suggestion_id` are additive so
  `kolabing-app` can adopt them unchanged, but no mobile client reads them yet.
- **Admin-editable weights.** Config only, for now.

### 19.10 Tests

`tests/Feature/Api/V1/SuggestionApiTest.php` (IDOR 403; non-subscribed business sees
`is_identity_blurred: true` with the rest of the payload intact; subscribed business sees identity;
community never blurred; attendee gets an empty list; expired and stale-counterpart rows absent;
`shown_at`/`clicked_at` stamping; malformed-uuid binding),
`tests/Feature/Api/V1/SuggestionConversionTest.php`, `tests/Feature/Suggestions/PairCandidateFinderTest.php`
(including the query-count ceiling), `SuggestionGenerationTest.php`, `SuggestionTelemetryTest.php`,
`tests/Unit/Services/Suggestions/*` (scorer per signal + boundaries, format suggester, reason renderer,
pair-context invariants), `tests/Unit/Support/{CategoryFitMatrixTest, OfferTypeAliasesTest, OfferVocabularyTest}.php`,
`tests/Unit/Models/KolabSuggestionTest.php`, and `tests/Feature/WebApp/WebAppRoutesTest.php` for
`/suggestions` under every locale plus the flag gating the page and its nav entry.

## 20. Kolabs on the open web — backend map (added 2026-08-22)

Rules live in ROLES §4.3. This is where each one is enforced.

| Rule | Enforced in |
|---|---|
| Which Kolabs a stranger may see | `App\Services\PublicKolabFeedService::publishable()` — `Kolab::published()` + `withSelectableDates()` + description floor. **The only definition.** |
| Same gate on a single URL | `App\Support\PublicKolabLink::resolve()` calls `publishable()`; it does not re-implement the filter. A draft or date-exhausted Kolab 404s even with the exact slug **and** with the bare UUID. |
| Same gate in the sitemap | `routes/web.php` sitemap builder queries `publishable()`. A URL the page would 404 can never be advertised. |
| Business named, community described | `App\Support\PublicKolabPoster::describe()` — the single decision point. Returns `{name, description, is_named}`. |
| Offers shown concretely, never "match" | `App\Support\OfferOptionLabels` reads the admin-managed `offer_options` table (`kind` + `slug` → `name`), falling back to `Str::headline()` so a missing lookup row degrades to a worse label, never a blank chip. |
| Indexing held back | `config('kolabing.public_kolabs.indexable')` — read by both views (the `robots` prop on `x-layouts.marketing-page`) and the sitemap builder, so they can never disagree. |

**Tables read:** `kolabs` (`status`, `published_at`, `availability_end`, `preferred_city`, `intent_type`, `title`, `description`,
`offer_headline`, `needs`, `offers_in_return`, `offering`, `expects`, `community_types`, `seeking_communities`,
`typical_attendance`, `community_size`, `capacity`), `profiles` + `business_profiles` / `community_profiles` (poster
description), `offer_options` (labels), `cities` (filter row). **Written:** nothing — every route is a GET.

**Shape trap.** `needs` / `offers_in_return` / `offering` / `expects` are **lists of slugs** (`CreateKolabRequest` validates
`needs.*` as a string from the vocabulary; production stores `["food_drink"]`). `KolabFactory` still writes the older
associative-boolean shape, so a test built on the factory renders empty labels rather than failing — BACKLOG **BE-FX-25**.

**Routes:** `GET /kolabs` → `public-kolabs`, `GET /kolabs/{slug}` → `public-kolab`, both `cache_marketing`
(`s-maxage=300`), both on the marketing host. No API endpoint was added.

**The login hand-off (fixed here).** `kb.requireAuth()` in `resources/views/webapp/layout.blade.php` used to navigate to a
bare `/login`, discarding the destination — so every cross-host intent (`/events/{id}?rsvp=1`, `/kolabs/{id}?apply=1`) was
lost at sign-in and the visitor landed on the dashboard. It now sends `?next=<path+query>`, which
`window.kbPostAuthTarget()` re-validates as a local path before use (an absolute URL there would be an open redirect).

---

## 21. Explore as an agenda + the Kolab drawer (added 2026-08-22)

Rules in ROLES §3.3. **No gate, no query and no endpoint changed** — this is the panel's
presentation of `GET /discovery/opportunities` (and `GET /kolabs?saved=1` on the Saved tab).
Recorded here because "the Explore feed" is a doc-governed surface and because the date rule
below is the kind of thing that gets re-derived wrongly.

| Concern | Where it lives |
|---|---|
| Which day a Kolab is filed under | `window.kbNextDates(kolab, limit)` in `resources/views/webapp/layout.blade.php` — **the only definition** |
| The rail's groups | `groups` getter in `explorePage()`, `resources/views/webapp/feed.blade.php` |
| Card view-model (both payload shapes) | `normalize()` in the same file |
| The drawer + its behaviour | `resources/views/webapp/partials/kolab-modals.blade.php` / `window.kbModalMixin()` |

**The date rule, and why it is shared.** `kbNextDates()` walks forward from
`availability_start` (floored at **tomorrow** — today is not bookable), skips days absent from
`recurring_days`, and stops at `availability_end`. Three ways it goes wrong when re-derived:
`recurring_days` is **ISO 1-7** while `Date.getDay()` is 0-6 (a mix-up is wrong *only on
Sundays*, so it survives casual testing — see the two-weekday-conventions note); an **empty**
`recurring_days` means "any day in the window", not "no days"; and the floor is tomorrow, not
today. The apply picker (`dateChips`), the feed's rail and the drawer's date tile now all call
it, so the day a Kolab is listed under is always a day the picker will offer.

**Two payload shapes.** Discovery nests dates under `availability: {start, end, recurring_days,
selected_time}` (`DiscoveryOpportunityResource`); `KolabResource` keeps them flat
(`availability_start`, …). `normalize()` flattens both before calling the helper, and
`kbNextDates()` accepts either shape directly.

**`dkGives` / `dkWants`.** The drawer shows the trade as two lists, and which column each
column-name belongs to flips with `intent_type`: `community_seeking` gives `offers_in_return`
and wants `needs`; `venue_promotion` / `product_promotion` give `offering` and expect `expects`.
`labelList()` accepts both the list-of-slugs shape production stores and the associative-boolean
map `KolabFactory` still writes (BE-FX-25), so a factory-built fixture renders instead of
silently emptying.

**Copy link.** Published → the public URL (`marketingUrl + '/kolabs/' + id`, which
`PublicKolabLink::resolve()` accepts by bare UUID) because that opens for someone with no
account. Draft → the in-app URL, because the public page 404s by design (§20 / ROLES §4.3).

**Navigation: the Events entry is gone.** `resources/views/webapp/partials/sidebar.blade.php` no
longer lists `events`. Routes untouched: `/events`, `/events/{event}` and `/checkin/{token}` are
all still served, and `events` rows are still created lazily by
`CollaborationQrCodeController` so a collaboration can mint a check-in token. What changed is
that the app no longer presents a parallel calendar as a place to go. BE-NF-40 tracks pointing
the attendee funnel at confirmed Kolabs instead.

**Written:** nothing. Every surface here is a read.

---

## 24. The community dashboard payload — backend map (BE-NF-46 / BE-FX-29, added 2026-08-25)

**Endpoint:** `GET /api/v1/me/dashboard` → `DashboardController::__invoke()`, which
branches `$profile->isBusiness() ? getBusinessDashboard() : getCommunityDashboard()`.
Note that branch is **not** three-way: an attendee also lands in the community method.

### 24.1 What changed

`getCommunityDashboard()` returned `applications_sent`, `collaborations`,
`upcoming_collaborations`. It now also returns:

| Key | Source | Why it was missing |
| --- | --- | --- |
| `opportunities` | `getOpportunityStats()` | Already role-neutral (`where creator_profile_id = me`); simply never called for a community |
| `applications_received` | `getReceivedApplicationStats()` | Same — `whereHas('kolab', creator_profile_id = me)`. **BE-FX-29**: the mobile `CommunityDashboard.fromJson` has parsed this key since it was written and defaults to `ApplicationsReceivedStats()` (all zeros) when absent |
| `next_action` | `getCommunityNextAction()` (new) | The business chain reads `businessProfile` in `isProfileComplete()`, so it could not be reused as-is |

`getCommunityNextAction()` and `isCommunityProfileComplete()` are the new private
methods. The completeness floor is the community mirror of the business one: `name`,
`about`, `community_type`, `city_id` on `community_profiles`.

### 24.2 Contract

**Additive only.** No key renamed, no key removed, no enum touched, no schema change.
The `next_action` shape is byte-identical to the business one (`key`, `title`, `body`)
and reuses `complete_profile`, `review_pending_applications` and `leave_review` so
both clients keep a single key → destination map. The one new key is `apply_to_first`.

`title` and `body` are still built as English prose server-side, because mobile reads
them that way. The web panel now translates them by `key` and falls back to the
server string, which also fixed the business card being English-only in ES/CA — no
contract change was needed for that.

### 24.3 Web wiring

- `resources/views/webapp/dashboard.blade.php` — the community branch gains the
  Next-up card (`nextActionTitle` / `nextActionBody` / `nextActionHref`), a `received`
  tile in `commStats` that only appears once `applications_received.total > 0`, and a
  community summary card.
- `resources/views/webapp/layout.blade.php` — `loadCommunityPending()` already fetched
  `/communities/{id}/stats` on every page load for the nav badge and threw everything
  but `pending` away. It now keeps the payload in `communityStats`, so the dashboard
  card costs no extra request. Access is unchanged and still grant-based: the stats
  endpoint enforces `cannot('manage', $community)` itself (§12).
- `resources/views/webapp/kolabs.blade.php` — `/kolabs?tab=requests` now honours
  `&sub=sent|received`. Without it the community CTA "Review N pending applications"
  landed on the *sent* sub-tab, which is that role's default and the wrong list.

### 24.4 Traps

- **`duplicateProfilePrompt`.** The server's `complete_profile` (four fields) and the
  panel's `profileScore` meter (seven fields) are the same advice measured two ways and
  can disagree. The panel hides the card while the meter is up.
- **Never gate any of this.** Communities are free (ROLES §3.5/§3.6);
  `test_a_community_needs_no_subscription_for_any_of_this` asserts the whole payload
  returns for a community with no active subscription.

### 24.5 Tests

- `tests/Feature/Api/V1/CommunityDashboardParityTest.php` — 12 tests: the new keys,
  their scoping, the whole `next_action` chain including precedence, and a guard that
  the business chain is untouched.
- `tests/Feature/WebApp/WebAppCommunityDashboardTest.php` — 14 tests over the shipped
  page source.

**Written:** nothing. Every read.

---
