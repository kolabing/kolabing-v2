# Gamification Phase 1 -- Mobile Implementation Guide

**Last updated:** 2026-02-06
**API Base URL:** `/api/v1/`
**Authentication:** Bearer token via Laravel Sanctum (all gamification endpoints require authentication)

---

## 1. Overview

Gamification Phase 1 introduces an event-based engagement system to Kolabing. The core concept:

- **Organizers** (business/community users) create events and define challenges for attendees.
- **Attendees** (new user type) check in to events via QR codes and complete peer-to-peer challenges to earn points.
- **Challenges** follow a peer verification model: one attendee initiates a challenge, and another checked-in attendee verifies completion.
- Points are tracked on the attendee profile, enabling future leaderboard and reward features.

### Key Components Built

| Component | Description |
|-----------|-------------|
| Attendee user type | New `attendee` value in the `user_type` enum, with a dedicated `attendee_profiles` table tracking stats |
| QR Check-in | Organizer generates a token per event; attendees scan QR to check in |
| Challenges | System-wide challenges + organizer custom challenges per event |
| Challenge Completions | Peer-to-peer initiate/verify/reject flow with automatic point awarding |

---

## 2. New User Type: Attendee

### 2.1 Registration Flow

Attendees register with a minimal payload -- only email and password. No extended profile data (name, city, etc.) is required at registration time.

**Endpoint:** `POST /api/v1/auth/register/attendee`

Upon registration, the backend:
1. Creates a `profiles` row with `user_type = "attendee"`
2. Creates an `attendee_profiles` row with all stats initialized to 0
3. Returns a Sanctum Bearer token (expires in 30 days)

Attendees can also log in via `POST /api/v1/auth/login` with email + password.

### 2.2 AttendeeProfile Structure

The `attendee_profiles` table tracks gamification statistics:

| Field | Type | Default | Description |
|-------|------|---------|-------------|
| `id` | UUID | auto | Primary key |
| `profile_id` | UUID | -- | FK to `profiles` table |
| `total_points` | integer | 0 | Accumulated points from verified challenges |
| `total_challenges_completed` | integer | 0 | Number of verified challenge completions |
| `total_events_attended` | integer | 0 | Number of events checked in to |
| `global_rank` | integer | null | Reserved for future leaderboard ranking |
| `created_at` | datetime | auto | -- |
| `updated_at` | datetime | auto | -- |

These stats are updated automatically by the backend -- the mobile app reads them but never writes to them directly.

---

## 3. Complete API Reference

### 3.1 Attendee Registration

#### `POST /api/v1/auth/register/attendee`

**Auth required:** No

**Request body:**

```json
{
  "email": "attendee@example.com",
  "password": "securepass123",
  "password_confirmation": "securepass123"
}
```

| Field | Type | Required | Validation |
|-------|------|----------|------------|
| `email` | string | Yes | Valid email, max 255, unique in `profiles` table |
| `password` | string | Yes | Min 8 characters |
| `password_confirmation` | string | Yes | Must match `password` |

**Success response (201):**

```json
{
  "success": true,
  "message": "Registration successful",
  "data": {
    "token": "1|abc123...",
    "token_type": "Bearer",
    "is_new_user": true,
    "user": {
      "id": "uuid-here",
      "email": "attendee@example.com",
      "phone_number": null,
      "user_type": "attendee",
      "avatar_url": null,
      "email_verified_at": null,
      "onboarding_completed": false,
      "created_at": "2026-02-06T10:00:00+00:00",
      "updated_at": "2026-02-06T10:00:00+00:00"
    }
  }
}
```

**Error response (422) -- Validation failed:**

```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "email": ["This email is already registered."],
    "password": ["Password must be at least 8 characters."]
  }
}
```

---

### 3.2 Generate QR Check-in Token

#### `POST /api/v1/events/{event_id}/generate-qr`

**Auth required:** Yes (must be the event owner)

**Request body:** None

**Success response (200):**

```json
{
  "success": true,
  "data": {
    "checkin_token": "aB3x...64-character-random-string..."
  }
}
```

The returned `checkin_token` is a 64-character random string. Each call generates a new token, replacing the previous one.

**Error response (403) -- Not event owner:**

```json
{
  "success": false,
  "message": "You are not authorized to generate a QR token for this event."
}
```

---

