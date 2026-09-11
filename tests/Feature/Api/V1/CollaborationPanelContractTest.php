<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\BusinessProfile;
use App\Models\BusinessSubscription;
use App\Models\Collaboration;
use App\Models\CommunityProfile;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * The exact fields the web panel's collaboration page binds to (BE-NF-45).
 *
 * `GET /collaborations/{id}` is a wide payload and the panel reads a narrow slice of
 * it. This pins that slice by name, because the failure mode is silent: a renamed or
 * differently-nested field does not error, it renders an empty string. The first
 * version of the page read `creator_profile.name`, which does not exist —
 * ProfileSummaryResource calls it `display_name` — so every partner would have shown
 * as the fallback word "Partner" with no link.
 */
class CollaborationPanelContractTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** @return array{0: Profile, 1: Profile} */
    private function pair(): array
    {
        $business = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $business->id, 'name' => 'Honest Greens']);

        $community = Profile::factory()->community()->create();
        CommunityProfile::factory()->create(['profile_id' => $community->id, 'name' => 'Barcelona Runners']);

        return [$business->fresh(), $community->fresh()];
    }

    public function test_the_page_can_name_and_link_the_other_side(): void
    {
        [$business, $community] = $this->pair();

        $collaboration = Collaboration::factory()
            ->scheduled()->forCreator($business)->forApplicant($community)->create();

        $this->actingAs($business)
            ->getJson("/api/v1/collaborations/{$collaboration->id}")
            ->assertOk()
            // The panel resolves the partner by comparing these ids, exactly as
            // My Kolabs does — so both sides must be present and identifiable.
            ->assertJsonPath('data.creator_profile.id', $business->id)
            ->assertJsonPath('data.applicant_profile.id', $community->id)
            ->assertJsonPath('data.applicant_profile.display_name', 'Barcelona Runners')
            ->assertJsonPath('data.creator_profile.display_name', 'Honest Greens')
            ->assertJsonStructure(['data' => ['applicant_profile' => ['avatar_url', 'user_type']]])
            ->assertJsonPath('data.my_role', 'creator');
    }

    public function test_the_page_can_read_the_stage_and_the_available_actions(): void
    {
        [$business, $community] = $this->pair();

        $collaboration = Collaboration::factory()
            ->scheduled()->forCreator($business)->forApplicant($community)->create();

        $this->actingAs($community)
            ->getJson("/api/v1/collaborations/{$collaboration->id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'scheduled')
            ->assertJsonStructure(['data' => [
                'scheduled_date', 'kolab_id', 'event_id',
                'actions' => ['can_activate', 'can_complete', 'can_cancel'],
            ]])
            ->assertJsonPath('data.my_role', 'applicant');
    }

    /**
     * The completion question is the gate, so the page has to be able to show both
     * answers — its own and the partner's — or the 422 that follows is inexplicable.
     */
    public function test_both_completion_answers_are_readable_from_either_side(): void
    {
        [$business, $community] = $this->pair();

        $collaboration = Collaboration::factory()
            ->active()->forCreator($business)->forApplicant($community)->create();

        $this->actingAs($business)
            ->getJson("/api/v1/collaborations/{$collaboration->id}")
            ->assertOk()
            ->assertJsonPath('data.own_completion', null)
            ->assertJsonPath('data.partner_completion_status', null)
            ->assertJsonPath('data.viewer_must_confirm_completion', true);

        $this->actingAs($business)
            ->postJson("/api/v1/collaborations/{$collaboration->id}/completion", [
                'status' => 'yes',
                'note' => 'Went well.',
            ])
            ->assertCreated();

        $this->actingAs($business)
            ->getJson("/api/v1/collaborations/{$collaboration->id}")
            ->assertOk()
            ->assertJsonPath('data.own_completion.status', 'yes')
            ->assertJsonPath('data.viewer_must_confirm_completion', false);

        // The other side sees it as the partner's answer, not as its own.
        $this->actingAs($community)
            ->getJson("/api/v1/collaborations/{$collaboration->id}")
            ->assertOk()
            ->assertJsonPath('data.own_completion', null)
            ->assertJsonPath('data.partner_completion_status', 'yes')
            ->assertJsonPath('data.viewer_must_confirm_completion', true);
    }

    /** Completing before the answers are in must fail with a code, not a bare message. */
    public function test_the_completion_gate_refuses_with_a_code_the_page_can_translate(): void
    {
        [$business, $community] = $this->pair();

        $collaboration = Collaboration::factory()
            ->active()->forCreator($business)->forApplicant($community)->create();

        $this->actingAs($business)
            ->postJson("/api/v1/collaborations/{$collaboration->id}/complete")
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'awaiting_own_completion_confirmation');

        $this->actingAs($business)
            ->postJson("/api/v1/collaborations/{$collaboration->id}/completion", ['status' => 'yes'])
            ->assertCreated();

        $this->actingAs($business)
            ->postJson("/api/v1/collaborations/{$collaboration->id}/complete")
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'awaiting_partner_completion_confirmation');
    }

    /** `has_reviewed` is what hides the review CTA once it has been used. */
    public function test_has_reviewed_flips_after_the_review_lands(): void
    {
        [$business, $community] = $this->pair();

        $collaboration = Collaboration::factory()
            ->completed()->forCreator($business)->forApplicant($community)->create();

        $this->actingAs($business)
            ->getJson("/api/v1/collaborations/{$collaboration->id}")
            ->assertOk()
            ->assertJsonPath('data.has_reviewed', false);

        $this->actingAs($business)
            ->postJson("/api/v1/collaborations/{$collaboration->id}/review", [
                'communication_rating' => 5,
                'reliability_rating' => 4,
                'fit_rating' => 5,
                'value_rating' => 4,
                'repeat_rating' => 5,
                'public_comment' => 'Great crowd.',
                'would_collaborate_again' => true,
            ])
            ->assertCreated();

        $this->actingAs($business)
            ->getJson("/api/v1/collaborations/{$collaboration->id}")
            ->assertOk()
            ->assertJsonPath('data.has_reviewed', true);
    }

    // ── ROLES §2.8, from the panel's side ────────────────────────────────

    public function test_a_lapsed_business_is_flagged_on_an_ongoing_collaboration(): void
    {
        [$business, $community] = $this->pair();

        $collaboration = Collaboration::factory()
            ->active()->forCreator($business)->forApplicant($community)->create();

        $this->actingAs($business)
            ->getJson("/api/v1/collaborations/{$collaboration->id}")
            ->assertOk()
            ->assertJsonPath('data.viewer_must_resubscribe', true);
    }

    /** The community counterparty is NEVER re-gated — that is the whole point of §2.8. */
    public function test_the_community_counterparty_is_never_flagged(): void
    {
        [$business, $community] = $this->pair();

        $collaboration = Collaboration::factory()
            ->active()->forCreator($business)->forApplicant($community)->create();

        $this->actingAs($community)
            ->getJson("/api/v1/collaborations/{$collaboration->id}")
            ->assertOk()
            ->assertJsonPath('data.viewer_must_resubscribe', false);
    }

    /** A subscribed business sees no gate at all. */
    public function test_a_subscribed_business_is_not_flagged(): void
    {
        [$business, $community] = $this->pair();
        BusinessSubscription::factory()->active()->create(['profile_id' => $business->id]);

        $collaboration = Collaboration::factory()
            ->active()->forCreator($business)->forApplicant($community)->create();

        $this->actingAs($business->fresh())
            ->getJson("/api/v1/collaborations/{$collaboration->id}")
            ->assertOk()
            ->assertJsonPath('data.viewer_must_resubscribe', false);
    }

    /**
     * A completed collaboration is not "ongoing", so the review and the impact
     * feedback stay reachable after a lapse — the exemption §4 spells out.
     */
    public function test_a_finished_collaboration_is_not_re_gated_so_the_review_stays_reachable(): void
    {
        [$business, $community] = $this->pair();

        $collaboration = Collaboration::factory()
            ->completed()->forCreator($business)->forApplicant($community)->create();

        $this->actingAs($business)
            ->getJson("/api/v1/collaborations/{$collaboration->id}")
            ->assertOk()
            ->assertJsonPath('data.viewer_must_resubscribe', false);
    }
}
