# Kolabing Mobile API -- Complete Reference

**Version:** 1.0
**Last Updated:** 2026-03-03
**Base URL:** `/api/v1/`
**Authentication:** Bearer Token (Laravel Sanctum)
**Total Endpoints:** 99

---

## Table of Contents

1. [Project Overview](#1-project-overview)
2. [Quick Start](#2-quick-start)
3. [Response Format Standard](#3-response-format-standard)
4. [Complete API Index](#4-complete-api-index)
5. [Authentication Guide](#5-authentication-guide)
6. [User Types](#6-user-types)
7. [Pagination Standard](#7-pagination-standard)
8. [Error Handling Standard](#8-error-handling-standard)
9. [Domain Summaries](#9-domain-summaries)
10. [Mobile Development Checklist](#10-mobile-development-checklist)
11. [Testing Guide](#11-testing-guide)

---

## 1. Project Overview

Kolabing is a B2B/B2C collaboration platform connecting businesses with community organizers in Spain. The platform enables businesses (restaurants, gyms, coworking spaces) to find community partners (running clubs, yoga groups, foodie communities) for mutually beneficial collaborations.

**Platform architecture:**

- Backend: Laravel 12 with PHP 8.3+, PostgreSQL 15+
- Auth: Laravel Sanctum (Bearer tokens)
- Payments: Stripe (monthly subscriptions, 75 EUR/month)
- Real-time: Laravel Reverb (WebSockets)
- Push Notifications: Firebase Cloud Messaging (FCM)
- Storage: Laravel Cloud file storage
- IDs: UUID primary keys on all tables

**Base URL for all API requests:**

```
https://api.kolabing.com/api/v1
```

**Required headers for every request:**

```
Accept: application/json
Content-Type: application/json
Authorization: Bearer {sanctum_token}   (for authenticated endpoints)
```

---

## 2. Quick Start

### Step 1: Register a user

```
POST /api/v1/auth/register/business    (or /community or /attendee)
```

### Step 2: Store the returned token

```json
{
  "success": true,
  "data": {
    "token": "1|abc123xyz...",
    "token_type": "Bearer",
    "user": { ... }
  }
}
```

### Step 3: Use the token for all subsequent requests

```
Authorization: Bearer 1|abc123xyz...
```

### Step 4: Register FCM device token (for push notifications)

```
POST /api/v1/me/device-token
{ "token": "fcm_device_token_here", "platform": "ios" }
```

### Step 5: Fetch dashboard data

```
GET /api/v1/me/dashboard
```

---

## 3. Response Format Standard

### Success Response

All successful responses follow this structure:

```json
{
  "success": true,
  "message": "Operation completed successfully",
  "data": { ... }
}
```

For list endpoints, `data` contains a nested data array plus `meta` for pagination:

```json
{
  "success": true,
  "data": [
    { ... },
    { ... }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 20,
    "total": 97
  }
}
```

### Error Response

```json
{
  "success": false,
  "message": "Human-readable error description",
  "errors": {
    "field_name": ["Specific error for this field"]
  }
}
```

### Special Error Fields

Some errors include extra fields:

```json
{
  "success": false,
  "message": "You have reached the free opportunity limit.",
  "requires_subscription": true
}
```

---

## 4. Complete API Index

### 4.1 Authentication

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | `/auth/register/business` | No | Register business user |
| POST | `/auth/register/community` | No | Register community user |
| POST | `/auth/register/attendee` | No | Register attendee user |
| POST | `/auth/login` | No | Email/password login |
| POST | `/auth/google` | No | Google OAuth login (existing users) |
| POST | `/auth/apple` | No | Apple Sign-In login |
| GET | `/auth/me` | Yes | Get current authenticated user |
| POST | `/auth/logout` | Yes | Logout (revoke token) |
| POST | `/auth/forgot-password` | No | Send password reset email |
| POST | `/auth/reset-password` | No | Reset password with token |

### 4.2 Onboarding

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| PUT | `/onboarding/business` | Yes | Complete business onboarding |
| PUT | `/onboarding/community` | Yes | Complete community onboarding |

### 4.3 Profile and Account

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/me/profile` | Yes | Get own profile |
| PUT | `/me/profile` | Yes | Update own profile |
| DELETE | `/me/account` | Yes | Delete account (soft delete) |
| GET | `/profiles/{profile}` | Yes | View any public profile |

### 4.4 Notification Preferences

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/me/notification-preferences` | Yes | Get notification preferences |
| PUT | `/me/notification-preferences` | Yes | Update notification preferences |

### 4.5 Subscription (Business Only)

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/me/subscription` | Yes | Get subscription status |
| POST | `/me/subscription/checkout` | Yes | Create Stripe checkout session |
| GET | `/me/subscription/portal` | Yes | Get Stripe billing portal URL |
| POST | `/me/subscription/cancel` | Yes | Cancel subscription at period end |
| POST | `/me/subscription/reactivate` | Yes | Reactivate cancelled subscription |

### 4.6 Opportunities

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/opportunities` | Yes | Browse published opportunities (with filters) |
| POST | `/opportunities` | Yes | Create new opportunity (draft) |
| GET | `/opportunities/{id}` | Yes | Get opportunity details |
| PUT | `/opportunities/{id}` | Yes | Update opportunity (draft/published only) |
| DELETE | `/opportunities/{id}` | Yes | Delete opportunity (draft, 0 apps only) |
| POST | `/opportunities/{id}/publish` | Yes | Publish draft opportunity |
| POST | `/opportunities/{id}/close` | Yes | Close published opportunity |
| GET | `/me/opportunities` | Yes | List own opportunities (all statuses) |

### 4.7 Applications

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | `/opportunities/{id}/applications` | Yes | Apply to opportunity |
| GET | `/opportunities/{id}/applications` | Yes | List applications for an opportunity |
| GET | `/applications/{id}` | Yes | Get application details |
| POST | `/applications/{id}/accept` | Yes | Accept application (creator only) |
| POST | `/applications/{id}/decline` | Yes | Decline application (creator only) |
| POST | `/applications/{id}/withdraw` | Yes | Withdraw own application |
| GET | `/me/applications` | Yes | List my sent applications |
| GET | `/me/received-applications` | Yes | List received applications |

### 4.8 Collaborations

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/collaborations` | Yes | List my collaborations |
| GET | `/collaborations/{id}` | Yes | Get collaboration details |
| POST | `/collaborations/{id}/activate` | Yes | Activate scheduled collaboration |
| POST | `/collaborations/{id}/complete` | Yes | Complete active collaboration |
| POST | `/collaborations/{id}/cancel` | Yes | Cancel collaboration |
| PUT | `/collaborations/{id}/challenges` | Yes | Sync challenges to collaboration |
| POST | `/collaborations/{id}/challenges` | Yes | Add custom challenge to collaboration |
| POST | `/collaborations/{id}/qr-code` | Yes | Generate QR code for collaboration event |
| GET | `/profiles/{profile}/collaborations` | Yes | View profile's public collaborations |

### 4.9 Chat / Messaging

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/applications/{id}/messages` | Yes | Get chat messages (paginated) |
| POST | `/applications/{id}/messages` | Yes | Send chat message |
| POST | `/applications/{id}/messages/read` | Yes | Mark messages as read |
| GET | `/me/unread-messages-count` | Yes | Get unread message count |

### 4.10 In-App Notifications

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/me/notifications` | Yes | List notifications (paginated) |
| GET | `/me/notifications/unread-count` | Yes | Get unread notification count |
| POST | `/me/notifications/{id}/read` | Yes | Mark single notification as read |
| POST | `/me/notifications/read-all` | Yes | Mark all notifications as read |

### 4.11 Push Notifications (FCM)

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | `/me/device-token` | Yes | Register/update FCM device token |

### 4.12 Gallery

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/me/gallery` | Yes | List own gallery photos |
| POST | `/me/gallery` | Yes | Upload gallery photo (multipart) |
| DELETE | `/me/gallery/{photo}` | Yes | Delete gallery photo |
| GET | `/profiles/{profile}/gallery` | Yes | View another profile's gallery |

### 4.13 Events (Past Events / Gamification)

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/events` | Yes | List events |
| POST | `/events` | Yes | Create event |
| GET | `/events/{id}` | Yes | Get event details |
| PUT | `/events/{id}` | Yes | Update event (owner only) |
| DELETE | `/events/{id}` | Yes | Delete event (owner only) |
| GET | `/events/discover` | Yes | Discover nearby events (GPS) |
| POST | `/events/{id}/generate-qr` | Yes | Generate QR code for event check-in |
| GET | `/events/{id}/checkins` | Yes | List event check-ins |

### 4.14 Gamification -- Check-in and Challenges

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | `/checkin` | Yes | Check in to event via QR token |
| GET | `/challenges/system` | Yes | List system-defined challenges |
| PUT | `/challenges/{id}` | Yes | Update challenge |
| DELETE | `/challenges/{id}` | Yes | Delete challenge |
| GET | `/events/{id}/challenges` | Yes | List challenges for event |
| POST | `/events/{id}/challenges` | Yes | Create custom challenge for event |
| POST | `/challenges/initiate` | Yes | Initiate a challenge completion |
| POST | `/challenge-completions/{id}/verify` | Yes | Verify challenge (peer) |
| POST | `/challenge-completions/{id}/reject` | Yes | Reject challenge (peer) |
| GET | `/me/challenge-completions` | Yes | List my challenge completions |

### 4.15 Gamification -- Rewards

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/events/{id}/rewards` | Yes | List event rewards |
| POST | `/events/{id}/rewards` | Yes | Create event reward (organizer) |
| PUT | `/event-rewards/{id}` | Yes | Update event reward |
| DELETE | `/event-rewards/{id}` | Yes | Delete event reward |
| POST | `/rewards/spin` | Yes | Spin the wheel after challenge |
| GET | `/me/rewards` | Yes | My reward wallet |
| POST | `/reward-claims/{id}/generate-redeem-qr` | Yes | Generate QR to redeem reward |
| POST | `/reward-claims/confirm-redeem` | Yes | Confirm reward redemption (organizer) |

### 4.16 Gamification -- Badges, Leaderboard, Stats

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/badges` | Yes | List all available badges |
| GET | `/me/badges` | Yes | List my earned badges |
| GET | `/events/{id}/leaderboard` | Yes | Event leaderboard |
| GET | `/leaderboard/global` | Yes | Global leaderboard |
| GET | `/me/gamification-stats` | Yes | My gamification statistics |
| GET | `/profiles/{profile}/game-card` | Yes | View profile's public game card |

### 4.17 Dashboard

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/me/dashboard` | Yes | Dashboard stats (varies by user type) |

### 4.18 Lookup Tables

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | `/cities` | No* | List all cities (126 Spanish cities) |
| GET | `/lookup/business-types` | No* | List business types (15 types) |
| GET | `/lookup/community-types` | No* | List community types (15 types) |

*Lookup endpoints may or may not require auth depending on usage context.

### 4.19 Webhooks (Server-to-Server)

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | `/webhooks/stripe` | Stripe Signature | Stripe webhook receiver |

---

## 5. Authentication Guide

### 5.1 Registration

Three registration endpoints exist, one per user type:

```
POST /api/v1/auth/register/business
POST /api/v1/auth/register/community
POST /api/v1/auth/register/attendee
```

**Business registration** requires: `email`, `password`, `password_confirmation`, `name`, `business_type`, `city_id`

**Community registration** requires: `email`, `password`, `password_confirmation`, `name`, `community_type`, `city_id`

**Attendee registration** requires: `email`, `password`, `password_confirmation` (minimal payload)

All registration endpoints return a Sanctum token on success (201).

### 5.2 Login

```
POST /api/v1/auth/login
Body: { "email": "...", "password": "..." }
```

Returns a token on success (200). If the account was created via Google OAuth only, returns 401 with a message directing the user to sign in with Google.

### 5.3 Social Login

```
POST /api/v1/auth/google
Body: { "id_token": "google_oauth_id_token", "user_type": "business" }

POST /api/v1/auth/apple
Body: { "id_token": "apple_identity_token", "user_type": "community" }
```

Social login is for **existing users only**. New users must register first via the register endpoints.

### 5.4 Token Usage

Store the token securely (Keychain on iOS, EncryptedSharedPreferences on Android, flutter_secure_storage on Flutter).

Include it in every authenticated request:

```
Authorization: Bearer 1|abc123xyz...
```

### 5.5 Token Lifecycle

- Tokens are long-lived (30 days by default)
- Logout revokes the current token: `POST /api/v1/auth/logout`
- Password reset revokes all tokens
- Account deletion revokes all tokens

### 5.6 Password Reset Flow

1. User requests reset: `POST /api/v1/auth/forgot-password` with `{"email": "..."}`
2. System sends email with reset link
3. Mobile app intercepts deep link: `kolabing://reset-password?token=TOKEN&email=EMAIL`
4. User sets new password: `POST /api/v1/auth/reset-password` with `token`, `email`, `password`, `password_confirmation`
5. Token is valid for 60 minutes. Throttle: 1 request per 60 seconds per email.

---

## 6. User Types

Kolabing has three user types, each with different capabilities:

| Capability | Business | Community | Attendee |
|------------|----------|-----------|----------|
| Create opportunities | Yes | Yes | No |
| Publish opportunities | Yes (subscription required) | Yes (free) | No |
| Apply to opportunities | Yes | Yes | No |
| Manage collaborations | Yes | Yes | No |
| Chat messaging | Yes | Yes | No |
| Profile gallery | Yes | Yes | No |
| Subscription management | Yes | No (403) | No (403) |
| Create events | Yes | Yes | No |
| Check in to events | No | No | Yes |
| Complete challenges | No | No | Yes |
| Spin for rewards | No | No | Yes |
| Earn badges | No | No | Yes |
| Leaderboard | No | No | Yes |

### Profile Structure

Each user type has a different extended profile:

- **Business:** `profiles` + `business_profiles` (business_type, city, name, about, instagram, website)
- **Community:** `profiles` + `community_profiles` (community_type, city, name, about, instagram, tiktok, website, is_featured)
- **Attendee:** `profiles` + `attendee_profiles` (total_points, total_challenges_completed, total_events_attended, global_rank)

### How user_type appears in responses

The `/auth/me` and profile responses include the appropriate nested profile:

```json
{
  "id": "uuid",
  "email": "user@example.com",
  "user_type": "business",
  "business_profile": { ... }
}
```

or

```json
{
  "id": "uuid",
  "email": "user@example.com",
  "user_type": "community",
  "community_profile": { ... }
}
```

or

```json
{
  "id": "uuid",
  "email": "user@example.com",
  "user_type": "attendee",
  "attendee_profile": { ... }
}
```

---

## 7. Pagination Standard

All list endpoints support cursor/offset pagination with these query parameters:

| Parameter | Type | Default | Max | Description |
|-----------|------|---------|-----|-------------|
| `page` | integer | 1 | -- | Page number |
| `per_page` | integer | 20 | 100 | Items per page |

The pagination metadata is returned in the `meta` key:

```json
{
  "meta": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 20,
    "total": 97
  }
}
```

### Best Practices

- Default to `per_page=20` for optimal performance
- Implement infinite scroll or "Load More" button
- Cache previous pages to avoid redundant network requests
- Show loading skeleton while fetching
- Handle empty states when `total` is 0

### Exceptions

Some endpoints use different pagination conventions:

- **Events list:** Uses `limit` and `page` parameters (max limit: 50)
- **Chat messages:** Default `per_page` is 50
- **Gallery:** Not paginated (max 10 photos per profile)

---

## 8. Error Handling Standard

### HTTP Status Codes

| Code | Meaning | When it happens |
|------|---------|-----------------|
| 200 | OK | Successful GET, PUT, POST (action), DELETE |
| 201 | Created | Successful POST (resource creation) |
| 400 | Bad Request | Business logic error (e.g., cannot publish, already applied) |
| 401 | Unauthorized | Missing or invalid/expired token |
| 403 | Forbidden | Authenticated but not authorized (wrong user type, not owner) |
| 404 | Not Found | Resource does not exist |
| 409 | Conflict | User type mismatch (Google login) |
| 422 | Unprocessable Entity | Validation error (invalid input) |
| 429 | Too Many Requests | Rate limit exceeded |
| 500 | Internal Server Error | Server-side error |

### Error Response Structures

**Validation error (422):**

```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "title": ["The title field is required."],
    "email": ["The email has already been taken."]
  }
}
```

**Business logic error (400):**

```json
{
  "success": false,
  "message": "Only draft opportunities can be published."
}
```

**Authorization error (403):**

```json
{
  "success": false,
  "message": "You are not authorized to perform this action."
}
```

**Authentication error (401):**

```json
{
  "success": false,
  "message": "Unauthenticated."
}
```

### Mobile Error Handling Strategy

```
401 --> Redirect to login screen, clear stored token
403 --> Show "permission denied" message, check user type
404 --> Show "not found" message
409 --> Show conflict message (e.g., wrong login method)
422 --> Parse errors object, display field-specific messages
400 --> Show the message to user as a toast/dialog
429 --> Show "please wait" message, implement retry with backoff
500 --> Show generic "something went wrong" message
```

---

## 9. Domain Summaries

### 9.1 Authentication
Handles user registration (business, community, attendee), email/password login, Google OAuth, Apple Sign-In, token-based session management, and password reset flows. Registration returns a Sanctum Bearer token that must be stored securely and sent with all authenticated requests.
**Detailed docs:** `mobile-auth-api-guide.md`

### 9.2 Password Reset
Two-step flow: forgot-password sends an email with a reset link containing a token (valid 60 minutes), then reset-password uses that token to set a new password. The mobile app intercepts the deep link `kolabing://reset-password?token=...&email=...` to present the reset form.
**Detailed docs:** `mobile-password-reset-api.md`

### 9.3 Profile and Subscription
Profile CRUD for both business and community users, including profile photo upload (base64), notification preferences (email, WhatsApp, application alerts, marketing), and account deletion. Subscription management is business-only: create Stripe checkout sessions, access the billing portal, cancel, and reactivate subscriptions. Subscription costs 75 EUR/month.
**Detailed docs:** `mobile-profile-subscription-guide.md`, `mobile-subscription-api.md`

### 9.4 Opportunities
The core feature. Users create collaboration opportunities as drafts, then publish them to become visible. Business users must have an active subscription to publish (community users publish free). Opportunities support filtering by creator_type, categories, city, venue_mode, availability_mode, date range, and full-text search. Business users without a subscription are limited to 3 free opportunities before hitting a paywall.
**Detailed docs:** `MOBILE_OPPORTUNITY_API.md`, `MOBILE_OPPORTUNITY_API_QUICK_REFERENCE.md`, `MOBILE_OPPORTUNITY_API_USER_FLOWS.md`, `mobile-search-opportunities-api.md`, `mobile-opportunity-limit-guide.md`

### 9.5 Applications
Users apply to published opportunities with a message and availability description. Opportunity creators can accept (creates a collaboration) or decline applications with an optional reason. Applicants can withdraw their own applications. One application per user per opportunity, enforced by unique constraint.
**Detailed docs:** `mobile-applications-api.md`, `mobile-accept-application-api.md`

### 9.6 Collaborations
Created automatically when an application is accepted. Collaborations follow the lifecycle: scheduled -> active -> completed (or cancelled). Supports challenge sync, custom challenge creation, and QR code generation for event check-in. Both participants can manage the collaboration.
**Detailed docs:** `collaboration-api-mobile-docs.md`

### 9.7 Chat
Application-scoped messaging between opportunity creator and applicant. Messages are paginated (newest first, default 50 per page), support read tracking with timestamps, and include real-time delivery via WebSocket (Laravel Reverb on channel `chat.application.{application_id}`). Unread count endpoint available for badge UI.
**Detailed docs:** `mobile-chat-api.md`

### 9.8 Notifications (In-App)
Database-backed notification system with 7 types: new_message, application_received, application_accepted, application_declined, badge_awarded, challenge_verified, reward_won. Supports listing with pagination, unread count, mark single as read, and mark all as read.
**Detailed docs:** `mobile-notification-api.md`

### 9.9 Push Notifications (FCM)
Firebase Cloud Messaging integration for real-time push notifications. Mobile app registers device tokens via `POST /me/device-token` after login. Backend sends push notifications for key events and the mobile app should refresh the notifications list when a push is received.
**Detailed docs:** `mobile-push-notifications-api.md` (in progress)

### 9.10 Gallery
Profile photo gallery supporting up to 10 photos per profile. Upload via multipart/form-data with optional caption. Supports JPEG, PNG, GIF, WebP formats. Both business and community users can use gallery features. Other users can view any profile's gallery.
**Detailed docs:** `mobile-gallery-api.md`

### 9.11 Events and Gamification
Three-phase gamification system. Phase 1: Attendee user type, event check-in via QR, peer-to-peer challenge initiation/verification with automatic point awarding. Phase 2: Event reward CRUD, spin-the-wheel after challenge completion, reward wallet with QR redemption, per-event and global leaderboards. Phase 3: 9 milestone badges auto-awarded by backend, GPS-based event discovery (Haversine formula), gamification stats dashboard, and public game cards.
**Detailed docs:** `mobile-events-api.md`, `mobile-gamification-api.md`, `mobile-gamification-phase2-3-api.md`

### 9.12 Dashboard
Single endpoint returning summary statistics tailored to user type. Business users see opportunity counts (by status), received application counts (by status), collaboration counts, and upcoming collaborations. Community users see application counts, collaboration counts, and upcoming collaborations.
**Detailed docs:** `mobile-dashboard-api.md`

### 9.13 Payment Integration
Stripe-based subscription payments using hosted checkout. The flow: request checkout URL from API, open in in-app browser, Stripe redirects via deep link on success/cancel, app refreshes subscription status. Deep links: `kolabing://subscription/success`, `kolabing://subscription/cancel`, `kolabing://subscription/portal-return`.
**Detailed docs:** `mobile-payment-integration.md`

### 9.14 Lookup Tables and File Upload
Static lookup data for onboarding: 126 Spanish cities, 15 business types, 15 community types. File uploads support base64 encoding (recommended for mobile) with supported formats: jpeg, jpg, png, gif, webp.
**Detailed docs:** `mobile-integration-guide.md`

---

## 10. Mobile Development Checklist

Ordered integration checklist for building the Kolabing mobile app. Complete each phase before moving to the next.

### Phase 1: Foundation

- [ ] Set up API client with base URL, headers, and token management
- [ ] Implement secure token storage (Keychain / EncryptedSharedPreferences / flutter_secure_storage)
- [ ] Build registration screens (business + community)
- [ ] Build login screen (email/password)
- [ ] Integrate Google Sign-In
- [ ] Integrate Apple Sign-In
- [ ] Implement `/auth/me` call on app launch to validate token
- [ ] Handle 401 responses globally (redirect to login)
- [ ] Fetch lookup data: cities, business types, community types
- [ ] Build onboarding flow for profile completion

### Phase 2: Core Profile

- [ ] Build profile view screen (GET `/me/profile`)
- [ ] Build profile edit screen (PUT `/me/profile`)
- [ ] Implement profile photo upload (base64)
- [ ] Build notification preferences toggle screen
- [ ] Implement forgot password flow with deep link handling
- [ ] Build account deletion with confirmation dialog

### Phase 3: Opportunities

- [ ] Build opportunity browse/explore screen with filters
- [ ] Implement search with 300ms debounce
- [ ] Build opportunity detail screen
- [ ] Build opportunity creation form (all fields + JSONB editors)
- [ ] Implement "My Opportunities" list with status filter
- [ ] Implement publish action (with subscription check for business)
- [ ] Implement close and delete actions
- [ ] Handle opportunity limit paywall for unsubscribed business users

### Phase 4: Applications

- [ ] Build "Apply" flow with message + availability inputs
- [ ] Build "My Applications" list with status filter tabs
- [ ] Build "Received Applications" list for opportunity creators
- [ ] Implement accept with optional scheduled_date and notes
- [ ] Implement decline with optional reason
- [ ] Implement withdraw with confirmation

### Phase 5: Collaborations

- [ ] Build collaborations list screen
- [ ] Build collaboration detail screen
- [ ] Implement activate, complete, cancel actions
- [ ] Build QR code generation for events

### Phase 6: Subscription (Business Users)

- [ ] Build subscription status card on profile
- [ ] Implement Stripe checkout flow (open URL in browser)
- [ ] Handle deep link callbacks (success, cancel, portal-return)
- [ ] Implement cancel subscription with confirmation
- [ ] Implement reactivate subscription
- [ ] Build subscription paywall modal

### Phase 7: Chat

- [ ] Build chat screen with message bubbles (own vs other)
- [ ] Implement message sending
- [ ] Implement pagination (load older messages on scroll up)
- [ ] Implement mark-as-read
- [ ] Build unread count badge on navigation
- [ ] Set up WebSocket connection for real-time messages (Laravel Reverb)

### Phase 8: Notifications

- [ ] Build notifications list screen
- [ ] Implement unread count badge
- [ ] Implement mark as read (single + all)
- [ ] Set up Firebase Cloud Messaging
- [ ] Register device token after login
- [ ] Handle push notification tap (deep link to relevant screen)

### Phase 9: Gallery

- [ ] Build gallery grid on profile
- [ ] Implement photo upload (camera + gallery picker)
- [ ] Implement photo deletion
- [ ] View other profiles' galleries

### Phase 10: Dashboard

- [ ] Build dashboard screen with stats cards
- [ ] Show upcoming collaborations list
- [ ] Handle different layouts for business vs community

### Phase 11: Events and Gamification (Attendee)

- [ ] Build attendee registration flow
- [ ] Implement QR code scanning for event check-in
- [ ] Build challenge initiation and verification flows
- [ ] Build spin-the-wheel UI for rewards
- [ ] Build reward wallet with QR redemption
- [ ] Build leaderboard screens (event + global)
- [ ] Build badges collection screen
- [ ] Implement event discovery with map view
- [ ] Build gamification stats dashboard
- [ ] Build public game card view

### Phase 12: Polish

- [ ] Implement pull-to-refresh on all list screens
- [ ] Add skeleton loaders for all screens
- [ ] Implement optimistic UI updates where appropriate
- [ ] Add empty state illustrations
- [ ] Implement offline mode / cached data display
- [ ] Add analytics tracking
- [ ] Test all deep link scenarios

---

## 11. Testing Guide

### 11.1 Development Environment Setup

**Email testing:** Set `MAIL_MAILER=log` in `.env` to log emails instead of sending. Password reset tokens appear in `storage/logs/laravel.log`.

**Firebase credentials:** Set `FIREBASE_CREDENTIALS` environment variable to the path of your Firebase service account JSON file for push notification testing.

**Stripe testing:** Use Stripe test mode keys. Test card number: `4242 4242 4242 4242`, any future expiry, any CVC.

### 11.2 Testing Authentication

```bash
# Register a business user
curl -X POST https://api.kolabing.com/api/v1/auth/register/business \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "email": "test@example.com",
    "password": "password123",
    "password_confirmation": "password123",
    "name": "Test Business",
    "business_type": "restaurante",
    "city_id": "CITY_UUID_HERE"
  }'

# Login
curl -X POST https://api.kolabing.com/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{"email": "test@example.com", "password": "password123"}'

# Get current user
curl https://api.kolabing.com/api/v1/auth/me \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json"
```

### 11.3 Testing Opportunities

```bash
# Browse opportunities
curl "https://api.kolabing.com/api/v1/opportunities?creator_type=business&city=Barcelona&per_page=10" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json"

# Create draft
curl -X POST https://api.kolabing.com/api/v1/opportunities \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "title": "Test Opportunity",
    "description": "A test collaboration opportunity for development",
    "business_offer": {"venue": true, "food_drink": true},
    "community_deliverables": {"instagram_post": true, "attendee_count": 30},
    "categories": ["Food & Drink"],
    "availability_mode": "one_time",
    "availability_start": "2026-04-01",
    "availability_end": "2026-04-01",
    "venue_mode": "business_venue",
    "address": "Test Address, Barcelona",
    "preferred_city": "Barcelona"
  }'
```

### 11.4 Key Enum Values for Testing

**Opportunity Status:** `draft`, `published`, `closed`, `completed`

**Application Status:** `pending`, `accepted`, `declined`, `withdrawn`

**Collaboration Status:** `scheduled`, `active`, `completed`, `cancelled`

**Subscription Status:** `active`, `cancelled`, `past_due`, `inactive`

**Availability Mode:** `one_time`, `recurring`, `flexible`

**Venue Mode:** `business_venue`, `community_venue`, `no_venue`

**Notification Types:** `new_message`, `application_received`, `application_accepted`, `application_declined`, `badge_awarded`, `challenge_verified`, `reward_won`

### 11.5 WebSocket Testing

Connect to Laravel Reverb for real-time chat:

```
WebSocket URL: wss://{REVERB_HOST}:{REVERB_PORT}/app/{REVERB_APP_KEY}
Auth endpoint: POST /broadcasting/auth (with Bearer token)
Channel: private-chat.application.{application_id}
Event: .message.sent
```

### 11.6 Deep Link Schemes

| Deep Link | Purpose |
|-----------|---------|
| `kolabing://reset-password?token=...&email=...` | Password reset |
| `kolabing://subscription/success` | Stripe checkout success |
| `kolabing://subscription/cancel` | Stripe checkout cancelled |
| `kolabing://subscription/portal-return` | Return from Stripe portal |

### 11.7 File Upload Testing

Supported image formats: `jpeg`, `jpg`, `png`, `gif`, `webp`

Base64 format (recommended for mobile):
```
data:image/jpeg;base64,/9j/4AAQSkZJRg...
```

Gallery limit: 10 photos per profile.

---

## Appendix A: Status Flow Diagrams

### Opportunity Lifecycle

```
draft --> published --> closed --> completed
```

### Application Lifecycle

```
pending --> accepted  --> [creates Collaboration]
        --> declined
        --> withdrawn
```

### Collaboration Lifecycle

```
scheduled --> active --> completed
                     --> cancelled
          --> cancelled
```

### Subscription Lifecycle

```
inactive --> active (via checkout/webhook)
                --> cancelled (cancel at period end)
                --> past_due (payment failed)
```

---

## Appendix B: JSONB Field Structures

### business_offer

```json
{
  "venue": true,
  "food_drink": true,
  "discount": {
    "enabled": true,
    "percentage": 20
  },
  "products": ["Yoga mats", "Water bottles"],
  "other": "Free trial memberships for attendees"
}
```

### community_deliverables

```json
{
  "instagram_post": true,
  "instagram_story": true,
  "tiktok_video": false,
  "event_mention": true,
  "attendee_count": 50,
  "other": "Feature in monthly newsletter"
}
```

### categories (array, 1-5 items)

```json
["Food & Drink", "Wellness", "Sports"]
```

Common categories: Food & Drink, Sports, Wellness, Culture, Technology, Education, Entertainment, Fashion, Music, Art

---

## Appendix C: Detailed Documentation Files

| File | Domain |
|------|--------|
| `mobile-auth-api-guide.md` | Authentication (register, login, Google, Apple, me, logout) |
| `mobile-password-reset-api.md` | Forgot password + reset password flow |
| `MOBILE_OPPORTUNITY_API.md` | Full opportunity API specification |
| `MOBILE_OPPORTUNITY_API_QUICK_REFERENCE.md` | Quick reference card for opportunities |
| `MOBILE_OPPORTUNITY_API_USER_FLOWS.md` | User flow examples for opportunities |
| `mobile-applications-api.md` | Application CRUD + accept/decline/withdraw |
| `mobile-accept-application-api.md` | Accepting applications in detail |
| `mobile-profile-subscription-guide.md` | Profile + Stripe subscription management |
| `mobile-subscription-api.md` | Subscription API details |
| `mobile-chat-api.md` | Chat messaging system |
| `mobile-notification-api.md` | In-app notification system |
| `mobile-push-notifications-api.md` | Firebase FCM push notifications |
| `mobile-gallery-api.md` | Profile gallery photos |
| `mobile-search-opportunities-api.md` | Search + explore opportunities |
| `mobile-opportunity-limit-guide.md` | Opportunity limits for unsubscribed users |
| `collaboration-api-mobile-docs.md` | Collaboration management |
| `mobile-events-api.md` | Events (gamification) |
| `mobile-gamification-api.md` | Gamification Phase 1 |
| `mobile-gamification-phase2-3-api.md` | Gamification Phase 2+3 (rewards, badges, leaderboard) |
| `mobile-dashboard-api.md` | Dashboard |
| `mobile-payment-integration.md` | Payment integration |
| `mobile-integration-guide.md` | Lookup tables + file upload |

---

*Generated: 2026-03-03 | API Version: 1.0 | Kolabing Backend v12*