### 3.3 Check In (Scan QR)

#### `POST /api/v1/checkin`

**Auth required:** Yes

**Request body:**

```json
{
  "token": "aB3x...64-character-token..."
}
```

| Field | Type | Required | Validation |
|-------|------|----------|------------|
| `token` | string | Yes | Max 64 characters |

**Success response (200):**

```json
{
  "success": true,
  "message": "Checked in successfully.",
  "data": {
    "id": "uuid-checkin",
    "event_id": "uuid-event",
    "profile_id": "uuid-profile",
    "checked_in_at": "2026-02-06T14:30:00+00:00",
    "created_at": "2026-02-06T14:30:00+00:00"
  }
}
```

**Error responses:**

| Status | Condition | Message |
|--------|-----------|---------|
| 404 | Invalid/expired token | `"Invalid check-in token."` |
| 422 | Event not accepting check-ins | `"This event is not currently accepting check-ins."` |
| 409 | Already checked in | `"You have already checked in to this event."` |

---

### 3.4 List Event Check-ins

#### `GET /api/v1/events/{event_id}/checkins`

**Auth required:** Yes

**Query parameters:**

| Param | Type | Default | Max | Description |
|-------|------|---------|-----|-------------|
| `limit` | integer | 10 | 50 | Items per page |
| `page` | integer | 1 | -- | Page number |

**Success response (200):**

```json
{
  "success": true,
  "data": {
    "checkins": [
      {
        "id": "uuid-checkin",
        "event_id": "uuid-event",
        "profile_id": "uuid-profile",
        "checked_in_at": "2026-02-06T14:30:00+00:00",
        "created_at": "2026-02-06T14:30:00+00:00"
      }
    ],
    "pagination": {
      "current_page": 1,
      "total_pages": 3,
      "total_count": 25,
      "per_page": 10
    }
  }
}
```

---

### 3.5 List Challenges for an Event

#### `GET /api/v1/events/{event_id}/challenges`

**Auth required:** Yes

Returns both system-wide challenges and custom challenges specific to this event. System challenges are listed first, then sorted by difficulty.

**Query parameters:**

| Param | Type | Default | Max | Description |
|-------|------|---------|-----|-------------|
| `limit` | integer | 20 | 50 | Items per page |
| `page` | integer | 1 | -- | Page number |

**Success response (200):**

```json
{
  "success": true,
  "data": {
    "challenges": [
      {
        "id": "uuid-challenge",
        "name": "Take a selfie with 3 strangers",
        "description": "Find 3 people you have never met and take a group selfie.",
        "difficulty": "easy",
        "points": 5,
        "is_system": true,
        "event_id": null,
        "created_at": "2026-01-15T09:00:00+00:00",
        "updated_at": "2026-01-15T09:00:00+00:00"
      },
      {
        "id": "uuid-challenge-2",
        "name": "Custom event challenge",
        "description": "A challenge specific to this event.",
        "difficulty": "hard",
        "points": 30,
        "is_system": false,
        "event_id": "uuid-event",
        "created_at": "2026-02-05T12:00:00+00:00",
        "updated_at": "2026-02-05T12:00:00+00:00"
      }
    ],
    "pagination": {
      "current_page": 1,
      "total_pages": 1,
      "total_count": 2,
      "per_page": 20
    }
  }
}
```

**Challenge fields:**

| Field | Type | Description |
|-------|------|-------------|
| `id` | string (UUID) | Challenge unique ID |
| `name` | string | Challenge title |
| `description` | string or null | Detailed description |
| `difficulty` | string | One of: `"easy"`, `"medium"`, `"hard"` |
| `points` | integer | Points awarded upon verified completion |
| `is_system` | boolean | `true` = system-wide challenge, `false` = custom event challenge |
| `event_id` | string (UUID) or null | The event this challenge belongs to (`null` for system challenges) |
| `created_at` | string (ISO 8601) | -- |
| `updated_at` | string (ISO 8601) | -- |

---

### 3.6 Create Custom Challenge

#### `POST /api/v1/events/{event_id}/challenges`

**Auth required:** Yes (must be the event owner)

**Request body:**

```json
{
  "name": "Dance with the DJ",
  "description": "Go to the DJ booth and do a 30-second dance.",
  "difficulty": "hard",
  "points": 30
}
```

