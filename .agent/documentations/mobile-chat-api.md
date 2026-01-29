# Mobile Implementation Guide: Chat API

## Overview

The Chat system allows Business and Community users to communicate after an application is created. Each chat conversation is tied to a specific Application, enabling both the opportunity creator and the applicant to exchange messages.

### Chat Participants
- **Opportunity Creator**: The business/community who created the collaboration opportunity
- **Applicant**: The business/community who applied to the opportunity

## Authentication

All endpoints require Bearer token authentication:
```
Authorization: Bearer {token}
```

---

## Endpoints Summary

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/v1/applications/{id}/messages` | GET | Get chat messages |
| `/api/v1/applications/{id}/messages` | POST | Send a message |
| `/api/v1/applications/{id}/messages/read` | POST | Mark messages as read |
| `/api/v1/me/unread-messages-count` | GET | Get unread message count |

---

## 1. Get Chat Messages

Retrieves paginated chat messages for an application. Only participants (applicant or opportunity creator) can access the chat.

### Request

```
GET /api/v1/applications/{application_id}/messages
```

### Query Parameters

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `per_page` | int | No | 50 | Messages per page (max: 100) |
| `page` | int | No | 1 | Page number |

### Example Request

```
GET /api/v1/applications/a1b2c3d4-5678-90ab-cdef-123456789abc/messages?per_page=20
```

### Success Response (200 OK)

```json
{
  "success": true,
  "data": {
    "data": [
      {
        "id": "msg12345-6789-0abc-def0-123456789abc",
        "application_id": "a1b2c3d4-5678-90ab-cdef-123456789abc",
        "sender_profile": {
          "id": "p1234567-89ab-cdef-0123-456789abcdef",
          "name": "Barcelona Yogis",
          "profile_photo": "https://example.com/photo.jpg",
          "user_type": "community"
        },
        "content": "Hi! I wanted to discuss the collaboration details further.",
        "is_own": false,
        "is_read": true,
        "read_at": "2026-01-29T17:00:00.000000Z",
        "created_at": "2026-01-29T16:45:00.000000Z"
      },
      {
        "id": "msg67890-1234-5abc-def0-123456789abc",
        "application_id": "a1b2c3d4-5678-90ab-cdef-123456789abc",
        "sender_profile": {
          "id": "b9876543-21ab-cdef-0123-456789abcdef",
          "name": "Wellness Center BCN",
          "profile_photo": "https://example.com/business.jpg",
          "user_type": "business"
        },
        "content": "Thank you for applying! We'd love to work with you.",
        "is_own": true,
        "is_read": false,
        "read_at": null,
        "created_at": "2026-01-29T16:30:00.000000Z"
      }
    ]
  },
  "meta": {
    "current_page": 1,
    "last_page": 2,
    "per_page": 20,
    "total": 35
  }
}
```

### Notes
- Messages are ordered by `created_at DESC` (newest first)
- `is_own: true` indicates the message was sent by the authenticated user
- When fetching messages, unread messages from the other party are automatically marked as read

---

## 2. Send Message

Sends a new chat message in the application conversation.

### Request

```
POST /api/v1/applications/{application_id}/messages
```

### Request Body

| Field | Type | Required | Validation | Description |
|-------|------|----------|------------|-------------|
| `content` | string | Yes | min: 1, max: 5000 | The message content |

### Example Request

```json
{
  "content": "Hi! I'm excited about this opportunity. When would be a good time to meet?"
}
```

### Success Response (201 Created)

```json
{
  "success": true,
  "message": "Message sent successfully.",
  "data": {
    "id": "msg-new-1234-5abc-def0-123456789abc",
    "application_id": "a1b2c3d4-5678-90ab-cdef-123456789abc",
    "sender_profile": {
      "id": "p1234567-89ab-cdef-0123-456789abcdef",
      "name": "Barcelona Yogis",
      "profile_photo": "https://example.com/photo.jpg",
      "user_type": "community"
    },
    "content": "Hi! I'm excited about this opportunity. When would be a good time to meet?",
    "is_own": true,
    "is_read": false,
    "read_at": null,
    "created_at": "2026-01-29T17:30:00.000000Z"
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
    "content": ["The message field is required."]
  }
}
```

**Not Authorized (403)**
```json
{
  "success": false,
  "message": "You are not authorized to send messages in this chat."
}
```

---

## 3. Mark Messages as Read

Marks all unread messages from the other participant as read.

### Request

```
POST /api/v1/applications/{application_id}/messages/read
```

### Success Response (200 OK)

```json
{
  "success": true,
  "message": "5 messages marked as read.",
  "data": {
    "marked_count": 5
  }
}
```

### Notes
- Only marks messages sent by the OTHER participant as read
- Your own messages are never marked (they don't need to be)

---

## 4. Get Unread Messages Count

Returns the total unread message count and count per application for the authenticated user.

### Request

```
GET /api/v1/me/unread-messages-count
```

### Success Response (200 OK)

```json
{
  "success": true,
  "data": {
    "total": 12,
    "by_application": {
      "a1b2c3d4-5678-90ab-cdef-123456789abc": 5,
      "x9y8z7w6-5432-10ab-cdef-123456789abc": 7
    }
  }
}
```

### Notes
- `total`: Total unread messages across all conversations
- `by_application`: Map of application ID to unread count
- Use this to show badge counts in the UI

---

## WebSocket Real-time Updates

### Channel

Subscribe to receive real-time messages:
```
Private Channel: chat.application.{application_id}
```

### Event: message.sent

Fired when a new message is sent in the chat.

```json
{
  "message": {
    "id": "msg-new-1234-5abc-def0-123456789abc",
    "application_id": "a1b2c3d4-5678-90ab-cdef-123456789abc",
    "sender_profile": {
      "id": "p1234567-89ab-cdef-0123-456789abcdef",
      "name": "Barcelona Yogis",
      "user_type": "community"
    },
    "content": "New message content here",
    "is_own": false,
    "is_read": false,
    "read_at": null,
    "created_at": "2026-01-29T17:45:00.000000Z"
  }
}
```

### Authorization

The WebSocket channel is private. Users must be authenticated and be a participant (applicant or opportunity creator) to subscribe.

---

## Mobile Implementation Examples

### TypeScript / React Native

```typescript
// Types
interface ChatMessage {
  id: string;
  application_id: string;
  sender_profile: ProfileSummary;
  content: string;
  is_own: boolean;
  is_read: boolean;
  read_at: string | null;
  created_at: string;
}

