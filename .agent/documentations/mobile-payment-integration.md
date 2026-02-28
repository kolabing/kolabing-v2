# Mobile Payment Integration Guide

**Platform:** Kolabing API v1
**Auth:** Sanctum Bearer Token (Business users only)
**Updated:** 2026-02-28

---

## Overview

Subscription payments use **Stripe Checkout** (hosted page). The mobile app:
1. Requests a checkout URL from the API
2. Opens it in an in-app browser / WebView
3. Stripe redirects back to the app via **deep link** after success or cancel
4. App polls or re-fetches subscription status to update UI

Only **business** profile users can have subscriptions. Community users get 403 on all subscription endpoints.

---

## Base URL

```
https://api.kolabing.com/api/v1
```

All requests require:
```
Authorization: Bearer <sanctum_token>
Content-Type: application/json
Accept: application/json
```

---

## Subscription Object

All subscription endpoints return this shape inside `data`:

```json
{
  "id": "uuid",
  "status": "active",
  "current_period_start": "2026-02-01T00:00:00+00:00",
  "current_period_end": "2026-03-01T00:00:00+00:00",
  "cancel_at_period_end": false
}
```

### Status values

| status | Meaning |
|---|---|
| `active` | Subscription is live and paid |
| `past_due` | Payment failed, Stripe will retry |
| `cancelled` | Subscription ended |
| `inactive` | Created but never activated (pre-checkout) |

### cancel_at_period_end

| value | Meaning |
|---|---|
| `false` | Normal — will auto-renew |
| `true` | Scheduled to cancel at `current_period_end` — still active until then |

---

## Endpoints

### 1. Get Subscription Status

```
GET /me/subscription
```

**Response 200 — has subscription:**
```json
{
  "success": true,
  "data": {
    "id": "uuid",
    "status": "active",
    "current_period_start": "2026-02-01T00:00:00+00:00",
    "current_period_end": "2026-03-01T00:00:00+00:00",
    "cancel_at_period_end": false
  }
}
```

**Response 200 — no subscription:**
```json
{
  "success": true,
  "data": null,
  "message": "No subscription found"
}
```

---

### 2. Start Checkout (Subscribe)

Creates a Stripe Checkout session and returns the hosted payment URL.

```
POST /me/subscription/checkout
```

**Request body:**
```json
{
  "success_url": "kolabing://payment/success",
  "cancel_url": "kolabing://payment/cancel"
}
```

> **Deep links are supported.** Both `https://` and custom scheme URLs (e.g. `kolabing://`) are accepted.

**Response 200:**
```json
{
  "success": true,
  "data": {
    "checkout_url": "https://checkout.stripe.com/c/pay/cs_live_...",
    "session_id": "cs_live_..."
  }
}
```

**Response 400 — already subscribed:**
```json
{
  "success": false,
  "message": "You already have an active subscription"
}
```

**Response 422 — validation:**
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "success_url": ["The success url field is required."],
    "cancel_url": ["The cancel url field is required."]
  }
}
```

#### Mobile Flow

```
1. POST /me/subscription/checkout  →  get checkout_url
2. Open checkout_url in SFSafariViewController / Chrome Custom Tab
3. User completes payment on Stripe-hosted page
4. Stripe redirects to success_url (your deep link)
5. App handles deep link: close browser, show success UI
6. GET /me/subscription  →  verify status = "active"
```

**iOS deep link setup (Info.plist):**
```xml
<key>CFBundleURLSchemes</key>
<array>
  <string>kolabing</string>
</array>
```

**Android deep link setup (AndroidManifest.xml):**
```xml
<intent-filter>
  <action android:name="android.intent.action.VIEW" />
  <category android:name="android.intent.category.DEFAULT" />
  <category android:name="android.intent.category.BROWSABLE" />
  <data android:scheme="kolabing" android:host="payment" />
