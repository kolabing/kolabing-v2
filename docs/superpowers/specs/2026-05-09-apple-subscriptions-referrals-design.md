# Apple Subscriptions And Referrals Design

**Goal:** Move business subscriptions to an Apple-only flow, add referral validation and one-time referral rewards, and keep subscription state synchronized through App Store Server Notifications V2.

## Scope

- `POST /api/v1/me/subscription/apple-verify` becomes the primary paid subscription entrypoint.
- `POST /api/v1/referrals/validate` validates a referral code before purchase.
- `POST /api/v1/webhooks/apple` keeps subscription state in sync for renewals, cancel intent, billing retry, grace period, refunds, revokes, and expiration.
- `GET /api/v1/me/subscription` remains the read endpoint for active plan and state.
- Legacy Stripe checkout, billing portal, cancel/reactivate, and Stripe webhook routes are removed.

## Referral Rules

- `referral_code` is optional on Apple verify.
- Input is normalized with `trim()` and `strtoupper()`.
- Invalid, expired, self-referral, and already-used cases return `422` with:

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "referral_code": ["The selected referral code is invalid."]
  }
}
```

- Reward is granted only once, on the first successful paid subscription for the referred business profile.
- Repeated verify calls and webhook replays must never create duplicate rewards.

## Data Model

- `referral_codes` gains nullable `expires_at`.
- New `referral_redemptions` stores the single referral usage per referred profile and links it to the rewarded subscription.
- `business_subscriptions` gains uniqueness guarantees for Apple transaction identifiers so the same Apple purchase cannot attach to multiple profiles.

## Subscription Sync

- Apple verify cross-checks request payload against Apple transaction payload.
- Existing inactive business subscription rows created during signup are upgraded in place instead of creating a second row.
- Webhook updates are idempotent and only mutate the current subscription row.
- Grace period keeps access active; billing retry without grace marks the subscription `past_due`; refund, revoke, and expiration move it to `inactive`.

## Cleanup

- Remove obsolete Stripe routes, request classes, controller actions, webhook controller, and tests that describe the old billing flow.
