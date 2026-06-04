<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\CommunityMemberStatus;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\CommunityTier;
use App\Models\Profile;
use App\Services\CommunityMemberService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CommunityMemberRosterTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function service(): CommunityMemberService
    {
        return app(CommunityMemberService::class);
    }

    private function openCommunityWithDefault(): Community
    {
        $community = Community::factory()->create();
        CommunityTier::factory()->defaultTier()->forCommunity($community)->create();

        return $community;
    }

    public function test_open_self_join_lands_in_default_tier(): void
    {
        $community = $this->openCommunityWithDefault();
        $default = $community->defaultTier;
        $profile = Profile::factory()->attendee()->create();

        $member = $this->service()->join($community, $profile);

        $this->assertSame($default->id, $member->tier_id);
        $this->assertSame(CommunityMemberStatus::Active, $member->status);
    }

    public function test_invite_only_self_join_is_blocked(): void
    {
        $community = Community::factory()->inviteOnly()->create();
        CommunityTier::factory()->defaultTier()->forCommunity($community)->create();
        $profile = Profile::factory()->attendee()->create();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('invite_only');

        $this->service()->join($community, $profile);
    }

    public function test_leader_can_add_member_to_invite_only_community(): void
    {
        $community = Community::factory()->inviteOnly()->create();
        CommunityTier::factory()->defaultTier()->forCommunity($community)->create();
        $profile = Profile::factory()->attendee()->create();

        $member = $this->service()->addMember($community, $profile->id);

        $this->assertSame($profile->id, $member->profile_id);
        $this->assertSame($community->defaultTier->id, $member->tier_id);
    }

    public function test_can_manage_is_independent_of_tier(): void
    {
        $community = $this->openCommunityWithDefault();
        $member = CommunityMember::factory()->forCommunity($community)->create([
            'tier_id' => $community->defaultTier->id,
        ]);

        $updated = $this->service()->updateMember($member, ['can_manage' => true]);

        $this->assertTrue($updated->can_manage);
        $this->assertSame($community->defaultTier->id, $updated->tier_id);
    }

    public function test_manual_tier_set_stamps_tier_assigned_at(): void
    {
        $community = $this->openCommunityWithDefault();
        $exec = CommunityTier::factory()->forCommunity($community)->create(['rank' => 3]);
        $member = CommunityMember::factory()->forCommunity($community)->create([
            'tier_id' => $community->defaultTier->id,
        ]);

        $updated = $this->service()->updateMember($member, ['tier_id' => $exec->id]);

        $this->assertSame($exec->id, $updated->tier_id);
        $this->assertNotNull($updated->tier_assigned_at);
    }

    public function test_join_is_idempotent_on_unique_constraint(): void
    {
        $community = $this->openCommunityWithDefault();
        $profile = Profile::factory()->attendee()->create();

        $first = $this->service()->join($community, $profile);
        $second = $this->service()->join($community, $profile);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, $community->members()->count());
    }

    public function test_remove_sets_status_removed(): void
    {
        $community = $this->openCommunityWithDefault();
        $member = CommunityMember::factory()->forCommunity($community)->create();

        $this->service()->remove($member);

        $this->assertSame(CommunityMemberStatus::Removed, $member->fresh()->status);
    }
}
