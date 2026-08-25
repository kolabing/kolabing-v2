<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\CommunityInvitationStatus;
use App\Enums\CommunityMemberStatus;
use App\Mail\CommunityInvitationMail;
use App\Models\Community;
use App\Models\CommunityInvitation;
use App\Models\CommunityMember;
use App\Models\CommunityTier;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CommunityInvitationEndpointsTest extends TestCase
{
    use LazilyRefreshDatabase;

    private Community $community;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->community = Community::factory()->create();
    }

    private function invite(array $payload): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->community->owner)
            ->postJson("/api/v1/communities/{$this->community->id}/invitations", $payload);
    }

    public function test_a_manager_invites_a_single_email(): void
    {
        $res = $this->invite(['email' => 'New@Example.com'])->assertCreated();

        $this->assertSame(1, $res->json('data.invited'));
        $this->assertSame(0, $res->json('data.already_members'));
        $this->assertDatabaseHas('community_invitations', [
            'community_id' => $this->community->id,
            // Normalised to lowercase so the claim-on-register lookup matches.
            'email' => 'new@example.com',
            'status' => CommunityInvitationStatus::Pending->value,
        ]);
        Mail::assertQueued(CommunityInvitationMail::class);
    }

    public function test_a_manager_invites_a_pasted_list(): void
    {
        $res = $this->invite([
            'emails' => ['a@example.com', 'b@example.com', 'c@example.com'],
        ])->assertCreated();

        $this->assertSame(3, $res->json('data.invited'));
        $this->assertSame(3, CommunityInvitation::query()->count());
    }

    public function test_re_inviting_the_same_email_reuses_the_pending_row_and_refreshes_the_window(): void
    {
        $this->invite(['email' => 'dup@example.com'])->assertCreated();
        $first = CommunityInvitation::query()->firstOrFail();
        $first->update(['expires_at' => now()->addDay()]);

        $this->invite(['email' => 'dup@example.com'])->assertCreated();

        $this->assertSame(1, CommunityInvitation::query()->count());
        $this->assertTrue($first->fresh()->expires_at->gt(now()->addDays(20)));
    }

    public function test_inviting_an_existing_active_member_reports_already_member(): void
    {
        $existing = Profile::factory()->attendee()->create(['email' => 'member@example.com']);
        CommunityMember::factory()->create([
            'community_id' => $this->community->id,
            'profile_id' => $existing->id,
        ]);

        $res = $this->invite(['email' => 'member@example.com'])->assertCreated();

        $this->assertSame(1, $res->json('data.already_members'));
        $this->assertSame(0, CommunityInvitation::query()->count());
    }

    public function test_a_tier_from_another_community_is_rejected(): void
    {
        $foreign = CommunityTier::factory()->create();

        $this->invite(['email' => 'x@example.com', 'tier_id' => $foreign->id])
            ->assertStatus(422)
            ->assertJsonPath('error', 'tier_not_in_community');
    }

    public function test_more_than_fifty_emails_is_rejected(): void
    {
        $emails = array_map(fn (int $i): string => "u{$i}@example.com", range(1, 51));

        $this->invite(['emails' => $emails])->assertStatus(422)->assertJsonValidationErrors('emails');
    }

    public function test_index_lists_pending_by_default_and_all_on_request(): void
    {
        CommunityInvitation::factory()->forCommunity($this->community)->create();
        CommunityInvitation::factory()->forCommunity($this->community)->revoked()->create();

        $this->actingAs($this->community->owner)
            ->getJson("/api/v1/communities/{$this->community->id}/invitations")
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->actingAs($this->community->owner)
            ->getJson("/api/v1/communities/{$this->community->id}/invitations?status=all")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_the_token_is_never_in_the_payload(): void
    {
        $invitation = CommunityInvitation::factory()->forCommunity($this->community)->create();

        $body = $this->actingAs($this->community->owner)
            ->getJson("/api/v1/communities/{$this->community->id}/invitations")
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString($invitation->token, (string) $body);
    }

    public function test_resend_refreshes_the_window_and_sends_again(): void
    {
        $invitation = CommunityInvitation::factory()->forCommunity($this->community)
            ->create(['expires_at' => now()->addDay()]);

        $this->actingAs($this->community->owner)
            ->postJson("/api/v1/invitations/{$invitation->id}/resend")
            ->assertOk();

        $this->assertTrue($invitation->fresh()->expires_at->gt(now()->addDays(20)));
        Mail::assertQueued(CommunityInvitationMail::class);
    }

    public function test_revoke_marks_it_revoked_and_it_stops_being_claimable(): void
    {
        $invitation = CommunityInvitation::factory()->forCommunity($this->community)->create();

        $this->actingAs($this->community->owner)
            ->deleteJson("/api/v1/invitations/{$invitation->id}")
            ->assertOk()
            ->assertJsonPath('data.status', CommunityInvitationStatus::Revoked->value)
            ->assertJsonPath('data.is_claimable', false);
    }

    public function test_accept_makes_the_caller_a_member_on_the_invited_tier(): void
    {
        $tier = CommunityTier::factory()->forCommunity($this->community)->create(['name' => 'Pledge', 'rank' => 1]);
        $invitation = CommunityInvitation::factory()->forCommunity($this->community)->create(['tier_id' => $tier->id]);
        $invitee = Profile::factory()->attendee()->create();

        $this->actingAs($invitee)
            ->postJson("/api/v1/invitations/accept/{$invitation->token}")
            ->assertOk()
            ->assertJsonPath('data.status', CommunityInvitationStatus::Accepted->value);

        $this->assertDatabaseHas('community_members', [
            'community_id' => $this->community->id,
            'profile_id' => $invitee->id,
            'tier_id' => $tier->id,
            'status' => CommunityMemberStatus::Active->value,
        ]);
    }

    public function test_accept_is_422_for_an_expired_invitation(): void
    {
        $invitation = CommunityInvitation::factory()->forCommunity($this->community)->expired()->create();

        $this->actingAs(Profile::factory()->attendee()->create())
            ->postJson("/api/v1/invitations/accept/{$invitation->token}")
            ->assertStatus(422)
            ->assertJsonPath('error', 'invitation_not_claimable');
    }

    public function test_accept_is_422_for_a_revoked_invitation(): void
    {
        $invitation = CommunityInvitation::factory()->forCommunity($this->community)->revoked()->create();

        $this->actingAs(Profile::factory()->attendee()->create())
            ->postJson("/api/v1/invitations/accept/{$invitation->token}")
            ->assertStatus(422);
    }

    public function test_accept_is_404_for_an_unknown_token(): void
    {
        $this->actingAs(Profile::factory()->attendee()->create())
            ->postJson('/api/v1/invitations/accept/nope')
            ->assertNotFound()
            ->assertJsonPath('error', 'invitation_not_found');
    }

    public function test_accepting_twice_leaves_exactly_one_membership(): void
    {
        $invitation = CommunityInvitation::factory()->forCommunity($this->community)->create();
        $invitee = Profile::factory()->attendee()->create();

        $this->actingAs($invitee)->postJson("/api/v1/invitations/accept/{$invitation->token}")->assertOk();
        $this->actingAs($invitee)->postJson("/api/v1/invitations/accept/{$invitation->token}")->assertStatus(422);

        $this->assertSame(1, CommunityMember::query()
            ->where('community_id', $this->community->id)
            ->where('profile_id', $invitee->id)
            ->count());
    }

    public function test_a_non_manager_cannot_invite_list_resend_or_revoke(): void
    {
        $outsider = Profile::factory()->attendee()->create();
        $invitation = CommunityInvitation::factory()->forCommunity($this->community)->create();

        $this->actingAs($outsider)
            ->postJson("/api/v1/communities/{$this->community->id}/invitations", ['email' => 'a@example.com'])
            ->assertForbidden();
        $this->actingAs($outsider)
            ->getJson("/api/v1/communities/{$this->community->id}/invitations")
            ->assertForbidden();
        $this->actingAs($outsider)
            ->postJson("/api/v1/invitations/{$invitation->id}/resend")
            ->assertForbidden();
        $this->actingAs($outsider)
            ->deleteJson("/api/v1/invitations/{$invitation->id}")
            ->assertForbidden();
    }

    public function test_a_can_manage_member_can_invite(): void
    {
        // ROLES §8.3 D1 — managing rights are a per-membership grant on an
        // attendee account, independent of tier.
        $manager = Profile::factory()->attendee()->create();
        CommunityMember::factory()->create([
            'community_id' => $this->community->id,
            'profile_id' => $manager->id,
            'can_manage' => true,
        ]);

        $this->actingAs($manager)
            ->postJson("/api/v1/communities/{$this->community->id}/invitations", ['email' => 'via-manager@example.com'])
            ->assertCreated();
    }
}
