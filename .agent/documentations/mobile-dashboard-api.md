# Mobile Implementation Guide: Dashboard API

## Overview

The Dashboard endpoint provides a summary of key statistics for the authenticated user. The response structure differs based on user type (business vs community).

## Authentication

All endpoints require Bearer token authentication:
```
Authorization: Bearer {token}
```

---

## Endpoint

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/v1/me/dashboard` | GET | Get dashboard stats |

---

## GET /api/v1/me/dashboard

Returns dashboard statistics tailored to the authenticated user's type.

### Business User Response (200 OK)

```json
{
  "success": true,
  "data": {
    "opportunities": {
      "total": 8,
      "published": 5,
      "draft": 2,
      "closed": 1
    },
    "applications_received": {
      "total": 15,
      "pending": 6,
      "accepted": 7,
      "declined": 2
    },
    "collaborations": {
      "total": 7,
      "active": 2,
      "upcoming": 3,
      "completed": 2
    },
    "upcoming_collaborations": [
      {
        "id": "collab-uuid-1",
        "status": "scheduled",
        "scheduled_date": "2026-02-10",
        "opportunity": {
          "id": "opp-uuid-1",
          "title": "Yoga Session at Wellness Center",
          "categories": ["Wellness", "Sports"]
        },
        "partner": {
          "id": "profile-uuid-1",
          "name": "Barcelona Yogis",
          "user_type": "community"
        }
      },
      {
        "id": "collab-uuid-2",
        "status": "active",
        "scheduled_date": "2026-02-05",
        "opportunity": {
          "id": "opp-uuid-2",
          "title": "Weekend Brunch Event",
          "categories": ["Food & Drink"]
        },
        "partner": {
          "id": "profile-uuid-2",
          "name": "Foodies BCN",
          "user_type": "community"
        }
      }
    ]
  }
}
```

### Community User Response (200 OK)

```json
{
  "success": true,
  "data": {
    "applications_sent": {
      "total": 12,
      "pending": 4,
      "accepted": 5,
      "declined": 2,
      "withdrawn": 1
    },
    "collaborations": {
      "total": 5,
      "active": 1,
      "upcoming": 2,
      "completed": 2
    },
    "upcoming_collaborations": [
      {
        "id": "collab-uuid-3",
        "status": "scheduled",
        "scheduled_date": "2026-02-15",
        "opportunity": {
          "id": "opp-uuid-3",
          "title": "Coffee Tasting Workshop",
          "categories": ["Food & Drink"]
        },
        "partner": {
          "id": "profile-uuid-3",
          "name": "Cafe Barcelona",
          "user_type": "business"
        }
      }
    ]
  }
}
```

### Error Responses

**Unauthorized (401)**
```json
{
  "message": "Unauthenticated."
}
```

---

## Response Field Descriptions

### Business: `opportunities`
| Field | Type | Description |
|-------|------|-------------|
| `total` | int | Total opportunities created |
| `published` | int | Currently published (accepting applications) |
| `draft` | int | Draft opportunities (not yet published) |
| `closed` | int | Closed opportunities |

### Business: `applications_received`
| Field | Type | Description |
|-------|------|-------------|
| `total` | int | Total applications received across all opportunities |
| `pending` | int | Applications awaiting review |
| `accepted` | int | Accepted applications |
| `declined` | int | Declined applications |

### Community: `applications_sent`
| Field | Type | Description |
|-------|------|-------------|
| `total` | int | Total applications sent |
| `pending` | int | Applications awaiting response |
| `accepted` | int | Accepted applications |
| `declined` | int | Declined applications |
| `withdrawn` | int | Withdrawn applications |

### `collaborations` (both user types)
| Field | Type | Description |
|-------|------|-------------|
| `total` | int | Total collaborations |
| `active` | int | Currently active collaborations |
| `upcoming` | int | Scheduled collaborations with future date |
| `completed` | int | Completed collaborations |

### `upcoming_collaborations` (both user types)
| Field | Type | Description |
|-------|------|-------------|
| `id` | string (UUID) | Collaboration ID |
| `status` | string | `scheduled` or `active` |
| `scheduled_date` | string (date) or null | Date in `YYYY-MM-DD` format |
| `opportunity.id` | string (UUID) | Opportunity ID |
| `opportunity.title` | string | Opportunity title |
| `opportunity.categories` | string[] | Category list |
| `partner.id` | string (UUID) | Partner profile ID |
| `partner.name` | string or null | Partner name |
| `partner.user_type` | string | `business` or `community` |

---

## Notes

- **User type detection is automatic.** The endpoint returns different response structures based on the authenticated user's type.
- **`upcoming_collaborations`** returns max 5 results, ordered by `scheduled_date` ascending.
- **`upcoming`** count in `collaborations` only includes scheduled collaborations with a future date.
- **Active collaborations** are included in `upcoming_collaborations` list (they may still be ongoing).

---

## Mobile Implementation Examples

### TypeScript / React Native

```typescript
// Types
interface OpportunityStats {
  total: number;
  published: number;
  draft: number;
  closed: number;
}

interface ReceivedApplicationStats {
  total: number;
  pending: number;
  accepted: number;
  declined: number;
}

interface SentApplicationStats {
  total: number;
  pending: number;
  accepted: number;
  declined: number;
  withdrawn: number;
}

interface CollaborationStats {
  total: number;
  active: number;
  upcoming: number;
  completed: number;
}

interface UpcomingCollaboration {
  id: string;
  status: 'scheduled' | 'active';
  scheduled_date: string | null;
  opportunity: {
    id: string;
    title: string;
    categories: string[];
  };
  partner: {
    id: string;
    name: string | null;
    user_type: 'business' | 'community';
  };
}

