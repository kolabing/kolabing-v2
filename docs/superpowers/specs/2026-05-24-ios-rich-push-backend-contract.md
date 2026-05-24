# iOS Rich Push Backend Contract

**Consumer:** Flutter mobile app on iOS  
**Primary goal:** deliver grouped, action-enabled, image-capable push notifications through OneSignal with correct deep links  
**Status:** required by mobile as of 2026-05-24

## Why this change exists

The iOS app now supports:

- OneSignal Notification Service Extension for rich media and confirmed delivery plumbing
- foreground custom banners with subtitle, badge, interruption level, thread grouping, and attachments
- action button routing for message, application, and general notifications
- shared action/category ids between foreground local presentation and background remote presentation

The backend currently sends only:

- `title`
- `body`
- `data.type`
- `data.id`
- `data.deeplink`

That is enough for a basic push, but not enough for a polished iOS notification experience.

## Existing backend files that need updates

- `app/Services/OneSignalService.php`
- `app/Services/PushNotificationService.php`
- `app/Jobs/SendPushNotification.php`
- optionally `app/Services/NotificationService.php` if presentation should vary per domain event

## Important bug to fix first

`PushNotificationService::resolveDeeplink()` currently returns `/chat/{id}` for `new_message`.

Mobile routing expects:

- `/application/{id}/chat`

This should be corrected even if no other iOS metadata is added.

## Required OneSignal fields

For richer iOS delivery, backend should be able to send these top-level OneSignal fields when needed:

- `subtitle`
- `ios_category`
- `ios_attachments`
- `ios_interruption_level`
- `ios_relevance_score`
- `ios_badgeType`
- `ios_badgeCount`
- `buttons`
- `data`

References:

- OneSignal create message API: `https://documentation.onesignal.com/reference/create-message`
- OneSignal action buttons: `https://documentation.onesignal.com/docs/en/action-buttons`
- OneSignal service extensions: `https://documentation.onesignal.com/docs/en/service-extensions`
- OneSignal iOS interruption levels: `https://documentation.onesignal.com/docs/en/ios-focus-modes-and-interruption-levels`

## Category ids registered in the mobile app

These ids must be treated as fixed API values on backend:

- `kolabing_messages`
- `kolabing_applications`
- `kolabing_general`

## Action ids registered in the mobile app

These ids must also be treated as fixed:

- `open_message_thread`
- `view_application`
- `open_app`
- `open_notifications`

## Recommended payload contract

### Top-level OneSignal message

```json
{
  "app_id": "YOUR_APP_ID",
  "include_aliases": {
    "external_id": ["123"]
  },
  "target_channel": "push",
  "headings": {
    "en": "New Message"
  },
  "contents": {
    "en": "Casa Sol sent you a new message."
  },
  "subtitle": "Messages",
  "ios_category": "kolabing_messages",
  "ios_attachments": {
    "image": "https://cdn.kolabing.com/push/chat-preview-123.jpg"
  },
  "ios_interruption_level": "active",
  "ios_relevance_score": 0.9,
  "ios_badgeType": "SetTo",
  "ios_badgeCount": 7,
  "buttons": [
    {
      "id": "open_message_thread",
      "text": "Open Chat"
    },
    {
      "id": "open_notifications",
      "text": "Notifications"
    }
  ],
  "data": {
    "type": "new_message",
    "id": "application-uuid",
    "deeplink": "/application/application-uuid/chat",
    "subtitle": "Messages",
    "thread_id": "messages_application-uuid",
    "badge": 7,
    "action_deeplinks": {
      "open_message_thread": "/application/application-uuid/chat",
      "open_notifications": "/notifications"
    }
  }
}
```

### Data payload rules

These `data` keys are supported by mobile and should be treated as the stable app contract:

- `type`: existing notification type enum value
- `id`: target entity id
- `deeplink`: in-app route path, not a web URL
- `subtitle`: optional iOS secondary label
- `thread_id`: optional grouping id
- `badge`: optional integer badge value
- `action_deeplinks`: optional object keyed by action id

Supported `action_deeplinks` example:

```json
{
  "open_message_thread": "/application/application-uuid/chat",
  "view_application": "/application/application-uuid",
  "open_app": "/notifications",
  "open_notifications": "/notifications"
}
```

## Type-by-type defaults

### `new_message`

- `ios_category`: `kolabing_messages`
- `subtitle`: `Messages`
- `deeplink`: `/application/{id}/chat`
- `thread_id`: `messages_{id}`
- `ios_interruption_level`: `active`
- `buttons`:
  - `open_message_thread`
  - `open_notifications`

### `application_received`