| Field | Type | Required | Validation |
|-------|------|----------|------------|
| `name` | string | Yes | Min 3, max 150 characters |
| `description` | string | No | Max 500 characters |
| `difficulty` | string | Yes | Must be `"easy"`, `"medium"`, or `"hard"` |
| `points` | integer | No | Min 1, max 100. If omitted, defaults to difficulty-based value (see section 6) |

**Success response (201):**

```json
{
  "success": true,
  "message": "Challenge created successfully.",
  "data": {
    "id": "uuid-new-challenge",
    "name": "Dance with the DJ",
    "description": "Go to the DJ booth and do a 30-second dance.",
    "difficulty": "hard",
    "points": 30,
    "is_system": false,
    "event_id": "uuid-event",
    "created_at": "2026-02-06T15:00:00+00:00",
    "updated_at": "2026-02-06T15:00:00+00:00"
  }
}
```

**Error response (403) -- Not event owner:**

```json
{
  "success": false,
  "message": "You are not authorized to create challenges for this event."
}
```

---

### 3.7 Update Custom Challenge

#### `PUT /api/v1/challenges/{challenge_id}`

**Auth required:** Yes (must be the event owner; cannot update system challenges)

**Request body (all fields optional):**

```json
{
  "name": "Updated challenge name",
  "description": "Updated description.",
  "difficulty": "medium",
  "points": 20
}
```

| Field | Type | Required | Validation |
|-------|------|----------|------------|
| `name` | string | No | Min 3, max 150 characters |
| `description` | string | No | Max 500 characters |
| `difficulty` | string | No | Must be `"easy"`, `"medium"`, or `"hard"` |
| `points` | integer | No | Min 1, max 100. If difficulty changes and points is omitted, points auto-update to new difficulty default |

**Success response (200):**

```json
{
  "success": true,
  "message": "Challenge updated successfully.",
  "data": {
    "id": "uuid-challenge",
    "name": "Updated challenge name",
    "description": "Updated description.",
    "difficulty": "medium",
    "points": 20,
    "is_system": false,
    "event_id": "uuid-event",
    "created_at": "2026-02-05T12:00:00+00:00",
    "updated_at": "2026-02-06T16:00:00+00:00"
  }
}
```

**Error response (403):**

```json
{
  "success": false,
  "message": "You are not authorized to update this challenge."
}
```

---

### 3.8 Delete Custom Challenge

#### `DELETE /api/v1/challenges/{challenge_id}`

**Auth required:** Yes (must be the event owner; cannot delete system challenges)

**Request body:** None

**Success response (200):**

```json
{
  "success": true,
  "message": "Challenge deleted successfully."
}
```

**Error response (403):**

```json
{
  "success": false,
  "message": "You are not authorized to delete this challenge."
}
```

---

### 3.9 Initiate a Challenge (Peer-to-Peer)

#### `POST /api/v1/challenges/initiate`

**Auth required:** Yes

This endpoint creates a `ChallengeCompletion` record in `pending` status. The challenger and the verifier must both be checked in to the same event.

**Request body:**

```json
{
  "challenge_id": "uuid-challenge",
  "event_id": "uuid-event",
  "verifier_profile_id": "uuid-other-attendee"
}
```

| Field | Type | Required | Validation |
|-------|------|----------|------------|
| `challenge_id` | string | Yes | Valid UUID, must exist in `challenges` table |
| `event_id` | string | Yes | Valid UUID, must exist in `events` table |
| `verifier_profile_id` | string | Yes | Valid UUID, must exist in `profiles` table, cannot be the same as the authenticated user |

**Success response (201):**

```json
{
  "success": true,
  "message": "Challenge initiated successfully.",
  "data": {
    "id": "uuid-completion",
    "challenge": {
      "id": "uuid-challenge",
      "name": "Take a selfie with 3 strangers",
      "description": "Find 3 people you have never met and take a group selfie.",
      "difficulty": "easy",
      "points": 5,
      "is_system": true,
      "event_id": null,
      "created_at": "2026-01-15T09:00:00+00:00",
      "updated_at": "2026-01-15T09:00:00+00:00"
    },
    "event_id": "uuid-event",
    "challenger_profile_id": "uuid-my-profile",
    "verifier_profile_id": "uuid-other-attendee",
    "status": "pending",
    "points_earned": 0,
    "completed_at": null,
    "created_at": "2026-02-06T15:30:00+00:00"
  }
}
```

