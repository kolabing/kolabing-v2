# Collaboration Completion-Flow Notifications (both parties)

**Date:** 2026-06-23
**Repos:** `kolabing-v2` (backend) + `kolabing-app` (Flutter) — coordinated change.

## Goal

Every state change in the collaboration completion flow must notify **both
parties** (business + community), including the actor who performed the action.
Tapping the notification (push or in-app) must open the **collaboration detail
screen**.

## Decisions (approved)

- **Scope:** full lifecycle — created, activated, feedback submitted, completed
  (manual / admin force / auto), cancelled.
- **Recipients:** genuinely both parties. The actor also gets a notification, but
  with actor-aware copy ("You marked…") vs counterpart copy ("{name} marked…").
- **Copy language:** English, hardcoded, matching the existing
  `notifyApplicationAccepted` convention. Copy lives **only in the backend**.
- **Completed:** one shared `CollaborationCompleted` type for manual/admin/auto;
  body wording differs (auto path has no actor).

## Shared contract (MUST match byte-for-byte across both repos)

### NotificationType string values
| Case | String value |
|------|--------------|
| CollaborationCreated | `collaboration_created` |
| CollaborationActivated | `collaboration_activated` |
| CollaborationFeedbackReceived | `collaboration_feedback_received` |
| CollaborationCompleted | `collaboration_completed` |
| CollaborationCancelled | `collaboration_cancelled` |

### Routing
- `target_type` = `collaboration`, `target_id` = `collaboration.id`.
- Deeplink string = `/collaboration/{collaboration.id}` (singular) for all five
  **and** for the existing `collab_day_reminder` / `collab_followup_reminder`
  (bug fix — they currently fall through to `/notifications`).

## Copy (backend, English)

`{kolab}` = collaboration's kolab title. `{name}` = actor display name.

| Action | Actor sees | Counterpart sees |
|--------|------------|------------------|
| Created (system) | — | both: "Collaboration started" / "Your collaboration for \"{kolab}\" is set up. Tap to view the details." |
| Activated | "Collaboration activated" / "You marked the collaboration for \"{kolab}\" as active." | "Collaboration activated" / "{name} marked your collaboration for \"{kolab}\" as active." |
| Feedback | "Feedback submitted" / "Your feedback for \"{kolab}\" has been recorded." | "New feedback" / "{name} left feedback for your collaboration \"{kolab}\"." |
| Completed (manual/admin) | "Collaboration completed" / "You marked the collaboration for \"{kolab}\" as complete." | "Collaboration completed" / "{name} marked your collaboration for \"{kolab}\" as complete." |
| Completed (auto) | — | both: "Collaboration completed" / "Your collaboration for \"{kolab}\" was automatically marked complete." |
| Cancelled | "Collaboration cancelled" / "You cancelled the collaboration for \"{kolab}\"." | "Collaboration cancelled" / "{name} cancelled your collaboration for \"{kolab}\"." |

## Backend changes (kolabing-v2)

1. `app/Enums/NotificationType.php` — add the 5 cases above.
2. **Audit every `match`/`switch` over `NotificationType`** (deeplink, iOS
   category, interruption level, relevance score, action deeplinks in
   `PushNotificationService`) and add explicit arms so the new types don't hit an
   `UnhandledMatchError` or a wrong default.
3. `PushNotificationService::resolveDeeplink()` — add arms for the 5 new types
   **and** for `CollabDayReminder` / `CollabFollowUpReminder` → `/collaboration/{id}`.
4. `app/Services/NotificationService.php` — add a private `notifyBothParties`
   helper (loops `creatorProfile` + `applicantProfile`, picks actor-aware copy)
   and 5 public methods:
   - `notifyCollaborationCreated(Collaboration $c)`
   - `notifyCollaborationActivated(Collaboration $c, Profile $actor)`
   - `notifyCollaborationFeedbackReceived(Collaboration $c, Profile $actor)`
   - `notifyCollaborationCompleted(Collaboration $c, ?Profile $actor)`
   - `notifyCollaborationCancelled(Collaboration $c, ?Profile $actor)`
   Use `target_type: 'collaboration'`, `target_id: $c->id`.
