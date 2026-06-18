<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\ApplicationStatus;
use App\Enums\CollaborationStatus;
use App\Enums\KolabStatus;
use App\Enums\OfferStatus;
use App\Enums\UserType;
use App\Models\Application;
use App\Models\BusinessProfile;
use App\Models\BusinessSubscription;
use App\Models\Collaboration;
use App\Models\CommunityProfile;
use App\Models\Kolab;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Phase 2 (kolab = source of truth): reads re-pointed to kolabs.
 *
 *  - GET /me/opportunities returns the viewer's KOLABS (incl. freshly created
 *    kolabs with no compatibility opportunity), in the OpportunityResource shape.
 *  - GET /me/dashboard counts opportunities off kolabs and applications off
 *    kolab_id, and never fatals when a collaboration's collabOpportunity is absent.
 *  - The inverse-bridge backfill creates a kolab for every legacy
 *    collab_opportunity and re-points kolab_id so ZERO rows are left NULL.
 */
class KolabSourceOfTruthPhase2Test extends TestCase
{
    use LazilyRefreshDatabase;

    private function businessWithSubscription(string $name = 'Business Creator'): Profile
    {
        $business = Profile::factory()->business()->create();
        BusinessProfile::factory()->create([
            'profile_id' => $business->id,
            'name' => $name,
        ]);
        BusinessSubscription::factory()->active()->create([
            'profile_id' => $business->id,
        ]);

        return $business;
    }

    private function community(string $name = 'Community Applicant'): Profile
    {
        $community = Profile::factory()->community()->create();
        CommunityProfile::factory()->create([
            'profile_id' => $community->id,
            'name' => $name,
        ]);

        return $community;
    }

    public function test_me_opportunities_returns_freshly_created_kolabs_without_compat_row(): void
    {
        $business = $this->businessWithSubscription();

        // A kolab with NO backing collab_opportunity row — the exact case that was
        // invisible to /me/opportunities before Phase 2.
        $kolab = Kolab::factory()->create([
            'creator_profile_id' => $business->id,
            'status' => KolabStatus::Published,
            'published_at' => now(),
            'title' => 'Fresh Kolab Offer',
        ]);

        $response = $this->actingAs($business)->getJson('/api/v1/kolabs/me');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.data.0.id', $kolab->id)
            ->assertJsonPath('data.data.0.title', 'Fresh Kolab Offer')
            ->assertJsonPath('data.data.0.status', OfferStatus::Published->value);

        // The OpportunityResource field set is preserved (incl. offer_photo).
        $response->assertJsonStructure([
            'data' => ['data' => [['id', 'title', 'status', 'offer_photo', 'is_own', 'applications_count']]],
        ]);
    }

    public function test_me_opportunities_status_filter_maps_to_kolab_status(): void
    {
        $business = $this->businessWithSubscription();

        Kolab::factory()->create([
            'creator_profile_id' => $business->id,
            'status' => KolabStatus::Published,
            'published_at' => now(),
        ]);
        Kolab::factory()->create([
            'creator_profile_id' => $business->id,
            'status' => KolabStatus::Draft,
        ]);

        $response = $this->actingAs($business)->getJson('/api/v1/kolabs/me?status=published');

        $response->assertOk()->assertJsonPath('meta.total', 1);
        $this->assertSame(
            OfferStatus::Published->value,
            $response->json('data.data.0.status'),
        );
    }

    public function test_dashboard_counts_opportunities_from_kolabs(): void
    {
        $business = $this->businessWithSubscription();

        Kolab::factory()->create(['creator_profile_id' => $business->id, 'status' => KolabStatus::Published, 'published_at' => now()]);
        Kolab::factory()->create(['creator_profile_id' => $business->id, 'status' => KolabStatus::Published, 'published_at' => now()]);
        Kolab::factory()->create(['creator_profile_id' => $business->id, 'status' => KolabStatus::Draft]);
        Kolab::factory()->create(['creator_profile_id' => $business->id, 'status' => KolabStatus::Closed]);

        $response = $this->actingAs($business)->getJson('/api/v1/me/dashboard');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.opportunities.total', 4)
            ->assertJsonPath('data.opportunities.published', 2)
            ->assertJsonPath('data.opportunities.draft', 1)
            ->assertJsonPath('data.opportunities.closed', 1);
    }

    public function test_dashboard_received_applications_count_via_kolab_fk(): void
    {
        $business = $this->businessWithSubscription();
        $communityA = $this->community('Community A');
        $communityB = $this->community('Community B');

        $kolab = Kolab::factory()->create([
            'creator_profile_id' => $business->id,
            'status' => KolabStatus::Published,
            'published_at' => now(),
        ]);

        // The dashboard COUNTS received applications via kolab_id.
        foreach ([$communityA, $communityB] as $applicant) {
            Application::factory()->create([
                'kolab_id' => $kolab->id,
                'applicant_profile_id' => $applicant->id,
                'applicant_profile_type' => UserType::Community,
                'status' => ApplicationStatus::Pending,
            ]);
        }

        $response = $this->actingAs($business)->getJson('/api/v1/me/dashboard');

        $response->assertOk()
            ->assertJsonPath('data.applications_received.total', 2)
            ->assertJsonPath('data.applications_received.pending', 2);
    }

    public function test_dashboard_upcoming_collaboration_opportunity_sourced_from_kolab(): void
    {
        // The dashboard sources the embedded opportunity from the KOLAB.
        // Also exercises the controller null-safety: categories is always an array.
        $business = $this->businessWithSubscription();
        $community = $this->community();

        $kolab = Kolab::factory()->create([
            'creator_profile_id' => $business->id,
            'status' => KolabStatus::Published,
            'published_at' => now(),
            'title' => 'Kolab Title (source of truth)',
        ]);

        $application = Application::factory()->create([
            'kolab_id' => $kolab->id,
            'applicant_profile_id' => $community->id,
            'applicant_profile_type' => UserType::Community,
            'status' => ApplicationStatus::Accepted,
        ]);

        Collaboration::factory()->create([
            'application_id' => $application->id,
            'kolab_id' => $kolab->id,
            'creator_profile_id' => $business->id,
            'applicant_profile_id' => $community->id,
            'status' => CollaborationStatus::Scheduled,
            'scheduled_date' => now()->addDays(5)->toDateString(),
        ]);

        $response = $this->actingAs($business)->getJson('/api/v1/me/dashboard');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.upcoming_collaborations.0.opportunity.id', $kolab->id)
            ->assertJsonPath('data.upcoming_collaborations.0.opportunity.title', 'Kolab Title (source of truth)');

        // Categories key is always present and an array (never null), so the app
        // parser never hits a missing/typed-wrong field.
        $this->assertIsArray($response->json('data.upcoming_collaborations.0.opportunity.categories'));
    }
}
