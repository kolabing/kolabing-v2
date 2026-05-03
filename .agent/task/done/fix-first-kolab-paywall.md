# Fix: first-kolab-paywall

## Status
- Created: 2026-05-03 16:00
- Started: 2026-05-03 16:00
- Completed: 2026-05-03 16:25

## Issue Type
- [x] Backend Logic Bug

## Affected Area
- [x] Backend
- [x] API

## Problem Statement
A brand-new business account that publishes its **first** venue-promotion or product-promotion kolab is shown the paywall screen (`HTTP 402`, `code: subscription_required`). The product rule is that every account should be allowed 1 free non-`CommunitySeeking` kolab publish before being asked to subscribe.

## Root Cause
`KolabService::publish()` at `app/Services/KolabService.php:168` enforces:

```php
if ($kolab->intent_type !== IntentType::CommunitySeeking
    && ! $creator->hasActiveSubscription()) {
    throw new InvalidArgumentException('A subscription is required to publish this type of kolab.');
}
```

There is no allowance for a "first free kolab". `CommunitySeeking` is always free, but `VenuePromotion` / `ProductPromotion` require a subscription **from the very first publish**, contradicting the freemium rule.

## Proposed Solution
1. Add `Profile::hasUsedFreeKolab(): bool` — true when the profile has ≥ 1 *previously published* (`published_at IS NOT NULL`) kolab whose `intent_type` is `VenuePromotion` or `ProductPromotion`. `CommunitySeeking` publishes (always free) do not consume the free slot.
2. Update `KolabService::publish()` to only throw the subscription error when the creator **has already used** their free kolab:

   ```php
   if ($kolab->intent_type !== IntentType::CommunitySeeking
       && ! $creator->hasActiveSubscription()
       && $creator->hasUsedFreeKolab()) {
       throw new InvalidArgumentException(...);
   }
   ```
3. Adjust the two existing paywall tests (`test_venue_promotion_requires_subscription_to_publish`, `test_product_promotion_requires_subscription_to_publish`) so the creator already has a published paid-tier kolab (i.e., the free quota is used up). Add new tests covering the first-publish-free case and the second-publish-paywall case.

## Implementation Details
- `app/Models/Profile.php`: imported `App\Enums\IntentType`; added `hasUsedFreeKolab(): bool` that returns true when the profile already has a kolab whose `intent_type` is `VenuePromotion` or `ProductPromotion` and `published_at IS NOT NULL`.
- `app/Services/KolabService.php`: extended the publish guard so the subscription error only fires when **all three** conditions hold — non-CommunitySeeking, no active subscription, **and** the free quota has already been used.
- `tests/Feature/Api/V1/KolabPublishCloseTest.php`:
  - Renamed and reframed the two existing paywall tests to `…_after_free_quota_used` and seeded a previously published paid-tier kolab to consume the quota.
  - Added `test_first_venue_promotion_is_free_without_subscription`.
  - Added `test_first_product_promotion_is_free_without_subscription`.
  - Added `test_community_seeking_publish_does_not_consume_paid_tier_free_quota`.

## Validation
- `php artisan test --compact tests/Feature/Api/V1/KolabPublishCloseTest.php` → 12 passed (35 assertions).
- `php artisan test --compact` (subscription-adjacent: Kolab + Opportunity publish/limit/CRUD) → 38 passed (99 assertions).
- `php artisan test --compact` (full suite) → 615 passed (3123 assertions), no regressions.
- `vendor/bin/pint --dirty` → clean.

## Files Affected
- `app/Models/Profile.php`
- `app/Services/KolabService.php`
- `tests/Feature/Api/V1/KolabPublishCloseTest.php`

## Assigned Agents
- [x] @backend-developer

## Follow-up Recommendations
- Surface `has_used_free_kolab` (or `free_kolab_remaining`) on the `UserResource` so the Flutter app can show a "1 free remaining" hint before the user reaches the paywall.
