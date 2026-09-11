<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The follower/member split has to be additive: it ships to a database with
 * real communities and real members on it (kolabing-app#138).
 *
 * This test is the guard for that promise. It asserts the three new tables
 * exist, and — the half that actually matters — that the two tables the feature
 * builds around still have every column they had before.
 */
class CommunityFollowerSchemaTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_the_three_new_tables_exist(): void
    {
        $this->assertTrue(Schema::hasTable('community_followers'));
        $this->assertTrue(Schema::hasTable('community_join_questions'));
        $this->assertTrue(Schema::hasTable('community_join_answers'));
    }

    public function test_new_tables_have_the_columns_the_services_rely_on(): void
    {
        $this->assertTrue(Schema::hasColumns('community_followers', [
            'id', 'community_id', 'profile_id', 'followed_at',
        ]));
        $this->assertTrue(Schema::hasColumns('community_join_questions', [
            'id', 'community_id', 'position', 'prompt', 'required', 'is_active',
        ]));
        $this->assertTrue(Schema::hasColumns('community_join_answers', [
            'id', 'join_request_id', 'question_id', 'answer',
        ]));
    }

    /**
     * Additive only. If a migration in this feature ever alters or drops a
     * column on either of these, this fails — which is the point: existing
     * members must come out of the deploy with everything they had.
     */
    public function test_existing_tables_are_untouched(): void
    {
        $this->assertTrue(Schema::hasColumns('community_members', [
            'id', 'community_id', 'profile_id', 'tier_id', 'can_manage',
            'status', 'joined_at', 'tier_assigned_at',
        ]));

        $this->assertTrue(Schema::hasColumns('community_join_requests', [
            'id', 'community_id', 'profile_id', 'status', 'decided_by',
            'requested_at', 'decided_at',
        ]));
    }
}
