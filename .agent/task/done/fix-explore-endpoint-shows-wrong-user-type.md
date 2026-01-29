# Fix: Explore Endpoint Shows Wrong User Type

## Status
- Created: 2026-01-27
- Started: 2026-01-27
- Completed: 2026-01-27

## Issue Type
- [ ] UI Bug
- [ ] API Contract Mismatch
- [x] Backend Logic Bug
- [ ] Architecture Violation
- [ ] Best Practice Issue

## Affected Area
- [ ] Frontend
- [x] Backend
- [x] API
- [ ] Documentation

## Problem Statement
When a business user hits the explore endpoint (`GET /api/v1/opportunities`), it returns all published opportunities regardless of creator type. Business users see business-created opportunities instead of community-created ones.

## Root Cause
The `OpportunityService::browse()` method had no awareness of the requesting user's type. It returned all published opportunities without filtering by the opposite user type. The `creator_type` filter was optional and client-driven only.

## Proposed Solution
Auto-filter browse results to show only opportunities from the opposite user type:
- Business viewers see community opportunities
- Community viewers see business opportunities
- Explicit `creator_type` query parameter overrides this default behavior

## Implementation Details
1. Added `Profile $viewer` parameter to `OpportunityService::browse()`
2. When no explicit `creator_type` filter is provided, automatically filter to the opposite user type
3. Updated `OpportunityController::index()` to pass authenticated profile
4. Updated existing test and added 2 new tests for the behavior

## Files Affected
- `app/Services/OpportunityService.php` - Added `Profile` param and opposite-type filtering logic
- `app/Http/Controllers/Api/V1/OpportunityController.php` - Pass authenticated profile to service
- `tests/Feature/Api/V1/OpportunityListingTest.php` - Fixed existing test, added 2 new tests

## Assigned Agents
- [x] @backend-developer

## Follow-up Recommendations
- Seed community profiles and community-created opportunities for dev/staging data
- Currently all 4 profiles in DB are business type with 12 business opportunities - no community data exists
