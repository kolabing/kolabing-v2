# Task: Public Profile Endpoints

## Status
- Created: 2026-01-30 18:00
- Started: 2026-01-30 18:00
- Completed:

## Description
Implement 2 new public profile endpoints:
1. GET /api/v1/profiles/{profile_id} - Public profile info (flat response)
2. GET /api/v1/profiles/{profile_id}/collaborations - Completed collaborations

## Assigned Agents
- [x] @backend-developer
- [x] @laravel-specialist

## Progress
### API Contract
Per mobile requirements doc - flat response with display_name, type, city_name fields.

### Backend
- New PublicProfileResource (flat format)
- New PublicCollaborationResource (partner info)
- Add methods to ProfileController + ProfileService
- Add routes
- Feature tests
