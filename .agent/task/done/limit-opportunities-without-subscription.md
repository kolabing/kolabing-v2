# Task: Limit Opportunities Without Subscription

## Status
- Created: 2026-01-30 12:00
- Started: 2026-01-30 12:00
- Completed: 2026-01-30 12:15

## Description
Business users without an active subscription can only create up to 3 collaboration opportunities. After reaching the limit, they should receive a response indicating they need to subscribe. This enforces the subscription paywall while allowing new businesses to try the platform.

### Requirements
- Business users without active subscription: max 3 opportunities (any status)
- Business users with active subscription: unlimited opportunities
- Return clear error with subscription required message when limit reached
- Update existing tests and add new ones for this behavior

## Assigned Agents
- [x] @api-designer - Define error response contract
- [x] @backend-developer - Implement limit logic in service layer
- [x] @laravel-specialist - Tests and policy updates

## Progress

### API Contract

**POST /api/v1/opportunities** - Error when limit reached:

```json
{
  "success": false,
  "message": "You have reached the free opportunity limit. Please subscribe to create more opportunities.",
  "requires_subscription": true
}
```

HTTP Status: `403 Forbidden`

The `requires_subscription: true` flag allows mobile clients to detect this specific case and show a subscription modal.

### Backend

**Files Modified:**
- `app/Services/OpportunityService.php`
  - Added `FREE_OPPORTUNITY_LIMIT = 3` constant
  - Added `hasReachedFreeLimit(Profile)` method
  - Updated `create()` to check limit before creating
- `app/Http/Controllers/Api/V1/OpportunityController.php`
  - Updated `store()` to catch `InvalidArgumentException` and return 403 with `requires_subscription` flag

**Files Created:**
- `tests/Feature/Api/V1/OpportunityCreationLimitTest.php` - 11 tests

### Testing
- 11 new tests passing (28 assertions)
- 24 existing opportunity tests still passing
- Tests cover:
  - Business user can create up to 3 without subscription
  - 4th opportunity blocked without subscription
  - Subscribed business user unlimited
  - Community user unlimited (no limit applies)
  - All statuses count toward limit
  - Cancelled/past_due subscriptions still limited
  - Service unit tests for `hasReachedFreeLimit()`

## Notes
- Limit applies to total opportunities created (all statuses), not just published ones
- Community users have no limit
- `requires_subscription: true` flag in response enables mobile to show subscription paywall