**Error responses:**

| Status | Condition | Message |
|--------|-----------|---------|
| 422 | Challenger not checked in | `"You must be checked in to the event to initiate a challenge."` |
| 422 | Verifier not checked in | `"The verifier must be checked in to the event."` |
| 422 | Self-challenge attempt | `"You cannot challenge yourself."` |
| 409 | Duplicate challenge between same pair | `"This challenge has already been initiated between these two attendees."` |
| 409 | Max challenges exceeded | `"You have reached the maximum number of challenges for this event."` |

---

### 3.10 Verify a Challenge Completion

#### `POST /api/v1/challenge-completions/{challenge_completion_id}/verify`

**Auth required:** Yes (must be the designated verifier)

This endpoint transitions the completion from `pending` to `verified`, awards points to the challenger, and updates the challenger's `attendee_profiles` stats.

**Request body:** None

**Success response (200):**

```json
{
  "success": true,
  "message": "Challenge verified successfully.",
  "data": {
    "id": "uuid-completion",
    "challenge": {
      "id": "uuid-challenge",
      "name": "Take a selfie with 3 strangers",
      "description": "Find 3 people you have never met and take a group selfie.",
      "difficulty": "easy",
      "points": 5,
      "is_system": true,
      "event_id": null,
      "created_at": "2026-01-15T09:00:00+00:00",
      "updated_at": "2026-01-15T09:00:00+00:00"
    },
    "event_id": "uuid-event",
    "challenger_profile_id": "uuid-challenger",
    "verifier_profile_id": "uuid-my-profile",
    "status": "verified",
    "points_earned": 5,
    "completed_at": "2026-02-06T15:35:00+00:00",
    "created_at": "2026-02-06T15:30:00+00:00"
  }
}
```

**Error responses:**

| Status | Condition | Message |
|--------|-----------|---------|
| 403 | Not the designated verifier | `"You are not the designated verifier for this challenge."` |
| 409 | Already processed (verified or rejected) | `"This challenge completion has already been processed."` |

---

### 3.11 Reject a Challenge Completion

#### `POST /api/v1/challenge-completions/{challenge_completion_id}/reject`

**Auth required:** Yes (must be the designated verifier)

Transitions the completion from `pending` to `rejected`. No points are awarded.

**Request body:** None

**Success response (200):**

```json
{
  "success": true,
  "message": "Challenge rejected.",
  "data": {
    "id": "uuid-completion",
    "challenge": {
      "id": "uuid-challenge",
      "name": "Take a selfie with 3 strangers",
      "description": null,
      "difficulty": "easy",
      "points": 5,
      "is_system": true,
      "event_id": null,
      "created_at": "2026-01-15T09:00:00+00:00",
      "updated_at": "2026-01-15T09:00:00+00:00"
    },
    "event_id": "uuid-event",
    "challenger_profile_id": "uuid-challenger",
    "verifier_profile_id": "uuid-my-profile",
    "status": "rejected",
    "points_earned": 0,
    "completed_at": null,
    "created_at": "2026-02-06T15:30:00+00:00"
  }
}
```

**Error responses:**

| Status | Condition | Message |
|--------|-----------|---------|
| 403 | Not the designated verifier | `"You are not the designated verifier for this challenge."` |
| 409 | Already processed | `"This challenge completion has already been processed."` |

---

### 3.12 My Challenge Completions

#### `GET /api/v1/me/challenge-completions`

**Auth required:** Yes

Returns all challenge completions where the authenticated user is either the **challenger** or the **verifier**. Ordered by most recent first.

**Query parameters:**

| Param | Type | Default | Max | Description |
|-------|------|---------|-----|-------------|
| `limit` | integer | 10 | 50 | Items per page |
| `page` | integer | 1 | -- | Page number |

**Success response (200):**

```json
{
  "success": true,
  "data": {
    "completions": [
      {
        "id": "uuid-completion",
        "challenge": {
          "id": "uuid-challenge",
          "name": "Take a selfie with 3 strangers",
          "description": "Find 3 people...",
          "difficulty": "easy",
          "points": 5,
          "is_system": true,
          "event_id": null,
          "created_at": "2026-01-15T09:00:00+00:00",
          "updated_at": "2026-01-15T09:00:00+00:00"
        },
        "event_id": "uuid-event",
        "challenger_profile_id": "uuid-me",
        "verifier_profile_id": "uuid-other",
        "status": "verified",
        "points_earned": 5,
        "completed_at": "2026-02-06T15:35:00+00:00",
        "created_at": "2026-02-06T15:30:00+00:00"
      }
    ],
    "pagination": {
      "current_page": 1,
      "total_pages": 1,
      "total_count": 1,
      "per_page": 10
    }
  }
}
```

