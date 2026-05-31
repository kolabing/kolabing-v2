# Kolabing — Roles → Backend → Database Map (Ground-Truth)

**Last updated:** 2026-06-01
**Status:** Authoritative companion to [`ROLES-AND-PERMISSIONS.md`](./ROLES-AND-PERMISSIONS.md). Read that first (the *what*), then this (the *where*).
**Sync note:** Duplicated in both repos (`kolabing-app`, `kolabing-v2`). Keep identical, and **bump the Last updated date in both** when role behaviour or backend wiring changes.

> This document maps each role rule from `ROLES-AND-PERMISSIONS.md` to the exact backend code and database tables/columns that implement it, and flags every place the current code mis-handles roles. Cite this map (file:line, table.column) before changing anything that touches Explore, profiles, the paywall, permissions, onboarding, or create/apply. Items marked **[VERIFY]** still need a live confirmation.

---

## 0. TL;DR — the role-confusion hot spots

> Fixed-since-last-update marked ✅; still-open marked ⚠️.

1. ⚠️ **Two parallel post systems exist** (`collab_opportunities` *and* `kolabs`). They share a `id` UUID via `LegacyOpportunityBridgeService` so applications and collaborations (which FK to `collab_opportunities.id`) still resolve when a `kolab` is the actual post. The duplication is still the single biggest source of role bugs. See §2.
2. ✅ **`KolabService::publish` now has the `isBusiness()` guard** — `app/Services/KolabService.php:190`. A community publishing a non-`CommunitySeeking` kolab no longer hits the freemium gate.
3. ⚠️ **The blur still does not exist.** `app/Http/Resources/Api/V1/DiscoveryOpportunityResource.php` returns full `creator_profile.display_name` + `avatar_url` to every viewer; no `identity_locked` / `hide_creator_identity` flag is emitted. Violates golden rules 4 & 5. See §4.
4. ✅ **Account deletion now frees the email, closes posts on both systems, cancels active collaborations, and revokes tokens.** `ProfileService::deleteProfile()` (`app/Services/ProfileService.php:111`) renames the soft-deleted profile's email to `deleted+{id}@kolabing.invalid` so the unique index releases the original address. See §6.
5. ✅ **Profile logos serialize as absolute URLs from the correct column.** `PublicProfileResource::absoluteUrl()` resolves the URL from the extended profile's `profile_photo` first, falling back to `profiles.avatar_url`. See §5.
6. ⚠️ **NEW — attendee gamification track has shipped** but the canonical permissions doc still describes attendees as "deferred / out of scope". `AttendeeProfile`, `Wallet`, `EarnedBadge`, `EventCheckin`, `ChallengeCompletion`, and ~40 gamification endpoints are live. See §11.
7. ⚠️ **NEW — `coliving` is in the canonical role spec (`ROLES-AND-PERMISSIONS.md` §2.1) but missing from `BusinessOnboardingRequest::BUSINESS_TYPES`.** A `coliving` onboarding payload is rejected server-side. Trivial fix; see §8 checklist.
8. ⚠️ **NEW — admin operator surfaces.** Maintainers can grant a 12-month subscription with `source = maintainer` and force-cancel collaborations from `/admin/*`. Make sure new gate code accounts for `source = maintainer` (still an `active` row; behaves identically to a Stripe-paid sub). See §9.

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
| **A. Opportunities** | `collab_opportunities` | `CollabOpportunity` | `OpportunityController` → `OpportunityService` | **Legacy** screens (`create_opportunity_screen` community, `create_collab_request_screen` business) via `opportunityFormProvider` | `isBusiness()`-guarded ✅ |
| **B. Kolabs** | `kolabs` | `Kolab` | `KolabController` → `KolabService` | **New** `/kolab/flow` via `kolab_form_provider` | intent-type only — **no `isBusiness()` guard** ⚠️ |
| **C. Collaborations** | `collaborations` | `Collaboration` | created when an application is accepted | — | none (lifecycle only) |
| Applications | `applications` | `Application` | `ApplicationController` | apply modal | — |

- `collab_opportunities.creator_profile_type` (enum Business|Community) encodes authorship in System A.
- `kolabs.intent_type` (CommunitySeeking | VenuePromotion | ProductPromotion) encodes authorship in System B: **CommunitySeeking = community-authored; Venue/ProductPromotion = business-authored.**
- **The bridge — partially resolved.** `App\Services\LegacyOpportunityBridgeService::resolveOrFail($id, $persistFromKolab)` mints a compatibility `collab_opportunities` row **with the same UUID as the kolab** when an application is filed against a kolab id. This is why `applications.collab_opportunity_id == kolab.id` works downstream. So both systems coexist at runtime; **the canonical authoring source is `kolabs`**, and System A is now effectively a denormalized projection used for the apply / collaboration FK chain.
- **Practical implication:** when in doubt about "which model is the post?" → it's the **Kolab**. When you need to query applications/collaborations, you can do so by `kolab.id` directly because the bridge ensures the row exists. Eliminating System A entirely is the long-running cleanup; until then, do not change the bridge invariant (kolab.id == collab_opportunity.id).

---

## 3. Paywall enforcement — every gate, classified

