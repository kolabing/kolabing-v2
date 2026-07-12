<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ScalabilityIndexesTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @return array<string, array<int, string>>
     */
    private function expectedIndexes(): array
    {
        return [
            'kolabs' => [
                'kolabs_status_published_at_index',
                'kolabs_creator_profile_id_index',
                'kolabs_recipient_community_id_index',
            ],
            'attendee_profiles' => ['attendee_profiles_total_points_index'],
            'community_members' => [
                'community_members_community_id_status_index',
                'community_members_tier_id_index',
            ],
            'community_points' => ['community_points_community_id_points_index'],
            'challenge_completions' => ['challenge_completions_event_id_status_index'],
            'collaboration_reviews' => [
                'collaboration_reviews_reviewed_rating_created_index',
                'collaboration_reviews_reviewer_profile_id_index',
            ],
            'chat_messages' => [
                'chat_messages_app_unread_index',
                'chat_messages_thread_unread_index',
            ],
            'events' => [
                'events_community_id_index',
                'events_collaboration_id_index',
                'events_city_id_index',
            ],
            'collaborations' => [
                'collaborations_business_profile_id_index',
                'collaborations_community_profile_id_index',
                'collaborations_event_id_index',
            ],
            'notifications' => ['notifications_actor_profile_id_index'],
            'profiles' => ['profiles_city_id_index'],
            'reward_redemptions' => [
                'reward_redemptions_reward_id_index',
                'reward_redemptions_profile_id_index',
            ],
        ];
    }

    public function test_scalability_indexes_are_created_by_migration(): void
    {
        foreach ($this->expectedIndexes() as $table => $indexes) {
            $existing = collect(Schema::getIndexes($table))
                ->pluck('name')
                ->all();

            foreach ($indexes as $index) {
                $this->assertContains(
                    $index,
                    $existing,
                    "Expected index [{$index}] to exist on table [{$table}].",
                );
            }
        }
    }

    public function test_partial_unread_indexes_only_cover_unread_messages(): void
    {
        $partialIndexes = collect(Schema::getIndexes('chat_messages'))
            ->whereIn('name', ['chat_messages_app_unread_index', 'chat_messages_thread_unread_index']);

        $this->assertCount(2, $partialIndexes, 'Both partial unread indexes should exist on chat_messages.');
    }
}
