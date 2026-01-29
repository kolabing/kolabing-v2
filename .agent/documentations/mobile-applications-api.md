# Mobile Implementation Guide: Applications API

## Overview

The Applications API allows users to apply to collaboration opportunities, manage their applications, and handle received applications. This is the core workflow for connecting businesses with communities.

## Authentication

All endpoints require Bearer token authentication:
```
Authorization: Bearer {token}
```

---

## Endpoints Summary

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/v1/opportunities/{id}/applications` | POST | Apply to an opportunity |
| `/api/v1/me/applications` | GET | List my sent applications |
| `/api/v1/me/received-applications` | GET | List applications I received |
| `/api/v1/applications/{id}` | GET | Get application details |
| `/api/v1/applications/{id}/accept` | POST | Accept an application |
| `/api/v1/applications/{id}/decline` | POST | Decline an application |
| `/api/v1/applications/{id}/withdraw` | POST | Withdraw my application |

---

## 1. Apply to Opportunity (APPLY Button)

Creates a new application for a published opportunity.

### Request

```
POST /api/v1/opportunities/{opportunity_id}/applications
```

### Request Body

| Field | Type | Required | Validation | Description |
|-------|------|----------|------------|-------------|
| `message` | string | Yes | min: 50, max: 2000 | Application message explaining why you want to collaborate |
| `availability` | string | Yes | min: 20, max: 500 | Your availability for this collaboration |

### Example Request

```json
{
  "message": "Hi! I run a yoga community called 'Barcelona Yogis' with over 500 active members. We host weekly sessions in various venues across Barcelona. I believe a partnership with your wellness center would be mutually beneficial. We can bring engaged attendees who are passionate about health and wellness, and promote your venue through our social media channels with over 10K followers.",
  "availability": "We are available on weekends throughout March and April. Our typical sessions run from 10:00 AM to 12:00 PM."
}
```

### Success Response (201 Created)

```json
{
  "success": true,
  "message": "Application submitted successfully",
  "data": {
    "id": "a1b2c3d4-5678-90ab-cdef-123456789abc",
    "status": "pending",
    "message": "Hi! I run a yoga community called 'Barcelona Yogis'...",
    "availability": "We are available on weekends throughout March and April...",
    "applicant_profile": {
      "id": "p1234567-89ab-cdef-0123-456789abcdef",
      "name": "Barcelona Yogis",
      "profile_photo": "https://example.com/photo.jpg",
      "user_type": "community",
      "community_type": "Sports & Fitness"
    },
    "collab_opportunity": {
      "id": "o9876543-21ab-cdef-0123-456789abcdef",
      "title": "Wellness Workshop Partnership",
      "status": "published"
    },
    "created_at": "2026-01-29T16:30:00.000000Z",
    "updated_at": "2026-01-29T16:30:00.000000Z"
  }
}
```

### Error Responses

**Validation Error (422)**
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "message": ["The message field must be at least 50 characters."],
    "availability": ["The availability field is required."]
  }
}
```

**Already Applied (400)**
```json
{
  "success": false,
  "message": "You have already applied to this opportunity"
}
```

**Not Authorized (403)**
```json
{
  "success": false,
  "message": "You are not authorized to apply to this opportunity"
}
```

**Opportunity Not Published (400)**
```json
{
  "success": false,
  "message": "You can only apply to published opportunities"
}
```

---

## 2. List My Applications

Returns applications sent by the authenticated user.

### Request

```
GET /api/v1/me/applications
```

### Query Parameters

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `status` | string | No | - | Filter by status: `pending`, `accepted`, `declined`, `withdrawn` |
| `per_page` | int | No | 20 | Items per page (max: 100) |
| `page` | int | No | 1 | Page number |

### Example Request

```
GET /api/v1/me/applications?status=pending&per_page=10
```

### Success Response (200 OK)

