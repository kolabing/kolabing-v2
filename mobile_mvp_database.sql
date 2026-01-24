-- ============================================================================
-- KOLABING MOBILE MVP - MINIMAL DATABASE SCHEMA
-- Version: 2.1
-- Date: 2026-01-24
-- Database: PostgreSQL 15+
-- Auth: Google OAuth only (no password)
-- Monetization: Monthly subscription only (no credits)
--
-- This script creates a minimal database schema for the Mobile MVP.
--
-- Tables included (8 total):
--   1. cities                 - City lookup
--   2. profiles               - Main user table (Google OAuth)
--   3. business_profiles      - Extended business user data
--   4. community_profiles     - Extended community user data
--   5. business_subscriptions - Stripe monthly subscriptions
--   6. collab_opportunities   - Collaboration opportunities
--   7. applications           - Applications to opportunities
--   8. collaborations         - Active collaborations
--
-- Tables removed from current schema (13 total):
--   - business_credits (NOT NEEDED - no credit system)
--   - credit_transactions (NOT NEEDED - no credit system)
--   - refund_transactions (NOT NEEDED - no credit system)
--   - invitation_codes (Phase 2 - Referral)
--   - referral_rules (Phase 2 - Referral)
--   - user_referral_progress (Phase 2 - Referral)
--   - referral_redemptions (Phase 2 - Referral)
--   - reviews (Phase 2 - Engagement)
--   - surveys (Phase 2 - Engagement)
--   - community_events (Not MVP)
--   - community_events_photos (Not MVP)
--   - analytics_events (Phase 2)
--   - success_stories (Not MVP)
-- ============================================================================

-- Enable UUID extension
CREATE EXTENSION IF NOT EXISTS "pgcrypto";

-- ============================================================================
-- DROP EXISTING TYPES (if recreating)
-- ============================================================================

-- Uncomment these if you need to recreate the schema
-- DROP TYPE IF EXISTS user_type CASCADE;
-- DROP TYPE IF EXISTS application_status CASCADE;
-- DROP TYPE IF EXISTS offer_status CASCADE;
-- DROP TYPE IF EXISTS collaboration_status CASCADE;
-- DROP TYPE IF EXISTS subscription_status CASCADE;

-- ============================================================================
-- ENUM TYPES
-- ============================================================================

-- User type discriminator
CREATE TYPE user_type AS ENUM ('business', 'community');

-- Application status flow: pending -> accepted/declined/withdrawn
CREATE TYPE application_status AS ENUM ('pending', 'accepted', 'declined', 'withdrawn');

-- Opportunity status flow: draft -> published -> closed -> completed
CREATE TYPE offer_status AS ENUM ('draft', 'published', 'closed', 'completed');

-- Collaboration status flow: scheduled -> active -> completed/cancelled
CREATE TYPE collaboration_status AS ENUM ('scheduled', 'active', 'completed', 'cancelled');

-- Subscription status for Stripe
CREATE TYPE subscription_status AS ENUM ('active', 'cancelled', 'past_due', 'inactive');

-- ============================================================================
-- TABLE: cities
-- Lookup table for cities
-- ============================================================================

