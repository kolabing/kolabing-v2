<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Enums\CollaborationStatus;
use App\Enums\PartnerStatusTier;
use App\Models\BusinessPartnerStatus;
use App\Models\Collaboration;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class RecalculatePartnerStatusesTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_recalculates_status_for_collaborations_that_bypassed_the_completion_flow(): void
    {
        $business = Profile::factory()->business()->create();

        // Simulates completed collaborations written directly to the
        // database (e.g. seeded test data) — recalculate() was never
        // triggered, so no BusinessPartnerStatus row exists yet.
        Collaboration::factory()
            ->forCreator($business)
            ->count(1)
            ->create(['status' => CollaborationStatus::Completed]);

        $this->assertDatabaseMissing('business_partner_statuses', [
            'profile_id' => $business->id,
        ]);

        $this->artisan('app:recalculate-partner-statuses')->assertSuccessful();

        $this->assertDatabaseHas('business_partner_statuses', [
            'profile_id' => $business->id,
            'status' => PartnerStatusTier::ActivePartner->value,
            'completed_kolabs_count' => 1,
        ]);
    }

    public function test_dry_run_reports_without_writing(): void
    {
        $business = Profile::factory()->business()->create();

        Collaboration::factory()
            ->forCreator($business)
            ->count(1)
            ->create(['status' => CollaborationStatus::Completed]);

        $this->artisan('app:recalculate-partner-statuses --dry-run')->assertSuccessful();

        $this->assertDatabaseMissing('business_partner_statuses', [
            'profile_id' => $business->id,
        ]);
    }

    public function test_profile_option_scopes_to_a_single_business(): void
    {
        $target = Profile::factory()->business()->create();
        $other = Profile::factory()->business()->create();

        Collaboration::factory()->forCreator($target)->count(1)->create([
            'status' => CollaborationStatus::Completed,
        ]);
        Collaboration::factory()->forCreator($other)->count(1)->create([
            'status' => CollaborationStatus::Completed,
        ]);

        $this->artisan("app:recalculate-partner-statuses --profile={$target->id}")->assertSuccessful();

        $this->assertDatabaseHas('business_partner_statuses', ['profile_id' => $target->id]);
        $this->assertDatabaseMissing('business_partner_statuses', ['profile_id' => $other->id]);
    }

    public function test_existing_stale_status_row_is_corrected(): void
    {
        $business = Profile::factory()->business()->create();

        BusinessPartnerStatus::factory()->create([
            'profile_id' => $business->id,
            'status' => PartnerStatusTier::NewPartner,
            'completed_kolabs_count' => 0,
        ]);

        Collaboration::factory()
            ->forCreator($business)
            ->count(1)
            ->create(['status' => CollaborationStatus::Completed]);

        $this->artisan('app:recalculate-partner-statuses')->assertSuccessful();

        $this->assertDatabaseHas('business_partner_statuses', [
            'profile_id' => $business->id,
            'status' => PartnerStatusTier::ActivePartner->value,
            'completed_kolabs_count' => 1,
        ]);
    }
}
