<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\BusinessProfile;
use App\Models\Collaboration;
use App\Models\CommunityProfile;
use App\Models\NotificationReminder;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class BusinessPartnerStatusTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function createBusinessProfile(): Profile
    {
        $profile = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $profile->id]);

        return $profile;
    }

    private function createCommunityProfile(): Profile
    {
        $profile = Profile::factory()->community()->create();
        CommunityProfile::factory()->create(['profile_id' => $profile->id]);

        return $profile;
    }

    /**
     * /complete now gates on both parties having submitted a 'yes'
     * completion confirmation first (PR 1, 2026-06-26).
     */
    private function confirmCompletionForBothParties(Collaboration $collaboration, Profile $creator, Profile $applicant): void
    {
        $this->actingAs($creator)
            ->postJson("/api/v1/collaborations/{$collaboration->id}/completion", ['status' => 'yes'])
            ->assertCreated();

        $this->actingAs($applicant)
            ->postJson("/api/v1/collaborations/{$collaboration->id}/completion", ['status' => 'yes'])
            ->assertCreated();
    }

    public function test_completing_first_kolab_upgrades_business_to_active_partner_and_notifies(): void
    {
        $creator = $this->createBusinessProfile();
        $applicant = $this->createCommunityProfile();

        $collaboration = Collaboration::factory()
            ->forCreator($creator)
            ->forApplicant($applicant)
            ->active()
            ->create();

        $this->confirmCompletionForBothParties($collaboration, $creator, $applicant);

        $this->actingAs($creator)
            ->postJson("/api/v1/collaborations/{$collaboration->id}/complete")
            ->assertOk();

        $this->assertDatabaseHas('business_partner_statuses', [
            'profile_id' => $creator->id,
            'status' => 'active_partner',
            'completed_kolabs_count' => 1,
        ]);

        $this->assertDatabaseHas('notifications', [
            'profile_id' => $creator->id,
            'type' => 'partner_status_upgraded',
        ]);
    }

    public function test_completing_kolab_does_not_create_a_partner_status_for_the_community_side(): void
    {
        $creator = $this->createBusinessProfile();
        $applicant = $this->createCommunityProfile();

        $collaboration = Collaboration::factory()
            ->forCreator($creator)
            ->forApplicant($applicant)
            ->active()
            ->create();

        $this->confirmCompletionForBothParties($collaboration, $creator, $applicant);

        $this->actingAs($creator)
            ->postJson("/api/v1/collaborations/{$collaboration->id}/complete")
            ->assertOk();

        $this->assertDatabaseMissing('business_partner_statuses', [
            'profile_id' => $applicant->id,
        ]);
    }

    public function test_leaving_a_review_recalculates_the_reviewed_businesss_status(): void
    {
        $creator = $this->createBusinessProfile();
        $applicant = $this->createCommunityProfile();

        $collaboration = Collaboration::factory()
            ->forCreator($creator)
            ->forApplicant($applicant)
            ->completed()
            ->create();

        $this->actingAs($applicant)
            ->postJson("/api/v1/collaborations/{$collaboration->id}/review", [
                'rating' => 5,
            ])
            ->assertCreated();

        $this->assertDatabaseHas('business_partner_statuses', [
            'profile_id' => $creator->id,
            'review_count' => 1,
        ]);
    }

    public function test_review_reminder_is_scheduled_after_completion_and_cancelled_after_review(): void
    {
        $creator = $this->createBusinessProfile();
        $applicant = $this->createCommunityProfile();

        $collaboration = Collaboration::factory()
            ->forCreator($creator)
            ->forApplicant($applicant)
            ->active()
            ->create();

        $this->confirmCompletionForBothParties($collaboration, $creator, $applicant);

        $this->actingAs($creator)
            ->postJson("/api/v1/collaborations/{$collaboration->id}/complete")
            ->assertOk();

        $this->assertDatabaseHas('notification_reminders', [
            'profile_id' => $creator->id,
            'type' => 'review_reminder',
            'entity_id' => $collaboration->id,
        ]);

        $this->actingAs($creator)
            ->postJson("/api/v1/collaborations/{$collaboration->id}/review", [
                'rating' => 5,
            ])
            ->assertCreated();

        $reminder = NotificationReminder::query()
            ->where('profile_id', $creator->id)
            ->where('type', 'review_reminder')
            ->where('entity_id', $collaboration->id)
            ->first();

        $this->assertNotNull($reminder);
        $this->assertNotNull($reminder->cancelled_at);
    }
}
