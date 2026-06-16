<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Application;
use App\Models\BusinessProfile;
use App\Models\BusinessSubscription;
use App\Models\CollabOpportunity;
use App\Models\Collaboration;
use App\Models\CommunityProfile;
use App\Models\Kolab;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Phase 1 (kolab source-of-truth) dual-write coverage. Asserts that an
 * apply -> accept flow against a kolab-originated opportunity populates kolab_id
 * on BOTH applications and collaborations, and that kolab_id == collab_opportunity_id
 * (because the bridge persists collab_opportunities with id = kolab.id).
 */
class KolabSourceOfTruthDualWriteTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_apply_then_accept_sets_matching_kolab_id_on_both_tables(): void
    {
        $business = Profile::factory()->business()->create();
        BusinessProfile::factory()->create([
            'profile_id' => $business->id,
            'name' => 'Business Creator',
        ]);
        BusinessSubscription::factory()->active()->create([
            'profile_id' => $business->id,
        ]);

        $community = Profile::factory()->community()->create();
        CommunityProfile::factory()->create([
            'profile_id' => $community->id,
            'name' => 'Community Applicant',
        ]);

        // The real kolab is the source of truth. The apply flow re-materializes the
        // collab_opportunity from this kolab (bridge persistFromKolab=true), so the
        // accept availability window is read from these dates.
        $kolab = Kolab::factory()->create([
            'creator_profile_id' => $business->id,
            'status' => \App\Enums\KolabStatus::Published,
            'published_at' => now(),
            'availability_mode' => 'flexible',
            'availability_start' => now()->addDays(7)->toDateString(),
            'availability_end' => now()->addDays(30)->toDateString(),
        ]);

        // The bridge materializes a collab_opportunity with id = kolab.id.
        $opportunity = CollabOpportunity::factory()
            ->published()
            ->forCreator($business)
            ->create([
                'id' => $kolab->id,
                'availability_mode' => 'flexible',
                'availability_start' => now()->addDays(7)->toDateString(),
                'availability_end' => now()->addDays(30)->toDateString(),
            ]);

        // Apply (community applies to the business kolab).
        $applyResponse = $this->actingAs($community)
            ->postJson("/api/v1/opportunities/{$opportunity->id}/applications", [
                'message' => 'We would love to collaborate with your venue this month.',
                'availability' => 'Weekday evenings work best for our running club members.',
            ]);

        $applyResponse->assertStatus(201)->assertJsonPath('success', true);

        $application = Application::query()->firstOrFail();
        $this->assertSame($kolab->id, $application->kolab_id);
        $this->assertSame($application->collab_opportunity_id, $application->kolab_id);

        // Accept -> forms a collaboration. Pick a date inside the publisher window.
        $scheduledDate = now()->addDays(10)->toDateString();

        $acceptResponse = $this->actingAs($business)
            ->postJson("/api/v1/applications/{$application->id}/accept", [
                'scheduled_date' => $scheduledDate,
            ]);

        $acceptResponse->assertOk()->assertJsonPath('success', true);

        $collaboration = Collaboration::query()->firstOrFail();
        $this->assertSame($kolab->id, $collaboration->kolab_id);
        $this->assertSame($collaboration->collab_opportunity_id, $collaboration->kolab_id);

        // Relations resolve.
        $this->assertTrue($kolab->applications()->whereKey($application->id)->exists());
        $this->assertTrue($kolab->collaborations()->whereKey($collaboration->id)->exists());
        $this->assertSame($kolab->id, $application->kolab->id);
        $this->assertSame($kolab->id, $collaboration->kolab->id);
    }
}
