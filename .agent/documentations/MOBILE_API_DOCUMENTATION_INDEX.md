# Kolabing Mobile API Documentation - Index

**Version:** 1.0
**Last Updated:** 2026-03-03

## Documentation Overview

This directory contains comprehensive API documentation for mobile app developers integrating with the Kolabing backend API.

---

## Master Reference (Start Here)

### KOLABING_MOBILE_API_COMPLETE.md
**The single comprehensive reference for all mobile API integration.** Contains the complete API index (99 endpoints), response format standard, authentication guide, user types, pagination, error handling, domain summaries, mobile development checklist, and testing guide.

**Use this for:** One-stop reference for the entire API surface. Start here before diving into domain-specific docs.

---

## Domain-Specific Documentation

### Authentication
| File | Description |
|------|-------------|
| `mobile-auth-api-guide.md` | Registration (business, community), login, Google OAuth, Apple Sign-In, me, logout |
| `mobile-password-reset-api.md` | Forgot password + reset password flow with deep links |

### Profile & Subscription
| File | Description |
|------|-------------|
| `mobile-profile-subscription-guide.md` | Profile CRUD, notification preferences, Stripe subscription management |
| `mobile-subscription-api.md` | Detailed subscription API (checkout, portal, cancel) |
| `mobile-payment-integration.md` | Stripe payment integration guide with deep link flows |

### Opportunities
| File | Description |
|------|-------------|
| `MOBILE_OPPORTUNITY_API.md` | Full opportunity API specification (all 8 endpoints) |
| `MOBILE_OPPORTUNITY_API_QUICK_REFERENCE.md` | Quick reference card for opportunities |
| `MOBILE_OPPORTUNITY_API_USER_FLOWS.md` | User flow examples and testing scenarios |
| `mobile-search-opportunities-api.md` | Search + explore opportunities with filters |
| `mobile-opportunity-limit-guide.md` | Opportunity limits and subscription paywall for business users |

### Applications
| File | Description |
|------|-------------|
| `mobile-applications-api.md` | Application CRUD, accept/decline/withdraw with code examples |
| `mobile-accept-application-api.md` | Detailed guide for accepting applications |

### Collaborations
| File | Description |
|------|-------------|
| `collaboration-api-mobile-docs.md` | Collaboration system (activate, complete, cancel, challenges, QR) |

### Chat & Messaging
| File | Description |
|------|-------------|
| `mobile-chat-api.md` | Chat messaging (send, receive, read tracking, WebSocket) |

### Notifications
| File | Description |
|------|-------------|
| `mobile-notification-api.md` | In-app notification system (list, unread count, mark read) |
| `mobile-push-notifications-api.md` | Firebase FCM push notifications (device token, push handling) |

### Gallery
| File | Description |
|------|-------------|
| `mobile-gallery-api.md` | Profile gallery photos (upload, delete, view) |

### Events & Gamification
| File | Description |
|------|-------------|
| `mobile-events-api.md` | Events CRUD and past events showcase |
| `mobile-gamification-api.md` | Gamification Phase 1 (attendee, check-in, challenges) |
| `mobile-gamification-phase2-3-api.md` | Gamification Phase 2+3 (rewards, badges, leaderboard, discovery) |

### Dashboard
| File | Description |
|------|-------------|
| `mobile-dashboard-api.md` | Dashboard stats (business vs community views) |

### Integration & Lookup
| File | Description |
|------|-------------|
| `mobile-integration-guide.md` | Lookup tables (cities, business/community types) + file upload |

---

## Quick Start Guide

### Authentication
All endpoints require authentication via Bearer token:

```http
Authorization: Bearer {your_sanctum_token}
```

The authenticated user is a `Profile` object with `user_type` of `business`, `community`, or `attendee`.

### Base URL
```
/api/v1
```

### Core Endpoints Summary

| Method | Endpoint | Purpose |
|--------|----------|---------|
| POST | `/auth/register/business` | Register business user |
| POST | `/auth/register/community` | Register community user |
| POST | `/auth/login` | Email/password login |
| GET | `/auth/me` | Get current user |
| GET | `/opportunities` | Browse published opportunities |
| POST | `/opportunities` | Create draft opportunity |
| POST | `/opportunities/{id}/applications` | Apply to opportunity |
| GET | `/me/dashboard` | Dashboard stats |
| GET | `/me/notifications` | Notification list |
| GET | `/collaborations` | List collaborations |

### Response Format

```json
{
  "success": true,
  "message": "...",
  "data": { ... }
}
```

---

## Common Enum Values

### User Type
- `business` - Business user
- `community` - Community user
- `attendee` - Event attendee (gamification)

### Opportunity Status
- `draft` - Not visible to others
- `published` - Live and browsable
- `closed` - No new applications
- `completed` - Collaboration finished

### Application Status
- `pending` - Awaiting response
- `accepted` - Accepted (creates collaboration)
- `declined` - Declined by creator
- `withdrawn` - Withdrawn by applicant

### Collaboration Status
- `scheduled` - Upcoming collaboration
- `active` - In progress
- `completed` - Successfully finished
- `cancelled` - Cancelled

### Subscription Status
- `active` - Active and paid
- `cancelled` - Cancelled
- `past_due` - Payment failed
- `inactive` - No subscription

---

## API Version History

### 2026-03-03
- Added master reference: `KOLABING_MOBILE_API_COMPLETE.md`
- Added push notifications: `mobile-push-notifications-api.md`
- Updated index with complete documentation catalog

### 2026-02-06
- Added gamification Phase 2+3 documentation
- Added events and gamification APIs

### 2026-01-29
- Added applications, chat, notifications documentation

### 2026-01-26
- Initial mobile API documentation
- 8 core opportunity endpoints

---

## Contact

For questions about the API or this documentation:
- Backend team: backend@kolabing.com
- API issues: Create ticket in backend repository

---

**Generated:** 2026-03-03
**API Version:** 1.0
**Documentation Status:** Complete
