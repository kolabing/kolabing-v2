# Mobile Implementation Guide: Subscription API

## Overview

Business users must have an active Stripe subscription (75 EUR/month) to publish collaboration opportunities. The subscription flow uses Stripe Checkout for payment and Stripe Billing Portal for management.

### Subscription Flow

```
Business User → Create Checkout Session → Stripe Checkout (hosted page) → Webhook activates subscription → User can publish opportunities
```

### Subscription Lifecycle

```
Inactive → Active (via checkout) → Cancelled (via cancel endpoint or Stripe portal)
                ↓
           Past Due (payment failed)
```

## Authentication

All endpoints require Bearer token authentication:
```
Authorization: Bearer {token}
```

All subscription endpoints are restricted to **business users only**. Community users receive a 403 response.

---

## Endpoints Summary

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/v1/me/subscription` | GET | Get subscription details |
| `/api/v1/me/subscription/checkout` | POST | Create Stripe checkout session |
| `/api/v1/me/subscription/portal` | GET | Get Stripe billing portal URL |
| `/api/v1/me/subscription/cancel` | POST | Cancel subscription at period end |

---

## 1. Get Subscription Details

Returns the current subscription status for the authenticated business user.

### Request

```
GET /api/v1/me/subscription
```

### Success Response - Active Subscription (200 OK)

```json
{
  "success": true,
  "data": {
    "id": "sub-uuid-123",
    "status": "active",
    "current_period_start": "2026-01-29T10:00:00+00:00",
    "current_period_end": "2026-02-28T10:00:00+00:00",
    "cancel_at_period_end": false
  }
}
```

### Success Response - No Subscription (200 OK)

```json
{
  "success": true,
  "data": null,
  "message": "No subscription found"
}
```

### Response Fields

| Field | Type | Description |
|-------|------|-------------|
| `id` | string (UUID) | Subscription record ID |
| `status` | string | `active`, `cancelled`, `past_due`, `inactive` |
| `current_period_start` | string (ISO 8601) or null | Start of current billing period |
| `current_period_end` | string (ISO 8601) or null | End of current billing period |
| `cancel_at_period_end` | boolean | Whether subscription will cancel at period end |

---

## 2. Create Checkout Session

Creates a Stripe Checkout session for the 75 EUR/month subscription. Returns a URL to redirect the user to Stripe's hosted payment page.

### Request

```
POST /api/v1/me/subscription/checkout
```

### Request Body

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `success_url` | string (URL) | Yes | URL to redirect after successful payment |
| `cancel_url` | string (URL) | Yes | URL to redirect if user cancels |

### Example Request

```json
{
  "success_url": "https://app.kolabing.com/subscription/success",
  "cancel_url": "https://app.kolabing.com/subscription/cancel"
}
```

### Success Response (200 OK)

```json
{
  "success": true,
  "data": {
    "checkout_url": "https://checkout.stripe.com/c/pay/cs_live_...",
    "session_id": "cs_live_..."
  }
}
```

### Error Responses

**Already Subscribed (400)**
```json
{
  "success": false,
  "message": "You already have an active subscription"
}
```

**Community User (403)**
```json
{
  "success": false,
  "message": "Only business users can subscribe"
}
```

**Validation Error (422)**
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

### Mobile Implementation Notes

For mobile apps, the `success_url` and `cancel_url` should be deep links that the app can handle:
- **React Native:** Use a URL scheme like `kolabing://subscription/success`
- **iOS:** Use a Universal Link like `https://app.kolabing.com/subscription/success`

The `checkout_url` should be opened in an in-app browser (WebView or SafariViewController). After payment, Stripe redirects to the success/cancel URL, which the app intercepts.

---

## 3. Get Billing Portal URL

Returns a URL to Stripe's Customer Billing Portal where the user can manage their payment method, view invoices, and cancel their subscription.

### Request

