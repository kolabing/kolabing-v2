<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\CommunityInvitationStatus;
use App\Enums\CommunityMemberStatus;
use App\Mail\CommunityInvitationMail;
use App\Models\Community;
use App\Models\CommunityInvitation;
use App\Models\CommunityTier;
use App\Models\Profile;
use App\Services\CommunityInvitationService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CommunityInvitationClaimOnRegisterTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    private function claimer(): CommunityInvitationService
    {
        return app(CommunityInvitationService::class);
    }

    public function test_a_pending_invitation_becomes_a_membership_when_that_email_registers(): void
    {
        $community = Community::factory()->create();
        $tier = CommunityTier::factory()->forCommunity($community)->create(['name' => 'Pledge', 'rank' => 1]);
        $invitation = CommunityInvitation::factory()->forCommunity($community)->create([
            'email' => 'invitee@example.com',
            'tier_id' => $tier->id,
        ]);

        $profile = Profile::factory()->attendee()->create(['email' => 'invitee@example.com']);
        $this->claimer()->claimForSafely($profile);

        $this->assertDatabaseHas('community_members', [
            'community_id' => $community->id,
            'profile_id' => $profile->id,
            'tier_id' => $tier->id,
            'status' => CommunityMemberStatus::Active->value,
        ]);
        $this->assertSame(CommunityInvitationStatus::Accepted, $invitation->fresh()->status);
        $this->assertSame($profile->id, $invitation->fresh()->accepted_profile_id);
    }

    public function test_it_claims_every_pending_invitation_across_communities(): void
    {
        $a = Community::factory()->create();
        $b = Community::factory()->create();
        CommunityInvitation::factory()->forCommunity($a)->create(['email' => 'multi@example.com']);
        CommunityInvitation::factory()->forCommunity($b)->create(['email' => 'multi@example.com']);

        $profile = Profile::factory()->attendee()->create(['email' => 'multi@example.com']);
        $this->claimer()->claimForSafely($profile);

        $this->assertDatabaseHas('community_members', ['community_id' => $a->id, 'profile_id' => $profile->id]);
        $this->assertDatabaseHas('community_members', ['community_id' => $b->id, 'profile_id' => $profile->id]);
    }

    public function test_an_expired_invitation_is_not_claimed(): void
    {
        $community = Community::factory()->create();
        CommunityInvitation::factory()->forCommunity($community)->expired()->create(['email' => 'late@example.com']);

        $profile = Profile::factory()->attendee()->create(['email' => 'late@example.com']);
        $this->claimer()->claimForSafely($profile);

        $this->assertDatabaseMissing('community_members', ['profile_id' => $profile->id]);
    }

    public function test_a_revoked_invitation_is_not_claimed(): void
    {
        $community = Community::factory()->create();
        CommunityInvitation::factory()->forCommunity($community)->revoked()->create(['email' => 'nope@example.com']);

        $profile = Profile::factory()->attendee()->create(['email' => 'nope@example.com']);
        $this->claimer()->claimForSafely($profile);

        $this->assertDatabaseMissing('community_members', ['profile_id' => $profile->id]);
    }

    public function test_a_failure_inside_the_claim_hook_never_breaks_signup(): void
    {
        // The community is gone out from under the invitation.
        $community = Community::factory()->create();
        CommunityInvitation::factory()->forCommunity($community)->create(['email' => 'orphan@example.com']);
        Community::query()->whereKey($community->id)->delete();

        $profile = Profile::factory()->attendee()->create(['email' => 'orphan@example.com']);

        // Must not throw.
        $this->claimer()->claimForSafely($profile);

        $this->assertDatabaseMissing('community_members', ['profile_id' => $profile->id]);
    }

    public function test_the_invitation_email_carries_the_join_link_with_the_token(): void
    {
        $community = Community::factory()->create(['name' => 'Run Club']);

        $this->actingAs($community->owner)
            ->postJson("/api/v1/communities/{$community->id}/invitations", ['email' => 'new@example.com'])
            ->assertCreated();

        $invitation = CommunityInvitation::query()->firstOrFail();

        Mail::assertQueued(CommunityInvitationMail::class, function (CommunityInvitationMail $mail) use ($invitation): bool {
            return $mail->hasTo('new@example.com') && $mail->invitation->is($invitation);
        });

        $rendered = $invitation->fresh()->token;
        $this->assertNotEmpty($rendered);
    }
}
