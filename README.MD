# Kolabing Mobile MVP - Minimal Database Architecture

> **Version:** 2.1
> **Last Updated:** 2026-01-24
> **Target:** Mobile MVP (iOS/Android)
> **Database:** Pure PostgreSQL (No Supabase dependency)
> **Authentication:** Google OAuth (No password-based auth)
> **Monetization:** Monthly Subscription only (No credit system)
> **Key Constraints:**
> - No Database Triggers - All logic handled in Laravel Service Layer
> - No Edge Functions - Pure API-based Architecture
> - Minimal Schema - Only MVP-essential tables
> - Google OAuth only - No password authentication
> - Simple subscription - No credit system

---

## Table of Contents

1. [Schema Comparison](#schema-comparison)
2. [Minimal MVP Schema](#minimal-mvp-schema)
3. [Entity Descriptions](#entity-descriptions)
4. [Enum Types](#enum-types)
5. [Migration Script](#migration-script)
6. [Phase Roadmap](#phase-roadmap)
7. [API Endpoints (MVP)](#api-endpoints-mvp)
8. [Data Flow Diagrams](#data-flow-diagrams)

---

## Schema Comparison

### Current vs MVP Schema Analysis

| Table | Current | MVP Phase 1 | Reason |
|-------|---------|-------------|--------|
| **profiles** | Yes | YES | Core - Google OAuth authentication |
| **business_profiles** | Yes | YES | Core - Business user details |
| **community_profiles** | Yes | YES | Core - Community user details |
| **cities** | Yes | YES | Core - Location lookup |
| **collab_opportunities** | Yes | YES | Core - Main content |
| **applications** | Yes | YES | Core - User interactions |
| **collaborations** | Yes | YES | Core - Active partnerships |
| **business_subscriptions** | Yes | YES | MVP - Monthly subscription (Stripe) |
| **business_credits** | Yes | NO | NOT NEEDED - No credit system |
| **credit_transactions** | Yes | NO | NOT NEEDED - No credit system |
| **invitation_codes** | Yes | NO | Phase 2 - Referral |
| **referral_rules** | Yes | NO | Phase 2 - Referral |
| **user_referral_progress** | Yes | NO | Phase 2 - Referral |
| **referral_redemptions** | Yes | NO | Phase 2 - Referral |
| **refund_transactions** | Yes | NO | NOT NEEDED - No credit system |
| **reviews** | Yes | NO | Phase 2 - Engagement |
| **surveys** | Yes | NO | Phase 2 - Engagement |
| **community_events** | Yes | NO | Not MVP - Nice to have |
| **community_events_photos** | Yes | NO | Not MVP - Nice to have |
| **analytics_events** | Yes | NO | Phase 2 - Analytics |
| **success_stories** | Yes | NO | Not MVP - CMS content |

### Summary

| Metric | Current | MVP Phase 1 | Reduction |
|--------|---------|-------------|-----------|
| **Total Tables** | 21 | 8 | -62% |
| **Enum Types** | 6 | 5 | -17% |
| **Relationships** | 35+ | 14 | -60% |

---

## Minimal MVP Schema

### UML Diagram

```
┌─────────────────────────────────────────────────────────────────────────────────────┐
│                     KOLABING MOBILE MVP - MINIMAL DATABASE SCHEMA                    │
│                          (8 Tables + Google OAuth + Stripe)                          │
└─────────────────────────────────────────────────────────────────────────────────────┘

                                    ┌─────────────┐
                                    │   cities    │
                                    ├─────────────┤
                                    │ id (PK)     │
                                    │ name        │
                                    │ country     │
                                    │ created_at  │
                                    └──────┬──────┘
                                           │
                    ┌──────────────────────┼──────────────────────┐
                    │                      │                      │
                    ▼                      ▼                      │
┌───────────────────────────┐  ┌───────────────────────────┐     │
│     business_profiles     │  │    community_profiles     │     │
├───────────────────────────┤  ├───────────────────────────┤     │
│ id (PK)                   │  │ id (PK)                   │     │
│ profile_id (FK,UNIQUE)    │  │ profile_id (FK,UNIQUE)    │     │
│ name                      │  │ name                      │     │
│ about                     │  │ about                     │     │
│ business_type             │  │ community_type            │     │
│ city_id (FK) ─────────────┼──│ city_id (FK) ─────────────┼─────┘
│ instagram                 │  │ instagram                 │
│ website                   │  │ tiktok                    │
│ profile_photo             │  │ website                   │
│ created_at                │  │ profile_photo             │
│ updated_at                │  │ is_featured               │
└───────────┬───────────────┘  │ created_at                │
            │                  │ updated_at                │
            │                  └───────────┬───────────────┘
            │                              │
            │  ┌───────────────────────────┴───────────────────────────┐
            │  │                                                       │
            ▼  ▼                                                       ▼
┌───────────────────────────────────────────────────────────────────────────────────┐
│                                     profiles                                       │
├───────────────────────────────────────────────────────────────────────────────────┤
│ id (PK) UUID                                                                      │
│ email (UNIQUE) NOT NULL                                                           │
│ phone_number                                                                      │
│ user_type: 'business' | 'community' NOT NULL                                      │
│ google_id VARCHAR(255) UNIQUE                       ← Google OAuth                │
│ avatar_url TEXT                                     ← From Google                 │
│ email_verified_at TIMESTAMP                                                       │
│ created_at                                                                        │
│ updated_at                                                                        │
└───────────────────────────────────────────────────────────────────────────────────┘
            │
            │ 1:1 (for business users only)
            ▼
┌───────────────────────────────────────────────────────────────────────────────────┐
│                            business_subscriptions                                  │
├───────────────────────────────────────────────────────────────────────────────────┤
│ id (PK) UUID                                                                      │
│ profile_id (FK → profiles) UNIQUE NOT NULL          ← Business user              │
│ stripe_customer_id VARCHAR(255)                     ← Stripe customer            │
│ stripe_subscription_id VARCHAR(255)                 ← Stripe subscription        │
│ status: 'active' | 'cancelled' | 'past_due' | 'inactive' DEFAULT 'inactive'      │
│ current_period_start TIMESTAMP                                                    │
│ current_period_end TIMESTAMP                                                      │
│ cancel_at_period_end BOOLEAN DEFAULT false                                        │
│ created_at                                                                        │
│ updated_at                                                                        │
└───────────────────────────────────────────────────────────────────────────────────┘
            │
            │ creator_profile_id
            │ applicant_profile_id
            ▼
┌───────────────────────────────────────────────────────────────────────────────────┐
│                              collab_opportunities                                  │
├───────────────────────────────────────────────────────────────────────────────────┤
│ id (PK) UUID                                                                      │
│ creator_profile_id (FK → profiles) NOT NULL                                       │
│ creator_profile_type: 'business' | 'community' NOT NULL                           │
│ title VARCHAR(255) NOT NULL                                                       │
│ description TEXT NOT NULL                                                         │
│ status: 'draft' | 'published' | 'closed' | 'completed' DEFAULT 'draft'            │
│ business_offer JSONB DEFAULT '{}'                                                 │
│ community_deliverables JSONB DEFAULT '{}'                                         │
│ categories JSONB DEFAULT '[]'                                                     │
│ availability_mode VARCHAR(50)                                                     │
│ availability_start DATE                                                           │
│ availability_end DATE                                                             │
│ venue_mode VARCHAR(50)                                                            │
│ address TEXT                                                                      │
│ preferred_city VARCHAR(100)                                                       │
│ offer_photo TEXT                                                                  │
│ published_at TIMESTAMP                                                            │
│ created_at TIMESTAMP DEFAULT NOW()                                                │
│ updated_at TIMESTAMP DEFAULT NOW()                                                │
└───────────────────────────────────────────────────────────────────────────────────┘
                    │
                    │ 1:N
                    ▼
┌───────────────────────────────────────────────────────────────────────────────────┐
│                                  applications                                      │
├───────────────────────────────────────────────────────────────────────────────────┤
│ id (PK) UUID                                                                      │
│ collab_opportunity_id (FK → collab_opportunities) NOT NULL                        │
│ applicant_profile_id (FK → profiles) NOT NULL                                     │
│ applicant_profile_type: 'business' | 'community' NOT NULL                         │
│ message TEXT                                                                      │
│ availability TEXT                                                                 │
│ status: 'pending' | 'accepted' | 'declined' | 'withdrawn' DEFAULT 'pending'       │
│ created_at TIMESTAMP DEFAULT NOW()                                                │
│ updated_at TIMESTAMP DEFAULT NOW()                                                │
│                                                                                   │
│ UNIQUE(collab_opportunity_id, applicant_profile_id)                               │
└───────────────────────────────────────────────────────────────────────────────────┘
                    │
                    │ 1:1 (when accepted)
                    ▼
┌───────────────────────────────────────────────────────────────────────────────────┐
│                                 collaborations                                     │
├───────────────────────────────────────────────────────────────────────────────────┤
│ id (PK) UUID                                                                      │
│ application_id (FK → applications) UNIQUE NOT NULL                                │
│ collab_opportunity_id (FK → collab_opportunities) NOT NULL                        │
│ creator_profile_id (FK → profiles) NOT NULL                                       │
│ applicant_profile_id (FK → profiles) NOT NULL                                     │
│ business_profile_id (FK → business_profiles)                                      │
│ community_profile_id (FK → community_profiles)                                    │
│ status: 'scheduled' | 'active' | 'completed' | 'cancelled' DEFAULT 'scheduled'    │
│ scheduled_date DATE                                                               │
│ completed_at TIMESTAMP                                                            │
│ contact_methods JSONB DEFAULT '{}'                                                │
│ created_at TIMESTAMP DEFAULT NOW()                                                │
│ updated_at TIMESTAMP DEFAULT NOW()                                                │
└───────────────────────────────────────────────────────────────────────────────────┘
```

### Entity Relationship Summary

```
profiles (1) ←──────── (1) business_profiles
profiles (1) ←──────── (1) community_profiles
profiles (1) ←──────── (N) collab_opportunities (creator)
profiles (1) ←──────── (N) applications (applicant)
profiles (1) ←──────── (N) collaborations (as creator or applicant)

cities (1) ←──────── (N) business_profiles
cities (1) ←──────── (N) community_profiles

collab_opportunities (1) ←──────── (N) applications
applications (1) ←──────── (0..1) collaborations
```

---

## Entity Descriptions

### Core Entities

#### `profiles`
Ana kullanici tablosu. Google OAuth ile authentication.

| Field | Type | Constraints | Description |
|-------|------|-------------|-------------|
| id | UUID | PK, DEFAULT gen_random_uuid() | Unique identifier |
| email | VARCHAR(255) | UNIQUE, NOT NULL | User email (from Google) |
| phone_number | VARCHAR(20) | | Optional phone |
| user_type | user_type | NOT NULL | 'business' or 'community' |
| google_id | VARCHAR(255) | UNIQUE | Google OAuth user ID |
| avatar_url | TEXT | | Profile photo from Google |
| email_verified_at | TIMESTAMP | | Email verification date |
| created_at | TIMESTAMP | DEFAULT NOW() | |
| updated_at | TIMESTAMP | DEFAULT NOW() | |

**Note:** Password-based auth removed. Only Google OAuth supported.

#### `business_subscriptions`
Business kullanicilar icin Stripe monthly subscription.

| Field | Type | Constraints | Description |
|-------|------|-------------|-------------|
| id | UUID | PK, DEFAULT gen_random_uuid() | Unique identifier |
| profile_id | UUID | FK, UNIQUE, NOT NULL | Reference to profiles |
| stripe_customer_id | VARCHAR(255) | | Stripe customer ID |
| stripe_subscription_id | VARCHAR(255) | | Stripe subscription ID |
| status | subscription_status | DEFAULT 'inactive' | Subscription status |
| current_period_start | TIMESTAMP | | Billing period start |
| current_period_end | TIMESTAMP | | Billing period end |
| cancel_at_period_end | BOOLEAN | DEFAULT false | Will cancel at period end |
| created_at | TIMESTAMP | DEFAULT NOW() | |
| updated_at | TIMESTAMP | DEFAULT NOW() | |

**Note:** Only monthly subscription. No credit system.

#### `business_profiles`
Business kullanicilarin detay bilgileri.

| Field | Type | Constraints | Description |
|-------|------|-------------|-------------|
| id | UUID | PK, DEFAULT gen_random_uuid() | Unique identifier |
| profile_id | UUID | FK, UNIQUE, NOT NULL | Reference to profiles |
| name | VARCHAR(255) | | Business name |
| about | TEXT | | Business description |
| business_type | VARCHAR(100) | | Industry type |
| city_id | UUID | FK | Reference to cities |
| instagram | VARCHAR(255) | | Instagram handle |
| website | VARCHAR(255) | | Website URL |
| profile_photo | TEXT | | Photo URL |
| created_at | TIMESTAMP | DEFAULT NOW() | |
| updated_at | TIMESTAMP | DEFAULT NOW() | |

#### `community_profiles`
Community kullanicilarin detay bilgileri.

| Field | Type | Constraints | Description |
|-------|------|-------------|-------------|
| id | UUID | PK, DEFAULT gen_random_uuid() | Unique identifier |
| profile_id | UUID | FK, UNIQUE, NOT NULL | Reference to profiles |
| name | VARCHAR(255) | | Community name |
| about | TEXT | | Community description |
| community_type | VARCHAR(100) | | Community type |
| city_id | UUID | FK | Reference to cities |
| instagram | VARCHAR(255) | | Instagram handle |
| tiktok | VARCHAR(255) | | TikTok handle |
| website | VARCHAR(255) | | Website URL |
| profile_photo | TEXT | | Photo URL |
| is_featured | BOOLEAN | DEFAULT false | Featured flag |
| created_at | TIMESTAMP | DEFAULT NOW() | |
| updated_at | TIMESTAMP | DEFAULT NOW() | |

#### `cities`
Sehir lookup tablosu.

| Field | Type | Constraints | Description |
|-------|------|-------------|-------------|
| id | UUID | PK, DEFAULT gen_random_uuid() | Unique identifier |
| name | VARCHAR(100) | NOT NULL, UNIQUE | City name |
| country | VARCHAR(100) | DEFAULT 'Spain' | Country name |
| created_at | TIMESTAMP | DEFAULT NOW() | |

#### `collab_opportunities`
Is birligi firsatlari.

| Field | Type | Constraints | Description |
|-------|------|-------------|-------------|
| id | UUID | PK | Unique identifier |
| creator_profile_id | UUID | FK, NOT NULL | Creator reference |
| creator_profile_type | user_type | NOT NULL | Creator type |
| title | VARCHAR(255) | NOT NULL | Opportunity title |
| description | TEXT | NOT NULL | Full description |
| status | offer_status | DEFAULT 'draft' | Current status |
| business_offer | JSONB | DEFAULT '{}' | What business offers |
| community_deliverables | JSONB | DEFAULT '{}' | What community delivers |
| categories | JSONB | DEFAULT '[]' | Category tags |
| availability_mode | VARCHAR(50) | | one_time, recurring |
| availability_start | DATE | | Start date |
| availability_end | DATE | | End date |
| venue_mode | VARCHAR(50) | | business_venue, community_venue |
| address | TEXT | | Location address |
| preferred_city | VARCHAR(100) | | Preferred city |
| offer_photo | TEXT | | Photo URL |
| published_at | TIMESTAMP | | Publish timestamp |
| created_at | TIMESTAMP | DEFAULT NOW() | |
| updated_at | TIMESTAMP | DEFAULT NOW() | |

#### `applications`
Firsatlara yapilan basvurular.

| Field | Type | Constraints | Description |
|-------|------|-------------|-------------|
| id | UUID | PK | Unique identifier |
| collab_opportunity_id | UUID | FK, NOT NULL | Opportunity reference |
| applicant_profile_id | UUID | FK, NOT NULL | Applicant reference |
| applicant_profile_type | user_type | NOT NULL | Applicant type |
| message | TEXT | | Application message |
| availability | TEXT | | Applicant availability |
| status | application_status | DEFAULT 'pending' | Current status |
| created_at | TIMESTAMP | DEFAULT NOW() | |
| updated_at | TIMESTAMP | DEFAULT NOW() | |

**Unique Constraint:** One application per user per opportunity.

#### `collaborations`
Kabul edilen basvurulardan olusan is birliktelikleri.

| Field | Type | Constraints | Description |
|-------|------|-------------|-------------|
| id | UUID | PK | Unique identifier |
| application_id | UUID | FK, UNIQUE, NOT NULL | Application reference |
| collab_opportunity_id | UUID | FK, NOT NULL | Opportunity reference |
| creator_profile_id | UUID | FK, NOT NULL | Creator reference |
| applicant_profile_id | UUID | FK, NOT NULL | Applicant reference |
| business_profile_id | UUID | FK | Business participant |
| community_profile_id | UUID | FK | Community participant |
| status | collaboration_status | DEFAULT 'scheduled' | Current status |
| scheduled_date | DATE | | Scheduled date |
| completed_at | TIMESTAMP | | Completion timestamp |
| contact_methods | JSONB | DEFAULT '{}' | Contact info |
| created_at | TIMESTAMP | DEFAULT NOW() | |
| updated_at | TIMESTAMP | DEFAULT NOW() | |

---

## Enum Types

```sql
-- User type discriminator
CREATE TYPE user_type AS ENUM ('business', 'community');

-- Application status flow
CREATE TYPE application_status AS ENUM ('pending', 'accepted', 'declined', 'withdrawn');

-- Opportunity status flow
CREATE TYPE offer_status AS ENUM ('draft', 'published', 'closed', 'completed');

-- Collaboration status flow
CREATE TYPE collaboration_status AS ENUM ('scheduled', 'active', 'completed', 'cancelled');

-- Subscription status (for business users)
CREATE TYPE subscription_status AS ENUM ('active', 'cancelled', 'past_due', 'inactive');
```

### Status Flow Diagrams

```
Opportunity Status:
  draft ──► published ──► closed ──► completed
                │
                └──► closed (early close)

Application Status:
  pending ──► accepted ──► (creates collaboration)
     │
     ├──► declined
     │
     └──► withdrawn (by applicant)

Collaboration Status:
  scheduled ──► active ──► completed
       │           │
       └───────────┴──► cancelled
```

---

## Migration Script

```sql
-- ============================================================================
-- KOLABING MOBILE MVP - MINIMAL DATABASE SCHEMA
-- Version: 2.1
-- Date: 2026-01-24
-- Database: PostgreSQL 15+
-- Auth: Google OAuth only
-- Monetization: Monthly subscription only (no credits)
-- ============================================================================

-- Enable UUID extension
CREATE EXTENSION IF NOT EXISTS "pgcrypto";

-- ============================================================================
-- ENUM TYPES
-- ============================================================================

CREATE TYPE user_type AS ENUM ('business', 'community');
CREATE TYPE application_status AS ENUM ('pending', 'accepted', 'declined', 'withdrawn');
CREATE TYPE offer_status AS ENUM ('draft', 'published', 'closed', 'completed');
CREATE TYPE collaboration_status AS ENUM ('scheduled', 'active', 'completed', 'cancelled');
CREATE TYPE subscription_status AS ENUM ('active', 'cancelled', 'past_due', 'inactive');

-- ============================================================================
-- TABLES
-- ============================================================================

-- Cities lookup table
CREATE TABLE cities (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(100) NOT NULL UNIQUE,
    country VARCHAR(100) DEFAULT 'Spain',
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- Main profiles table (Google OAuth only)
CREATE TABLE profiles (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    email VARCHAR(255) NOT NULL UNIQUE,
    phone_number VARCHAR(20),
    user_type user_type NOT NULL,
    google_id VARCHAR(255) UNIQUE,
    avatar_url TEXT,
    email_verified_at TIMESTAMP WITH TIME ZONE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- Business profiles (1:1 with profiles)
CREATE TABLE business_profiles (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    profile_id UUID NOT NULL UNIQUE REFERENCES profiles(id) ON DELETE CASCADE,
    name VARCHAR(255),
    about TEXT,
    business_type VARCHAR(100),
    city_id UUID REFERENCES cities(id) ON DELETE SET NULL,
    instagram VARCHAR(255),
    website VARCHAR(255),
    profile_photo TEXT,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- Community profiles (1:1 with profiles)
CREATE TABLE community_profiles (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    profile_id UUID NOT NULL UNIQUE REFERENCES profiles(id) ON DELETE CASCADE,
    name VARCHAR(255),
    about TEXT,
    community_type VARCHAR(100),
    city_id UUID REFERENCES cities(id) ON DELETE SET NULL,
    instagram VARCHAR(255),
    tiktok VARCHAR(255),
    website VARCHAR(255),
    profile_photo TEXT,
    is_featured BOOLEAN DEFAULT false,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- Business subscriptions (monthly only, for business users)
CREATE TABLE business_subscriptions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    profile_id UUID NOT NULL UNIQUE REFERENCES profiles(id) ON DELETE CASCADE,
    stripe_customer_id VARCHAR(255),
    stripe_subscription_id VARCHAR(255),
    status subscription_status DEFAULT 'inactive',
    current_period_start TIMESTAMP WITH TIME ZONE,
    current_period_end TIMESTAMP WITH TIME ZONE,
    cancel_at_period_end BOOLEAN DEFAULT false,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- Collaboration opportunities
CREATE TABLE collab_opportunities (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    creator_profile_id UUID NOT NULL REFERENCES profiles(id) ON DELETE CASCADE,
    creator_profile_type user_type NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    status offer_status DEFAULT 'draft',
    business_offer JSONB DEFAULT '{}',
    community_deliverables JSONB DEFAULT '{}',
    categories JSONB DEFAULT '[]',
    availability_mode VARCHAR(50),
    availability_start DATE,
    availability_end DATE,
    venue_mode VARCHAR(50),
    address TEXT,
    preferred_city VARCHAR(100),
    offer_photo TEXT,
    published_at TIMESTAMP WITH TIME ZONE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- Applications to opportunities
CREATE TABLE applications (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    collab_opportunity_id UUID NOT NULL REFERENCES collab_opportunities(id) ON DELETE CASCADE,
    applicant_profile_id UUID NOT NULL REFERENCES profiles(id) ON DELETE CASCADE,
    applicant_profile_type user_type NOT NULL,
    message TEXT,
    availability TEXT,
    status application_status DEFAULT 'pending',
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),

    -- One application per user per opportunity
    UNIQUE(collab_opportunity_id, applicant_profile_id)
);

-- Active collaborations (created when application is accepted)
CREATE TABLE collaborations (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    application_id UUID NOT NULL UNIQUE REFERENCES applications(id) ON DELETE CASCADE,
    collab_opportunity_id UUID NOT NULL REFERENCES collab_opportunities(id) ON DELETE CASCADE,
    creator_profile_id UUID NOT NULL REFERENCES profiles(id) ON DELETE CASCADE,
    applicant_profile_id UUID NOT NULL REFERENCES profiles(id) ON DELETE CASCADE,
    business_profile_id UUID REFERENCES business_profiles(id) ON DELETE SET NULL,
    community_profile_id UUID REFERENCES community_profiles(id) ON DELETE SET NULL,
    status collaboration_status DEFAULT 'scheduled',
    scheduled_date DATE,
    completed_at TIMESTAMP WITH TIME ZONE,
    contact_methods JSONB DEFAULT '{}',
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

-- ============================================================================
-- INDEXES
-- ============================================================================

-- Profiles
CREATE INDEX idx_profiles_email ON profiles(email);
CREATE INDEX idx_profiles_google_id ON profiles(google_id);
CREATE INDEX idx_profiles_user_type ON profiles(user_type);

-- Business subscriptions
CREATE INDEX idx_subscriptions_profile ON business_subscriptions(profile_id);
CREATE INDEX idx_subscriptions_stripe_customer ON business_subscriptions(stripe_customer_id);
CREATE INDEX idx_subscriptions_status ON business_subscriptions(status);
CREATE INDEX idx_subscriptions_status_active ON business_subscriptions(status) WHERE status = 'active';

-- Business profiles
CREATE INDEX idx_business_profiles_profile_id ON business_profiles(profile_id);
CREATE INDEX idx_business_profiles_city_id ON business_profiles(city_id);
CREATE INDEX idx_business_profiles_business_type ON business_profiles(business_type);

-- Community profiles
CREATE INDEX idx_community_profiles_profile_id ON community_profiles(profile_id);
CREATE INDEX idx_community_profiles_city_id ON community_profiles(city_id);
CREATE INDEX idx_community_profiles_community_type ON community_profiles(community_type);
CREATE INDEX idx_community_profiles_is_featured ON community_profiles(is_featured) WHERE is_featured = true;

-- Opportunities
CREATE INDEX idx_opportunities_creator ON collab_opportunities(creator_profile_id);
CREATE INDEX idx_opportunities_status ON collab_opportunities(status);
CREATE INDEX idx_opportunities_status_published ON collab_opportunities(status) WHERE status = 'published';
CREATE INDEX idx_opportunities_creator_type ON collab_opportunities(creator_profile_type);
CREATE INDEX idx_opportunities_created_at ON collab_opportunities(created_at DESC);
CREATE INDEX idx_opportunities_categories ON collab_opportunities USING GIN (categories);

-- Applications
CREATE INDEX idx_applications_opportunity ON applications(collab_opportunity_id);
CREATE INDEX idx_applications_applicant ON applications(applicant_profile_id);
CREATE INDEX idx_applications_status ON applications(status);
CREATE INDEX idx_applications_status_pending ON applications(status) WHERE status = 'pending';

-- Collaborations
CREATE INDEX idx_collaborations_application ON collaborations(application_id);
CREATE INDEX idx_collaborations_opportunity ON collaborations(collab_opportunity_id);
CREATE INDEX idx_collaborations_creator ON collaborations(creator_profile_id);
CREATE INDEX idx_collaborations_applicant ON collaborations(applicant_profile_id);
CREATE INDEX idx_collaborations_status ON collaborations(status);
CREATE INDEX idx_collaborations_status_active ON collaborations(status) WHERE status IN ('scheduled', 'active');

-- ============================================================================
-- SEED DATA
-- ============================================================================

-- Insert default cities
INSERT INTO cities (name, country) VALUES
    ('Barcelona', 'Spain'),
    ('Madrid', 'Spain'),
    ('Valencia', 'Spain'),
    ('Sevilla', 'Spain'),
    ('Bilbao', 'Spain'),
    ('Malaga', 'Spain'),
    ('Zaragoza', 'Spain'),
    ('Palma', 'Spain');

-- ============================================================================
-- COMMENTS
-- ============================================================================

COMMENT ON TABLE profiles IS 'Main user table with Google OAuth';
COMMENT ON TABLE business_profiles IS 'Extended profile for business users';
COMMENT ON TABLE community_profiles IS 'Extended profile for community users';
COMMENT ON TABLE business_subscriptions IS 'Stripe monthly subscriptions for business users';
COMMENT ON TABLE cities IS 'City lookup table';
COMMENT ON TABLE collab_opportunities IS 'Collaboration opportunities created by users';
COMMENT ON TABLE applications IS 'Applications to collaboration opportunities';
COMMENT ON TABLE collaborations IS 'Active collaborations created from accepted applications';

COMMENT ON COLUMN profiles.google_id IS 'Google OAuth user ID';
COMMENT ON COLUMN profiles.user_type IS 'Discriminator for profile type';
COMMENT ON COLUMN business_subscriptions.status IS 'Stripe subscription status';
COMMENT ON COLUMN collab_opportunities.business_offer IS 'JSON: venue, food_drink, discount, products, other';
COMMENT ON COLUMN collab_opportunities.community_deliverables IS 'JSON: instagram_post, story, tiktok, attendee_count';
COMMENT ON COLUMN collaborations.contact_methods IS 'JSON: whatsapp, email, instagram';
```

---

## Phase Roadmap

### Phase 1: Core MVP (Current)

**8 Tables:**
- profiles (Google OAuth)
- business_profiles
- community_profiles
- cities
- collab_opportunities
- applications
- collaborations
- business_subscriptions (Stripe monthly)

**Features:**
- Google OAuth login
- Profile management
- Create/browse opportunities
- Apply to opportunities
- Accept/decline applications
- View collaborations
- Monthly subscription (Stripe)

### Phase 2: Engagement (Future)

**Additional Tables:**
- reviews
- surveys
- push_notification_tokens
- messages (optional in-app chat)

**Features:**
- Push notifications
- Reviews & ratings
- Post-collaboration surveys
- In-app messaging

### Phase 3: Referral (Future)

**Additional Tables:**
- invitation_codes
- referral_rules
- user_referral_progress
- referral_redemptions

**Features:**
- Referral program
- Invitation codes
- Referral rewards

**NOT Planned:**
- Credit system (removed from roadmap)

---

## API Endpoints (MVP)

### Authentication (Google OAuth)

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | /api/v1/auth/google | Google OAuth login/register |
| POST | /api/v1/auth/google/callback | Google OAuth callback |
| POST | /api/v1/auth/logout | Logout user |
| GET | /api/v1/auth/me | Get current user |

**Google OAuth Flow:**
```
1. Mobile app gets Google ID token
2. POST /api/v1/auth/google with {id_token, user_type}
3. Backend verifies token with Google
4. Creates/updates profile
5. Returns JWT token
```

### Profiles

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | /api/v1/profiles/{id} | Get profile |
| PUT | /api/v1/profiles/{id} | Update profile |
| POST | /api/v1/profiles/{id}/photo | Upload photo |

### Opportunities

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | /api/v1/opportunities | List opportunities (browse) |
| POST | /api/v1/opportunities | Create opportunity |
| GET | /api/v1/opportunities/{id} | Get opportunity |
| PUT | /api/v1/opportunities/{id} | Update opportunity |
| DELETE | /api/v1/opportunities/{id} | Delete opportunity |
| POST | /api/v1/opportunities/{id}/publish | Publish opportunity |
| POST | /api/v1/opportunities/{id}/close | Close opportunity |
| GET | /api/v1/me/opportunities | Get my opportunities |

### Applications

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | /api/v1/opportunities/{id}/apply | Apply to opportunity |
| GET | /api/v1/opportunities/{id}/applications | Get applications for opportunity |
| POST | /api/v1/applications/{id}/accept | Accept application |
| POST | /api/v1/applications/{id}/decline | Decline application |
| POST | /api/v1/applications/{id}/withdraw | Withdraw application |
| GET | /api/v1/me/applications | Get my applications |

### Collaborations

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | /api/v1/collaborations/{id} | Get collaboration |
| POST | /api/v1/collaborations/{id}/schedule | Schedule collaboration |
| POST | /api/v1/collaborations/{id}/activate | Activate collaboration |
| POST | /api/v1/collaborations/{id}/complete | Complete collaboration |
| POST | /api/v1/collaborations/{id}/cancel | Cancel collaboration |
| GET | /api/v1/me/collaborations | Get my collaborations |

### Subscriptions (Business only)

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | /api/v1/me/subscription | Get subscription status |
| POST | /api/v1/subscriptions/checkout | Create Stripe checkout session |
| POST | /api/v1/subscriptions/cancel | Cancel subscription |
| GET | /api/v1/subscriptions/portal | Get Stripe customer portal URL |

### Webhooks

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | /api/v1/webhooks/stripe | Handle Stripe webhook events |

### Lookup

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | /api/v1/cities | Get cities list |
| GET | /api/v1/business-types | Get business types |
| GET | /api/v1/community-types | Get community types |
| GET | /api/v1/categories | Get opportunity categories |

---

## Data Flow Diagrams

### 1. Google OAuth Login Flow

```
┌──────────┐      ┌─────────────┐      ┌─────────────┐      ┌────────────┐
│  Mobile  │      │   Google    │      │   Laravel   │      │  Database  │
│   App    │      │    OAuth    │      │     API     │      │            │
└────┬─────┘      └──────┬──────┘      └──────┬──────┘      └─────┬──────┘
     │                   │                    │                   │
     │ 1. Google Sign-In │                    │                   │
     │──────────────────►│                    │                   │
     │                   │                    │                   │
     │ 2. {id_token}     │                    │                   │
     │◄──────────────────│                    │                   │
     │                   │                    │                   │
     │ 3. POST /auth/google                   │                   │
     │   {id_token, user_type}                │                   │
     │───────────────────────────────────────►│                   │
     │                   │                    │                   │
     │                   │ 4. Verify token    │                   │
     │                   │◄───────────────────│                   │
     │                   │                    │                   │
     │                   │ 5. {email,         │                   │
     │                   │    google_id,      │                   │
     │                   │    name, avatar}   │                   │
     │                   │───────────────────►│                   │
     │                   │                    │                   │
     │                   │                    │ 6. UPSERT profiles│
     │                   │                    │   (by google_id)  │
     │                   │                    │──────────────────►│
     │                   │                    │                   │
     │                   │                    │ 7. UPSERT         │
     │                   │                    │    business_profiles
     │                   │                    │    OR             │
     │                   │                    │    community_profiles
     │                   │                    │──────────────────►│
     │                   │                    │                   │
     │                   │                    │ 8. Generate JWT   │
     │                   │                    │                   │
     │ 9. {token, user, is_new_user}          │                   │
     │◄───────────────────────────────────────│                   │
     │                   │                    │                   │
```

### 2. Application Accept Flow

```
┌──────────┐      ┌─────────────┐      ┌────────────┐
│  Mobile  │      │   Laravel   │      │  Database  │
│   App    │      │     API     │      │            │
└────┬─────┘      └──────┬──────┘      └─────┬──────┘
     │                   │                   │
     │ 1. POST /applications/{id}/accept    │
     │──────────────────►│                   │
     │                   │                   │
     │                   │ 2. Verify user is │
     │                   │    opportunity owner
     │                   │                   │
     │                   │ 3. BEGIN TRANSACTION
     │                   │                   │
     │                   │ 4. UPDATE applications
     │                   │    SET status='accepted'
     │                   │──────────────────►│
     │                   │                   │
     │                   │ 5. INSERT collaborations
     │                   │──────────────────►│
     │                   │                   │
     │                   │ 6. COMMIT         │
     │                   │                   │
     │                   │ 7. Queue notification
     │                   │    job (async)    │
     │                   │                   │
     │ 8. {collaboration}│                   │
     │◄──────────────────│                   │
     │                   │                   │
```

### 3. Browse Opportunities Flow

```
┌──────────┐      ┌─────────────┐      ┌────────────┐
│  Mobile  │      │   Laravel   │      │  Database  │
│   App    │      │     API     │      │            │
└────┬─────┘      └──────┬──────┘      └─────┬──────┘
     │                   │                   │
     │ 1. GET /opportunities               │
     │    ?status=published                │
     │    &creator_type=business           │
     │    &city=Barcelona                  │
     │    &page=1                          │
     │──────────────────►│                   │
     │                   │                   │
     │                   │ 2. Build query   │
     │                   │    with filters  │
     │                   │                   │
     │                   │ 3. SELECT * FROM │
     │                   │    collab_opportunities
     │                   │    WHERE status='published'
     │                   │    AND ...       │
     │                   │    ORDER BY created_at DESC
     │                   │    LIMIT 20 OFFSET 0
     │                   │──────────────────►│
     │                   │                   │
     │                   │ 4. JOIN profiles │
     │                   │    for creator info
     │                   │──────────────────►│
     │                   │                   │
     │ 5. {data, meta}   │                   │
     │◄──────────────────│                   │
     │                   │                   │
```

---

## JSON Field Structures

### `business_offer` (in collab_opportunities)
```json
{
  "venue": true,
  "food_drink": true,
  "discount": {
    "enabled": true,
    "percentage": 20
  },
  "products": ["T-shirt", "Cap"],
  "other": "Free parking"
}
```

### `community_deliverables` (in collab_opportunities)
```json
{
  "instagram_post": true,
  "instagram_story": true,
  "tiktok_video": false,
  "event_mention": true,
  "attendee_count": 50
}
```

### `categories` (in collab_opportunities)
```json
["Food & Drink", "Sports", "Wellness"]
```

### `contact_methods` (in collaborations)
```json
{
  "whatsapp": "+34612345678",
  "email": "contact@example.com",
  "instagram": "@handle"
}
```

---

## Implementation Notes

### Key Differences from Current Schema

1. **Google OAuth Only (No Password Auth)**
   - `profiles.password_hash` removed
   - `profiles.google_id` added for OAuth
   - JWT generation in Laravel after Google token verification

2. **Monthly Subscription Only (No Credits)**
   - `business_credits` table removed
   - `credit_transactions` table removed
   - Simple `business_subscriptions` for Stripe

3. **No Supabase Dependency**
   - Pure PostgreSQL database
   - No Edge Functions
   - No RLS policies

4. **No Triggers**
   - All logic in Laravel service layer
   - Explicit service calls for related operations

5. **Simplified Schema**
   - 8 tables instead of 21
   - Focused on core collaboration flow

### Laravel Implementation Checklist

- [ ] Create models for all 8 tables
- [ ] Define Eloquent relationships
- [ ] Create form requests for validation
- [ ] Implement service classes:
  - [ ] GoogleAuthService (verify token, create/update user)
  - [ ] ProfileService
  - [ ] OpportunityService
  - [ ] ApplicationService
  - [ ] CollaborationService
  - [ ] SubscriptionService (Stripe integration)
- [ ] Create API resources for responses
- [ ] Set up authentication (Sanctum + Google OAuth)
- [ ] Configure Stripe webhook handling
- [ ] Configure CORS for mobile app

### Google OAuth Setup (Laravel)

```php
// Install: composer require google/apiclient

// config/services.php
'google' => [
    'client_id' => env('GOOGLE_CLIENT_ID'),
    'client_id_ios' => env('GOOGLE_CLIENT_ID_IOS'),
    'client_id_android' => env('GOOGLE_CLIENT_ID_ANDROID'),
],

// GoogleAuthService.php
public function verifyIdToken(string $idToken): array
{
    $client = new Google_Client();
    $client->setClientId(config('services.google.client_id'));

    $payload = $client->verifyIdToken($idToken);

    if (!$payload) {
        throw new AuthenticationException('Invalid Google token');
    }

    return [
        'google_id' => $payload['sub'],
        'email' => $payload['email'],
        'name' => $payload['name'],
        'avatar_url' => $payload['picture'] ?? null,
    ];
}
```

### Stripe Subscription Setup (Laravel)

```php
// Install: composer require stripe/stripe-php

// SubscriptionService.php
public function createCheckoutSession(Profile $profile): string
{
    $stripe = new \Stripe\StripeClient(config('services.stripe.secret'));

    // Get or create Stripe customer
    $subscription = $profile->businessSubscription;

    if (!$subscription->stripe_customer_id) {
        $customer = $stripe->customers->create([
            'email' => $profile->email,
            'metadata' => ['profile_id' => $profile->id],
        ]);
        $subscription->update(['stripe_customer_id' => $customer->id]);
    }

    // Create checkout session
    $session = $stripe->checkout->sessions->create([
        'customer' => $subscription->stripe_customer_id,
        'mode' => 'subscription',
        'line_items' => [[
            'price' => config('services.stripe.price_monthly'),
            'quantity' => 1,
        ]],
        'success_url' => config('app.url') . '/subscription/success',
        'cancel_url' => config('app.url') . '/subscription/cancel',
    ]);

    return $session->url;
}
```

---

*Document generated for Kolabing Mobile MVP*
*Last updated: 2026-01-24*
