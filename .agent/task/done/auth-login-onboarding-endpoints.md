# Task: Auth Login & Onboarding Endpoints

## Status
- Created: 2026-01-24 22:35
- Started: 2026-01-24 22:35
- Completed: 2026-01-24 23:15

## Description
Implement authentication login endpoints with Google OAuth and onboarding flow for both user types (business and community).

### Requirements:
1. Google OAuth login/register endpoint
2. User type selection during registration (business/community)
3. Onboarding flow for business users (business_profiles)
4. Onboarding flow for community users (community_profiles)
5. Get current user endpoint (/me)
6. Logout endpoint
7. Mobile app API documentation

### Database Tables Involved:
- profiles (main user table with google_id, user_type)
- business_profiles (1:1 extended data for business users)
- community_profiles (1:1 extended data for community users)
- business_subscriptions (auto-created for business users)
- cities (lookup for city selection)

## Assigned Agents
- [x] @api-designer - Design API contract ✅
- [x] @laravel-specialist - Implement backend ✅
- [x] @backend-developer - Implement services ✅

## Progress

### API Contract
✅ Completed - See `/api-contract-auth-onboarding.md`

**Endpoints Designed:**
1. POST /api/v1/auth/google - Google OAuth login/register
2. GET /api/v1/auth/me - Get authenticated user
3. POST /api/v1/auth/logout - Revoke token
4. PUT /api/v1/onboarding/business - Complete business onboarding
5. PUT /api/v1/onboarding/community - Complete community onboarding
6. GET /api/v1/cities - City lookup
7. GET /api/v1/lookup/business-types - Business types
8. GET /api/v1/lookup/community-types - Community types

### Backend Implementation
✅ Completed by @laravel-specialist

**Files Created:**

**Migrations (9 files):**
- cities, profiles, business_profiles, community_profiles
- business_subscriptions, collab_opportunities, applications, collaborations
- personal_access_tokens (Sanctum)

**Enums (5 files):**
- UserType, SubscriptionStatus, OfferStatus, ApplicationStatus, CollaborationStatus

**Models (5 files):**
- Profile (main user with Sanctum), BusinessProfile, CommunityProfile
- BusinessSubscription, City

**Services (4 files):**
- GoogleAuthService, AuthService, ProfileService, OnboardingService

**Form Requests (3 files):**
- GoogleLoginRequest, BusinessOnboardingRequest, CommunityOnboardingRequest

**API Resources (6 files):**
- CityResource, SubscriptionResource, BusinessProfileResource
- CommunityProfileResource, ProfileResource, UserResource

**Controllers (3 files):**
- AuthController, OnboardingController, LookupController

**Middleware:**
- EnsureUserType

**Tests:**
- 31 tests, 314 assertions - ALL PASSING

### Documentation
✅ Completed

**Documentation Created:**
- `/api-contract-auth-onboarding.md` - Full API contract specification
- `/docs/MOBILE_API_DOCUMENTATION.md` - Mobile app integration guide

**Mobile Documentation Includes:**
- Authentication flow diagrams
- Endpoint specifications with examples
- iOS (Swift) and Android (Kotlin) code samples
- Error handling guide
- Token storage best practices
- Caching recommendations

## Notes
- Google OAuth only - no password authentication
- Business users get inactive subscription record on creation
- Community users are free to use
- Onboarding collects extended profile data after initial login