5. Wire dispatch into the service methods (on the `fresh()` model, **after** the
   state change), each wrapped in `try/catch` + `report($e)` so a push failure
   never breaks the transition:
   - `CollaborationService::createFromApplication()` → Created
   - `CollaborationService::activate()` → Activated (actor = authed profile)
   - `CollaborationService::complete()` / `adminForceComplete()` → Completed (actor)
   - `CollaborationService::autoComplete()` → Completed (actor = null)
   - `CollaborationService::cancel()` → Cancelled (actor)
   - `CollaborationFeedbackService::submit()` → FeedbackReceived (actor = reviewer)
   Inject `NotificationService` via constructor where missing. If a method does
   not currently receive the acting `Profile`, thread it from the controller.
6. Tests (`tests/Feature/`, `LazilyRefreshDatabase`, factories): for each action,
   assert **both** parties get a `notifications` row with the right `type`,
   `target_type='collaboration'`, `target_id`, and actor-aware body. Assert the
   deeplink resolves to `/collaboration/{id}`.
7. `vendor/bin/pint` clean; `php artisan test` for the touched files green.
8. Docs: `docs/BACKLOG.md` §2 — note the new dispatched types + the
   day/followup deeplink fix (mirror byte-identical in both repos).

## Mobile changes (kolabing-app)

1. `lib/features/notification/models/app_notification.dart` — add 5 enum values +
   `fromString()` mappings for the string values above.
2. `lib/features/notification/utils/notification_navigation.dart` — add type cases
   for the 5 new types → `KolabingRoutes.collaborationDetails` (mirror the
   existing `collab_day_reminder` arm). `/collaboration/{id}` deeplink path is
   already supported, so this is belt-and-suspenders + correct icon/labeling.
3. Notification list UI: ensure the 5 new types render a sensible icon/label
   (reuse the collaboration-reminder icon mapping).
4. Tests: if the app has tests for `resolveNotificationRoute`, add cases for the
   5 new types → `/collaboration/:id`.

## Localization (added 2026-06-23)

Notifications are now resolved server-side per recipient, superseding the
earlier "hardcoded EN" out-of-scope note below.

- **Recipient locale:** `profiles.preferred_locale` (nullable `varchar(5)`,
  values `en|es|ca`). The mobile app sends `locale` on
  `POST /api/v1/device-token`; it is validated `nullable|string|in:en,es,ca`
  and persisted only when present (token/platform behavior unchanged).
- **Mechanism:** `NotificationService::createLocalizedNotification(recipient,
  type, titleKey, bodyKey, replace, actor?, targetId?, targetType?,
  pushOptions?)` resolves
  `$locale = $recipient->preferred_locale ?? config('app.fallback_locale')`
  and calls `__($key, $replace, $locale)` (the 4th `__()` arg) — **no
  `app()->setLocale`**, so no global-state leak. It then delegates to the
  existing `createNotification()`.
- **Both-parties copy:** `notifyBothParties` now takes translation **keys** +
  a `$replace` array and resolves title/body **inside the per-recipient loop**,
  so each party gets their own locale and the correct actor-vs-counterpart copy.
  The actor display name and dynamic values (kolab/event/community/badge/reward
  names, points, reasons) are passed as `:placeholder` replacements, never
  pre-interpolated.
- **Strings:** `lang/{en,es,ca}/notifications.php`. The `en` values are
  **byte-identical** to the previous hardcoded strings (existing
  English-asserting tests pass unchanged via the `en` fallback).
- **Coverage:** every `notify*` in `NotificationService` (incl. `NewMessage`
  title — body stays the raw chat preview), plus `EventSignupService`,
  `CommunityJoinRequestService`, `Admin\CommunityVerificationService`,
  `GamificationWalletService`, `BadgeService`, and `ProfileService`
  (account-deletion collaboration-cancelled).
- **Admin force-complete fix:** `adminForceComplete` passes a `null` actor so
  both parties get the actor-less "automatically marked complete" copy.
- **Withdraw notification:** new `ApplicationWithdrawn = 'application_withdrawn'`
  type, dispatched from `ApplicationService::withdraw()` (creator primary +
  applicant confirmation), deeplink `/application/{id}`.

## Out of scope (YAGNI)

- `NotificationPreference` filtering (BACKLOG §2 P1, separate).
- Email channel (BACKLOG §1).

## Mobile impact (kolabing-app)

API contract adds 5 `NotificationType` enum values + sets
`deeplink=/collaboration/{id}` / `target_type=collaboration`. App ships the
matching enum + routing. Tracked in the kolabing-app branch
`feat/collaboration-completion-notifications`.