Spec: paywall is **Business-only**, on **exactly two actions** (create a collaboration, apply to a Kolab). Communities are never gated.

| Action | Code | Gates whom | Verdict |
|---|---|---|---|
| Create opportunity (System A) | `OpportunityService::hasReachedFreemiumCollabLimit()`; early-out `if (!$creator->isBusiness()) return false;` | Business w/o sub, >1 collab | ✅ correct |
| Publish opportunity (System A) | `OpportunityService::publish()` `if ($creator->isBusiness() && !$creator->hasActiveSubscription())` | Business only | ✅ correct |
| Create kolab (System B) | `KolabService::create()` — no sub check | nobody | ✅ correct |
| Publish kolab (System B) | `KolabService::publish()` `:190` — `$creator->isBusiness() && intent_type !== CommunitySeeking && !hasActiveSubscription() && hasUsedFreeKolab()` | Business only | ✅ correct (fixed since 2026-05-22) |
| Accept application | `ApplicationService::accept()` `:86` — `$opportunity->creatorProfile->isBusiness() && ! hasActiveSubscription()` throws `RuntimeException('An active subscription is required to accept applications.')` | Business creator without sub | ✅ correct |
| Apply to a post | `ApplicationController::store()` `:78` → catches `SubscriptionRequiredException` and returns HTTP **402** so the client renders the paywall. The community side throws no such exception. | Business applier without sub | ✅ correct |
| Client: create entry (intent) | `intent_selection_screen.dart` shows the paywall for unsubscribed business; communities only see the FREE CommunitySeeking option | Business only | ✅ correct |

All four backend gates now follow the same pattern: `if ($profile->isBusiness() && ! $profile->hasActiveSubscription())`. **Never copy this gate into community or attendee paths.**

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
 │                              community_deliverables json, venue_mode, preferred_city)         ← System A
 ├─1:N─ kolabs                 (creator_profile_id FK, intent_type[community_seeking|venue_promotion|
 │                              product_promotion], status[draft|published|closed], media json,
 │                              needs json, community_types json, venue_preference, offering json,
 │                              published_at)                                                    ← System B (canonical)
 ├─1:N─ events                 (profile_id FK, partner_id FK, event_date, attendee_count)        ← past events
 └─1:N─ applications           (applicant_profile_id FK, applicant_profile_type[business|community],
                                collab_opportunity_id FK, status[pending|accepted|declined|withdrawn],
                                accepted_at?, declined_at?, withdrawn_at?)                       ← (§10)
            └─1:1─ collaborations (application_id FK UNIQUE, collab_opportunity_id FK,
                                   creator_profile_id, applicant_profile_id,
                                   business_profile_id nullOnDelete, community_profile_id nullOnDelete,
                                   status[scheduled|active|completed|cancelled],
                                   scheduled_date, activated_at?, completed_at?,
                                   cancelled_at?, cancellation_reason?, cancelled_by_profile_id? nullOnDelete,
                                   event_id, qr_code_url)                                        ← (§10)
                       ├─1:N─ collaboration_reviews (collaboration_id FK, reviewer_profile_id,
                       │                              reviewed_profile_id, reviewer_role, rating,
                       │                              body, note, would_collaborate_again)
                       └─1:N─ chat_messages         (application_id FK, sender_profile_id, …)

Gamification / wallets (§11):
 ├─ wallets, withdrawal_requests, point_ledger
 ├─ badges, badge_awards, earned_badges
 ├─ event_checkins, event_photos, event_rewards
 └─ collaboration_challenges, challenge_completions, referral_codes, referral_redemptions

Lookups / admin:
 ├─ cities, city_suggestions
 └─ users (admin/maintainer auth — separate from profiles, see §9)
```

**Role lives in `profiles.user_type`. Subscription lives in `business_subscriptions.status` (with `source` as audit trail).** Everything else branches off those two.

---

## 8. Mistakes-to-fix checklist (role-handling)

Fixed since the last revision:

- [x] `KolabService::publish` gated by `isBusiness()` (`:190`). (§3)
- [x] Account deletion frees the email + closes posts on both systems + cancels active collaborations. (§6)
- [x] Profile logo returns an absolute URL via `PublicProfileResource::absoluteUrl()` from the correct column. (§5)
- [x] Collaboration cancellation now persists `cancellation_reason`, `cancelled_at`, and `cancelled_by_profile_id` (§10).

Still open:

- [ ] Implement the **blur** (name + logo) for free businesses on Explore. Server should emit an `identity_locked` flag (or null the identity for free businesses) and the client should render an actual blur. **No hard block on Explore.** (§4)
- [ ] Past events linked to collaborations and `completed_at` populated.
- [ ] "View profile" deep-link confirmed to pass a `profiles.id` (not a `business_profile.id` or `collaboration.id`).
- [ ] Type tags formatted on exactly one side (discovery formats server-side; profiles format client-side — pick one).
- [ ] **Reconcile the two post systems** — bridge is in place via `LegacyOpportunityBridgeService`, but the long-running cleanup is to delete System A entirely. (§2)
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

Backfill for legacy rows: `php artisan app:backfill-lifecycle-timestamps [--dry-run]` copies `updated_at` into the matching transition column. Run once per environment after deploy.

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
