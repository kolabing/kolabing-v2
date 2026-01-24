# Kolabing Mobile App API Documentation

**Version:** 1.0.0
**Base URL:** `https://api.kolabing.com/api/v1`
**Last Updated:** 2026-01-24

---

## Table of Contents

1. [Authentication Flow](#authentication-flow)
2. [Endpoints](#endpoints)
3. [Error Handling](#error-handling)
4. [Best Practices](#best-practices)

---

## Authentication Flow

### Overview

Kolabing uses **Google OAuth** for authentication. The mobile app handles Google Sign-In and sends the ID token to the backend for verification.

```
┌──────────────┐     ┌─────────────┐     ┌─────────────┐
│  Mobile App  │     │   Google    │     │   Backend   │
└──────┬───────┘     └──────┬──────┘     └──────┬──────┘
       │                    │                   │
       │ 1. Google Sign-In  │                   │
       │───────────────────►│                   │
       │                    │                   │
       │ 2. ID Token        │                   │
       │◄───────────────────│                   │
       │                    │                   │
       │ 3. POST /auth/google                   │
       │    {id_token, user_type}               │
       │───────────────────────────────────────►│
       │                    │                   │
       │                    │ 4. Verify token   │
       │                    │◄──────────────────│
       │                    │                   │
       │ 5. {token, user, is_new_user}          │
       │◄───────────────────────────────────────│
       │                    │                   │
```

### User Types

- **business**: Business owners (restaurants, cafes, gyms, etc.)
- **community**: Content creators, influencers, local explorers

### Login Flow (Existing User)

1. User taps "Sign in with Google"
2. App gets Google ID token
3. App calls `POST /auth/google` with `{id_token, user_type}`
4. Backend returns `{token, user, is_new_user: false}`
5. App stores token and navigates to dashboard

### Registration Flow (New User)

1. User selects user type (Business or Community)
2. User taps "Sign in with Google"
3. App gets Google ID token
4. App calls `POST /auth/google` with `{id_token, user_type}`
5. Backend returns `{token, user, is_new_user: true}`
6. App stores token and navigates to **onboarding**
7. User completes onboarding form
8. App calls `PUT /onboarding/business` or `PUT /onboarding/community`
9. App navigates to dashboard

---

## Endpoints

### Authentication

#### POST /auth/google

Authenticate or register user with Google OAuth.

**Request:**
```json
{
  "id_token": "eyJhbGciOiJSUzI1...",
  "user_type": "business"
}
```

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "token": "1|abc123...",
    "token_type": "Bearer",
    "is_new_user": false,
    "user": {
      "id": "550e8400-e29b-41d4-a716-446655440000",
      "email": "user@example.com",
      "phone_number": "+34612345678",
      "user_type": "business",
      "avatar_url": "https://lh3.googleusercontent.com/...",
      "onboarding_completed": true,
      "created_at": "2026-01-15T08:00:00.000000Z"
    }
  }
}
```

**Mobile Implementation:**
```swift
// iOS (Swift)
func loginWithGoogle(idToken: String, userType: String) async throws -> AuthResponse {
    let url = URL(string: "\(baseURL)/auth/google")!
    var request = URLRequest(url: url)
    request.httpMethod = "POST"
    request.setValue("application/json", forHTTPHeaderField: "Content-Type")
    request.httpBody = try JSONEncoder().encode([
        "id_token": idToken,
        "user_type": userType
    ])

    let (data, _) = try await URLSession.shared.data(for: request)
    return try JSONDecoder().decode(AuthResponse.self, from: data)
}
```

```kotlin
// Android (Kotlin)
suspend fun loginWithGoogle(idToken: String, userType: String): AuthResponse {
    return apiService.googleLogin(
        GoogleLoginRequest(idToken = idToken, userType = userType)
    )
}
```

---

#### GET /auth/me

Get current authenticated user with full profile.

**Headers:**
```
Authorization: Bearer {token}
```

**Response (200 OK) - Business User:**
```json
{
  "success": true,
  "data": {
    "id": "550e8400-e29b-41d4-a716-446655440000",
    "email": "business@example.com",
    "phone_number": "+34612345678",
    "user_type": "business",
    "avatar_url": "https://lh3.googleusercontent.com/...",
    "onboarding_completed": true,
    "business_profile": {
      "id": "770e8400-...",
      "name": "Café Barcelona",
      "about": "Artisan coffee shop",
      "business_type": "cafe",
      "city": {
        "id": "880e8400-...",
        "name": "Barcelona",
        "country": "Spain"
      },
      "instagram": "cafebarcelona",
      "website": "https://cafebarcelona.com",
      "profile_photo": "https://storage.kolabing.com/..."
    },
    "subscription": {
      "status": "active",
      "current_period_end": "2026-02-15T08:00:00.000000Z",
      "cancel_at_period_end": false
    }
  }
}
```

**Response (200 OK) - Community User:**
```json
{
  "success": true,
  "data": {
    "id": "660e8400-...",
    "email": "creator@example.com",
    "user_type": "community",
    "onboarding_completed": true,
    "community_profile": {
      "id": "aa0e8400-...",
      "name": "Maria García",
      "about": "Food blogger",
      "community_type": "food_blogger",
      "city": {
        "id": "880e8400-...",
        "name": "Barcelona"
      },
      "instagram": "maria_food",
      "tiktok": "maria_food",
      "website": "https://mariafood.com",
      "profile_photo": "https://storage.kolabing.com/..."
    }
  }
}
```

---

#### POST /auth/logout

Logout and revoke current token.

**Headers:**
```
Authorization: Bearer {token}
```

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Logged out successfully"
}
```

---

### Onboarding

#### PUT /onboarding/business

Complete business user onboarding. **Business users only.**

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Request:**
```json
{
  "name": "Café Barcelona",
  "about": "Artisan coffee shop in the heart of Barcelona",
  "business_type": "cafe",
  "city_id": "880e8400-e29b-41d4-a716-446655440003",
  "phone_number": "+34612345678",
  "instagram": "cafebarcelona",
  "website": "https://cafebarcelona.com",
  "profile_photo": "data:image/jpeg;base64,/9j/4AAQ..."
}
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| name | string | Yes | Business name (max 255) |
| about | string | No | Description (max 1000) |
| business_type | string | Yes | From `/lookup/business-types` |
| city_id | uuid | Yes | From `/cities` |
| phone_number | string | No | E.164 format (+34...) |
| instagram | string | No | Handle without @ |
| website | string | No | Valid URL |
| profile_photo | string | No | Base64 or URL |

**Response (200 OK):**
```json
{
  "success": true,
  "message": "Business profile updated successfully",
  "data": {
    "id": "550e8400-...",
    "onboarding_completed": true,
    "business_profile": { ... }
  }
}
```

---

#### PUT /onboarding/community

Complete community user onboarding. **Community users only.**

**Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Request:**
```json
{
  "name": "Maria García",
  "about": "Food blogger and coffee enthusiast",
  "community_type": "food_blogger",
  "city_id": "880e8400-e29b-41d4-a716-446655440003",
  "phone_number": "+34698765432",
  "instagram": "maria_food",
  "tiktok": "maria_food",
  "website": "https://mariafood.com",
  "profile_photo": "data:image/jpeg;base64,/9j/4AAQ..."
}
```

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| name | string | Yes | Display name (max 255) |
| about | string | No | Bio (max 1000) |
| community_type | string | Yes | From `/lookup/community-types` |
| city_id | uuid | Yes | From `/cities` |
| phone_number | string | No | E.164 format |
| instagram | string | No | Handle without @ |
| tiktok | string | No | Handle without @ |
| website | string | No | Valid URL |
| profile_photo | string | No | Base64 or URL |

---

### Lookup Data

#### GET /cities

Get list of available cities.

**Response (200 OK):**
```json
{
  "success": true,
  "data": [
    { "id": "...", "name": "Barcelona", "country": "Spain" },
    { "id": "...", "name": "Madrid", "country": "Spain" },
    { "id": "...", "name": "Valencia", "country": "Spain" }
  ],
  "meta": { "total": 8 }
}
```

**Caching:** Cache for 24 hours.

---

#### GET /lookup/business-types

Get list of business types for onboarding.

**Response (200 OK):**
```json
{
  "success": true,
  "data": [
    { "value": "cafe", "label": "Café", "description": "Coffee shops" },
    { "value": "restaurant", "label": "Restaurant", "description": "Dining" },
    { "value": "bar", "label": "Bar", "description": "Bars and pubs" },
    { "value": "bakery", "label": "Bakery", "description": "Bakeries" },
    { "value": "coworking", "label": "Coworking Space", "description": "Shared workspace" },
    { "value": "gym", "label": "Gym/Fitness", "description": "Fitness centers" },
    { "value": "salon", "label": "Salon/Spa", "description": "Beauty" },
    { "value": "retail", "label": "Retail Store", "description": "Shops" },
    { "value": "hotel", "label": "Hotel/Accommodation", "description": "Lodging" },
    { "value": "other", "label": "Other", "description": "Other types" }
  ]
}
```

**Caching:** Cache for 7 days.

---

#### GET /lookup/community-types

Get list of community types for onboarding.

**Response (200 OK):**
```json
{
  "success": true,
  "data": [
    { "value": "food_blogger", "label": "Food Blogger", "description": "Food content" },
    { "value": "lifestyle_influencer", "label": "Lifestyle Influencer", "description": "Lifestyle" },
    { "value": "fitness_enthusiast", "label": "Fitness Enthusiast", "description": "Fitness" },
    { "value": "travel_blogger", "label": "Travel Blogger", "description": "Travel" },
    { "value": "photographer", "label": "Photographer", "description": "Photography" },
    { "value": "local_explorer", "label": "Local Explorer", "description": "City guides" },
    { "value": "student", "label": "Student", "description": "Students" },
    { "value": "professional", "label": "Professional", "description": "Professionals" },
    { "value": "community_organizer", "label": "Community Organizer", "description": "Events" },
    { "value": "other", "label": "Other", "description": "Other" }
  ]
}
```

**Caching:** Cache for 7 days.

---

## Error Handling

### Error Response Format

```json
{
  "success": false,
  "message": "Human-readable error message",
  "errors": {
    "field_name": ["Error message 1", "Error message 2"]
  }
}
```

### HTTP Status Codes

| Code | Description | Action |
|------|-------------|--------|
| 200 | Success | Process response |
| 401 | Unauthenticated | Clear token, redirect to login |
| 403 | Forbidden | Show access denied message |
| 409 | Conflict | User type mismatch - show error |
| 422 | Validation Error | Show field-level errors |
| 500 | Server Error | Show generic error, retry later |

### Common Error Scenarios

**Invalid Google Token (400):**
```json
{
  "success": false,
  "message": "Invalid Google ID token",
  "errors": {
    "id_token": ["The provided Google ID token is invalid or expired"]
  }
}
```

**User Type Mismatch (409):**
```json
{
  "success": false,
  "message": "User type mismatch",
  "errors": {
    "user_type": ["User already exists with a different user type"]
  }
}
```

**Validation Error (422):**
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "name": ["The name field is required"],
    "city_id": ["The selected city does not exist"]
  }
}
```

**Wrong User Type Access (403):**
```json
{
  "success": false,
  "message": "Access denied",
  "errors": {
    "user_type": ["This endpoint is only accessible to business users"]
  }
}
```

---

## Best Practices

### Token Storage

**iOS:**
```swift
// Store in Keychain
KeychainHelper.save(token, forKey: "auth_token")

// Retrieve
let token = KeychainHelper.get(forKey: "auth_token")
```

**Android:**
```kotlin
// Store in EncryptedSharedPreferences
val masterKey = MasterKey.Builder(context)
    .setKeyScheme(MasterKey.KeyScheme.AES256_GCM)
    .build()

val prefs = EncryptedSharedPreferences.create(
    context, "auth_prefs", masterKey,
    EncryptedSharedPreferences.PrefKeyEncryptionScheme.AES256_SIV,
    EncryptedSharedPreferences.PrefValueEncryptionScheme.AES256_GCM
)

prefs.edit().putString("auth_token", token).apply()
```

### Request Headers

All authenticated requests must include:
```
Authorization: Bearer {token}
Accept: application/json
Content-Type: application/json
```

### Onboarding Flow Detection

Check `onboarding_completed` flag after login:
```swift
if authResponse.data.isNewUser || !authResponse.data.user.onboardingCompleted {
    navigateToOnboarding()
} else {
    navigateToDashboard()
}
```

### Offline Caching

Cache lookup data locally:
- Cities: 24 hours TTL
- Business/Community types: 7 days TTL

### Image Upload

For profile photos, use base64 encoding:
```swift
let imageData = image.jpegData(compressionQuality: 0.8)
let base64 = "data:image/jpeg;base64," + imageData.base64EncodedString()
```

Maximum size: 5MB

### Token Expiration

Tokens expire after 30 days. Handle 401 responses:
```swift
if response.statusCode == 401 {
    clearStoredToken()
    navigateToLogin()
}
```

---

## Environment Configuration

### Development
```
Base URL: http://localhost:8000/api/v1
```

### Staging
```
Base URL: https://staging-api.kolabing.com/api/v1
```

### Production
```
Base URL: https://api.kolabing.com/api/v1
```

---

## Changelog

**v1.0.0** (2026-01-24)
- Initial release
- Google OAuth authentication
- Business and Community onboarding
- Lookup endpoints for cities and types
