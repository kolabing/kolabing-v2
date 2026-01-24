# Kolabing Authentication & Onboarding API Contract

**Version:** v1
**Base URL:** `/api/v1`
**Authentication:** Laravel Sanctum (Bearer token)
**Date:** 2026-01-24

---

## Table of Contents
1. [Authentication Endpoints](#authentication-endpoints)
2. [Onboarding Endpoints](#onboarding-endpoints)
3. [Lookup/Reference Endpoints](#lookupreference-endpoints)
4. [Common Response Patterns](#common-response-patterns)
5. [Error Codes](#error-codes)

---

## Authentication Endpoints

### 1.1 Google OAuth Login/Register

**Endpoint:** `POST /api/v1/auth/google`

**Description:** Authenticates or registers a user via Google OAuth. Mobile app sends Google ID token obtained from Google Sign-In SDK. Backend verifies token with Google, creates user if needed, and returns Sanctum authentication token.

**Authentication:** None (public endpoint)

**Request Headers:**
```http
Content-Type: application/json
Accept: application/json
```

**Request Body:**
```json
{
  "id_token": "string (required)",
  "user_type": "string (required, enum: 'business' | 'community')"
}
```

**Field Validation:**
- `id_token`: Required, valid Google ID token (JWT format)
- `user_type`: Required, must be either "business" or "community"

**Success Response (200 OK):**

*Existing User Login:*
```json
{
  "success": true,
  "message": "Login successful",
  "data": {
    "token": "1|laravel_sanctum_token_here",
    "token_type": "Bearer",
    "is_new_user": false,
    "user": {
      "id": "550e8400-e29b-41d4-a716-446655440000",
      "email": "user@example.com",
      "phone_number": "+34612345678",
      "user_type": "business",
      "avatar_url": "https://lh3.googleusercontent.com/...",
      "email_verified_at": "2026-01-20T10:30:00.000000Z",
      "onboarding_completed": true,
      "created_at": "2026-01-15T08:00:00.000000Z",
      "updated_at": "2026-01-20T10:30:00.000000Z"
    }
  }
}
```

*New User Registration:*
```json
{
  "success": true,
  "message": "Registration successful",
  "data": {
    "token": "2|laravel_sanctum_token_here",
    "token_type": "Bearer",
    "is_new_user": true,
    "user": {
      "id": "660e8400-e29b-41d4-a716-446655440001",
      "email": "newuser@example.com",
      "phone_number": null,
      "user_type": "community",
      "avatar_url": "https://lh3.googleusercontent.com/...",
      "email_verified_at": "2026-01-24T12:00:00.000000Z",
      "onboarding_completed": false,
      "created_at": "2026-01-24T12:00:00.000000Z",
      "updated_at": "2026-01-24T12:00:00.000000Z"
    }
  }
}
```

**Error Responses:**

*400 Bad Request - Invalid Token:*
```json
{
  "success": false,
  "message": "Invalid Google ID token",
  "errors": {
    "id_token": ["The provided Google ID token is invalid or expired"]
  }
}
```

*422 Unprocessable Entity - Validation Error:*
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

*409 Conflict - User Type Mismatch:*
```json
{
  "success": false,
  "message": "User type mismatch",
  "errors": {
    "user_type": ["User already exists with a different user type"]
  }
}
```

*500 Internal Server Error:*
```json
{
  "success": false,
  "message": "Authentication failed",
  "errors": {
    "server": ["Unable to process authentication request"]
  }
}
```

**Business Rules:**
1. Google ID token must be verified with Google's token verification API
2. Extract email, google_id, and avatar_url from verified token payload
3. If user exists (matched by google_id or email):
   - Verify user_type matches existing record (return 409 if mismatch)
   - Update avatar_url if changed
   - Update email_verified_at if not already set
   - Return is_new_user: false
4. If user doesn't exist:
   - Create new profile record with google_id, email, avatar_url, user_type
   - Set email_verified_at to current timestamp
   - If user_type is "business": create business_profiles and business_subscriptions records (status: 'inactive')
   - If user_type is "community": create community_profiles record
   - Return is_new_user: true
5. Generate Sanctum authentication token with 30-day expiration
6. Token should include ability/scope based on user_type

---

### 1.2 Get Authenticated User

**Endpoint:** `GET /api/v1/auth/me`

**Description:** Retrieves the complete profile of the currently authenticated user, including extended profile data and subscription information.

**Authentication:** Required (Bearer token)

**Request Headers:**
```http
Authorization: Bearer {token}
Accept: application/json
```

**Request Body:** None

**Success Response (200 OK):**

*Business User:*
```json
{
  "success": true,
  "data": {
    "id": "550e8400-e29b-41d4-a716-446655440000",
    "email": "business@example.com",
    "phone_number": "+34612345678",
    "user_type": "business",
    "avatar_url": "https://lh3.googleusercontent.com/...",
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

*Community User:*
```json
{
  "success": true,
  "data": {
    "id": "660e8400-e29b-41d4-a716-446655440001",
    "email": "community@example.com",
    "phone_number": "+34698765432",
    "user_type": "community",
    "avatar_url": "https://lh3.googleusercontent.com/...",
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

*User Without Completed Onboarding:*
```json
{
  "success": true,
  "data": {
    "id": "bb0e8400-e29b-41d4-a716-446655440006",
    "email": "newuser@example.com",
    "phone_number": null,
    "user_type": "business",
    "avatar_url": "https://lh3.googleusercontent.com/...",
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

**Error Responses:**

*401 Unauthorized:*
```json
{
  "success": false,
  "message": "Unauthenticated",
  "errors": {
    "auth": ["Authentication token is invalid or expired"]
  }
}
```

**Business Rules:**
1. Always include extended profile (business_profile or community_profile) based on user_type
2. For business users, always include subscription data
3. onboarding_completed is true when extended profile has: name, city_id, and at least one social/contact field
4. City object is nested within profile (not just city_id)
5. All null fields should be explicitly returned as null (not omitted)
6. Timestamps are in ISO 8601 format with timezone

---

### 1.3 Logout

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

**Error Responses:**

*401 Unauthorized:*
```json
{
  "success": false,
  "message": "Unauthenticated",
  "errors": {
    "auth": ["Authentication token is invalid or expired"]
  }
}
```

**Business Rules:**
1. Revoke only the current token (not all user tokens)
2. If token is already invalid/expired, still return 401
3. Mobile app should clear stored token after successful logout

---

## Onboarding Endpoints

### 2.1 Complete Business Onboarding

**Endpoint:** `PUT /api/v1/onboarding/business`

**Description:** Updates business profile with onboarding information. This endpoint completes the onboarding flow for business users.

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
  "name": "string (required, max: 255)",
  "about": "string (optional, max: 1000)",
  "business_type": "string (required)",
  "city_id": "uuid (required)",
  "phone_number": "string (optional, E.164 format)",
  "instagram": "string (optional, max: 255)",
  "website": "string (optional, url, max: 255)",
  "profile_photo": "string (optional, base64 encoded image or url)"
}
```

**Field Validation:**
- `name`: Required, 1-255 characters, business name
- `about`: Optional, max 1000 characters, business description
- `business_type`: Required, must match value from /api/v1/lookup/business-types
- `city_id`: Required, valid UUID, must exist in cities table
- `phone_number`: Optional, E.164 format (e.g., +34612345678)
- `instagram`: Optional, Instagram handle (without @), max 255 chars
- `website`: Optional, valid URL format, max 255 chars
- `profile_photo`: Optional, base64 image (jpg/png, max 5MB) or URL

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
    "avatar_url": "https://lh3.googleusercontent.com/...",
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

**Error Responses:**

*401 Unauthorized:*
```json
{
  "success": false,
  "message": "Unauthenticated",
  "errors": {
    "auth": ["Authentication token is invalid or expired"]
  }
}
```

*403 Forbidden - Wrong User Type:*
```json
{
  "success": false,
  "message": "Access denied",
  "errors": {
    "user_type": ["This endpoint is only accessible to business users"]
  }
}
```

*422 Unprocessable Entity - Validation Error:*
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

**Business Rules:**
1. Only accessible to users with user_type = 'business'
2. Updates business_profiles record associated with authenticated user
3. If phone_number provided, also update profiles.phone_number
4. If profile_photo is base64: upload to cloud storage, store URL in database
5. If profile_photo is URL: validate and store directly
6. business_type must exist in predefined list (validated against lookup endpoint)
7. city_id must reference existing city record
8. Instagram handle: strip @ symbol if provided, validate format (alphanumeric, dots, underscores)
9. After successful update, onboarding_completed becomes true
10. Return complete user object with updated profile (same structure as /auth/me)

---

### 2.2 Complete Community Onboarding

**Endpoint:** `PUT /api/v1/onboarding/community`

**Description:** Updates community profile with onboarding information. This endpoint completes the onboarding flow for community users.

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
  "name": "string (required, max: 255)",
  "about": "string (optional, max: 1000)",
  "community_type": "string (required)",
  "city_id": "uuid (required)",
  "phone_number": "string (optional, E.164 format)",
  "instagram": "string (optional, max: 255)",
  "tiktok": "string (optional, max: 255)",
  "website": "string (optional, url, max: 255)",
  "profile_photo": "string (optional, base64 encoded image or url)"
}
```

**Field Validation:**
- `name`: Required, 1-255 characters, user's display name
- `about`: Optional, max 1000 characters, bio/description
- `community_type`: Required, must match value from /api/v1/lookup/community-types
- `city_id`: Required, valid UUID, must exist in cities table
- `phone_number`: Optional, E.164 format (e.g., +34612345678)
- `instagram`: Optional, Instagram handle (without @), max 255 chars
- `tiktok`: Optional, TikTok handle (without @), max 255 chars
- `website`: Optional, valid URL format, max 255 chars
- `profile_photo`: Optional, base64 image (jpg/png, max 5MB) or URL

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
    "avatar_url": "https://lh3.googleusercontent.com/...",
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

**Error Responses:**

*401 Unauthorized:*
```json
{
  "success": false,
  "message": "Unauthenticated",
  "errors": {
    "auth": ["Authentication token is invalid or expired"]
  }
}
```

*403 Forbidden - Wrong User Type:*
```json
{
  "success": false,
  "message": "Access denied",
  "errors": {
    "user_type": ["This endpoint is only accessible to community users"]
  }
}
```

*422 Unprocessable Entity - Validation Error:*
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
    "website": ["The website must be a valid URL"],
    "profile_photo": ["The profile photo must not exceed 5MB"]
  }
}
```

**Business Rules:**
1. Only accessible to users with user_type = 'community'
2. Updates community_profiles record associated with authenticated user
3. If phone_number provided, also update profiles.phone_number
4. If profile_photo is base64: upload to cloud storage, store URL in database
5. If profile_photo is URL: validate and store directly
6. community_type must exist in predefined list (validated against lookup endpoint)
7. city_id must reference existing city record
8. Instagram/TikTok handles: strip @ symbol if provided, validate format (alphanumeric, dots, underscores)
9. After successful update, onboarding_completed becomes true
10. is_featured remains false (only admins can set this)
11. Return complete user object with updated profile (same structure as /auth/me)

---

## Lookup/Reference Endpoints

### 3.1 Get Cities List

**Endpoint:** `GET /api/v1/cities`

**Description:** Retrieves the list of available cities for user selection during onboarding.

**Authentication:** None (public endpoint)

**Request Headers:**
```http
Accept: application/json
```

**Query Parameters:** None

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

**Error Responses:**

*500 Internal Server Error:*
```json
{
  "success": false,
  "message": "Unable to fetch cities",
  "errors": {
    "server": ["An error occurred while retrieving cities"]
  }
}
```

**Business Rules:**
1. Returns all active cities alphabetically sorted by name
2. No pagination (limited dataset expected)
3. Public endpoint - no authentication required
4. Can be cached on client side with reasonable TTL (24 hours)
5. All cities currently default to country: "Spain"

---

### 3.2 Get Business Types

**Endpoint:** `GET /api/v1/lookup/business-types`

**Description:** Retrieves the list of available business types for business user onboarding.

**Authentication:** None (public endpoint)

**Request Headers:**
```http
Accept: application/json
```

**Query Parameters:** None

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

**Error Responses:**

*500 Internal Server Error:*
```json
{
  "success": false,
  "message": "Unable to fetch business types",
  "errors": {
    "server": ["An error occurred while retrieving business types"]
  }
}
```

**Business Rules:**
1. Returns predefined list of business types
2. value field is used for database storage (business_profiles.business_type)
3. label field is display text for UI
4. description provides additional context
5. Public endpoint - no authentication required
6. Can be cached on client side with long TTL (7 days)
7. List is currently hardcoded but can be database-driven in future

---

### 3.3 Get Community Types

**Endpoint:** `GET /api/v1/lookup/community-types`

**Description:** Retrieves the list of available community types for community user onboarding.

**Authentication:** None (public endpoint)

**Request Headers:**
```http
Accept: application/json
```

**Query Parameters:** None

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

**Error Responses:**

*500 Internal Server Error:*
```json
{
  "success": false,
  "message": "Unable to fetch community types",
  "errors": {
    "server": ["An error occurred while retrieving community types"]
  }
}
```

**Business Rules:**
1. Returns predefined list of community types
2. value field is used for database storage (community_profiles.community_type)
3. label field is display text for UI
4. description provides additional context
5. Public endpoint - no authentication required
6. Can be cached on client side with long TTL (7 days)
7. List is currently hardcoded but can be database-driven in future

---

## Common Response Patterns

### Success Response Structure
All successful responses follow this structure:
```json
{
  "success": true,
  "message": "Optional success message",
  "data": {}, // or []
  "meta": {} // optional metadata (pagination, counts, etc.)
}
```

### Error Response Structure
All error responses follow this structure:
```json
{
  "success": false,
  "message": "Human-readable error message",
  "errors": {
    "field_name": ["Error message 1", "Error message 2"]
  }
}
```

### Timestamp Format
All timestamps use ISO 8601 format with UTC timezone:
```
2026-01-24T12:00:00.000000Z
```

### UUID Format
All UUIDs use RFC 4122 format:
```
550e8400-e29b-41d4-a716-446655440000
```

### Onboarding Completed Logic
A user's onboarding is considered completed when their extended profile contains:
- `name` is not null
- `city_id` is not null
- At least one social/contact field is not null (instagram, tiktok, website, or phone_number)

---

## Error Codes

### HTTP Status Codes Used

| Code | Description | Usage |
|------|-------------|-------|
| 200 | OK | Successful request |
| 401 | Unauthorized | Missing or invalid authentication token |
| 403 | Forbidden | User lacks permission (e.g., wrong user_type) |
| 404 | Not Found | Resource doesn't exist |
| 409 | Conflict | Request conflicts with current state (e.g., user_type mismatch) |
| 422 | Unprocessable Entity | Validation errors in request data |
| 500 | Internal Server Error | Server-side error |

### Common Error Fields

| Field | Type | Description |
|-------|------|-------------|
| auth | array | Authentication/authorization errors |
| id_token | array | Google token validation errors |
| user_type | array | User type validation or access errors |
| server | array | Generic server errors |
| {field_name} | array | Field-specific validation errors |

---

## Implementation Notes

### Security Considerations
1. **Token Security**: Sanctum tokens should be stored securely on mobile device (encrypted storage)
2. **HTTPS Only**: All endpoints must be served over HTTPS in production
3. **Rate Limiting**: Apply rate limiting to prevent abuse:
   - Auth endpoints: 5 requests per minute per IP
   - Other endpoints: 60 requests per minute per user
4. **Google Token Verification**: Always verify ID token with Google servers, never trust client
5. **CORS**: Configure CORS headers appropriately for mobile app

### Performance Optimization
1. **Eager Loading**: Load extended profiles and cities with single query
2. **Caching**: Cache lookup endpoints (cities, business types, community types)
3. **Database Indexes**: Index google_id, email, user_type, city_id
4. **Image Optimization**: Resize/compress profile photos on upload

### Mobile App Integration
1. **Token Storage**: Store Sanctum token in secure storage (Keychain/KeyStore)
2. **Token Refresh**: Tokens expire after 30 days, handle re-authentication gracefully
3. **Offline Handling**: Cache lookup data locally for offline form validation
4. **Image Upload**: Use base64 encoding for profile photos or implement multipart upload

### Testing Checklist
- [ ] Google OAuth token verification with valid/invalid tokens
- [ ] User creation for both business and community types
- [ ] User type mismatch scenarios (409 errors)
- [ ] Onboarding completion logic validation
- [ ] Profile photo upload (base64 and URL)
- [ ] Phone number validation (E.164 format)
- [ ] Instagram/TikTok handle sanitization
- [ ] City and type lookups
- [ ] Token expiration and refresh
- [ ] Rate limiting enforcement
- [ ] Concurrent request handling

---

## Changelog

**v1.0.0** (2026-01-24)
- Initial API contract design
- Authentication endpoints (Google OAuth, me, logout)
- Onboarding endpoints (business, community)
- Lookup endpoints (cities, business types, community types)