---

## 4. User Flows

### 4.1 Attendee Registration Flow

```
1. User opens app -> selects "I am an Attendee"
2. App collects: email, password, password_confirmation
3. POST /api/v1/auth/register/attendee
4. Store returned token in secure storage
5. Navigate to attendee home screen
6. User can later log in via POST /api/v1/auth/login
```

### 4.2 Event Check-in Flow (QR Code)

**Organizer side:**

```
1. Organizer views their event in the app
2. Taps "Generate QR Code"
3. App calls POST /api/v1/events/{event_id}/generate-qr
4. App receives checkin_token (64-char string)
5. App encodes token into a QR code and displays it
   (see Section 7 for QR content format)
6. Organizer shows QR code on screen for attendees to scan
```

**Attendee side:**

```
1. Attendee opens QR scanner in the app
2. Scans the QR code displayed by organizer
3. App extracts the token from the QR data
4. App calls POST /api/v1/checkin with { "token": "..." }
5. On success: show confirmation with event details
6. Backend automatically increments total_events_attended on the attendee profile
```

### 4.3 Challenge Completion Flow

```
                  CHALLENGER                        VERIFIER
                  ──────────                        ────────
1. Browse event challenges
   GET /api/v1/events/{id}/challenges
                      │
2. Select a challenge + pick a
   verifier (another checked-in attendee)
                      │
3. POST /api/v1/challenges/initiate
   { challenge_id, event_id,
     verifier_profile_id }
                      │
   ────────── completion created (pending) ──────────
                      │                         │
                      │              4. Verifier sees pending
                      │                 request (via polling
                      │                 GET /me/challenge-completions)
                      │                         │
                      │              5a. POST .../verify
                      │                  -> status: verified
                      │                  -> points awarded
                      │                         │
                      │              5b. POST .../reject
                      │                  -> status: rejected
                      │                  -> no points
                      │                         │
6. Challenger polls their completions
   to see the updated status
```

**Important:** Both the challenger and the verifier must be checked in to the same event before a challenge can be initiated.

### 4.4 Organizer Challenge Management Flow

```
1. Organizer navigates to their event
2. Views existing challenges:
   GET /api/v1/events/{event_id}/challenges
   (shows system + custom challenges)
3. Creates a custom challenge:
   POST /api/v1/events/{event_id}/challenges
   { name, description, difficulty, points? }
4. Updates a custom challenge (cannot update system challenges):
   PUT /api/v1/challenges/{challenge_id}
5. Deletes a custom challenge (cannot delete system challenges):
   DELETE /api/v1/challenges/{challenge_id}
```

---

## 5. Data Models (Suggested Dart Classes)

### 5.1 AttendeeProfile

```dart
class AttendeeProfile {
  final String id;
  final String profileId;
  final int totalPoints;
  final int totalChallengesCompleted;
  final int totalEventsAttended;
  final int? globalRank;
  final DateTime createdAt;
  final DateTime updatedAt;

  AttendeeProfile({
    required this.id,
    required this.profileId,
    this.totalPoints = 0,
    this.totalChallengesCompleted = 0,
    this.totalEventsAttended = 0,
    this.globalRank,
    required this.createdAt,
    required this.updatedAt,
  });

  factory AttendeeProfile.fromJson(Map<String, dynamic> json) {
    return AttendeeProfile(
      id: json['id'] as String,
      profileId: json['profile_id'] as String,
      totalPoints: json['total_points'] as int? ?? 0,
      totalChallengesCompleted: json['total_challenges_completed'] as int? ?? 0,
      totalEventsAttended: json['total_events_attended'] as int? ?? 0,
      globalRank: json['global_rank'] as int?,
      createdAt: DateTime.parse(json['created_at'] as String),
      updatedAt: DateTime.parse(json['updated_at'] as String),
    );
  }
}
```

### 5.2 EventCheckin

