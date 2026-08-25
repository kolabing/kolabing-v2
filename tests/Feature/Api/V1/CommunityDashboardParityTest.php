<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\KolabStatus;
use App\Models\Application;
use App\Models\Collaboration;
use App\Models\CommunityProfile;
use App\Models\Kolab;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * `GET /me/dashboard` for a community (BE-NF-46 / BE-FX-29).
 *
 * The community dashboard returned three keys against the business dashboard's
 * seven, and the asymmetry was an omission rather than a decision. The sharpest
 * consequence was not cosmetic: the Flutter client has parsed
 * `applications_received` on the community dashboard since it was written and
 * silently defaulted it to all-zeros, because the backend never sent it — so a
 * community with businesses waiting on an answer was shown "0".
 */
class CommunityDashboardParityTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** A community whose profile passes the four-field floor. */
    private function community(): Profile
    {
        $profile = Profile::factory()->community()->create();
        CommunityProfile::factory()->create([
            'profile_id' => $profile->id,
            'name' => 'Barcelona Runners',
            'about' => 'We run every Tuesday and Thursday, rain or shine.',
            'community_type' => 'run_club',
        ]);

        return $profile->fresh();
    }

    private function dashboard(Profile $profile): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($profile)->getJson('/api/v1/me/dashboard')->assertOk();
    }

    // ── BE-FX-29: the field mobile already parses ────────────────────────

    public function test_a_community_that_posted_a_kolab_sees_the_applications_it_received(): void
    {
        $community = $this->community();
        $business = Profile::factory()->business()->create();

        $kolab = Kolab::factory()->published()->create(['creator_profile_id' => $community->id]);
        Application::factory()->forKolab($kolab)->forApplicant($business)->pending()->create();

        $this->dashboard($community)
            ->assertJsonPath('data.applications_received.total', 1)
            ->assertJsonPath('data.applications_received.pending', 1);
    }

    /** It is scoped to the caller's own Kolabs, not to every Kolab in the table. */
    public function test_another_communitys_inbound_applications_are_not_counted(): void
    {
        $mine = $this->community();
        $theirs = $this->community();
        $business = Profile::factory()->business()->create();

        $kolab = Kolab::factory()->published()->create(['creator_profile_id' => $theirs->id]);
        Application::factory()->forKolab($kolab)->forApplicant($business)->pending()->create();

        $this->dashboard($mine)->assertJsonPath('data.applications_received.total', 0);
    }

    public function test_a_community_sees_the_count_of_its_own_kolabs(): void
    {
        $community = $this->community();
        Kolab::factory()->published()->create(['creator_profile_id' => $community->id]);
        // No `draft()` state on the factory; the column default is what a fresh
        // Kolab carries anyway.
        Kolab::factory()->create([
            'creator_profile_id' => $community->id,
            'status' => KolabStatus::Draft,
        ]);

        $this->dashboard($community)
            ->assertJsonPath('data.opportunities.published', 1)
            ->assertJsonPath('data.opportunities.draft', 1)
            ->assertJsonPath('data.opportunities.total', 2);
    }

    /** The keys the existing clients already read must not move. */
    public function test_the_original_three_keys_are_untouched(): void
    {
        $this->dashboard($this->community())
            ->assertJsonStructure(['data' => [
                'applications_sent' => ['total', 'pending', 'accepted', 'declined', 'withdrawn'],
                'collaborations' => ['total', 'active', 'upcoming', 'completed'],
                'upcoming_collaborations',
            ]]);
    }

    // ── next_action, the community chain ─────────────────────────────────

    public function test_a_thin_profile_is_the_first_thing_asked_for(): void
    {
        $profile = Profile::factory()->community()->create();
        CommunityProfile::factory()->create([
            'profile_id' => $profile->id,
            'name' => 'Barcelona Runners',
            'about' => null,
            'community_type' => 'run_club',
        ]);

        $this->dashboard($profile->fresh())
            ->assertJsonPath('data.next_action.key', 'complete_profile');
    }

    /**
     * A community's first move is to APPLY, not to publish — browsing and applying
     * are free (ROLES §3.5), so pushing "create a Kolab" first would push the rarer
     * path.
     */
    public function test_a_complete_but_idle_community_is_pointed_at_applying(): void
    {
        $this->dashboard($this->community())
            ->assertJsonPath('data.next_action.key', 'apply_to_first');
    }

    /** Someone waiting on an answer outranks anything this community might go and do. */
    public function test_a_waiting_application_outranks_the_apply_prompt(): void
    {
        $community = $this->community();
        $business = Profile::factory()->business()->create();

        $kolab = Kolab::factory()->published()->create(['creator_profile_id' => $community->id]);
        Application::factory()->forKolab($kolab)->forApplicant($business)->pending()->create();

        $this->dashboard($community)
            ->assertJsonPath('data.next_action.key', 'review_pending_applications')
            ->assertJsonPath('data.next_action.title', 'Review 1 pending application');
    }

    public function test_more_than_one_waiting_application_is_counted_in_the_title(): void
    {
        $community = $this->community();

        $kolab = Kolab::factory()->published()->create(['creator_profile_id' => $community->id]);
        Application::factory()->forKolab($kolab)
            ->forApplicant(Profile::factory()->business()->create())->pending()->create();
        Application::factory()->forKolab($kolab)
            ->forApplicant(Profile::factory()->business()->create())->pending()->create();

        $this->dashboard($community)
            ->assertJsonPath('data.next_action.title', 'Review 2 pending applications');
    }

    public function test_an_unreviewed_finished_kolab_asks_for_the_review(): void
    {
        $community = $this->community();
        $business = Profile::factory()->business()->create();

        $kolab = Kolab::factory()->published()->create(['creator_profile_id' => $business->id]);
        $application = Application::factory()->forKolab($kolab)->forApplicant($community)->accepted()->create();
        Collaboration::factory()
            ->completed()
            ->forKolab($kolab)
            ->forApplication($application)
            ->forCreator($business)
            ->forApplicant($community)
            ->create();

        $this->dashboard($community)
            ->assertJsonPath('data.next_action.key', 'leave_review');
    }

    /** With nothing outstanding the card disappears rather than inventing busywork. */
    public function test_nothing_outstanding_returns_no_next_action(): void
    {
        $community = $this->community();
        $business = Profile::factory()->business()->create();

        $kolab = Kolab::factory()->published()->create(['creator_profile_id' => $business->id]);
        Application::factory()->forKolab($kolab)->forApplicant($community)->pending()->create();

        $this->dashboard($community)->assertJsonPath('data.next_action', null);
    }

    // ── No role rules moved ──────────────────────────────────────────────

    /** Communities are free, always (ROLES §3.5) — nothing here may gate on a plan. */
    public function test_a_community_needs_no_subscription_for_any_of_this(): void
    {
        $community = $this->community();

        $this->assertFalse($community->hasActiveSubscription());

        $this->dashboard($community)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['opportunities', 'applications_received', 'next_action']]);
    }

    /** The business dashboard keeps its own chain — this change must not leak into it. */
    public function test_the_business_chain_is_untouched(): void
    {
        $business = Profile::factory()->business()->create();

        $this->actingAs($business)
            ->getJson('/api/v1/me/dashboard')
            ->assertOk()
            ->assertJsonStructure(['data' => ['partner_status', 'monthly_goal', 'next_action']])
            ->assertJsonPath('data.next_action.key', 'complete_profile');
    }
}
