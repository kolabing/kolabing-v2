# Gamification Phase 2 & 3 -- Mobile Implementation Guide

**Last updated:** 2026-02-06
**API Base URL:** `/api/v1/`
**Authentication:** Bearer token via Laravel Sanctum (all endpoints require authentication)
**Prerequisite:** Phase 1 must be implemented first (attendee registration, check-in, challenges, challenge completions)

---

## 1. Overview

Phase 2 and Phase 3 extend the gamification system with rewards, badges, discovery, and competitive features.

### Phase 2 -- Rewards & Competition

| Component | Description |
|-----------|-------------|
| Event Reward CRUD | Organizers define reward pools for their events (name, quantity, probability) |
| Spin-the-Wheel | Attendees spin after verified challenges to win rewards probabilistically |
| Reward Wallet | Attendees view won rewards, generate QR for redemption, organizers confirm |
| Leaderboard | Per-event and global leaderboards ranked by points |

### Phase 3 -- Badges, Discovery & Stats

| Component | Description |
|-----------|-------------|
| Badge System | 9 milestone-based badges auto-awarded by the backend |
| Event Discovery | Map-based nearby event search using GPS coordinates |
| Gamification Stats | Personal stats dashboard and public game card |
| Gamification Notifications | 3 new notification types: badge_awarded, challenge_verified, reward_won |

---

## 2. Complete API Reference -- Phase 2

### 2.1 Event Reward CRUD (Organizer Only)

#### `GET /api/v1/events/{event_id}/rewards`

**Auth required:** Yes

**Description:** List all rewards configured for an event. Any authenticated user can view.

**Success response (200):**

```json
{
  "success": true,
  "data": [
    {
      "id": "uuid-reward",
      "event_id": "uuid-event",
      "name": "Free Coffee",
      "description": "A complimentary coffee at the venue bar",
      "total_quantity": 50,
      "remaining_quantity": 47,
      "probability": 0.3,
      "expires_at": "2026-03-01T23:59:59+00:00",
      "created_at": "2026-02-06T10:00:00+00:00",
      "updated_at": "2026-02-06T10:00:00+00:00"
    }
  ]
}
```

**EventReward fields:**

| Field | Type | Description |
|-------|------|-------------|
| `id` | UUID | Primary key |
| `event_id` | UUID | FK to the event |
| `name` | string | Reward name (2-150 characters) |
| `description` | string\|null | Optional description (max 500 characters) |
| `total_quantity` | integer | Total reward stock |
| `remaining_quantity` | integer | Remaining stock (decremented on spin wins) |
| `probability` | float | Probability of winning (0.0001 to 1.0) |
| `expires_at` | ISO 8601\|null | Optional expiration date |
| `created_at` | ISO 8601 | Record creation timestamp |
| `updated_at` | ISO 8601 | Record update timestamp |

---

#### `POST /api/v1/events/{event_id}/rewards`

**Auth required:** Yes (must be the event owner)

**Request body:**

```json
{
  "name": "Free Coffee",
  "description": "A complimentary coffee at the venue bar",
  "total_quantity": 50,
  "probability": 0.3,
  "expires_at": "2026-03-01T23:59:59Z"
}
```

| Field | Type | Required | Validation |
|-------|------|----------|------------|
| `name` | string | Yes | Min 2, max 150 characters |
| `description` | string | No | Max 500 characters |
| `total_quantity` | integer | Yes | Min 1 |
| `probability` | float | Yes | Between 0.0001 and 1.0 |
| `expires_at` | datetime | No | Must be in the future (`after:now`) |

**Success response (201):**

```json
{
  "success": true,
  "message": "Reward created successfully.",
  "data": {
    "id": "uuid-reward",
    "event_id": "uuid-event",
    "name": "Free Coffee",
    "description": "A complimentary coffee at the venue bar",
    "total_quantity": 50,
    "remaining_quantity": 50,
    "probability": 0.3,
    "expires_at": "2026-03-01T23:59:59+00:00",
    "created_at": "2026-02-06T10:00:00+00:00",
    "updated_at": "2026-02-06T10:00:00+00:00"
  }
}
```

**Error responses:**

| Status | Condition | Message |
|--------|-----------|---------|
| 403 | Not the event owner | `"You are not authorized to create rewards for this event."` |
| 422 | Validation failed | Standard validation errors |

---

#### `PUT /api/v1/event-rewards/{eventReward_id}`

**Auth required:** Yes (must be the event owner)

**Request body:** (all fields optional)

```json
{
  "name": "Premium Coffee",
  "total_quantity": 100,
  "probability": 0.2
}
```

| Field | Type | Required | Validation |
|-------|------|----------|------------|
| `name` | string | No | Min 2, max 150 characters |
| `description` | string | No | Max 500 characters |
| `total_quantity` | integer | No | Min 1 |
| `probability` | float | No | Between 0.0001 and 1.0 |
| `expires_at` | datetime | No | Must be in the future (`after:now`) |

**Success response (200):**

```json
{
  "success": true,
  "message": "Reward updated successfully.",
  "data": {
    "id": "uuid-reward",
    "event_id": "uuid-event",
    "name": "Premium Coffee",
    "description": "A complimentary coffee at the venue bar",
    "total_quantity": 100,
    "remaining_quantity": 50,
    "probability": 0.2,
    "expires_at": "2026-03-01T23:59:59+00:00",
    "created_at": "2026-02-06T10:00:00+00:00",
    "updated_at": "2026-02-06T12:00:00+00:00"
  }
}
```

**Error responses:**

| Status | Condition | Message |
|--------|-----------|---------|
| 403 | Not the event owner | `"You are not authorized to update this reward."` |
| 422 | Validation failed | Standard validation errors |

---

#### `DELETE /api/v1/event-rewards/{eventReward_id}`

**Auth required:** Yes (must be the event owner)

**Request body:** None

**Success response (200):**

```json
{
  "success": true,
  "message": "Reward deleted successfully."
}
```

**Error responses:**

