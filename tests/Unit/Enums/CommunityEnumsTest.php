<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\CommunityMemberStatus;
use App\Enums\CommunityType;
use App\Enums\JoinPolicy;
use App\Enums\TierAssignmentRule;
use Tests\TestCase;

class CommunityEnumsTest extends TestCase
{
    public function test_community_type_values(): void
    {
        $this->assertSame(
            ['greek', 'fitness', 'running', 'business', 'other'],
            CommunityType::values()
        );
    }

    public function test_join_policy_values(): void
    {
        $this->assertSame(['open', 'invite_only'], JoinPolicy::values());
    }

    public function test_community_member_status_values(): void
    {
        $this->assertSame(['active', 'inactive', 'removed'], CommunityMemberStatus::values());
    }

    public function test_tier_assignment_rule_values(): void
    {
        $this->assertSame(
            ['manual', 'xp_threshold', 'tenure', 'events_attended'],
            TierAssignmentRule::values()
        );
    }

    public function test_manual_rule_does_not_require_threshold(): void
    {
        $this->assertFalse(TierAssignmentRule::Manual->requiresThreshold());
    }

    public function test_non_manual_rules_require_threshold(): void
    {
        $this->assertTrue(TierAssignmentRule::XpThreshold->requiresThreshold());
        $this->assertTrue(TierAssignmentRule::Tenure->requiresThreshold());
        $this->assertTrue(TierAssignmentRule::EventsAttended->requiresThreshold());
    }
}
