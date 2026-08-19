<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CommunityMemberResourceNameTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_roster_uses_the_profile_name_for_an_attendee_member(): void
    {
        // attendee_profiles has no `name` column, so the old resource — which
        // read the extended profile first — fell through to the email prefix
        // for every community member. profiles.name is the real source.
        $community = Community::factory()->create();
        $member = Profile::factory()->attendee()->create([
            'name' => 'Volkan Oluc',
            'handle' => 'volkan',
            'email' => 'volkanoluc@example.com',
        ]);
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'profile_id' => $member->id,
        ]);

        $this->actingAs($community->owner)
            ->getJson("/api/v1/communities/{$community->id}/members")
            ->assertOk()
            ->assertJsonPath('data.members.0.profile.name', 'Volkan Oluc')
            ->assertJsonPath('data.members.0.profile.handle', 'volkan')
            ->assertJsonPath('data.members.0.profile.email', 'volkanoluc@example.com');
    }

    public function test_display_name_falls_back_to_the_email_prefix_when_nothing_is_set(): void
    {
        $community = Community::factory()->create();
        $member = Profile::factory()->attendee()->create([
            'name' => null,
            'handle' => null,
            'email' => 'nameless@example.com',
        ]);
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'profile_id' => $member->id,
        ]);

        $this->actingAs($community->owner)
            ->getJson("/api/v1/communities/{$community->id}/members")
            ->assertOk()
            ->assertJsonPath('data.members.0.profile.name', 'nameless');
    }
}
