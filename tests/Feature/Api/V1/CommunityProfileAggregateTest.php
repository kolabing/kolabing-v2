<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\EventSignupStatus;
use App\Models\AttendeeProfile;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\CommunityTier;
use App\Models\Event;
use App\Models\EventSignup;
use App\Models\Profile;
use App\Models\ProfileGalleryPhoto;
use App\Services\CommunityService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CommunityProfileAggregateTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function makeCommunity(Profile $owner): Community
    {
        return app(CommunityService::class)->create($owner, [
            'name' => 'Barcelona Run Club',
            'type' => 'running',
            'description' => 'We run.',
        ]);
    }

    private function upcomingEvent(Community $community, ?array $tierGate = null): Event
    {
        return Event::factory()->create([
            'profile_id' => $community->owner_profile_id,
            'community_id' => $community->id,
            'event_date' => now()->addDays(7)->toDateString(),
            'starts_at' => now()->addDays(7),
            'ends_at' => now()->addDays(7)->addHours(2),
            'tier_gate' => $tierGate,
        ]);
    }

    private function addMember(Community $community, ?CommunityTier $tier = null, int $points = 0): Profile
    {
        $profile = Profile::factory()->attendee()->create();
        AttendeeProfile::factory()->create([
            'profile_id' => $profile->id,
            'total_points' => $points,
        ]);

        CommunityMember::factory()->forCommunity($community)->create([
            'profile_id' => $profile->id,
            'tier_id' => $tier?->id ?? $community->defaultTier->id,
            'status' => 'active',
        ]);

        return $profile;
    }

    public function test_member_sees_full_payload_with_tier_rank_and_chats(): void
    {
        $leader = Profile::factory()->community()->create();
        $community = $this->makeCommunity($leader);

        // A higher tier the member will hold.
        $execTier = CommunityTier::factory()->forCommunity($community)->create([
            'name' => 'Exec',
            'rank' => 5,
        ]);

        // Member with points so the chapter rank resolves.
        $member = $this->addMember($community, $execTier, points: 120);

        // Another lower-ranked member for the preview + a non-zero chapter rank.
        $this->addMember($community, points: 40);

        // Two upcoming events: one public, one tier-gated, with going sign-ups.
        $public = $this->upcomingEvent($community);
        $gated = $this->upcomingEvent($community, tierGate: [$execTier->id]);

        EventSignup::query()->create([
            'event_id' => $public->id,
            'profile_id' => $member->id,
            'status' => EventSignupStatus::Going->value,
        ]);

        // Owner gallery photos become the community gallery.
        ProfileGalleryPhoto::factory()->count(2)->create(['profile_id' => $leader->id]);

        $response = $this->actingAs($member)
            ->getJson("/api/v1/communities/{$community->id}/profile")
            ->assertOk();

        // community block + counts.
        $response->assertJsonPath('data.community.id', $community->id)
            ->assertJsonPath('data.community.name', 'Barcelona Run Club')
            ->assertJsonPath('data.community.slug', $community->slug)
            ->assertJsonPath('data.community.type', 'running')
            ->assertJsonPath('data.community.member_count', 2)
            ->assertJsonPath('data.community.event_count', 2);

        // tiers ordered rank desc: Exec(5) then default Member(1).
        $response->assertJsonCount(2, 'data.tiers')
            ->assertJsonPath('data.tiers.0.name', 'Exec')
            ->assertJsonPath('data.tiers.0.rank', 5)
            ->assertJsonPath('data.tiers.1.is_default', true);

        // upcoming events: 2, with visibility + going_count.
        $response->assertJsonCount(2, 'data.upcoming_events');
        $events = collect($response->json('data.upcoming_events'));
        $this->assertEqualsCanonicalizing(
            ['public', 'tier_gated'],
            $events->pluck('visibility')->all(),
        );
        $this->assertSame(1, $events->firstWhere('id', $public->id)['going_count']);
        $this->assertSame(0, $events->firstWhere('id', $gated->id)['going_count']);

        // members preview (max 12) returns both members with tier names.
        $response->assertJsonCount(2, 'data.members_preview');
        $preview = collect($response->json('data.members_preview'));
        $this->assertSame('Exec', $preview->firstWhere('profile_id', $member->id)['tier_name']);

        // gallery from the owner profile.
        $response->assertJsonCount(2, 'data.gallery');

        // chats: member sees the main thread (is_open true).
        $this->assertNotNull($response->json('data.chats'));
        $response->assertJsonCount(1, 'data.chats')
            ->assertJsonPath('data.chats.0.type', 'community_main')
            ->assertJsonPath('data.chats.0.is_open', true);

        // viewer block.
        $response->assertJsonPath('data.viewer.is_member', true)
            ->assertJsonPath('data.viewer.is_owner', false)
            ->assertJsonPath('data.viewer.can_manage', false)
            ->assertJsonPath('data.viewer.tier_name', 'Exec')
            ->assertJsonPath('data.viewer.chapter_rank', 1);
    }

    public function test_non_member_sees_public_subset_without_chats(): void
    {
        $leader = Profile::factory()->community()->create();
        $community = $this->makeCommunity($leader);

        $this->addMember($community, points: 30);
        $this->upcomingEvent($community);

        $outsider = Profile::factory()->attendee()->create();
        AttendeeProfile::factory()->create([
            'profile_id' => $outsider->id,
            'total_points' => 999,
        ]);

        $response = $this->actingAs($outsider)
            ->getJson("/api/v1/communities/{$community->id}/profile")
            ->assertOk();

        // Public data is present.
        $response->assertJsonPath('data.community.member_count', 1)
            ->assertJsonPath('data.community.event_count', 1)
            ->assertJsonCount(1, 'data.upcoming_events')
            ->assertJsonCount(1, 'data.members_preview');

        // Chats are null for a non-member; viewer reflects no membership.
        $response->assertJsonPath('data.chats', null)
            ->assertJsonPath('data.viewer.is_member', false)
            ->assertJsonPath('data.viewer.is_owner', false)
            ->assertJsonPath('data.viewer.can_manage', false)
            ->assertJsonPath('data.viewer.tier_name', null)
            ->assertJsonPath('data.viewer.chapter_rank', null);
    }

    public function test_owner_is_flagged_and_can_manage(): void
    {
        $leader = Profile::factory()->community()->create();
        $community = $this->makeCommunity($leader);

        $response = $this->actingAs($leader)
            ->getJson("/api/v1/communities/{$community->id}/profile")
            ->assertOk();

        $response->assertJsonPath('data.viewer.is_owner', true)
            ->assertJsonPath('data.viewer.can_manage', true)
            // Owner is not a member row, so is_member stays false.
            ->assertJsonPath('data.viewer.is_member', false);

        // Owner still gets the chats array (main thread), not null.
        $this->assertNotNull($response->json('data.chats'));
        $response->assertJsonPath('data.chats.0.type', 'community_main');
    }

    public function test_requires_authentication(): void
    {
        $leader = Profile::factory()->community()->create();
        $community = $this->makeCommunity($leader);

        $this->getJson("/api/v1/communities/{$community->id}/profile")
            ->assertStatus(401);
    }
}
