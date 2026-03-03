# Freemium Collaboration Gate

**Date:** 2026-03-03

## Problem

Business profiles without an active Stripe subscription could previously create unlimited opportunities (the old limit of 3 opportunities was opportunity-count based). The new freemium model gates further opportunity creation on collaboration count: once a business has completed at least 1 collaboration, they must subscribe to create more.

## Business Rule

When `POST /api/v1/opportunities` is called by a business profile:

- If the profile has **no active subscription** AND `createdCollaborations().count() >= 1` → return **HTTP 402** with `{ "success": false, "requires_subscription": true, "message": "..." }`
- All collaboration statuses count (scheduled, active, completed, cancelled)
- Community profiles and subscribed business profiles are unaffected

## Chosen Approach

**Approach A — Update existing method in OpportunityService**

Minimal changes to the existing freemium limit pattern:

1. Remove `FREE_OPPORTUNITY_LIMIT = 3` constant
2. Rename `hasReachedFreeLimit()` → `hasReachedFreemiumCollabLimit()`
3. Count `createdCollaborations()` instead of `createdOpportunities()`, threshold = 1
4. Change controller `catch` response status from 403 → 402

## Affected Files

| File | Change |
|------|--------|
| `app/Services/OpportunityService.php` | Remove constant, rename + rewrite `hasReachedFreeLimit()` |
| `app/Http/Controllers/Api/V1/OpportunityController.php` | Return 402 instead of 403 in `store()` catch block |
| `tests/Feature/...OpportunityTest.php` | Update/add freemium gate test cases |

## Test Cases

| Scenario | Expected |
|----------|----------|
| Business, 0 collabs, no subscription | 201 Created |
| Business, 1+ collabs, no subscription | 402 + `requires_subscription: true` |
| Business, 1+ collabs, active subscription | 201 Created |
| Community profile | 201 Created (unaffected) |
