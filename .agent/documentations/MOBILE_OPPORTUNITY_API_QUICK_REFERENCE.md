# Kolabing API Quick Reference - Opportunities

**Base URL:** `/api/v1`
**Auth:** `Authorization: Bearer {token}`

## Endpoints Summary

| Method | Endpoint | Description | Auth |
|--------|----------|-------------|------|
| GET | `/opportunities` | Browse published opportunities | Required |
| GET | `/me/opportunities` | Get my opportunities (all statuses) | Required |
| GET | `/opportunities/{id}` | Get single opportunity | Required |
| POST | `/opportunities` | Create draft opportunity | Required |
| PUT | `/opportunities/{id}` | Update opportunity | Required (creator) |
| POST | `/opportunities/{id}/publish` | Publish draft | Required (creator + subscription for business) |
| POST | `/opportunities/{id}/close` | Close published | Required (creator) |
| DELETE | `/opportunities/{id}` | Delete draft (no apps) | Required (creator) |

## Status Flow

```
draft → published → closed → completed
```

## Quick Create Example

```json
POST /api/v1/opportunities

{
  "title": "Yoga Studio Event",
  "description": "Partner with wellness communities...",
  "business_offer": {
    "venue": true,
    "food_drink": true,
    "discount": { "enabled": true, "percentage": 20 }
  },
  "community_deliverables": {
    "instagram_post": true,
    "instagram_story": true,
    "attendee_count": 30
  },
  "categories": ["Wellness", "Sports"],
  "availability_mode": "recurring",
  "availability_start": "2026-02-15",
  "availability_end": "2026-08-15",
  "venue_mode": "business_venue",
  "address": "Carrer del Consell de Cent, 334, Barcelona",
  "preferred_city": "Barcelona",
  "offer_photo": "https://example.com/photo.jpg"
}
```

## Enum Values

### Opportunity Status
- `draft` - Not visible to others
- `published` - Live and browsable
- `closed` - No new applications
- `completed` - Finished

### Availability Mode
- `one_time` - Single event
- `recurring` - Repeated events
- `flexible` - Open to discussion

### Venue Mode
- `business_venue` - At business location (address required)
- `community_venue` - At community location (address required)
- `no_venue` - Online/no venue (address optional)

## Browse with Filters

```http
GET /api/v1/opportunities?creator_type=business&city=Barcelona&categories[]=Wellness&per_page=10&page=1
```

**Available Filters:**
- `creator_type`: business, community
- `categories[]`: array of category strings
- `city`: city name
- `venue_mode`: business_venue, community_venue, no_venue
- `availability_mode`: one_time, recurring, flexible
- `availability_from`: Y-m-d
- `availability_to`: Y-m-d
- `search`: search in title/description
- `per_page`: 1-100 (default 20)
- `page`: page number

## Response Structure

```json
{
  "success": true,
  "data": {
    "id": "uuid",
    "creator_profile": { "id": "uuid", "user_type": "business", "business_name": "..." },
    "title": "string",
    "description": "string",
    "status": "draft|published|closed|completed",
    "business_offer": { "venue": true, "food_drink": true, ... },
    "community_deliverables": { "instagram_post": true, ... },
    "categories": ["Wellness", "Sports"],
    "availability_mode": "one_time|recurring|flexible",
    "availability_start": "2026-02-15",
    "availability_end": "2026-08-15",
    "venue_mode": "business_venue|community_venue|no_venue",
    "address": "string or null",
    "preferred_city": "string",
    "offer_photo": "url or null",
    "published_at": "ISO8601 or null",
    "applications_count": 0,
    "is_own": false,
    "has_applied": false,
    "my_application": null,
    "created_at": "ISO8601",
    "updated_at": "ISO8601"
  }
}
```

## Business Rules Checklist

### Can Create?
- Any authenticated user

### Can Publish?
- Creator only
- Must be draft status
- Business users need active subscription
- Community users can publish for free

### Can Update?
- Creator only
- Must be draft or published status

### Can Delete?
- Creator only
- Must be draft status
- Must have zero applications

### Can Close?
- Creator only
- Must be published status

## Error Status Codes

| Code | Meaning |
|------|---------|
| 200 | Success |
| 201 | Created |
| 400 | Business logic error |
| 401 | Not authenticated |
| 403 | Not authorized |
| 404 | Not found |
| 422 | Validation failed |
| 500 | Server error |

## Common Validation Rules

- `title`: required, max 255
- `description`: required, max 5000
- `categories`: required array, 1-5 items
- `availability_start`: required, after today
- `availability_end`: required, after start date
- `address`: required unless venue_mode=no_venue
- `preferred_city`: required, max 100
- `offer_photo`: optional, must be valid URL

## JSONB Fields

### business_offer Example
```json
{
  "venue": true,
  "food_drink": true,
  "discount": {
    "enabled": true,
    "percentage": 20
  },
  "products": ["Product A", "Product B"],
  "other": "Free text description"
}
```

### community_deliverables Example
```json
{
  "instagram_post": true,
  "instagram_story": true,
  "tiktok_video": false,
  "event_mention": true,
  "attendee_count": 50,
  "other": "Newsletter feature"
}
```

## Mobile Implementation Tips

### Before Publishing (Business Users)
```javascript
if (user.user_type === 'business' && !user.has_active_subscription) {
  showSubscriptionRequiredDialog();
  return;
}
```

### Optimistic UI Update
```javascript
// Update UI immediately, rollback on error
updateLocalState({ status: 'published' });
try {
  await api.post(`/opportunities/${id}/publish`);
} catch (error) {
  updateLocalState({ status: 'draft' });
  showError(error.message);
}
```

### Pagination
```javascript
// Infinite scroll example
const loadMore = async () => {
  const response = await api.get('/opportunities', {
    params: { page: currentPage + 1, per_page: 20 }
  });
  appendToList(response.data.data);
  setCurrentPage(response.data.meta.current_page);
  setHasMore(response.data.meta.current_page < response.data.meta.last_page);
};
```

### Search Debouncing
```javascript
// Debounce search input
const debouncedSearch = debounce((query) => {
  api.get('/opportunities', { params: { search: query } });
}, 500);
```

## Categories Reference

Common category strings:
- Food & Drink
- Sports
- Wellness
- Culture
- Technology
- Education
- Entertainment
- Fashion
- Music
- Art
- Health
- Fitness
- Community
- Charity
- Sustainability

## API Version
**Version:** 1.0
**Last Updated:** 2026-01-26