| Status | Condition | Message |
|--------|-----------|---------|
| 403 | Not the event owner | `"You are not authorized to delete this reward."` |
| 409 | Reward has been claimed | `"Cannot delete a reward that has existing claims."` |

---

### 2.2 Spin the Wheel

#### `POST /api/v1/rewards/spin`

**Auth required:** Yes

**Description:** After a challenge completion is verified, the challenger can spin the wheel to try winning a reward from the event's reward pool. The outcome is probabilistic.

**Request body:**

```json
{
  "challenge_completion_id": "uuid-completion"
}
```

| Field | Type | Required | Validation |
|-------|------|----------|------------|
| `challenge_completion_id` | UUID | Yes | Must exist in `challenge_completions` table |

**Success response -- Won (200):**

```json
{
  "success": true,
  "message": "Congratulations! You won a reward!",
  "data": {
    "won": true,
    "reward_claim": {
      "id": "uuid-claim",
      "event_reward": {
        "id": "uuid-reward",
        "event_id": "uuid-event",
        "name": "Free Coffee",
        "description": "A complimentary coffee at the venue bar",
        "total_quantity": 50,
        "remaining_quantity": 46,
        "probability": 0.3,
        "expires_at": "2026-03-01T23:59:59+00:00",
        "created_at": "2026-02-06T10:00:00+00:00",
        "updated_at": "2026-02-06T14:00:00+00:00"
      },
      "profile_id": "uuid-profile",
      "status": "available",
      "won_at": "2026-02-06T14:30:00+00:00",
      "redeemed_at": null,
      "redeem_token": null,
      "created_at": "2026-02-06T14:30:00+00:00"
    }
  }
}
```

**Success response -- Lost (200):**

```json
{
  "success": true,
  "message": "Better luck next time!",
  "data": {
    "won": false,
    "reward_claim": null
  }
}
```

**Error responses:**

| Status | Condition | Message |
|--------|-----------|---------|
| 422 | Challenge not verified | `"Challenge completion must be verified before spinning."` |
| 422 | Not the challenger | `"You are not the challenger for this completion."` |
| 409 | Already spun | `"You have already spun for this challenge completion."` |
| 422 | Validation failed | Standard validation errors |

**Business logic:**
- One spin per challenge completion per user (enforced server-side)
- The spin walks through available rewards accumulating probability until a winner is found or all probabilities exhausted
- If no rewards remain in stock or all are expired, returns `won: false`
- Database locking prevents race conditions when multiple users spin simultaneously
- Winning a reward triggers a `reward_won` notification and checks for badge milestones

---

### 2.3 Reward Wallet

#### `GET /api/v1/me/rewards`

**Auth required:** Yes