```dart
class EventCheckin {
  final String id;
  final String eventId;
  final String profileId;
  final DateTime checkedInAt;
  final DateTime createdAt;

  EventCheckin({
    required this.id,
    required this.eventId,
    required this.profileId,
    required this.checkedInAt,
    required this.createdAt,
  });

  factory EventCheckin.fromJson(Map<String, dynamic> json) {
    return EventCheckin(
      id: json['id'] as String,
      eventId: json['event_id'] as String,
      profileId: json['profile_id'] as String,
      checkedInAt: DateTime.parse(json['checked_in_at'] as String),
      createdAt: DateTime.parse(json['created_at'] as String),
    );
  }
}
```

### 5.3 Challenge

```dart
enum ChallengeDifficulty { easy, medium, hard }

class Challenge {
  final String id;
  final String name;
  final String? description;
  final ChallengeDifficulty difficulty;
  final int points;
  final bool isSystem;
  final String? eventId;
  final DateTime createdAt;
  final DateTime updatedAt;

  Challenge({
    required this.id,
    required this.name,
    this.description,
    required this.difficulty,
    required this.points,
    required this.isSystem,
    this.eventId,
    required this.createdAt,
    required this.updatedAt,
  });

  factory Challenge.fromJson(Map<String, dynamic> json) {
    return Challenge(
      id: json['id'] as String,
      name: json['name'] as String,
      description: json['description'] as String?,
      difficulty: ChallengeDifficulty.values.firstWhere(
        (e) => e.name == json['difficulty'],
      ),
      points: json['points'] as int,
      isSystem: json['is_system'] as bool,
      eventId: json['event_id'] as String?,
      createdAt: DateTime.parse(json['created_at'] as String),
      updatedAt: DateTime.parse(json['updated_at'] as String),
    );
  }
}
```

### 5.4 ChallengeCompletion

```dart
enum ChallengeCompletionStatus { pending, verified, rejected }

class ChallengeCompletion {
  final String id;
  final Challenge? challenge;
  final String eventId;
  final String challengerProfileId;
  final String verifierProfileId;
  final ChallengeCompletionStatus status;
  final int pointsEarned;
  final DateTime? completedAt;
  final DateTime createdAt;

  ChallengeCompletion({
    required this.id,
    this.challenge,
    required this.eventId,
    required this.challengerProfileId,
    required this.verifierProfileId,
    required this.status,
    required this.pointsEarned,
    this.completedAt,
    required this.createdAt,
  });

  factory ChallengeCompletion.fromJson(Map<String, dynamic> json) {
    return ChallengeCompletion(
      id: json['id'] as String,
      challenge: json['challenge'] != null
          ? Challenge.fromJson(json['challenge'] as Map<String, dynamic>)
          : null,
      eventId: json['event_id'] as String,
      challengerProfileId: json['challenger_profile_id'] as String,
      verifierProfileId: json['verifier_profile_id'] as String,
      status: ChallengeCompletionStatus.values.firstWhere(
        (e) => e.name == json['status'],
      ),
      pointsEarned: json['points_earned'] as int,
      completedAt: json['completed_at'] != null
          ? DateTime.parse(json['completed_at'] as String)
          : null,
      createdAt: DateTime.parse(json['created_at'] as String),
    );
  }

  bool get isPending => status == ChallengeCompletionStatus.pending;
  bool get isVerified => status == ChallengeCompletionStatus.verified;
  bool get isRejected => status == ChallengeCompletionStatus.rejected;
}
```

---

## 6. Business Rules

### 6.1 Point Values by Difficulty

When creating a challenge, if `points` is not provided, the backend assigns a default based on difficulty:

| Difficulty | Default Points |
|------------|---------------|
| `easy` | 5 |
| `medium` | 15 |
| `hard` | 30 |

Custom point values can be set between 1 and 100 per challenge.

### 6.2 Challenge Constraints

- **Max challenges per attendee per event:** Controlled by the `max_challenges_per_attendee` field on the `events` table. The backend enforces this limit when initiating challenges. The total count includes all statuses (pending, verified, rejected).
- **No duplicate challenge between the same pair:** The same challenger cannot initiate the same challenge with the same verifier at the same event twice.
- **Self-challenge prevention:** A user cannot set themselves as the verifier. This is validated at the request level (returns 422).
- **Both parties must be checked in:** Both the challenger and the verifier must have an existing `event_checkins` record for the event.

