<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\CommunityMemberStatus;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\CommunityPointLedger;
use App\Models\CommunityTier;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CommunityMemberDetailAndBulkTest extends TestCase
{
    use LazilyRefreshDatabase;

    private Community $community;

    protected function setUp(): void
    {
        parent::setUp();
        $this->community = Community::factory()->create();
    }

    private function ledger(string $profileId, int $points, string $source): void
    {
        CommunityPointLedger::query()->create([
            'community_id' => $this->community->id,
            'profile_id' => $profileId,
            'points' => $points,
            'source' => $source,
            'description' => 'test row',
        ]);
    }

    /*
    |---------------------------------------------------------------------
    | Member detail
    |---------------------------------------------------------------------
    */

    public function test_member_detail_carries_the_metrics_and_the_activity_timeline(): void
    {
        $member = CommunityMember::factory()->create([
            'community_id' => $this->community->id,
            'joined_at' => now()->subDays(5),
        ]);
        $this->ledger($member->profile_id, 10, 'event_check_in');
        $this->ledger($member->profile_id, 25, 'goal_completed');

        $data = $this->actingAs($this->community->owner)
            ->getJson("/api/v1/communities/{$this->community->id}/members/{$member->id}")
            ->assertOk()
            ->json('data');

        $this->assertSame(5, $data['member']['tenure_days']);
        $this->assertArrayHasKey('points', $data['member']);
        $this->assertCount(2, $data['activity']);
        $this->assertSame('test row', $data['activity'][0]['description']);
    }

    public function test_the_activity_timeline_is_capped_at_25_rows(): void
    {
        $member = CommunityMember::factory()->create(['community_id' => $this->community->id]);

        for ($i = 0; $i < 30; $i++) {
            $this->ledger($member->profile_id, 1, 'event_check_in');
        }

        $this->actingAs($this->community->owner)
            ->getJson("/api/v1/communities/{$this->community->id}/members/{$member->id}")
            ->assertOk()
            ->assertJsonCount(25, 'data.activity');
    }

    public function test_a_member_of_another_community_is_not_found(): void
    {
        $foreign = CommunityMember::factory()->create();

        $this->actingAs($this->community->owner)
            ->getJson("/api/v1/communities/{$this->community->id}/members/{$foreign->id}")
            ->assertNotFound();
    }

    public function test_member_detail_is_manage_gated(): void
    {
        $member = CommunityMember::factory()->create(['community_id' => $this->community->id]);

        $this->actingAs(Profile::factory()->attendee()->create())
            ->getJson("/api/v1/communities/{$this->community->id}/members/{$member->id}")
            ->assertForbidden();
    }

    /*
    |---------------------------------------------------------------------
    | Bulk update
    |---------------------------------------------------------------------
    */

    public function test_bulk_tier_assign_moves_every_row_and_stamps_the_assignment(): void
    {
        $tier = CommunityTier::factory()->forCommunity($this->community)->create(['name' => 'Exec', 'rank' => 5]);
        $members = CommunityMember::factory()->count(3)->create([
            'community_id' => $this->community->id,
            'tier_id' => null,
        ]);

        $this->actingAs($this->community->owner)
            ->patchJson("/api/v1/communities/{$this->community->id}/members", [
                'member_ids' => $members->pluck('id')->all(),
                'tier_id' => $tier->id,
            ])
            ->assertOk()
            ->assertJsonPath('data.updated', 3)
            ->assertJsonPath('data.skipped', 0);

        foreach ($members as $member) {
            $fresh = $member->fresh();
            $this->assertSame($tier->id, $fresh->tier_id);
            $this->assertNotNull($fresh->tier_assigned_at);
        }
    }

    public function test_bulk_status_change(): void
    {
        $members = CommunityMember::factory()->count(2)->create(['community_id' => $this->community->id]);

        $this->actingAs($this->community->owner)
            ->patchJson("/api/v1/communities/{$this->community->id}/members", [
                'member_ids' => $members->pluck('id')->all(),
                'status' => CommunityMemberStatus::Removed->value,
            ])
            ->assertOk()
            ->assertJsonPath('data.updated', 2);

        $this->assertSame(CommunityMemberStatus::Removed, $members->first()->fresh()->status);
    }

    public function test_ids_from_another_community_are_skipped_never_written(): void
    {
        $mine = CommunityMember::factory()->create(['community_id' => $this->community->id, 'can_manage' => false]);
        $foreign = CommunityMember::factory()->create(['can_manage' => false]);

        $this->actingAs($this->community->owner)
            ->patchJson("/api/v1/communities/{$this->community->id}/members", [
                'member_ids' => [$mine->id, $foreign->id],
                'can_manage' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.updated', 1)
            ->assertJsonPath('data.skipped', 1);

        $this->assertTrue($mine->fresh()->can_manage);
        $this->assertFalse($foreign->fresh()->can_manage);
    }

    public function test_more_than_a_hundred_ids_is_rejected(): void
    {
        $ids = array_map(fn (): string => (string) \Illuminate\Support\Str::uuid(), range(1, 101));

        $this->actingAs($this->community->owner)
            ->patchJson("/api/v1/communities/{$this->community->id}/members", ['member_ids' => $ids])
            ->assertStatus(422)
            ->assertJsonValidationErrors('member_ids');
    }

    public function test_a_tier_from_another_community_is_rejected(): void
    {
        $member = CommunityMember::factory()->create(['community_id' => $this->community->id]);
        $foreignTier = CommunityTier::factory()->create();

        $this->actingAs($this->community->owner)
            ->patchJson("/api/v1/communities/{$this->community->id}/members", [
                'member_ids' => [$member->id],
                'tier_id' => $foreignTier->id,
            ])
            ->assertStatus(422)
            ->assertJsonPath('error', 'tier_not_in_community');
    }

    public function test_bulk_update_is_manage_gated(): void
    {
        $member = CommunityMember::factory()->create(['community_id' => $this->community->id]);

        $this->actingAs(Profile::factory()->attendee()->create())
            ->patchJson("/api/v1/communities/{$this->community->id}/members", [
                'member_ids' => [$member->id],
                'can_manage' => true,
            ])
            ->assertForbidden();
    }
}
