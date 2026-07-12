<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Composite and partial indexes backing the hottest query paths
     * (browse feed, leaderboards, reputation, unread counts).
     *
     * @var array<string, string> index name => "table (columns) [WHERE ...]"
     */
    private array $hotPathIndexes = [
        'kolabs_status_published_at_index' => 'kolabs (status, published_at)',
        'attendee_profiles_total_points_index' => 'attendee_profiles (total_points)',
        'community_members_community_id_status_index' => 'community_members (community_id, status)',
        'community_points_community_id_points_index' => 'community_points (community_id, points)',
        'challenge_completions_event_id_status_index' => 'challenge_completions (event_id, status)',
        'collaboration_reviews_reviewed_rating_created_index' => 'collaboration_reviews (reviewed_profile_id, rating, created_at)',
        'chat_messages_app_unread_index' => 'chat_messages (application_id) WHERE read_at IS NULL',
        'chat_messages_thread_unread_index' => 'chat_messages (thread_id) WHERE read_at IS NULL',
    ];

    /**
     * Single-column indexes for foreign keys that Postgres left unindexed
     * (Postgres does not auto-index FKs). Speeds up joins/whereHas and
     * avoids slow cascade-delete scans as these tables grow.
     *
     * @var array<string, string> index name => "table (column)"
     */
    private array $foreignKeyIndexes = [
        'badge_awards_profile_id_index' => 'badge_awards (profile_id)',
        'chat_thread_bans_created_by_index' => 'chat_thread_bans (created_by)',
        'chat_threads_created_by_index' => 'chat_threads (created_by)',
        'collab_opportunities_recipient_community_id_index' => 'collab_opportunities (recipient_community_id)',
        'collaboration_challenge_bonuses_set_by_profile_id_index' => 'collaboration_challenge_bonuses (set_by_profile_id)',
        'collaboration_challenge_bonuses_challenge_id_index' => 'collaboration_challenge_bonuses (challenge_id)',
        'collaboration_challenges_challenge_id_index' => 'collaboration_challenges (challenge_id)',
        'collaboration_completions_profile_id_index' => 'collaboration_completions (profile_id)',
        'collaboration_feedback_reviewer_profile_id_index' => 'collaboration_feedback (reviewer_profile_id)',
        'collaboration_reviews_reviewer_profile_id_index' => 'collaboration_reviews (reviewer_profile_id)',
        'collaborations_community_profile_id_index' => 'collaborations (community_profile_id)',
        'collaborations_business_profile_id_index' => 'collaborations (business_profile_id)',
        'collaborations_cancelled_by_profile_id_index' => 'collaborations (cancelled_by_profile_id)',
        'collaborations_completed_by_profile_id_index' => 'collaborations (completed_by_profile_id)',
        'collaborations_event_id_index' => 'collaborations (event_id)',
        'communities_community_profile_id_index' => 'communities (community_profile_id)',
        'community_badge_awards_profile_id_index' => 'community_badge_awards (profile_id)',
        'community_goals_challenge_id_index' => 'community_goals (challenge_id)',
        'community_join_requests_decided_by_index' => 'community_join_requests (decided_by)',
        'community_members_tier_id_index' => 'community_members (tier_id)',
        'community_point_ledger_profile_id_index' => 'community_point_ledger (profile_id)',
        'crm_accounts_linked_profile_id_index' => 'crm_accounts (linked_profile_id)',
        'crm_tasks_crm_account_id_index' => 'crm_tasks (crm_account_id)',
        'event_series_city_id_index' => 'event_series (city_id)',
        'events_community_id_index' => 'events (community_id)',
        'events_collaboration_id_index' => 'events (collaboration_id)',
        'events_city_id_index' => 'events (city_id)',
        'kolabs_recipient_community_id_index' => 'kolabs (recipient_community_id)',
        'kolabs_creator_profile_id_index' => 'kolabs (creator_profile_id)',
        'notifications_actor_profile_id_index' => 'notifications (actor_profile_id)',
        'profiles_city_id_index' => 'profiles (city_id)',
        'referral_redemptions_business_subscription_id_index' => 'referral_redemptions (business_subscription_id)',
        'reward_claims_challenge_completion_id_index' => 'reward_claims (challenge_completion_id)',
        'reward_redemptions_reward_id_index' => 'reward_redemptions (reward_id)',
        'reward_redemptions_profile_id_index' => 'reward_redemptions (profile_id)',
        'saved_kolabs_kolab_id_index' => 'saved_kolabs (kolab_id)',
        'withdrawal_requests_profile_id_index' => 'withdrawal_requests (profile_id)',
    ];

    public function up(): void
    {
        foreach ([...$this->hotPathIndexes, ...$this->foreignKeyIndexes] as $name => $target) {
            DB::statement("CREATE INDEX IF NOT EXISTS {$name} ON {$target}");
        }
    }

    public function down(): void
    {
        $names = array_merge(
            array_keys($this->hotPathIndexes),
            array_keys($this->foreignKeyIndexes),
        );

        foreach ($names as $name) {
            DB::statement("DROP INDEX IF EXISTS {$name}");
        }
    }
};