**Description:** List the authenticated user's won reward claims, paginated, with associated event reward and event data.

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
    "rewards": [
      {
        "id": "uuid-claim",
        "event_reward": {
          "id": "uuid-reward",
          "event_id": "uuid-event",
          "name": "Free Coffee",
          "description": "A complimentary coffee at the venue bar",
          "total_quantity": 50,
          "remaining_quantity": 46,
          "probability": 0.3,
          "expires_at": "2026-03-01T23:59:59+00:00",
          "created_at": "2026-02-06T10:00:00+00:00",
          "updated_at": "2026-02-06T14:00:00+00:00"
        },
        "profile_id": "uuid-profile",
        "status": "available",
        "won_at": "2026-02-06T14:30:00+00:00",
        "redeemed_at": null,
        "redeem_token": null,
        "created_at": "2026-02-06T14:30:00+00:00"
      }
    ],
    "pagination": {
      "current_page": 1,
      "total_pages": 1,
      "total_count": 3,
      "per_page": 10
    }
  }
}
```

**RewardClaim fields:**

| Field | Type | Description |
|-------|------|-------------|
| `id` | UUID | Primary key |
| `event_reward` | object | Nested EventReward object (always loaded) |
| `profile_id` | UUID | Owner profile ID |
| `status` | string | `"available"`, `"redeemed"`, or `"expired"` |
| `won_at` | ISO 8601 | When the reward was won |
| `redeemed_at` | ISO 8601\|null | When the reward was redeemed (null if not yet) |
| `redeem_token` | string\|null | 64-character token for QR redemption (null until generated) |
| `created_at` | ISO 8601 | Record creation timestamp |

---

#### `POST /api/v1/reward-claims/{rewardClaim_id}/generate-redeem-qr`

**Auth required:** Yes (must be the reward claim owner)

**Description:** Generate a unique 64-character token for this reward claim. The attendee displays this token as a QR code, which the organizer scans to confirm redemption.

**Request body:** None

**Success response (200):**

```json
{
  "success": true,
  "message": "Redeem QR generated successfully.",
  "data": {
    "id": "uuid-claim",
    "event_reward": {
      "id": "uuid-reward",
      "event_id": "uuid-event",
      "name": "Free Coffee",
      "description": "A complimentary coffee at the venue bar",
      "total_quantity": 50,
      "remaining_quantity": 46,
      "probability": 0.3,
      "expires_at": "2026-03-01T23:59:59+00:00",
      "created_at": "2026-02-06T10:00:00+00:00",
      "updated_at": "2026-02-06T14:00:00+00:00"
    },
    "profile_id": "uuid-profile",
    "status": "available",
    "won_at": "2026-02-06T14:30:00+00:00",
    "redeemed_at": null,
    "redeem_token": "aB3x...64-character-random-string...",
    "created_at": "2026-02-06T14:30:00+00:00"
  }
}
```

**Error responses:**

| Status | Condition | Message |
|--------|-----------|---------|
| 403 | Not the claim owner | `"This reward claim does not belong to you."` |
| 409 | Not available status | `"This reward claim is not available for redemption."` |
| 409 | Reward expired | `"This reward has expired."` |

**Notes:**
- Each call generates a new token, replacing the previous one
- Token is a 64-character alphanumeric string (generated via `Str::random(64)`)
- The token is stored in the `redeem_token` column on the `reward_claims` table

---

#### `POST /api/v1/reward-claims/confirm-redeem`

**Auth required:** Yes (must be the event owner/organizer)

**Description:** The organizer scans the attendee's QR code and sends the token to this endpoint to confirm the reward has been physically redeemed.

**Request body:**

```json
{
  "token": "aB3x...64-character-token..."
}
```

| Field | Type | Required | Validation |
|-------|------|----------|------------|
| `token` | string | Yes | Exactly 64 characters |

**Success response (200):**

```json
{
  "success": true,
  "message": "Reward redeemed successfully.",
  "data": {
    "id": "uuid-claim",
    "event_reward": {
      "id": "uuid-reward",
      "event_id": "uuid-event",
      "name": "Free Coffee",
      "description": "A complimentary coffee at the venue bar",
      "total_quantity": 50,
      "remaining_quantity": 46,
      "probability": 0.3,
      "expires_at": "2026-03-01T23:59:59+00:00",
      "created_at": "2026-02-06T10:00:00+00:00",
      "updated_at": "2026-02-06T14:00:00+00:00"
    },
    "profile_id": "uuid-profile",
    "status": "redeemed",
    "won_at": "2026-02-06T14:30:00+00:00",
    "redeemed_at": "2026-02-06T15:00:00+00:00",
    "redeem_token": null,
    "created_at": "2026-02-06T14:30:00+00:00"
  }
}
```

**Error responses:**

| Status | Condition | Message |
|--------|-----------|---------|
| 404 | Invalid token | `"Invalid redeem token."` |
| 403 | Not the event owner | `"You are not the owner of this event."` |
| 409 | Not available | `"This reward claim is not available for redemption."` |

**After successful redemption:**
- Status changes from `"available"` to `"redeemed"`
- `redeemed_at` is set to the current timestamp
- `redeem_token` is cleared (set to null)

---

### 2.4 Leaderboard

#### `GET /api/v1/events/{event_id}/leaderboard`

**Auth required:** Yes

**Description:** Get the leaderboard for a specific event, ranked by verified challenge completion points. Includes the authenticated user's own rank.

**Query parameters:**

| Param | Type | Default | Max | Description |
|-------|------|---------|-----|-------------|
| `limit` | integer | 50 | 100 | Maximum entries to return |

**Success response (200):**

```json
{
  "success": true,
  "data": {
    "leaderboard": [
      {
        "profile_id": "uuid-profile-1",
        "display_name": "user1@example.com",
        "profile_photo": "https://example.com/avatar1.jpg",
        "total_points": 150,
        "rank": 1
      },
      {
        "profile_id": "uuid-profile-2",
        "display_name": "user2@example.com",
        "profile_photo": null,
        "total_points": 100,
        "rank": 2
      },
      {
        "profile_id": "uuid-profile-3",
        "display_name": "user3@example.com",
        "profile_photo": null,
        "total_points": 100,
        "rank": 2
      }
    ],
    "my_rank": {
      "profile_id": "uuid-my-profile",
      "total_points": 100,
      "rank": 2
    }
  }
}
```

**Leaderboard entry fields:**

| Field | Type | Description |
|-------|------|-------------|
| `profile_id` | UUID | User's profile ID |
| `display_name` | string | User's email (fallback: "Unknown") |
| `profile_photo` | string\|null | Avatar URL |
| `total_points` | integer | Sum of verified challenge points for this event |
| `rank` | integer | Rank position (tied scores share the same rank) |

**my_rank:**
- Returns `null` if the user has 0 points in this event
- Uses dense ranking (tied scores = same rank)

---

#### `GET /api/v1/leaderboard/global`

**Auth required:** Yes

**Description:** Get the global leaderboard based on all-time total points across all events.

**Query parameters:**

| Param | Type | Default | Max | Description |
|-------|------|---------|-----|-------------|
| `limit` | integer | 50 | 100 | Maximum entries to return |

**Success response (200):**

```json
{
  "success": true,
  "data": {
    "leaderboard": [
      {
        "profile_id": "uuid-profile-1",
        "display_name": "topuser@example.com",
        "profile_photo": "https://example.com/avatar.jpg",
        "total_points": 1200,
        "rank": 1
      }
    ],
    "my_rank": {
      "profile_id": "uuid-my-profile",
      "total_points": 350,
      "rank": 5
    }
  }
}
```

**Notes:**
- Global leaderboard reads from `attendee_profiles.total_points` (pre-aggregated)
- Only users with `total_points > 0` appear on the leaderboard
- `my_rank` returns `null` if the authenticated user has 0 total points

---

## 3. Complete API Reference -- Phase 3

### 3.1 Badge System

#### `GET /api/v1/badges`

**Auth required:** Yes

**Description:** List all 9 system badges. These are seeded in the database and cannot be created or modified by users.

**Success response (200):**

```json
{
  "success": true,
  "data": {
    "badges": [
      {
        "id": "uuid-badge",
        "name": "Ilk Adim",
        "description": "Ilk etkinlige check-in yap",
        "icon": "badge-first-checkin",
        "milestone_type": "first_checkin",
        "milestone_value": 1
      },
      {
        "id": "uuid-badge-2",
        "name": "Challenge Baslangic",
        "description": "Ilk challenge'ini tamamla",
        "icon": "badge-first-challenge",
        "milestone_type": "first_challenge",
        "milestone_value": 1
      }
    ]
  }
}
```

**Badge fields:**

| Field | Type | Description |
|-------|------|-------------|
| `id` | UUID | Primary key |
| `name` | string | Badge display name (Turkish) |
| `description` | string | Human-readable description of the milestone |
| `icon` | string | Icon identifier for the mobile app |
| `milestone_type` | string | Enum key identifying the milestone trigger (see table below) |
| `milestone_value` | integer | Threshold value required to earn this badge |

**System Badges (9 total):**

| milestone_type | Name | Description | milestone_value | Trigger |
|----------------|------|-------------|-----------------|---------|
| `first_checkin` | Ilk Adim | First event check-in | 1 | After any check-in |
| `first_challenge` | Challenge Baslangic | First challenge completed | 1 | After first verified challenge |
| `social_butterfly_10` | Sosyal Kelebek | Challenged 10 different people | 10 | Unique verifier count |
| `challenges_completed_50` | Challenge Master | 50 challenges completed | 50 | total_challenges_completed |
| `events_attended_10` | Etkinlik Gurusu | Attended 10 events | 10 | total_events_attended |
| `points_500` | Puan Avcisi | Earned 500 total points | 500 | total_points |
| `points_2000` | Efsane | Earned 2000 total points | 2000 | total_points |
| `rewards_won_10` | Odul Koleksiyoncusu | Won 10 rewards | 10 | RewardClaim count |
| `events_attended_5` | Sadik Katilimci | Attended 5 events | 5 | total_events_attended |

---

#### `GET /api/v1/me/badges`

**Auth required:** Yes

**Description:** List the authenticated user's earned badges, sorted by most recently awarded.

**Success response (200):**

```json
{
  "success": true,
  "data": {
    "badges": [
      {
        "id": "uuid-award",
        "badge": {
          "id": "uuid-badge",
          "name": "Ilk Adim",
          "description": "Ilk etkinlige check-in yap",
          "icon": "badge-first-checkin",
          "milestone_type": "first_checkin",
          "milestone_value": 1
        },
        "awarded_at": "2026-02-06T14:30:00+00:00"
      }
    ]
  }
}
```

**BadgeAward fields:**

| Field | Type | Description |
|-------|------|-------------|
| `id` | UUID | Primary key of the badge_awards record |
| `badge` | object | Full Badge object (see above) |
| `awarded_at` | ISO 8601 | When the badge was awarded |

**Notes:**
- Badges are auto-awarded by the backend -- the mobile app never requests badge awarding directly
- Badge checks happen after: check-in, challenge verification, and spin-the-wheel
- A user can only earn each badge once (enforced by unique constraint on `[badge_id, profile_id]`)

---

### 3.2 Event Discovery

#### `GET /api/v1/events/discover`

**Auth required:** Yes

**Description:** Find active events near a given GPS coordinate within a configurable radius. Uses the Haversine formula for accurate distance calculation. Results include distance from the user's location.

**Query parameters:**

| Param | Type | Required | Default | Validation | Description |
|-------|------|----------|---------|------------|-------------|
| `lat` | float | Yes | -- | -90 to 90 | User's latitude |
| `lng` | float | Yes | -- | -180 to 180 | User's longitude |
| `radius` | float | No | 50 | 1 to 200 (km) | Search radius in kilometers |
| `limit` | integer | No | 10 | 1 to 50 | Results per page |
| `page` | integer | No | 1 | -- | Page number |

**Success response (200):**

```json
{
  "success": true,
  "data": {
    "events": [
      {
        "id": "uuid-event",
        "name": "Summer Music Festival",
        "partner_name": "Barcelona Events Co.",
        "partner_type": "business",
        "date": "2026-03-15",
        "attendee_count": 150,
        "location_lat": 41.3851,
        "location_lng": 2.1734,
        "address": "Parc de la Ciutadella, Barcelona",
        "photos": [],
        "created_at": "2026-02-01T10:00:00+00:00",
        "updated_at": "2026-02-05T12:00:00+00:00",
        "distance_km": 2.34
      },
      {
        "id": "uuid-event-2",
        "name": "Food Truck Rally",
        "partner_name": "Street Food BCN",
        "partner_type": "community",
        "date": "2026-03-20",
        "attendee_count": 75,
        "location_lat": 41.3902,
        "location_lng": 2.1540,
        "address": "Placa de Catalunya, Barcelona",
        "photos": [],
        "created_at": "2026-02-02T10:00:00+00:00",
        "updated_at": "2026-02-04T12:00:00+00:00",
        "distance_km": 5.12
      }
    ],
    "pagination": {
      "current_page": 1,
      "total_pages": 2,
      "total_count": 15,
      "per_page": 10
    }
  }
}
```

**Event fields (same as EventResource + distance):**

| Field | Type | Description |
|-------|------|-------------|
| `id` | UUID | Primary key |
| `name` | string | Event name |
| `partner_name` | string | Organizer name |
| `partner_type` | string | `"business"` or `"community"` |
| `date` | string | Event date (YYYY-MM-DD format) |
| `attendee_count` | integer | Number of attendees |
| `location_lat` | float | Event latitude |
| `location_lng` | float | Event longitude |
| `address` | string\|null | Human-readable address |
| `photos` | array | Event photos (if loaded) |
| `distance_km` | float | Distance from the provided coordinates in km (rounded to 2 decimals) |
| `created_at` | ISO 8601 | Record creation timestamp |
| `updated_at` | ISO 8601 | Record update timestamp |

**Error responses:**

| Status | Condition | Message |
|--------|-----------|---------|
| 422 | Missing lat/lng | Standard validation errors |
| 422 | Invalid coordinate range | Standard validation errors |
| 422 | Radius out of range | Standard validation errors |

**Notes:**
- Only events with `is_active = true` and valid lat/lng coordinates are returned
- Results are sorted by distance (nearest first)
- The `distance_km` field is computed server-side and only appears in discovery results
- The mobile app should request location permissions and send the device's GPS coordinates

---

### 3.3 Gamification Stats

#### `GET /api/v1/me/gamification-stats`

**Auth required:** Yes

**Description:** Get comprehensive gamification statistics for the authenticated user.

**Success response (200):**

```json
{
  "success": true,
  "data": {
    "total_points": 350,
    "total_challenges_completed": 12,
    "total_events_attended": 5,
    "global_rank": null,
    "badges_count": 3,
    "rewards_count": 2
  }
}
```

**Stats fields:**

| Field | Type | Description |
|-------|------|-------------|
| `total_points` | integer | Accumulated points from verified challenges |
| `total_challenges_completed` | integer | Total verified challenges |
| `total_events_attended` | integer | Total events checked in to |
| `global_rank` | integer\|null | Global rank (null if not computed or user has no attendee profile) |
| `badges_count` | integer | Number of badges earned |
| `rewards_count` | integer | Number of rewards won (all statuses) |

**Notes:**
- Returns all zeros if the user has no `attendee_profiles` record
- `badges_count` and `rewards_count` are computed live from the database

---

#### `GET /api/v1/profiles/{profile_id}/game-card`

**Auth required:** Yes

**Description:** Get the public game card for any profile. This is the shareable view of a user's gamification progress.

**Success response (200):**

```json
{
  "success": true,
  "data": {
    "profile": {
      "id": "uuid-profile",
      "email": "player@example.com",
      "avatar_url": "https://example.com/avatar.jpg",
      "user_type": "attendee"
    },
    "stats": {
      "total_points": 350,
      "total_challenges_completed": 12,
      "total_events_attended": 5,
      "global_rank": null,
      "badges_count": 3,
      "rewards_count": 2
    },
    "recent_badges": [
      {
        "id": "uuid-award",
        "badge": {
          "id": "uuid-badge",
          "name": "Ilk Adim",
          "description": "Ilk etkinlige check-in yap",
          "icon": "badge-first-checkin",
          "milestone_type": "first_checkin",
          "milestone_value": 1
        },
        "awarded_at": "2026-02-06T14:30:00+00:00"
      }
    ]
  }
}
```

**Game card fields:**

| Field | Type | Description |
|-------|------|-------------|
| `profile` | object | Basic profile info (id, email, avatar_url, user_type) |
| `stats` | object | Same stats object as `/me/gamification-stats` |
| `recent_badges` | array | Last 5 badges awarded, newest first (BadgeAward objects with nested Badge) |

---

## 4. Gamification Notifications

Phase 2 and Phase 3 add 3 new notification types to the existing in-app notification system. These are delivered via the existing notification API:

- `GET /api/v1/me/notifications` -- List all notifications (includes new types)
- `GET /api/v1/me/notifications/unread-count` -- Unread count
- `POST /api/v1/me/notifications/{id}/read` -- Mark as read
- `POST /api/v1/me/notifications/read-all` -- Mark all as read

### 4.1 New Notification Types

| Type Value | Trigger | Title | Body Example | target_id | target_type |
|------------|---------|-------|-------------|-----------|-------------|
| `badge_awarded` | Badge milestone reached | `"Badge Earned!"` | `"You earned the \"Ilk Adim\" badge!"` | badge UUID | `"badge"` |
| `challenge_verified` | Challenge completion verified | `"Challenge Verified!"` | `"Your challenge \"Take a selfie\" has been verified! You earned 5 points."` | challenge_completion UUID | `"challenge_completion"` |
| `reward_won` | Spin-the-wheel win | `"Reward Won!"` | `"You won \"Free Coffee\"!"` | reward_claim UUID | `"reward_claim"` |

### 4.2 When Notifications Are Sent

1. **`badge_awarded`**: Sent after any action that triggers badge milestone checks:
   - After check-in (may trigger `first_checkin`, `events_attended_5`, `events_attended_10`)
   - After challenge verification (may trigger `first_challenge`, `social_butterfly_10`, `challenges_completed_50`, `points_500`, `points_2000`)
   - After spin-the-wheel win (may trigger `rewards_won_10`)

2. **`challenge_verified`**: Sent to the challenger when a verifier approves their challenge completion.

3. **`reward_won`**: Sent to the attendee when the spin-the-wheel results in a win.

### 4.3 Notification Object in API Response

```json
{
  "id": "uuid-notification",
  "type": "badge_awarded",
  "title": "Badge Earned!",
  "body": "You earned the \"Ilk Adim\" badge!",
  "target_id": "uuid-badge",
  "target_type": "badge",
  "read_at": null,
  "created_at": "2026-02-06T14:30:00+00:00"
}
```

---

## 5. Suggested Dart Models

### 5.1 EventReward

```dart
class EventReward {
  final String id;
  final String eventId;
  final String name;
  final String? description;
  final int totalQuantity;
  final int remainingQuantity;
  final double probability;
  final DateTime? expiresAt;
  final DateTime createdAt;
  final DateTime updatedAt;

