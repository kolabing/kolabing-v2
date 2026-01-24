# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Kolabing is a B2B/B2C collaboration platform connecting businesses with community organizers in Spain. This repository contains the **Laravel 12 backend API** for the Mobile MVP. The frontend mobile app is in a separate repository.

**Key constraints:**
- Google OAuth only (no password authentication)
- Monthly Stripe subscription only (no credit system)
- No database triggers - all logic in Laravel service layer
- Pure PostgreSQL database (no Supabase dependency)
- Backend API only - landing page uses Blade, mobile app is separate

## Tech Stack

- **Framework:** Laravel 12 with PHP 8.3+
- **Database:** PostgreSQL 15+
- **Authentication:** Laravel Sanctum + Google OAuth
- **Payments:** Stripe (monthly subscriptions)
- **API:** RESTful JSON API with versioning (`/api/v1/`)

## Common Commands

```bash
# Development
php artisan serve                    # Start dev server
php artisan migrate                  # Run migrations
php artisan migrate:fresh --seed     # Reset database with seeds
php artisan db:seed                  # Run seeders only

# Testing
php artisan test                     # Run all tests
php artisan test --filter=TestName   # Run specific test
php artisan test tests/Feature/Auth  # Run tests in directory

# Code Generation
php artisan make:model ModelName -mfs   # Model + migration + factory + seeder
php artisan make:controller Api/V1/ControllerName --api
php artisan make:request StoreModelRequest
php artisan make:resource ModelResource
php artisan make:policy ModelPolicy --model=Model

# Queues & Cache
php artisan queue:work               # Process queue jobs
php artisan cache:clear              # Clear application cache
php artisan config:clear             # Clear config cache
```

## Architecture

### Dual-Portal User System

Single `profiles` table with `user_type` discriminator (`business` | `community`):
- **Business users:** Create opportunities, require active subscription to publish
- **Community users:** Browse and apply to opportunities, free access

Each profile type has a 1:1 extended profile table:
- `profiles` → `business_profiles` (business details, type, city)
- `profiles` → `community_profiles` (community details, type, featured flag)

### Core Workflow

```
Opportunity (draft) → publish → Application → accept → Collaboration
                                    ↓
                              decline/withdraw
```

Status flows:
- Opportunity: `draft` → `published` → `closed` → `completed`
- Application: `pending` → `accepted` | `declined` | `withdrawn`
- Collaboration: `scheduled` → `active` → `completed` | `cancelled`

### Database Schema (8 Tables MVP)

1. `profiles` - Main user table (Google OAuth)
2. `business_profiles` - Extended business user data
3. `community_profiles` - Extended community user data
4. `business_subscriptions` - Stripe monthly subscriptions
5. `cities` - City lookup
6. `collab_opportunities` - Collaboration opportunities
7. `applications` - Applications to opportunities
8. `collaborations` - Active collaborations

### Service Layer Pattern

All business logic resides in service classes, not controllers or models:

```
app/Services/
├── GoogleAuthService.php      # Google token verification, user creation
├── ProfileService.php         # Profile CRUD operations
├── OpportunityService.php     # Opportunity lifecycle management
├── ApplicationService.php     # Application accept/decline/withdraw
├── CollaborationService.php   # Collaboration status transitions
└── SubscriptionService.php    # Stripe integration
```

### API Structure

```
/api/v1/auth/google           # Google OAuth login/register
/api/v1/auth/logout           # Logout
/api/v1/auth/me               # Current user

/api/v1/profiles/{id}         # Profile CRUD
/api/v1/opportunities         # Opportunity CRUD + publish/close
/api/v1/applications          # Application accept/decline/withdraw
/api/v1/collaborations        # Collaboration status transitions
/api/v1/me/subscription       # Business subscription management

/api/v1/webhooks/stripe       # Stripe webhook receiver
/api/v1/cities                # City lookup
```

### Authorization

Use Laravel Policies for authorization, not middleware-only checks:

```php
// OpportunityPolicy
public function update(User $user, Opportunity $opportunity): bool
{
    return $user->profile->id === $opportunity->creator_profile_id;
}

public function publish(User $user, Opportunity $opportunity): bool
{
    return $this->update($user, $opportunity)
        && $user->profile->hasActiveSubscription();
}
```

### JSONB Fields

Opportunities use JSONB for flexible offer structures:

```php
// business_offer
['venue' => true, 'food_drink' => true, 'discount' => ['enabled' => true, 'percentage' => 20]]

// community_deliverables
['instagram_post' => true, 'instagram_story' => true, 'attendee_count' => 50]

// categories
['Food & Drink', 'Sports', 'Wellness']
```

## Testing Conventions

- Use `DatabaseTransactions` trait (not `RefreshDatabase`)
- Create factories for all models
- Feature tests in `tests/Feature/` organized by domain
- Unit tests for services in `tests/Unit/Services/`

```php
// Example test pattern
public function test_accepting_application_creates_collaboration(): void
{
    $creator = Profile::factory()->business()->withSubscription()->create();
    $opportunity = Opportunity::factory()->published()->for($creator)->create();
    $application = Application::factory()->for($opportunity)->create();

    $this->actingAs($creator->user)
        ->postJson("/api/v1/applications/{$application->id}/accept")
        ->assertOk();

    $this->assertDatabaseHas('collaborations', [
        'application_id' => $application->id,
        'status' => 'scheduled',
    ]);
}
```

## Key Business Rules

1. **Subscription required for business publish:** Business users must have active Stripe subscription to publish opportunities
2. **One application per user per opportunity:** Enforced by unique constraint
3. **Accepting application creates collaboration:** When accepted, a collaboration record is created and opportunity may close
4. **Google OAuth only:** No password-based authentication in MVP

## File Organization

```
.agent/                        # Agent task management
  ├── documentations/          # Generated documentation
  ├── todo/                    # Pending tasks
  ├── inprogess/              # In-progress tasks
  └── done/                   # Completed tasks

mobile_mvp_database.sql        # PostgreSQL schema for MVP
README.MD                      # Full project documentation
```

## Enum Definitions

Use PHP enums for type safety:

```php
enum UserType: string {
    case Business = 'business';
    case Community = 'community';
}

enum OfferStatus: string {
    case Draft = 'draft';
    case Published = 'published';
    case Closed = 'closed';
    case Completed = 'completed';
}

enum ApplicationStatus: string {
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Withdrawn = 'withdrawn';
}

enum CollaborationStatus: string {
    case Scheduled = 'scheduled';
    case Active = 'active';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}

enum SubscriptionStatus: string {
    case Active = 'active';
    case Cancelled = 'cancelled';
    case PastDue = 'past_due';
    case Inactive = 'inactive';
}
```
