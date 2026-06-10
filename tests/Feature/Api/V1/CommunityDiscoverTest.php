<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CommunityDiscoverTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_discover_lists_public_non_joined_communities_featured_first(): void
    {
        $viewer = Profile::factory()->attendee()->create();

        // Featured community with 1 active member.
        $featured = Community::factory()->create([
            'name' => 'Featured Club',
            'join_policy' => 'open',
            'is_featured' => true,
        ]);
        CommunityMember::factory()->forCommunity($featured)->create();

        // Non-featured but more members -> still ranked below featured.
        $popular = Community::factory()->create([
            'name' => 'Popular Club',
            'join_policy' => 'open',
            'is_featured' => false,
        ]);
        CommunityMember::factory()->forCommunity($popular)->count(3)->create();

        // Invite-only -> excluded (not public).
        Community::factory()->create([
            'name' => 'Secret Club',
            'join_policy' => 'invite_only',
        ]);

        // Owned by viewer -> excluded.
        Community::factory()->forOwner($viewer)->create([
            'name' => 'My Own Club',
            'join_policy' => 'open',
        ]);

        // Already an active member -> excluded.
        $joined = Community::factory()->create([
            'name' => 'Already Joined',
            'join_policy' => 'open',
        ]);
        CommunityMember::factory()->forCommunity($joined)->create([
            'profile_id' => $viewer->id,
        ]);

        $response = $this->actingAs($viewer)->getJson('/api/v1/communities/discover');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data')
            // Featured first, then by member count.
            ->assertJsonPath('data.0.name', 'Featured Club')
            ->assertJsonPath('data.0.is_featured', true)
            ->assertJsonPath('data.0.member_count', 1)
            ->assertJsonPath('data.1.name', 'Popular Club')
            ->assertJsonPath('data.1.member_count', 3)
            ->assertJsonStructure([
                'data' => [['id', 'name', 'avatar_url', 'type', 'member_count', 'join_policy', 'leader_name']],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ]);
    }

    public function test_discover_respects_type_filter(): void
    {
        $viewer = Profile::factory()->attendee()->create();

        // Real 17-slug community types (the unified vocabulary), not the retired
        // 5-value placeholder enum.
        Community::factory()->create([
            'name' => 'Fitness One',
            'type' => 'fitness_community',
            'join_policy' => 'open',
        ]);
        Community::factory()->create([
            'name' => 'Run One',
            'type' => 'run_club',
            'join_policy' => 'open',
        ]);

        $response = $this->actingAs($viewer)
            ->getJson('/api/v1/communities/discover?type=fitness_community');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Fitness One')
            ->assertJsonPath('data.0.type', 'fitness_community');
    }

    public function test_discover_requires_authentication(): void
    {
        $this->getJson('/api/v1/communities/discover')->assertStatus(401);
    }
}