```json
{
  "success": true,
  "data": {
    "data": [
      {
        "id": "a1b2c3d4-5678-90ab-cdef-123456789abc",
        "status": "pending",
        "message": "Hi! I run a yoga community...",
        "availability": "Weekends in March...",
        "applicant_profile": {
          "id": "p1234567-89ab-cdef-0123-456789abcdef",
          "name": "Barcelona Yogis",
          "user_type": "community"
        },
        "collab_opportunity": {
          "id": "o9876543-21ab-cdef-0123-456789abcdef",
          "title": "Wellness Workshop Partnership",
          "status": "published",
          "creator_profile": {
            "id": "c1234567-89ab-cdef-0123-456789abcdef",
            "name": "Wellness Center BCN",
            "user_type": "business"
          }
        },
        "created_at": "2026-01-29T16:30:00.000000Z"
      }
    ]
  },
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 10,
    "total": 1
  }
}
```

---

## 3. List Received Applications

Returns applications received on opportunities created by the authenticated user.

### Request

```
GET /api/v1/me/received-applications
```

### Query Parameters

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `status` | string | No | - | Filter by status: `pending`, `accepted`, `declined`, `withdrawn` |
| `opportunity_id` | string | No | - | Filter by specific opportunity |
| `per_page` | int | No | 20 | Items per page (max: 100) |
| `page` | int | No | 1 | Page number |

### Example Request

```
GET /api/v1/me/received-applications?status=pending
```

### Success Response (200 OK)

```json
{
  "success": true,
  "data": {
    "data": [
      {
        "id": "a1b2c3d4-5678-90ab-cdef-123456789abc",
        "status": "pending",
        "message": "Hi! I run a yoga community...",
        "availability": "Weekends in March...",
        "applicant_profile": {
          "id": "p1234567-89ab-cdef-0123-456789abcdef",
          "name": "Barcelona Yogis",
          "profile_photo": "https://example.com/photo.jpg",
          "user_type": "community",
          "instagram": "@barcelona_yogis",
          "community_type": "Sports & Fitness"
        },
        "collab_opportunity": {
          "id": "o9876543-21ab-cdef-0123-456789abcdef",
          "title": "Wellness Workshop Partnership"
        },
        "created_at": "2026-01-29T16:30:00.000000Z"
      }
    ]
  },
  "meta": {
    "current_page": 1,
    "last_page": 1,
    "per_page": 20,
    "total": 1
  }
}
```

---

## 4. Get Application Details

### Request

```
GET /api/v1/applications/{application_id}
```

### Success Response (200 OK)

```json
{
  "success": true,
  "data": {
    "id": "a1b2c3d4-5678-90ab-cdef-123456789abc",
    "status": "pending",
    "message": "Hi! I run a yoga community called 'Barcelona Yogis'...",
    "availability": "We are available on weekends throughout March and April...",
    "decline_reason": null,
    "applicant_profile": {
      "id": "p1234567-89ab-cdef-0123-456789abcdef",
      "name": "Barcelona Yogis",
      "profile_photo": "https://example.com/photo.jpg",
      "user_type": "community",
      "about": "We are a community of yoga enthusiasts...",
      "instagram": "@barcelona_yogis",
      "website": "https://barcelonayogis.com",
      "community_type": "Sports & Fitness"
    },
    "collab_opportunity": {
      "id": "o9876543-21ab-cdef-0123-456789abcdef",
      "title": "Wellness Workshop Partnership",
      "description": "Looking for yoga instructors...",
      "status": "published",
      "business_offer": {
        "venue": true,
        "food_drink": true
      },
      "community_deliverables": {
        "instagram_post": true,
        "attendee_count": 50
      },
      "creator_profile": {
        "id": "c1234567-89ab-cdef-0123-456789abcdef",
        "name": "Wellness Center BCN",
        "user_type": "business"
      }
    },
    "created_at": "2026-01-29T16:30:00.000000Z",
    "updated_at": "2026-01-29T16:30:00.000000Z"
  }
}
```

---

## 5. Accept Application

Accepts an application and creates a collaboration. Only the opportunity creator can accept.

### Request

```
POST /api/v1/applications/{application_id}/accept
```

### Request Body

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `scheduled_date` | date | No | Scheduled collaboration date |
| `notes` | string | No | Notes for the collaboration |