  EventReward({
    required this.id,
    required this.eventId,
    required this.name,
    this.description,
    required this.totalQuantity,
    required this.remainingQuantity,
    required this.probability,
    this.expiresAt,
    required this.createdAt,
    required this.updatedAt,
  });

  factory EventReward.fromJson(Map<String, dynamic> json) {
    return EventReward(
      id: json['id'] as String,
      eventId: json['event_id'] as String,
      name: json['name'] as String,
      description: json['description'] as String?,
      totalQuantity: json['total_quantity'] as int,
      remainingQuantity: json['remaining_quantity'] as int,
      probability: (json['probability'] as num).toDouble(),
      expiresAt: json['expires_at'] != null
          ? DateTime.parse(json['expires_at'] as String)
          : null,
      createdAt: DateTime.parse(json['created_at'] as String),
      updatedAt: DateTime.parse(json['updated_at'] as String),
    );
  }

  bool get isExpired =>
      expiresAt != null && expiresAt!.isBefore(DateTime.now());

  bool get isInStock => remainingQuantity > 0;
}
```

### 5.2 RewardClaimStatus

```dart
enum RewardClaimStatus { available, redeemed, expired }
```

### 5.3 RewardClaim

```dart
class RewardClaim {
  final String id;
  final EventReward? eventReward;
  final String profileId;
  final RewardClaimStatus status;
  final DateTime wonAt;
  final DateTime? redeemedAt;
  final String? redeemToken;
  final DateTime createdAt;

