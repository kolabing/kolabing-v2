# Mobile Implementation Guide: Search Opportunities API

## Overview

The Search Opportunities endpoint allows mobile users to find collaboration opportunities by searching across:
- Opportunity title
- Opportunity description
- Creator name (business or community profile name)

## API Endpoint

```
GET /api/v1/opportunities
```

### Authentication
Requires Bearer token in Authorization header:
```
Authorization: Bearer {token}
```

## Query Parameters

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `search` | string | No | - | Search term (searches title, description, and creator name) |
| `creator_type` | string | No | Opposite of user's type | Filter: `business` or `community` |
| `categories` | string[] | No | - | Filter by categories array |
| `city` | string | No | - | Filter by preferred city |
| `venue_mode` | string | No | - | Filter by venue mode |
| `availability_mode` | string | No | - | Filter by availability mode |
| `availability_from` | date | No | - | Filter: availability starts on/after |
| `availability_to` | date | No | - | Filter: availability ends on/before |
| `per_page` | int | No | 20 | Items per page (1-100) |
| `page` | int | No | 1 | Page number |

### Default Behavior
- **Business users** see community-created opportunities by default
- **Community users** see business-created opportunities by default
- Use `creator_type` parameter to override this behavior

## Search Functionality

### Search Fields
The `search` parameter queries:
1. `title` - Opportunity title
2. `description` - Opportunity description
3. `creator_name` - Business name or Community name

### Search Behavior
- **Case-insensitive** - "Yoga", "yoga", "YOGA" all match
- **Partial matching** - "run" matches "Running Club", "runners"
- **OR logic** - Matches if ANY field contains the search term

## Example Requests

### Basic Search
```bash
GET /api/v1/opportunities?search=yoga
```

### Search with Filters
```bash
GET /api/v1/opportunities?search=fitness&city=Barcelona&creator_type=community
```

### Paginated Search
```bash
GET /api/v1/opportunities?search=coffee&per_page=10&page=2
```

## Response Format

### Success Response (200 OK)

```json
{
  "success": true,
  "data": {
    "data": [
      {
        "id": "9e2f3c4d-5678-90ab-cdef-123456789abc",
        "title": "Yoga Workshop Partnership",
        "description": "Looking for yoga instructors to partner with our wellness center",
        "status": "published",
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
        "categories": ["Wellness", "Sports"],
        "availability_mode": "flexible",
        "availability_start": "2026-02-01",
        "availability_end": "2026-03-31",
        "venue_mode": "on_site",
        "preferred_city": "Barcelona",
        "creator_profile": {
          "id": "abc12345-6789-0def-ghij-klmnopqrstuv",
          "name": "Wellness Center BCN",
          "profile_photo": "https://example.com/photo.jpg",
          "user_type": "business"
        },
        "is_own": false,
        "created_at": "2026-01-15T10:30:00.000000Z",
        "updated_at": "2026-01-20T15:45:00.000000Z"
      }
    ]
  },
  "meta": {
    "current_page": 1,
    "last_page": 3,
    "per_page": 20,
    "total": 45
  }
}
```

### Empty Results (200 OK)

```json
{
  "success": true,
  "data": {
    "data": []
  },
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 20,
    "total": 0
  }
}
```

### Error Response (401 Unauthorized)

```json
{
  "message": "Unauthenticated."
}
```

## Mobile Implementation Notes

### UI/UX Recommendations

1. **Search Input**
   - Add debounce (300-500ms) before API call
   - Show loading indicator while searching
   - Display "No results found" for empty responses

2. **Filter Chips**
   - "All" / "Business" / "Community" for `creator_type`
   - Category chips for filtering
   - City selector for location filter

3. **Pull-to-Refresh**
   - Re-execute search with current parameters

4. **Infinite Scroll**
   - Increment `page` parameter
   - Stop when `current_page >= last_page`

### Code Example (React Native / TypeScript)

```typescript
interface SearchParams {
  search?: string;
  creator_type?: 'business' | 'community';
  categories?: string[];
  city?: string;
  per_page?: number;
  page?: number;
}

const searchOpportunities = async (params: SearchParams) => {
  const queryString = new URLSearchParams();

  if (params.search) queryString.append('search', params.search);
  if (params.creator_type) queryString.append('creator_type', params.creator_type);
  if (params.city) queryString.append('city', params.city);
  if (params.per_page) queryString.append('per_page', String(params.per_page));
  if (params.page) queryString.append('page', String(params.page));
  if (params.categories) {
    params.categories.forEach(cat => queryString.append('categories[]', cat));
  }

  const response = await fetch(
    `${API_BASE_URL}/api/v1/opportunities?${queryString}`,
    {
      headers: {
        'Authorization': `Bearer ${token}`,
        'Accept': 'application/json',
      },
    }
  );

  return response.json();
};
```

### Code Example (Swift / iOS)

```swift
struct OpportunitySearchParams {
    var search: String?
    var creatorType: String?
    var categories: [String]?
    var city: String?
    var perPage: Int = 20
    var page: Int = 1
}

func searchOpportunities(params: OpportunitySearchParams) async throws -> OpportunityResponse {
    var components = URLComponents(string: "\(baseURL)/api/v1/opportunities")!
    var queryItems: [URLQueryItem] = []

    if let search = params.search {
        queryItems.append(URLQueryItem(name: "search", value: search))
    }
    if let creatorType = params.creatorType {
        queryItems.append(URLQueryItem(name: "creator_type", value: creatorType))
    }
    if let city = params.city {
        queryItems.append(URLQueryItem(name: "city", value: city))
    }
    queryItems.append(URLQueryItem(name: "per_page", value: String(params.perPage)))
    queryItems.append(URLQueryItem(name: "page", value: String(params.page)))

    if let categories = params.categories {
        for category in categories {
            queryItems.append(URLQueryItem(name: "categories[]", value: category))
        }
    }

    components.queryItems = queryItems

    var request = URLRequest(url: components.url!)
    request.setValue("Bearer \(token)", forHTTPHeaderField: "Authorization")
    request.setValue("application/json", forHTTPHeaderField: "Accept")

    let (data, _) = try await URLSession.shared.data(for: request)
    return try JSONDecoder().decode(OpportunityResponse.self, from: data)
}
```

## Search Examples by Use Case

### Business User Searching for Communities

```bash
# Find yoga communities
GET /api/v1/opportunities?search=yoga

# Find running clubs in Madrid
GET /api/v1/opportunities?search=running&city=Madrid

# Find fitness-related opportunities
GET /api/v1/opportunities?search=fitness&categories[]=Sports&categories[]=Wellness
```

### Community User Searching for Businesses

```bash
# Find coffee shops
GET /api/v1/opportunities?search=coffee

# Find restaurants in Barcelona
GET /api/v1/opportunities?search=restaurant&city=Barcelona

# Find wellness businesses
GET /api/v1/opportunities?search=spa&categories[]=Wellness
```

## Testing the API

### cURL Example

```bash
curl -X GET "https://api.kolabing.com/api/v1/opportunities?search=yoga" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json"
```

### Expected Results

| Search Term | Matches |
|-------------|---------|
| "yoga" | Titles/descriptions/creator names containing "yoga" |
| "Barcelona" | Creator names containing "Barcelona" (e.g., "Barcelona Runners Club") |
| "coffee" | Titles like "Coffee Tasting Event" or businesses like "Organic Coffee House" |

## Changelog

- **2026-01-29**: Initial implementation
  - Added search across title, description, and creator profile name
  - Case-insensitive matching
  - OR logic for search terms