interface ProfileSummary {
  id: string;
  name: string;
  profile_photo: string | null;
  user_type: 'business' | 'community';
}

interface UnreadCount {
  total: number;
  by_application: Record<string, number>;
}

// API Functions
const getChatMessages = async (
  applicationId: string,
  page: number = 1,
  perPage: number = 50
): Promise<PaginatedResponse<ChatMessage>> => {
  const params = new URLSearchParams({
    page: String(page),
    per_page: String(perPage),
  });

  const response = await fetch(
    `${API_BASE_URL}/api/v1/applications/${applicationId}/messages?${params}`,
    {
      headers: {
        'Authorization': `Bearer ${token}`,
        'Accept': 'application/json',
      },
    }
  );

  return response.json();
};

const sendMessage = async (
  applicationId: string,
  content: string
): Promise<{ success: boolean; data: ChatMessage }> => {
  const response = await fetch(
    `${API_BASE_URL}/api/v1/applications/${applicationId}/messages`,
    {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json',
        'Accept': 'application/json',
      },
      body: JSON.stringify({ content }),
    }
  );

  return response.json();
};

const markMessagesAsRead = async (
  applicationId: string
): Promise<{ success: boolean; data: { marked_count: number } }> => {
  const response = await fetch(
    `${API_BASE_URL}/api/v1/applications/${applicationId}/messages/read`,
    {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${token}`,
        'Accept': 'application/json',
      },
    }
  );

  return response.json();
};

const getUnreadCount = async (): Promise<{ success: boolean; data: UnreadCount }> => {
  const response = await fetch(
    `${API_BASE_URL}/api/v1/me/unread-messages-count`,
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

### WebSocket Connection (React Native with Laravel Echo)

```typescript
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

// Initialize Echo
const echo = new Echo({
  broadcaster: 'reverb',
  key: REVERB_APP_KEY,
  wsHost: REVERB_HOST,
  wsPort: 443,
  wssPort: 443,
  forceTLS: true,
  enabledTransports: ['ws', 'wss'],
  authEndpoint: `${API_BASE_URL}/broadcasting/auth`,
  auth: {
    headers: {
      Authorization: `Bearer ${token}`,
    },
  },
});

// Subscribe to chat channel
const subscribeToChat = (applicationId: string, onMessage: (message: ChatMessage) => void) => {
  return echo.private(`chat.application.${applicationId}`)
    .listen('.message.sent', (event: { message: ChatMessage }) => {
      onMessage(event.message);
    });
};

// Unsubscribe when leaving chat
const unsubscribeFromChat = (applicationId: string) => {
  echo.leave(`chat.application.${applicationId}`);
};
```

### Swift / iOS

```swift
// Models
struct ChatMessage: Codable {
    let id: String
    let applicationId: String
    let senderProfile: ProfileSummary
    let content: String
    let isOwn: Bool
    let isRead: Bool
    let readAt: String?
    let createdAt: String

    enum CodingKeys: String, CodingKey {
        case id
        case applicationId = "application_id"
        case senderProfile = "sender_profile"
        case content
        case isOwn = "is_own"
        case isRead = "is_read"
        case readAt = "read_at"
        case createdAt = "created_at"
    }
}

struct UnreadCount: Codable {
    let total: Int
    let byApplication: [String: Int]

    enum CodingKeys: String, CodingKey {
        case total
        case byApplication = "by_application"
    }
}

// Chat Service
class ChatService {
    private let baseURL: String
    private let token: String

    init(baseURL: String, token: String) {
        self.baseURL = baseURL
        self.token = token
    }

    func getMessages(applicationId: String, page: Int = 1, perPage: Int = 50) async throws -> PaginatedResponse<ChatMessage> {
        var components = URLComponents(string: "\(baseURL)/api/v1/applications/\(applicationId)/messages")!
        components.queryItems = [
            URLQueryItem(name: "page", value: String(page)),
            URLQueryItem(name: "per_page", value: String(perPage))
        ]

        var request = URLRequest(url: components.url!)
        request.setValue("Bearer \(token)", forHTTPHeaderField: "Authorization")
        request.setValue("application/json", forHTTPHeaderField: "Accept")

        let (data, _) = try await URLSession.shared.data(for: request)
        return try JSONDecoder().decode(PaginatedResponse<ChatMessage>.self, from: data)
    }

    func sendMessage(applicationId: String, content: String) async throws -> ChatMessage {
        let url = URL(string: "\(baseURL)/api/v1/applications/\(applicationId)/messages")!
        var request = URLRequest(url: url)
        request.httpMethod = "POST"
        request.setValue("Bearer \(token)", forHTTPHeaderField: "Authorization")
        request.setValue("application/json", forHTTPHeaderField: "Content-Type")
        request.setValue("application/json", forHTTPHeaderField: "Accept")
        request.httpBody = try JSONEncoder().encode(["content": content])

        let (data, _) = try await URLSession.shared.data(for: request)
        let response = try JSONDecoder().decode(APIResponse<ChatMessage>.self, from: data)

        guard response.success, let message = response.data else {
            throw APIError.requestFailed(response.message ?? "Unknown error")
        }

        return message
    }

    func markAsRead(applicationId: String) async throws -> Int {
        let url = URL(string: "\(baseURL)/api/v1/applications/\(applicationId)/messages/read")!
        var request = URLRequest(url: url)
        request.httpMethod = "POST"
        request.setValue("Bearer \(token)", forHTTPHeaderField: "Authorization")
        request.setValue("application/json", forHTTPHeaderField: "Accept")

        let (data, _) = try await URLSession.shared.data(for: request)

        struct MarkReadResponse: Codable {
            let success: Bool
            let data: MarkedData

            struct MarkedData: Codable {
                let markedCount: Int

                enum CodingKeys: String, CodingKey {
                    case markedCount = "marked_count"
                }
            }
        }

        let response = try JSONDecoder().decode(MarkReadResponse.self, from: data)
        return response.data.markedCount
    }

    func getUnreadCount() async throws -> UnreadCount {
        let url = URL(string: "\(baseURL)/api/v1/me/unread-messages-count")!
        var request = URLRequest(url: url)
        request.setValue("Bearer \(token)", forHTTPHeaderField: "Authorization")
        request.setValue("application/json", forHTTPHeaderField: "Accept")

        let (data, _) = try await URLSession.shared.data(for: request)
        let response = try JSONDecoder().decode(APIResponse<UnreadCount>.self, from: data)

        guard response.success, let unreadCount = response.data else {
            throw APIError.requestFailed(response.message ?? "Unknown error")
        }

        return unreadCount
    }
}
```

---

## UI/UX Recommendations

### Chat Screen
1. **Header**: Show the other participant's name and profile photo
2. **Message List**:
   - Scroll to load older messages (pagination)
   - Group messages by date
   - Show sender's avatar for each message
   - Differentiate own messages (right-aligned) from others (left-aligned)
3. **Input Area**:
   - Text input with send button
   - Disable send button when empty
   - Show sending state while request is in progress

### Message Bubble Design
- **Own messages**: Right-aligned, primary color background
- **Other's messages**: Left-aligned, secondary/gray background
- Show timestamp below message
- Show read status for own messages (✓ for sent, ✓✓ for read)

### Unread Badges
- Show unread count badge on:
  - Bottom navigation chat tab
  - Application card in "My Applications" list
  - Individual chat list items

### Real-time Updates
- Subscribe to WebSocket channel when entering chat
- Unsubscribe when leaving chat screen
- Play notification sound for new messages
- Mark messages as read when chat screen is visible

### Error Handling
| Error | User Message |
|-------|--------------|
| 401 | Session expired, redirect to login |
| 403 | You cannot access this chat |
| 422 | Please enter a message |
| 500 | Something went wrong, please try again |

---

## Database Schema

```sql
CREATE TABLE chat_messages (
    id UUID PRIMARY KEY,
    application_id UUID NOT NULL REFERENCES applications(id) ON DELETE CASCADE,
    sender_profile_id UUID NOT NULL REFERENCES profiles(id),
    content TEXT NOT NULL,
    read_at TIMESTAMP NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

CREATE INDEX idx_chat_messages_application ON chat_messages(application_id, created_at);
CREATE INDEX idx_chat_messages_sender ON chat_messages(sender_profile_id);
```

---

## Changelog

- **2026-01-29**: Initial implementation
  - Get messages endpoint
  - Send message endpoint
  - Mark as read endpoint
  - Unread count endpoint
  - WebSocket real-time messaging
  - Application-scoped chat
