# Task: Design Collaboration Request APIs

## Status
- Created: 2026-01-25 10:00
- Started: 2026-01-25 10:00
- Completed: 2026-01-25 10:45

## Description
Design and document the collaboration request creation APIs based on the database structure and user workflows. This includes:
1. Collab Opportunity CRUD APIs (both Business and Community can create)
2. Application APIs (apply, accept, decline, withdraw)
3. Collaboration management APIs
4. Mobile documentation with example requests/responses

## User Workflows Summary
- **Community Users**: Browse business offers, create community collab offers, apply to businesses
- **Business Users**: Create business collab offers, browse community offers, apply to communities, accept/decline applications

## Key Business Rules
1. Business users need active subscription to publish opportunities or accept applications
2. One application per user per opportunity (unique constraint)
3. Accepting an application creates a collaboration record
4. Status flows:
   - Opportunity: draft → published → closed → completed
   - Application: pending → accepted | declined | withdrawn
   - Collaboration: scheduled → active → completed | cancelled

## Assigned Agents
- [x] @api-designer - API contract design
- [x] @product-manager - Requirements validation
- [ ] @laravel-specialist - Implementation details
- [ ] @fullstack-developer - Integration review

## Progress

### API Contract Design
**Status:** Completed by @api-designer

#### Endpoints Designed:

**Collab Opportunities (8 endpoints):**
- `GET /api/v1/opportunities` - Browse with filters (creator_type, categories, city, venue_mode, availability)
- `GET /api/v1/opportunities/{id}` - Get details
- `POST /api/v1/opportunities` - Create (draft status)
- `PUT /api/v1/opportunities/{id}` - Update
- `DELETE /api/v1/opportunities/{id}` - Delete (draft only)
- `POST /api/v1/opportunities/{id}/publish` - Publish (subscription required for business)
- `POST /api/v1/opportunities/{id}/close` - Close
- `GET /api/v1/me/opportunities` - List my opportunities

**Applications (8 endpoints):**
- `GET /api/v1/opportunities/{id}/applications` - List for opportunity (creator only)
- `POST /api/v1/opportunities/{id}/applications` - Apply
- `GET /api/v1/applications/{id}` - Get details
- `POST /api/v1/applications/{id}/accept` - Accept (creates collaboration)
- `POST /api/v1/applications/{id}/decline` - Decline
- `POST /api/v1/applications/{id}/withdraw` - Withdraw
- `GET /api/v1/me/applications` - My sent applications
- `GET /api/v1/me/received-applications` - Received applications

**Collaborations (5 endpoints):**
- `GET /api/v1/collaborations` - List my collaborations
- `GET /api/v1/collaborations/{id}` - Get details
- `POST /api/v1/collaborations/{id}/activate` - Mark active
- `POST /api/v1/collaborations/{id}/complete` - Mark completed
- `POST /api/v1/collaborations/{id}/cancel` - Cancel

### Mobile Documentation
**Status:** Completed

**Documentation Location:** `.agent/documentations/collaboration-api-mobile-docs.md`

Includes:
- Quick reference table with all 21 endpoints
- Detailed request/response examples for each endpoint
- Validation rules for all input fields
- Error response formats with error codes
- Status flow diagrams
- Rate limiting information
- Mobile implementation notes

### Backend Implementation Notes
**Pending** - Next phase will implement:
- Controllers: OpportunityController, ApplicationController, CollaborationController
- Services: OpportunityService, ApplicationService, CollaborationService
- Policies: OpportunityPolicy, ApplicationPolicy, CollaborationPolicy
- Resources: OpportunityResource, ApplicationResource, CollaborationResource
- Form Requests: CreateOpportunityRequest, UpdateOpportunityRequest, ApplyRequest, AcceptApplicationRequest, etc.

## Notes
- Database uses JSONB for flexible offer structures (business_offer, community_deliverables, categories)
- Both Business and Community users can create collab opportunities (bidirectional marketplace)
