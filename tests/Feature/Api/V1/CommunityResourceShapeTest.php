<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Http\Resources\Api\V1\CommunityMemberResource;
use App\Http\Resources\Api\V1\CommunityResource;
use App\Http\Resources\Api\V1\CommunityTierResource;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\CommunityTier;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class CommunityResourceShapeTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_community_resource_matches_wire_shape(): void
    {
        $community = Community::factory()->create(['type' => 'greek', 'join_policy' => 'open']);

        $array = (new CommunityResource($community))->toArray(Request::create('/'));

        $this->assertSame([
            'id', 'owner_profile_id', 'community_profile_id', 'name', 'slug', 'type',
            'description', 'avatar_url', 'is_primary', 'join_policy', 'invite_url', 'member_count',
            'is_member', 'my_join_request_status', 'my_points', 'my_tier',
            'is_verified', 'verification_status', 'public_channels', 'created_at', 'updated_at',
        ], array_keys($array));
        $this->assertSame('greek', $array['type']);
        $this->assertSame('open', $array['join_policy']);
        $this->assertSame(rtrim(config('communities.invite_base_url'), '/').'/'.$community->slug, $array['invite_url']);
        $this->assertIsInt($array['member_count']);
        // Unauthenticated viewer (no request user) → null-safe CTA defaults.
        $this->assertFalse($array['is_member']);
        $this->assertNull($array['my_join_request_status']);
        $this->assertSame(0, $array['my_points']);
        $this->assertNull($array['my_tier']);
    }

    public function test_tier_resource_always_returns_permission_buckets(): void
    {
        $community = Community::factory()->create();
        $tier = CommunityTier::factory()->forCommunity($community)->create([
            'assignment_rule' => 'xp_threshold',
            'threshold' => 500,
            'permissions' => null,
        ]);

        $array = (new CommunityTierResource($tier))->toArray(Request::create('/'));

        $this->assertSame('xp_threshold', $array['assignment_rule']);
        $this->assertSame(500, $array['threshold']);
        $this->assertSame(['view' => [], 'chat_channels' => [], 'perks' => [], 'capabilities' => []], $array['permissions']);
    }

    public function test_member_resource_nests_tier_and_profile_when_loaded(): void
    {
        $community = Community::factory()->create();
        $tier = CommunityTier::factory()->forCommunity($community)->create();
        $profile = Profile::factory()->attendee()->create();
        $member = CommunityMember::factory()->forCommunity($community)->create([
            'profile_id' => $profile->id,
            'tier_id' => $tier->id,
        ]);
        $member->load(['tier', 'profile']);

        $array = (new CommunityMemberResource($member))->toArray(Request::create('/'));

        $this->assertArrayHasKey('tier', $array);
        $this->assertSame($tier->id, $array['tier']->resource->id);
        $this->assertSame($tier->id, $array['tier_id']);
        $this->assertArrayHasKey('profile', $array);
        $this->assertArrayHasKey('name', $array['profile']);
        $this->assertArrayHasKey('avatar_url', $array['profile']);
        $this->assertSame('active', $array['status']);
    }
}