### 6.3 Challenge Types

- **System challenges** (`is_system = true`): Pre-seeded in the database. Available at all events. Cannot be updated or deleted by any user.
- **Custom challenges** (`is_system = false`): Created by the event owner. Tied to a specific event via `event_id`. Can be updated/deleted only by the event owner.

### 6.4 Points and Stats Auto-Update

When a challenge is **verified**, the backend automatically:
1. Sets `points_earned` on the `challenge_completions` record to the challenge's point value
2. Sets `completed_at` to the current timestamp
3. Increments `total_points` on the challenger's `attendee_profiles` record
4. Increments `total_challenges_completed` on the challenger's `attendee_profiles` record

When a challenge is **rejected**, no points or stats are updated.

When an attendee **checks in** to an event, the backend automatically:
1. Increments `total_events_attended` on the attendee's `attendee_profiles` record

### 6.5 Check-in Constraints

- **One check-in per attendee per event:** Attempting a second check-in returns 409 Conflict.
- **Event must be active:** The event's `is_active` field must be `true` to accept check-ins. Generating a QR token automatically sets `is_active = true`.
- **Token replacement:** Each call to `generate-qr` creates a new 64-character token, replacing the previous one for that event.

### 6.6 Challenge Update Behavior

When updating a challenge's `difficulty` without specifying `points`, the points are automatically recalculated to the new difficulty's default value. If `points` is explicitly provided, it takes precedence regardless of difficulty change.

---

## 7. QR Code Implementation Notes

### 7.1 QR Content Format

The QR code should encode a simple JSON payload:

```json
{
  "type": "kolabing_checkin",
  "token": "aB3x...64-character-string..."
}
```

Alternatively, you can encode just the raw token string if you prefer a simpler approach. The backend only needs the `token` value.

### 7.2 Organizer Side -- QR Generation

1. Call `POST /api/v1/events/{event_id}/generate-qr`
2. Receive the `checkin_token` string from the response
3. Use a Flutter QR generation package (e.g., `qr_flutter`) to render the token as a QR code
4. Display the QR code full-screen or in a card for attendees to scan
5. Optionally add a "Regenerate" button that calls the endpoint again (note: previous tokens become invalid)

### 7.3 Attendee Side -- QR Scanning

1. Use a Flutter QR scanning package (e.g., `mobile_scanner` or `qr_code_scanner`)
2. Extract the token string from the scanned QR data
3. Call `POST /api/v1/checkin` with `{ "token": "<scanned_value>" }`
4. Handle success/error responses and show appropriate UI feedback

### 7.4 Token Characteristics

- 64 characters long, generated using `Str::random(64)` (alphanumeric)
- Stored on the `events` table in the `checkin_token` column
- Only one active token per event at any time (regenerating replaces it)
- Tokens do not expire on their own -- the organizer controls availability via the `is_active` flag on the event

---

## 8. Error Handling Guide

### 8.1 Standard Error Envelope

All error responses follow this structure:

```json
{
  "success": false,
  "message": "Human-readable error description",
  "errors": {
    "field_name": ["Specific validation error for this field"]
  }
}
```

The `errors` field is only present for validation errors (422). Other error responses only include `success` and `message`.

### 8.2 Complete Error Code Reference

#### Authentication Errors

| Status | Endpoint | Message | Meaning |
|--------|----------|---------|---------|
| 401 | Any protected route | `"Unauthenticated."` | Missing or invalid Bearer token |
| 422 | `POST /auth/register/attendee` | `"Validation failed"` | Invalid email, weak password, or duplicate email |

#### Check-in Errors

| Status | Endpoint | Message | Meaning |
|--------|----------|---------|---------|
| 403 | `POST /events/{id}/generate-qr` | `"You are not authorized to generate a QR token for this event."` | Authenticated user is not the event owner |
| 404 | `POST /checkin` | `"Invalid check-in token."` | Token does not match any event |
| 409 | `POST /checkin` | `"You have already checked in to this event."` | Duplicate check-in attempt |
| 422 | `POST /checkin` | `"This event is not currently accepting check-ins."` | Event `is_active` is `false` |
| 422 | `POST /checkin` | Validation errors | Token field missing or too long |

#### Challenge Management Errors