CREATE TABLE cities (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(100) NOT NULL UNIQUE,
    country VARCHAR(100) DEFAULT 'Spain',
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

COMMENT ON TABLE cities IS 'City lookup table for location filtering';

-- ============================================================================
-- TABLE: profiles
-- Main user table with Google OAuth authentication
-- Note: No password - only Google OAuth supported
-- ============================================================================

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

COMMENT ON TABLE profiles IS 'Main user table with Google OAuth authentication';
COMMENT ON COLUMN profiles.user_type IS 'Discriminator for profile type: business or community';
COMMENT ON COLUMN profiles.google_id IS 'Google OAuth user ID - unique identifier from Google';
COMMENT ON COLUMN profiles.avatar_url IS 'Profile photo URL from Google account';

-- ============================================================================
-- TABLE: business_profiles
-- Extended profile data for business users (1:1 with profiles)
-- ============================================================================

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

COMMENT ON TABLE business_profiles IS 'Extended profile for business users';
COMMENT ON COLUMN business_profiles.business_type IS 'Industry type: restaurant, hotel, cafe, gym, etc.';
COMMENT ON COLUMN business_profiles.profile_photo IS 'URL to profile photo in storage';

-- ============================================================================
-- TABLE: community_profiles
-- Extended profile data for community users (1:1 with profiles)
-- ============================================================================

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

COMMENT ON TABLE community_profiles IS 'Extended profile for community users';
COMMENT ON COLUMN community_profiles.community_type IS 'Community type: sports, art, food, tech, etc.';
COMMENT ON COLUMN community_profiles.is_featured IS 'Flag for featured communities on homepage';

-- ============================================================================
-- TABLE: business_subscriptions
-- Stripe monthly subscriptions for business users
-- Note: Only monthly subscription - no credit system
-- ============================================================================

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

COMMENT ON TABLE business_subscriptions IS 'Stripe monthly subscriptions for business users';
COMMENT ON COLUMN business_subscriptions.stripe_customer_id IS 'Stripe customer ID';
COMMENT ON COLUMN business_subscriptions.stripe_subscription_id IS 'Stripe subscription ID';
COMMENT ON COLUMN business_subscriptions.status IS 'Current subscription status from Stripe';
COMMENT ON COLUMN business_subscriptions.cancel_at_period_end IS 'If true, subscription will cancel at period end';

-- ============================================================================
-- TABLE: collab_opportunities
-- Collaboration opportunities created by users
-- Can be created by both business and community users
-- ============================================================================

CREATE TABLE collab_opportunities (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    creator_profile_id UUID NOT NULL REFERENCES profiles(id) ON DELETE CASCADE,
    creator_profile_type user_type NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    status offer_status DEFAULT 'draft',

    -- What the business offers (JSON)
    business_offer JSONB DEFAULT '{}',

    -- What the community delivers (JSON)
    community_deliverables JSONB DEFAULT '{}',

    -- Category tags (JSON array)
    categories JSONB DEFAULT '[]',

    -- Availability settings
    availability_mode VARCHAR(50),          -- 'one_time', 'recurring', 'flexible'
    availability_start DATE,
    availability_end DATE,

    -- Venue settings
    venue_mode VARCHAR(50),                 -- 'business_venue', 'community_venue', 'no_venue'
    address TEXT,
    preferred_city VARCHAR(100),

    -- Media
    offer_photo TEXT,

    -- Timestamps
    published_at TIMESTAMP WITH TIME ZONE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
);

COMMENT ON TABLE collab_opportunities IS 'Collaboration opportunities created by users';
COMMENT ON COLUMN collab_opportunities.business_offer IS 'JSON: {venue, food_drink, discount: {enabled, percentage}, products: [], other}';
COMMENT ON COLUMN collab_opportunities.community_deliverables IS 'JSON: {instagram_post, instagram_story, tiktok_video, event_mention, attendee_count}';
COMMENT ON COLUMN collab_opportunities.categories IS 'JSON array of category strings: ["Food & Drink", "Sports"]';

-- ============================================================================
-- TABLE: applications
-- Applications to collaboration opportunities
-- One application per user per opportunity (enforced by unique constraint)
-- ============================================================================

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

    -- Prevent duplicate applications
    UNIQUE(collab_opportunity_id, applicant_profile_id)
);

COMMENT ON TABLE applications IS 'Applications to collaboration opportunities';
COMMENT ON COLUMN applications.message IS 'Optional message from applicant';
COMMENT ON COLUMN applications.availability IS 'Applicant availability description';

-- ============================================================================
-- TABLE: collaborations
-- Active collaborations created when an application is accepted
-- One collaboration per application (1:1 relationship)
-- ============================================================================

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

COMMENT ON TABLE collaborations IS 'Active collaborations created from accepted applications';
COMMENT ON COLUMN collaborations.contact_methods IS 'JSON: {whatsapp, email, instagram}';
COMMENT ON COLUMN collaborations.business_profile_id IS 'Denormalized reference to business participant';
COMMENT ON COLUMN collaborations.community_profile_id IS 'Denormalized reference to community participant';

-- ============================================================================
-- INDEXES
-- Optimized for common query patterns in mobile app
-- ============================================================================

-- Profiles indexes
CREATE INDEX idx_profiles_email ON profiles(email);
CREATE INDEX idx_profiles_google_id ON profiles(google_id);
CREATE INDEX idx_profiles_user_type ON profiles(user_type);

-- Business profiles indexes
CREATE INDEX idx_business_profiles_profile_id ON business_profiles(profile_id);
CREATE INDEX idx_business_profiles_city_id ON business_profiles(city_id);
CREATE INDEX idx_business_profiles_business_type ON business_profiles(business_type);