  RewardClaim({
    required this.id,
    this.eventReward,
    required this.profileId,
    required this.status,
    required this.wonAt,
    this.redeemedAt,
    this.redeemToken,
    required this.createdAt,
  });

  factory RewardClaim.fromJson(Map<String, dynamic> json) {
    return RewardClaim(
      id: json['id'] as String,
      eventReward: json['event_reward'] != null
          ? EventReward.fromJson(
              json['event_reward'] as Map<String, dynamic>)
          : null,
      profileId: json['profile_id'] as String,
      status: RewardClaimStatus.values.firstWhere(
        (e) => e.name == json['status'],
      ),
      wonAt: DateTime.parse(json['won_at'] as String),
      redeemedAt: json['redeemed_at'] != null
          ? DateTime.parse(json['redeemed_at'] as String)
          : null,
      redeemToken: json['redeem_token'] as String?,
      createdAt: DateTime.parse(json['created_at'] as String),
    );
  }

  bool get isAvailable => status == RewardClaimStatus.available;
  bool get isRedeemed => status == RewardClaimStatus.redeemed;
  bool get isExpired => status == RewardClaimStatus.expired;
  bool get hasRedeemToken => redeemToken != null;
}
```

### 5.4 SpinResult

```dart
class SpinResult {
  final bool won;
  final RewardClaim? rewardClaim;