```
GET /api/v1/me/subscription/portal?return_url=https://app.kolabing.com/settings
```

### Query Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `return_url` | string (URL) | No | URL to return to after leaving portal |

### Success Response (200 OK)

```json
{
  "success": true,
  "data": {
    "portal_url": "https://billing.stripe.com/p/session/..."
  }
}
```

### Error Response - No Subscription (400)

```json
{
  "success": false,
  "message": "No subscription found for this user"
}
```

---

## 4. Cancel Subscription

Cancels the subscription at the end of the current billing period. The user retains access until `current_period_end`.

### Request

```
POST /api/v1/me/subscription/cancel
```

### Success Response (200 OK)

```json
{
  "success": true,
  "message": "Subscription will be cancelled at the end of the billing period",
  "data": {
    "id": "sub-uuid-123",
    "status": "active",
    "current_period_start": "2026-01-29T10:00:00+00:00",
    "current_period_end": "2026-02-28T10:00:00+00:00",
    "cancel_at_period_end": true
  }
}
```

### Error Responses

**No Active Subscription (400)**
```json
{
  "success": false,
  "message": "No active subscription found"
}
```

**Inactive Subscription (400)**
```json
{
  "success": false,
  "message": "Subscription is not active"
}
```

---

## Stripe Webhook Events

The backend handles these Stripe webhook events automatically:

| Event | Action |
|-------|--------|
| `checkout.session.completed` | Activates subscription after successful payment |
| `customer.subscription.updated` | Syncs subscription status and period dates |
| `customer.subscription.deleted` | Marks subscription as cancelled |
| `invoice.payment_failed` | Marks subscription as past_due |

The webhook endpoint is: `POST /api/v1/webhooks/stripe` (configured in Stripe Dashboard).

---

## Subscription Status Values

| Status | Description | Can Publish? |
|--------|-------------|-------------|
| `active` | Subscription is active and paid | Yes |
| `past_due` | Payment failed, retrying | No |
| `cancelled` | Subscription has been cancelled | No |
| `inactive` | No subscription or expired | No |

---

## Mobile Implementation Examples

### TypeScript / React Native

```typescript
// Types
interface Subscription {
  id: string;
  status: 'active' | 'cancelled' | 'past_due' | 'inactive';
  current_period_start: string | null;
  current_period_end: string | null;
  cancel_at_period_end: boolean;
}

interface CheckoutSession {
  checkout_url: string;
  session_id: string;
}

interface BillingPortal {
  portal_url: string;
}

// API Functions
const getSubscription = async (): Promise<{ success: boolean; data: Subscription | null }> => {
  const response = await fetch(
    `${API_BASE_URL}/api/v1/me/subscription`,
    {
      headers: {
        'Authorization': `Bearer ${token}`,
        'Accept': 'application/json',
      },
    }
  );
  return response.json();
};

const createCheckoutSession = async (
  successUrl: string,
  cancelUrl: string
): Promise<{ success: boolean; data: CheckoutSession }> => {
  const response = await fetch(
    `${API_BASE_URL}/api/v1/me/subscription/checkout`,
    {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
      body: JSON.stringify({ success_url: successUrl, cancel_url: cancelUrl }),
    }
  );
  return response.json();
};

const getBillingPortalUrl = async (
  returnUrl?: string
): Promise<{ success: boolean; data: BillingPortal }> => {
  const params = returnUrl ? `?return_url=${encodeURIComponent(returnUrl)}` : '';
  const response = await fetch(
    `${API_BASE_URL}/api/v1/me/subscription/portal${params}`,
    {
      headers: {
        'Authorization': `Bearer ${token}`,
        'Accept': 'application/json',
      },
    }
  );
  return response.json();
};

const cancelSubscription = async (): Promise<{ success: boolean; data: Subscription }> => {
  const response = await fetch(
    `${API_BASE_URL}/api/v1/me/subscription/cancel`,
    {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${token}`,
        'Accept': 'application/json',
      },
    }
  );
  return response.json();
};

