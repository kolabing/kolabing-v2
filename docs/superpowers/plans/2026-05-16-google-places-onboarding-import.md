# Google Places Onboarding Import Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a backend Google Places details import flow for business onboarding, including previewable Google photos and final-save photo rehosting.

**Architecture:** Add a public import endpoint and a public photo proxy endpoint in the lookup controller. Extend the existing Google Places service to fetch Place Details, resolve preview photo URLs, and rehost selected Google photo resource names during business onboarding save. Keep the existing onboarding endpoint as the single persistence point.

**Tech Stack:** Laravel 12, FormRequest validation, HTTP client, existing `FileUploadService`, PHPUnit feature tests

---

### Task 1: Add failing tests for the import and photo proxy endpoints

**Files:**
- Modify: `tests/Feature/Api/V1/LookupControllerTest.php`
- Modify: `routes/api.php`
- Modify: `app/Http/Controllers/Api/V1/LookupController.php`
- Modify: `app/Services/GooglePlacesService.php`

- [ ] **Step 1: Write the failing tests**

Add tests that:
- assert `GET /api/v1/places/details?place_id=...` returns app-shaped onboarding prefill data
- assert imported photos include `resource_name`, `preview_url`, and attributions
- assert `GET /api/v1/places/photo?name=...` redirects to the temporary Google `photoUri`
- assert details import returns `503` with the manual fallback message when Google data cannot be fetched

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Api/V1/LookupControllerTest.php`
Expected: FAIL because the details import and photo proxy endpoints do not exist yet.

- [ ] **Step 3: Write minimal implementation**

Add routes, controller methods, Google Places details mapping, and Google photo proxy support required for those tests only.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/Api/V1/LookupControllerTest.php`
Expected: PASS

### Task 2: Add failing tests for final-save Google photo rehosting and imported metadata persistence

**Files:**
- Modify: `tests/Feature/Api/V1/OnboardingControllerTest.php`
- Modify: `app/Http/Requests/Api/V1/BusinessOnboardingRequest.php`
- Modify: `app/Services/OnboardingService.php`
- Modify: `app/Services/BusinessVenueService.php`

- [ ] **Step 1: Write the failing tests**

Add tests that:
- submit `PUT /api/v1/onboarding/business` with `primary_venue.photos` containing Google photo resource names in a chosen order
- assert the save rehosts those photos to Kolabing storage and preserves the submitted order
- assert imported Google metadata fields are persisted on `primary_venue`
- assert mixed Google-photo and base64-photo inputs still save successfully

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test tests/Feature/Api/V1/OnboardingControllerTest.php`
Expected: FAIL because Google photo resource names are not currently recognized or rehosted.

- [ ] **Step 3: Write minimal implementation**

Extend request validation and venue normalization to recognize Google photo resource names, fetch temporary photo media URLs server-side, and store uploaded copies using the existing upload service.

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test tests/Feature/Api/V1/OnboardingControllerTest.php`
Expected: PASS

### Task 3: Final verification and handoff docs

**Files:**
- Review: `app/Services/GooglePlacesService.php`
- Review: `app/Services/BusinessVenueService.php`
- Review: `app/Http/Controllers/Api/V1/LookupController.php`
- Review: `app/Http/Requests/Api/V1/BusinessOnboardingRequest.php`
- Create: `docs/mobile/google-places-onboarding-import.md`

- [ ] **Step 1: Run focused regression tests**

Run: `php artisan test tests/Feature/Api/V1/LookupControllerTest.php tests/Feature/Api/V1/OnboardingControllerTest.php`
Expected: PASS

- [ ] **Step 2: Run formatter**

Run: `./vendor/bin/pint --dirty`
Expected: PASS

- [ ] **Step 3: Re-run focused regression tests**

Run: `php artisan test tests/Feature/Api/V1/LookupControllerTest.php tests/Feature/Api/V1/OnboardingControllerTest.php`
Expected: PASS

- [ ] **Step 4: Write mobile integration documentation**

Document:
- import endpoint usage
- preview photo rendering
- final save payload shape
- fallback behavior
- attribution requirements
