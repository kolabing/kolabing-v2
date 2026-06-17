<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\BusinessProfile;
use App\Models\CommunityProfile;
use App\Models\Kolab;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ApplicationDetailTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_application_detail_includes_unlocked_negotiation_triggers_for_kolab_backed_opportunities(): void
    {
        $creator = Profile::factory()->business()->create();
        BusinessProfile::factory()->create([
            'profile_id' => $creator->id,
            'name' => 'Casa Sol',
        ]);

        $applicant = Profile::factory()->community()->create([
            'phone_number' => '+34600000001',
        ]);
        CommunityProfile::factory()->create([
            'profile_id' => $applicant->id,
            'name' => 'Barcelona Run Club',
            'community_type' => 'run_club',
        ]);

        $kolab = Kolab::factory()
            ->published()
            ->venuePromotion()
            ->forCreator($creator)
            ->create([
                'title' => 'Sunset rooftop collab',
                'description' => 'Host your creator event on our rooftop',
                'offer_headline' => 'Post-run rooftop takeovers',
                'base_offer' => 'Reserved rooftop space plus drinks for community meetups.',
                'negotiation_triggers' => [
                    [
                        'condition' => 'Recurring monthly events',
                        'additional_offer' => 'Free pastry platter from the third event onward.',
                    ],
                ],
            ]);

        $applicationResponse = $this->actingAs($applicant)
            ->postJson("/api/v1/opportunities/{$kolab->id}/applications", [
                'message' => 'sounds cool',
                'availability' => 'Weekends work best for us.',
            ]);

        $applicationId = $applicationResponse->json('data.id');

        $this->actingAs($applicant)
            ->getJson("/api/v1/applications/{$applicationId}")
            ->assertOk()
            ->assertJsonPath('data.kolab.id', $kolab->id)
            ->assertJsonPath('data.kolab.offer_headline', 'Post-run rooftop takeovers')
            ->assertJsonPath('data.kolab.base_offer', 'Reserved rooftop space plus drinks for community meetups.')
            ->assertJsonPath('data.kolab.negotiation_triggers.0.condition', 'Recurring monthly events')
            ->assertJsonPath('data.kolab.negotiation_triggers.0.additional_offer', 'Free pastry platter from the third event onward.')
            ->assertJsonPath('data.collab_opportunity.id', $kolab->id)
            ->assertJsonPath('data.collab_opportunity.offer_headline', 'Post-run rooftop takeovers')
            ->assertJsonPath('data.collab_opportunity.base_offer', 'Reserved rooftop space plus drinks for community meetups.')
            ->assertJsonPath('data.collab_opportunity.negotiation_triggers.0.condition', 'Recurring monthly events')
            ->assertJsonPath('data.collab_opportunity.negotiation_triggers.0.additional_offer', 'Free pastry platter from the third event onward.')
            ->assertJsonPath('data.opportunity.id', $kolab->id)
            ->assertJsonPath('data.opportunity.negotiation_triggers.0.condition', 'Recurring monthly events');
    }
}
