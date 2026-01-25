# Mobile Profile & Subscription API Guide

Bu dokuman, business kullanıcılar için profile ve subscription yönetimi API entegrasyonunu açıklar.

---

## Profile Sayfası UI Yapısı

```
┌─────────────────────────────────────────────────────────────┐
│                      PROFILE SCREEN                          │
├─────────────────────────────────────────────────────────────┤
│  ┌─────────────────────────────────────────────────────┐   │
│  │  PROFILE CARD                                        │   │
│  │  ┌──────┐  BUSINESS NAME           [EDIT PROFILE]   │   │
│  │  │ FOTO │  ┌─────────┐                               │   │
│  │  └──────┘  │RESTAURAN│                               │   │
│  │            └─────────┘                               │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
│  ┌─────────────────────┐  ┌─────────────────────────────┐  │
│  │ ABOUT               │  │ CONTACT INFO                │  │
│  │ Description text... │  │ 📧 email@example.com        │  │
│  │                     │  │ 📱 +34612345678             │  │
│  │                     │  │ 🌐 www.website.com          │  │
│  │                     │  │ 📷 @instagram               │  │
│  └─────────────────────┘  └─────────────────────────────┘  │
│                                                             │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ NOTIFICATION PREFERENCES                             │   │
│  │ Email Notifications              [====○]             │   │
│  │ WhatsApp Notifications           [====○]             │   │
│  │ New Application Alerts           [====○]             │   │
│  │ Collaboration Updates            [====○]             │   │
│  │ Marketing & Tips                 [○====]             │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ SUBSCRIPTION (Business only)                         │   │
│  │ Status: ACTIVE / INACTIVE / CANCELLED                │   │
│  │                                                      │   │
│  │ [MANAGE SUBSCRIPTION]  →  Stripe Portal              │   │
│  │ [VIEW PLANS]           →  Stripe Checkout            │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                             │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ ACCOUNT                                              │   │
│  │ Email: user@example.com                              │   │
│  │                                                      │   │
│  │ [        SIGN OUT        ]  (Red button)             │   │
│  │ [       DELETE ACCOUNT   ]  (Danger zone)            │   │
│  └─────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────┘
```

---

## 1. Profile Endpoints

### 1.1 Get Profile

```
GET /api/v1/me/profile
Authorization: Bearer {token}
```

**Response (Business User):**
```json
{
  "success": true,
  "data": {
    "id": "019bf6b3-xxxx-xxxx-xxxx-xxxxxxxxxxxx",
    "email": "restaurant@example.com",
    "phone_number": "+34612345678",
    "user_type": "business",
    "avatar_url": null,
    "onboarding_completed": true,
    "created_at": "2026-01-25T10:00:00Z",
    "business_profile": {
      "id": "019bf6b3-yyyy-yyyy-yyyy-yyyyyyyyyyyy",
      "name": "Mi Restaurante",
      "about": "El mejor restaurante de tapas en Barcelona",
      "business_type": "restaurante",
      "city": {
        "id": "019bf6b3-zzzz-zzzz-zzzz-zzzzzzzzzzzz",
        "name": "Barcelona",
        "country": "Spain"
      },
      "instagram": "mirestaurante",
      "website": "https://mirestaurante.es",
      "profile_photo": "https://fls-xxx.laravel.cloud/profiles/xxx/photo.jpg"
    },
    "subscription": {
      "id": "019bf6b3-ssss-ssss-ssss-ssssssssssss",
      "status": "active",
      "current_period_start": "2026-01-01T00:00:00Z",
      "current_period_end": "2026-02-01T00:00:00Z",
      "cancel_at_period_end": false,
      "is_active": true
    }
  }
}
```

**Response (Community User):**
```json
{
  "success": true,
  "data": {
    "id": "...",
    "email": "runner@example.com",
    "user_type": "community",
    "community_profile": {
      "id": "...",
      "name": "Barcelona Runners",
      "about": "Running community",
      "community_type": "running-club",
      "city": { ... },
      "instagram": "bcnrunners",
      "tiktok": "bcnrunners",
      "website": "https://bcnrunners.com",
      "profile_photo": "...",
      "is_featured": false
    }
  }
}
```

---

### 1.2 Update Profile

