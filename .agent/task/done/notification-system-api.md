# Task: Notification System API

## Status
- Created: 2026-01-30
- Started:
- Completed:

## Description
Implement in-app notification system with 4 endpoints:
1. `GET /api/v1/me/notifications` - List notifications (paginated)
2. `GET /api/v1/me/notifications/unread-count` - Unread count for badge
3. `POST /api/v1/me/notifications/{notification}/read` - Mark single as read
4. `POST /api/v1/me/notifications/read-all` - Mark all as read

Notification types: new_message, application_received, application_accepted, application_declined

Notifications are created when:
- Chat message sent → notify other party
- Application submitted → notify opportunity owner
- Application accepted → notify applicant
- Application declined → notify applicant

## Assigned Agents
- [x] @laravel-specialist (migration, model, enum, factory, service, controller, resource, routes, tests)

## Progress
### Backend
(to be filled)

## Notes
- Firebase push notifications will be handled separately on mobile side
- This task covers only the backend API endpoints and notification creation logic
- Existing `GET /api/v1/me/unread-messages-count` is separate (chat-specific)
