<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Enums\CommunityInvitationStatus;
use App\Models\CommunityInvitation;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CommunityInvitationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_a_fresh_pending_invitation_is_claimable(): void
    {
        $this->assertTrue(CommunityInvitation::factory()->create()->isClaimable());
    }

    public function test_an_expired_invitation_is_not_claimable(): void
    {
        $this->assertFalse(CommunityInvitation::factory()->expired()->create()->isClaimable());
    }

    public function test_a_revoked_invitation_is_not_claimable(): void
    {
        $invitation = CommunityInvitation::factory()->revoked()->create();

        $this->assertSame(CommunityInvitationStatus::Revoked, $invitation->status);
        $this->assertFalse($invitation->isClaimable());
    }

    public function test_it_belongs_to_a_community_and_an_optional_tier(): void
    {
        $invitation = CommunityInvitation::factory()->create();

        $this->assertNotNull($invitation->community);
        $this->assertNull($invitation->tier);
    }
}
