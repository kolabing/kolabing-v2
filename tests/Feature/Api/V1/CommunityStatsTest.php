<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\CommunityMemberStatus;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\CommunityPointLedger;
use App\Models\CommunityPoints;
use App\Models\CommunityTier;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CommunityStatsTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function ledgerAt(string $communityId, string $profileId, \DateTimeInterface $when): void
    {
        $row = CommunityPointLedger::query()->create([
            'community_id' => $communityId,
            'profile_id' => $profileId,
            'points' => 5,
            'source' => 'event_check_in',
        ]);

        $row->created_at = $when;
        $row->save();
    }

    public function test_member_counts_split_by_status_and_recency(): void
    {
        $community = Community::factory()->create();

        CommunityMember::factory()->count(2)->create([
            'community_id' => $community->id,
            'joined_at' => now()->subMonths(4),
        ]);
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'joined_at' => now()->subDays(2),
        ]);
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'joined_at' => now()->subMonths(4),
            'status' => CommunityMemberStatus::Inactive->value,
        ]);
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'joined_at' => now()->subMonths(4),
            'status' => CommunityMemberStatus::Removed->value,
        ]);

        $data = $this->actingAs($community->owner)
            ->getJson("/api/v1/communities/{$community->id}/stats")
            ->assertOk()
            ->json('data');

        $this->assertSame(5, $data['members']['total']);
        $this->assertSame(3, $data['members']['active']);
        $this->assertSame(1, $data['members']['inactive']);
        $this->assertSame(1, $data['members']['removed']);
        $this->assertSame(1, $data['members']['new_this_month']);
    }

    public function test_dormant_counts_active_members_with_no_ledger_activity_in_30_days(): void
    {
        $community = Community::factory()->create();

        $recent = CommunityMember::factory()->create(['community_id' => $community->id]);
        $stale = CommunityMember::factory()->create(['community_id' => $community->id]);
        CommunityMember::factory()->create(['community_id' => $community->id]); // never active

        $this->ledgerAt($community->id, $recent->profile_id, now()->subDays(3));
        $this->ledgerAt($community->id, $stale->profile_id, now()->subDays(60));

        $data = $this->actingAs($community->owner)
            ->getJson("/api/v1/communities/{$community->id}/stats")
            ->assertOk()
            ->json('data');

        $this->assertSame(2, $data['members']['dormant_30d']);
    }

    public function test_attendance_rate_is_zero_when_the_community_ran_no_events(): void
    {
        $community = Community::factory()->create();
        CommunityMember::factory()->count(3)->create(['community_id' => $community->id]);

        $data = $this->actingAs($community->owner)
            ->getJson("/api/v1/communities/{$community->id}/stats")
            ->assertOk()
            ->json('data');

        // Never divide by zero, and never report a misleading 100%.
        $this->assertSame(0, $data['engagement']['events_30d']);
        $this->assertEqualsWithDelta(0.0, $data['engagement']['attendance_rate_30d'], 0.001);
    }

    public function test_tier_distribution_and_top_members(): void
    {
        $community = Community::factory()->create();
        $tier = CommunityTier::factory()->forCommunity($community)->create(['name' => 'Exec', 'rank' => 3]);

        $star = Profile::factory()->attendee()->create(['name' => 'Star']);
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'profile_id' => $star->id,
            'tier_id' => $tier->id,
        ]);
        CommunityPoints::query()->create([
            'community_id' => $community->id,
            'profile_id' => $star->id,
            'points' => 980,
        ]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'tier_id' => null]);

        $data = $this->actingAs($community->owner)
            ->getJson("/api/v1/communities/{$community->id}/stats")
            ->assertOk()
            ->json('data');

        $exec = collect($data['tiers'])->firstWhere('name', 'Exec');
        $this->assertSame(1, $exec['member_count']);
        $this->assertSame('Star', $data['top_members'][0]['name']);
        $this->assertSame(980, $data['top_members'][0]['points']);
    }

    public function test_pending_counts_join_requests_and_invitations(): void
    {
        $community = Community::factory()->create();
        \App\Models\CommunityInvitation::factory()->forCommunity($community)->count(2)->create();
        \App\Models\CommunityInvitation::factory()->forCommunity($community)->expired()->create();

        $data = $this->actingAs($community->owner)
            ->getJson("/api/v1/communities/{$community->id}/stats")
            ->assertOk()
            ->json('data');

        // Expired invitations are not pending work.
        $this->assertSame(2, $data['pending']['invitations']);
        $this->assertSame(0, $data['pending']['join_requests']);
    }

    public function test_stats_is_manage_gated(): void
    {
        $community = Community::factory()->create();

        $this->actingAs(Profile::factory()->attendee()->create())
            ->getJson("/api/v1/communities/{$community->id}/stats")
            ->assertForbidden();
    }

    public function test_a_leader_with_no_subscription_gets_stats(): void
    {
        // ROLES §8.4 — this surface is NEVER paywalled.
        $community = Community::factory()->create();

        $this->assertFalse($community->owner->hasActiveSubscription());

        $this->actingAs($community->owner)
            ->getJson("/api/v1/communities/{$community->id}/stats")
            ->assertOk();
    }
}
