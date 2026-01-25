# Kolabing Mobile API Documentation - Collaboration System

**Version:** 1.0
**Base URL:** `https://api.kolabing.com/api/v1`
**Authentication:** Bearer Token (Laravel Sanctum)

---

## Quick Reference

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/opportunities` | GET | Browse opportunities |
| `/opportunities/{id}` | GET | Get opportunity details |
| `/opportunities` | POST | Create opportunity |
| `/opportunities/{id}` | PUT | Update opportunity |
| `/opportunities/{id}` | DELETE | Delete opportunity |
| `/opportunities/{id}/publish` | POST | Publish opportunity |
| `/opportunities/{id}/close` | POST | Close opportunity |
| `/me/opportunities` | GET | List my opportunities |
| `/opportunities/{id}/applications` | GET | List applications for opportunity |
| `/opportunities/{id}/applications` | POST | Apply to opportunity |
| `/applications/{id}` | GET | Get application details |
| `/applications/{id}/accept` | POST | Accept application |
| `/applications/{id}/decline` | POST | Decline application |
| `/applications/{id}/withdraw` | POST | Withdraw application |
| `/me/applications` | GET | List my sent applications |
| `/me/received-applications` | GET | List received applications |
| `/collaborations` | GET | List my collaborations |
| `/collaborations/{id}` | GET | Get collaboration details |
| `/collaborations/{id}/activate` | POST | Activate collaboration |
| `/collaborations/{id}/complete` | POST | Complete collaboration |
| `/collaborations/{id}/cancel` | POST | Cancel collaboration |

---

## Authentication Headers

All endpoints require:

```
Authorization: Bearer {your_token}
Content-Type: application/json
Accept: application/json
```

---

## 1. Collab Opportunities

### 1.1 Browse Opportunities

**GET** `/opportunities`

Browse all published opportunities with filters.

**Query Parameters:**

| Param | Type | Description | Example |
|-------|------|-------------|---------|
| `creator_type` | string | `business` or `community` | `?creator_type=business` |
| `categories` | string | Comma-separated | `?categories=Food%20%26%20Drink,Sports` |
| `city` | string | City name | `?city=Barcelona` |
| `venue_mode` | string | `business_venue`, `community_venue`, `no_venue` | `?venue_mode=business_venue` |
| `availability_mode` | string | `one_time`, `recurring`, `flexible` | `?availability_mode=flexible` |
| `availability_from` | date | ISO format | `?availability_from=2026-02-01` |
| `availability_to` | date | ISO format | `?availability_to=2026-03-01` |
| `search` | string | Search text | `?search=brunch` |
| `page` | int | Page number | `?page=1` |
| `per_page` | int | Items per page (max 100) | `?per_page=20` |

**Example Request:**

```bash
GET /api/v1/opportunities?creator_type=business&city=Barcelona&page=1
Authorization: Bearer eyJ0eXAiOiJKV1Q...
```

**Example Response (200 OK):**

```json
{
  "data": [
    {
      "id": "550e8400-e29b-41d4-a716-446655440000",
      "creator_profile": {
        "id": "123e4567-e89b-12d3-a456-426614174000",
        "user_type": "business",
        "display_name": "Cafe Central",
        "avatar_url": "https://storage.kolabing.com/avatars/cafe-central.jpg",
        "city": "Barcelona",
        "business_type": "Restaurant"
      },
      "title": "Instagram Collab for Brunch Event",
      "description": "Looking for lifestyle influencers to promote our new brunch menu",
      "status": "published",
      "business_offer": {
        "venue": true,
        "food_drink": true,
        "discount": {
          "enabled": true,
          "percentage": 20
        },
        "products": false,
        "other": null
      },
      "community_deliverables": {
        "instagram_post": true,
        "instagram_story": true,
        "tiktok_video": false,
        "event_mention": true,
        "attendee_count": 50
      },
      "categories": ["Food & Drink", "Lifestyle"],
      "availability_mode": "flexible",
      "availability_start": "2026-02-01",
      "availability_end": "2026-02-28",
      "venue_mode": "business_venue",
      "address": "Carrer de Pelai, 62, Barcelona",
      "preferred_city": "Barcelona",
      "offer_photo": "https://storage.kolabing.com/offers/brunch-event.jpg",
      "published_at": "2026-01-20T09:00:00Z",
      "applications_count": 5,
      "is_own": false,
      "has_applied": false,
      "created_at": "2026-01-15T10:30:00Z",
      "updated_at": "2026-01-20T09:00:00Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 20,
    "total": 98,
    "from": 1,
    "to": 20
  }
}
```

---

### 1.2 Get Opportunity Details

**GET** `/opportunities/{id}`

**Example Request:**

```bash
GET /api/v1/opportunities/550e8400-e29b-41d4-a716-446655440000
Authorization: Bearer eyJ0eXAiOiJKV1Q...
```

**Example Response (200 OK):**

```json
{
  "data": {
    "id": "550e8400-e29b-41d4-a716-446655440000",
    "creator_profile": {
      "id": "123e4567-e89b-12d3-a456-426614174000",
      "user_type": "business",
      "display_name": "Cafe Central",
      "avatar_url": "https://storage.kolabing.com/avatars/cafe-central.jpg",
      "city": "Barcelona",
      "business_type": "Restaurant",
      "bio": "Modern cafe in the heart of Barcelona"
    },
    "title": "Instagram Collab for Brunch Event",
    "description": "Looking for lifestyle influencers to promote our new brunch menu. We offer a beautiful venue, complimentary brunch for you and a guest, and ongoing partnership opportunities.",
    "status": "published",
    "business_offer": {
      "venue": true,
      "food_drink": true,
      "discount": {
        "enabled": true,
        "percentage": 20
      },
      "products": false,
      "other": null
    },
    "community_deliverables": {
      "instagram_post": true,
      "instagram_story": true,
      "tiktok_video": false,
      "event_mention": true,
      "attendee_count": 50
    },
    "categories": ["Food & Drink", "Lifestyle"],
    "availability_mode": "flexible",
    "availability_start": "2026-02-01",
    "availability_end": "2026-02-28",
    "venue_mode": "business_venue",
    "address": "Carrer de Pelai, 62, Barcelona",
    "preferred_city": "Barcelona",
    "offer_photo": "https://storage.kolabing.com/offers/brunch-event.jpg",
    "published_at": "2026-01-20T09:00:00Z",
    "applications_count": 5,
    "is_own": false,
    "has_applied": false,
    "my_application": null,
    "created_at": "2026-01-15T10:30:00Z",
    "updated_at": "2026-01-20T09:00:00Z"
  }
}
```

---

### 1.3 Create Opportunity

**POST** `/opportunities`

Creates a new opportunity in draft status.

**Request Body:**

```json
{
  "title": "Instagram Collab for Brunch Event",
  "description": "Looking for lifestyle influencers to promote our new brunch menu",
  "business_offer": {
    "venue": true,
    "food_drink": true,
    "discount": {
      "enabled": true,
      "percentage": 20
    },
    "products": false,
    "other": null
  },
  "community_deliverables": {
    "instagram_post": true,
    "instagram_story": true,
    "tiktok_video": false,
    "event_mention": true,
    "attendee_count": 50
  },
  "categories": ["Food & Drink", "Lifestyle"],
  "availability_mode": "flexible",
  "availability_start": "2026-02-01",
  "availability_end": "2026-02-28",
  "venue_mode": "business_venue",
  "address": "Carrer de Pelai, 62, Barcelona",
  "preferred_city": "Barcelona",
  "offer_photo": "https://storage.kolabing.com/offers/brunch-event.jpg"
}
```

**Validation Rules:**

| Field | Required | Rules |
|-------|----------|-------|
| `title` | Yes | Max 255 characters |
| `description` | Yes | Max 5000 characters |
| `business_offer` | Yes | Valid JSON object |
| `community_deliverables` | Yes | Valid JSON object |
| `categories` | Yes | Array, min 1, max 5 items |
| `availability_mode` | Yes | `one_time`, `recurring`, `flexible` |
| `availability_start` | Yes | ISO date, must be future |
| `availability_end` | Yes | Must be after start date |
| `venue_mode` | Yes | `business_venue`, `community_venue`, `no_venue` |
| `address` | Conditional | Required if venue_mode is not `no_venue` |
| `preferred_city` | Yes | Max 100 characters |
| `offer_photo` | No | Valid URL |

**Example Response (201 Created):**

```json
{
  "data": {
    "id": "550e8400-e29b-41d4-a716-446655440000",
    "status": "draft",
    "title": "Instagram Collab for Brunch Event",
    "published_at": null,
    "applications_count": 0,
    "is_own": true,
    "has_applied": false,
    "created_at": "2026-01-25T10:30:00Z",
    "updated_at": "2026-01-25T10:30:00Z"
  },
  "message": "Opportunity created as draft"
}
```

---

### 1.4 Update Opportunity

**PUT** `/opportunities/{id}`

Update an existing opportunity. All fields are optional.

**Example Request:**

```bash
PUT /api/v1/opportunities/550e8400-e29b-41d4-a716-446655440000
Authorization: Bearer eyJ0eXAiOiJKV1Q...
Content-Type: application/json

{
  "title": "Updated Title",
  "description": "Updated description..."
}
```

**Example Response (200 OK):**

```json
{
  "data": {
    "id": "550e8400-e29b-41d4-a716-446655440000",
    "title": "Updated Title",
    "description": "Updated description..."
  },
  "message": "Opportunity updated successfully"
}
```

---

### 1.5 Delete Opportunity

**DELETE** `/opportunities/{id}`

Only draft opportunities with no applications can be deleted.

**Example Response (200 OK):**

```json
{
  "message": "Opportunity deleted successfully"
}
```

---

### 1.6 Publish Opportunity

**POST** `/opportunities/{id}/publish`

Publish a draft opportunity.

> **Note:** Business users require active subscription.

**Example Response (200 OK):**

```json
{
  "data": {
    "id": "550e8400-e29b-41d4-a716-446655440000",
    "status": "published",
    "published_at": "2026-01-25T10:30:00Z"
  },
  "message": "Opportunity published successfully"
}
```

---

### 1.7 Close Opportunity

**POST** `/opportunities/{id}/close`

Close a published opportunity to stop accepting applications.

**Example Response (200 OK):**

```json
{
  "data": {
    "id": "550e8400-e29b-41d4-a716-446655440000",
    "status": "closed"
  },
  "message": "Opportunity closed successfully"
}
```

---

### 1.8 List My Opportunities

**GET** `/me/opportunities`

**Query Parameters:**

| Param | Type | Description |
|-------|------|-------------|
| `status` | string | `draft`, `published`, `closed`, `completed` |
| `page` | int | Page number |
| `per_page` | int | Items per page |

**Example Response (200 OK):**

```json
{
  "data": [
    {
      "id": "550e8400-e29b-41d4-a716-446655440000",
      "title": "Instagram Collab for Brunch Event",
      "status": "published",
      "applications_count": 5,
      "pending_applications_count": 3,
      "accepted_applications_count": 1,
      "published_at": "2026-01-20T09:00:00Z",
      "availability_start": "2026-02-01",
      "availability_end": "2026-02-28",
      "categories": ["Food & Drink", "Lifestyle"],
      "offer_photo": "https://storage.kolabing.com/offers/brunch-event.jpg",
      "created_at": "2026-01-15T10:30:00Z",
      "updated_at": "2026-01-20T09:00:00Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 20,
    "total": 3,
    "from": 1,
    "to": 3
  }
}
```

---

## 2. Applications

### 2.1 Apply to Opportunity

**POST** `/opportunities/{id}/applications`

Submit an application to a published opportunity.

> **Business Rules:**
> - Cannot apply to own opportunity
> - One application per user per opportunity
> - Business users require active subscription

**Request Body:**

```json
{
  "message": "We'd love to collaborate! Our community has 5000+ engaged members interested in food and lifestyle events. We regularly host brunch meetups with 50-100 attendees.",
  "availability": "Weekends in February work best for us, specifically Feb 10-11 or Feb 17-18. We can also accommodate weekday evenings after 6pm."
}
```

**Validation Rules:**

| Field | Required | Rules |
|-------|----------|-------|
| `message` | Yes | Min 50, Max 2000 characters |
| `availability` | Yes | Min 20, Max 500 characters |

**Example Response (201 Created):**

```json
{
  "data": {
    "id": "660e8400-e29b-41d4-a716-446655440000",
    "collab_opportunity_id": "550e8400-e29b-41d4-a716-446655440000",
    "applicant_profile": {
      "id": "789e0123-e89b-12d3-a456-426614174000",
      "user_type": "community",
      "display_name": "Barcelona Runners Club",
      "avatar_url": "https://storage.kolabing.com/avatars/brc.jpg",
      "city": "Barcelona",
      "community_type": "Sports"
    },
    "message": "We'd love to collaborate!...",
    "availability": "Weekends in February...",
    "status": "pending",
    "created_at": "2026-01-25T10:30:00Z",
    "updated_at": "2026-01-25T10:30:00Z"
  },
  "message": "Application submitted successfully"
}
```

---

### 2.2 List Applications for Opportunity

**GET** `/opportunities/{id}/applications`

Only the opportunity creator can access.

**Query Parameters:**

| Param | Type | Description |
|-------|------|-------------|
| `status` | string | `pending`, `accepted`, `declined`, `withdrawn` |
| `page` | int | Page number |
| `per_page` | int | Items per page |

**Example Response (200 OK):**

```json
{
  "data": [
    {
      "id": "660e8400-e29b-41d4-a716-446655440000",
      "collab_opportunity_id": "550e8400-e29b-41d4-a716-446655440000",
      "applicant_profile": {
        "id": "789e0123-e89b-12d3-a456-426614174000",
        "user_type": "community",
        "display_name": "Barcelona Runners Club",
        "avatar_url": "https://storage.kolabing.com/avatars/brc.jpg",
        "city": "Barcelona",
        "community_type": "Sports",
        "followers_count": 5000
      },
      "message": "We'd love to collaborate!...",
      "availability": "Weekends in February...",
      "status": "pending",
      "created_at": "2026-01-22T14:20:00Z",
      "updated_at": "2026-01-22T14:20:00Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 20,
    "total": 5,
    "from": 1,
    "to": 5
  }
}
```

---

### 2.3 Get Application Details

**GET** `/applications/{id}`

Accessible by applicant or opportunity creator only.

**Example Response (200 OK):**

```json
{
  "data": {
    "id": "660e8400-e29b-41d4-a716-446655440000",
    "collab_opportunity": {
      "id": "550e8400-e29b-41d4-a716-446655440000",
      "title": "Instagram Collab for Brunch Event",
      "status": "published",
      "creator_profile": {
        "id": "123e4567-e89b-12d3-a456-426614174000",
        "user_type": "business",
        "display_name": "Cafe Central"
      }
    },
    "applicant_profile": {
      "id": "789e0123-e89b-12d3-a456-426614174000",
      "user_type": "community",
      "display_name": "Barcelona Runners Club",
      "avatar_url": "https://storage.kolabing.com/avatars/brc.jpg",
      "city": "Barcelona",
      "community_type": "Sports",
      "bio": "Community of 5000+ runners in Barcelona",
      "followers_count": 5000
    },
    "message": "We'd love to collaborate!...",
    "availability": "Weekends in February...",
    "status": "pending",
    "collaboration": null,
    "created_at": "2026-01-22T14:20:00Z",
    "updated_at": "2026-01-22T14:20:00Z"
  }
}
```

---

### 2.4 Accept Application

**POST** `/applications/{id}/accept`

Only opportunity creator can accept.

> **Note:** Business users require active subscription.

**Request Body:**

```json
{
  "scheduled_date": "2026-02-10",
  "contact_methods": {
    "whatsapp": "+34612345678",
    "email": "contact@cafecentralbcn.com",
    "instagram": "@cafecentralbcn"
  }
}
```

**Validation Rules:**

| Field | Required | Rules |
|-------|----------|-------|
| `scheduled_date` | Yes | ISO date, must be future |
| `contact_methods` | Yes | At least one method required |
| `contact_methods.whatsapp` | No | Phone with country code |
| `contact_methods.email` | No | Valid email |
| `contact_methods.instagram` | No | Instagram handle |

**Example Response (200 OK):**

```json
{
  "data": {
    "application": {
      "id": "660e8400-e29b-41d4-a716-446655440000",
      "status": "accepted"
    },
    "collaboration": {
      "id": "770e8400-e29b-41d4-a716-446655440000",
      "application_id": "660e8400-e29b-41d4-a716-446655440000",
      "collab_opportunity_id": "550e8400-e29b-41d4-a716-446655440000",
      "creator_profile": {
        "id": "123e4567-e89b-12d3-a456-426614174000",
        "user_type": "business",
        "display_name": "Cafe Central"
      },
      "applicant_profile": {
        "id": "789e0123-e89b-12d3-a456-426614174000",
        "user_type": "community",
        "display_name": "Barcelona Runners Club"
      },
      "status": "scheduled",
      "scheduled_date": "2026-02-10",
      "contact_methods": {
        "whatsapp": "+34612345678",
        "email": "contact@cafecentralbcn.com",
        "instagram": "@cafecentralbcn"
      },
      "completed_at": null,
      "created_at": "2026-01-25T10:30:00Z",
      "updated_at": "2026-01-25T10:30:00Z"
    }
  },
  "message": "Application accepted and collaboration created"
}
```

---

### 2.5 Decline Application

**POST** `/applications/{id}/decline`

Only opportunity creator can decline.

**Request Body:**

```json
{
  "reason": "Thank you for your interest. We're looking for communities with a focus on food and dining specifically."
}
```

**Example Response (200 OK):**

```json
{
  "data": {
    "id": "660e8400-e29b-41d4-a716-446655440000",
    "status": "declined"
  },
  "message": "Application declined"
}
```

---

### 2.6 Withdraw Application

**POST** `/applications/{id}/withdraw`

Only applicant can withdraw.

**Example Response (200 OK):**

```json
{
  "data": {
    "id": "660e8400-e29b-41d4-a716-446655440000",
    "status": "withdrawn"
  },
  "message": "Application withdrawn"
}
```

---

### 2.7 List My Applications

**GET** `/me/applications`

List applications I have sent.

**Query Parameters:**

| Param | Type | Description |
|-------|------|-------------|
| `status` | string | `pending`, `accepted`, `declined`, `withdrawn` |
| `page` | int | Page number |
| `per_page` | int | Items per page |

**Example Response (200 OK):**

```json
{
  "data": [
    {
      "id": "660e8400-e29b-41d4-a716-446655440000",
      "collab_opportunity": {
        "id": "550e8400-e29b-41d4-a716-446655440000",
        "title": "Instagram Collab for Brunch Event",
        "status": "published",
        "creator_profile": {
          "id": "123e4567-e89b-12d3-a456-426614174000",
          "user_type": "business",
          "display_name": "Cafe Central"
        },
        "offer_photo": "https://storage.kolabing.com/offers/brunch-event.jpg"
      },
      "message": "We'd love to collaborate!...",
      "availability": "Weekends in February...",
      "status": "pending",
      "created_at": "2026-01-22T14:20:00Z",
      "updated_at": "2026-01-22T14:20:00Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 20,
    "total": 2,
    "from": 1,
    "to": 2
  }
}
```

---

### 2.8 List Received Applications

**GET** `/me/received-applications`

List applications received on my opportunities.

**Query Parameters:**

| Param | Type | Description |
|-------|------|-------------|
| `status` | string | `pending`, `accepted`, `declined`, `withdrawn` |
| `opportunity_id` | uuid | Filter by specific opportunity |
| `page` | int | Page number |
| `per_page` | int | Items per page |

**Example Response (200 OK):**

```json
{
  "data": [
    {
      "id": "660e8400-e29b-41d4-a716-446655440000",
      "collab_opportunity": {
        "id": "550e8400-e29b-41d4-a716-446655440000",
        "title": "Instagram Collab for Brunch Event"
      },
      "applicant_profile": {
        "id": "789e0123-e89b-12d3-a456-426614174000",
        "user_type": "community",
        "display_name": "Barcelona Runners Club",
        "avatar_url": "https://storage.kolabing.com/avatars/brc.jpg",
        "city": "Barcelona",
        "community_type": "Sports"
      },
      "message": "We'd love to collaborate!...",
      "availability": "Weekends in February...",
      "status": "pending",
      "created_at": "2026-01-22T14:20:00Z",
      "updated_at": "2026-01-22T14:20:00Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 20,
    "total": 5,
    "from": 1,
    "to": 5
  }
}
```

---

## 3. Collaborations

### 3.1 List My Collaborations

**GET** `/collaborations`

**Query Parameters:**

| Param | Type | Description |
|-------|------|-------------|
| `status` | string | `scheduled`, `active`, `completed`, `cancelled` |
| `role` | string | `creator` or `applicant` |
| `page` | int | Page number |
| `per_page` | int | Items per page |

**Example Response (200 OK):**

```json
{
  "data": [
    {
      "id": "770e8400-e29b-41d4-a716-446655440000",
      "collab_opportunity": {
        "id": "550e8400-e29b-41d4-a716-446655440000",
        "title": "Instagram Collab for Brunch Event"
      },
      "creator_profile": {
        "id": "123e4567-e89b-12d3-a456-426614174000",
        "user_type": "business",
        "display_name": "Cafe Central",
        "avatar_url": "https://storage.kolabing.com/avatars/cafe-central.jpg",
        "city": "Barcelona"
      },
      "applicant_profile": {
        "id": "789e0123-e89b-12d3-a456-426614174000",
        "user_type": "community",
        "display_name": "Barcelona Runners Club",
        "avatar_url": "https://storage.kolabing.com/avatars/brc.jpg",
        "city": "Barcelona"
      },
      "business_profile": {
        "id": "aaa11111-e89b-12d3-a456-426614174000",
        "name": "Cafe Central",
        "business_type": "Restaurant"
      },
      "community_profile": {
        "id": "bbb22222-e89b-12d3-a456-426614174000",
        "name": "Barcelona Runners Club",
        "community_type": "Sports"
      },
      "status": "scheduled",
      "scheduled_date": "2026-02-10",
      "contact_methods": {
        "whatsapp": "+34612345678",
        "email": "contact@cafecentralbcn.com",
        "instagram": "@cafecentralbcn"
      },
      "completed_at": null,
      "my_role": "creator",
      "created_at": "2026-01-25T10:30:00Z",
      "updated_at": "2026-01-25T10:30:00Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 20,
    "total": 3,
    "from": 1,
    "to": 3
  }
}
```

---

### 3.2 Get Collaboration Details

**GET** `/collaborations/{id}`

Only participants can access.

**Example Response (200 OK):**

```json
{
  "data": {
    "id": "770e8400-e29b-41d4-a716-446655440000",
    "application": {
      "id": "660e8400-e29b-41d4-a716-446655440000",
      "message": "We'd love to collaborate!...",
      "availability": "Weekends in February..."
    },
    "collab_opportunity": {
      "id": "550e8400-e29b-41d4-a716-446655440000",
      "title": "Instagram Collab for Brunch Event",
      "description": "Looking for lifestyle influencers...",
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
        "attendee_count": 50
      },
      "categories": ["Food & Drink", "Lifestyle"],
      "venue_mode": "business_venue",
      "address": "Carrer de Pelai, 62, Barcelona"
    },
    "creator_profile": {
      "id": "123e4567-e89b-12d3-a456-426614174000",
      "user_type": "business",
      "display_name": "Cafe Central",
      "avatar_url": "https://storage.kolabing.com/avatars/cafe-central.jpg",
      "city": "Barcelona",
      "business_type": "Restaurant",
      "bio": "Modern cafe in the heart of Barcelona"
    },
    "applicant_profile": {
      "id": "789e0123-e89b-12d3-a456-426614174000",
      "user_type": "community",
      "display_name": "Barcelona Runners Club",
      "avatar_url": "https://storage.kolabing.com/avatars/brc.jpg",
      "city": "Barcelona",
      "community_type": "Sports",
      "bio": "Community of 5000+ runners in Barcelona",
      "followers_count": 5000
    },
    "business_profile": {
      "id": "aaa11111-e89b-12d3-a456-426614174000",
      "name": "Cafe Central",
      "business_type": "Restaurant",
      "instagram": "@cafecentralbcn"
    },
    "community_profile": {
      "id": "bbb22222-e89b-12d3-a456-426614174000",
      "name": "Barcelona Runners Club",
      "community_type": "Sports",
      "instagram": "@barcelonarunners"
    },
    "status": "scheduled",
    "scheduled_date": "2026-02-10",
    "contact_methods": {
      "whatsapp": "+34612345678",
      "email": "contact@cafecentralbcn.com",
      "instagram": "@cafecentralbcn"
    },
    "completed_at": null,
    "my_role": "creator",
    "created_at": "2026-01-25T10:30:00Z",
    "updated_at": "2026-01-25T10:30:00Z"
  }
}
```

---

### 3.3 Activate Collaboration

**POST** `/collaborations/{id}/activate`

Mark scheduled collaboration as active.

**Example Response (200 OK):**

```json
{
  "data": {
    "id": "770e8400-e29b-41d4-a716-446655440000",
    "status": "active"
  },
  "message": "Collaboration activated"
}
```

---

### 3.4 Complete Collaboration

**POST** `/collaborations/{id}/complete`

Mark collaboration as completed.

**Request Body:**

```json
{
  "feedback": "Great collaboration! Looking forward to working together again."
}
```

**Example Response (200 OK):**

```json
{
  "data": {
    "id": "770e8400-e29b-41d4-a716-446655440000",
    "status": "completed",
    "completed_at": "2026-02-15T18:30:00Z"
  },
  "message": "Collaboration completed successfully"
}
```

---

### 3.5 Cancel Collaboration

**POST** `/collaborations/{id}/cancel`

**Request Body:**

```json
{
  "reason": "Unfortunately we need to reschedule due to unforeseen circumstances."
}
```

**Validation Rules:**

| Field | Required | Rules |
|-------|----------|-------|
| `reason` | Yes | Min 20, Max 500 characters |

**Example Response (200 OK):**

```json
{
  "data": {
    "id": "770e8400-e29b-41d4-a716-446655440000",
    "status": "cancelled"
  },
  "message": "Collaboration cancelled"
}
```

---

## Error Responses

### Validation Error (422)

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "title": ["The title field is required."],
    "availability_start": ["The availability start must be a date after today."]
  }
}
```

### Unauthorized (401)

```json
{
  "message": "Unauthenticated."
}
```

### Subscription Required (403)

```json
{
  "message": "Active subscription required to publish opportunities.",
  "error_code": "subscription_required",
  "upgrade_url": "/api/v1/me/subscription"
}
```

### Profile Incomplete (403)

```json
{
  "message": "Please complete your profile before creating opportunities.",
  "error_code": "profile_incomplete",
  "missing_fields": ["bio", "avatar_url"]
}
```

### Already Applied (409)

```json
{
  "message": "You have already applied to this opportunity.",
  "error_code": "duplicate_application"
}
```

### Invalid Status Transition (409)

```json
{
  "message": "Cannot accept an application that has already been processed.",
  "error_code": "invalid_status_transition",
  "current_status": "declined"
}
```

### Not Found (404)

```json
{
  "message": "Opportunity not found."
}
```

---

## Status Flows

### Opportunity Status Flow

```
draft → published → closed → completed
```

### Application Status Flow

```
pending → accepted
        → declined
        → withdrawn
```

### Collaboration Status Flow

```
scheduled → active → completed
                   → cancelled
          → cancelled
```

---

## Rate Limits

| Endpoint Type | Limit |
|---------------|-------|
| General | 60 requests/minute |
| Browse (GET /opportunities) | 120 requests/minute |
| Application submissions | 10 requests/minute |

Rate limit headers included in responses:

```
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 45
X-RateLimit-Reset: 1643120400
```

---

## Mobile Implementation Notes

1. **Token Storage:** Store Bearer token securely (iOS Keychain / Android Keystore)

2. **Pagination:** Always handle pagination for list endpoints

3. **Offline Support:** Cache opportunity list for offline browsing

4. **Error Handling:** Check `error_code` field for business logic errors

5. **Subscription Gate:** Handle `subscription_required` by showing upgrade prompt

6. **Profile Gate:** Handle `profile_incomplete` by redirecting to profile setup

7. **Real-time Updates:** Poll `/me/received-applications` every 30-60 seconds

8. **Image Uploads:** Upload images separately, then include URL in request

9. **Date Format:** Always use ISO 8601 format

10. **Deep Links:** Support deep links to specific opportunities/collaborations
