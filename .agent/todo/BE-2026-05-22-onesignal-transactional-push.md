# BE-2026-05-22 · OneSignal transactional push integration

## Context

The mobile app now initializes OneSignal with app ID `5fe7283d-a93e-46c7-b12a-f5d88b7c6571` and identifies signed-in users with:

- `external_id = <auth user id>`
- user tags:
  - `user_type = business | community | attendee`
  - `subscription_status = active | inactive`

This means backend can target app users directly through OneSignal without needing raw device tokens from the mobile app.

## Goal

Add backend support for sending targeted / transactional push notifications through OneSignal REST APIs using Kolabing user IDs.

## Required backend work

1. Add OneSignal config/env support.
   - `ONESIGNAL_APP_ID`
   - `ONESIGNAL_REST_API_KEY`
   - optional: `ONESIGNAL_BASE_URL=https://api.onesignal.com`

2. Add a small OneSignal service client in backend.
   - support `Create Message` API
   - support targeting by `include_aliases.external_id`
   - default `target_channel = push`

3. Add a transactional send entrypoint.
   - service method example:
     - `sendPushToUsers(List<int|string> userIds, {title, body, data})`
   - normalize IDs to strings before sending

4. Support Kolabing navigation payload contract in `data`.
   - `type`
   - `id`
   - `deeplink`

5. Add delivery logging.
   - store outgoing request intent
   - store OneSignal response id
   - store failure payload/message for retries/debugging

## Recommended OneSignal payload shape

```json
{
  "app_id": "YOUR_APP_ID",
  "include_aliases": {
    "external_id": ["123", "456"]
  },
  "target_channel": "push",
  "headings": {
    "en": "New Kolab Application"
  },
  "contents": {
    "en": "You received a new application."
  },
  "data": {
    "type": "application",
    "id": "789",
    "deeplink": "/application/789"
  }
}
```

## Why this contract

- OneSignal recommends targeting users with `include_aliases.external_id`.
- Mobile now calls `OneSignal.login(user.id)` on sign-in and restored sessions.
- Mobile click handling reads `data.type`, `data.id`, and `data.deeplink` and routes inside the app.

## Acceptance

- Backend can send a push to a single Kolabing user by app user id.
- Backend can send a batch push to multiple user ids.
- OneSignal payload supports in-app routing via `data`.
- Failures are logged with actionable error details.

## Source notes

- OneSignal Flutter SDK setup:
  - `OneSignal.login(externalId)` for user identity
- OneSignal transactional messaging:
  - `include_aliases.external_id` for targeted sends
