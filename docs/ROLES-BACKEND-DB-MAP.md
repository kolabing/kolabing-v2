# Kolabing — Roles → Backend → Database Map (Ground-Truth)

**Last updated:** 2026-05-22
**Status:** Authoritative companion to [`ROLES-AND-PERMISSIONS.md`](./ROLES-AND-PERMISSIONS.md). Read that first (the *what*), then this (the *where*).
**Sync note:** Duplicated in both repos (`kolabing-app`, `kolabing-v2`). Keep identical.

> This document maps each role rule from `ROLES-AND-PERMISSIONS.md` to the exact backend code and database tables/columns that implement it, and flags every place the current code mis-handles roles. Cite this map (file:line, table.column) before changing anything that touches Explore, profiles, the paywall, permissions, onboarding, or create/apply. Items marked **[VERIFY]** still need a live confirmation.

---

## 0. TL;DR — the role-confusion hot spots

1. **Two parallel post systems exist** (`collab_opportunities` *and* `kolabs`). They are reached by two different client flows and gated by two different services with **different paywall logic**. This duplication is the single biggest source of role bugs. See §2.
2. **`KolabService::publish` has no `isBusiness()` guard** (`app/Services/KolabService.php:171`). A community is protected only by intent type, not by role. This is the latent "community blocked from creating" path. See §3.
3. **The blur does not exist.** Server returns full community identity to everyone; the client computes a `hideCreatorIdentity` flag but never renders a blur. Free businesses are instead hard-blocked. Violates golden rules 4 & 5. See §4.
4. **Account deletion is soft-delete only** — email is never freed and related collaborations/opportunities are orphaned (DB cascades don't fire on soft delete). See §6.
5. **Profile logo lives in two columns** (`profiles.avatar_url` vs `{business,community}_profiles.profile_photo`) and may be returned as a non-absolute URL → logos don't load. See §5.

---

## 1. Role identity & subscription (where the role lives)

| Concept | Table.Column | Notes |
|---|---|---|
| **User role** | `profiles.user_type` (varchar 20) | `Business` / `Community` / `Attendee`. Read via `Profile::isBusiness()` (`app/Models/Profile.php:355`), `isCommunity()` (`:363`). |
| **Subscription state** | `business_subscriptions.status` (varchar) | `inactive`/`active`/`trial`/`cancelled`; 1:1 to `profiles` via `profile_id` (unique). |
| **Active-sub check** | `Profile::hasActiveSubscription()` (`:380`) | Correctly returns `false` for any non-business (`if (!$this->isBusiness()) return false;`). ✅ |
| **Free-kolab-used check** | `Profile::hasUsedFreeKolab()` (`:400`) | True if the profile has a **published** kolab with `intent_type` in (VenuePromotion, ProductPromotion). Role-agnostic — see §3. |

Role helpers are consistent; the bugs are in *where they are (not) applied*, not in the helpers themselves.

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
- **[VERIFY] Which system is canonical for the live marketplace?** Discovery (§4) queries `kolabs`. But `applications.collab_opportunity_id` and `collaborations.collab_opportunity_id` FK to `collab_opportunities` (System A), not `kolabs`. Confirm how an applied-to `kolab` becomes an `application`/`collaboration`, or whether one system is dead. **This reconciliation is sprint task 0** — most "wrong role / wrong data" bugs trace here.

---

## 3. Paywall enforcement — every gate, classified

Spec: paywall is **Business-only**, on **exactly two actions** (create a collaboration, apply to a Kolab). Communities are never gated.

| Action | Code | Gates whom | Verdict |
|---|---|---|---|
| Create opportunity (System A) | `OpportunityService::hasReachedFreemiumCollabLimit()` `:144`; early-out `if (!$creator->isBusiness()) return false;` `:146` | Business w/o sub, >1 collab | ✅ correct |
| Publish opportunity (System A) | `OpportunityService::publish()` `:275` `if ($creator->isBusiness() && !$creator->hasActiveSubscription())` | Business only | ✅ correct |
| Create kolab (System B) | `KolabService::create()` `:74` — no sub check | nobody | ✅ correct |
| **Publish kolab (System B)** | `KolabService::publish()` **`:171`** `intent_type !== CommunitySeeking && !hasActiveSubscription() && hasUsedFreeKolab()` | **role-agnostic** | ⚠️ **VIOLATION RISK** — no `isBusiness()` guard. A community publishing a non-CommunitySeeking kolab (e.g. via any path that sets Venue/ProductPromotion) hits this. Add `&& $creator->isBusiness()`. |
| Apply to a post | `ApplicationController` | **[VERIFY]** confirm a free business is gated and a community is **never** gated | [VERIFY] |
| Client: create entry (intent) | `intent_selection_screen.dart:53` `businessRequiresSubscription = isBusiness && !isSubscribed` → `_LockedBusinessCreateState` (`:82`) | Business only | ✅ role-correct, but it's a **full-screen hard block** (acceptable for the *create* action per spec; do not copy this pattern to Explore) |

**Community-blocked-from-creating ("Create opportunity still blocked for communities"):** the new-master `intent_selection_screen` correctly offers communities only the FREE communitySeeking option, so the obvious client path is clean. Prime suspects, in order:
1. **`KolabService::publish:171`** missing `isBusiness()` guard (fix regardless).
2. **Profile-type resolution**: `intent_selection_screen.dart:51-52` compares `userType?.name == 'community'` / `'business'` (lowercase). If the profile API returns `user_type` capitalized (`Community`) or the client enum casing differs, **both** flags go false → a community falls into the business `else` branch / mis-gates. **[VERIFY]** the exact `user_type` casing the API returns vs the client enum.
3. The legacy `create_opportunity_screen` path (System A) — confirmed `isBusiness`-guarded server-side, so unlikely.

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

## 6. Account deletion & data integrity (critical)

- **Endpoint:** `ProfileController::destroy()` (`:137`) → `ProfileService::deleteProfile()` (`app/Services/ProfileService.php:89`): revokes tokens + `$profile->delete()` only.
- **Soft delete:** `Profile` uses `SoftDeletes` (`app/Models/Profile.php:64`); `deleted_at` added by `2026_01_26_000002_add_deleted_at_to_profiles_table.php`. So:
  - **Email never freed:** `profiles.email` has a `unique()` index (`2026_01_24_000002_create_profiles_table.php:15`); the soft-deleted row keeps the email → re-registration fails.
  - **Orphans:** FKs (`collaborations`, `collab_opportunities`, `applications`, `chat_messages`) are `cascadeOnDelete`, but **DB cascade only fires on a hard delete**. Soft delete leaves all of them live, pointing at a dead profile.
- **Fix direction:** on delete, within a transaction — close/cancel live collaborations & opportunities and soft/force-delete dependent rows explicitly; and free the email (either hard-delete, or null/rename the email e.g. `deleted+{id}@…`, or make the unique index `deleted_at`-aware). Decide soft vs hard with Daniel (audit/retention vs reuse).

---

## 7. Database schema map (roles & marketplace core)

```
profiles (id uuid PK, email UNIQUE, user_type[Business|Community|Attendee], avatar_url, deleted_at?)
 ├─1:1─ business_profiles  (profile_id FK, company_name, business_type slug, profile_photo)
 ├─1:1─ community_profiles (profile_id FK, community_name, community_type slug, member_count, profile_photo)
 ├─1:1─ attendee_profiles  (out of scope)
 ├─1:1─ business_subscriptions (profile_id FK UNIQUE, status[inactive|active|trial|cancelled], plan_type, renews_at)
 ├─1:N─ collab_opportunities (creator_profile_id FK, creator_profile_type[Business|Community], status, business_offer, community_deliverables, venue_mode, address, preferred_city, offer_photo)   ← System A
 ├─1:N─ kolabs (creator_profile_id FK, intent_type[CommunitySeeking|VenuePromotion|ProductPromotion], status, media json, needs json, community_types json, venue_preference, offering json)        ← System B (discovery source)
 ├─1:N─ events (profile_id FK, partner_id FK, event_date, attendee_count)   ← past events
 └─1:N─ applications (applicant_profile_id FK, collab_opportunity_id FK, status[pending|accepted|rejected])
            └─1:1─ collaborations (application_id FK UNIQUE, collab_opportunity_id FK, creator_profile_id, applicant_profile_id, business_profile_id nullOnDelete, community_profile_id nullOnDelete, status[scheduled|active|completed|cancelled], scheduled_date, completed_at, event_id)
                       └─1:N─ collaboration_reviews / chat_messages (application_id FK)
lookups: business_types, community_types
```

**Role lives in `profiles.user_type`. Subscription lives in `business_subscriptions.status`.** Everything else branches off those two.

---

## 8. Mistakes-to-fix checklist (role-handling)

- [ ] `KolabService::publish` add `&& $creator->isBusiness()` to the gate (§3).
- [ ] Confirm/repro the community create-block; fix profile `user_type` casing mismatch if present (§3).
- [ ] Implement the **blur** (name+logo) for free businesses on Explore; remove the hard-block (§4).
- [ ] Account deletion: free the email + clean up collaborations/opportunities (§6).
- [ ] Profile logo: return absolute URL from the correct column (§5).
- [ ] Past events linked to collaborations + `completed_at` populated (§5).
- [ ] "View profile" passes a `profiles.id` (§5).
- [ ] Type tags formatted on exactly one side (§4/§5).
- [ ] **Reconcile the two post systems** (`collab_opportunities` vs `kolabs`) — sprint task 0 (§2).
- [ ] Never paywall communities anywhere; never remove either role's ability to post/apply (golden rules).
