# OneSignal Transactional Push Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the backend FCM transactional push path with OneSignal user-targeted delivery keyed by Kolabing profile IDs.

**Architecture:** Keep the existing notification and queue boundaries, but swap the delivery layer from Firebase token targeting to OneSignal `include_aliases.external_id`. `NotificationService` will continue creating in-app notifications and dispatching `SendPushNotification`, while a new OneSignal client will own payload construction, API calls, and delivery logging.

**Tech Stack:** Laravel 12, queued jobs, `Http` facade, OneSignal REST API

---

### Task 1: Replace the delivery client

**Files:**
- Create: `app/Services/OneSignalService.php`
- Modify: `app/Services/PushNotificationService.php`
- Modify: `config/services.php`
- Modify: `.env.example`

- [ ] Add OneSignal config entries for app id, REST API key, and base URL.
- [ ] Implement a focused `OneSignalService` with a `sendPushToUsers()` entrypoint that normalizes profile IDs to strings, posts to `notifications`, logs request intent, logs the response id, and throws on API failure so queue retries/`failed_jobs` work.
- [ ] Refactor `PushNotificationService` to delegate to `OneSignalService`, add the `deeplink` payload, and remove all FCM token-specific behavior.

### Task 2: Switch notification dispatch to OneSignal targeting

**Files:**
- Modify: `app/Services/NotificationService.php`
- Modify: `app/Http/Controllers/Api/V1/DeviceTokenController.php`

- [ ] Remove the `device_token` gate from push job dispatch so any notification can target the signed-in user through OneSignal `external_id`.
- [ ] Update stale controller comments/messages that still describe the endpoint as FCM-only; keep the endpoint operational for backward compatibility.

### Task 3: Verification and regression coverage

**Files:**
- Modify: `tests/Feature/Api/V1/PushNotificationTest.php`

- [ ] Update push notification expectations so queue dispatch no longer depends on a stored `device_token`.
- [ ] Verify the targeted push suite and the full Laravel test suite after the delivery swap.
