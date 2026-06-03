<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\TierAssignmentRule;
use App\Exceptions\CommunityLimitReachedException;
use App\Models\Profile;
use App\Policies\CommunityPolicy;
use App\Services\CommunityService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CommunityServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function service(): CommunityService
    {
        return app(CommunityService::class);
    }

    public function test_create_makes_community_with_auto_default_tier(): void
    {
        $owner = Profile::factory()->community()->create();

        $community = $this->service()->create($owner, [
            'name' => 'Kappa Delta — Beta Chi',
            'type' => 'greek',
        ]);

        $this->assertSame($owner->id, $community->owner_profile_id);
        $this->assertTrue($community->is_primary);

        $default = $community->tiers()->where('is_default', true)->first();
        $this->assertNotNull($default);
        $this->assertSame(TierAssignmentRule::Manual, $default->assignment_rule);
        $this->assertSame(
            ['view' => [], 'chat_channels' => [], 'perks' => [], 'capabilities' => []],
            $default->permissions
        );
        $this->assertSame(1, $community->tiers()->count());
    }

    public function test_second_community_throws_limit_reached(): void
    {
        $owner = Profile::factory()->community()->create();
        $this->service()->create($owner, ['name' => 'First', 'type' => 'other']);

        $this->expectException(CommunityLimitReachedException::class);
        $this->service()->create($owner, ['name' => 'Second', 'type' => 'other']);
    }

    public function test_policy_manage_allows_owner_and_can_manage_member_only(): void
    {
        $owner = Profile::factory()->community()->create();
        $community = $this->service()->create($owner, ['name' => 'C', 'type' => 'other']);

        $manager = Profile::factory()->attendee()->create();
        $community->members()->create([
            'profile_id' => $manager->id,
            'can_manage' => true,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $plainMember = Profile::factory()->attendee()->create();
        $community->members()->create([
            'profile_id' => $plainMember->id,
            'can_manage' => false,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $outsider = Profile::factory()->attendee()->create();

        $policy = new CommunityPolicy;
        $this->assertTrue($policy->manage($owner, $community));
        $this->assertTrue($policy->manage($manager, $community));
        $this->assertFalse($policy->manage($plainMember, $community));
        $this->assertFalse($policy->manage($outsider, $community));
    }

    public function test_inactive_can_manage_member_cannot_manage(): void
    {
        $owner = Profile::factory()->community()->create();
        $community = $this->service()->create($owner, ['name' => 'C', 'type' => 'other']);

        $suspended = Profile::factory()->attendee()->create();
        $community->members()->create([
            'profile_id' => $suspended->id,
            'can_manage' => true,
            'status' => 'removed',
            'joined_at' => now(),
        ]);

        $this->assertFalse((new CommunityPolicy)->manage($suspended, $community));
    }
}
