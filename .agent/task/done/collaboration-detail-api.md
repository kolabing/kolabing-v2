# Task: Collaboration Detail & Event Management API

## Status
- Created: 2026-02-08 23:00
- Started: 2026-02-08 23:10
- Completed: (pending)

## Gap Analysis

### Already Exists
- Collaboration model with status, contact_methods, business/community profiles
- GET /api/v1/collaborations/{id} (show) - needs enhancement
- POST activate/complete/cancel endpoints
- CollaborationResource, CollaborationService, CollaborationPolicy
- Challenge system (48 system challenges with categories)
- CollabOpportunity has business_offer, community_deliverables, availability_mode

### Needs Building
1. **Migration**: `collaboration_challenges` pivot table + add `event_id`/`qr_code_url` to collaborations
2. **Collaboration model**: Add `challenges()` BelongsToMany + `event()` relationship
3. **Enhanced CollaborationResource**: Add opportunity details, challenges, selected_challenge_ids
4. **CollaborationChallengeController**: PUT (sync) + POST (create custom)
5. **SystemChallengeController**: GET /api/v1/challenges/system
6. **CollaborationQrCodeController**: POST generate QR
7. **Routes**: New endpoints
8. **Tests**: Full coverage

### Key Decisions
- Collaboration uses `active` (not `in_progress`) - keep existing enum
- Challenges linked via pivot `collaboration_challenges` table
- Custom challenges get `event_id` from collaboration's event
- QR code: generate checkin_token URL (reuses existing Event checkin system)

## Implementation Steps

### Step 1: Migration
- Create `collaboration_challenges` pivot table (uuid pk, collaboration_id, challenge_id, unique constraint)
- Add `event_id` (nullable FK to events) and `qr_code_url` (nullable text) to collaborations

### Step 2: Model Updates
- Collaboration: add challenges() BelongsToMany, event() BelongsTo
- Update fillable + casts

### Step 3: Enhanced CollaborationResource
- Add business_offer + community_deliverables from collabOpportunity
- Add opportunity details (title, description, availability)
- Add challenges collection + selected_challenge_ids array
- Add event_id + qr_code_url

### Step 4: CollaborationChallengeController + Service
- PUT sync selected challenges (validate IDs exist)
- POST create custom challenge (tied to collaboration's event)

### Step 5: SystemChallengeController
- GET list all is_system=true challenges with category grouping

### Step 6: QR Code
- POST generate event + checkin_token for collaboration

### Step 7: Routes + FormRequests

### Step 8: Tests

## Notes
- OpportunitySummaryResource already exists but doesn't include business_offer/community_deliverables — enhance it
- The spec's `business_partner`/`community_partner` maps to existing businessProfile/communityProfile relationships