interface BusinessDashboard {
  opportunities: OpportunityStats;
  applications_received: ReceivedApplicationStats;
  collaborations: CollaborationStats;
  upcoming_collaborations: UpcomingCollaboration[];
}

interface CommunityDashboard {
  applications_sent: SentApplicationStats;
  collaborations: CollaborationStats;
  upcoming_collaborations: UpcomingCollaboration[];
}

type DashboardData = BusinessDashboard | CommunityDashboard;

// API Function
const getDashboard = async (): Promise<{ success: boolean; data: DashboardData }> => {
  const response = await fetch(
    `${API_BASE_URL}/api/v1/me/dashboard`,
    {
      headers: {
        'Authorization': `Bearer ${token}`,
        'Accept': 'application/json',
      },
    }
  );

  return response.json();
};

// Type guard
const isBusinessDashboard = (data: DashboardData): data is BusinessDashboard => {
  return 'opportunities' in data;
};
```

### Swift / iOS

```swift
// Models
struct OpportunityStats: Codable {
    let total: Int
    let published: Int
    let draft: Int
    let closed: Int
}

struct ReceivedApplicationStats: Codable {
    let total: Int
    let pending: Int
    let accepted: Int
    let declined: Int
}

struct SentApplicationStats: Codable {
    let total: Int
    let pending: Int
    let accepted: Int
    let declined: Int
    let withdrawn: Int
}

struct CollaborationStats: Codable {
    let total: Int
    let active: Int
    let upcoming: Int
    let completed: Int
}

struct UpcomingCollaboration: Codable {
    let id: String
    let status: String
    let scheduledDate: String?
    let opportunity: OpportunitySummary
    let partner: PartnerSummary

    enum CodingKeys: String, CodingKey {
        case id, status, opportunity, partner
        case scheduledDate = "scheduled_date"
    }
}

struct OpportunitySummary: Codable {
    let id: String
    let title: String
    let categories: [String]
}

struct PartnerSummary: Codable {
    let id: String
    let name: String?
    let userType: String

    enum CodingKeys: String, CodingKey {
        case id, name
        case userType = "user_type"
    }
}

struct BusinessDashboard: Codable {
    let opportunities: OpportunityStats
    let applicationsReceived: ReceivedApplicationStats
    let collaborations: CollaborationStats
    let upcomingCollaborations: [UpcomingCollaboration]

    enum CodingKeys: String, CodingKey {
        case opportunities, collaborations
        case applicationsReceived = "applications_received"
        case upcomingCollaborations = "upcoming_collaborations"
    }
}

struct CommunityDashboard: Codable {
    let applicationsSent: SentApplicationStats
    let collaborations: CollaborationStats
    let upcomingCollaborations: [UpcomingCollaboration]

    enum CodingKeys: String, CodingKey {
        case collaborations
        case applicationsSent = "applications_sent"
        case upcomingCollaborations = "upcoming_collaborations"
    }
}

// Dashboard Service
class DashboardService {
    private let baseURL: String
    private let token: String

    init(baseURL: String, token: String) {
        self.baseURL = baseURL
        self.token = token
    }

    func getBusinessDashboard() async throws -> BusinessDashboard {
        let url = URL(string: "\(baseURL)/api/v1/me/dashboard")!
        var request = URLRequest(url: url)
        request.setValue("Bearer \(token)", forHTTPHeaderField: "Authorization")
        request.setValue("application/json", forHTTPHeaderField: "Accept")

        let (data, _) = try await URLSession.shared.data(for: request)
        let response = try JSONDecoder().decode(APIResponse<BusinessDashboard>.self, from: data)

        guard response.success, let dashboard = response.data else {
            throw APIError.requestFailed(response.message ?? "Unknown error")
        }

        return dashboard
    }

    func getCommunityDashboard() async throws -> CommunityDashboard {
        let url = URL(string: "\(baseURL)/api/v1/me/dashboard")!
        var request = URLRequest(url: url)
        request.setValue("Bearer \(token)", forHTTPHeaderField: "Authorization")
        request.setValue("application/json", forHTTPHeaderField: "Accept")

        let (data, _) = try await URLSession.shared.data(for: request)
        let response = try JSONDecoder().decode(APIResponse<CommunityDashboard>.self, from: data)

        guard response.success, let dashboard = response.data else {
            throw APIError.requestFailed(response.message ?? "Unknown error")
        }

        return dashboard
    }
}
```

---

## UI/UX Recommendations

### Business Dashboard
1. **Stats Cards Row:** Show key numbers (published opportunities, pending applications, upcoming collabs)
2. **Quick Actions:** "Create Opportunity", "Review Applications"
3. **Upcoming Collaborations List:** Show next 5 with partner name, date, and opportunity title

### Community Dashboard
1. **Stats Cards Row:** Show key numbers (pending applications, upcoming collabs, completed collabs)
2. **Quick Actions:** "Browse Opportunities", "My Applications"
3. **Upcoming Collaborations List:** Show next 5 with partner name, date, and opportunity title

### Badge Indicators
- Use the `pending` count from `applications_received` (business) or `applications_sent` (community) to show notification badges
- Use `upcoming` count from `collaborations` to highlight upcoming events

---

## Changelog

- **2026-01-29**: Initial implementation
  - Business dashboard with opportunity, application, and collaboration stats
  - Community dashboard with application and collaboration stats
  - Upcoming collaborations with partner info
  - Max 5 upcoming collaborations returned