- `ios_category`: `kolabing_applications`
- `subtitle`: `New application`
- `deeplink`: `/application/{id}`
- `thread_id`: `applications_{id}`
- `ios_interruption_level`: `active`
- `buttons`:
  - `view_application`
  - `open_notifications`

### `application_accepted`

- `ios_category`: `kolabing_applications`
- `subtitle`: `Application update`
- `deeplink`: `/application/{id}`
- `thread_id`: `applications_{id}`
- `ios_interruption_level`: `active`
- `buttons`:
  - `view_application`
  - `open_notifications`

### `application_declined`

- `ios_category`: `kolabing_applications`
- `subtitle`: `Application update`
- `deeplink`: `/application/{id}`
- `thread_id`: `applications_{id}`
- `ios_interruption_level`: `active`
- `buttons`:
  - `view_application`
  - `open_notifications`

### Rewards / badges / other low-urgency notifications

- `ios_category`: `kolabing_general`
- `subtitle`: `Kolabing`
- `thread_id`: `kolabing_general`
- `ios_interruption_level`: `passive`
- `buttons`:
  - `open_app`
  - `open_notifications`

## Attachment rules

Use `ios_attachments` only when there is a real image worth expanding:

- direct file URL only
- prefer `jpg`, `jpeg`, `png`, or `gif`
- keep files under 5 MB
- use stable CDN URLs, not signed URLs with very short expiry

Do not send `ios_attachments` for every push by default. Use it for:

- new message with a preview image
- application update with a cover image
- branded campaign-like transactional pushes

## Action button rules

For iOS, action buttons should always be paired with `ios_category`.

Recommended pattern:

- keep button ids stable
- keep labels short
- use the same ids in both `buttons` and `data.action_deeplinks`

## Interruption level rules

Allowed values:

- `active`
- `time-sensitive`
- `passive`
- `critical`

Backend should **not** send `critical` right now.

Reason:

- the app is not provisioned for Apple Critical Alerts entitlement
- mobile intentionally downgrades unsafe critical foreground presentation

Use `time-sensitive` only for truly urgent collaboration events. Most traffic should remain `active` or `passive`.

## Suggested PHP refactor

### 1. Broaden the OneSignal payload method

Current `OneSignalService::sendPushToUsers()` is typed around `array<string, string> $data`.

That should become flexible enough for:

- `array<string, mixed> $data`
- optional `array<string, mixed> $messageOptions`

Recommended direction:

```php
public function sendPushToUsers(
    array $userIds,
    string $title,
    string $body,
    array $data = [],
    array $messageOptions = [],
): array
```

Then merge safe allow-listed OneSignal keys into the final payload.

### 2. Introduce presentation metadata in `PushNotificationService`

`PushNotificationService` should stop being string-only.

Recommended:

- build a richer presentation array per `NotificationType`
- send both navigation data and iOS presentation metadata together

Example output shape before passing into `OneSignalService`:

```php
[
    'subtitle' => 'Messages',
    'ios_category' => 'kolabing_messages',
    'ios_interruption_level' => 'active',
    'ios_badgeType' => 'SetTo',
    'ios_badgeCount' => 7,
    'buttons' => [
        ['id' => 'open_message_thread', 'text' => 'Open Chat'],
        ['id' => 'open_notifications', 'text' => 'Notifications'],
    ],
    'data' => [
        'type' => 'new_message',
        'id' => 'application-uuid',
        'deeplink' => '/application/application-uuid/chat',
        'subtitle' => 'Messages',
        'thread_id' => 'messages_application-uuid',
        'badge' => 7,
        'action_deeplinks' => [
            'open_message_thread' => '/application/application-uuid/chat',
            'open_notifications' => '/notifications',
        ],
    ],
]
```

### 3. Keep queue job extensible

`SendPushNotification` currently carries only:

- recipient
- title
- body
- type
- target id

If richer pushes depend on image URLs, badge counts, or custom action overrides, the job signature will need an extra structured payload or DTO.

## Minimum first shipping scope

If backend wants the smallest safe change set, ship this first:

1. Fix `new_message` deeplink to `/application/{id}/chat`
2. Add `subtitle`
3. Add `ios_category`
4. Add `thread_id`
5. Add `buttons`
6. Add `action_deeplinks`

Second pass:

1. Add `ios_attachments`
2. Add `ios_badgeType` and `ios_badgeCount`
3. Add `ios_interruption_level`
4. Add `ios_relevance_score`

## Mobile assumptions

The current iOS app already handles:

- OneSignal click `actionId`
- foreground local banner rendering from OneSignal payload data
- category-based action buttons
- attachment download for foreground banners
- Notification Service Extension for background rich media

Backend does not need to invent new category names or action ids. It should use the exact values in this document.