  SpinResult({
    required this.won,
    this.rewardClaim,
  });

  factory SpinResult.fromJson(Map<String, dynamic> json) {
    return SpinResult(
      won: json['won'] as bool,
      rewardClaim: json['reward_claim'] != null
          ? RewardClaim.fromJson(
              json['reward_claim'] as Map<String, dynamic>)
          : null,
    );
  }
}
```

### 5.5 LeaderboardEntry

```dart
class LeaderboardEntry {
  final String profileId;
  final String displayName;
  final String? profilePhoto;
  final int totalPoints;
  final int rank;

  LeaderboardEntry({
    required this.profileId,
    required this.displayName,
    this.profilePhoto,
    required this.totalPoints,
    required this.rank,
  });

  factory LeaderboardEntry.fromJson(Map<String, dynamic> json) {
    return LeaderboardEntry(
      profileId: json['profile_id'] as String,
      displayName: json['display_name'] as String,
      profilePhoto: json['profile_photo'] as String?,
      totalPoints: json['total_points'] as int,
      rank: json['rank'] as int,
    );
  }
}
```

### 5.6 MyRank

```dart
class MyRank {
  final String profileId;
  final int totalPoints;
  final int rank;

  MyRank({
    required this.profileId,
    required this.totalPoints,
    required this.rank,
  });

  factory MyRank.fromJson(Map<String, dynamic> json) {
    return MyRank(
      profileId: json['profile_id'] as String,
      totalPoints: json['total_points'] as int,
      rank: json['rank'] as int,
    );
  }
}
```

### 5.7 Badge

```dart
class Badge {
  final String id;
  final String name;
  final String description;
  final String icon;
  final String milestoneType;
  final int milestoneValue;

  Badge({
    required this.id,
    required this.name,
    required this.description,
    required this.icon,
    required this.milestoneType,
    required this.milestoneValue,
  });

  factory Badge.fromJson(Map<String, dynamic> json) {
    return Badge(
      id: json['id'] as String,
      name: json['name'] as String,
      description: json['description'] as String,
      icon: json['icon'] as String,
      milestoneType: json['milestone_type'] as String,
      milestoneValue: json['milestone_value'] as int,
    );
  }
}
```

### 5.8 BadgeAward

```dart
class BadgeAward {
  final String id;
  final Badge? badge;
  final DateTime awardedAt;

  BadgeAward({
    required this.id,
    this.badge,
    required this.awardedAt,
  });

  factory BadgeAward.fromJson(Map<String, dynamic> json) {
    return BadgeAward(
      id: json['id'] as String,
      badge: json['badge'] != null
          ? Badge.fromJson(json['badge'] as Map<String, dynamic>)
          : null,
      awardedAt: DateTime.parse(json['awarded_at'] as String),
    );
  }
}
```

### 5.9 GamificationStats

```dart
class GamificationStats {
  final int totalPoints;
  final int totalChallengesCompleted;
  final int totalEventsAttended;
  final int? globalRank;
  final int badgesCount;
  final int rewardsCount;

  GamificationStats({
    required this.totalPoints,
    required this.totalChallengesCompleted,
    required this.totalEventsAttended,
    this.globalRank,
    required this.badgesCount,
    required this.rewardsCount,
  });

  factory GamificationStats.fromJson(Map<String, dynamic> json) {
    return GamificationStats(
      totalPoints: json['total_points'] as int,
      totalChallengesCompleted: json['total_challenges_completed'] as int,
      totalEventsAttended: json['total_events_attended'] as int,
      globalRank: json['global_rank'] as int?,
      badgesCount: json['badges_count'] as int,
      rewardsCount: json['rewards_count'] as int,
    );
  }
}
```

### 5.10 GameCard

```dart
class GameCardProfile {
  final String id;
  final String email;
  final String? avatarUrl;
  final String userType;

  GameCardProfile({
    required this.id,
    required this.email,
    this.avatarUrl,
    required this.userType,
  });

  factory GameCardProfile.fromJson(Map<String, dynamic> json) {
    return GameCardProfile(
      id: json['id'] as String,
      email: json['email'] as String,
      avatarUrl: json['avatar_url'] as String?,
      userType: json['user_type'] as String,
    );
  }
}

class GameCard {
  final GameCardProfile profile;
  final GamificationStats stats;
  final List<BadgeAward> recentBadges;

  GameCard({
    required this.profile,
    required this.stats,
    required this.recentBadges,
  });

  factory GameCard.fromJson(Map<String, dynamic> json) {
    return GameCard(
      profile: GameCardProfile.fromJson(
          json['profile'] as Map<String, dynamic>),
      stats: GamificationStats.fromJson(
          json['stats'] as Map<String, dynamic>),
      recentBadges: (json['recent_badges'] as List<dynamic>)
          .map((e) => BadgeAward.fromJson(e as Map<String, dynamic>))
          .toList(),
    );
  }
}
```

### 5.11 DiscoveredEvent (extends Event)

```dart
class DiscoveredEvent {
  final String id;
  final String name;
  final String partnerName;
  final String partnerType;
  final String date;
  final int attendeeCount;
  final double locationLat;
  final double locationLng;
  final String? address;
  final double distanceKm;
  final DateTime createdAt;
  final DateTime updatedAt;