| Status | Endpoint | Message | Meaning |
|--------|----------|---------|---------|
| 403 | `POST /events/{id}/challenges` | `"You are not authorized to create challenges for this event."` | Not the event owner |
| 403 | `PUT /challenges/{id}` | `"You are not authorized to update this challenge."` | Not the event owner, or attempting to update a system challenge |
| 403 | `DELETE /challenges/{id}` | `"You are not authorized to delete this challenge."` | Not the event owner, or attempting to delete a system challenge |
| 404 | `PUT /challenges/{id}` | (Laravel default) | Challenge UUID not found |
| 404 | `DELETE /challenges/{id}` | (Laravel default) | Challenge UUID not found |
| 422 | `POST /events/{id}/challenges` | Validation errors | Name too short/long, invalid difficulty, points out of range |

#### Challenge Completion Errors

| Status | Endpoint | Message | Meaning |
|--------|----------|---------|---------|
| 422 | `POST /challenges/initiate` | `"You must be checked in to the event to initiate a challenge."` | Challenger has no check-in for this event |
| 422 | `POST /challenges/initiate` | `"The verifier must be checked in to the event."` | Verifier has no check-in for this event |
| 422 | `POST /challenges/initiate` | `"You cannot challenge yourself."` | `verifier_profile_id` equals the authenticated user's profile ID |
| 422 | `POST /challenges/initiate` | Validation errors | Missing required fields, invalid UUIDs, non-existent foreign keys |
| 409 | `POST /challenges/initiate` | `"This challenge has already been initiated between these two attendees."` | Duplicate challenge/event/pair combination |
| 409 | `POST /challenges/initiate` | `"You have reached the maximum number of challenges for this event."` | Challenger exceeded `max_challenges_per_attendee` |
| 403 | `POST /challenge-completions/{id}/verify` | `"You are not the designated verifier for this challenge."` | Authenticated user is not the completion's verifier |
| 403 | `POST /challenge-completions/{id}/reject` | `"You are not the designated verifier for this challenge."` | Authenticated user is not the completion's verifier |
| 409 | `POST /challenge-completions/{id}/verify` | `"This challenge completion has already been processed."` | Status is already `verified` or `rejected` |
| 409 | `POST /challenge-completions/{id}/reject` | `"This challenge completion has already been processed."` | Status is already `verified` or `rejected` |
| 404 | `POST /challenge-completions/{id}/verify` | (Laravel default) | Completion UUID not found |
| 404 | `POST /challenge-completions/{id}/reject` | (Laravel default) | Completion UUID not found |

### 8.3 HTTP Status Code Summary

| Status | Meaning |
|--------|---------|
| 200 | Success |
| 201 | Resource created successfully |
| 401 | Unauthenticated -- missing or expired token |
| 403 | Forbidden -- user lacks permission for this action |
| 404 | Resource not found |
| 409 | Conflict -- duplicate record or already-processed state |
| 422 | Validation failed or business rule violation |

---

## Appendix: Endpoint Summary Table

| Method | URL | Auth | Description |
|--------|-----|------|-------------|
| `POST` | `/api/v1/auth/register/attendee` | No | Register attendee account |
| `POST` | `/api/v1/auth/login` | No | Login (any user type) |
| `POST` | `/api/v1/events/{event}/generate-qr` | Yes | Generate QR check-in token (event owner) |
| `POST` | `/api/v1/checkin` | Yes | Check in via QR token |
| `GET` | `/api/v1/events/{event}/checkins` | Yes | List event check-ins |
| `GET` | `/api/v1/events/{event}/challenges` | Yes | List challenges for an event |
| `POST` | `/api/v1/events/{event}/challenges` | Yes | Create custom challenge (event owner) |
| `PUT` | `/api/v1/challenges/{challenge}` | Yes | Update custom challenge (event owner) |
| `DELETE` | `/api/v1/challenges/{challenge}` | Yes | Delete custom challenge (event owner) |
| `POST` | `/api/v1/challenges/initiate` | Yes | Initiate peer-to-peer challenge |
| `POST` | `/api/v1/challenge-completions/{id}/verify` | Yes | Verify a pending challenge |
| `POST` | `/api/v1/challenge-completions/{id}/reject` | Yes | Reject a pending challenge |
| `GET` | `/api/v1/me/challenge-completions` | Yes | List my challenge completions |
