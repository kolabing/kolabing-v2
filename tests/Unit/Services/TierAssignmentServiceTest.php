<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\CommunityTier;
use App\Models\Event;
use App\Models\EventCheckin;
use App\Models\PointLedger;
use App\Models\Profile;
use App\Services\TierAssignmentService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class TierAssignmentServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function service(): TierAssignmentService
    {
        return app(TierAssignmentService::class);
    }

    /**
     * @return array{0: Community, 1: CommunityTier}
     */
    private function communityWithDefault(): array
    {
        $community = Community::factory()->create();
        $default = CommunityTier::factory()->defaultTier()->forCommunity($community)->create();

        return [$community, $default];
    }

    private function member(Community $community, CommunityTier $tier, ?Profile $profile = null): CommunityMember
    {
        return CommunityMember::factory()->forCommunity($community)->create([
            'profile_id' => ($profile ?? Profile::factory()->attendee()->create())->id,
            'tier_id' => $tier->id,
        ]);
    }

    public function test_xp_threshold_promotes_member(): void
    {
        [$community, $default] = $this->communityWithDefault();
        $active = CommunityTier::factory()->xpThreshold(500)->forCommunity($community)->create(['rank' => 2]);

        $profile = Profile::factory()->attendee()->create();
        PointLedger::factory()->create(['profile_id' => $profile->id, 'points' => 500]);
        $member = $this->member($community, $default, $profile);

        $this->service()->evaluateMember($member);

        $this->assertSame($active->id, $member->fresh()->tier_id);
        $this->assertNotNull($member->fresh()->tier_assigned_at);
    }

    public function test_xp_below_threshold_does_not_promote(): void
    {
        [$community, $default] = $this->communityWithDefault();
        CommunityTier::factory()->xpThreshold(500)->forCommunity($community)->create(['rank' => 2]);

        $profile = Profile::factory()->attendee()->create();
        PointLedger::factory()->create(['profile_id' => $profile->id, 'points' => 499]);
        $member = $this->member($community, $default, $profile);

        $this->service()->evaluateMember($member);

        $this->assertSame($default->id, $member->fresh()->tier_id);
    }

    public function test_tenure_rule_promotes_after_threshold_days(): void
    {
        [$community, $default] = $this->communityWithDefault();
        $veteran = CommunityTier::factory()->tenure(30)->forCommunity($community)->create(['rank' => 2]);

        $member = $this->member($community, $default);
        $member->update(['joined_at' => now()->subDays(31)]);

        $this->service()->evaluateMember($member);

        $this->assertSame($veteran->id, $member->fresh()->tier_id);
    }

    public function test_events_attended_counts_only_this_communitys_events(): void
    {
        [$community, $default] = $this->communityWithDefault();
        $regular = CommunityTier::factory()->eventsAttended(2)->forCommunity($community)->create(['rank' => 2]);

        $otherCommunity = Community::factory()->create();
        $profile = Profile::factory()->attendee()->create();
        $member = $this->member($community, $default, $profile);

        // Two check-ins on THIS community's events.
        $e1 = Event::factory()->create(['community_id' => $community->id]);
        $e2 = Event::factory()->create(['community_id' => $community->id]);
        // One check-in on ANOTHER community's event (must not count).
        $e3 = Event::factory()->create(['community_id' => $otherCommunity->id]);
        foreach ([$e1, $e2, $e3] as $event) {
            EventCheckin::factory()->create([
                'event_id' => $event->id,
                'profile_id' => $profile->id,
                'checked_in_at' => now(),
            ]);
        }

        $this->service()->evaluateMember($member);

        $this->assertSame($regular->id, $member->fresh()->tier_id);
    }

    public function test_one_event_short_does_not_promote_on_events_rule(): void
    {
        [$community, $default] = $this->communityWithDefault();
        CommunityTier::factory()->eventsAttended(2)->forCommunity($community)->create(['rank' => 2]);

        $profile = Profile::factory()->attendee()->create();
        $member = $this->member($community, $default, $profile);
        $event = Event::factory()->create(['community_id' => $community->id]);
        EventCheckin::factory()->create([
            'event_id' => $event->id,
            'profile_id' => $profile->id,
            'checked_in_at' => now(),
        ]);

        $this->service()->evaluateMember($member);

        $this->assertSame($default->id, $member->fresh()->tier_id);
    }

    public function test_highest_satisfied_rank_wins(): void
    {
        [$community, $default] = $this->communityWithDefault();
        CommunityTier::factory()->xpThreshold(100)->forCommunity($community)->create(['rank' => 2]);
        $top = CommunityTier::factory()->xpThreshold(500)->forCommunity($community)->create(['rank' => 3]);

        $profile = Profile::factory()->attendee()->create();
        PointLedger::factory()->create(['profile_id' => $profile->id, 'points' => 600]);
        $member = $this->member($community, $default, $profile);

        $this->service()->evaluateMember($member);

        $this->assertSame($top->id, $member->fresh()->tier_id);
    }

    public function test_manual_non_default_tier_is_never_auto_overwritten(): void
    {
        [$community, $default] = $this->communityWithDefault();
        $manualExec = CommunityTier::factory()->forCommunity($community)->create([
            'name' => 'Exec', 'rank' => 5, // manual rule by default
        ]);
        CommunityTier::factory()->xpThreshold(100)->forCommunity($community)->create(['rank' => 2]);

        $profile = Profile::factory()->attendee()->create();
        PointLedger::factory()->create(['profile_id' => $profile->id, 'points' => 9999]);
        $member = $this->member($community, $manualExec, $profile);

        $this->service()->evaluateMember($member);

        // Leader-placed manual tier stays untouched.
        $this->assertSame($manualExec->id, $member->fresh()->tier_id);
    }

    public function test_no_demotion_when_already_above(): void
    {
        [$community, $default] = $this->communityWithDefault();
        $high = CommunityTier::factory()->xpThreshold(500)->forCommunity($community)->create(['rank' => 3]);

        $profile = Profile::factory()->attendee()->create();
        // Only 100 XP, but member already sits on the rank-3 auto tier.
        PointLedger::factory()->create(['profile_id' => $profile->id, 'points' => 100]);
        $member = $this->member($community, $high, $profile);

        $this->service()->evaluateMember($member);

        $this->assertSame($high->id, $member->fresh()->tier_id);
    }

    public function test_inactive_member_is_skipped(): void
    {
        [$community, $default] = $this->communityWithDefault();
        CommunityTier::factory()->xpThreshold(1)->forCommunity($community)->create(['rank' => 2]);

        $profile = Profile::factory()->attendee()->create();
        PointLedger::factory()->create(['profile_id' => $profile->id, 'points' => 50]);
        $member = $this->member($community, $default, $profile);
        $member->update(['status' => 'removed']);

        $this->service()->evaluateMember($member);

        $this->assertSame($default->id, $member->fresh()->tier_id);
    }

    public function test_evaluate_community_returns_changed_count(): void
    {
        [$community, $default] = $this->communityWithDefault();
        CommunityTier::factory()->xpThreshold(10)->forCommunity($community)->create(['rank' => 2]);

        $p1 = Profile::factory()->attendee()->create();
        PointLedger::factory()->create(['profile_id' => $p1->id, 'points' => 50]);
        $this->member($community, $default, $p1);

        $p2 = Profile::factory()->attendee()->create();
        $this->member($community, $default, $p2); // 0 XP, stays

        $changed = $this->service()->evaluateCommunity($community);

        $this->assertSame(1, $changed);
    }
}