  DiscoveredEvent({
    required this.id,
    required this.name,
    required this.partnerName,
    required this.partnerType,
    required this.date,
    required this.attendeeCount,
    required this.locationLat,
    required this.locationLng,
    this.address,
    required this.distanceKm,
    required this.createdAt,
    required this.updatedAt,
  });

  factory DiscoveredEvent.fromJson(Map<String, dynamic> json) {
    return DiscoveredEvent(
      id: json['id'] as String,
      name: json['name'] as String,
      partnerName: json['partner_name'] as String,
      partnerType: json['partner_type'] as String,
      date: json['date'] as String,
      attendeeCount: json['attendee_count'] as int,
      locationLat: (json['location_lat'] as num).toDouble(),
      locationLng: (json['location_lng'] as num).toDouble(),
      address: json['address'] as String?,
      distanceKm: (json['distance_km'] as num).toDouble(),
      createdAt: DateTime.parse(json['created_at'] as String),
      updatedAt: DateTime.parse(json['updated_at'] as String),
    );
  }
}
```

---

## 6. Business Rules

### 6.1 Reward Probability

- Each reward has an independent `probability` between 0.0001 and 1.0
- The spin algorithm walks through available rewards, accumulating probabilities
- A random number between 0 and 1 determines the outcome
- If cumulative probability is exceeded before a match, the user loses
- Multiple rewards at an event can have different probabilities (they are NOT required to sum to 1.0)

### 6.2 Spin Constraints

- One spin per challenge completion per user (cannot re-spin the same completion)
- The challenge completion must be in `verified` status
- Only the challenger (not the verifier) can spin
- Rewards must have `remaining_quantity > 0` and not be expired

### 6.3 Redemption Flow

1. Attendee wins a reward via spin-the-wheel (status: `available`)
2. Attendee navigates to reward wallet and taps "Redeem"
3. App calls `generate-redeem-qr` endpoint to get a 64-char token
4. App renders the token as a QR code
5. Organizer scans the QR code with their device
6. Organizer's app calls `confirm-redeem` with the scanned token
7. Status changes to `redeemed`, `redeemed_at` is set, token is cleared

### 6.4 Reward Claim Statuses

| Status | Meaning | Transitions |
|--------|---------|-------------|
| `available` | Won but not yet redeemed | Can become `redeemed` or `expired` |
| `redeemed` | Successfully redeemed at the event | Final state |
| `expired` | The underlying EventReward's `expires_at` has passed | Final state |

### 6.5 Leaderboard Ranking

- Uses dense ranking: tied scores share the same rank number
- Event leaderboard: aggregates `points_earned` from verified `challenge_completions` for that event
- Global leaderboard: reads pre-aggregated `total_points` from `attendee_profiles`
- Only users with points > 0 appear

### 6.6 Badge Auto-Awarding

- Badges are never awarded via API -- all awarding is automatic
- The backend checks milestones after: check-in, challenge verification, spin-the-wheel win
- Each badge can only be awarded once per user
- When awarded, a `badge_awarded` notification is sent
- The mobile app should poll `/me/badges` or listen to notifications for new badge announcements

### 6.7 Event Discovery

- Only events with `is_active = true` and non-null lat/lng appear
- The mobile app must request location permissions
- Default search radius is 50 km, max is 200 km
- Results are sorted by distance (nearest first)

---

## 7. QR Code Implementation Notes -- Reward Redemption

### 7.1 QR Content Format

For reward redemption QR codes, encode the redeem token:

```json
{
  "type": "kolabing_redeem",
  "token": "aB3x...64-character-string..."
}
```

Or simply the raw 64-character token string.

### 7.2 Attendee Side -- QR Generation (Reward Redemption)

1. Navigate to Reward Wallet (`GET /api/v1/me/rewards`)
2. Tap on an `available` reward to view details
3. Tap "Redeem" button
4. Call `POST /api/v1/reward-claims/{id}/generate-redeem-qr`
5. Receive the `redeem_token` from the response
6. Use `qr_flutter` package to render the token as a QR code
7. Display QR code full-screen for the organizer to scan
8. Optionally show a countdown or "waiting for organizer" state

### 7.3 Organizer Side -- QR Scanning (Reward Confirmation)

1. Use `mobile_scanner` or `qr_code_scanner` to scan the attendee's QR
2. Extract the 64-character token
3. Call `POST /api/v1/reward-claims/confirm-redeem` with `{ "token": "<scanned_value>" }`
4. On success, show confirmation with the reward details
5. On error, display the appropriate error message (invalid token, wrong event, etc.)

### 7.4 Redeem Token Characteristics

- 64 characters long, generated using `Str::random(64)` (alphanumeric)
- Stored on the `reward_claims` table in the `redeem_token` column
- Only one active token per reward claim at any time
- Token is cleared (set to null) after successful redemption
- Tokens do not expire on their own (but the underlying reward can expire)

---

## 8. User Flows

### 8.1 Organizer: Setting Up Rewards for an Event

```
1. Create event (Phase 1)
2. Navigate to event management
3. Tap "Add Reward"
4. Fill in: name, description, quantity, probability, optional expiration
5. POST /api/v1/events/{event_id}/rewards
6. Repeat for additional rewards
7. View/edit/delete rewards at any time
```

### 8.2 Attendee: Complete Flow (Check-in to Reward)

```
1. Discover event via map (GET /api/v1/events/discover)
2. Go to event, scan QR to check in (POST /api/v1/checkin)
   -> May earn "Ilk Adim" badge (first check-in)
3. Initiate a challenge with another attendee (POST /api/v1/challenges/initiate)
4. Other attendee verifies (POST /challenge-completions/{id}/verify)
   -> Points awarded, "challenge_verified" notification received
   -> May earn "Challenge Baslangic" badge (first challenge)
5. Spin the wheel (POST /api/v1/rewards/spin)
   -> If won: "reward_won" notification, reward appears in wallet
   -> May earn "Odul Koleksiyoncusu" badge (10 rewards)
