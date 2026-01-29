# Task: Search Opportunities Endpoint Enhancement

## Status
- Created: 2026-01-29 16:20
- Started: 2026-01-29 16:20
- Completed: 2026-01-29 16:35

## Description
Enhance the search functionality in the Explore endpoint to allow businesses to search for opportunities by:
1. Community name (when browsing community-created opportunities)
2. Business name (when browsing business-created opportunities)
3. Collab request/opportunity title (already implemented)

The search should query the database using PostgreSQL ILIKE for case-insensitive matching.

## Assigned Agents
- [x] @api-designer - Define search API contract
- [x] @laravel-specialist - Implement backend search logic
- [x] @fullstack-developer - Integration and testing

## Progress

### API Contract

**Endpoint:** `GET /api/v1/opportunities`

**Query Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| `search` | string | Search term to match against opportunity title, description, and creator profile name |
| `creator_type` | string | Filter by creator type: `business` or `community` |
| `categories` | array | Filter by categories |
| `city` | string | Filter by preferred city |
| `venue_mode` | string | Filter by venue mode |
| `availability_mode` | string | Filter by availability mode |
| `availability_from` | date | Filter by start date |
| `availability_to` | date | Filter by end date |
| `per_page` | int | Items per page (1-100, default: 20) |

**Search Behavior:**
- Searches `collab_opportunities.title` (case-insensitive)
- Searches `collab_opportunities.description` (case-insensitive)
- Searches `business_profiles.name` when creator is business type (case-insensitive)
- Searches `community_profiles.name` when creator is community type (case-insensitive)
- All search conditions are OR'd together

**Example Request:**
```
GET /api/v1/opportunities?search=yoga&creator_type=community
```

**Response:** Standard paginated OpportunityCollection response

### Backend Implementation

**Modified File:** `app/Services/OpportunityService.php`

Key changes:
1. Enhanced `applyFilters()` method to search across creator profile names
2. Uses `whereHas()` with nested relationships to search in `business_profiles.name` and `community_profiles.name`
3. Database-agnostic implementation (supports both PostgreSQL ILIKE and SQLite LIKE)
4. Helper method `getCaseInsensitiveLikeOperator()` to detect database driver

### Testing

**New Test File:** `tests/Feature/Api/V1/OpportunitySearchTest.php`

Test coverage includes:
- ✅ Search finds opportunities by title
- ✅ Search is case-insensitive
- ✅ Search finds opportunities by description
- ✅ Search finds opportunities by community creator name
- ✅ Search finds opportunities by partial community name
- ✅ Search finds opportunities by business creator name
- ✅ Search matches across title, description, and creator name
- ✅ Search with no results returns empty
- ✅ Search combined with other filters
- ✅ Empty search returns all matching opportunities

**All 10 tests pass (25 assertions)**

## Notes
- Using PostgreSQL ILIKE for production (case-insensitive native)
- Using SQLite LOWER() + LIKE for testing compatibility
- Search includes both opportunity fields AND creator name
- Search term is converted to lowercase for consistent matching