-- Community profiles indexes
CREATE INDEX idx_community_profiles_profile_id ON community_profiles(profile_id);
CREATE INDEX idx_community_profiles_city_id ON community_profiles(city_id);
CREATE INDEX idx_community_profiles_community_type ON community_profiles(community_type);
CREATE INDEX idx_community_profiles_is_featured ON community_profiles(is_featured) WHERE is_featured = true;

-- Business subscriptions indexes
CREATE INDEX idx_subscriptions_profile ON business_subscriptions(profile_id);
CREATE INDEX idx_subscriptions_stripe_customer ON business_subscriptions(stripe_customer_id);
CREATE INDEX idx_subscriptions_status ON business_subscriptions(status);
CREATE INDEX idx_subscriptions_status_active ON business_subscriptions(status) WHERE status = 'active';

-- Opportunities indexes (most important for browse)
CREATE INDEX idx_opportunities_creator ON collab_opportunities(creator_profile_id);
CREATE INDEX idx_opportunities_status ON collab_opportunities(status);
CREATE INDEX idx_opportunities_status_published ON collab_opportunities(status) WHERE status = 'published';
CREATE INDEX idx_opportunities_creator_type ON collab_opportunities(creator_profile_type);
CREATE INDEX idx_opportunities_created_at ON collab_opportunities(created_at DESC);
CREATE INDEX idx_opportunities_categories ON collab_opportunities USING GIN (categories);

-- Applications indexes
CREATE INDEX idx_applications_opportunity ON applications(collab_opportunity_id);
CREATE INDEX idx_applications_applicant ON applications(applicant_profile_id);
CREATE INDEX idx_applications_status ON applications(status);
CREATE INDEX idx_applications_status_pending ON applications(status) WHERE status = 'pending';

-- Collaborations indexes
CREATE INDEX idx_collaborations_application ON collaborations(application_id);
CREATE INDEX idx_collaborations_opportunity ON collaborations(collab_opportunity_id);
CREATE INDEX idx_collaborations_creator ON collaborations(creator_profile_id);
CREATE INDEX idx_collaborations_applicant ON collaborations(applicant_profile_id);
CREATE INDEX idx_collaborations_status ON collaborations(status);
CREATE INDEX idx_collaborations_status_active ON collaborations(status) WHERE status IN ('scheduled', 'active');

-- ============================================================================
-- SEED DATA
-- Default cities for Spain
-- ============================================================================

INSERT INTO cities (name, country) VALUES
    ('Barcelona', 'Spain'),
    ('Madrid', 'Spain'),
    ('Valencia', 'Spain'),
    ('Sevilla', 'Spain'),
    ('Bilbao', 'Spain'),
    ('Malaga', 'Spain'),
    ('Zaragoza', 'Spain'),
    ('Palma', 'Spain')
ON CONFLICT (name) DO NOTHING;

-- ============================================================================
-- HELPER FUNCTIONS
-- ============================================================================

-- Function to update updated_at timestamp
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$ language 'plpgsql';

-- Apply updated_at trigger to all tables with updated_at column
CREATE TRIGGER update_profiles_updated_at
    BEFORE UPDATE ON profiles
    FOR EACH ROW
    EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_business_profiles_updated_at
    BEFORE UPDATE ON business_profiles
    FOR EACH ROW
    EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_community_profiles_updated_at
    BEFORE UPDATE ON community_profiles
    FOR EACH ROW
    EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_business_subscriptions_updated_at
    BEFORE UPDATE ON business_subscriptions
    FOR EACH ROW
    EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_collab_opportunities_updated_at
    BEFORE UPDATE ON collab_opportunities
    FOR EACH ROW
    EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_applications_updated_at
    BEFORE UPDATE ON applications
    FOR EACH ROW
    EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_collaborations_updated_at
    BEFORE UPDATE ON collaborations
    FOR EACH ROW
    EXECUTE FUNCTION update_updated_at_column();

-- ============================================================================
-- VERIFICATION QUERIES
-- Run these to verify the schema was created correctly
-- ============================================================================

-- List all tables
-- SELECT table_name FROM information_schema.tables WHERE table_schema = 'public';

-- List all indexes
-- SELECT indexname, tablename FROM pg_indexes WHERE schemaname = 'public';

-- Count tables (should be 8)
-- SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'public' AND table_type = 'BASE TABLE';

-- ============================================================================
-- END OF MIGRATION SCRIPT
-- ============================================================================
