<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\CommunityMemberStatus;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\CommunityTier;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class CommunityRosterFilterTest extends TestCase
{
    use LazilyRefreshDatabase;

    private Community $community;

    protected function setUp(): void
    {
        parent::setUp();
        $this->community = Community::factory()->create();
    }

    /**
     * @param  array<string, mixed>  $profile
     * @param  array<string, mixed>  $membership
     */
    private function member(array $profile = [], array $membership = []): CommunityMember
    {
        $p = Profile::factory()->attendee()->create($profile);

        return CommunityMember::factory()->create(array_merge([
            'community_id' => $this->community->id,
            'profile_id' => $p->id,
        ], $membership));
    }

    private function roster(string $query = ''): TestResponse
    {
        return $this->actingAs($this->community->owner)
            ->getJson("/api/v1/communities/{$this->community->id}/members".$query);
    }

    public function test_removed_members_are_excluded_by_default(): void
    {
        $this->member(['name' => 'Stays']);
        $this->member(['name' => 'Gone'], ['status' => CommunityMemberStatus::Removed->value]);

        $res = $this->roster()->assertOk();

        $this->assertSame(1, $res->json('data.pagination.total_count'));
        $this->assertSame('Stays', $res->json('data.members.0.profile.name'));
    }

    public function test_status_all_restores_removed_members(): void
    {
        $this->member(['name' => 'Stays']);
        $this->member(['name' => 'Gone'], ['status' => CommunityMemberStatus::Removed->value]);

        $this->assertSame(2, $this->roster('?status=all')->assertOk()->json('data.pagination.total_count'));
    }

    public function test_status_filter_selects_one_status(): void
    {
        $this->member(['name' => 'Active One']);
        $this->member(['name' => 'Inactive One'], ['status' => CommunityMemberStatus::Inactive->value]);

        $res = $this->roster('?status=inactive')->assertOk();

        $this->assertSame(1, $res->json('data.pagination.total_count'));
        $this->assertSame('Inactive One', $res->json('data.members.0.profile.name'));
    }

    public function test_search_matches_name_email_and_handle(): void
    {
        $this->member(['name' => 'Ada Lovelace', 'email' => 'ada@example.com', 'handle' => 'ada']);
        $this->member(['name' => 'Grace Hopper', 'email' => 'grace@example.com', 'handle' => 'grace']);

        $this->assertSame(1, $this->roster('?search=lovelace')->assertOk()->json('data.pagination.total_count'));
        $this->assertSame(1, $this->roster('?search=grace@example')->assertOk()->json('data.pagination.total_count'));
        // A leading @ is stripped so pasting a handle works.
        $this->assertSame(1, $this->roster('?search=@ada')->assertOk()->json('data.pagination.total_count'));
        $this->assertSame(0, $this->roster('?search=nobody')->assertOk()->json('data.pagination.total_count'));
    }

    public function test_tier_filter_and_the_none_bucket(): void
    {
        $tier = CommunityTier::factory()->forCommunity($this->community)->create(['name' => 'Exec', 'rank' => 3]);
        $this->member(['name' => 'Tiered'], ['tier_id' => $tier->id]);
        $this->member(['name' => 'Untiered'], ['tier_id' => null]);

        $this->assertSame('Tiered', $this->roster("?tier_id={$tier->id}")->assertOk()->json('data.members.0.profile.name'));
        $this->assertSame('Untiered', $this->roster('?tier_id=none')->assertOk()->json('data.members.0.profile.name'));
    }

    public function test_can_manage_filter(): void
    {
        $this->member(['name' => 'Manager'], ['can_manage' => true]);
        $this->member(['name' => 'Plain'], ['can_manage' => false]);

        $res = $this->roster('?can_manage=1')->assertOk();

        $this->assertSame(1, $res->json('data.pagination.total_count'));
        $this->assertSame('Manager', $res->json('data.members.0.profile.name'));
    }

    public function test_sort_by_name_ascending(): void
    {
        $this->member(['name' => 'Zoe']);
        $this->member(['name' => 'Alice']);

        $names = collect($this->roster('?sort=name')->assertOk()->json('data.members'))
            ->pluck('profile.name')->all();

        $this->assertSame(['Alice', 'Zoe'], $names);
    }

    public function test_sort_by_tier_defaults_to_highest_rank_first(): void
    {
        $low = CommunityTier::factory()->forCommunity($this->community)->create(['name' => 'Pledge', 'rank' => 1]);
        $high = CommunityTier::factory()->forCommunity($this->community)->create(['name' => 'Exec', 'rank' => 5]);
        $this->member(['name' => 'Low'], ['tier_id' => $low->id]);
        $this->member(['name' => 'High'], ['tier_id' => $high->id]);

        $names = collect($this->roster('?sort=tier')->assertOk()->json('data.members'))
            ->pluck('profile.name')->all();

        $this->assertSame(['High', 'Low'], $names);
    }

    public function test_an_unknown_sort_key_falls_back_to_joined_at_instead_of_erroring(): void
    {
        $this->member(['name' => 'Only']);

        $this->roster('?sort=drop%20table')->assertOk();
    }

    public function test_limit_is_capped_at_100(): void
    {
        $this->member();

        $this->assertSame(100, $this->roster('?limit=5000')->assertOk()->json('data.pagination.per_page'));
    }

    public function test_a_non_manager_cannot_read_the_roster(): void
    {
        // The roster carries member emails, so it is not a public list.
        $this->member();

        $this->actingAs(Profile::factory()->attendee()->create())
            ->getJson("/api/v1/communities/{$this->community->id}/members?search=x")
            ->assertForbidden();
    }

    public function test_a_can_manage_member_can_read_the_roster(): void
    {
        // ROLES §8.3 D1 — managing rights are a per-membership grant on an
        // attendee account, independent of tier.
        $manager = Profile::factory()->attendee()->create();
        CommunityMember::factory()->create([
            'community_id' => $this->community->id,
            'profile_id' => $manager->id,
            'can_manage' => true,
        ]);

        $this->actingAs($manager)
            ->getJson("/api/v1/communities/{$this->community->id}/members")
            ->assertOk();
    }
}