</intent-filter>
```

---

### 3. Cancel Subscription

Schedules cancellation at the end of the current billing period. Subscription stays **active** until `current_period_end`.

```
POST /me/subscription/cancel
```

No request body required.

**Response 200:**
```json
{
  "success": true,
  "message": "Subscription will be cancelled at the end of the billing period",
  "data": {
    "id": "uuid",
    "status": "active",
    "current_period_start": "2026-02-01T00:00:00+00:00",
    "current_period_end": "2026-03-01T00:00:00+00:00",
    "cancel_at_period_end": true
  }
}
```

> After cancel, status remains `active` and `cancel_at_period_end` becomes `true`.
> The user can still use the platform until `current_period_end`.

**Response 400 — no active subscription:**
```json
{
  "success": false,
  "message": "No active subscription found"
}
```

---

### 4. Reactivate Subscription

Removes the pending cancellation. Only works when `cancel_at_period_end = true` and status is still `active`.

```
POST /me/subscription/reactivate
```

No request body required.

**Response 200:**
```json
{
  "success": true,
  "message": "Subscription has been reactivated",
  "data": {
    "id": "uuid",
    "status": "active",
    "current_period_start": "2026-02-01T00:00:00+00:00",
    "current_period_end": "2026-03-01T00:00:00+00:00",
    "cancel_at_period_end": false
  }
}
```

**Response 400 — not scheduled for cancellation:**
```json
{
  "success": false,
  "message": "Subscription is not scheduled for cancellation"
}
```

**Response 400 — subscription not active:**
```json
{
  "success": false,
  "message": "Cannot reactivate an inactive subscription"
}
```

---

### 5. Billing Portal (Manage Subscription)

Opens Stripe's hosted billing portal where the user can update their payment method, view invoices, etc.

```
GET /me/subscription/portal?return_url=kolabing://settings
```

| Query param | Required | Description |
|---|---|---|
| `return_url` | No | Where Stripe redirects after the user finishes in the portal. Defaults to app URL. |

**Response 200:**
```json
{
  "success": true,
  "data": {
    "portal_url": "https://billing.stripe.com/p/session/..."
  }
}
```

#### Mobile Flow

```
1. GET /me/subscription/portal?return_url=kolabing://settings  →  get portal_url
2. Open portal_url in browser
3. User manages billing on Stripe's portal
4. Stripe redirects back to return_url (your deep link)
5. GET /me/subscription  →  refresh subscription state
```

---

## UI State Machine

Use `status` + `cancel_at_period_end` to drive UI:

```
data = null
  → Show "Subscribe" button

status = "inactive"
  → Show "Subscribe" button (checkout started but not completed)

status = "active" + cancel_at_period_end = false
  → Show "Active" badge + "Cancel" button + "Manage Billing" button

status = "active" + cancel_at_period_end = true
  → Show "Cancels on {current_period_end}" warning + "Reactivate" button

status = "past_due"
  → Show "Payment failed" warning + "Update Payment Method" button
  → Open billing portal for card update

status = "cancelled"
  → Show "Expired" badge + "Resubscribe" button
```

---

## Error Handling

| HTTP | Meaning | Action |
|---|---|---|
| 401 | Token missing/expired | Redirect to login |
| 403 | Community user or wrong user type | Hide subscription UI |
| 400 | Business logic error | Show `message` to user |
| 422 | Validation error | Show field errors |
| 500 | Server error | Show generic error, retry |

---

## Webhook Events (Backend Only)

These are handled server-side automatically. No mobile action required:

| Stripe Event | What Happens |
|---|---|
| `checkout.session.completed` | Subscription activated → `status = active` |
| `customer.subscription.updated` | Status/dates synced |
| `customer.subscription.deleted` | `status = cancelled` |
| `invoice.payment_failed` | `status = past_due` |
| `invoice.payment_succeeded` | `past_due` → `active` (payment retry recovered) |

When `past_due` clears automatically (Stripe retried and succeeded), the mobile app will see `status = active` on the next `GET /me/subscription` call.

---

## Quick Reference

```
GET  /me/subscription              → current subscription status
POST /me/subscription/checkout     → start payment (returns checkout_url)
POST /me/subscription/cancel       → schedule cancellation
POST /me/subscription/reactivate   → undo cancellation
GET  /me/subscription/portal       → manage billing on Stripe portal
```
