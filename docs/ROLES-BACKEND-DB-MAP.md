# Kolabing — Roles → Backend → Database Map (Ground-Truth)

> Note: `kolabing-v2/CLAUDE.md` references `docs/BACKEND-SCHEMA.md` as a
> MUST-READ schema doc; that file does not currently exist in this repo.
> This file (`ROLES-BACKEND-DB-MAP.md`) is the closest existing
> schema/reference doc and is kept up to date for role-affecting tables.
> Restoring/creating `BACKEND-SCHEMA.md` is tracked as backlog, not part of
> this PR.

**Last updated:** 2026-07-15 (admin company/legal settings: single-row `company_settings` (name/address/NIF/refund/emails + `terms_version`/`terms_effective_date`), maintainer CRUD at `/admin/company-settings`, `CompanySettingService::termsVersion()` is now the consent-version source (config = fallback), a view composer injects the values into the four legal pages — §0 item 12. Prior: legal consent gate: `accepted_terms` (`required|accepted`) on all `register/*` endpoints, OAuth signups stamped in `AuthService::consentStamp()`, consent on `profiles.terms_accepted_at`/`terms_version` vs `config('legal.terms_version')`, `GET /auth/me` `terms` block, `POST /me/consent` (`ConsentController`), `Profile::needsTermsAcceptance()` — §0 item 12. Prior: profile reputation cache (#76): `getReputationSummary()` cached per profile with observer-based invalidation (`CollaborationReviewObserver` + `CollaborationObserver`) and the duplicate `completed_kolabs_count` COUNT removed — §13. Prior: DB scalability indexes (#72): 37 previously-unindexed FKs + hot-path composite/partial indexes added; `ProfileService::getReputationSummary()` window function gained a deterministic `id ASC` tiebreaker so the per-pair cap is stable across index/scan order — §13. Prior: PR 5: reputation shape — `unique_partner_count` removed from public reputation block; per-pair fairness cap added (max 2 reviews per reviewer→reviewed pair via SQL window function, no schema change); `recent_reviews` items serialise `is_verified_kolab_review: true` — §13. Prior: PR 4: public reputation summary — `collaboration_reviews` schema expansion with five category star ratings + `public_comment` + `public_comment_visible` gate, `ProfileService::getReputationSummary()` aggregation, and new `reputation` block on `PublicProfileResource` — §13. Prior: 2026-06-28 gamification mission system v1 curation: `challenges.app_visible` column + the three event/general mission filter sites — #49. Prior same day: #61 Saved Kolabs — new `saved_kolabs` pivot + save/unsave endpoints + `?saved=1` list + viewer-scoped `is_saved` flag — §7, §7.1. PR #59 review fixes: completion-confirmation gate hardening — terminal-state guard, `pending = not-yes` resource/gate alignment, auto-complete grace anchored on `updated_at`, `Collaboration::roleFor()`, **legacy feedback fallback + backfill removed (`/complete` now gates purely on real completion confirmations)**, dead-code removal — §0 item 10, §3, §8, §10. Prior: 2026-06-26 PR 1 moved the `/complete` gate to `collaboration_completions`)
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

---

## 13. Public reputation aggregates (added 2026-06-30, PR 4)

The `collaboration_reviews` table now carries five category-specific star ratings (`communication_rating`, `reliability_rating`, `fit_rating`, `value_rating`, `repeat_rating`), a `public_comment` (user-authored text), and a `public_comment_visible` boolean gate (default true). The computed accessor `overall_rating` averages the five stars, falling back to the legacy `rating` column if a reviewer has not set the new ratings yet.

`public_comment_visible` gates only the `public_comment` field (not the legacy `body` / `note`). `ProfileService::getReputationSummary()` aggregates these fields into a public-facing reputation block (`average_rating`, `review_count`, and a `breakdown` object with category averages — **`unique_partner_count` was removed in PR 5**), counting only reviews on `status = completed` collaborations. A per-pair fairness cap limits each (reviewer_profile_id, reviewed_profile_id) pair to at most 2 reviews contributing to the aggregate; this is enforced at query time via a SQL window function (`ROW_NUMBER() OVER (PARTITION BY reviewer_profile_id ORDER BY created_at ASC, id ASC)`; `reviewed_profile_id` is fixed by the outer `WHERE`) with no schema change. The `id ASC` tiebreaker keeps the ranking deterministic when two reviews share a `created_at` (uuid7 ids are time-ordered), so the per-pair cap always keeps the genuinely earliest reviews regardless of index/scan order. The block is serialized as the `reputation` key on `PublicProfileResource` (sibling to `recent_reviews`), and is accessible to any authenticated viewer (same public-profile visibility rules apply). Each object in `recent_reviews` includes `is_verified_kolab_review: true` on every review item.

**Caching (#76):** `getReputationSummary()` is cached per profile under `profile:reputation:{id}` (24h backstop TTL) and its `completed_kolabs_count` is the single source for the resource's top-level field too (the previously-duplicated COUNT was removed). The cache is invalidated by model observers — `CollaborationReviewObserver` (any review received created/updated/deleted) and `CollaborationObserver` (a collaboration created / status-changed / deleted, busting both parties) — so the returned values are always fresh regardless of the write path (API, admin moderation, seeders). Values and shape are unchanged; only the read cost is.
