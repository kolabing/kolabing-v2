# Kolabing Mobile API Documentation - Index

**Version:** 1.0
**Last Updated:** 2026-01-26

## Documentation Overview

This directory contains comprehensive API documentation for mobile app developers integrating with the Kolabing backend API.

## Available Documentation

### 1. Full API Specification
**File:** `MOBILE_OPPORTUNITY_API.md`

Complete API reference documentation including:
- All 8 opportunity endpoints with full specifications
- Request/response schemas and examples
- Detailed field descriptions and validation rules
- JSONB structure for `business_offer` and `community_deliverables`
- Error handling and status codes
- Business rules and authorization matrix
- Mobile development tips and best practices

**Use this for:** Complete API reference, understanding all endpoints, implementation details

---

### 2. Quick Reference Guide
**File:** `MOBILE_OPPORTUNITY_API_QUICK_REFERENCE.md`

Condensed one-page reference including:
- Endpoint summary table
- Status and enum values
- Quick create example
- Common filter parameters
- Response structure
- Business rules checklist
- Common validation rules
- JSONB field examples
- Mobile implementation code snippets

**Use this for:** Quick lookups, common patterns, code examples during development

---

### 3. User Flow Examples
**File:** `MOBILE_OPPORTUNITY_API_USER_FLOWS.md`

Real-world user journey scenarios including:
- Business user: Create draft → Edit → Publish flow
- Community user: Browse → View details flow
- Managing published opportunities (Close, Delete)
- Complete request/response examples for each step
- Error scenarios with actual responses
- Mobile implementation checklist

**Use this for:** Understanding typical workflows, testing scenarios, error handling

---

## Quick Start Guide

### Authentication
All endpoints require authentication via Bearer token:

```http
Authorization: Bearer {your_sanctum_token}
```

The authenticated user is a `Profile` object with `user_type` of either `business` or `community`.

### Base URL
```
/api/v1
```

### Core Endpoints Summary

| Method | Endpoint | Purpose |
|--------|----------|---------|
| GET | `/opportunities` | Browse published opportunities |
| GET | `/me/opportunities` | Get my opportunities (all statuses) |
| GET | `/opportunities/{id}` | Get single opportunity |
| POST | `/opportunities` | Create draft opportunity |
| PUT | `/opportunities/{id}` | Update opportunity |
| POST | `/opportunities/{id}/publish` | Publish draft |
| POST | `/opportunities/{id}/close` | Close published |
| DELETE | `/opportunities/{id}` | Delete draft (no apps) |

### Status Flow

```
draft → published → closed → completed
```

### Key Business Rules

1. **Any authenticated user** can create opportunities (creates as draft)
2. **Business users** need active Stripe subscription to publish
3. **Community users** can publish for free
4. Only **draft or published** opportunities can be updated
5. Only **draft** opportunities with **zero applications** can be deleted
6. Only **published** opportunities can be closed

### Critical Data Structures

#### business_offer (JSONB)
```json
{
  "venue": true,
  "food_drink": true,
  "discount": {
    "enabled": true,
    "percentage": 20
  },
  "products": ["Product A", "Product B"],
  "other": "Custom offer description"
}
```

#### community_deliverables (JSONB)
```json
{
  "instagram_post": true,
  "instagram_story": true,
  "tiktok_video": false,
  "event_mention": true,
  "attendee_count": 50,
  "other": "Additional promotional activities"
}
```

---

## Integration Checklist

### Phase 1: Browse & View
- [ ] Implement opportunity list screen with pagination
- [ ] Add filter UI (categories, city, venue mode, etc.)
- [ ] Implement search functionality with debouncing
- [ ] Create opportunity detail screen
- [ ] Handle empty states and loading states

### Phase 2: Create & Edit
- [ ] Build opportunity creation form
- [ ] Implement field validation
- [ ] Add category multi-select (max 5)
- [ ] Build business offer configuration UI
- [ ] Build community deliverables configuration UI
- [ ] Implement image upload for offer_photo
- [ ] Add date picker for availability dates
- [ ] Save as draft functionality

### Phase 3: Publish & Manage
- [ ] Implement subscription check for business users
- [ ] Build publish confirmation flow
- [ ] Add "My Opportunities" screen
- [ ] Implement close opportunity action
- [ ] Implement delete draft action (with confirmation)
- [ ] Add opportunity status badges

### Phase 4: Error Handling
- [ ] Display validation errors on form fields
- [ ] Handle 403 authorization errors
- [ ] Handle subscription required error (redirect to payment)
- [ ] Show business rule errors in dialogs
- [ ] Implement retry logic for network errors

### Phase 5: UX Enhancements
- [ ] Implement optimistic UI updates
- [ ] Add pull-to-refresh on lists
- [ ] Cache opportunity data locally
- [ ] Add skeleton loaders
- [ ] Implement share functionality
- [ ] Add favorite/bookmark feature (future)

---

## Common Enum Values

### Opportunity Status
- `draft` - Not visible to others
- `published` - Live and browsable
- `closed` - No new applications
- `completed` - Collaboration finished

### Availability Mode
- `one_time` - Single event
- `recurring` - Repeated events
- `flexible` - Open to discussion

### Venue Mode
- `business_venue` - At business location
- `community_venue` - At community location
- `no_venue` - Online or no specific venue

### User Type
- `business` - Business user
- `community` - Community user

---

## Testing Scenarios

### Happy Path
1. Business user creates draft opportunity
2. Business user edits draft
3. Business user publishes (with subscription)
4. Community user browses and views opportunity
5. Business user closes opportunity after receiving applications

### Edge Cases
1. Business user tries to publish without subscription → Should show subscription flow
2. User tries to update closed opportunity → Should fail with 400 error
3. User tries to delete draft with applications → Should fail with 400 error
4. User tries to update someone else's opportunity → Should fail with 403 error
5. Invalid date range (end before start) → Should fail with 422 validation error
6. More than 5 categories → Should fail with 422 validation error

---

## Support & Troubleshooting

### Common Issues

**Issue:** "Business users must have an active subscription to publish opportunities"
- **Solution:** Check if user has active subscription via `/api/v1/me/subscription`
- **Action:** Redirect to subscription purchase flow

**Issue:** "Opportunity can only be updated when in draft or published status"
- **Solution:** Check opportunity status before allowing edit
- **Action:** Disable edit button for closed/completed opportunities

**Issue:** "Validation failed" with multiple field errors
- **Solution:** Parse `errors` object and display field-specific messages
- **Action:** Highlight invalid fields in red with error text

**Issue:** "You are not authorized to update this opportunity"
- **Solution:** Check if current user is the opportunity creator
- **Action:** Hide edit/delete buttons for opportunities not owned by user

---

## API Version History

### Version 1.0 (2026-01-26)
- Initial mobile API documentation
- 8 core opportunity endpoints
- Full CRUD operations
- Publish/Close actions
- Filtering and pagination
- Authorization policies

---

## Contact

For questions about the API or this documentation:
- Backend team: backend@kolabing.com
- API issues: Create ticket in backend repository

---

## Related Documentation

- **Backend Codebase:** `/Users/volkanoluc/Projects/kolabing-v2/README.MD`
- **Database Schema:** `/Users/volkanoluc/Projects/kolabing-v2/mobile_mvp_database.sql`
- **Project Guidelines:** `/Users/volkanoluc/Projects/kolabing-v2/CLAUDE.md`
- **Development Progress:** `/Users/volkanoluc/Projects/kolabing-v2/.agent/documentations/DEVELOPMENT_PROGRESS.md`

---

**Generated:** 2026-01-26
**API Version:** 1.0
**Documentation Status:** Complete ✓
