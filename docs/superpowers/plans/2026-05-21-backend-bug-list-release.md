# Backend Bug List Release Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Close the backend issues listed in `backend-tickets-from-bug-list-2026-05-21.md`, ship the missing API behavior, and produce a release-ready backend state.

**Architecture:** Extend the existing Laravel API in place. Keep fixes grouped by bounded areas: auth/publish blockers, application/collaboration flow, discovery/matching, and public profile/contracts. Prefer incremental migrations and service-layer changes over broad refactors, while centralizing new matching rules in a dedicated taxonomy/matching layer.

**Tech Stack:** Laravel 12, Sanctum, Eloquent, PHPUnit feature tests, JSON API resources, MySQL/SQLite test database.

---

### Task 1: Audit Current Partial Worktree Changes

**Files:**
- Inspect: `app/Http/Requests/Api/V1/AcceptApplicationRequest.php`
- Inspect: `app/Services/ApplicationService.php`
- Inspect: `app/Policies/CollaborationPolicy.php`
- Inspect: `app/Http/Resources/Api/V1/CollaborationResource.php`
- Inspect: `tests/Feature/Api/V1/ApplicationAcceptTest.php`
- Inspect: `tests/Feature/Api/V1/CollaborationDetailTest.php`

- [ ] Confirm the existing dirty worktree changes align with BE-005, BE-006, and BE-008.
- [ ] Preserve those changes and avoid reverting unrelated user edits.
- [ ] Fold any additional implementation into the same files only when it directly completes the ticket scope.

### Task 2: Fix Publish Contract and Kolab Media Validation

**Files:**
- Modify: `app/Http/Requests/Api/V1/CreateKolabRequest.php`
- Modify: `app/Http/Requests/Api/V1/UpdateKolabRequest.php`
- Modify: `app/Services/KolabService.php`
- Modify: `app/Http/Controllers/Api/V1/KolabController.php`
- Modify: `app/Http/Resources/Api/V1/KolabResource.php`
- Test: `tests/Feature/Api/V1/KolabCreateTest.php`
- Test: `tests/Feature/Api/V1/KolabPublishCloseTest.php`

- [ ] Add failing tests for `media[*].type = image|video`, and reject legacy `photo`.
- [ ] Add failing tests for unsubscribed publish returning a backend-owned paywall signal (`402` + `requires_subscription`).
- [ ] Implement minimal request validation and publish error handling to satisfy those tests.
- [ ] Re-run the focused Kolab feature tests.

### Task 3: Harden Business Registration and Auth Refresh Diagnostics

**Files:**
- Modify: `app/Http/Requests/Api/V1/RegisterBusinessRequest.php`
- Modify: `app/Http/Controllers/Api/V1/AuthController.php`
- Modify: `app/Services/AuthService.php`
- Create/Modify: logging support in `app/Http/Middleware` or `app/Providers`
- Test: `tests/Feature/Api/V1/AuthControllerTest.php`
- Test: `tests/Feature/Api/V1/UploadControllerTest.php`

- [ ] Add failing tests that prove `register/business` returns nested field errors for invalid venue/media input.
- [ ] Add failing tests that prove a freshly issued login token can hit a protected endpoint immediately and that refresh works without a consistency gap.
- [ ] Implement structured debug logging for `register/*`, `publish`, token issue, first protected use, refresh attempts, and refresh outcomes.
- [ ] Re-run focused auth and upload tests.

### Task 4: Finish Accept/Complete Collaboration Flow

**Files:**
- Modify: `app/Http/Requests/Api/V1/AcceptApplicationRequest.php`
- Modify: `app/Services/ApplicationService.php`
- Modify: `app/Models/Collaboration.php`
- Modify: `app/Policies/CollaborationPolicy.php`
- Modify: `app/Services/CollaborationService.php`
- Modify: `app/Http/Resources/Api/V1/CollaborationResource.php`
- Test: `tests/Feature/Api/V1/ApplicationAcceptTest.php`
- Test: `tests/Feature/Api/V1/CollaborationDetailTest.php`
- Test: `tests/Feature/Api/V1/GamificationCollaborationIntegrationTest.php`

- [ ] Keep the in-progress BE-005 and BE-006 changes, and verify them red/green with tests.
- [ ] Add failing tests for collaboration completion from every allowed status and only once.
- [ ] Update model/service/policy behavior so completion rules and action visibility match the final API contract.
- [ ] Re-run focused collaboration tests.

### Task 5: Add Community Public Profile Endpoint

**Files:**
- Modify: `routes/api.php`
- Create/Modify: `app/Http/Controllers/Api/V1/CommunityPublicProfileController.php` or extend `ProfileController.php`
- Create/Modify: `app/Http/Resources/Api/V1/CommunityPublicProfileResource.php`
- Modify: `app/Services/ProfileService.php`
- Test: `tests/Feature/Api/V1/PublicProfileTest.php`

- [ ] Add failing tests for `GET /api/v1/communities/{id}/public-profile`.
- [ ] Implement a response that is safe for external viewers and includes stats/portfolio fields required by the frontend.
- [ ] Re-run focused public profile tests.

### Task 6: Extend Kolab Schema for Offer Model and Headline

**Files:**
- Create: `database/migrations/2026_05_21_*.php`
- Modify: `app/Models/Kolab.php`
- Modify: `app/Services/KolabService.php`
- Modify: `app/Http/Requests/Api/V1/CreateKolabRequest.php`
- Modify: `app/Http/Requests/Api/V1/UpdateKolabRequest.php`
- Modify: `app/Http/Resources/Api/V1/KolabResource.php`
- Modify: `app/Http/Resources/Api/V1/DiscoveryOpportunityResource.php`
- Test: `tests/Feature/Api/V1/KolabCreateTest.php`
- Test: `tests/Feature/Api/V1/KolabCrudTest.php`
- Test: `tests/Feature/Api/V1/DiscoveryOpportunityControllerTest.php`

- [ ] Add failing tests for `offer_headline`, `base_offer`, and `negotiation_triggers`.
- [ ] Add the migration and model/resource plumbing with backfill-safe defaults.
- [ ] Keep public discovery/list payloads limited to public offer data and reserve negotiation details for private/detail contexts.
- [ ] Re-run focused Kolab and discovery tests.

### Task 7: Rework Discovery Match Scoring and Breakdown

**Files:**
- Create/Modify: matching/taxonomy support under `app/Services`
- Modify: `app/Services/DiscoveryOpportunityService.php`
- Modify: `app/Http/Resources/Api/V1/DiscoveryOpportunityResource.php`
- Modify: `app/Http/Controllers/Api/V1/LookupController.php`
- Modify: any shared taxonomy constants used by onboarding/lookup validation
- Test: `tests/Feature/Api/V1/DiscoveryOpportunityControllerTest.php`

- [ ] Add failing tests for `match_breakdown` shape and score math.
- [ ] Add the food-community regression test asserting cafe outranks coworking.
- [ ] Centralize category/taxonomy logic enough that discovery, lookup, and scoring share the same normalized vocabulary.
- [ ] Rebalance the score weights so category fit is first-impression dominant while keeping location/audience/freshness visible in the breakdown.
- [ ] Re-run the full discovery test file.

### Task 8: Update API Docs and Release Verification

**Files:**
- Modify: `docs/MOBILE_API_DOCUMENTATION.md`
- Modify: `docs/MOBILE_APP_INTEGRATION_GUIDE.md`
- Modify/create: release notes file if needed

- [ ] Document the contract changes from BE-015.
- [ ] Run the relevant focused feature suites, then `composer test` if green.
- [ ] Summarize any residual risk before cutting the release.