// Usage: Open Stripe Checkout in InAppBrowser
import { Linking } from 'react-native';
import { InAppBrowser } from 'react-native-inappbrowser-reborn';

const handleSubscribe = async () => {
  const result = await createCheckoutSession(
    'kolabing://subscription/success',
    'kolabing://subscription/cancel'
  );

  if (result.success) {
    if (await InAppBrowser.isAvailable()) {
      await InAppBrowser.open(result.data.checkout_url, {
        showTitle: true,
        enableUrlBarHiding: true,
      });
    } else {
      Linking.openURL(result.data.checkout_url);
    }
  }
};
```

### Swift / iOS

```swift
// Models
struct Subscription: Codable {
    let id: String
    let status: String
    let currentPeriodStart: String?
    let currentPeriodEnd: String?
    let cancelAtPeriodEnd: Bool

    enum CodingKeys: String, CodingKey {
        case id, status
        case currentPeriodStart = "current_period_start"
        case currentPeriodEnd = "current_period_end"
        case cancelAtPeriodEnd = "cancel_at_period_end"
    }

    var isActive: Bool { status == "active" }
    var isPastDue: Bool { status == "past_due" }
}

struct CheckoutSession: Codable {
    let checkoutUrl: String
    let sessionId: String

    enum CodingKeys: String, CodingKey {
        case checkoutUrl = "checkout_url"
        case sessionId = "session_id"
    }
}

struct BillingPortal: Codable {
    let portalUrl: String

    enum CodingKeys: String, CodingKey {
        case portalUrl = "portal_url"
    }
}

// Subscription Service
class SubscriptionAPIService {
    private let baseURL: String
    private let token: String

    init(baseURL: String, token: String) {
        self.baseURL = baseURL
        self.token = token
    }

    func getSubscription() async throws -> Subscription? {
        let url = URL(string: "\(baseURL)/api/v1/me/subscription")!
        var request = URLRequest(url: url)
        request.setValue("Bearer \(token)", forHTTPHeaderField: "Authorization")
        request.setValue("application/json", forHTTPHeaderField: "Accept")

        let (data, _) = try await URLSession.shared.data(for: request)
        let response = try JSONDecoder().decode(APIResponse<Subscription?>.self, from: data)
        return response.data
    }

    func createCheckoutSession(successUrl: String, cancelUrl: String) async throws -> CheckoutSession {
        let url = URL(string: "\(baseURL)/api/v1/me/subscription/checkout")!
        var request = URLRequest(url: url)
        request.httpMethod = "POST"
        request.setValue("Bearer \(token)", forHTTPHeaderField: "Authorization")
        request.setValue("application/json", forHTTPHeaderField: "Content-Type")
        request.setValue("application/json", forHTTPHeaderField: "Accept")
        request.httpBody = try JSONEncoder().encode([
            "success_url": successUrl,
            "cancel_url": cancelUrl,
        ])

        let (data, _) = try await URLSession.shared.data(for: request)
        let response = try JSONDecoder().decode(APIResponse<CheckoutSession>.self, from: data)

        guard response.success, let session = response.data else {
            throw APIError.requestFailed(response.message ?? "Unknown error")
        }
        return session
    }

    func getBillingPortalUrl(returnUrl: String? = nil) async throws -> String {
        var components = URLComponents(string: "\(baseURL)/api/v1/me/subscription/portal")!
        if let returnUrl {
            components.queryItems = [URLQueryItem(name: "return_url", value: returnUrl)]
        }

        var request = URLRequest(url: components.url!)
        request.setValue("Bearer \(token)", forHTTPHeaderField: "Authorization")
        request.setValue("application/json", forHTTPHeaderField: "Accept")

        let (data, _) = try await URLSession.shared.data(for: request)
        let response = try JSONDecoder().decode(APIResponse<BillingPortal>.self, from: data)

        guard response.success, let portal = response.data else {
            throw APIError.requestFailed(response.message ?? "Unknown error")
        }
        return portal.portalUrl
    }

