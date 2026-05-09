# Kolabing Notification Contract

## Status

- Phase `P1` is enabled by default:
  - `new_message`
  - `application_received`
  - `application_accepted`
  - `application_declined`
  - `badge_awarded`
  - `challenge_verified`
  - `reward_won`
- Phase `P2` and `P3` notification types are implemented behind backend feature flags in `config/notifications.php`.
- Push fan-out is multi-device. A single notification row can deliver to all active tokens for the recipient profile.
- Growth campaigns are scheduler-driven and currently respect:
  - `marketing_enabled`
  - quiet hours
  - max `1` growth notification per `24h`
  - max `3` growth notifications per `7d`

## Mobile-Safe Types

| Type | Deeplink | Mobile status |
| --- | --- | --- |
| `new_message` | `/application/{application_id}/chat` | Safe now |
| `application_received` | `/application/{application_id}` | Safe now |
| `application_accepted` | `/application/{application_id}` | Safe now |
| `application_declined` | `/application/{application_id}` | Safe now |
| `badge_awarded` | `/notifications` | Safe now with fallback |
| `challenge_verified` | `/notifications` | Safe now with fallback |
| `reward_won` | `/notifications` | Safe now with fallback |

## Backend-Gated Types

These types already exist in the backend enum and payload factory, but stay disabled until the related mobile routing/UI is ready:

- `collaboration_scheduled`
- `collaboration_rescheduled`
- `collaboration_cancelled`
- `collaboration_reminder_24h`
- `collaboration_reminder_same_day`
- `challenge_verification_requested`
- `challenge_rejected`
- `withdrawal_approved`
- `withdrawal_rejected`
- `withdrawal_paid`
- `referral_reward_earned`
- `pending_application_nudge`
- `opportunity_match`
- `nearby_event_match`
- `wallet_threshold_reached`
- `dormant_user_reactivation`

Until a type is enabled end-to-end, mobile should treat any unknown `type` as a generic notification and open `deeplink` if present, otherwise `/notifications`.

## Push Payload

Push data sent to FCM includes the current mobile-compatible keys plus forward-compatible routing fields:

```json
{
  "notification_id": "uuid",
  "type": "new_message",
  "id": "application_uuid",
  "target_type": "application",
  "target_id": "application_uuid",
  "deeplink": "/application/application_uuid/chat",
  "actor_id": "profile_uuid",
  "actor_name": "Ayse",
  "title": "Ayse sent a message",
  "body": "Are we good for Friday?",
  "image_url": "",
  "priority": "high",
  "dedupe_key": "message:message_uuid",
  "sent_at": "2026-05-06T15:30:00Z"
}
```

### Payload rules

- `type` and `id` always exist for current Flutter compatibility.
- `id` is the primary router entity for the current app.
- `notification_id` is the persistent in-app notification row ID.
- `deeplink` is the new source of truth for future routing.
- `title` and `body` are already human-readable and must be shown directly.
- `dedupe_key` is stable per recipient and event occurrence.

## In-App Notification API

### `GET /api/v1/me/notifications`

Returns paginated notification rows for the authenticated profile.

Example item:

```json
{
  "id": "notification_uuid",
  "notification_id": "notification_uuid",
  "type": "application_received",
  "title": "New application received",
  "body": "Ayse applied to Sunset Rooftop Campaign",
  "deeplink": "/application/application_uuid",
  "priority": "high",
  "is_read": false,
  "read_at": null,
  "created_at": "2026-05-09T10:20:30Z",
  "actor_name": "Ayse",
  "actor_avatar_url": "https://...",
  "target_id": "application_uuid",
  "target_type": "application"
}
```

Notes:

- `id` remains the notification row ID in the in-app list because mobile uses it for mark-read APIs.
- `target_id`, `target_type`, and `deeplink` should be used for navigation.

### `GET /api/v1/me/notifications/unread-count`

Returns notification unread count only. This is separate from chat unread counts.

### `POST /api/v1/me/notifications/{id}/read`

Marks one notification as read.

### `POST /api/v1/me/notifications/read-all`

Marks all notification rows as read for the authenticated profile.