```
PUT /api/v1/me/profile
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body (Business):**
```json
{
  "name": "Mi Restaurante Updated",
  "about": "New description",
  "business_type": "restaurante",
  "city_id": "uuid-here",
  "phone_number": "+34612345678",
  "instagram": "newhandle",
  "website": "https://newwebsite.com",
  "profile_photo": "data:image/jpeg;base64,..."
}
```

**Request Body (Community):**
```json
{
  "name": "Barcelona Runners Updated",
  "about": "New description",
  "community_type": "running-club",
  "city_id": "uuid-here",
  "phone_number": "+34612345678",
  "instagram": "newhandle",
  "tiktok": "newtiktok",
  "website": "https://newwebsite.com",
  "profile_photo": "data:image/jpeg;base64,..."
}
```

**Validation Rules:**
| Field | Rules |
|-------|-------|
| name | max:255 |
| about | string, nullable |
| business_type | exists in business_types (slug) |
| community_type | exists in community_types (slug) |
| city_id | exists in cities |
| phone_number | max:20 |
| instagram | max:255 |
| tiktok | max:255 (community only) |
| website | valid URL |
| profile_photo | base64 or URL |

**Note:** All fields are optional - partial updates supported.

**Success Response (200):**
```json
{
  "success": true,
  "message": "Profile updated successfully",
  "data": { /* updated profile */ }
}
```

---

### 1.3 Delete Account

```
DELETE /api/v1/me/account
Authorization: Bearer {token}
```

**Success Response (200):**
```json
{
  "success": true,
  "message": "Account deleted successfully"
}
```

**Notes:**
- Soft delete (recoverable)
- All tokens revoked
- User cannot login after deletion

---

## 2. Notification Preferences

### 2.1 Get Preferences

```
GET /api/v1/me/notification-preferences
Authorization: Bearer {token}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "email_notifications": true,
    "whatsapp_notifications": true,
    "new_application_alerts": true,
    "collaboration_updates": true,
    "marketing_tips": false
  }
}
```

**Note:** Creates default preferences if not exist.

---

### 2.2 Update Preferences

```
PUT /api/v1/me/notification-preferences
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
  "email_notifications": true,
  "whatsapp_notifications": false,
  "new_application_alerts": true,
  "collaboration_updates": true,
  "marketing_tips": false
}
```

**Note:** All fields optional - partial updates supported.

**Success Response (200):**
```json
{
  "success": true,
  "message": "Notification preferences updated successfully",
  "data": {
    "email_notifications": true,
    "whatsapp_notifications": false,
    "new_application_alerts": true,
    "collaboration_updates": true,
    "marketing_tips": false
  }
}
```

---

## 3. Subscription Management (Business Only)

### 3.1 Get Subscription

```
GET /api/v1/me/subscription
Authorization: Bearer {token}
```

**Response (Active Subscription):**
```json
{
  "success": true,
  "data": {
    "id": "019bf6b3-ssss-ssss-ssss-ssssssssssss",
    "status": "active",
    "status_label": "Active",
    "current_period_start": "2026-01-01T00:00:00Z",
    "current_period_end": "2026-02-01T00:00:00Z",
    "cancel_at_period_end": false,
    "is_active": true,
    "days_remaining": 25
  }
}
```

**Response (No Subscription):**
```json
{
  "success": true,
  "data": null
}
```

**Status Values:**
| Status | Label | Description |
|--------|-------|-------------|
| active | Active | Subscription is active |
| cancelled | Cancelled | Subscription was cancelled |
| past_due | Past Due | Payment failed |
| inactive | Inactive | No active subscription |

**Note:** Community users get 403 Forbidden.

---

### 3.2 Create Checkout Session

```
POST /api/v1/me/subscription/checkout
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
  "success_url": "kolabing://subscription/success",
  "cancel_url": "kolabing://subscription/cancel"
}
```

**Success Response (200):**
```json
{
  "success": true,
  "message": "Checkout session created",
  "data": {
    "checkout_url": "https://checkout.stripe.com/pay/cs_xxx..."
  }
}
```

**Error (Already Subscribed - 400):**
```json
{
  "success": false,
  "message": "You already have an active subscription"
}
```

**Mobile Implementation:**
```dart
// Open Stripe Checkout in browser/webview
final response = await api.createCheckoutSession(
  successUrl: 'kolabing://subscription/success',
  cancelUrl: 'kolabing://subscription/cancel',
);

// Open URL in external browser
await launchUrl(Uri.parse(response.checkoutUrl));

// Handle deep link callback
// kolabing://subscription/success -> Refresh subscription status
```

---

### 3.3 Get Billing Portal

```
GET /api/v1/me/subscription/portal
Authorization: Bearer {token}
```

**Query Parameters:**
| Param | Required | Description |
|-------|----------|-------------|
| return_url | No | URL to return after portal (default: app deep link) |

**Success Response (200):**
```json
{
  "success": true,
  "data": {
    "portal_url": "https://billing.stripe.com/session/xxx..."
  }
}
```

**Error (No Subscription - 400):**
```json
{
  "success": false,
  "message": "No subscription found"
}
```

**Mobile Implementation:**
```dart
// Get portal URL
final response = await api.getBillingPortal(
  returnUrl: 'kolabing://subscription/portal-return',
);