    func cancelSubscription() async throws -> Subscription {
        let url = URL(string: "\(baseURL)/api/v1/me/subscription/cancel")!
        var request = URLRequest(url: url)
        request.httpMethod = "POST"
        request.setValue("Bearer \(token)", forHTTPHeaderField: "Authorization")
        request.setValue("application/json", forHTTPHeaderField: "Accept")

        let (data, _) = try await URLSession.shared.data(for: request)
        let response = try JSONDecoder().decode(APIResponse<Subscription>.self, from: data)

        guard response.success, let subscription = response.data else {
            throw APIError.requestFailed(response.message ?? "Unknown error")
        }
        return subscription
    }
}

// Usage: Open Stripe Checkout with SFSafariViewController
import SafariServices

func handleSubscribe() async {
    do {
        let session = try await subscriptionService.createCheckoutSession(
            successUrl: "https://app.kolabing.com/subscription/success",
            cancelUrl: "https://app.kolabing.com/subscription/cancel"
        )

        if let url = URL(string: session.checkoutUrl) {
            let safariVC = SFSafariViewController(url: url)
            present(safariVC, animated: true)
        }
    } catch {
        // Handle error
    }
}
```

---

## UI/UX Recommendations

### Subscription Screen
1. **No Subscription State:**
   - Show subscription benefits
   - "Subscribe for 75 EUR/month" button
   - "Required to publish collaboration opportunities" note

2. **Active Subscription State:**
   - Show current period end date
   - "Manage Billing" button (opens Stripe Portal)
   - "Cancel Subscription" button with confirmation

3. **Cancelled/Pending Cancel State:**
   - Show "Active until [date]" message
   - "Resubscribe" button (opens new checkout)

4. **Past Due State:**
   - "Payment failed" warning
   - "Update Payment Method" button (opens Stripe Portal)

### Publish Gating
- If user tries to publish without subscription, show subscription paywall
- Deep link directly to checkout from the paywall

### Error Handling

| Error | User Message |
|-------|-------------|
| 401 | Session expired, redirect to login |
| 403 | Only available for business accounts |
| 400 (already subscribed) | You already have an active subscription |
| 400 (no subscription) | No subscription found |
| 422 | Please provide valid URLs |

---

## Stripe Setup Checklist

### Stripe Dashboard Configuration

1. **Create Product:** "Kolabing Business Monthly"
   - Price: 75.00 EUR / month
   - Copy the Price ID (e.g., `price_1234...`) to `STRIPE_MONTHLY_PRICE_ID`

2. **Configure Webhook:**
   - URL: `https://api.kolabing.com/api/v1/webhooks/stripe`
   - Events to listen for:
     - `checkout.session.completed`
     - `customer.subscription.updated`
     - `customer.subscription.deleted`
     - `invoice.payment_failed`
   - Copy Webhook Signing Secret to `STRIPE_WEBHOOK_SECRET`

3. **Enable Customer Portal:**
   - Allow subscription cancellation
   - Allow payment method update
   - Set portal branding

### Environment Variables

```
STRIPE_PUBLISHABLE_KEY=pk_live_...
STRIPE_SECRET_KEY=sk_live_...
STRIPE_WEBHOOK_SECRET=whsec_...
STRIPE_MONTHLY_PRICE_ID=price_...
```

---

## Changelog

- **2026-01-29**: Real Stripe integration
  - Replaced placeholder with real Stripe PHP SDK calls
  - Stripe Checkout Session creation
  - Stripe Billing Portal integration
  - Stripe webhook handling (checkout.session.completed, subscription.updated, subscription.deleted, invoice.payment_failed)
  - Subscription status mapping (active, past_due, cancelled, inactive)
  - Webhook signature verification
