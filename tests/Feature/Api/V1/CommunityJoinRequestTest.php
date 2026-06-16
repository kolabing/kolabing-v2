<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\CommunityMemberStatus;
use App\Enums\JoinRequestStatus;
use App\Enums\NotificationType;
use App\Models\Community;
use App\Models\CommunityJoinRequest;
use App\Models\CommunityMember;
use App\Models\CommunityTier;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CommunityJoinRequestTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake(); // SendPushNotification is queued; we assert on DB rows.
    }

    private function inviteOnlyCommunity(): Community
    {
        $community = Community::factory()->inviteOnly()->create();
        CommunityTier::factory()->defaultTier()->forCommunity($community)->create();

        return $community;
    }

    public function test_invite_only_request_creates_pending_and_notifies_leader(): void
    {
        $community = $this->inviteOnlyCommunity();
        $leaderId = $community->owner_profile_id;
        $attendee = Profile::factory()->attendee()->create();

        $response = $this->actingAs($attendee)
            ->postJson("/api/v1/communities/{$community->id}/join-requests");

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('community_join_requests', [
            'community_id' => $community->id,
            'profile_id' => $attendee->id,
            'status' => JoinRequestStatus::Pending->value,
        ]);

        $this->assertDatabaseHas('notifications', [
            'profile_id' => $leaderId,
            'type' => NotificationType::CommunityJoinRequested->value,
            'target_id' => $community->id,
        ]);
    }

    public function test_open_community_rejects_the_request_path(): void
    {
        $community = Community::factory()->create(); // open by default
        CommunityTier::factory()->defaultTier()->forCommunity($community)->create();
        $attendee = Profile::factory()->attendee()->create();

        $response = $this->actingAs($attendee)
            ->postJson("/api/v1/communities/{$community->id}/join-requests");

        $response->assertStatus(422)->assertJsonPath('error', 'community_is_open');
        $this->assertDatabaseCount('community_join_requests', 0);
    }

    public function test_re_request_while_pending_is_idempotent(): void
    {
        $community = $this->inviteOnlyCommunity();
        $attendee = Profile::factory()->attendee()->create();

        $first = $this->actingAs($attendee)
            ->postJson("/api/v1/communities/{$community->id}/join-requests");
        $first->assertStatus(201);

        $second = $this->actingAs($attendee)
            ->postJson("/api/v1/communities/{$community->id}/join-requests");
        $second->assertStatus(200);

        $this->assertSame($first->json('data.id'), $second->json('data.id'));
        $this->assertDatabaseCount('community_join_requests', 1);
    }

    public function test_non_manager_cannot_list_requests(): void
    {
        $community = $this->inviteOnlyCommunity();
        $stranger = Profile::factory()->attendee()->create();

        $this->actingAs($stranger)
            ->getJson("/api/v1/communities/{$community->id}/join-requests")
            ->assertStatus(403);
    }

    public function test_leader_lists_pending_requests_with_requester_profile(): void
    {
        $community = $this->inviteOnlyCommunity();
        $leader = Profile::query()->find($community->owner_profile_id);
        // Attendee display name in the resource is derived from the extended
        // profile name, falling back to the email local-part when absent.
        $attendee = Profile::factory()->attendee()->create(['email' => 'ada.lovelace@example.com']);
        CommunityJoinRequest::factory()->forCommunity($community)->create([
            'profile_id' => $attendee->id,
        ]);

        $response = $this->actingAs($leader)
            ->getJson("/api/v1/communities/{$community->id}/join-requests");

        $response->assertStatus(200)
            ->assertJsonPath('data.0.status', 'pending')
            ->assertJsonPath('data.0.profile.name', 'ada.lovelace')
            ->assertJsonPath('data.0.profile_id', $attendee->id);
    }

    public function test_non_manager_cannot_approve(): void
    {
        $community = $this->inviteOnlyCommunity();
        $attendee = Profile::factory()->attendee()->create();
        $request = CommunityJoinRequest::factory()->forCommunity($community)->create([
            'profile_id' => $attendee->id,
        ]);
        $stranger = Profile::factory()->attendee()->create();

        $this->actingAs($stranger)
            ->postJson("/api/v1/join-requests/{$request->id}/approve")
            ->assertStatus(403);

        $this->assertDatabaseMissing('community_members', [
            'community_id' => $community->id,
            'profile_id' => $attendee->id,
        ]);
    }

    public function test_approve_creates_active_member_notifies_and_flips_status(): void
    {
        $community = $this->inviteOnlyCommunity();
        $leader = Profile::query()->find($community->owner_profile_id);
        $attendee = Profile::factory()->attendee()->create();
        $request = CommunityJoinRequest::factory()->forCommunity($community)->create([
            'profile_id' => $attendee->id,
        ]);

        $response = $this->actingAs($leader)
            ->postJson("/api/v1/join-requests/{$request->id}/approve");

        $response->assertStatus(200)->assertJsonPath('data.status', 'approved');

        // Active member created with the default tier (like a normal join).
        $member = CommunityMember::query()
            ->where('community_id', $community->id)
            ->where('profile_id', $attendee->id)
            ->first();
        $this->assertNotNull($member);
        $this->assertSame(CommunityMemberStatus::Active, $member->status);
        $this->assertSame($community->defaultTier->id, $member->tier_id);

        // Requester notified.
        $this->assertDatabaseHas('notifications', [
            'profile_id' => $attendee->id,
            'type' => NotificationType::CommunityJoinApproved->value,
        ]);

        // GET /communities/{id} now reports is_member + my_join_request_status.
        $show = $this->actingAs($attendee)->getJson("/api/v1/communities/{$community->id}");
        $show->assertStatus(200)
            ->assertJsonPath('data.is_member', true)
            ->assertJsonPath('data.my_join_request_status', 'approved');
    }

    public function test_double_approve_is_guarded(): void
    {
        $community = $this->inviteOnlyCommunity();
        $leader = Profile::query()->find($community->owner_profile_id);
        $attendee = Profile::factory()->attendee()->create();
        $request = CommunityJoinRequest::factory()->forCommunity($community)->approved()->create([
            'profile_id' => $attendee->id,
        ]);

        $this->actingAs($leader)
            ->postJson("/api/v1/join-requests/{$request->id}/approve")
            ->assertStatus(422)
            ->assertJsonPath('error', 'already_decided');
    }

    public function test_decline_marks_declined_and_notifies(): void
    {
        $community = $this->inviteOnlyCommunity();
        $leader = Profile::query()->find($community->owner_profile_id);
        $attendee = Profile::factory()->attendee()->create();
        $request = CommunityJoinRequest::factory()->forCommunity($community)->create([
            'profile_id' => $attendee->id,
        ]);

        $this->actingAs($leader)
            ->postJson("/api/v1/join-requests/{$request->id}/decline")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'declined');

        $this->assertDatabaseHas('notifications', [
            'profile_id' => $attendee->id,
            'type' => NotificationType::CommunityJoinDeclined->value,
        ]);

        $this->assertDatabaseMissing('community_members', [
            'community_id' => $community->id,
            'profile_id' => $attendee->id,
        ]);

        $show = $this->actingAs($attendee)->getJson("/api/v1/communities/{$community->id}");
        $show->assertJsonPath('data.is_member', false)
            ->assertJsonPath('data.my_join_request_status', 'declined');
    }

    public function test_show_exposes_null_status_for_non_requester(): void
    {
        $community = $this->inviteOnlyCommunity();
        $stranger = Profile::factory()->attendee()->create();

        $this->actingAs($stranger)
            ->getJson("/api/v1/communities/{$community->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.join_policy', 'invite_only')
            ->assertJsonPath('data.is_member', false)
            ->assertJsonPath('data.my_join_request_status', null);
    }
}