### Example Request

```json
{
  "scheduled_date": "2026-03-15",
  "notes": "Looking forward to working together! Let's meet at 9:30 AM to set up."
}
```

### Success Response (200 OK)

```json
{
  "success": true,
  "message": "Application accepted and collaboration created",
  "data": {
    "application": {
      "id": "a1b2c3d4-5678-90ab-cdef-123456789abc",
      "status": "accepted",
      "message": "Hi! I run a yoga community...",
      "availability": "Weekends in March..."
    },
    "collaboration": {
      "id": "col12345-6789-0abc-def0-123456789abc",
      "status": "scheduled",
      "scheduled_date": "2026-03-15",
      "notes": "Looking forward to working together!...",
      "creator_profile": {
        "id": "c1234567-89ab-cdef-0123-456789abcdef",
        "name": "Wellness Center BCN",
        "user_type": "business"
      },
      "applicant_profile": {
        "id": "p1234567-89ab-cdef-0123-456789abcdef",
        "name": "Barcelona Yogis",
        "user_type": "community"
      },
      "collab_opportunity": {
        "id": "o9876543-21ab-cdef-0123-456789abcdef",
        "title": "Wellness Workshop Partnership"
      },
      "created_at": "2026-01-29T17:00:00.000000Z"
    }
  }
}
```

---

## 6. Decline Application

Declines an application. Only the opportunity creator can decline.

### Request

```
POST /api/v1/applications/{application_id}/decline
```

### Request Body

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `reason` | string | No | Reason for declining (max: 500) |

### Example Request

```json
{
  "reason": "Thank you for your interest, but we've already found a partner for this collaboration."
}
```

### Success Response (200 OK)

```json
{
  "success": true,
  "message": "Application declined",
  "data": {
    "id": "a1b2c3d4-5678-90ab-cdef-123456789abc",
    "status": "declined",
    "message": "Hi! I run a yoga community...",
    "decline_reason": "Thank you for your interest, but we've already found a partner..."
  }
}
```

---

## 7. Withdraw Application

Withdraws your own application. Only the applicant can withdraw.

### Request

```
POST /api/v1/applications/{application_id}/withdraw
```

### Success Response (200 OK)

```json
{
  "success": true,
  "message": "Application withdrawn",
  "data": {
    "id": "a1b2c3d4-5678-90ab-cdef-123456789abc",
    "status": "withdrawn",
    "message": "Hi! I run a yoga community...",
    "availability": "Weekends in March..."
  }
}
```

---

## Application Status Flow

```
                    ┌─────────────┐
                    │   pending   │
                    └──────┬──────┘
                           │
           ┌───────────────┼───────────────┐
           │               │               │
           ▼               ▼               ▼
    ┌──────────┐    ┌──────────┐    ┌───────────┐
    │ accepted │    │ declined │    │ withdrawn │
    └──────────┘    └──────────┘    └───────────┘
           │
           ▼
    ┌──────────────────┐
    │   Collaboration  │
    │     Created      │
    └──────────────────┘
```

---

## Mobile Implementation Examples

### TypeScript / React Native

