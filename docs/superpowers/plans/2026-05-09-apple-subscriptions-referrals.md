# Apple Subscriptions And Referrals Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the legacy Stripe subscription flow with Apple-only verification and webhook syncing, and add a one-time referral reward flow for the first paid subscription.

**Architecture:** Keep `GET /me/subscription` as the read surface, move Apple verification and webhook synchronization into a transaction-safe service flow, and persist referral redemptions separately from referral code ownership. Remove legacy Stripe routes and handlers so the API surface matches the Apple-only product decision.

**Tech Stack:** Laravel 12, Eloquent, Form Requests, Sanctum, PHPUnit feature tests, Apple App Store Server API integration

---

### Task 1: Lock in failing API tests

**Files:**
- Modify: `tests/Feature/Api/V1/AppleIAPControllerTest.php`
- Modify: `tests/Feature/Api/V1/AppleWebhookControllerTest.php`
- Modify: `tests/Feature/Api/V1/SubscriptionControllerTest.php`
- Create: `tests/Feature/Api/V1/ReferralValidationControllerTest.php`

- [ ] Add tests for referral validation success and invalid/expired/self/already-used failures.
- [ ] Add Apple verify tests for normalized referral processing, first-time rewarding, duplicate verify idempotency, and request/Apple payload mismatch rejection.
- [ ] Add Apple webhook tests for renew, cancel intent, grace period, billing retry, refund/revoke, and expiration mappings.
- [ ] Replace Stripe endpoint tests with assertions that removed routes now return `404`.

### Task 2: Add persistence for referral usage and Apple uniqueness

**Files:**
- Create: `database/migrations/2026_05_09_000001_add_expiry_and_uniques_for_apple_referrals.php`
- Create: `database/migrations/2026_05_09_000002_create_referral_redemptions_table.php`
- Create: `app/Models/ReferralRedemption.php`
- Create: `database/factories/ReferralRedemptionFactory.php`
- Modify: `app/Models/ReferralCode.php`
- Modify: `app/Models/Profile.php`
- Modify: `database/factories/ReferralCodeFactory.php`

- [ ] Add `expires_at` to referral codes and unique Apple indexes to subscriptions.
- [ ] Create the referral redemption model/table with one redemption per referred profile.
- [ ] Wire model relationships needed by controllers and services.

### Task 3: Implement referral validation and rewarding

**Files:**
- Create: `app/Services/ReferralService.php`
- Create: `app/Http/Controllers/Api/V1/ReferralController.php`
- Create: `app/Http/Requests/Api/V1/ValidateReferralCodeRequest.php`
- Modify: `routes/api.php`

- [ ] Implement normalization and business-rule validation.
- [ ] Return the required `422` payload for referral business-rule failures.
- [ ] Award the existing `referral_conversion` points exactly once and update referral aggregates.

### Task 4: Rework Apple verify and webhook synchronization

**Files:**
- Modify: `app/Services/AppleIAPService.php`
- Modify: `app/Http/Controllers/Api/V1/AppleIAPController.php`
- Modify: `app/Http/Controllers/Api/V1/AppleWebhookController.php`
- Modify: `app/Http/Requests/Api/V1/VerifyAppleTransactionRequest.php`

- [ ] Upgrade existing inactive business subscription rows instead of creating duplicates.
- [ ] Cross-check request payload against Apple payload.
- [ ] Make repeated verify calls return the existing subscription state without duplicate rewards.
- [ ] Handle renewal, cancel intent, grace period, billing retry, refund/revoke, and expiration idempotently.

### Task 5: Remove legacy Stripe API surface

**Files:**
- Modify: `routes/api.php`
- Modify: `app/Http/Controllers/Api/V1/SubscriptionController.php`
- Modify: `app/Services/SubscriptionService.php`
- Delete: `app/Http/Controllers/Api/V1/StripeWebhookController.php`
- Delete: `app/Http/Requests/Api/V1/CreateCheckoutSessionRequest.php`

- [ ] Remove checkout, portal, cancel, reactivate, and Stripe webhook routes.
- [ ] Trim the subscription service down to read-only responsibilities used by `GET /me/subscription`.
- [ ] Remove request/controller code that only supported Stripe.

### Task 6: Verify the full flow

**Files:**
- Verify: `tests/Feature/Api/V1/ReferralValidationControllerTest.php`
- Verify: `tests/Feature/Api/V1/AppleIAPControllerTest.php`
- Verify: `tests/Feature/Api/V1/AppleWebhookControllerTest.php`
- Verify: `tests/Feature/Api/V1/SubscriptionControllerTest.php`

- [ ] Run focused feature tests until green.
- [ ] Run a broader subscription/referral suite to catch regressions.
- [ ] Summarize any remaining risk, especially around production Apple credentials and real notification payload coverage.
