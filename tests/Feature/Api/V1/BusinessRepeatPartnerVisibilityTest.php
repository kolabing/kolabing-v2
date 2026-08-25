<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\PartnerStatusTier;
use App\Models\BusinessPartnerStatus;
use App\Models\BusinessProfile;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class BusinessRepeatPartnerVisibilityTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_repeat_partner_count_is_exposed_on_the_public_business_profile_badge(): void
    {
        $profile = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $profile->id]);
        BusinessPartnerStatus::factory()->create([
            'profile_id' => $profile->id,
            'status' => PartnerStatusTier::TrustedPartner,
            'repeat_partner_count' => 2,
        ]);

        $response = $this->actingAs($profile)->getJson('/api/v1/auth/me');

        $response->assertOk()
            ->assertJsonPath('data.business_profile.partner_status.status', 'trusted_partner')
            ->assertJsonPath('data.business_profile.partner_status.repeat_partner_count', 2);
    }

    public function test_repeat_partner_count_defaults_to_zero_without_a_status_row(): void
    {
        $profile = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $profile->id]);

        $response = $this->actingAs($profile)->getJson('/api/v1/auth/me');

        $response->assertOk()
            ->assertJsonPath('data.business_profile.partner_status.repeat_partner_count', 0);
    }
}