```typescript
// Types
interface Application {
  id: string;
  status: 'pending' | 'accepted' | 'declined' | 'withdrawn';
  message: string;
  availability: string;
  decline_reason?: string;
  applicant_profile: Profile;
  collab_opportunity: Opportunity;
  created_at: string;
}

interface ApplyRequest {
  message: string;
  availability: string;
}

interface AcceptRequest {
  scheduled_date?: string;
  notes?: string;
}

interface DeclineRequest {
  reason?: string;
}

// API Functions
const applyToOpportunity = async (
  opportunityId: string,
  data: ApplyRequest
): Promise<Application> => {
  const response = await fetch(
    `${API_BASE_URL}/api/v1/opportunities/${opportunityId}/applications`,
    {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
      body: JSON.stringify(data),
    }
  );

  const result = await response.json();

  if (!result.success) {
    throw new Error(result.message || 'Application failed');
  }

  return result.data;
};

const getMyApplications = async (
  status?: string,
  page: number = 1
): Promise<PaginatedResponse<Application>> => {
  const params = new URLSearchParams();
  if (status) params.append('status', status);
  params.append('page', String(page));

  const response = await fetch(
    `${API_BASE_URL}/api/v1/me/applications?${params}`,
    {
      headers: {
        'Authorization': `Bearer ${token}`,
        'Accept': 'application/json',
      },
    }
  );

  return response.json();
};

const acceptApplication = async (
  applicationId: string,
  data?: AcceptRequest
): Promise<{ application: Application; collaboration: Collaboration }> => {
  const response = await fetch(
    `${API_BASE_URL}/api/v1/applications/${applicationId}/accept`,
    {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
      body: JSON.stringify(data || {}),
    }
  );

  const result = await response.json();

  if (!result.success) {
    throw new Error(result.message);
  }

  return result.data;
};

const declineApplication = async (
  applicationId: string,
  reason?: string
): Promise<Application> => {
  const response = await fetch(
    `${API_BASE_URL}/api/v1/applications/${applicationId}/decline`,
    {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
      body: JSON.stringify({ reason }),
    }
  );

  const result = await response.json();

  if (!result.success) {
    throw new Error(result.message);
  }

  return result.data;
};

const withdrawApplication = async (
  applicationId: string
): Promise<Application> => {
  const response = await fetch(
    `${API_BASE_URL}/api/v1/applications/${applicationId}/withdraw`,
    {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${token}`,
        'Accept': 'application/json',
      },
    }
  );

  const result = await response.json();

  if (!result.success) {
    throw new Error(result.message);
  }

  return result.data;
};
```

### Swift / iOS

```swift
// Models
struct Application: Codable {
    let id: String
    let status: ApplicationStatus
    let message: String
    let availability: String
    let declineReason: String?
    let applicantProfile: Profile
    let collabOpportunity: Opportunity
    let createdAt: String

    enum CodingKeys: String, CodingKey {
        case id, status, message, availability
        case declineReason = "decline_reason"
        case applicantProfile = "applicant_profile"
        case collabOpportunity = "collab_opportunity"
        case createdAt = "created_at"
    }
}

enum ApplicationStatus: String, Codable {
    case pending
    case accepted
    case declined
    case withdrawn
}

struct ApplyRequest: Encodable {
    let message: String
    let availability: String
}

// API Service
class ApplicationService {

    func apply(
        to opportunityId: String,
        message: String,
        availability: String
    ) async throws -> Application {
        let url = URL(string: "\(baseURL)/api/v1/opportunities/\(opportunityId)/applications")!
        var request = URLRequest(url: url)
        request.httpMethod = "POST"
        request.setValue("Bearer \(token)", forHTTPHeaderField: "Authorization")
        request.setValue("application/json", forHTTPHeaderField: "Content-Type")

        let body = ApplyRequest(message: message, availability: availability)
        request.httpBody = try JSONEncoder().encode(body)

        let (data, _) = try await URLSession.shared.data(for: request)
        let response = try JSONDecoder().decode(APIResponse<Application>.self, from: data)

        guard response.success, let application = response.data else {
            throw APIError.requestFailed(response.message ?? "Unknown error")
        }

        return application
    }

    func getMyApplications(status: String? = nil, page: Int = 1) async throws -> PaginatedResponse<Application> {
        var components = URLComponents(string: "\(baseURL)/api/v1/me/applications")!
        var queryItems: [URLQueryItem] = [URLQueryItem(name: "page", value: String(page))]

        if let status = status {
            queryItems.append(URLQueryItem(name: "status", value: status))
        }

        components.queryItems = queryItems

        var request = URLRequest(url: components.url!)
        request.setValue("Bearer \(token)", forHTTPHeaderField: "Authorization")

        let (data, _) = try await URLSession.shared.data(for: request)
        return try JSONDecoder().decode(PaginatedResponse<Application>.self, from: data)
    }

