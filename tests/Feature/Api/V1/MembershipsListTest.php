<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\CommunityPoints;
use App\Models\CommunityTier;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MembershipsListTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Regression for PHP-LARAVEL-7: the viewer-scoped fields CommunityResource
     * emits (member_count, my_points, my_tier, my_join_request_status) must be
     * bulk-resolved, so the endpoint runs a constant number of queries instead
     * of ~5 per community. Also asserts the payload values stay correct.
     */
    public function test_my_memberships_is_correct_and_does_not_n_plus_one(): void
    {
        $viewer = Profile::factory()->attendee()->create();

        // Three communities the viewer belongs to.
        $communities = Community::factory()->count(3)->create();

        foreach ($communities as $community) {
            $tier = CommunityTier::factory()->forCommunity($community)->create(['name' => 'Gold']);
            CommunityMember::factory()->create([
                'community_id' => $community->id,
                'profile_id' => $viewer->id,
                'tier_id' => $tier->id,
            ]);
        }

        $first = $communities->first();
        // A second active member in the first community → member_count == 2.
        CommunityMember::factory()->forCommunity($first)->create();
        CommunityPoints::forceCreate([
            'community_id' => $first->id,
            'profile_id' => $viewer->id,
            'points' => 50,
        ]);

        $pointsQueries = 0;
        $joinRequestQueries = 0;
        DB::listen(function ($query) use (&$pointsQueries, &$joinRequestQueries): void {
            if (str_contains($query->sql, 'from "community_points"')) {
                $pointsQueries++;
            }
            if (str_contains($query->sql, 'from "community_join_requests"')) {
                $joinRequestQueries++;
            }
        });

        $response = $this->actingAs($viewer)
            ->getJson('/api/v1/me/memberships')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertCount(3, $response->json('data'));

        // Bulk-resolved: exactly one query each, regardless of community count
        // (was one per community → N+1).
        $this->assertSame(1, $pointsQueries);
        $this->assertSame(1, $joinRequestQueries);

        // Values stay correct via the preloaded fast path.
        $entry = collect($response->json('data'))->firstWhere('community.id', $first->id);
        $this->assertNotNull($entry);
        $this->assertSame(2, $entry['community']['member_count']);
        $this->assertSame(50, $entry['community']['my_points']);
        $this->assertTrue($entry['community']['is_member']);
        $this->assertSame('Gold', $entry['community']['my_tier']['name']);
    }
}
