# Task: payment-integration-fixes

## Status
- Created: 2026-02-28 16:10
- Started: 2026-02-28 16:10
- Completed: 2026-02-28 16:30

## Description
Implement 3 missing pieces in the Stripe payment integration:

1. **Reactivate endpoint** — `POST /api/v1/me/subscription/reactivate`
   - Service method exists (`reactivateSubscription`), route + controller action missing

2. **Mobile deep link URL support** — `success_url` / `cancel_url` must accept
   custom scheme URLs like `kolabing://payment/success` in addition to https://

3. **`invoice.payment_succeeded` webhook** — restore `past_due` → `active`
   when Stripe retries and collects payment successfully

## Assigned Agents
- [x] @laravel-specialist

## Progress
### Backend
- [ ] SubscriptionController::reactivate()
- [ ] Route: POST /api/v1/me/subscription/reactivate
- [ ] CreateCheckoutSessionRequest — allow deep links
- [ ] StripeWebhookController — handle invoice.payment_succeeded
- [ ] SubscriptionService::handlePaymentSucceeded()
- [ ] Tests for all 3 fixes

### Documentation
- [ ] docs/mobile-payment-integration.md
