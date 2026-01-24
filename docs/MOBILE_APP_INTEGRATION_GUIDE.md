# Kolabing Mobile App API Integration Guide

**Version:** 1.0.0
**API Version:** v1
**Base URL:** `https://api.kolabing.com/api/v1`
**Last Updated:** 2026-01-24

---

## Table of Contents

1. [Overview](#overview)
2. [User Flow Diagrams](#user-flow-diagrams)
3. [Quick Start Guide](#quick-start-guide)
4. [Authentication](#authentication)
5. [Complete API Reference](#complete-api-reference)
6. [Mobile Implementation Guide](#mobile-implementation-guide)
7. [Common Scenarios](#common-scenarios)
8. [Error Handling](#error-handling)
9. [Testing & Debugging](#testing--debugging)
10. [Best Practices](#best-practices)

---

## Overview

Kolabing API provides a RESTful interface for mobile applications to integrate authentication, user onboarding, and profile management. The API uses Laravel Sanctum for token-based authentication and Google OAuth for user authentication.

### Key Features

- **Google OAuth Authentication** - Seamless sign-in with Google
- **Dual User Types** - Business and Community users with separate onboarding flows
- **Profile Management** - Complete user profile with extended business/community data
- **Lookup Services** - Cities, business types, and community types reference data
- **Secure Token-Based Auth** - 30-day token expiration with Sanctum

### Technical Stack

- **Authentication**: Laravel Sanctum (Bearer tokens)
- **OAuth Provider**: Google Sign-In
- **Response Format**: JSON
- **Timestamp Format**: ISO 8601 with UTC timezone
- **ID Format**: UUID v4

---

## User Flow Diagrams

### 1. Business User Registration Flow

```
┌─────────────────────────────────────────────────────────────┐
│                    NEW BUSINESS USER                         │
└─────────────────────────────────────────────────────────────┘

  Mobile App                    Backend API                Google
      │                             │                          │
      │                             │                          │
      ├─ User taps "Sign in with Google"                      │
      │                             │                          │
      ├─ Google Sign-In SDK ────────┼─────────────────────────>│
      │                             │                          │
      │<────────────────────────────┼───── Google ID Token ────┤
      │                             │                          │
      │                             │                          │
      ├─ POST /auth/google ────────>│                          │
      │   {id_token, user_type}     │                          │
      │                             │                          │
      │                    ┌────────┴─────────┐                │
      │                    │ Verify ID token  │                │
      │                    │  with Google     │                │
      │                    └────────┬─────────┘                │
      │                             │                          │
      │                    ┌────────┴─────────┐                │
      │                    │ Create profile   │                │
      │                    │ Create business_ │                │
      │                    │   profile (empty)│                │
      │                    │ Create business_ │                │
      │                    │   subscription   │                │
      │                    └────────┬─────────┘                │
      │                             │                          │
      │<─── 200 OK ─────────────────┤                          │
      │   {token, user, is_new_user: true}                     │
      │   onboarding_completed: false                          │
      │                             │                          │
      │                             │                          │
      ├─ Store token securely       │                          │
      │                             │                          │
      ├─ Check onboarding_completed │                          │
      │   = false                   │                          │
      │                             │                          │
      ├─ Navigate to Onboarding     │                          │
      │                             │                          │
      │─────────────────────────────────────────────────────────
      │              ONBOARDING SCREEN                          │
      │─────────────────────────────────────────────────────────
      │                             │                          │
      ├─ Fetch cities ─────────────>│                          │
      │                             │                          │
      │<─── Cities list ────────────┤                          │
      │                             │                          │
      ├─ Fetch business types ─────>│                          │
      │                             │                          │
      │<─── Business types ─────────┤                          │
      │                             │                          │
      │                             │                          │
      ├─ User fills form            │                          │
      │   - Business name            │                          │
      │   - About                    │                          │
      │   - Business type            │                          │
      │   - City                     │                          │
      │   - Phone, Instagram, etc    │                          │
      │   - Profile photo            │                          │
      │                             │                          │
      │                             │                          │
      ├─ PUT /onboarding/business ─>│                          │
      │   Authorization: Bearer {token}                        │
      │                             │                          │
      │                    ┌────────┴─────────┐                │
      │                    │ Validate data    │                │
      │                    │ Upload photo     │                │
      │                    │ Update profile   │                │
      │                    │ Set onboarding_  │                │
      │                    │   completed=true │                │
      │                    └────────┬─────────┘                │
      │                             │                          │
      │<─── 200 OK ─────────────────┤                          │
      │   {user, onboarding_completed: true}                   │
      │                             │                          │
      ├─ Navigate to Home           │                          │
      │                             │                          │
      ▼                             ▼                          ▼
```

---

### 2. Community User Registration Flow

```
┌─────────────────────────────────────────────────────────────┐
│                   NEW COMMUNITY USER                         │
└─────────────────────────────────────────────────────────────┘

  Mobile App                    Backend API                Google
      │                             │                          │
      │                             │                          │
      ├─ User taps "Sign in with Google"                      │
      │                             │                          │
      ├─ Google Sign-In SDK ────────┼─────────────────────────>│
      │                             │                          │
      │<────────────────────────────┼───── Google ID Token ────┤
      │                             │                          │
      │                             │                          │
      ├─ POST /auth/google ────────>│                          │
      │   {id_token, user_type: "community"}                   │
      │                             │                          │
      │                    ┌────────┴─────────┐                │
      │                    │ Verify ID token  │                │
      │                    │  with Google     │                │
      │                    └────────┬─────────┘                │
      │                             │                          │
      │                    ┌────────┴─────────┐                │
      │                    │ Create profile   │                │
      │                    │ Create community_│                │
      │                    │   profile (empty)│                │
      │                    └────────┬─────────┘                │
      │                             │                          │
      │<─── 200 OK ─────────────────┤                          │
      │   {token, user, is_new_user: true}                     │
      │   onboarding_completed: false                          │
      │                             │                          │
      │                             │                          │
      ├─ Store token securely       │                          │
      │                             │                          │
      ├─ Navigate to Onboarding     │                          │
      │                             │                          │
      │─────────────────────────────────────────────────────────
      │              ONBOARDING SCREEN                          │
      │─────────────────────────────────────────────────────────
      │                             │                          │
      ├─ Fetch cities ─────────────>│                          │
      │                             │                          │
      │<─── Cities list ────────────┤                          │
      │                             │                          │
      ├─ Fetch community types ────>│                          │
      │                             │                          │
      │<─── Community types ────────┤                          │
      │                             │                          │
      │                             │                          │
      ├─ User fills form            │                          │
      │   - Display name             │                          │
      │   - About                    │                          │
      │   - Community type           │                          │
      │   - City                     │                          │
      │   - Social handles           │                          │
      │   - Profile photo            │                          │
      │                             │                          │
      │                             │                          │
      ├─ PUT /onboarding/community >│                          │
      │   Authorization: Bearer {token}                        │
      │                             │                          │
      │                    ┌────────┴─────────┐                │
      │                    │ Validate data    │                │
      │                    │ Upload photo     │                │
      │                    │ Update profile   │                │
      │                    │ Set onboarding_  │                │
      │                    │   completed=true │                │
      │                    └────────┬─────────┘                │
      │                             │                          │
      │<─── 200 OK ─────────────────┤                          │
      │   {user, onboarding_completed: true}                   │
      │                             │                          │
      ├─ Navigate to Home           │                          │
      │                             │                          │
      ▼                             ▼                          ▼
```

---

### 3. Returning User Login Flow

```
┌─────────────────────────────────────────────────────────────┐
│                    RETURNING USER LOGIN                      │
└─────────────────────────────────────────────────────────────┘

  Mobile App                    Backend API                Google
      │                             │                          │
      │                             │                          │
      ├─ App Launch                 │                          │
      │                             │                          │
      ├─ Check stored token         │                          │
      │                             │                          │
   ┌──┴──┐                          │                          │
   │Token│                          │                          │
   │Found│                          │                          │
   └──┬──┘                          │                          │
      │                             │                          │
      │   YES                       │                          │
      ├─ GET /auth/me ─────────────>│                          │
      │   Authorization: Bearer {token}                        │
      │                             │                          │
      │                    ┌────────┴─────────┐                │
      │                    │ Validate token   │                │
      │                    │ Load user data   │                │
      │                    └────────┬─────────┘                │
      │                             │                          │
   ┌──┴──┐                          │                          │
   │Valid│                          │                          │
   └──┬──┘                          │                          │
      │   YES                       │                          │
      │<─── 200 OK ─────────────────┤                          │
      │   {user, onboarding_completed: true}                   │
      │                             │                          │
      ├─ Navigate to Home           │                          │
      │                             │                          │
      │                             │                          │
      │   NO (401)                  │                          │
      │<─── 401 Unauthorized ───────┤                          │
      │                             │                          │
      ├─ Clear stored token         │                          │
      │                             │                          │
      ├─ Show login screen          │                          │
      │                             │                          │
      ├─ User taps "Sign in"        │                          │
      │                             │                          │
      ├─ Google Sign-In SDK ────────┼─────────────────────────>│
      │                             │                          │
      │<────────────────────────────┼───── Google ID Token ────┤
      │                             │                          │
      ├─ POST /auth/google ────────>│                          │
      │   {id_token, user_type}     │                          │
      │                             │                          │
      │                    ┌────────┴─────────┐                │
      │                    │ Find existing    │                │
      │                    │ user by google_id│                │
      │                    └────────┬─────────┘                │
      │                             │                          │
      │<─── 200 OK ─────────────────┤                          │
      │   {token, user, is_new_user: false}                    │
      │   onboarding_completed: true                           │
      │                             │                          │
      ├─ Store new token            │                          │
      │                             │                          │
      ├─ Navigate to Home           │                          │
      │                             │                          │
      ▼                             ▼                          ▼
```

---

### 4. Onboarding Completion Check Flow

```
┌─────────────────────────────────────────────────────────────┐
│              ONBOARDING COMPLETION LOGIC                     │
└─────────────────────────────────────────────────────────────┘

                    After Login
                        │
                        │
                        ▼
              ┌──────────────────┐
              │ GET /auth/me     │
              └────────┬─────────┘
                       │
                       ▼
         ┌─────────────────────────┐
         │ onboarding_completed?   │
         └───────┬─────────────┬───┘
                 │             │
            YES  │             │  NO
                 │             │
                 ▼             ▼
         ┌──────────┐   ┌─────────────┐
         │ Navigate │   │  Navigate   │
         │ to Home  │   │ to Onboarding│
         └──────────┘   └─────────────┘
                              │
                              ▼
                   ┌──────────────────────┐
                   │ Show onboarding form │
                   │ - Fetch lookups      │
                   │ - Collect data       │
                   └──────────┬───────────┘
                              │
                              ▼
                   ┌──────────────────────┐
                   │ PUT /onboarding/{type}│
                   └──────────┬───────────┘
                              │
                              ▼
                       ┌──────────────┐
                       │ 200 Success  │
                       │ onboarding_  │
                       │ completed=true│
                       └──────┬───────┘
                              │
                              ▼
                       ┌──────────┐
                       │ Navigate │
                       │ to Home  │
                       └──────────┘

Onboarding Completed Criteria:
  ✓ name is not null
  ✓ city_id is not null
  ✓ At least one of:
    - instagram
    - tiktok (community only)
    - website
    - phone_number
```

---

### 5. Session Management Flow

```
┌─────────────────────────────────────────────────────────────┐
│                   SESSION MANAGEMENT                         │
└─────────────────────────────────────────────────────────────┘

  Mobile App                    Backend API
      │                             │
      │                             │
      ├─ App Launch                 │
      │                             │
      ├─ Load token from            │
      │   secure storage            │
      │                             │
   ┌──┴──────────┐                  │
   │ Token exists?│                 │
   └──┬──────────┘                  │
      │                             │
      │  YES                        │
      │                             │
      ├─ Validate token ────────────>│
      │   GET /auth/me              │
      │                             │
   ┌──┴──────┐                      │
   │Response │                      │
   └──┬──────┘                      │
      │                             │
  ┌───┴────┬────────┐               │
  │        │        │               │
  │ 200 OK │ 401    │ Network Error │
  │        │        │               │
  ▼        ▼        ▼               │
┌────┐ ┌────────┐ ┌──────────┐     │
│Home│ │ Clear  │ │ Retry or │     │
│    │ │ token  │ │ Show     │     │
│    │ │ Show   │ │ cached   │     │
│    │ │ Login  │ │ data     │     │
└────┘ └────────┘ └──────────┘     │
                                    │
                                    │
      │  NO TOKEN                   │
      │                             │
      ▼                             │
  ┌────────┐                        │
  │ Show   │                        │
  │ Login  │                        │
  │ Screen │                        │
  └────────┘                        │
      │                             │
      ▼                             ▼

Token Lifecycle:
  - Created: On successful login
  - Stored: Encrypted secure storage
  - Expiry: 30 days from creation
  - Revoked: On logout or invalidation
  - Validation: Every app launch and API call
```

---

## Quick Start Guide

### Step 1: Set Up Google Sign-In SDK

**iOS (Swift)**
```swift
// Add Google Sign-In to your Podfile
// pod 'GoogleSignIn', '~> 7.0'

import GoogleSignIn

// Configure Google Sign-In
GIDSignIn.sharedInstance.configuration = GIDConfiguration(
    clientID: "YOUR_GOOGLE_CLIENT_ID.apps.googleusercontent.com"
)

// Sign in
GIDSignIn.sharedInstance.signIn(
    withPresenting: self
) { signInResult, error in
    guard let result = signInResult else { return }
    let idToken = result.user.idToken?.tokenString
    // Send idToken to your backend
}
```

**Android (Kotlin)**
```kotlin
// Add to build.gradle
// implementation 'com.google.android.gms:play-services-auth:20.7.0'

val gso = GoogleSignInOptions.Builder(GoogleSignInOptions.DEFAULT_SIGN_IN)
    .requestIdToken("YOUR_GOOGLE_CLIENT_ID.apps.googleusercontent.com")
    .requestEmail()
    .build()

val googleSignInClient = GoogleSignIn.getClient(this, gso)

// Sign in
val signInIntent = googleSignInClient.signInIntent
startActivityForResult(signInIntent, RC_SIGN_IN)

// In onActivityResult
override fun onActivityResult(requestCode: Int, resultCode: Int, data: Intent?) {
    if (requestCode == RC_SIGN_IN) {
        val task = GoogleSignIn.getSignedInAccountFromIntent(data)
        val account = task.getResult(ApiException::class.java)
        val idToken = account.idToken
        // Send idToken to your backend
    }
}
```

---

### Step 2: Configure API Client

**iOS (Swift) - Using URLSession**
```swift
class KolabingAPIClient {
    static let shared = KolabingAPIClient()
    private let baseURL = "https://api.kolabing.com/api/v1"

    private var authToken: String? {
        get { KeychainHelper.load(key: "auth_token") }
        set {
            if let token = newValue {
                KeychainHelper.save(key: "auth_token", data: token)
            } else {
                KeychainHelper.delete(key: "auth_token")
            }
        }
    }

    func request<T: Codable>(
        endpoint: String,
        method: String = "GET",
        body: Codable? = nil,
        authenticated: Bool = false
    ) async throws -> T {
        var request = URLRequest(url: URL(string: "\(baseURL)\(endpoint)")!)
        request.httpMethod = method
        request.setValue("application/json", forHTTPHeaderField: "Accept")
        request.setValue("application/json", forHTTPHeaderField: "Content-Type")

        if authenticated, let token = authToken {
            request.setValue("Bearer \(token)", forHTTPHeaderField: "Authorization")
        }

        if let body = body {
            request.httpBody = try JSONEncoder().encode(body)
        }

        let (data, response) = try await URLSession.shared.data(for: request)

        guard let httpResponse = response as? HTTPURLResponse else {
            throw APIError.invalidResponse
        }

        if httpResponse.statusCode == 401 {
            authToken = nil
            throw APIError.unauthorized
        }

        guard (200...299).contains(httpResponse.statusCode) else {
            throw APIError.httpError(statusCode: httpResponse.statusCode)
        }

        return try JSONDecoder().decode(T.self, from: data)
    }
}
```

**Android (Kotlin) - Using Retrofit**
```kotlin
// API Service Interface
interface KolabingAPI {
    @POST("auth/google")
    suspend fun googleAuth(@Body request: GoogleAuthRequest): ApiResponse<AuthData>

    @GET("auth/me")
    suspend fun getMe(): ApiResponse<User>

    @POST("auth/logout")
    suspend fun logout(): ApiResponse<Unit>

    @PUT("onboarding/business")
    suspend fun completeBusinessOnboarding(@Body request: BusinessOnboardingRequest): ApiResponse<User>

    @PUT("onboarding/community")
    suspend fun completeCommunityOnboarding(@Body request: CommunityOnboardingRequest): ApiResponse<User>

    @GET("cities")
    suspend fun getCities(): ApiResponse<List<City>>

    @GET("lookup/business-types")
    suspend fun getBusinessTypes(): ApiResponse<List<LookupItem>>

    @GET("lookup/community-types")
    suspend fun getCommunityTypes(): ApiResponse<List<LookupItem>>
}

// Retrofit Setup
object RetrofitClient {
    private const val BASE_URL = "https://api.kolabing.com/api/v1/"

    private val okHttpClient = OkHttpClient.Builder()
        .addInterceptor(AuthInterceptor())
        .addInterceptor(LoggingInterceptor())
        .build()

    val api: KolabingAPI = Retrofit.Builder()
        .baseUrl(BASE_URL)
        .client(okHttpClient)
        .addConverterFactory(GsonConverterFactory.create())
        .build()
        .create(KolabingAPI::class.java)
}

// Auth Interceptor
class AuthInterceptor : Interceptor {
    override fun intercept(chain: Interceptor.Chain): Response {
        val token = TokenManager.getToken()
        val request = chain.request().newBuilder()
            .addHeader("Accept", "application/json")
            .apply {
                if (token != null) {
                    addHeader("Authorization", "Bearer $token")
                }
            }
            .build()
        return chain.proceed(request)
    }
}
```

---

### Step 3: Implement Authentication Flow

**iOS (Swift)**
```swift
class AuthService {
    func signInWithGoogle(userType: String) async throws -> User {
        // Get Google ID token
        let result = try await withCheckedThrowingContinuation { continuation in
            GIDSignIn.sharedInstance.signIn(
                withPresenting: getRootViewController()
            ) { signInResult, error in
                if let error = error {
                    continuation.resume(throwing: error)
                } else if let result = signInResult {
                    continuation.resume(returning: result)
                }
            }
        }

        guard let idToken = result.user.idToken?.tokenString else {
            throw AuthError.noIdToken
        }

        // Send to backend
        struct GoogleAuthRequest: Codable {
            let id_token: String
            let user_type: String
        }

        struct AuthResponse: Codable {
            let success: Bool
            let message: String?
            let data: AuthData?
        }

        struct AuthData: Codable {
            let token: String
            let token_type: String
            let is_new_user: Bool
            let user: User
        }

        let request = GoogleAuthRequest(id_token: idToken, user_type: userType)
        let response: AuthResponse = try await KolabingAPIClient.shared.request(
            endpoint: "/auth/google",
            method: "POST",
            body: request
        )

        guard let data = response.data else {
            throw AuthError.invalidResponse
        }

        // Store token
        KolabingAPIClient.shared.authToken = data.token

        return data.user
    }

    func getCurrentUser() async throws -> User {
        struct UserResponse: Codable {
            let success: Bool
            let data: User
        }

        let response: UserResponse = try await KolabingAPIClient.shared.request(
            endpoint: "/auth/me",
            authenticated: true
        )

        return response.data
    }

    func logout() async throws {
        struct LogoutResponse: Codable {
            let success: Bool
            let message: String
        }

        let _: LogoutResponse = try await KolabingAPIClient.shared.request(
            endpoint: "/auth/logout",
            method: "POST",
            authenticated: true
        )

        // Clear local token
        KolabingAPIClient.shared.authToken = nil

        // Sign out from Google
        GIDSignIn.sharedInstance.signOut()
    }
}
```

**Android (Kotlin)**
```kotlin
class AuthRepository(private val api: KolabingAPI) {
    suspend fun signInWithGoogle(idToken: String, userType: String): Result<User> {
        return try {
            val request = GoogleAuthRequest(idToken, userType)
            val response = api.googleAuth(request)

            if (response.success && response.data != null) {
                // Store token
                TokenManager.saveToken(response.data.token)
                Result.success(response.data.user)
            } else {
                Result.failure(Exception(response.message ?: "Login failed"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun getCurrentUser(): Result<User> {
        return try {
            val response = api.getMe()
            if (response.success && response.data != null) {
                Result.success(response.data)
            } else {
                Result.failure(Exception(response.message ?: "Failed to get user"))
            }
        } catch (e: Exception) {
            Result.failure(e)
        }
    }

    suspend fun logout(): Result<Unit> {
        return try {
            api.logout()
            TokenManager.clearToken()
            Result.success(Unit)
        } catch (e: Exception) {
            Result.failure(e)
        }
    }
}
```

---

### Step 4: Implement Onboarding Flow

**iOS (Swift)**
```swift
class OnboardingService {
    func completeBusinessOnboarding(data: BusinessOnboardingData) async throws -> User {
        struct OnboardingResponse: Codable {
            let success: Bool
            let message: String
            let data: User
        }

        let response: OnboardingResponse = try await KolabingAPIClient.shared.request(
            endpoint: "/onboarding/business",
            method: "PUT",
            body: data,
            authenticated: true
        )

        return response.data
    }

    func completeCommunityOnboarding(data: CommunityOnboardingData) async throws -> User {
        struct OnboardingResponse: Codable {
            let success: Bool
            let message: String
            let data: User
        }

        let response: OnboardingResponse = try await KolabingAPIClient.shared.request(
            endpoint: "/onboarding/community",
            method: "PUT",
            body: data,
            authenticated: true
        )

        return response.data
    }
}

// Models
struct BusinessOnboardingData: Codable {
    let name: String
    let about: String?
    let business_type: String
    let city_id: String
    let phone_number: String?
    let instagram: String?
    let website: String?
    let profile_photo: String?
}

struct CommunityOnboardingData: Codable {
    let name: String
    let about: String?
    let community_type: String
    let city_id: String
    let phone_number: String?
    let instagram: String?
    let tiktok: String?
    let website: String?
    let profile_photo: String?
}
```

---

## Authentication

### Overview

Kolabing uses Google OAuth for user authentication combined with Laravel Sanctum for API token management. All API requests (except public endpoints) require a valid Bearer token.

### Token Management

**Token Lifecycle:**
- **Creation**: Generated on successful Google OAuth login
- **Expiration**: 30 days from creation
- **Storage**: Must be stored securely in device keychain/keystore
- **Revocation**: On logout or when invalidated by backend

**Token Format:**
```
{token_id}|{random_string}
Example: 1|xQwRtYpLkMnBvCxZ...
```

---

## Complete API Reference

### Base Information

**Base URL**: `https://api.kolabing.com/api/v1`

**Common Headers:**
```http
Accept: application/json
Content-Type: application/json
Authorization: Bearer {token}  # For authenticated endpoints only
```

**Response Format:**
All responses follow a consistent structure:
```json
{
  "success": true,
  "message": "Optional message",
  "data": {},
  "meta": {}
}
```

---

### 1. Authentication Endpoints

#### 1.1 Google OAuth Login/Register

**Endpoint:** `POST /api/v1/auth/google`

**Description:** Authenticates or registers a user via Google OAuth. This is the primary authentication endpoint for the mobile app.

**Authentication:** None (public endpoint)

**Request Headers:**
```http
Content-Type: application/json
Accept: application/json
```

**Request Body:**
```json
{
  "id_token": "eyJhbGciOiJSUzI1NiIsImtpZCI6IjY4YTk4...",
  "user_type": "business"
}
```

**Field Validation:**
| Field | Type | Required | Validation |
|-------|------|----------|------------|
| `id_token` | string | Yes | Valid Google ID token (JWT format) |
| `user_type` | string | Yes | Must be `"business"` or `"community"` |

**Success Response (200 OK) - New User:**
```json
{
  "success": true,
  "message": "Registration successful",
  "data": {
    "token": "2|xQwRtYpLkMnBvCxZ123456789",
    "token_type": "Bearer",
    "is_new_user": true,
    "user": {
      "id": "660e8400-e29b-41d4-a716-446655440001",
      "email": "newuser@example.com",
      "phone_number": null,
      "user_type": "business",
      "avatar_url": "https://lh3.googleusercontent.com/a/ACg8ocK...",
      "email_verified_at": "2026-01-24T12:00:00.000000Z",
      "onboarding_completed": false,
      "created_at": "2026-01-24T12:00:00.000000Z",
      "updated_at": "2026-01-24T12:00:00.000000Z"
    }
  }
}
```

**Success Response (200 OK) - Existing User:**
```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "token": "1|aBcDeFgHiJkLmNoPqRsTuVwXyZ",
    "token_type": "Bearer",
    "is_new_user": false,
    "user": {
      "id": "550e8400-e29b-41d4-a716-446655440000",
      "email": "user@example.com",
      "phone_number": "+34612345678",
      "user_type": "business",
      "avatar_url": "https://lh3.googleusercontent.com/a/ACg8ocK...",
      "email_verified_at": "2026-01-20T10:30:00.000000Z",
      "onboarding_completed": true,
      "created_at": "2026-01-15T08:00:00.000000Z",
      "updated_at": "2026-01-20T10:30:00.000000Z"
    }
  }
}
```

**Error Response (400 Bad Request) - Invalid Token:**
```json
{
  "success": false,
  "message": "Invalid Google ID token",
  "errors": {
    "id_token": ["The provided Google ID token is invalid or expired"]
  }
}
```

**Error Response (422 Unprocessable Entity) - Validation Error:**
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "user_type": ["The user type field is required"],
    "id_token": ["The id token field is required"]
  }
}
```

**Error Response (409 Conflict) - User Type Mismatch:**
```json
{
  "success": false,
  "message": "User type mismatch",
  "errors": {
    "user_type": ["User already exists with a different user type"]
  }
}
```

**cURL Example:**
```bash
curl -X POST https://api.kolabing.com/api/v1/auth/google \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "id_token": "eyJhbGciOiJSUzI1NiIsImtpZCI6IjY4YTk4...",
    "user_type": "business"
  }'
```

**Mobile Implementation Notes:**
- Always use the ID token from Google Sign-In SDK, never access tokens
- Store the returned token securely in Keychain (iOS) or EncryptedSharedPreferences (Android)
- Check `is_new_user` to determine if onboarding is needed
- Check `onboarding_completed` to determine navigation flow
- If user exists with different user_type, show appropriate error message

---

#### 1.2 Get Authenticated User

**Endpoint:** `GET /api/v1/auth/me`

**Description:** Retrieves the complete profile of the currently authenticated user, including extended profile data.

**Authentication:** Required (Bearer token)

**Request Headers:**
```http
Authorization: Bearer {token}
Accept: application/json
```

**Request Body:** None

**Success Response (200 OK) - Business User:**
```json
{
  "success": true,
  "data": {
    "id": "550e8400-e29b-41d4-a716-446655440000",
    "email": "business@example.com",
    "phone_number": "+34612345678",
    "user_type": "business",
    "avatar_url": "https://lh3.googleusercontent.com/a/ACg8ocK...",
    "email_verified_at": "2026-01-20T10:30:00.000000Z",
    "onboarding_completed": true,
    "created_at": "2026-01-15T08:00:00.000000Z",
    "updated_at": "2026-01-23T14:20:00.000000Z",
    "business_profile": {
      "id": "770e8400-e29b-41d4-a716-446655440002",
      "name": "Café Barcelona",
      "about": "Artisan coffee shop in the heart of Barcelona",
      "business_type": "cafe",
      "city": {
        "id": "880e8400-e29b-41d4-a716-446655440003",
        "name": "Barcelona",
        "country": "Spain"
      },
      "instagram": "cafebarcelona",
      "website": "https://cafebarcelona.com",
      "profile_photo": "https://storage.kolabing.com/profiles/cafe-photo.jpg",
      "created_at": "2026-01-15T08:00:00.000000Z",
      "updated_at": "2026-01-20T11:00:00.000000Z"
    },
    "subscription": {
      "id": "990e8400-e29b-41d4-a716-446655440004",
      "status": "active",
      "current_period_start": "2026-01-15T08:00:00.000000Z",
      "current_period_end": "2026-02-15T08:00:00.000000Z",
      "cancel_at_period_end": false
    }
  }
}
```

**Success Response (200 OK) - Community User:**
```json
{
  "success": true,
  "data": {
    "id": "660e8400-e29b-41d4-a716-446655440001",
    "email": "community@example.com",
    "phone_number": "+34698765432",
    "user_type": "community",
    "avatar_url": "https://lh3.googleusercontent.com/a/ACg8ocK...",
    "email_verified_at": "2026-01-22T09:15:00.000000Z",
    "onboarding_completed": true,
    "created_at": "2026-01-22T09:15:00.000000Z",
    "updated_at": "2026-01-22T10:00:00.000000Z",
    "community_profile": {
      "id": "aa0e8400-e29b-41d4-a716-446655440005",
      "name": "Maria García",
      "about": "Food blogger and coffee enthusiast",
      "community_type": "food_blogger",
      "city": {
        "id": "880e8400-e29b-41d4-a716-446655440003",
        "name": "Barcelona",
        "country": "Spain"
      },
      "instagram": "maria_food_bcn",
      "tiktok": "maria_food",
      "website": "https://mariafoodblog.com",
      "profile_photo": "https://storage.kolabing.com/profiles/maria-photo.jpg",
      "is_featured": false,
      "created_at": "2026-01-22T09:15:00.000000Z",
      "updated_at": "2026-01-22T10:00:00.000000Z"
    }
  }
}
```

**Success Response (200 OK) - Incomplete Onboarding:**
```json
{
  "success": true,
  "data": {
    "id": "bb0e8400-e29b-41d4-a716-446655440006",
    "email": "newuser@example.com",
    "phone_number": null,
    "user_type": "business",
    "avatar_url": "https://lh3.googleusercontent.com/a/ACg8ocK...",
    "email_verified_at": "2026-01-24T12:00:00.000000Z",
    "onboarding_completed": false,
    "created_at": "2026-01-24T12:00:00.000000Z",
    "updated_at": "2026-01-24T12:00:00.000000Z",
    "business_profile": {
      "id": "cc0e8400-e29b-41d4-a716-446655440007",
      "name": null,
      "about": null,
      "business_type": null,
      "city": null,
      "instagram": null,
      "website": null,
      "profile_photo": null,
      "created_at": "2026-01-24T12:00:00.000000Z",
      "updated_at": "2026-01-24T12:00:00.000000Z"
    },
    "subscription": {
      "id": "dd0e8400-e29b-41d4-a716-446655440008",
      "status": "inactive",
      "current_period_start": null,
      "current_period_end": null,
      "cancel_at_period_end": false
    }
  }
}
```

**Error Response (401 Unauthorized):**
```json
{
  "success": false,
  "message": "Unauthenticated",
  "errors": {
    "auth": ["Authentication token is invalid or expired"]
  }
}
```

**cURL Example:**
```bash
curl -X GET https://api.kolabing.com/api/v1/auth/me \
  -H "Authorization: Bearer 1|aBcDeFgHiJkLmNoPqRsTuVwXyZ" \
  -H "Accept: application/json"
```

**Mobile Implementation Notes:**
- Call this endpoint on app launch to validate stored token
- Use to check onboarding status and navigate accordingly
- Cache user data locally but always refresh on app launch
- Handle 401 by clearing token and redirecting to login

---

#### 1.3 Logout

**Endpoint:** `POST /api/v1/auth/logout`

**Description:** Revokes the current user's authentication token and logs them out.

**Authentication:** Required (Bearer token)

**Request Headers:**
```http
Authorization: Bearer {token}
Accept: application/json
```

**Request Body:** None

**Success Response (200 OK):**
```json
{
  "success": true,
  "message": "Logged out successfully"
}
```

**Error Response (401 Unauthorized):**
```json
{
  "success": false,
  "message": "Unauthenticated",
  "errors": {
    "auth": ["Authentication token is invalid or expired"]
  }
}
```

**cURL Example:**
```bash
curl -X POST https://api.kolabing.com/api/v1/auth/logout \
  -H "Authorization: Bearer 1|aBcDeFgHiJkLmNoPqRsTuVwXyZ" \
  -H "Accept: application/json"
```

**Mobile Implementation Notes:**
- Always call this endpoint before clearing local token
- Clear all cached user data after successful logout
- Sign out from Google Sign-In SDK as well
- Navigate to login screen after logout

---

### 2. Onboarding Endpoints

#### 2.1 Complete Business Onboarding

**Endpoint:** `PUT /api/v1/onboarding/business`

**Description:** Updates business profile with onboarding information. Completes the onboarding flow for business users.

**Authentication:** Required (Bearer token, user_type must be 'business')

**Request Headers:**
```http
Authorization: Bearer {token}
Content-Type: application/json
Accept: application/json
```

**Request Body:**
```json
{
  "name": "Café Barcelona",
  "about": "Artisan coffee shop in the heart of Barcelona",
  "business_type": "cafe",
  "city_id": "880e8400-e29b-41d4-a716-446655440003",
  "phone_number": "+34612345678",
  "instagram": "cafebarcelona",
  "website": "https://cafebarcelona.com",
  "profile_photo": "data:image/jpeg;base64,/9j/4AAQSkZJRgABAQEAYABgAAD..."
}
```

**Field Validation:**
| Field | Type | Required | Validation |
|-------|------|----------|------------|
| `name` | string | Yes | 1-255 characters, business name |
| `about` | string | No | Max 1000 characters |
| `business_type` | string | Yes | Must match value from `/lookup/business-types` |
| `city_id` | UUID | Yes | Must exist in cities table |
| `phone_number` | string | No | E.164 format (e.g., +34612345678) |
| `instagram` | string | No | Max 255 chars, alphanumeric/dots/underscores |
| `website` | string | No | Valid URL, max 255 chars |
| `profile_photo` | string | No | Base64 image (jpg/png, max 5MB) or URL |

**Success Response (200 OK):**
```json
{
  "success": true,
  "message": "Business profile updated successfully",
  "data": {
    "id": "550e8400-e29b-41d4-a716-446655440000",
    "email": "business@example.com",
    "phone_number": "+34612345678",
    "user_type": "business",
    "avatar_url": "https://lh3.googleusercontent.com/a/ACg8ocK...",
    "email_verified_at": "2026-01-20T10:30:00.000000Z",
    "onboarding_completed": true,
    "created_at": "2026-01-15T08:00:00.000000Z",
    "updated_at": "2026-01-24T15:30:00.000000Z",
    "business_profile": {
      "id": "770e8400-e29b-41d4-a716-446655440002",
      "name": "Café Barcelona",
      "about": "Artisan coffee shop in the heart of Barcelona",
      "business_type": "cafe",
      "city": {
        "id": "880e8400-e29b-41d4-a716-446655440003",
        "name": "Barcelona",
        "country": "Spain"
      },
      "instagram": "cafebarcelona",
      "website": "https://cafebarcelona.com",
      "profile_photo": "https://storage.kolabing.com/profiles/cafe-photo.jpg",
      "created_at": "2026-01-15T08:00:00.000000Z",
      "updated_at": "2026-01-24T15:30:00.000000Z"
    },
    "subscription": {
      "id": "990e8400-e29b-41d4-a716-446655440004",
      "status": "inactive",
      "current_period_start": null,
      "current_period_end": null,
      "cancel_at_period_end": false
    }
  }
}
```

**Error Response (401 Unauthorized):**
```json
{
  "success": false,
  "message": "Unauthenticated",
  "errors": {
    "auth": ["Authentication token is invalid or expired"]
  }
}
```

**Error Response (403 Forbidden) - Wrong User Type:**
```json
{
  "success": false,
  "message": "Access denied",
  "errors": {
    "user_type": ["This endpoint is only accessible to business users"]
  }
}
```

**Error Response (422 Unprocessable Entity) - Validation Errors:**
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "name": ["The name field is required"],
    "business_type": ["The selected business type is invalid"],
    "city_id": ["The selected city does not exist"],
    "phone_number": ["The phone number format is invalid"],
    "website": ["The website must be a valid URL"],
    "profile_photo": ["The profile photo must not exceed 5MB"]
  }
}
```

**cURL Example:**
```bash
curl -X PUT https://api.kolabing.com/api/v1/onboarding/business \
  -H "Authorization: Bearer 1|aBcDeFgHiJkLmNoPqRsTuVwXyZ" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "name": "Café Barcelona",
    "about": "Artisan coffee shop",
    "business_type": "cafe",
    "city_id": "880e8400-e29b-41d4-a716-446655440003",
    "phone_number": "+34612345678",
    "instagram": "cafebarcelona",
    "website": "https://cafebarcelona.com"
  }'
```

**Mobile Implementation Notes:**
- Strip @ symbol from Instagram handle if user includes it
- Validate phone number format before sending
- For profile_photo: convert image to base64 or upload separately and send URL
- Handle validation errors by highlighting specific form fields
- After success, navigate to home screen

---

#### 2.2 Complete Community Onboarding

**Endpoint:** `PUT /api/v1/onboarding/community`

**Description:** Updates community profile with onboarding information. Completes the onboarding flow for community users.

**Authentication:** Required (Bearer token, user_type must be 'community')

**Request Headers:**
```http
Authorization: Bearer {token}
Content-Type: application/json
Accept: application/json
```

**Request Body:**
```json
{
  "name": "Maria García",
  "about": "Food blogger and coffee enthusiast",
  "community_type": "food_blogger",
  "city_id": "880e8400-e29b-41d4-a716-446655440003",
  "phone_number": "+34698765432",
  "instagram": "maria_food_bcn",
  "tiktok": "maria_food",
  "website": "https://mariafoodblog.com",
  "profile_photo": "data:image/jpeg;base64,/9j/4AAQSkZJRgABAQEAYABgAAD..."
}
```

**Field Validation:**
| Field | Type | Required | Validation |
|-------|------|----------|------------|
| `name` | string | Yes | 1-255 characters, display name |
| `about` | string | No | Max 1000 characters |
| `community_type` | string | Yes | Must match value from `/lookup/community-types` |
| `city_id` | UUID | Yes | Must exist in cities table |
| `phone_number` | string | No | E.164 format (e.g., +34698765432) |
| `instagram` | string | No | Max 255 chars, alphanumeric/dots/underscores |
| `tiktok` | string | No | Max 255 chars, alphanumeric/dots/underscores |
| `website` | string | No | Valid URL, max 255 chars |
| `profile_photo` | string | No | Base64 image (jpg/png, max 5MB) or URL |

**Success Response (200 OK):**
```json
{
  "success": true,
  "message": "Community profile updated successfully",
  "data": {
    "id": "660e8400-e29b-41d4-a716-446655440001",
    "email": "community@example.com",
    "phone_number": "+34698765432",
    "user_type": "community",
    "avatar_url": "https://lh3.googleusercontent.com/a/ACg8ocK...",
    "email_verified_at": "2026-01-22T09:15:00.000000Z",
    "onboarding_completed": true,
    "created_at": "2026-01-22T09:15:00.000000Z",
    "updated_at": "2026-01-24T16:45:00.000000Z",
    "community_profile": {
      "id": "aa0e8400-e29b-41d4-a716-446655440005",
      "name": "Maria García",
      "about": "Food blogger and coffee enthusiast",
      "community_type": "food_blogger",
      "city": {
        "id": "880e8400-e29b-41d4-a716-446655440003",
        "name": "Barcelona",
        "country": "Spain"
      },
      "instagram": "maria_food_bcn",
      "tiktok": "maria_food",
      "website": "https://mariafoodblog.com",
      "profile_photo": "https://storage.kolabing.com/profiles/maria-photo.jpg",
      "is_featured": false,
      "created_at": "2026-01-22T09:15:00.000000Z",
      "updated_at": "2026-01-24T16:45:00.000000Z"
    }
  }
}
```

**Error Response (403 Forbidden) - Wrong User Type:**
```json
{
  "success": false,
  "message": "Access denied",
  "errors": {
    "user_type": ["This endpoint is only accessible to community users"]
  }
}
```

**Error Response (422 Unprocessable Entity) - Validation Errors:**
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "name": ["The name field is required"],
    "community_type": ["The selected community type is invalid"],
    "city_id": ["The selected city does not exist"],
    "phone_number": ["The phone number format is invalid"],
    "tiktok": ["The tiktok handle format is invalid"],
    "website": ["The website must be a valid URL"]
  }
}
```

**cURL Example:**
```bash
curl -X PUT https://api.kolabing.com/api/v1/onboarding/community \
  -H "Authorization: Bearer 2|xQwRtYpLkMnBvCxZ123456789" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "name": "Maria García",
    "about": "Food blogger and coffee enthusiast",
    "community_type": "food_blogger",
    "city_id": "880e8400-e29b-41d4-a716-446655440003",
    "instagram": "maria_food_bcn",
    "tiktok": "maria_food"
  }'
```

**Mobile Implementation Notes:**
- Strip @ symbol from Instagram/TikTok handles if user includes it
- At least one social contact field (instagram, tiktok, website, phone_number) is required
- Community users have TikTok field that business users don't have
- After success, navigate to home screen

---

### 3. Lookup/Reference Endpoints

#### 3.1 Get Cities List

**Endpoint:** `GET /api/v1/cities`

**Description:** Retrieves the list of available cities for user selection during onboarding.

**Authentication:** None (public endpoint)

**Request Headers:**
```http
Accept: application/json
```

**Success Response (200 OK):**
```json
{
  "success": true,
  "data": [
    {
      "id": "880e8400-e29b-41d4-a716-446655440003",
      "name": "Barcelona",
      "country": "Spain"
    },
    {
      "id": "990e8400-e29b-41d4-a716-446655440009",
      "name": "Madrid",
      "country": "Spain"
    },
    {
      "id": "aa0e8400-e29b-41d4-a716-446655440010",
      "name": "Valencia",
      "country": "Spain"
    },
    {
      "id": "bb0e8400-e29b-41d4-a716-446655440011",
      "name": "Seville",
      "country": "Spain"
    },
    {
      "id": "cc0e8400-e29b-41d4-a716-446655440012",
      "name": "Bilbao",
      "country": "Spain"
    }
  ],
  "meta": {
    "total": 5
  }
}
```

**cURL Example:**
```bash
curl -X GET https://api.kolabing.com/api/v1/cities \
  -H "Accept: application/json"
```

**Mobile Implementation Notes:**
- Cache this data locally with 24-hour TTL
- Use for dropdown/picker in onboarding form
- Sort alphabetically in UI
- No pagination needed (limited dataset)

---

#### 3.2 Get Business Types

**Endpoint:** `GET /api/v1/lookup/business-types`

**Description:** Retrieves the list of available business types for business user onboarding.

**Authentication:** None (public endpoint)

**Request Headers:**
```http
Accept: application/json
```

**Success Response (200 OK):**
```json
{
  "success": true,
  "data": [
    {
      "value": "cafe",
      "label": "Café",
      "description": "Coffee shops and cafeterias"
    },
    {
      "value": "restaurant",
      "label": "Restaurant",
      "description": "Restaurants and dining establishments"
    },
    {
      "value": "bar",
      "label": "Bar",
      "description": "Bars and pubs"
    },
    {
      "value": "bakery",
      "label": "Bakery",
      "description": "Bakeries and pastry shops"
    },
    {
      "value": "coworking",
      "label": "Coworking Space",
      "description": "Shared workspace and coworking facilities"
    },
    {
      "value": "gym",
      "label": "Gym/Fitness",
      "description": "Gyms and fitness centers"
    },
    {
      "value": "salon",
      "label": "Salon/Spa",
      "description": "Hair salons, beauty salons, and spas"
    },
    {
      "value": "retail",
      "label": "Retail Store",
      "description": "Retail shops and boutiques"
    },
    {
      "value": "hotel",
      "label": "Hotel/Accommodation",
      "description": "Hotels, hostels, and accommodations"
    },
    {
      "value": "other",
      "label": "Other",
      "description": "Other business types"
    }
  ],
  "meta": {
    "total": 10
  }
}
```

**cURL Example:**
```bash
curl -X GET https://api.kolabing.com/api/v1/lookup/business-types \
  -H "Accept: application/json"
```

**Mobile Implementation Notes:**
- Cache this data locally with 7-day TTL
- Display `label` in UI, send `value` to backend
- Use `description` for tooltips or help text
- Use for picker/dropdown in business onboarding

---

#### 3.3 Get Community Types

**Endpoint:** `GET /api/v1/lookup/community-types`

**Description:** Retrieves the list of available community types for community user onboarding.

**Authentication:** None (public endpoint)

**Request Headers:**
```http
Accept: application/json
```

**Success Response (200 OK):**
```json
{
  "success": true,
  "data": [
    {
      "value": "food_blogger",
      "label": "Food Blogger",
      "description": "Food and dining content creators"
    },
    {
      "value": "lifestyle_influencer",
      "label": "Lifestyle Influencer",
      "description": "Lifestyle and general content influencers"
    },
    {
      "value": "fitness_enthusiast",
      "label": "Fitness Enthusiast",
      "description": "Fitness and wellness content creators"
    },
    {
      "value": "travel_blogger",
      "label": "Travel Blogger",
      "description": "Travel and tourism content creators"
    },
    {
      "value": "photographer",
      "label": "Photographer",
      "description": "Professional and hobbyist photographers"
    },
    {
      "value": "local_explorer",
      "label": "Local Explorer",
      "description": "City guides and local experience creators"
    },
    {
      "value": "student",
      "label": "Student",
      "description": "University and college students"
    },
    {
      "value": "professional",
      "label": "Professional",
      "description": "Working professionals and freelancers"
    },
    {
      "value": "community_organizer",
      "label": "Community Organizer",
      "description": "Event organizers and community builders"
    },
    {
      "value": "other",
      "label": "Other",
      "description": "Other community member types"
    }
  ],
  "meta": {
    "total": 10
  }
}
```

**cURL Example:**
```bash
curl -X GET https://api.kolabing.com/api/v1/lookup/community-types \
  -H "Accept: application/json"
```

**Mobile Implementation Notes:**
- Cache this data locally with 7-day TTL
- Display `label` in UI, send `value` to backend
- Use `description` for tooltips or help text
- Use for picker/dropdown in community onboarding

---

## Mobile Implementation Guide

### Token Storage

#### iOS - Keychain Storage

```swift
import Security
import Foundation

class KeychainHelper {
    static func save(key: String, data: String) {
        let data = data.data(using: .utf8)!

        let query: [String: Any] = [
            kSecClass as String: kSecClassGenericPassword,
            kSecAttrAccount as String: key,
            kSecValueData as String: data
        ]

        SecItemDelete(query as CFDictionary)
        SecItemAdd(query as CFDictionary, nil)
    }

    static func load(key: String) -> String? {
        let query: [String: Any] = [
            kSecClass as String: kSecClassGenericPassword,
            kSecAttrAccount as String: key,
            kSecReturnData as String: true,
            kSecMatchLimit as String: kSecMatchLimitOne
        ]

        var dataTypeRef: AnyObject?
        let status = SecItemCopyMatching(query as CFDictionary, &dataTypeRef)

        if status == errSecSuccess {
            if let data = dataTypeRef as? Data {
                return String(data: data, encoding: .utf8)
            }
        }

        return nil
    }

    static func delete(key: String) {
        let query: [String: Any] = [
            kSecClass as String: kSecClassGenericPassword,
            kSecAttrAccount as String: key
        ]

        SecItemDelete(query as CFDictionary)
    }
}

// Usage
KeychainHelper.save(key: "auth_token", data: token)
let token = KeychainHelper.load(key: "auth_token")
KeychainHelper.delete(key: "auth_token")
```

#### Android - EncryptedSharedPreferences

```kotlin
import androidx.security.crypto.EncryptedSharedPreferences
import androidx.security.crypto.MasterKey
import android.content.Context

class SecureStorage(context: Context) {
    private val masterKey = MasterKey.Builder(context)
        .setKeyScheme(MasterKey.KeyScheme.AES256_GCM)
        .build()

    private val sharedPreferences = EncryptedSharedPreferences.create(
        context,
        "kolabing_secure_prefs",
        masterKey,
        EncryptedSharedPreferences.PrefKeyEncryptionScheme.AES256_SIV,
        EncryptedSharedPreferences.PrefValueEncryptionScheme.AES256_GCM
    )

    fun saveToken(token: String) {
        sharedPreferences.edit().putString("auth_token", token).apply()
    }

    fun getToken(): String? {
        return sharedPreferences.getString("auth_token", null)
    }

    fun clearToken() {
        sharedPreferences.edit().remove("auth_token").apply()
    }
}

// Usage
val secureStorage = SecureStorage(context)
secureStorage.saveToken(token)
val token = secureStorage.getToken()
secureStorage.clearToken()
```

---

### Error Handling Patterns

#### iOS - Error Handling

```swift
enum APIError: Error, LocalizedError {
    case unauthorized
    case forbidden
    case validationError(errors: [String: [String]])
    case networkError(Error)
    case httpError(statusCode: Int)
    case invalidResponse
    case decodingError(Error)

    var errorDescription: String? {
        switch self {
        case .unauthorized:
            return "Your session has expired. Please sign in again."
        case .forbidden:
            return "You don't have permission to access this resource."
        case .validationError(let errors):
            let messages = errors.flatMap { $0.value }.joined(separator: "\n")
            return messages
        case .networkError:
            return "Network connection error. Please check your internet connection."
        case .httpError(let code):
            return "Server error (Code: \(code)). Please try again later."
        case .invalidResponse:
            return "Invalid server response. Please try again."
        case .decodingError:
            return "Failed to process server response."
        }
    }
}

// Error handling in ViewModel
func login(idToken: String, userType: String) async {
    do {
        let user = try await authService.signInWithGoogle(
            idToken: idToken,
            userType: userType
        )

        if user.onboardingCompleted {
            navigateToHome()
        } else {
            navigateToOnboarding()
        }
    } catch APIError.unauthorized {
        showError("Session expired. Please try again.")
    } catch APIError.validationError(let errors) {
        showValidationErrors(errors)
    } catch APIError.networkError {
        showError("Please check your internet connection.")
    } catch {
        showError("An unexpected error occurred. Please try again.")
    }
}
```

#### Android - Error Handling

```kotlin
sealed class ApiResult<out T> {
    data class Success<T>(val data: T) : ApiResult<T>()
    data class Error(val exception: Exception) : ApiResult<Nothing>()
}

sealed class ApiException(message: String) : Exception(message) {
    object Unauthorized : ApiException("Your session has expired")
    object Forbidden : ApiException("Access denied")
    data class ValidationError(val errors: Map<String, List<String>>) :
        ApiException("Validation failed")
    data class HttpError(val code: Int) : ApiException("Server error: $code")
    object NetworkError : ApiException("Network connection error")
    object InvalidResponse : ApiException("Invalid server response")
}

// Error handling in ViewModel
fun login(idToken: String, userType: String) {
    viewModelScope.launch {
        _uiState.value = UiState.Loading

        when (val result = authRepository.signInWithGoogle(idToken, userType)) {
            is ApiResult.Success -> {
                val user = result.data
                if (user.onboardingCompleted) {
                    navigateToHome()
                } else {
                    navigateToOnboarding()
                }
            }
            is ApiResult.Error -> {
                val message = when (val exception = result.exception) {
                    is ApiException.Unauthorized ->
                        "Session expired. Please try again."
                    is ApiException.ValidationError ->
                        exception.errors.values.flatten().joinToString("\n")
                    is ApiException.NetworkError ->
                        "Please check your internet connection."
                    else ->
                        "An error occurred. Please try again."
                }
                _uiState.value = UiState.Error(message)
            }
        }
    }
}
```

---

### Network Layer Setup

#### iOS - URLSession + Async/Await

```swift
class NetworkManager {
    static let shared = NetworkManager()
    private let baseURL = "https://api.kolabing.com/api/v1"
    private let decoder: JSONDecoder

    init() {
        decoder = JSONDecoder()
        decoder.dateDecodingStrategy = .iso8601
    }

    func request<T: Codable>(
        endpoint: String,
        method: String = "GET",
        body: Codable? = nil,
        authenticated: Bool = false
    ) async throws -> T {
        guard let url = URL(string: "\(baseURL)\(endpoint)") else {
            throw APIError.invalidResponse
        }

        var request = URLRequest(url: url)
        request.httpMethod = method
        request.setValue("application/json", forHTTPHeaderField: "Accept")

        if let body = body {
            request.setValue("application/json", forHTTPHeaderField: "Content-Type")
            request.httpBody = try JSONEncoder().encode(body)
        }

        if authenticated {
            guard let token = KeychainHelper.load(key: "auth_token") else {
                throw APIError.unauthorized
            }
            request.setValue("Bearer \(token)", forHTTPHeaderField: "Authorization")
        }

        do {
            let (data, response) = try await URLSession.shared.data(for: request)

            guard let httpResponse = response as? HTTPURLResponse else {
                throw APIError.invalidResponse
            }

            switch httpResponse.statusCode {
            case 200...299:
                return try decoder.decode(T.self, from: data)
            case 401:
                KeychainHelper.delete(key: "auth_token")
                throw APIError.unauthorized
            case 403:
                throw APIError.forbidden
            case 422:
                let errorResponse = try decoder.decode(
                    ValidationErrorResponse.self,
                    from: data
                )
                throw APIError.validationError(errors: errorResponse.errors)
            default:
                throw APIError.httpError(statusCode: httpResponse.statusCode)
            }
        } catch let error as APIError {
            throw error
        } catch {
            throw APIError.networkError(error)
        }
    }
}

struct ValidationErrorResponse: Codable {
    let success: Bool
    let message: String
    let errors: [String: [String]]
}
```

#### Android - Retrofit + Coroutines

```kotlin
object NetworkModule {
    private const val BASE_URL = "https://api.kolabing.com/api/v1/"

    private val loggingInterceptor = HttpLoggingInterceptor().apply {
        level = if (BuildConfig.DEBUG) {
            HttpLoggingInterceptor.Level.BODY
        } else {
            HttpLoggingInterceptor.Level.NONE
        }
    }

    private val authInterceptor = Interceptor { chain ->
        val original = chain.request()
        val builder = original.newBuilder()
            .header("Accept", "application/json")

        TokenManager.getToken()?.let { token ->
            builder.header("Authorization", "Bearer $token")
        }

        chain.proceed(builder.build())
    }

    private val okHttpClient = OkHttpClient.Builder()
        .addInterceptor(authInterceptor)
        .addInterceptor(loggingInterceptor)
        .connectTimeout(30, TimeUnit.SECONDS)
        .readTimeout(30, TimeUnit.SECONDS)
        .build()

    private val gson = GsonBuilder()
        .setDateFormat("yyyy-MM-dd'T'HH:mm:ss.SSSSSS'Z'")
        .create()

    val retrofit: Retrofit = Retrofit.Builder()
        .baseUrl(BASE_URL)
        .client(okHttpClient)
        .addConverterFactory(GsonConverterFactory.create(gson))
        .build()

    val api: KolabingAPI = retrofit.create(KolabingAPI::class.java)
}

// Error handling wrapper
suspend fun <T> safeApiCall(apiCall: suspend () -> T): ApiResult<T> {
    return try {
        ApiResult.Success(apiCall())
    } catch (e: HttpException) {
        when (e.code()) {
            401 -> {
                TokenManager.clearToken()
                ApiResult.Error(ApiException.Unauthorized)
            }
            403 -> ApiResult.Error(ApiException.Forbidden)
            422 -> {
                val errorBody = e.response()?.errorBody()?.string()
                val errorResponse = Gson().fromJson(
                    errorBody,
                    ValidationErrorResponse::class.java
                )
                ApiResult.Error(ApiException.ValidationError(errorResponse.errors))
            }
            else -> ApiResult.Error(ApiException.HttpError(e.code()))
        }
    } catch (e: IOException) {
        ApiResult.Error(ApiException.NetworkError)
    } catch (e: Exception) {
        ApiResult.Error(ApiException.InvalidResponse)
    }
}
```

---

### Offline Caching Strategies

#### iOS - UserDefaults + Codable

```swift
class CacheManager {
    static let shared = CacheManager()
    private let defaults = UserDefaults.standard

    func cache<T: Codable>(_ data: T, forKey key: String, ttl: TimeInterval) {
        let cacheItem = CacheItem(data: data, expiresAt: Date().addingTimeInterval(ttl))

        if let encoded = try? JSONEncoder().encode(cacheItem) {
            defaults.set(encoded, forKey: key)
        }
    }

    func retrieve<T: Codable>(forKey key: String) -> T? {
        guard let data = defaults.data(forKey: key),
              let cacheItem = try? JSONDecoder().decode(CacheItem<T>.self, from: data) else {
            return nil
        }

        if cacheItem.expiresAt > Date() {
            return cacheItem.data
        } else {
            defaults.removeObject(forKey: key)
            return nil
        }
    }

    func clear(forKey key: String) {
        defaults.removeObject(forKey: key)
    }
}

struct CacheItem<T: Codable>: Codable {
    let data: T
    let expiresAt: Date
}

// Usage
class LookupService {
    func getCities(forceRefresh: Bool = false) async throws -> [City] {
        // Try cache first
        if !forceRefresh,
           let cached: [City] = CacheManager.shared.retrieve(forKey: "cities") {
            return cached
        }

        // Fetch from API
        struct CitiesResponse: Codable {
            let success: Bool
            let data: [City]
        }

        let response: CitiesResponse = try await NetworkManager.shared.request(
            endpoint: "/cities"
        )

        // Cache for 24 hours
        CacheManager.shared.cache(
            response.data,
            forKey: "cities",
            ttl: 24 * 60 * 60
        )

        return response.data
    }
}
```

#### Android - Room Database

```kotlin
@Entity(tableName = "cached_data")
data class CachedData(
    @PrimaryKey val key: String,
    val data: String,
    val expiresAt: Long
)

@Dao
interface CacheDao {
    @Query("SELECT * FROM cached_data WHERE key = :key AND expiresAt > :currentTime")
    suspend fun get(key: String, currentTime: Long): CachedData?

    @Insert(onConflict = OnConflictStrategy.REPLACE)
    suspend fun insert(cachedData: CachedData)

    @Query("DELETE FROM cached_data WHERE key = :key")
    suspend fun delete(key: String)

    @Query("DELETE FROM cached_data WHERE expiresAt < :currentTime")
    suspend fun deleteExpired(currentTime: Long)
}

class CacheManager(private val cacheDao: CacheDao) {
    private val gson = Gson()

    suspend fun <T> cache(key: String, data: T, ttlSeconds: Long) {
        val json = gson.toJson(data)
        val expiresAt = System.currentTimeMillis() + (ttlSeconds * 1000)
        cacheDao.insert(CachedData(key, json, expiresAt))
    }

    suspend inline fun <reified T> retrieve(key: String): T? {
        val cached = cacheDao.get(key, System.currentTimeMillis())
        return cached?.let {
            try {
                gson.fromJson(it.data, T::class.java)
            } catch (e: Exception) {
                null
            }
        }
    }

    suspend fun clear(key: String) {
        cacheDao.delete(key)
    }
}

// Usage
class LookupRepository(
    private val api: KolabingAPI,
    private val cacheManager: CacheManager
) {
    suspend fun getCities(forceRefresh: Boolean = false): ApiResult<List<City>> {
        // Try cache first
        if (!forceRefresh) {
            cacheManager.retrieve<List<City>>("cities")?.let {
                return ApiResult.Success(it)
            }
        }

        // Fetch from API
        return safeApiCall {
            val response = api.getCities()
            if (response.success && response.data != null) {
                // Cache for 24 hours
                cacheManager.cache("cities", response.data, 24 * 60 * 60)
                response.data
            } else {
                throw Exception("Failed to fetch cities")
            }
        }
    }
}
```

---

### Image Upload Handling

#### iOS - Base64 Encoding

```swift
class ImageUploadHelper {
    static func prepareImageForUpload(_ image: UIImage, maxSizeKB: Int = 5000) throws -> String {
        // Compress image
        guard let imageData = compressImage(image, maxSizeKB: maxSizeKB) else {
            throw ImageError.compressionFailed
        }

        // Convert to base64
        let base64String = imageData.base64EncodedString()

        // Add data URI prefix
        let mimeType = "image/jpeg"
        return "data:\(mimeType);base64,\(base64String)"
    }

    private static func compressImage(_ image: UIImage, maxSizeKB: Int) -> Data? {
        var compression: CGFloat = 1.0
        let maxBytes = maxSizeKB * 1024

        guard var imageData = image.jpegData(compressionQuality: compression) else {
            return nil
        }

        while imageData.count > maxBytes && compression > 0.1 {
            compression -= 0.1
            guard let data = image.jpegData(compressionQuality: compression) else {
                break
            }
            imageData = data
        }

        return imageData.count <= maxBytes ? imageData : nil
    }
}

enum ImageError: Error {
    case compressionFailed
    case sizeTooLarge
}

// Usage in onboarding
class OnboardingViewModel {
    func uploadProfilePhoto(_ image: UIImage) async {
        do {
            let base64Image = try ImageUploadHelper.prepareImageForUpload(image)
            self.profilePhotoData = base64Image
        } catch {
            showError("Failed to process image. Please try a smaller image.")
        }
    }
}
```

#### Android - Base64 Encoding

```kotlin
object ImageUploadHelper {
    private const val MAX_SIZE_BYTES = 5 * 1024 * 1024 // 5MB

    fun prepareImageForUpload(context: Context, uri: Uri): String? {
        val bitmap = MediaStore.Images.Media.getBitmap(context.contentResolver, uri)

        // Compress image
        val compressedData = compressImage(bitmap) ?: return null

        // Convert to base64
        val base64 = Base64.encodeToString(compressedData, Base64.NO_WRAP)

        // Add data URI prefix
        return "data:image/jpeg;base64,$base64"
    }

    private fun compressImage(bitmap: Bitmap): ByteArray? {
        var quality = 100
        var output: ByteArray

        do {
            val stream = ByteArrayOutputStream()
            bitmap.compress(Bitmap.CompressFormat.JPEG, quality, stream)
            output = stream.toByteArray()
            quality -= 10
        } while (output.size > MAX_SIZE_BYTES && quality > 10)

        return if (output.size <= MAX_SIZE_BYTES) output else null
    }

    fun resizeBitmap(bitmap: Bitmap, maxWidth: Int, maxHeight: Int): Bitmap {
        val ratio = minOf(
            maxWidth.toFloat() / bitmap.width,
            maxHeight.toFloat() / bitmap.height
        )

        if (ratio >= 1) return bitmap

        val width = (bitmap.width * ratio).toInt()
        val height = (bitmap.height * ratio).toInt()

        return Bitmap.createScaledBitmap(bitmap, width, height, true)
    }
}

// Usage in ViewModel
class OnboardingViewModel(private val imageHelper: ImageUploadHelper) {
    fun selectProfilePhoto(uri: Uri) {
        viewModelScope.launch {
            _uiState.value = UiState.Loading

            val base64Image = withContext(Dispatchers.IO) {
                imageHelper.prepareImageForUpload(context, uri)
            }

            if (base64Image != null) {
                profilePhotoData = base64Image
                _uiState.value = UiState.Success
            } else {
                _uiState.value = UiState.Error("Image too large. Please select a smaller image.")
            }
        }
    }
}
```

---

## Common Scenarios

### Scenario 1: New Business User Registration and Onboarding

**Step-by-step flow with requests and responses:**

**Step 1: User initiates Google Sign-In**
```
User action: Taps "Continue with Google" and selects "I'm a Business"
App: Launches Google Sign-In SDK
Google: Returns ID token
```

**Step 2: Send ID token to backend**
```bash
POST /api/v1/auth/google
Content-Type: application/json

{
  "id_token": "eyJhbGciOiJSUzI1NiIsImtpZCI6IjY4YTk4...",
  "user_type": "business"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Registration successful",
  "data": {
    "token": "3|ABcdefGHIjklMNOpqrSTUvwxYZ",
    "token_type": "Bearer",
    "is_new_user": true,
    "user": {
      "id": "123e4567-e89b-12d3-a456-426614174000",
      "email": "cafe@example.com",
      "phone_number": null,
      "user_type": "business",
      "avatar_url": "https://lh3.googleusercontent.com/a/...",
      "email_verified_at": "2026-01-24T18:00:00.000000Z",
      "onboarding_completed": false,
      "created_at": "2026-01-24T18:00:00.000000Z",
      "updated_at": "2026-01-24T18:00:00.000000Z"
    }
  }
}
```

**Step 3: App stores token and navigates to onboarding**
```
App logic:
- Store token in secure storage
- Check is_new_user: true
- Check onboarding_completed: false
- Navigate to Onboarding Screen
```

**Step 4: Fetch reference data for onboarding form**
```bash
GET /api/v1/cities
Accept: application/json
```

**Response:**
```json
{
  "success": true,
  "data": [
    {"id": "city-uuid-1", "name": "Barcelona", "country": "Spain"},
    {"id": "city-uuid-2", "name": "Madrid", "country": "Spain"}
  ],
  "meta": {"total": 2}
}
```

```bash
GET /api/v1/lookup/business-types
Accept: application/json
```

**Response:**
```json
{
  "success": true,
  "data": [
    {"value": "cafe", "label": "Café", "description": "Coffee shops and cafeterias"},
    {"value": "restaurant", "label": "Restaurant", "description": "Restaurants and dining"}
  ],
  "meta": {"total": 2}
}
```

**Step 5: User fills onboarding form and submits**
```bash
PUT /api/v1/onboarding/business
Authorization: Bearer 3|ABcdefGHIjklMNOpqrSTUvwxYZ
Content-Type: application/json

{
  "name": "Café Barcelona",
  "about": "Artisan coffee in the heart of Barcelona",
  "business_type": "cafe",
  "city_id": "city-uuid-1",
  "phone_number": "+34612345678",
  "instagram": "cafebarcelona",
  "website": "https://cafebarcelona.com",
  "profile_photo": "data:image/jpeg;base64,/9j/4AAQSkZJRg..."
}
```

**Response:**
```json
{
  "success": true,
  "message": "Business profile updated successfully",
  "data": {
    "id": "123e4567-e89b-12d3-a456-426614174000",
    "email": "cafe@example.com",
    "phone_number": "+34612345678",
    "user_type": "business",
    "onboarding_completed": true,
    "business_profile": {
      "name": "Café Barcelona",
      "about": "Artisan coffee in the heart of Barcelona",
      "business_type": "cafe",
      "city": {"id": "city-uuid-1", "name": "Barcelona", "country": "Spain"},
      "instagram": "cafebarcelona",
      "website": "https://cafebarcelona.com",
      "profile_photo": "https://storage.kolabing.com/profiles/abc123.jpg"
    },
    "subscription": {
      "status": "inactive",
      "current_period_start": null,
      "current_period_end": null
    }
  }
}
```

**Step 6: Navigate to home screen**
```
App logic:
- Update local user state
- onboarding_completed is now true
- Navigate to Home Screen
```

---

### Scenario 2: New Community User Registration and Onboarding

**Step 1: Google Sign-In**
```bash
POST /api/v1/auth/google

{
  "id_token": "eyJhbGciOiJSUzI1NiIsImtpZCI6IjY4YTk4...",
  "user_type": "community"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Registration successful",
  "data": {
    "token": "4|XYZabcDEFghiJKLmnoPQRstuV",
    "is_new_user": true,
    "user": {
      "id": "234e5678-e89b-12d3-a456-426614174001",
      "email": "maria@example.com",
      "user_type": "community",
      "onboarding_completed": false
    }
  }
}
```

**Step 2: Fetch reference data**
```bash
GET /api/v1/cities
GET /api/v1/lookup/community-types
```

**Step 3: Complete onboarding**
```bash
PUT /api/v1/onboarding/community
Authorization: Bearer 4|XYZabcDEFghiJKLmnoPQRstuV

{
  "name": "Maria García",
  "about": "Food blogger exploring Barcelona's best cafes",
  "community_type": "food_blogger",
  "city_id": "city-uuid-1",
  "instagram": "maria_food_bcn",
  "tiktok": "maria_food",
  "profile_photo": "data:image/jpeg;base64,/9j/4AAQSkZJRg..."
}
```

**Response:**
```json
{
  "success": true,
  "message": "Community profile updated successfully",
  "data": {
    "id": "234e5678-e89b-12d3-a456-426614174001",
    "onboarding_completed": true,
    "community_profile": {
      "name": "Maria García",
      "about": "Food blogger exploring Barcelona's best cafes",
      "community_type": "food_blogger",
      "instagram": "maria_food_bcn",
      "tiktok": "maria_food"
    }
  }
}
```

---

### Scenario 3: Returning User Login

**Step 1: App launch - Check stored token**
```bash
GET /api/v1/auth/me
Authorization: Bearer 3|ABcdefGHIjklMNOpqrSTUvwxYZ
```

**Response (Success):**
```json
{
  "success": true,
  "data": {
    "id": "123e4567-e89b-12d3-a456-426614174000",
    "email": "cafe@example.com",
    "user_type": "business",
    "onboarding_completed": true,
    "business_profile": {
      "name": "Café Barcelona"
    }
  }
}
```

**App logic:**
```
- Token is valid
- User data loaded
- onboarding_completed: true
- Navigate directly to Home Screen
```

---

### Scenario 4: User with Incomplete Onboarding Logs In

**Step 1: Login with Google**
```bash
POST /api/v1/auth/google

{
  "id_token": "eyJhbGciOiJSUzI1NiIsImtpZCI6IjY4YTk4...",
  "user_type": "business"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "token": "5|TokenStringHere",
    "is_new_user": false,
    "user": {
      "id": "345e6789-e89b-12d3-a456-426614174002",
      "email": "incomplete@example.com",
      "onboarding_completed": false
    }
  }
}
```

**App logic:**
```
- is_new_user: false (returning user)
- onboarding_completed: false
- Navigate to Onboarding Screen to complete profile
```

---

### Scenario 5: Token Expiration and Re-authentication

**Step 1: API call with expired token**
```bash
GET /api/v1/auth/me
Authorization: Bearer 1|ExpiredTokenHere
```

**Response:**
```json
{
  "success": false,
  "message": "Unauthenticated",
  "errors": {
    "auth": ["Authentication token is invalid or expired"]
  }
}
```

**App logic:**
```
- Receive 401 Unauthorized
- Clear stored token
- Clear cached user data
- Show login screen
- User must sign in again with Google
```

---

### Scenario 6: Validation Errors During Onboarding

**Request with invalid data:**
```bash
PUT /api/v1/onboarding/business
Authorization: Bearer 3|ABcdefGHIjklMNOpqrSTUvwxYZ

{
  "name": "",
  "business_type": "invalid_type",
  "city_id": "non-existent-uuid",
  "phone_number": "invalid-phone",
  "website": "not-a-url"
}
```

**Response (422 Unprocessable Entity):**
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "name": ["The name field is required"],
    "business_type": ["The selected business type is invalid"],
    "city_id": ["The selected city does not exist"],
    "phone_number": ["The phone number format is invalid"],
    "website": ["The website must be a valid URL"]
  }
}
```

**App logic:**
```
- Parse errors object
- Show error message under each field:
  - Name field: "The name field is required"
  - Business type: "The selected business type is invalid"
  - City: "The selected city does not exist"
  - Phone: "The phone number format is invalid"
  - Website: "The website must be a valid URL"
- Keep user on onboarding screen to fix errors
```

---

## Error Handling

### HTTP Status Codes

| Code | Meaning | When It Occurs | Client Action |
|------|---------|----------------|---------------|
| 200 | OK | Successful request | Process response data |
| 400 | Bad Request | Invalid Google ID token | Show error, retry login |
| 401 | Unauthorized | Invalid/expired auth token | Clear token, redirect to login |
| 403 | Forbidden | Wrong user type for endpoint | Show error message |
| 409 | Conflict | User type mismatch | Show error, prevent login |
| 422 | Unprocessable Entity | Validation errors | Show field-specific errors |
| 500 | Internal Server Error | Server error | Show generic error, retry |

### Error Response Structure

All error responses follow this format:
```json
{
  "success": false,
  "message": "Human-readable error message",
  "errors": {
    "field_name": ["Error message 1", "Error message 2"]
  }
}
```

### Common Error Fields

| Field | Description | Example |
|-------|-------------|---------|
| `auth` | Authentication errors | Token expired, invalid credentials |
| `id_token` | Google token errors | Invalid token, token verification failed |
| `user_type` | User type errors | Wrong user type, type mismatch |
| `server` | Generic server errors | Database error, service unavailable |
| `{field_name}` | Field-specific validation | name, email, phone_number, etc. |

### Error Handling Best Practices

1. **Network Errors**: Always handle network failures gracefully
2. **Token Expiration**: Automatically redirect to login on 401
3. **Validation Errors**: Show errors next to the relevant form fields
4. **Offline Mode**: Cache data locally and sync when online
5. **User Feedback**: Always show user-friendly error messages

---

## Testing & Debugging

### cURL Commands for Testing

**Test Google OAuth (replace with real token):**
```bash
curl -X POST https://api.kolabing.com/api/v1/auth/google \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "id_token": "YOUR_GOOGLE_ID_TOKEN_HERE",
    "user_type": "business"
  }'
```

**Test Get Current User:**
```bash
curl -X GET https://api.kolabing.com/api/v1/auth/me \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -H "Accept: application/json"
```

**Test Logout:**
```bash
curl -X POST https://api.kolabing.com/api/v1/auth/logout \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -H "Accept: application/json"
```

**Test Business Onboarding:**
```bash
curl -X PUT https://api.kolabing.com/api/v1/onboarding/business \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "name": "Test Cafe",
    "about": "A test cafe",
    "business_type": "cafe",
    "city_id": "VALID_CITY_UUID",
    "instagram": "testcafe"
  }'
```

**Test Get Cities:**
```bash
curl -X GET https://api.kolabing.com/api/v1/cities \
  -H "Accept: application/json"
```

**Test Get Business Types:**
```bash
curl -X GET https://api.kolabing.com/api/v1/lookup/business-types \
  -H "Accept: application/json"
```

### Debugging Checklist

- [ ] **Google Sign-In configured correctly** (client ID matches backend)
- [ ] **Base URL points to correct environment** (dev/staging/production)
- [ ] **Auth token stored securely** (Keychain/EncryptedSharedPreferences)
- [ ] **Headers include Accept: application/json** on all requests
- [ ] **Authorization header formatted correctly** (Bearer {token})
- [ ] **Request body JSON properly formatted**
- [ ] **Date/time parsing handles ISO 8601 format**
- [ ] **UUID validation on client side**
- [ ] **Phone number formatted in E.164**
- [ ] **Image base64 encoding doesn't exceed 5MB**
- [ ] **Network timeouts configured** (30-60 seconds)
- [ ] **Error responses parsed correctly**
- [ ] **401 errors trigger logout flow**
- [ ] **Validation errors shown on correct fields**
- [ ] **Offline mode handled gracefully**

---

## Best Practices

### Security

1. **Token Storage**: Always use secure storage (Keychain/Keystore)
2. **HTTPS Only**: Never send requests over HTTP in production
3. **Token Refresh**: Handle expired tokens gracefully
4. **Input Validation**: Validate on client before sending to server
5. **Sensitive Data**: Never log tokens or passwords

### Performance

1. **Cache Reference Data**: Cache cities, business types, community types locally
2. **Image Compression**: Compress images before upload
3. **Lazy Loading**: Load data only when needed
4. **Request Timeouts**: Set reasonable timeouts (30-60 seconds)
5. **Batch Requests**: Fetch all reference data at once during onboarding

### User Experience

1. **Loading States**: Show spinners during API calls
2. **Error Messages**: Display user-friendly error messages
3. **Form Validation**: Validate forms before submission
4. **Offline Support**: Cache data for offline viewing
5. **Retry Logic**: Allow users to retry failed requests

### Code Organization

1. **Separate API Layer**: Keep API calls in dedicated service classes
2. **Model Objects**: Use strongly-typed models for all data
3. **Error Handling**: Centralize error handling logic
4. **Constants**: Store endpoints and keys in constants file
5. **Environment Config**: Use different configs for dev/staging/production

---

## Appendix

### Data Models

**User Model:**
```typescript
interface User {
  id: string;  // UUID
  email: string;
  phone_number: string | null;
  user_type: "business" | "community";
  avatar_url: string;
  email_verified_at: string;  // ISO 8601
  onboarding_completed: boolean;
  created_at: string;  // ISO 8601
  updated_at: string;  // ISO 8601
  business_profile?: BusinessProfile;
  community_profile?: CommunityProfile;
  subscription?: Subscription;
}
```

**Business Profile Model:**
```typescript
interface BusinessProfile {
  id: string;  // UUID
  name: string | null;
  about: string | null;
  business_type: string | null;
  city: City | null;
  instagram: string | null;
  website: string | null;
  profile_photo: string | null;
  created_at: string;
  updated_at: string;
}
```

**Community Profile Model:**
```typescript
interface CommunityProfile {
  id: string;  // UUID
  name: string | null;
  about: string | null;
  community_type: string | null;
  city: City | null;
  instagram: string | null;
  tiktok: string | null;
  website: string | null;
  profile_photo: string | null;
  is_featured: boolean;
  created_at: string;
  updated_at: string;
}
```

**City Model:**
```typescript
interface City {
  id: string;  // UUID
  name: string;
  country: string;
}
```

**Lookup Item Model:**
```typescript
interface LookupItem {
  value: string;
  label: string;
  description: string;
}
```

**Subscription Model:**
```typescript
interface Subscription {
  id: string;  // UUID
  status: "active" | "inactive" | "canceled";
  current_period_start: string | null;  // ISO 8601
  current_period_end: string | null;  // ISO 8601
  cancel_at_period_end: boolean;
}
```

### Rate Limits

| Endpoint Type | Limit | Window |
|---------------|-------|--------|
| Authentication | 5 requests | per minute per IP |
| Authenticated Endpoints | 60 requests | per minute per user |
| Public Lookups | 120 requests | per minute per IP |

### Support & Resources

- **API Base URL**: https://api.kolabing.com/api/v1
- **API Documentation**: https://docs.kolabing.com
- **Support Email**: support@kolabing.com
- **Status Page**: https://status.kolabing.com

---

**Document Version**: 1.0.0
**Last Updated**: 2026-01-24
**Maintained By**: Kolabing Development Team