## Message Unread API

### `GET /api/v1/me/unread-messages-count`

Returns chat unread counts only. This endpoint is intentionally separate from notifications.

## Device Token API

### `POST /api/v1/me/device-token`

Request:

```json
{
  "token": "fcm_device_token_here",
  "platform": "ios",
  "app_version": "1.4.0",
  "locale": "tr",
  "timezone": "Europe/Istanbul",
  "last_location_lat": 41.3874,
  "last_location_lng": 2.1686,
  "location_permission_granted_at": "2026-05-09T10:00:00Z"
}
```

Rules:

- Upsert happens by raw `token`.
- If the same token is registered by a different profile, ownership moves to the new authenticated profile.
- Every successful register refreshes metadata and `last_seen_at`.
- `last_location_lat`, `last_location_lng`, and `location_permission_granted_at` are optional backward-compatible fields for `nearby_event_match`.
- Mobile should only send location metadata when the user has granted location access.

### `DELETE /api/v1/me/device-token`

Request:

```json
{
  "token": "fcm_device_token_here"
}
```

Use this on logout or when the user manually disconnects the device.

## Notification Preferences API

### `GET /api/v1/me/notification-preferences`

Returns current notification toggles and quiet-hours config.

Example:

```json
{
  "email_notifications": true,
  "whatsapp_notifications": true,
  "new_application_alerts": true,
  "collaboration_updates": true,
  "marketing_tips": false,
  "messages_enabled": true,
  "applications_enabled": true,
  "collaborations_enabled": true,
  "rewards_enabled": true,
  "marketing_enabled": false,
  "quiet_hours_start": null,
  "quiet_hours_end": null,
  "timezone": null
}
```

### `PUT /api/v1/me/notification-preferences`

Supports partial updates for:

- `messages_enabled`
- `applications_enabled`
- `collaborations_enabled`
- `rewards_enabled`
- `marketing_enabled`
- `quiet_hours_start`
- `quiet_hours_end`
- `timezone`
- legacy compatibility fields already used by the app

## Referral Input

The following auth endpoints now accept an optional `referral_code` field:

- `POST /api/v1/auth/google`
- `POST /api/v1/auth/register/business`
- `POST /api/v1/auth/register/community`
- `POST /api/v1/auth/register/attendee`

Rules:

- `referral_code` is optional.
- If supplied, it must match an existing `referral_codes.code`.
- Referral rewards are idempotent per converted profile.

Example:

```json
{
  "id_token": "google_id_token",
  "user_type": "community",
  "referral_code": "KOLAB-ABCD"
}
```

## Growth Notification Notes

The backend already produces these growth notification types behind flags:

- `pending_application_nudge`
- `opportunity_match`
- `nearby_event_match`
- `wallet_threshold_reached`
- `dormant_user_reactivation`

Current backend routing defaults:

| Type | Deeplink |
| --- | --- |
| `pending_application_nudge` | `/business` or `/community` |
| `opportunity_match` | `/business/browse` or `/community/offers` |
| `nearby_event_match` | `/attendee` |
| `wallet_threshold_reached` | `/community/wallet` |
| `dormant_user_reactivation` | `/notifications` |

Because these remain backend-gated, mobile can safely ship generic handling first:

- unknown `type` => open `deeplink` if present
- missing/unsupported route => fallback to `/notifications`

## Mobile Checklist

- Keep using `type` and `id` exactly as delivered.
- Prefer `deeplink` for all new routing work.
- Continue treating `GET /api/v1/me/unread-messages-count` and notification unread count as separate sources.
- Send `DELETE /api/v1/me/device-token` on logout.
- When growth campaigns are enabled, send optional device location metadata only after explicit user permission.

## Backend Guarantees

- Every notification-producing event writes an in-app row first.
- Push fan-out is asynchronous and per active device token.
- Notification dedupe is enforced per recipient with `(profile_id, dedupe_key)`.
- Invalid FCM tokens are deactivated automatically.
- Transactional notification types bypass quiet hours.
- Growth types must respect marketing opt-in, quiet hours, and rate limits before being enabled.