6. View wallet (GET /api/v1/me/rewards)
7. Generate QR for redemption (POST /reward-claims/{id}/generate-redeem-qr)
8. Show QR to organizer, organizer confirms (POST /reward-claims/confirm-redeem)
```

### 8.3 Attendee: Checking Stats and Profile

```
1. View personal stats (GET /api/v1/me/gamification-stats)
2. View earned badges (GET /api/v1/me/badges)
3. Check leaderboard position:
   - Event leaderboard: GET /api/v1/events/{event_id}/leaderboard
   - Global leaderboard: GET /api/v1/leaderboard/global
4. Share game card: GET /api/v1/profiles/{profile_id}/game-card
```

---

## 9. Error Handling Guide

### 9.1 Standard Error Envelope

Same as Phase 1:

```json
{
  "success": false,
  "message": "Human-readable error description",
  "errors": {
    "field_name": ["Specific validation error for this field"]
  }
}
```

The `errors` field is only present for validation errors (422).

### 9.2 Complete Error Code Reference -- Phase 2

#### Event Reward Errors

| Status | Endpoint | Message | Meaning |
|--------|----------|---------|---------|
| 403 | `POST /events/{id}/rewards` | `"You are not authorized to create rewards for this event."` | Not event owner |
| 403 | `PUT /event-rewards/{id}` | `"You are not authorized to update this reward."` | Not event owner |
| 403 | `DELETE /event-rewards/{id}` | `"You are not authorized to delete this reward."` | Not event owner |
| 409 | `DELETE /event-rewards/{id}` | `"Cannot delete a reward that has existing claims."` | Reward has been claimed |
| 422 | Create/Update | Validation errors | Invalid name, quantity, probability, or expires_at |

#### Spin-the-Wheel Errors

| Status | Endpoint | Message | Meaning |
|--------|----------|---------|---------|
| 422 | `POST /rewards/spin` | `"Challenge completion must be verified before spinning."` | Completion not verified |
| 422 | `POST /rewards/spin` | `"You are not the challenger for this completion."` | Wrong user |
| 409 | `POST /rewards/spin` | `"You have already spun for this challenge completion."` | Duplicate spin |
| 422 | `POST /rewards/spin` | Validation errors | Invalid UUID or missing field |

#### Reward Wallet Errors

| Status | Endpoint | Message | Meaning |
|--------|----------|---------|---------|
| 403 | `POST /reward-claims/{id}/generate-redeem-qr` | `"This reward claim does not belong to you."` | Not claim owner |
| 409 | `POST /reward-claims/{id}/generate-redeem-qr` | `"This reward claim is not available for redemption."` | Already redeemed or expired |
| 409 | `POST /reward-claims/{id}/generate-redeem-qr` | `"This reward has expired."` | EventReward expired |
| 404 | `POST /reward-claims/confirm-redeem` | `"Invalid redeem token."` | Token not found |
| 403 | `POST /reward-claims/confirm-redeem` | `"You are not the owner of this event."` | Not event organizer |
| 409 | `POST /reward-claims/confirm-redeem` | `"This reward claim is not available for redemption."` | Already redeemed |

### 9.3 Complete Error Code Reference -- Phase 3

#### Badge Errors

No specific error cases -- badge endpoints are read-only list endpoints.

| Status | Endpoint | Message | Meaning |
|--------|----------|---------|---------|
| 401 | Any endpoint | `"Unauthenticated."` | Missing or invalid Bearer token |

#### Event Discovery Errors

| Status | Endpoint | Message | Meaning |
|--------|----------|---------|---------|
| 422 | `GET /events/discover` | Validation errors | Missing lat/lng, invalid range, etc. |

#### Gamification Stats Errors

No specific error cases -- stats endpoints are read-only.

### 9.4 HTTP Status Code Summary

| Status | Meaning |
|--------|---------|
| 200 | Success |
| 201 | Resource created successfully (EventReward create) |
| 401 | Unauthenticated -- missing or expired token |
| 403 | Forbidden -- user lacks permission for this action |
| 404 | Resource not found (or invalid redeem token) |
| 409 | Conflict -- duplicate spin, already redeemed, or has existing claims |
| 422 | Validation failed or business rule violation |

---

## Appendix: Endpoint Summary Table

| Method | URL | Auth | Description |
|--------|-----|------|-------------|
| **Phase 2 -- Rewards** | | | |
| `GET` | `/api/v1/events/{event}/rewards` | Yes | List event rewards |
| `POST` | `/api/v1/events/{event}/rewards` | Yes | Create event reward (event owner) |
| `PUT` | `/api/v1/event-rewards/{eventReward}` | Yes | Update event reward (event owner) |
| `DELETE` | `/api/v1/event-rewards/{eventReward}` | Yes | Delete event reward (event owner) |
| **Phase 2 -- Spin** | | | |
| `POST` | `/api/v1/rewards/spin` | Yes | Spin the wheel for a verified challenge |
| **Phase 2 -- Wallet** | | | |
| `GET` | `/api/v1/me/rewards` | Yes | List my reward claims (paginated) |
| `POST` | `/api/v1/reward-claims/{rewardClaim}/generate-redeem-qr` | Yes | Generate redeem QR token |
| `POST` | `/api/v1/reward-claims/confirm-redeem` | Yes | Confirm redemption (organizer) |
| **Phase 2 -- Leaderboard** | | | |
| `GET` | `/api/v1/events/{event}/leaderboard` | Yes | Event leaderboard |
| `GET` | `/api/v1/leaderboard/global` | Yes | Global leaderboard |
| **Phase 3 -- Badges** | | | |
| `GET` | `/api/v1/badges` | Yes | List all system badges |
| `GET` | `/api/v1/me/badges` | Yes | List my awarded badges |
| **Phase 3 -- Discovery** | | | |
| `GET` | `/api/v1/events/discover?lat=X&lng=Y` | Yes | Discover nearby events |
| **Phase 3 -- Stats** | | | |
| `GET` | `/api/v1/me/gamification-stats` | Yes | My gamification stats |
| `GET` | `/api/v1/profiles/{profile}/game-card` | Yes | Public game card |
