<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\CommunityMemberStatus;
use App\Enums\JoinPolicy;
use App\Models\Community;
use App\Models\CommunityTier;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AttendeeOnboardingIdentityTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function openCommunity(string $type = 'other'): Community
    {
        $community = Community::factory()->create([
            'type' => $type,
            'join_policy' => JoinPolicy::Open->value,
        ]);
        CommunityTier::factory()->defaultTier()->forCommunity($community)->create();

        return $community;
    }

    /*
    |--------------------------------------------------------------------------
    | Handle availability + uniqueness
    |--------------------------------------------------------------------------
    */

    public function test_handle_available_returns_true_for_free_handle(): void
    {
        $this->getJson('/api/v1/handle/available?handle=freshname')
            ->assertStatus(200)
            ->assertJsonPath('data.available', true)
            ->assertJsonPath('data.suggestions', []);
    }

    public function test_handle_available_returns_false_with_suggestions_when_taken(): void
    {
        Profile::factory()->attendee()->create(['handle' => 'taken_one']);

        $response = $this->getJson('/api/v1/handle/available?handle=taken_one')
            ->assertStatus(200)
            ->assertJsonPath('data.available', false);

        $this->assertNotEmpty($response->json('data.suggestions'));
    }

    public function test_handle_available_rejects_bad_format(): void
    {
        // Too short / contains illegal chars.
        $this->getJson('/api/v1/handle/available?handle=ab')
            ->assertStatus(200)
            ->assertJsonPath('data.available', false);

        $this->getJson('/api/v1/handle/available?handle=Bad%20Name!')
            ->assertStatus(200)
            ->assertJsonPath('data.available', false);
    }

    public function test_handle_available_strips_leading_at_and_lowercases(): void
    {
        Profile::factory()->attendee()->create(['handle' => 'casing']);

        $this->getJson('/api/v1/handle/available?handle=@CASING')
            ->assertStatus(200)
            ->assertJsonPath('data.available', false);
    }

    /*
    |--------------------------------------------------------------------------
    | Onboarding submit — persists + auto-joins
    |--------------------------------------------------------------------------
    */

    public function test_attendee_onboarding_persists_and_auto_joins_open_communities(): void
    {
        $attendee = Profile::factory()->attendee()->create(['handle' => null, 'name' => null]);
        $open = $this->openCommunity('fitness_community');
        $inviteOnly = Community::factory()->inviteOnly()->create();
        CommunityTier::factory()->defaultTier()->forCommunity($inviteOnly)->create();

        $this->actingAs($attendee)
            ->putJson('/api/v1/onboarding/attendee', [
                'name' => 'Ada Lovelace',
                'handle' => '@AdaL',
                'interests' => ['fitness_community', 'run_club'],
                'community_ids' => [$open->id, $inviteOnly->id],
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.handle', 'adal')
            ->assertJsonPath('data.name', 'Ada Lovelace')
            ->assertJsonPath('data.interests', ['fitness_community', 'run_club']);

        $this->assertDatabaseHas('profiles', [
            'id' => $attendee->id,
            'handle' => 'adal',
            'name' => 'Ada Lovelace',
        ]);

        // Joined the open community...
        $this->assertDatabaseHas('community_members', [
            'community_id' => $open->id,
            'profile_id' => $attendee->id,
            'status' => CommunityMemberStatus::Active->value,
        ]);

        // ...but NOT the invite-only one (skipped silently).
        $this->assertDatabaseMissing('community_members', [
            'community_id' => $inviteOnly->id,
            'profile_id' => $attendee->id,
        ]);
    }

    public function test_attendee_onboarding_rejects_taken_handle(): void
    {
        Profile::factory()->attendee()->create(['handle' => 'occupied']);
        $attendee = Profile::factory()->attendee()->create(['handle' => null]);

        $this->actingAs($attendee)
            ->putJson('/api/v1/onboarding/attendee', [
                'name' => 'Someone',
                'handle' => 'occupied',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['handle']);
    }

    public function test_attendee_onboarding_accepts_real_17_slug_interest(): void
    {
        $attendee = Profile::factory()->attendee()->create(['handle' => null]);

        $this->actingAs($attendee)
            ->putJson('/api/v1/onboarding/attendee', [
                'name' => 'Runner',
                'handle' => 'runner_x',
                // run_club is a real GET /lookup/community-types slug, NOT one of
                // the legacy 5-value placeholder values.
                'interests' => ['run_club', 'tech_startup_community'],
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.interests', ['run_club', 'tech_startup_community']);
    }

    public function test_attendee_onboarding_rejects_legacy_placeholder_interest(): void
    {
        $attendee = Profile::factory()->attendee()->create(['handle' => null]);

        // The retired 5-value placeholder values (e.g. 'running', 'fitness') are
        // no longer valid interests — only the real 17-slug vocabulary is.
        $this->actingAs($attendee)
            ->putJson('/api/v1/onboarding/attendee', [
                'name' => 'Someone',
                'handle' => 'someone_y',
                'interests' => ['running'],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['interests.0']);
    }

    public function test_attendee_onboarding_rejects_unknown_interest_slug(): void
    {
        $attendee = Profile::factory()->attendee()->create(['handle' => null]);

        $this->actingAs($attendee)
            ->putJson('/api/v1/onboarding/attendee', [
                'name' => 'Someone',
                'handle' => 'someone_x',
                'interests' => ['not_a_real_type'],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['interests.0']);
    }

    public function test_attendee_onboarding_is_attendee_only(): void
    {
        $business = Profile::factory()->business()->create();

        $this->actingAs($business)
            ->putJson('/api/v1/onboarding/attendee', [
                'name' => 'Biz',
                'handle' => 'biz_handle',
            ])
            ->assertStatus(403);
    }

    /*
    |--------------------------------------------------------------------------
    | profiles/lookup — by email + by @handle
    |--------------------------------------------------------------------------
    */

    public function test_lookup_by_email_returns_public_card(): void
    {
        $viewer = Profile::factory()->attendee()->create();
        $target = Profile::factory()->attendee()->create([
            'email' => 'target@example.com',
            'handle' => 'targethandle',
            'name' => 'Target Person',
        ]);

        $this->actingAs($viewer)
            ->getJson('/api/v1/profiles/lookup?q=target@example.com')
            ->assertStatus(200)
            ->assertJsonPath('data.id', $target->id)
            ->assertJsonPath('data.handle', 'targethandle')
            ->assertJsonPath('data.user_type', 'attendee')
            ->assertJsonPath('data.friend_status', 'none');
    }

    public function test_lookup_by_handle_with_at_prefix(): void
    {
        $viewer = Profile::factory()->attendee()->create();
        $target = Profile::factory()->attendee()->create(['handle' => 'findme']);

        $this->actingAs($viewer)
            ->getJson('/api/v1/profiles/lookup?q=@findme')
            ->assertStatus(200)
            ->assertJsonPath('data.id', $target->id);
    }

    public function test_lookup_unknown_identifier_returns_404(): void
    {
        $viewer = Profile::factory()->attendee()->create();

        $this->actingAs($viewer)
            ->getJson('/api/v1/profiles/lookup?q=nobody@nowhere.com')
            ->assertStatus(404)
            ->assertJsonPath('error', 'profile_not_found');
    }

    /*
    |--------------------------------------------------------------------------
    | Discover interest ranking
    |--------------------------------------------------------------------------
    */

    public function test_discover_ranks_interest_match_first(): void
    {
        $viewer = Profile::factory()->attendee()->create([
            'handle' => null,
            'interests' => ['run_club'],
        ]);

        // A featured non-match and a non-featured interest match. Interest match
        // must come first despite the other being featured.
        $featuredOther = Community::factory()->create([
            'type' => 'other',
            'join_policy' => JoinPolicy::Open->value,
            'is_featured' => true,
            'name' => 'AAA Featured Other',
        ]);
        $runMatch = Community::factory()->create([
            'type' => 'run_club',
            'join_policy' => JoinPolicy::Open->value,
            'is_featured' => false,
            'name' => 'ZZZ Run Club',
        ]);

        $response = $this->actingAs($viewer)
            ->getJson('/api/v1/communities/discover')
            ->assertStatus(200);

        $this->assertSame($runMatch->id, $response->json('data.0.id'));
        $this->assertTrue($response->json('data.0.matched'));
        $this->assertSame($featuredOther->id, $response->json('data.1.id'));
        $this->assertFalse($response->json('data.1.matched'));
    }

    public function test_discover_type_filter_overrides_interest_ranking(): void
    {
        $viewer = Profile::factory()->attendee()->create([
            'handle' => null,
            'interests' => ['run_club'],
        ]);

        Community::factory()->create([
            'type' => 'run_club',
            'join_policy' => JoinPolicy::Open->value,
        ]);
        $fitness = Community::factory()->create([
            'type' => 'fitness_community',
            'join_policy' => JoinPolicy::Open->value,
        ]);

        $response = $this->actingAs($viewer)
            ->getJson('/api/v1/communities/discover?type=fitness_community')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');

        $this->assertSame($fitness->id, $response->json('data.0.id'));
    }

    /*
    |--------------------------------------------------------------------------
    | Community-group create — inherits the leader's real 17-slug type
    |--------------------------------------------------------------------------
    */

    public function test_create_community_inherits_type_from_community_profile(): void
    {
        $leader = Profile::factory()->community()->create();
        \App\Models\CommunityProfile::factory()->create([
            'profile_id' => $leader->id,
            'community_type' => 'run_club',
        ]);
        $leader->refresh()->load('communityProfile');

        // Even though the request omits `type`, the group inherits the leader's
        // community_profiles.community_type (the real 17-slug they picked at
        // sign-up), NOT the legacy placeholder default.
        $response = $this->actingAs($leader)
            ->postJson('/api/v1/communities', [
                'name' => 'Morning Milers',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.type', 'run_club');

        $this->assertDatabaseHas('communities', [
            'id' => $response->json('data.id'),
            'type' => 'run_club',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Member add by @handle (extended invite-by-email path)
    |--------------------------------------------------------------------------
    */

    public function test_leader_adds_member_by_handle(): void
    {
        $leader = Profile::factory()->community()->create();
        $community = Community::factory()->forOwner($leader)->create();
        CommunityTier::factory()->defaultTier()->forCommunity($community)->create();

        $target = Profile::factory()->attendee()->create(['handle' => 'newbie']);

        $this->actingAs($leader)
            ->postJson("/api/v1/communities/{$community->id}/members", [
                'identifier' => '@newbie',
            ])
            ->assertStatus(201);

        $this->assertDatabaseHas('community_members', [
            'community_id' => $community->id,
            'profile_id' => $target->id,
            'status' => CommunityMemberStatus::Active->value,
        ]);
    }

    public function test_leader_add_by_unknown_handle_returns_404(): void
    {
        $leader = Profile::factory()->community()->create();
        $community = Community::factory()->forOwner($leader)->create();
        CommunityTier::factory()->defaultTier()->forCommunity($community)->create();

        $this->actingAs($leader)
            ->postJson("/api/v1/communities/{$community->id}/members", [
                'identifier' => '@ghost',
            ])
            ->assertStatus(404)
            ->assertJsonPath('error', 'profile_not_found');
    }

    public function test_leader_adds_member_by_email_still_works(): void
    {
        $leader = Profile::factory()->community()->create();
        $community = Community::factory()->forOwner($leader)->create();
        CommunityTier::factory()->defaultTier()->forCommunity($community)->create();

        $target = Profile::factory()->attendee()->create(['email' => 'byemail@example.com']);

        $this->actingAs($leader)
            ->postJson("/api/v1/communities/{$community->id}/members", [
                'email' => 'byemail@example.com',
            ])
            ->assertStatus(201);

        $this->assertDatabaseHas('community_members', [
            'community_id' => $community->id,
            'profile_id' => $target->id,
        ]);
    }
}
