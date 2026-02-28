# Task: community-deliverables-lookup

## Status
- Created: 2026-02-28 15:55
- Started: 2026-02-28 15:55
- Completed: (to be updated)

## Description
Update community deliverables from individual toggle fields (instagram_post, instagram_story, etc.)
to 5 grouped categories + "other" free text:

1. social_media_content — Instagram Post, Instagram Story, Reel/Short Video, TikTok Video, Photo Content (UGC)
2. event_activation — Brand integration or mention during our event
3. product_placement — Product showcase or visibility during our event
4. community_reach — Minimum attendee guarantee, access to our members, feature, community discount code
5. review_feedback — Google/social reviews, testimonials or member feedback
6. other — write your own

## Assigned Agents
- [x] @laravel-specialist

## Progress
### API Contract
GET /api/v1/lookup/community-deliverables (public)
Returns: { success, data: [{value, label, description}], meta: {total} }

### Backend
- [ ] Add `communityDeliverables()` to LookupController
- [ ] Add route to api.php
- [ ] Update CollabOpportunityFactory to new format
- [ ] Add/update tests