// Open in browser
await launchUrl(Uri.parse(response.portalUrl));
```

---

### 3.4 Cancel Subscription

```
POST /api/v1/me/subscription/cancel
Authorization: Bearer {token}
```

**Success Response (200):**
```json
{
  "success": true,
  "message": "Subscription will be cancelled at the end of the current billing period",
  "data": {
    "status": "active",
    "cancel_at_period_end": true,
    "current_period_end": "2026-02-01T00:00:00Z"
  }
}
```

**Error (No Active Subscription - 400):**
```json
{
  "success": false,
  "message": "No active subscription to cancel"
}
```

**Note:** Subscription remains active until period end.

---

## 4. API Endpoints Summary

| Method | Endpoint | Auth | User Type | Description |
|--------|----------|------|-----------|-------------|
| GET | `/api/v1/me/profile` | Yes | All | Get full profile |
| PUT | `/api/v1/me/profile` | Yes | All | Update profile |
| DELETE | `/api/v1/me/account` | Yes | All | Delete account |
| GET | `/api/v1/me/notification-preferences` | Yes | All | Get preferences |
| PUT | `/api/v1/me/notification-preferences` | Yes | All | Update preferences |
| GET | `/api/v1/me/subscription` | Yes | Business | Get subscription |
| POST | `/api/v1/me/subscription/checkout` | Yes | Business | Create checkout |
| GET | `/api/v1/me/subscription/portal` | Yes | Business | Get portal URL |
| POST | `/api/v1/me/subscription/cancel` | Yes | Business | Cancel subscription |

---

## 5. Flutter Implementation Example

### 5.1 Profile Screen State

```dart
class ProfileState {
  final Profile? profile;
  final NotificationPreferences? notificationPrefs;
  final Subscription? subscription;
  final bool isLoading;
  final String? error;
}
```

### 5.2 Profile Repository

```dart
class ProfileRepository {
  final ApiClient _api;

  Future<Profile> getProfile() async {
    final response = await _api.get('/me/profile');
    return Profile.fromJson(response.data);
  }

  Future<Profile> updateProfile(UpdateProfileRequest request) async {
    final response = await _api.put('/me/profile', data: request.toJson());
    return Profile.fromJson(response.data);
  }

  Future<void> deleteAccount() async {
    await _api.delete('/me/account');
  }

  Future<NotificationPreferences> getNotificationPreferences() async {
    final response = await _api.get('/me/notification-preferences');
    return NotificationPreferences.fromJson(response.data);
  }

  Future<NotificationPreferences> updateNotificationPreferences(
    Map<String, bool> prefs,
  ) async {
    final response = await _api.put('/me/notification-preferences', data: prefs);
    return NotificationPreferences.fromJson(response.data);
  }
}
```

### 5.3 Subscription Repository

```dart
class SubscriptionRepository {
  final ApiClient _api;

  Future<Subscription?> getSubscription() async {
    final response = await _api.get('/me/subscription');
    if (response.data == null) return null;
    return Subscription.fromJson(response.data);
  }

  Future<String> createCheckoutSession({
    required String successUrl,
    required String cancelUrl,
  }) async {
    final response = await _api.post('/me/subscription/checkout', data: {
      'success_url': successUrl,
      'cancel_url': cancelUrl,
    });
    return response.data['checkout_url'];
  }

  Future<String> getBillingPortalUrl({String? returnUrl}) async {
    final response = await _api.get('/me/subscription/portal', queryParameters: {
      if (returnUrl != null) 'return_url': returnUrl,
    });
    return response.data['portal_url'];
  }

  Future<Subscription> cancelSubscription() async {
    final response = await _api.post('/me/subscription/cancel');
    return Subscription.fromJson(response.data);
  }
}
```

### 5.4 Deep Link Handling

```dart
// In main.dart or app initialization
void handleDeepLink(Uri uri) {
  switch (uri.path) {
    case '/subscription/success':
      // Refresh subscription status
      ref.read(subscriptionProvider.notifier).refresh();
      showSuccessSnackbar('Subscription activated!');
      break;
    case '/subscription/cancel':
      // User cancelled checkout
      break;
    case '/subscription/portal-return':
      // Returned from billing portal
      ref.read(subscriptionProvider.notifier).refresh();
      break;
  }
}
```

---

## 6. Error Handling

| Code | Description | Action |
|------|-------------|--------|
| 401 | Unauthorized | Redirect to login |
| 403 | Forbidden (wrong user type) | Show error |
| 400 | Bad request | Show validation errors |
| 422 | Validation failed | Show field errors |
| 500 | Server error | Show generic error |

---

## 7. Caching Strategy

| Data | Cache Duration | Strategy |
|------|----------------|----------|
| Profile | 5 minutes | Stale-while-revalidate |
| Notification Prefs | 5 minutes | Stale-while-revalidate |
| Subscription | 1 minute | Always fresh after actions |

---

## 8. Subscription Status UI

```dart
Widget buildSubscriptionCard(Subscription? subscription) {
  if (subscription == null) {
    return SubscriptionCard(
      status: 'No Subscription',
      statusColor: Colors.grey,
      showUpgradeButton: true,
    );
  }

  return SubscriptionCard(
    status: subscription.statusLabel,
    statusColor: _getStatusColor(subscription.status),
    periodEnd: subscription.currentPeriodEnd,
    cancelAtPeriodEnd: subscription.cancelAtPeriodEnd,
    daysRemaining: subscription.daysRemaining,
    showManageButton: subscription.isActive,
    showUpgradeButton: !subscription.isActive,
  );
}

Color _getStatusColor(String status) {
  switch (status) {
    case 'active': return Colors.green;
    case 'cancelled': return Colors.orange;
    case 'past_due': return Colors.red;
    default: return Colors.grey;
  }
}
```
