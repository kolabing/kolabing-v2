# Kolabing Mobile API - Opportunity User Flows

This document demonstrates complete API request/response flows for common user journeys.

## Table of Contents
- [Business User Journey: Create and Publish](#business-user-journey-create-and-publish)
- [Community User Journey: Browse and Apply](#community-user-journey-browse-and-apply)
- [Managing Published Opportunities](#managing-published-opportunities)
- [Error Scenarios](#error-scenarios)

---

## Business User Journey: Create and Publish

### Step 1: Business User Creates Draft Opportunity

**Request:**
```http
POST /api/v1/opportunities
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc...
Content-Type: application/json

{
  "title": "Weekend Brunch Collaboration",
  "description": "We're a new brunch cafe in Barcelona looking to collaborate with food & lifestyle communities. We can host your group for a weekend brunch experience, provide complimentary food and drinks, and offer 20% discount codes for your followers.",
  "business_offer": {
    "venue": true,
    "food_drink": true,
    "discount": {
      "enabled": true,
      "percentage": 20
    },
    "products": ["Brunch menu", "Coffee", "Pastries"],
    "other": "Professional photography of the event for your social media"
  },
  "community_deliverables": {
    "instagram_post": true,
    "instagram_story": true,
    "tiktok_video": false,
    "event_mention": false,
    "attendee_count": 25,
    "other": "Tag our cafe in all posts and stories"
  },
  "categories": ["Food & Drink", "Culture", "Community"],
  "availability_mode": "one_time",
  "availability_start": "2026-03-15",
  "availability_end": "2026-03-16",
  "venue_mode": "business_venue",
  "address": "Carrer de Pau Claris, 142, Barcelona",
  "preferred_city": "Barcelona",
  "offer_photo": "https://storage.kolabing.com/cafes/brunch-space-01.jpg"
}
```

**Response: 201 Created**
```json
{
  "success": true,
  "message": "Opportunity created successfully.",
  "data": {
    "id": "a1b2c3d4-e5f6-4a5b-8c9d-0e1f2a3b4c5d",
    "creator_profile": {
      "id": "b2c3d4e5-f6a7-5b6c-9d0e-1f2a3b4c5d6e",
      "user_type": "business",
      "business_name": "Sunrise Brunch Cafe",
      "business_type": "Restaurant",
      "avatar_url": "https://storage.kolabing.com/avatars/sunrise-cafe.jpg"
    },
    "title": "Weekend Brunch Collaboration",
    "description": "We're a new brunch cafe in Barcelona...",
    "status": "draft",
    "business_offer": {
      "venue": true,
      "food_drink": true,
      "discount": {
        "enabled": true,
        "percentage": 20
      },
      "products": ["Brunch menu", "Coffee", "Pastries"],
      "other": "Professional photography of the event for your social media"
    },
    "community_deliverables": {
      "instagram_post": true,
      "instagram_story": true,
      "tiktok_video": false,
      "event_mention": false,
      "attendee_count": 25,
      "other": "Tag our cafe in all posts and stories"
    },
    "categories": ["Food & Drink", "Culture", "Community"],
    "availability_mode": "one_time",
    "availability_start": "2026-03-15",
    "availability_end": "2026-03-16",
    "venue_mode": "business_venue",
    "address": "Carrer de Pau Claris, 142, Barcelona",
    "preferred_city": "Barcelona",
    "offer_photo": "https://storage.kolabing.com/cafes/brunch-space-01.jpg",
    "published_at": null,
    "is_own": true,
    "created_at": "2026-01-26T17:45:30+00:00",
    "updated_at": "2026-01-26T17:45:30+00:00"
  }
}
```

### Step 2: Business User Reviews Draft

**Request:**
```http
GET /api/v1/me/opportunities?status=draft
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc...
```

**Response: 200 OK**
```json
{
  "success": true,
  "data": [
    {
      "id": "a1b2c3d4-e5f6-4a5b-8c9d-0e1f2a3b4c5d",
      "creator_profile": {
        "id": "b2c3d4e5-f6a7-5b6c-9d0e-1f2a3b4c5d6e",
        "user_type": "business",
        "business_name": "Sunrise Brunch Cafe",
        "avatar_url": "https://storage.kolabing.com/avatars/sunrise-cafe.jpg"
      },
      "title": "Weekend Brunch Collaboration",
      "description": "We're a new brunch cafe in Barcelona...",
      "status": "draft",
      "business_offer": { "venue": true, "food_drink": true },
      "community_deliverables": { "instagram_post": true, "instagram_story": true },
      "categories": ["Food & Drink", "Culture", "Community"],
      "availability_mode": "one_time",
      "availability_start": "2026-03-15",
      "availability_end": "2026-03-16",
      "venue_mode": "business_venue",
      "address": "Carrer de Pau Claris, 142, Barcelona",
      "preferred_city": "Barcelona",
      "offer_photo": "https://storage.kolabing.com/cafes/brunch-space-01.jpg",
      "published_at": null,
      "applications_count": 0,
      "is_own": true,
      "created_at": "2026-01-26T17:45:30+00:00",
      "updated_at": "2026-01-26T17:45:30+00:00"
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

### Step 3: Business User Makes Quick Edit

**Request:**
```http
PUT /api/v1/opportunities/a1b2c3d4-e5f6-4a5b-8c9d-0e1f2a3b4c5d
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc...
Content-Type: application/json

{
  "community_deliverables": {
    "instagram_post": true,
    "instagram_story": true,
    "tiktok_video": true,
    "event_mention": false,
    "attendee_count": 30,
    "other": "Tag our cafe in all posts and stories, include discount code"
  }
}
```

**Response: 200 OK**
```json
{
  "success": true,
  "message": "Opportunity updated successfully.",
  "data": {
    "id": "a1b2c3d4-e5f6-4a5b-8c9d-0e1f2a3b4c5d",
    "creator_profile": {
      "id": "b2c3d4e5-f6a7-5b6c-9d0e-1f2a3b4c5d6e",
      "user_type": "business",
      "business_name": "Sunrise Brunch Cafe",
      "avatar_url": "https://storage.kolabing.com/avatars/sunrise-cafe.jpg"
    },
    "title": "Weekend Brunch Collaboration",
    "description": "We're a new brunch cafe in Barcelona...",
    "status": "draft",
    "business_offer": {
      "venue": true,
      "food_drink": true,
      "discount": { "enabled": true, "percentage": 20 }
    },
    "community_deliverables": {
      "instagram_post": true,
      "instagram_story": true,
      "tiktok_video": true,
      "event_mention": false,
      "attendee_count": 30,
      "other": "Tag our cafe in all posts and stories, include discount code"
    },
    "categories": ["Food & Drink", "Culture", "Community"],
    "availability_mode": "one_time",
    "availability_start": "2026-03-15",
    "availability_end": "2026-03-16",
    "venue_mode": "business_venue",
    "address": "Carrer de Pau Claris, 142, Barcelona",
    "preferred_city": "Barcelona",
    "offer_photo": "https://storage.kolabing.com/cafes/brunch-space-01.jpg",
    "published_at": null,
    "is_own": true,
    "created_at": "2026-01-26T17:45:30+00:00",
    "updated_at": "2026-01-26T17:50:15+00:00"
  }
}
```

### Step 4: Business User Publishes (With Active Subscription)

**Request:**
```http
POST /api/v1/opportunities/a1b2c3d4-e5f6-4a5b-8c9d-0e1f2a3b4c5d/publish
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc...
```

**Response: 200 OK**
```json
{
  "success": true,
  "message": "Opportunity published successfully.",
  "data": {
    "id": "a1b2c3d4-e5f6-4a5b-8c9d-0e1f2a3b4c5d",
    "creator_profile": {
      "id": "b2c3d4e5-f6a7-5b6c-9d0e-1f2a3b4c5d6e",
      "user_type": "business",
      "business_name": "Sunrise Brunch Cafe",
      "avatar_url": "https://storage.kolabing.com/avatars/sunrise-cafe.jpg"
    },
    "title": "Weekend Brunch Collaboration",
    "description": "We're a new brunch cafe in Barcelona...",
    "status": "published",
    "business_offer": {
      "venue": true,
      "food_drink": true,
      "discount": { "enabled": true, "percentage": 20 }
    },
    "community_deliverables": {
      "instagram_post": true,
      "instagram_story": true,
      "tiktok_video": true,
      "attendee_count": 30
    },
    "categories": ["Food & Drink", "Culture", "Community"],
    "availability_mode": "one_time",
    "availability_start": "2026-03-15",
    "availability_end": "2026-03-16",
    "venue_mode": "business_venue",
    "address": "Carrer de Pau Claris, 142, Barcelona",
    "preferred_city": "Barcelona",
    "offer_photo": "https://storage.kolabing.com/cafes/brunch-space-01.jpg",
    "published_at": "2026-01-26T17:55:00+00:00",
    "applications_count": 0,
    "is_own": true,
    "created_at": "2026-01-26T17:45:30+00:00",
    "updated_at": "2026-01-26T17:55:00+00:00"
  }
}
```

---

## Community User Journey: Browse and Apply

### Step 1: Community User Browses Opportunities in Barcelona

**Request:**
```http
GET /api/v1/opportunities?city=Barcelona&categories[]=Food & Drink&per_page=10&page=1
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc...
```

**Response: 200 OK**
```json
{
  "success": true,
  "data": [
    {
      "id": "a1b2c3d4-e5f6-4a5b-8c9d-0e1f2a3b4c5d",
      "creator_profile": {
        "id": "b2c3d4e5-f6a7-5b6c-9d0e-1f2a3b4c5d6e",
        "user_type": "business",
        "business_name": "Sunrise Brunch Cafe",
        "business_type": "Restaurant",
        "avatar_url": "https://storage.kolabing.com/avatars/sunrise-cafe.jpg"
      },
      "title": "Weekend Brunch Collaboration",
      "description": "We're a new brunch cafe in Barcelona...",
      "status": "published",
      "business_offer": {
        "venue": true,
        "food_drink": true,
        "discount": { "enabled": true, "percentage": 20 }
      },
      "community_deliverables": {
        "instagram_post": true,
        "instagram_story": true,
        "tiktok_video": true,
        "attendee_count": 30
      },
      "categories": ["Food & Drink", "Culture", "Community"],
      "availability_mode": "one_time",
      "availability_start": "2026-03-15",
      "availability_end": "2026-03-16",
      "venue_mode": "business_venue",
      "address": "Carrer de Pau Claris, 142, Barcelona",
      "preferred_city": "Barcelona",
      "offer_photo": "https://storage.kolabing.com/cafes/brunch-space-01.jpg",
      "published_at": "2026-01-26T17:55:00+00:00",
      "applications_count": 0,
      "is_own": false,
      "has_applied": false,
      "created_at": "2026-01-26T17:45:30+00:00",
      "updated_at": "2026-01-26T17:55:00+00:00"
    },
    {
      "id": "b3c4d5e6-f7a8-5b6c-9d0e-1f2a3b4c5d6e",
      "creator_profile": {
        "id": "c4d5e6f7-a8b9-6c7d-0e1f-2a3b4c5d6e7f",
        "user_type": "business",
        "business_name": "Yoga Studio Barcelona",
        "avatar_url": "https://storage.kolabing.com/avatars/yoga-studio.jpg"
      },
      "title": "Wellness Community Events",
      "description": "Monthly wellness events at our studio...",
      "status": "published",
      "business_offer": { "venue": true, "food_drink": true },
      "community_deliverables": { "instagram_post": true, "attendee_count": 20 },
      "categories": ["Wellness", "Sports", "Community"],
      "availability_mode": "recurring",
      "availability_start": "2026-02-01",
      "availability_end": "2026-12-31",
      "venue_mode": "business_venue",
      "address": "Carrer de Consell de Cent, 334, Barcelona",
      "preferred_city": "Barcelona",
      "offer_photo": "https://storage.kolabing.com/yoga/studio-01.jpg",
      "published_at": "2026-01-25T10:00:00+00:00",
      "applications_count": 3,
      "is_own": false,
      "has_applied": false,
      "created_at": "2026-01-25T09:30:00+00:00",
      "updated_at": "2026-01-25T10:00:00+00:00"
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 2,
    "per_page": 10,
    "total": 15
  }
}
```

### Step 2: Community User Views Single Opportunity Details

**Request:**
```http
GET /api/v1/opportunities/a1b2c3d4-e5f6-4a5b-8c9d-0e1f2a3b4c5d
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc...
```

**Response: 200 OK**
```json
{
  "success": true,
  "data": {
    "id": "a1b2c3d4-e5f6-4a5b-8c9d-0e1f2a3b4c5d",
    "creator_profile": {
      "id": "b2c3d4e5-f6a7-5b6c-9d0e-1f2a3b4c5d6e",
      "user_type": "business",
      "business_name": "Sunrise Brunch Cafe",
      "business_type": "Restaurant",
      "city": "Barcelona",
      "avatar_url": "https://storage.kolabing.com/avatars/sunrise-cafe.jpg",
      "instagram_handle": "@sunrisebcn"
    },
    "title": "Weekend Brunch Collaboration",
    "description": "We're a new brunch cafe in Barcelona looking to collaborate with food & lifestyle communities. We can host your group for a weekend brunch experience, provide complimentary food and drinks, and offer 20% discount codes for your followers.",
    "status": "published",
    "business_offer": {
      "venue": true,
      "food_drink": true,
      "discount": {
        "enabled": true,
        "percentage": 20
      },
      "products": ["Brunch menu", "Coffee", "Pastries"],
      "other": "Professional photography of the event for your social media"
    },
    "community_deliverables": {
      "instagram_post": true,
      "instagram_story": true,
      "tiktok_video": true,
      "event_mention": false,
      "attendee_count": 30,
      "other": "Tag our cafe in all posts and stories, include discount code"
    },
    "categories": ["Food & Drink", "Culture", "Community"],
    "availability_mode": "one_time",
    "availability_start": "2026-03-15",
    "availability_end": "2026-03-16",
    "venue_mode": "business_venue",
    "address": "Carrer de Pau Claris, 142, Barcelona",
    "preferred_city": "Barcelona",
    "offer_photo": "https://storage.kolabing.com/cafes/brunch-space-01.jpg",
    "published_at": "2026-01-26T17:55:00+00:00",
    "applications_count": 2,
    "is_own": false,
    "has_applied": false,
    "created_at": "2026-01-26T17:45:30+00:00",
    "updated_at": "2026-01-26T18:30:00+00:00"
  }
}
```

---

## Managing Published Opportunities

### Close Published Opportunity

**Request:**
```http
POST /api/v1/opportunities/a1b2c3d4-e5f6-4a5b-8c9d-0e1f2a3b4c5d/close
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc...
```

**Response: 200 OK**
```json
{
  "success": true,
  "message": "Opportunity closed successfully.",
  "data": {
    "id": "a1b2c3d4-e5f6-4a5b-8c9d-0e1f2a3b4c5d",
    "creator_profile": {
      "id": "b2c3d4e5-f6a7-5b6c-9d0e-1f2a3b4c5d6e",
      "user_type": "business",
      "business_name": "Sunrise Brunch Cafe",
      "avatar_url": "https://storage.kolabing.com/avatars/sunrise-cafe.jpg"
    },
    "title": "Weekend Brunch Collaboration",
    "description": "We're a new brunch cafe in Barcelona...",
    "status": "closed",
    "business_offer": {
      "venue": true,
      "food_drink": true,
      "discount": { "enabled": true, "percentage": 20 }
    },
    "community_deliverables": {
      "instagram_post": true,
      "instagram_story": true,
      "tiktok_video": true,
      "attendee_count": 30
    },
    "categories": ["Food & Drink", "Culture", "Community"],
    "availability_mode": "one_time",
    "availability_start": "2026-03-15",
    "availability_end": "2026-03-16",
    "venue_mode": "business_venue",
    "address": "Carrer de Pau Claris, 142, Barcelona",
    "preferred_city": "Barcelona",
    "offer_photo": "https://storage.kolabing.com/cafes/brunch-space-01.jpg",
    "published_at": "2026-01-26T17:55:00+00:00",
    "applications_count": 8,
    "is_own": true,
    "created_at": "2026-01-26T17:45:30+00:00",
    "updated_at": "2026-01-27T12:00:00+00:00"
  }
}
```

### Get My Opportunities (All Statuses)

**Request:**
```http
GET /api/v1/me/opportunities
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc...
```

**Response: 200 OK**
```json
{
  "success": true,
  "data": [
    {
      "id": "a1b2c3d4-e5f6-4a5b-8c9d-0e1f2a3b4c5d",
      "creator_profile": {
        "id": "b2c3d4e5-f6a7-5b6c-9d0e-1f2a3b4c5d6e",
        "user_type": "business",
        "business_name": "Sunrise Brunch Cafe",
        "avatar_url": "https://storage.kolabing.com/avatars/sunrise-cafe.jpg"
      },
      "title": "Weekend Brunch Collaboration",
      "status": "closed",
      "categories": ["Food & Drink", "Culture", "Community"],
      "published_at": "2026-01-26T17:55:00+00:00",
      "applications_count": 8,
      "is_own": true,
      "created_at": "2026-01-26T17:45:30+00:00",
      "updated_at": "2026-01-27T12:00:00+00:00"
    },
    {
      "id": "c5d6e7f8-a9b0-6c7d-0e1f-2a3b4c5d6e7f",
      "creator_profile": {
        "id": "b2c3d4e5-f6a7-5b6c-9d0e-1f2a3b4c5d6e",
        "user_type": "business",
        "business_name": "Sunrise Brunch Cafe",
        "avatar_url": "https://storage.kolabing.com/avatars/sunrise-cafe.jpg"
      },
      "title": "Summer Rooftop Events",
      "status": "published",
      "categories": ["Food & Drink", "Entertainment"],
      "published_at": "2026-01-27T10:00:00+00:00",
      "applications_count": 2,
      "is_own": true,
      "created_at": "2026-01-27T09:30:00+00:00",
      "updated_at": "2026-01-27T10:00:00+00:00"
    },
    {
      "id": "d7e8f9a0-b1c2-7d8e-1f2a-3b4c5d6e7f8a",
      "creator_profile": {
        "id": "b2c3d4e5-f6a7-5b6c-9d0e-1f2a3b4c5d6e",
        "user_type": "business",
        "business_name": "Sunrise Brunch Cafe",
        "avatar_url": "https://storage.kolabing.com/avatars/sunrise-cafe.jpg"
      },
      "title": "Holiday Season Pop-up",
      "status": "draft",
      "categories": ["Food & Drink", "Culture"],
      "published_at": null,
      "applications_count": 0,
      "is_own": true,
      "created_at": "2026-01-27T14:00:00+00:00",
      "updated_at": "2026-01-27T14:00:00+00:00"
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 20,
    "total": 3
  }
}
```

---

## Error Scenarios

### Attempt to Publish Without Subscription (Business User)

**Request:**
```http
POST /api/v1/opportunities/a1b2c3d4-e5f6-4a5b-8c9d-0e1f2a3b4c5d/publish
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc...
```

**Response: 400 Bad Request**
```json
{
  "success": false,
  "message": "Business users must have an active subscription to publish opportunities."
}
```

**Mobile App Action:** Redirect to subscription purchase flow

### Validation Failed - Missing Required Fields

**Request:**
```http
POST /api/v1/opportunities
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc...
Content-Type: application/json

{
  "title": "My Opportunity"
}
```

**Response: 422 Unprocessable Entity**
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "description": ["The description field is required."],
    "business_offer": ["The business offer field is required."],
    "community_deliverables": ["The community deliverables field is required."],
    "categories": ["The categories field is required."],
    "availability_mode": ["The availability mode field is required."],
    "availability_start": ["The availability start field is required."],
    "availability_end": ["The availability end field is required."],
    "venue_mode": ["The venue mode field is required."],
    "preferred_city": ["The preferred city field is required."]
  }
}
```

**Mobile App Action:** Display field-specific error messages in the form

### Unauthorized Update Attempt

**Request:**
```http
PUT /api/v1/opportunities/a1b2c3d4-e5f6-4a5b-8c9d-0e1f2a3b4c5d
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc...
Content-Type: application/json

{
  "title": "Trying to edit someone else's opportunity"
}
```

**Response: 403 Forbidden**
```json
{
  "success": false,
  "message": "You are not authorized to update this opportunity."
}
```

**Mobile App Action:** Show error toast, do not allow editing

### Attempt to Delete Published Opportunity

**Request:**
```http
DELETE /api/v1/opportunities/a1b2c3d4-e5f6-4a5b-8c9d-0e1f2a3b4c5d
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc...
```

**Response: 400 Bad Request**
```json
{
  "success": false,
  "message": "Opportunity can only be deleted when in draft status with no applications."
}
```

**Mobile App Action:** Show error message, explain that published opportunities cannot be deleted

### Invalid Date Range

**Request:**
```http
POST /api/v1/opportunities
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc...
Content-Type: application/json

{
  "title": "Test Opportunity",
  "description": "Testing dates",
  "business_offer": { "venue": true },
  "community_deliverables": { "instagram_post": true },
  "categories": ["Food & Drink"],
  "availability_mode": "one_time",
  "availability_start": "2026-01-20",
  "availability_end": "2026-01-19",
  "venue_mode": "business_venue",
  "address": "Test address",
  "preferred_city": "Barcelona"
}
```

**Response: 422 Unprocessable Entity**
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

**Mobile App Action:** Highlight date fields with errors, show validation messages

### Too Many Categories

**Request:**
```http
POST /api/v1/opportunities
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc...
Content-Type: application/json

{
  "title": "Test Opportunity",
  "description": "Testing categories",
  "business_offer": { "venue": true },
  "community_deliverables": { "instagram_post": true },
  "categories": ["Cat1", "Cat2", "Cat3", "Cat4", "Cat5", "Cat6"],
  "availability_mode": "one_time",
  "availability_start": "2026-02-01",
  "availability_end": "2026-02-02",
  "venue_mode": "business_venue",
  "address": "Test address",
  "preferred_city": "Barcelona"
}
```

**Response: 422 Unprocessable Entity**
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "categories": ["The categories field must not have more than 5 items."]
  }
}
```

**Mobile App Action:** Limit category selection to max 5, disable selection after 5 chosen

---

## Mobile Implementation Checklist

### Before Creating Opportunity
- [ ] Check all required fields are filled
- [ ] Validate date range (start > today, end > start)
- [ ] Ensure 1-5 categories selected
- [ ] Validate address if venue_mode !== 'no_venue'
- [ ] Validate URL format for offer_photo if provided

### Before Publishing
- [ ] Check user type
- [ ] If business user, verify has_active_subscription
- [ ] If no subscription, show subscription flow
- [ ] Confirm opportunity is in draft status
- [ ] Show confirmation dialog before publishing

### After Successful Publish
- [ ] Update local state from 'draft' to 'published'
- [ ] Refresh opportunity list
- [ ] Show success toast
- [ ] Navigate to published opportunity detail

### Error Handling
- [ ] Parse validation errors and display field-specific messages
- [ ] Show business rule errors in dialogs
- [ ] Handle 403 errors gracefully (redirect or hide actions)
- [ ] Implement retry logic for network errors
- [ ] Log errors for debugging

### Pagination
- [ ] Implement infinite scroll or "Load More"
- [ ] Show loading state during fetch
- [ ] Cache already loaded pages
- [ ] Handle empty states

### Filtering
- [ ] Debounce search input (500ms)
- [ ] Show active filter badges
- [ ] Clear filters button
- [ ] Persist filter state in local storage

---

**Last Updated:** 2026-01-26
**API Version:** 1.0
