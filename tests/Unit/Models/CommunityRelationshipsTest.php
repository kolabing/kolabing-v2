<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\CommunityTier;
use App\Models\Event;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CommunityRelationshipsTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_community_resolves_owner_tiers_members_and_default_tier(): void
    {
        $owner = Profile::factory()->community()->create();
        $community = Community::factory()->forOwner($owner)->create();
        $tier = CommunityTier::factory()->defaultTier()->forCommunity($community)->create();
        $member = CommunityMember::factory()->forCommunity($community)->create([
            'tier_id' => $tier->id,
        ]);

        $this->assertTrue($community->owner->is($owner));
        $this->assertTrue($community->tiers->contains($tier));
        $this->assertTrue($community->members->contains($member));
        $this->assertTrue($community->defaultTier->is($tier));
    }

    public function test_member_resolves_community_profile_and_tier(): void
    {
        $community = Community::factory()->create();
        $tier = CommunityTier::factory()->forCommunity($community)->create();
        $profile = Profile::factory()->attendee()->create();
        $member = CommunityMember::factory()->forCommunity($community)->create([
            'profile_id' => $profile->id,
            'tier_id' => $tier->id,
        ]);

        $this->assertTrue($member->community->is($community));
        $this->assertTrue($member->profile->is($profile));
        $this->assertTrue($member->tier->is($tier));
    }

    public function test_profile_resolves_owned_communities_and_memberships(): void
    {
        $owner = Profile::factory()->community()->create();
        $community = Community::factory()->forOwner($owner)->create();

        $memberProfile = Profile::factory()->attendee()->create();
        CommunityMember::factory()->forCommunity($community)->create([
            'profile_id' => $memberProfile->id,
        ]);

        $this->assertTrue($owner->ownedCommunities->contains($community));
        $this->assertCount(1, $memberProfile->communityMemberships);
    }

    public function test_event_resolves_community(): void
    {
        $community = Community::factory()->create();
        $event = Event::factory()->create(['community_id' => $community->id]);

        $this->assertTrue($event->community->is($community));
    }
}
