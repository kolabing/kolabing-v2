<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Community;
use App\Models\CommunityInvitation;
use App\Models\CommunityMember;
use App\Models\CommunityTier;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * ROLES §8.4 — the community members & tiers surface is NEVER paywalled.
 *
 * §6 of the same document names "paywalling a community" as the single most
 * repeated regression in this codebase, so this is a standing guard, not a
 * nice-to-have. Every endpoint added for the Community Hub is exercised by an
 * owner with no subscription AND by a can_manage attendee with no
 * subscription.
 */
class CommunityNeverPaywalledTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    public function test_no_endpoint_in_the_community_hub_is_subscription_gated(): void
    {
        $community = Community::factory()->create();
        $tier = CommunityTier::factory()->forCommunity($community)->create();
        $member = CommunityMember::factory()->create(['community_id' => $community->id]);
        $invitation = CommunityInvitation::factory()->forCommunity($community)->create();

        $manager = Profile::factory()->attendee()->create();
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'profile_id' => $manager->id,
            'can_manage' => true,
        ]);

        // Neither actor has ever paid for anything.
        $this->assertFalse($community->owner->hasActiveSubscription());
        $this->assertFalse($manager->hasActiveSubscription());

        foreach ([$community->owner, $manager] as $actor) {
            $this->actingAs($actor)
                ->getJson("/api/v1/communities/{$community->id}/members")->assertOk();
            $this->actingAs($actor)
                ->getJson("/api/v1/communities/{$community->id}/members/{$member->id}")->assertOk();
            $this->actingAs($actor)
                ->getJson("/api/v1/communities/{$community->id}/stats")->assertOk();
            $this->actingAs($actor)
                ->getJson("/api/v1/communities/{$community->id}/invitations")->assertOk();
            $this->actingAs($actor)
                ->getJson("/api/v1/communities/{$community->id}/tiers")->assertOk();
            $this->actingAs($actor)
                ->getJson("/api/v1/communities/{$community->id}/join-requests")->assertOk();
            $this->actingAs($actor)
                ->getJson("/api/v1/communities/{$community->id}/leaderboard")->assertOk();

            $this->actingAs($actor)
                ->postJson("/api/v1/communities/{$community->id}/invitations", [
                    'email' => uniqid('paywall-check-').'@example.com',
                ])->assertCreated();

            $this->actingAs($actor)
                ->patchJson("/api/v1/communities/{$community->id}/members/{$member->id}", [
                    'tier_id' => $tier->id,
                ])->assertOk();

            $this->actingAs($actor)
                ->patchJson("/api/v1/communities/{$community->id}/members", [
                    'member_ids' => [$member->id],
                    'can_manage' => false,
                ])->assertOk();

            $this->actingAs($actor)
                ->postJson("/api/v1/invitations/{$invitation->id}/resend")->assertOk();
        }
    }

    public function test_the_public_join_page_is_not_paywalled_and_needs_no_account(): void
    {
        $community = Community::factory()->create(['slug' => 'paywall-free']);

        $this->get('/c/paywall-free')->assertOk();
    }

    public function test_an_attendee_can_accept_an_invitation_without_a_subscription(): void
    {
        $community = Community::factory()->create();
        $invitation = CommunityInvitation::factory()->forCommunity($community)->create();
        $invitee = Profile::factory()->attendee()->create();

        $this->assertFalse($invitee->hasActiveSubscription());

        $this->actingAs($invitee)
            ->postJson("/api/v1/invitations/accept/{$invitation->token}")
            ->assertOk();
    }
}
