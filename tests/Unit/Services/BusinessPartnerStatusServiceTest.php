<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\PartnerStatusTier;
use App\Models\Collaboration;
use App\Models\CollaborationReview;
use App\Models\Profile;
use App\Services\BusinessPartnerStatusService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class BusinessPartnerStatusServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_new_business_with_no_history_is_new_partner(): void
    {
        $business = Profile::factory()->business()->create();

        $status = app(BusinessPartnerStatusService::class)->recalculate($business);

        $this->assertSame(PartnerStatusTier::NewPartner, $status);
    }

    public function test_business_becomes_active_partner_after_one_completed_kolab(): void
    {
        $business = Profile::factory()->business()->create();
        Collaboration::factory()->completed()->forCreator($business)->create();

        $status = app(BusinessPartnerStatusService::class)->recalculate($business);

        $this->assertSame(PartnerStatusTier::ActivePartner, $status);
    }

    public function test_business_becomes_trusted_partner_with_enough_completions_and_reviews(): void
    {
        $business = Profile::factory()->business()->create();

        for ($i = 0; $i < 3; $i++) {
            $collaboration = Collaboration::factory()->completed()->forCreator($business)->create();

            CollaborationReview::factory()->create([
                'collaboration_id' => $collaboration->id,
                'reviewer_profile_id' => $collaboration->applicant_profile_id,
                'reviewed_profile_id' => $business->id,
                'reviewer_role' => 'applicant',
                'rating' => 5,
            ]);
        }

        $status = app(BusinessPartnerStatusService::class)->recalculate($business);

        $this->assertSame(PartnerStatusTier::TrustedPartner, $status);
    }

    public function test_low_average_rating_prevents_trusted_partner_despite_enough_completions(): void
    {
        $business = Profile::factory()->business()->create();

        for ($i = 0; $i < 3; $i++) {
            $collaboration = Collaboration::factory()->completed()->forCreator($business)->create();

            CollaborationReview::factory()->create([
                'collaboration_id' => $collaboration->id,
                'reviewer_profile_id' => $collaboration->applicant_profile_id,
                'reviewed_profile_id' => $business->id,
                'reviewer_role' => 'applicant',
                'rating' => 2,
            ]);
        }

        $status = app(BusinessPartnerStatusService::class)->recalculate($business);

        $this->assertSame(PartnerStatusTier::ActivePartner, $status);
    }

    public function test_repeat_partner_counted_toward_community_favourite(): void
    {
        $business = Profile::factory()->business()->create();

        // Two communities, each collaborated with twice (two repeat partners),
        // plus enough additional distinct completions to clear the
        // community-favourite bar.
        foreach (Profile::factory()->community()->count(2)->create() as $repeatCommunity) {
            for ($i = 0; $i < 2; $i++) {
                $collaboration = Collaboration::factory()->completed()->forCreator($business)->forApplicant($repeatCommunity)->create();
                CollaborationReview::factory()->create([
                    'collaboration_id' => $collaboration->id,
                    'reviewer_profile_id' => $repeatCommunity->id,
                    'reviewed_profile_id' => $business->id,
                    'reviewer_role' => 'applicant',
                    'rating' => 5,
                ]);
            }
        }

        for ($i = 0; $i < 4; $i++) {
            $collaboration = Collaboration::factory()->completed()->forCreator($business)->create();
            CollaborationReview::factory()->create([
                'collaboration_id' => $collaboration->id,
                'reviewer_profile_id' => $collaboration->applicant_profile_id,
                'reviewed_profile_id' => $business->id,
                'reviewer_role' => 'applicant',
                'rating' => 5,
            ]);
        }

        $status = app(BusinessPartnerStatusService::class)->recalculate($business);

        $this->assertSame(PartnerStatusTier::CommunityFavourite, $status);

        $record = $business->fresh()->businessPartnerStatus;
        $this->assertSame(2, $record->repeat_partner_count);
    }

    public function test_status_for_falls_back_to_new_partner_without_recomputing(): void
    {
        $business = Profile::factory()->business()->create();

        $status = app(BusinessPartnerStatusService::class)->statusFor($business);

        $this->assertSame(PartnerStatusTier::NewPartner, $status);
    }
}