    func accept(applicationId: String, scheduledDate: Date? = nil, notes: String? = nil) async throws -> AcceptResponse {
        let url = URL(string: "\(baseURL)/api/v1/applications/\(applicationId)/accept")!
        var request = URLRequest(url: url)
        request.httpMethod = "POST"
        request.setValue("Bearer \(token)", forHTTPHeaderField: "Authorization")
        request.setValue("application/json", forHTTPHeaderField: "Content-Type")

        var body: [String: Any] = [:]
        if let date = scheduledDate {
            let formatter = DateFormatter()
            formatter.dateFormat = "yyyy-MM-dd"
            body["scheduled_date"] = formatter.string(from: date)
        }
        if let notes = notes {
            body["notes"] = notes
        }

        request.httpBody = try JSONSerialization.data(withJSONObject: body)

        let (data, _) = try await URLSession.shared.data(for: request)
        return try JSONDecoder().decode(AcceptResponse.self, from: data)
    }

    func decline(applicationId: String, reason: String? = nil) async throws -> Application {
        let url = URL(string: "\(baseURL)/api/v1/applications/\(applicationId)/decline")!
        var request = URLRequest(url: url)
        request.httpMethod = "POST"
        request.setValue("Bearer \(token)", forHTTPHeaderField: "Authorization")
        request.setValue("application/json", forHTTPHeaderField: "Content-Type")

        if let reason = reason {
            request.httpBody = try JSONEncoder().encode(["reason": reason])
        }

        let (data, _) = try await URLSession.shared.data(for: request)
        let response = try JSONDecoder().decode(APIResponse<Application>.self, from: data)

        guard response.success, let application = response.data else {
            throw APIError.requestFailed(response.message ?? "Unknown error")
        }

        return application
    }

    func withdraw(applicationId: String) async throws -> Application {
        let url = URL(string: "\(baseURL)/api/v1/applications/\(applicationId)/withdraw")!
        var request = URLRequest(url: url)
        request.httpMethod = "POST"
        request.setValue("Bearer \(token)", forHTTPHeaderField: "Authorization")

        let (data, _) = try await URLSession.shared.data(for: request)
        let response = try JSONDecoder().decode(APIResponse<Application>.self, from: data)

        guard response.success, let application = response.data else {
            throw APIError.requestFailed(response.message ?? "Unknown error")
        }

        return application
    }
}
```

---

## UI/UX Recommendations

### Apply Flow
1. User taps "APPLY" button on opportunity card
2. Show modal/screen with:
   - Opportunity summary at top
   - Message textarea (min 50 chars indicator)
   - Availability textarea (min 20 chars indicator)
   - Character count indicators
   - Submit button (disabled until validation passes)
3. Show loading state during submission
4. On success: Show confirmation, navigate to "My Applications"
5. On error: Show error message, keep form data

### My Applications Screen
1. Tabs/filters for status: All, Pending, Accepted, Declined
2. Pull-to-refresh
3. Each card shows:
   - Opportunity title
   - Creator name & photo
   - Application status badge
   - Submitted date
4. Tap to view full details

### Received Applications Screen (for opportunity creators)
1. Group by opportunity or show flat list
2. Filter by status
3. Each card shows:
   - Applicant name & photo
   - Community/Business type
   - Application message preview
   - Quick action buttons: Accept / Decline
4. Accept flow: Optional date picker and notes field
5. Decline flow: Optional reason field

### Status Badge Colors
- `pending` → Yellow/Orange
- `accepted` → Green
- `declined` → Red
- `withdrawn` → Gray

---

## Error Handling

| HTTP Code | Meaning | User Message |
|-----------|---------|--------------|
| 400 | Bad Request | Show error message from API |
| 401 | Unauthorized | Redirect to login |
| 403 | Forbidden | "You don't have permission to perform this action" |
| 404 | Not Found | "Application not found" |
| 422 | Validation Error | Show field-specific errors |
| 500 | Server Error | "Something went wrong. Please try again." |

---

## Changelog

- **2026-01-29**: Initial documentation
  - Apply to opportunity
  - My applications list
  - Received applications list
  - Accept/Decline/Withdraw actions
