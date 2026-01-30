# Task: Hide Applied Opportunities from Explore

## Status
- Created: 2026-01-30 13:00
- Started:
- Completed:

## Description
When a user browses opportunities in the explore feed (GET /api/v1/opportunities), opportunities they have already applied to should be excluded from the results. This prevents users from seeing opportunities they've already interacted with.

### Requirements
- Exclude opportunities where the viewer has any application (pending, accepted, declined, withdrawn)
- Only affects the browse/explore endpoint, not "my opportunities"
- Update tests to verify the filtering behavior

## Assigned Agents
- [ ] @backend-developer - Update browse query
- [ ] @laravel-specialist - Tests

## Progress
### API Contract
(to be filled)

### Backend
(to be filled)

## Notes
- Uses existing `applications` table relationship
- No schema changes needed
