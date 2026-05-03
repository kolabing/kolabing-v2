# Fix: remove-onboarding-completed-flag

## Status
- Created: 2026-05-03 16:40
- Started: 2026-05-03 16:40
- Completed: 2026-05-03 17:05

## Issue Type
- [x] Architecture Violation (dead-flag / redundant gate)
- [x] Best Practice Issue

## Affected Area
- [x] Backend
- [x] API
- [x] Documentation (PHPDoc)

## Problem Statement
Every user is required to complete onboarding immediately after registration; the `onboarding_completed` boolean is therefore redundant. It exists in two places that need to go:
1. As a gate inside `ApplicationService` and `ApplicationPolicy` that re-checks something the signup flow already enforces.
2. As a computed accessor on `Profile` exposed in `UserResource` / `ProfileResource`.

## Root Cause
Historical: `getOnboardingCompletedAttribute()` was added so the API could tell clients "this user hasn't finished onboarding yet". Now onboarding is mandatory by product flow, so the field is dead weight, and the gate checks duplicate logic the flow already enforces.

## Proposed Solution (Option B — full removal)
1. Drop `Profile::getOnboardingCompletedAttribute()` and its `@property-read bool $onboarding_completed` PHPDoc.
2. Remove the gate in `ApplicationService::validateApplication` (lines around 270–275).
3. Remove the gate in `ApplicationPolicy::create` (lines around 49–52).
4. Remove the field from `UserResource` and `ProfileResource`.
5. Remove every `onboarding_completed` reference from the four affected test files (assertions + `assertJsonStructure` keys).

## Implementation Details
- `app/Models/Profile.php`: removed `getOnboardingCompletedAttribute()` accessor and the `@property-read bool $onboarding_completed` PHPDoc tag.
- `app/Policies/ApplicationPolicy.php`: removed the `onboarding_completed` gate from `create()` (and updated the doc comment).
- `app/Services/ApplicationService.php`: removed the onboarding gate (and its message) from `validateApplication()`.
- `app/Http/Resources/Api/V1/UserResource.php` and `app/Http/Resources/Api/V1/ProfileResource.php`: removed the `onboarding_completed` field from API output.
- Tests: removed every `onboarding_completed` reference (assertions + `assertJsonStructure` keys) from `AuthControllerTest`, `AppleAuthControllerTest`, `OnboardingControllerTest`, and `ProfileControllerTest`.

## Validation
- `vendor/bin/pint --dirty` → 9 files clean (2 style fixes applied automatically).
- `php artisan test --compact` (full suite) → 615 passed (3109 assertions), no regressions.
- Targeted re-run of all tests touched in this change set (Auth, AppleAuth, Onboarding, Profile, KolabPublishClose, ApplicationAccept) → 92 passed (685 assertions).
- Final grep across `app/`, `tests/`, `database/`, `routes/`, `config/`: zero remaining references to `onboarding_completed` or `getOnboardingCompletedAttribute`.

## Files Affected
- `app/Models/Profile.php`
- `app/Services/ApplicationService.php`
- `app/Policies/ApplicationPolicy.php`
- `app/Http/Resources/Api/V1/UserResource.php`
- `app/Http/Resources/Api/V1/ProfileResource.php`
- `tests/Feature/Api/V1/AuthControllerTest.php`
- `tests/Feature/Api/V1/OnboardingControllerTest.php`
- `tests/Feature/Api/V1/ProfileControllerTest.php`
- `tests/Feature/Api/V1/AppleAuthControllerTest.php`

## Assigned Agents
- [x] @backend-developer

## Follow-up Recommendations
- Once Flutter has shipped a release that no longer reads `onboarding_completed`, no further follow-up. If Flutter still relies on the field, either roll back to Option A or have the Flutter app derive the state from the profile's `name`/`city_id`/social fields directly.
