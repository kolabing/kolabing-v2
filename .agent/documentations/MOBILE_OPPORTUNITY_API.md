# Kolabing Mobile API - Collaboration Opportunities

**Version:** 1.0
**Base URL:** `/api/v1`
**Authentication:** Bearer Token (Laravel Sanctum)

## Table of Contents

- [Overview](#overview)
- [Authentication](#authentication)
- [Data Models](#data-models)
- [Endpoints](#endpoints)
  - [Browse Opportunities](#browse-opportunities)
  - [Get My Opportunities](#get-my-opportunities)
  - [Get Single Opportunity](#get-single-opportunity)
  - [Create Opportunity](#create-opportunity)
  - [Update Opportunity](#update-opportunity)
  - [Publish Opportunity](#publish-opportunity)
  - [Close Opportunity](#close-opportunity)
  - [Delete Opportunity](#delete-opportunity)
- [Error Handling](#error-handling)
- [Business Rules](#business-rules)

---

## Overview

The Collaboration Opportunities API allows both business and community users to create, manage, and browse collaboration opportunities. This is the core feature for matching businesses with community organizers.

### Key Concepts

- **Draft → Published → Closed → Completed**: Opportunity lifecycle
- **Business users**: Must have active Stripe subscription to publish
- **Community users**: Can publish for free
- **JSONB Fields**: `business_offer` and `community_deliverables` use flexible JSON structures

---

## Authentication

All endpoints require authentication via Bearer token in the header:

```http
Authorization: Bearer {your_sanctum_token}
```

The authenticated user is identified as a `Profile` (not a Laravel `User`). Each request user is a profile with `user_type` of either `business` or `community`.

---

## Data Models

### Opportunity Status Flow

```
draft → published → closed → completed
```

**Status Enum Values:**
- `draft` - Created but not visible to others
- `published` - Live and visible to all users
- `closed` - No longer accepting applications
- `completed` - Collaboration finished (future use)

### Availability Mode

**Enum Values:**
- `one_time` - Single event/collaboration
- `recurring` - Repeated collaborations
- `flexible` - Open to discussion

### Venue Mode

**Enum Values:**
- `business_venue` - At business location (address required)
- `community_venue` - At community location (address required)
- `no_venue` - Online or no specific venue (address optional)

### Business Offer Structure (JSONB)

The `business_offer` field is a flexible JSON object representing what the business offers:

```json
{
  "venue": true,
  "food_drink": true,
  "discount": {
    "enabled": true,
    "percentage": 20
  },
  "products": ["Product A", "Product B"],
  "other": "Custom offer description"
}
```

**Common Fields:**
- `venue` (boolean) - Providing venue space
- `food_drink` (boolean) - Providing food/drinks
- `discount` (object) - Discount offer
  - `enabled` (boolean)
  - `percentage` (number) - 0-100
- `products` (array) - List of products offered
- `other` (string) - Free-form text for other offers

### Community Deliverables Structure (JSONB)

The `community_deliverables` field is a flexible JSON object representing what the community will provide:

```json
{
  "instagram_post": true,
  "instagram_story": true,
  "tiktok_video": false,
  "event_mention": true,
  "attendee_count": 50,
  "other": "Additional promotional activities"
}
```

**Common Fields:**
- `instagram_post` (boolean) - Will create Instagram post
- `instagram_story` (boolean) - Will create Instagram story
- `tiktok_video` (boolean) - Will create TikTok video
- `event_mention` (boolean) - Will mention at event
- `attendee_count` (number) - Expected number of attendees
- `other` (string) - Free-form text for other deliverables

### Categories

Array of category strings (min: 1, max: 5):

```json
["Food & Drink", "Sports", "Wellness"]
```

**Common Categories:**
- "Food & Drink"
- "Sports"
- "Wellness"
- "Culture"
- "Technology"
- "Education"
- "Entertainment"
- "Fashion"
- "Music"
- "Art"

---

## Endpoints

### Browse Opportunities

Get a paginated list of all published opportunities with optional filters.

**Endpoint:** `GET /api/v1/opportunities`

**Authentication:** Required

**Query Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `page` | integer | No | Page number (default: 1) |
| `per_page` | integer | No | Items per page (default: 20, max: 100) |
| `creator_type` | string | No | Filter by creator type: `business` or `community` |
| `categories` | array | No | Filter by categories (array of strings) |
| `city` | string | No | Filter by preferred city |
| `venue_mode` | string | No | Filter by venue mode: `business_venue`, `community_venue`, `no_venue` |
| `availability_mode` | string | No | Filter by availability: `one_time`, `recurring`, `flexible` |
| `availability_from` | date | No | Filter opportunities starting from this date (Y-m-d) |
| `availability_to` | date | No | Filter opportunities ending before this date (Y-m-d) |
| `search` | string | No | Search in title and description (case-insensitive) |

**Example Request:**

```http
GET /api/v1/opportunities?creator_type=business&city=Barcelona&per_page=10&page=1
Authorization: Bearer {token}
```

**Success Response (200 OK):**

```json
{
  "success": true,
  "data": [
    {
      "id": "9d8f7a5b-4c3e-2a1d-8e9f-7a5b4c3e2a1d",
      "creator_profile": {
        "id": "9d8f7a5b-1111-2222-3333-444444444444",
        "user_type": "business",
        "business_name": "Cafe Barcelona",
        "avatar_url": "https://example.com/avatar.jpg"
      },
      "title": "Wellness Event Collaboration",
      "description": "Looking for wellness communities to host events at our venue...",
      "status": "published",
      "business_offer": {
        "venue": true,
        "food_drink": true,
        "discount": {
          "enabled": true,
          "percentage": 15
        }
      },
      "community_deliverables": {
        "instagram_post": true,
        "instagram_story": true,
        "attendee_count": 30
      },
      "categories": ["Wellness", "Food & Drink"],
      "availability_mode": "recurring",
      "availability_start": "2026-02-01",
      "availability_end": "2026-06-30",
      "venue_mode": "business_venue",
      "address": "Carrer de la Marina, 19-21, Barcelona",
      "preferred_city": "Barcelona",
      "offer_photo": "https://example.com/photo.jpg",
      "published_at": "2026-01-26T10:30:00+00:00",
      "applications_count": 5,
      "is_own": false,
      "has_applied": false,
      "created_at": "2026-01-25T15:00:00+00:00",
      "updated_at": "2026-01-26T10:30:00+00:00"
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 3,
    "per_page": 10,
    "total": 27
  }
}
```

---

### Get My Opportunities

Get all opportunities created by the authenticated user (all statuses).

**Endpoint:** `GET /api/v1/me/opportunities`

**Authentication:** Required

**Query Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `page` | integer | No | Page number (default: 1) |
| `per_page` | integer | No | Items per page (default: 20, max: 100) |
| `status` | string | No | Filter by status: `draft`, `published`, `closed`, `completed` |

**Example Request:**

```http
GET /api/v1/me/opportunities?status=draft
Authorization: Bearer {token}
```

**Success Response (200 OK):**

```json
{
  "success": true,
  "data": [
    {
      "id": "9d8f7a5b-4c3e-2a1d-8e9f-7a5b4c3e2a1d",
      "creator_profile": {
        "id": "9d8f7a5b-1111-2222-3333-444444444444",
        "user_type": "business",
        "business_name": "My Business",
        "avatar_url": "https://example.com/avatar.jpg"
      },
      "title": "Draft Opportunity",
      "description": "This is still being worked on...",
      "status": "draft",
      "business_offer": {
        "venue": true
      },
      "community_deliverables": {
        "instagram_post": true
      },
      "categories": ["Sports"],
      "availability_mode": "one_time",
      "availability_start": "2026-03-01",
      "availability_end": "2026-03-01",
      "venue_mode": "business_venue",
      "address": "My address",
      "preferred_city": "Madrid",
      "offer_photo": null,
      "published_at": null,
      "applications_count": 0,
      "is_own": true,
      "created_at": "2026-01-26T12:00:00+00:00",
      "updated_at": "2026-01-26T12:00:00+00:00"
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 20,
    "total": 1
  }
}
```

---

### Get Single Opportunity

Get detailed information about a specific opportunity.

**Endpoint:** `GET /api/v1/opportunities/{opportunity_id}`

**Authentication:** Required

**Authorization Rules:**
- Anyone can view published opportunities
- Only the creator can view draft/closed/completed opportunities

**Example Request:**

```http
GET /api/v1/opportunities/9d8f7a5b-4c3e-2a1d-8e9f-7a5b4c3e2a1d
Authorization: Bearer {token}
```

**Success Response (200 OK):**

```json
{
  "success": true,
  "data": {
    "id": "9d8f7a5b-4c3e-2a1d-8e9f-7a5b4c3e2a1d",
    "creator_profile": {
      "id": "9d8f7a5b-1111-2222-3333-444444444444",
      "user_type": "business",
      "business_name": "Cafe Barcelona",
      "avatar_url": "https://example.com/avatar.jpg"
    },
    "title": "Wellness Event Collaboration",
    "description": "Looking for wellness communities to host events at our venue...",
    "status": "published",
    "business_offer": {
      "venue": true,
      "food_drink": true,
      "discount": {
        "enabled": true,
        "percentage": 15
      }
    },
    "community_deliverables": {
      "instagram_post": true,
      "instagram_story": true,
      "attendee_count": 30
    },
    "categories": ["Wellness", "Food & Drink"],
    "availability_mode": "recurring",
    "availability_start": "2026-02-01",
    "availability_end": "2026-06-30",
    "venue_mode": "business_venue",
    "address": "Carrer de la Marina, 19-21, Barcelona",
    "preferred_city": "Barcelona",
    "offer_photo": "https://example.com/photo.jpg",
    "published_at": "2026-01-26T10:30:00+00:00",
    "applications_count": 5,
    "is_own": false,
    "has_applied": true,
    "my_application": {
      "id": "9d8f7a5b-aaaa-bbbb-cccc-dddddddddddd",
      "status": "pending",
      "message": "I'd love to collaborate!",
      "created_at": "2026-01-26T14:00:00+00:00"
    },
    "created_at": "2026-01-25T15:00:00+00:00",
    "updated_at": "2026-01-26T10:30:00+00:00"
  }
}
```

**Error Response - Unauthorized (403 Forbidden):**

```json
{
  "success": false,
  "message": "You are not authorized to view this opportunity."
}
```

**Error Response - Not Found (404 Not Found):**

```json
{
  "success": false,
  "message": "Opportunity not found."
}
```

---

### Create Opportunity

Create a new collaboration opportunity in draft status.

**Endpoint:** `POST /api/v1/opportunities`

**Authentication:** Required

**Authorization:** Any authenticated user can create opportunities

**Request Body:**

| Field | Type | Required | Validation | Description |
|-------|------|----------|------------|-------------|
| `title` | string | Yes | max:255 | Opportunity title |
| `description` | string | Yes | max:5000 | Detailed description |
| `business_offer` | object | Yes | JSON object | What the business offers |
| `community_deliverables` | object | Yes | JSON object | What the community will deliver |
| `categories` | array | Yes | 1-5 strings | Category tags |
| `availability_mode` | string | Yes | one_time, recurring, flexible | Availability type |
| `availability_start` | date | Yes | Y-m-d, after today | Start date |
| `availability_end` | date | Yes | Y-m-d, after start | End date |
| `venue_mode` | string | Yes | business_venue, community_venue, no_venue | Venue type |
| `address` | string | Conditional | Required unless venue_mode=no_venue | Physical address |
| `preferred_city` | string | Yes | max:100 | Preferred city |
| `offer_photo` | string | No | Valid URL | Photo URL |

**Example Request:**

```http
POST /api/v1/opportunities
Authorization: Bearer {token}
Content-Type: application/json

{
  "title": "Yoga Studio Community Event",
  "description": "We're looking to partner with wellness communities to host regular yoga events at our studio. We can provide the venue, mats, and refreshments. Ideal for communities with 20-40 members interested in wellness.",
  "business_offer": {
    "venue": true,
    "food_drink": true,
    "discount": {
      "enabled": true,
      "percentage": 20
    },
    "products": ["Yoga mats", "Water bottles"],
    "other": "Free trial memberships for attendees"
  },
  "community_deliverables": {
    "instagram_post": true,
    "instagram_story": true,
    "tiktok_video": false,
    "event_mention": true,
    "attendee_count": 30,
    "other": "Feature us in monthly newsletter"
  },
  "categories": ["Wellness", "Sports", "Health"],
  "availability_mode": "recurring",
  "availability_start": "2026-02-15",
  "availability_end": "2026-08-15",
  "venue_mode": "business_venue",
  "address": "Carrer del Consell de Cent, 334, Barcelona",
  "preferred_city": "Barcelona",
  "offer_photo": "https://example.com/studio-photo.jpg"
}
```

**Success Response (201 Created):**

```json
{
  "success": true,
  "message": "Opportunity created successfully.",
  "data": {
    "id": "9d8f7a5b-4c3e-2a1d-8e9f-7a5b4c3e2a1d",
    "creator_profile": {
      "id": "9d8f7a5b-1111-2222-3333-444444444444",
      "user_type": "business",
      "business_name": "Zen Yoga Studio",
      "avatar_url": "https://example.com/avatar.jpg"
    },
    "title": "Yoga Studio Community Event",
    "description": "We're looking to partner with wellness communities...",
    "status": "draft",
    "business_offer": {
      "venue": true,
      "food_drink": true,
      "discount": {
        "enabled": true,
        "percentage": 20
      },
      "products": ["Yoga mats", "Water bottles"],
      "other": "Free trial memberships for attendees"
    },
    "community_deliverables": {
      "instagram_post": true,
      "instagram_story": true,
      "tiktok_video": false,
      "event_mention": true,
      "attendee_count": 30,
      "other": "Feature us in monthly newsletter"
    },
    "categories": ["Wellness", "Sports", "Health"],
    "availability_mode": "recurring",
    "availability_start": "2026-02-15",
    "availability_end": "2026-08-15",
    "venue_mode": "business_venue",
    "address": "Carrer del Consell de Cent, 334, Barcelona",
    "preferred_city": "Barcelona",
    "offer_photo": "https://example.com/studio-photo.jpg",
    "published_at": null,
    "is_own": true,
    "created_at": "2026-01-26T15:30:00+00:00",
    "updated_at": "2026-01-26T15:30:00+00:00"
  }
}
```

**Error Response - Validation Failed (422 Unprocessable Entity):**

```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "title": ["The title field is required."],
    "availability_start": ["The availability start must be a date after today."],
    "categories": ["The categories field must have at least 1 items."]
  }
}
```

**Error Response - Unauthorized (403 Forbidden):**

```json
{
  "success": false,
  "message": "You are not authorized to create opportunities."
}
```

---

### Update Opportunity

Update an existing opportunity. Only available for draft or published opportunities.

**Endpoint:** `PUT /api/v1/opportunities/{opportunity_id}`

**Authentication:** Required

**Authorization:** Only the creator can update their opportunities (draft or published status only)

**Request Body:** (All fields optional, send only what you want to update)

| Field | Type | Validation | Description |
|-------|------|------------|-------------|
| `title` | string | max:255 | Opportunity title |
| `description` | string | max:5000 | Detailed description |
| `business_offer` | object | JSON object | What the business offers |
| `community_deliverables` | object | JSON object | What the community will deliver |
| `categories` | array | 1-5 strings | Category tags |
| `availability_mode` | string | one_time, recurring, flexible | Availability type |
| `availability_start` | date | Y-m-d, after today | Start date |
| `availability_end` | date | Y-m-d, after start | End date |
| `venue_mode` | string | business_venue, community_venue, no_venue | Venue type |
| `address` | string | string or null | Physical address |
| `preferred_city` | string | max:100 | Preferred city |
| `offer_photo` | string | Valid URL or null | Photo URL |

**Example Request:**

```http
PUT /api/v1/opportunities/9d8f7a5b-4c3e-2a1d-8e9f-7a5b4c3e2a1d
Authorization: Bearer {token}
Content-Type: application/json

{
  "title": "Updated: Yoga Studio Community Event",
  "community_deliverables": {
    "instagram_post": true,
    "instagram_story": true,
    "tiktok_video": true,
    "event_mention": true,
    "attendee_count": 50
  }
}
```

**Success Response (200 OK):**

```json
{
  "success": true,
  "message": "Opportunity updated successfully.",
  "data": {
    "id": "9d8f7a5b-4c3e-2a1d-8e9f-7a5b4c3e2a1d",
    "creator_profile": {
      "id": "9d8f7a5b-1111-2222-3333-444444444444",
      "user_type": "business",
      "business_name": "Zen Yoga Studio",
      "avatar_url": "https://example.com/avatar.jpg"
    },
    "title": "Updated: Yoga Studio Community Event",
    "description": "We're looking to partner with wellness communities...",
    "status": "draft",
    "business_offer": {
      "venue": true,
      "food_drink": true,
      "discount": {
        "enabled": true,
        "percentage": 20
      }
    },
    "community_deliverables": {
      "instagram_post": true,
      "instagram_story": true,
      "tiktok_video": true,
      "event_mention": true,
      "attendee_count": 50
    },
    "categories": ["Wellness", "Sports", "Health"],
    "availability_mode": "recurring",
    "availability_start": "2026-02-15",
    "availability_end": "2026-08-15",
    "venue_mode": "business_venue",
    "address": "Carrer del Consell de Cent, 334, Barcelona",
    "preferred_city": "Barcelona",
    "offer_photo": "https://example.com/studio-photo.jpg",
    "published_at": null,
    "is_own": true,
    "created_at": "2026-01-26T15:30:00+00:00",
    "updated_at": "2026-01-26T16:00:00+00:00"
  }
}
```

**Error Response - Cannot Update (400 Bad Request):**

```json
{
  "success": false,
  "message": "Opportunity can only be updated when in draft or published status."
}
```

**Error Response - Unauthorized (403 Forbidden):**

```json
{
  "success": false,
  "message": "You are not authorized to update this opportunity."
}
```

**Error Response - Validation Failed (422 Unprocessable Entity):**

```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "categories": ["The categories field must not have more than 5 items."]
  }
}
```

---

### Publish Opportunity

Publish a draft opportunity to make it visible to all users.

**Endpoint:** `POST /api/v1/opportunities/{opportunity_id}/publish`

**Authentication:** Required

**Authorization Rules:**
- Only the creator can publish
- Only draft opportunities can be published
- **Business users must have an active Stripe subscription**
- Community users can publish for free

**Request Body:** None

**Example Request:**

```http
POST /api/v1/opportunities/9d8f7a5b-4c3e-2a1d-8e9f-7a5b4c3e2a1d/publish
Authorization: Bearer {token}
```

**Success Response (200 OK):**

```json
{
  "success": true,
  "message": "Opportunity published successfully.",
  "data": {
    "id": "9d8f7a5b-4c3e-2a1d-8e9f-7a5b4c3e2a1d",
    "creator_profile": {
      "id": "9d8f7a5b-1111-2222-3333-444444444444",
      "user_type": "business",
      "business_name": "Zen Yoga Studio",
      "avatar_url": "https://example.com/avatar.jpg"
    },
    "title": "Yoga Studio Community Event",
    "description": "We're looking to partner with wellness communities...",
    "status": "published",
    "business_offer": {
      "venue": true,
      "food_drink": true
    },
    "community_deliverables": {
      "instagram_post": true,
      "instagram_story": true,
      "attendee_count": 50
    },
    "categories": ["Wellness", "Sports", "Health"],
    "availability_mode": "recurring",
    "availability_start": "2026-02-15",
    "availability_end": "2026-08-15",
    "venue_mode": "business_venue",
    "address": "Carrer del Consell de Cent, 334, Barcelona",
    "preferred_city": "Barcelona",
    "offer_photo": "https://example.com/studio-photo.jpg",
    "published_at": "2026-01-26T16:30:00+00:00",
    "applications_count": 0,
    "is_own": true,
    "created_at": "2026-01-26T15:30:00+00:00",
    "updated_at": "2026-01-26T16:30:00+00:00"
  }
}
```

**Error Response - Not Draft (400 Bad Request):**

```json
{
  "success": false,
  "message": "Only draft opportunities can be published."
}
```

**Error Response - No Active Subscription (400 Bad Request):**

```json
{
  "success": false,
  "message": "Business users must have an active subscription to publish opportunities."
}
```

**Error Response - Unauthorized (403 Forbidden):**

```json
{
  "success": false,
  "message": "You are not authorized to publish this opportunity."
}
```

---

### Close Opportunity

Close a published opportunity to stop accepting new applications.

**Endpoint:** `POST /api/v1/opportunities/{opportunity_id}/close`

**Authentication:** Required

**Authorization Rules:**
- Only the creator can close
- Only published opportunities can be closed

**Request Body:** None

**Example Request:**

```http
POST /api/v1/opportunities/9d8f7a5b-4c3e-2a1d-8e9f-7a5b4c3e2a1d/close
Authorization: Bearer {token}
```

**Success Response (200 OK):**

```json
{
  "success": true,
  "message": "Opportunity closed successfully.",
  "data": {
    "id": "9d8f7a5b-4c3e-2a1d-8e9f-7a5b4c3e2a1d",
    "creator_profile": {
      "id": "9d8f7a5b-1111-2222-3333-444444444444",
      "user_type": "business",
      "business_name": "Zen Yoga Studio",
      "avatar_url": "https://example.com/avatar.jpg"
    },
    "title": "Yoga Studio Community Event",
    "description": "We're looking to partner with wellness communities...",
    "status": "closed",
    "business_offer": {
      "venue": true,
      "food_drink": true
    },
    "community_deliverables": {
      "instagram_post": true,
      "instagram_story": true,
      "attendee_count": 50
    },
    "categories": ["Wellness", "Sports", "Health"],
    "availability_mode": "recurring",
    "availability_start": "2026-02-15",
    "availability_end": "2026-08-15",
    "venue_mode": "business_venue",
    "address": "Carrer del Consell de Cent, 334, Barcelona",
    "preferred_city": "Barcelona",
    "offer_photo": "https://example.com/studio-photo.jpg",
    "published_at": "2026-01-26T16:30:00+00:00",
    "applications_count": 12,
    "is_own": true,
    "created_at": "2026-01-26T15:30:00+00:00",
    "updated_at": "2026-01-27T10:00:00+00:00"
  }
}
```

**Error Response - Not Published (400 Bad Request):**

```json
{
  "success": false,
  "message": "Only published opportunities can be closed."
}
```

**Error Response - Unauthorized (403 Forbidden):**

```json
{
  "success": false,
  "message": "You are not authorized to close this opportunity."
}
```

---

### Delete Opportunity

Delete a draft opportunity with no applications.

**Endpoint:** `DELETE /api/v1/opportunities/{opportunity_id}`

**Authentication:** Required

**Authorization Rules:**
- Only the creator can delete
- Only draft opportunities can be deleted
- Must have zero applications

**Request Body:** None

**Example Request:**

```http
DELETE /api/v1/opportunities/9d8f7a5b-4c3e-2a1d-8e9f-7a5b4c3e2a1d
Authorization: Bearer {token}
```

**Success Response (200 OK):**

```json
{
  "success": true,
  "message": "Opportunity deleted successfully."
}
```

**Error Response - Cannot Delete (400 Bad Request):**

```json
{
  "success": false,
  "message": "Opportunity can only be deleted when in draft status with no applications."
}
```

**Error Response - Unauthorized (403 Forbidden):**

```json
{
  "success": false,
  "message": "You are not authorized to delete this opportunity."
}
```

---

## Error Handling

### Standard Error Response Format

All error responses follow this structure:

```json
{
  "success": false,
  "message": "Human-readable error message",
  "errors": {
    "field_name": ["Error description"]
  }
}
```

### HTTP Status Codes

| Code | Meaning | Usage |
|------|---------|-------|
| 200 | OK | Successful GET, PUT, POST (non-create), DELETE |
| 201 | Created | Successful POST (create) |
| 400 | Bad Request | Business logic error (cannot publish, cannot delete, etc.) |
| 401 | Unauthorized | Missing or invalid authentication token |
| 403 | Forbidden | Authenticated but not authorized for this action |
| 404 | Not Found | Resource does not exist |
| 422 | Unprocessable Entity | Validation failed |
| 500 | Internal Server Error | Server error |

### Common Validation Errors

**Missing Required Fields:**
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "title": ["The title field is required."],
    "description": ["The description field is required."]
  }
}
```

**Invalid Date Range:**
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "availability_start": ["The availability start must be a date after today."],
    "availability_end": ["The availability end must be a date after availability start."]
  }
}
```

**Invalid Enum Values:**
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "venue_mode": ["The selected venue mode is invalid."],
    "availability_mode": ["The selected availability mode is invalid."]
  }
}
```

**Array Size Validation:**
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "categories": ["The categories field must have at least 1 items."],
    "categories": ["The categories field must not have more than 5 items."]
  }
}
```

---

## Business Rules

### Opportunity Lifecycle

1. **Creation**: Any authenticated user can create an opportunity in draft status
2. **Publishing**:
   - Business users need an active Stripe subscription
   - Community users can publish for free
   - Only draft opportunities can be published
3. **Updating**:
   - Only draft or published opportunities can be updated
   - Closed/completed opportunities are immutable
4. **Closing**:
   - Only published opportunities can be closed
   - Applications can still be managed after closing
5. **Deletion**:
   - Only draft opportunities with zero applications can be deleted
   - Published/closed opportunities cannot be deleted

### Authorization Matrix

| Action | Business (Draft) | Business (Published) | Community (Draft) | Community (Published) | Other Users |
|--------|------------------|----------------------|-------------------|----------------------|-------------|
| View | Creator only | Everyone | Creator only | Everyone | Published only |
| Update | Creator | Creator | Creator | Creator | No |
| Delete | Creator (no apps) | No | Creator (no apps) | No | No |
| Publish | Creator (subscription required) | No | Creator | No | No |
| Close | No | Creator | No | Creator | No |

### Subscription Requirements

- **Business users publishing**: Must have `business_subscriptions` record with `status = 'active'`
- **Community users publishing**: No subscription required (free access)
- **Draft creation**: No subscription required for anyone
- **Viewing opportunities**: No subscription required

### Application Rules

- One user can apply only once per opportunity (enforced by unique constraint)
- Applications can be made to published opportunities only
- Creators cannot apply to their own opportunities

### Data Validation

- **Dates**: `availability_start` must be after today, `availability_end` must be after `availability_start`
- **Categories**: Must have 1-5 category strings
- **Address**: Required unless `venue_mode = 'no_venue'`
- **Title**: Max 255 characters
- **Description**: Max 5000 characters
- **Preferred City**: Max 100 characters
- **Photo URL**: Must be a valid URL if provided

### JSONB Field Flexibility

- `business_offer` and `community_deliverables` are flexible JSON objects
- No strict schema enforcement at the database level
- Mobile app should define expected structure for UX consistency
- Backend stores any valid JSON object

---

## Mobile Development Tips

### Handling Status Changes

Track status locally and update UI accordingly:

```javascript
// Example status flow
const statusFlow = {
  draft: { next: 'published', action: 'publish', canEdit: true, canDelete: true },
  published: { next: 'closed', action: 'close', canEdit: true, canDelete: false },
  closed: { next: null, action: null, canEdit: false, canDelete: false },
  completed: { next: null, action: null, canEdit: false, canDelete: false }
};
```

### Subscription Check Before Publishing

For business users, check subscription status before showing the publish button:

```javascript
if (user.user_type === 'business' && !user.has_active_subscription) {
  // Show "Subscribe to publish" UI
  // Redirect to subscription flow
}
```

### Form Validation

Implement client-side validation matching backend rules:

```javascript
const validateOpportunity = (data) => {
  const errors = {};

  if (!data.title || data.title.length > 255) {
    errors.title = 'Title is required (max 255 characters)';
  }

  if (!data.categories || data.categories.length < 1 || data.categories.length > 5) {
    errors.categories = 'Select 1-5 categories';
  }

  if (new Date(data.availability_start) <= new Date()) {
    errors.availability_start = 'Start date must be in the future';
  }

  if (data.venue_mode !== 'no_venue' && !data.address) {
    errors.address = 'Address is required for this venue mode';
  }

  return errors;
};
```

### Pagination Best Practices

- Default to `per_page=20` for good performance
- Implement infinite scroll or "Load More" button
- Cache previous pages to avoid unnecessary requests
- Show loading state during pagination

### Search and Filter UX

- Debounce search input (300-500ms) to reduce API calls
- Persist filter state in local storage
- Clear filters button for better UX
- Show active filter count badge

### Optimistic Updates

For better UX, update local state optimistically:

```javascript
// Example: Optimistic publish
const publishOpportunity = async (opportunityId) => {
  // Update local state immediately
  updateLocalOpportunity(opportunityId, { status: 'published' });

  try {
    // Call API
    await api.post(`/opportunities/${opportunityId}/publish`);
  } catch (error) {
    // Revert on error
    updateLocalOpportunity(opportunityId, { status: 'draft' });
    showError(error.message);
  }
};
```

### Error Message Localization

The backend returns localized error messages. Display them directly in your UI:

```javascript
// Display validation errors
if (response.status === 422) {
  const { errors } = response.data;
  Object.keys(errors).forEach(field => {
    showFieldError(field, errors[field][0]);
  });
}
```

---

## Support

For questions or issues with this API, contact the backend development team.

**Last Updated:** 2026-01-26
**API Version:** 1.0
