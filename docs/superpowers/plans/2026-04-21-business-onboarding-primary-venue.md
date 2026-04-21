# Business Onboarding Primary Venue Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add backend support for business onboarding primary venues, expose them in business profile responses, add a places autocomplete proxy, and derive `venue_promotion` kolab venue fields from the saved primary venue.

**Architecture:** Persist a normalized `primary_venue` JSON snapshot on `business_profiles`, including uploaded photo URLs. Keep venue snapshot fields on `kolabs`, but derive them from the authenticated business profile during `venue_promotion` create and update flows. Add a small Google Places service and lookup endpoint that returns app-shaped autocomplete suggestions with best-effort `city_id` matching.

**Tech Stack:** Laravel 12, Eloquent models/resources, FormRequest validation, PHPUnit feature tests, existing `FileUploadService`

---

### Task 1: Add failing tests for business registration and onboarding primary venue support

**Files:**
- Modify: `tests/Feature/Api/V1/AuthControllerTest.php`
- Modify: `tests/Feature/Api/V1/OnboardingControllerTest.php`
- Test: `tests/Feature/Api/V1/AuthControllerTest.php`
- Test: `tests/Feature/Api/V1/OnboardingControllerTest.php`

- [ ] **Step 1: Write the failing tests**

Add tests that:
- register a business with `city_name` fallback and nested `primary_venue`
- assert `data.user.business_profile.primary_venue` is present in the registration response
- assert uploaded primary venue photos resolve to stored URLs
- complete onboarding with nested `primary_venue`
- validate required nested venue fields on onboarding

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/Api/V1/AuthControllerTest.php tests/Feature/Api/V1/OnboardingControllerTest.php`
Expected: FAIL with missing validation support and missing `primary_venue` response fields.

- [ ] **Step 3: Write minimal implementation**

Add migration, request validation, service logic, resource serialization, and upload helpers needed for those tests only.

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test tests/Feature/Api/V1/AuthControllerTest.php tests/Feature/Api/V1/OnboardingControllerTest.php`
Expected: PASS

### Task 2: Add failing tests for business profile response and places autocomplete

**Files:**
- Modify: `tests/Feature/Api/V1/ProfileControllerTest.php`
- Create or Modify: `tests/Feature/Api/V1/LookupControllerTest.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Api/V1/ProfileControllerTest.php`
- Test: `tests/Feature/Api/V1/LookupControllerTest.php`

- [ ] **Step 1: Write the failing tests**

Add tests that:
- assert `GET /api/v1/me/profile` includes `business_profile.primary_venue`
- assert `GET /api/v1/places/autocomplete?query=...` returns app-shaped place suggestions
- assert city matching returns `city_id` when the city exists locally

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/Api/V1/ProfileControllerTest.php tests/Feature/Api/V1/LookupControllerTest.php`
Expected: FAIL because `primary_venue` is absent and the endpoint does not exist.

- [ ] **Step 3: Write minimal implementation**

Add resource changes, route/controller method, a Google Places service abstraction, and city matching logic required for the new endpoint.

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test tests/Feature/Api/V1/ProfileControllerTest.php tests/Feature/Api/V1/LookupControllerTest.php`
Expected: PASS

### Task 3: Add failing tests for venue promotion kolab derivation from primary venue

**Files:**
- Modify: `tests/Feature/Api/V1/KolabCreateTest.php`
- Modify: `app/Http/Requests/Api/V1/CreateKolabRequest.php`
- Modify: `app/Services/KolabService.php`
- Test: `tests/Feature/Api/V1/KolabCreateTest.php`

- [ ] **Step 1: Write the failing tests**

Add tests that:
- create a `venue_promotion` kolab without direct venue fields and assert venue fields are derived from the business profile `primary_venue`
- require at least one `media` item for `venue_promotion`
- reject `venue_promotion` creation when the business has no `primary_venue`

- [ ] **Step 2: Run tests to verify they fail**

Run: `php artisan test tests/Feature/Api/V1/KolabCreateTest.php`
Expected: FAIL because request validation still requires direct venue fields and the service does not derive venue data.

- [ ] **Step 3: Write minimal implementation**

Adjust validation rules and add server-side enrichment from `business_profiles.primary_venue` into kolab venue columns.

- [ ] **Step 4: Run tests to verify they pass**

Run: `php artisan test tests/Feature/Api/V1/KolabCreateTest.php`
Expected: PASS

### Task 4: Final verification

**Files:**
- Review: `app/Http/Requests/Api/V1/RegisterBusinessRequest.php`
- Review: `app/Http/Requests/Api/V1/BusinessOnboardingRequest.php`
- Review: `app/Http/Resources/Api/V1/BusinessProfileResource.php`
- Review: `app/Services/AuthService.php`
- Review: `app/Services/OnboardingService.php`
- Review: `app/Services/KolabService.php`
- Review: `app/Http/Controllers/Api/V1/LookupController.php`

- [ ] **Step 1: Run focused regression tests**

Run: `php artisan test tests/Feature/Api/V1/AuthControllerTest.php tests/Feature/Api/V1/OnboardingControllerTest.php tests/Feature/Api/V1/ProfileControllerTest.php tests/Feature/Api/V1/KolabCreateTest.php tests/Feature/Api/V1/LookupControllerTest.php`
Expected: PASS

- [ ] **Step 2: Run formatter if needed**

Run: `./vendor/bin/pint --dirty`
Expected: PASS with no style violations.

- [ ] **Step 3: Re-run the focused regression tests**

Run: `php artisan test tests/Feature/Api/V1/AuthControllerTest.php tests/Feature/Api/V1/OnboardingControllerTest.php tests/Feature/Api/V1/ProfileControllerTest.php tests/Feature/Api/V1/KolabCreateTest.php tests/Feature/Api/V1/LookupControllerTest.php`
Expected: PASS
