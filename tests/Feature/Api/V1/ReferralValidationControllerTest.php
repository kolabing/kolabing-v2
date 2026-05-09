<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\BusinessProfile;
use App\Models\BusinessSubscription;
use App\Models\Profile;
use App\Models\ReferralCode;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReferralValidationControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_validate_requires_authentication(): void
    {
        $this->postJson('/api/v1/referrals/validate', [
            'referral_code' => 'KOLAB-TEST',
        ])->assertStatus(401);
    }

    public function test_validate_accepts_trimmed_and_uppercased_valid_code(): void
    {
        $profile = $this->createBusinessProfile();
        $owner = Profile::factory()->community()->create();
        ReferralCode::factory()->forProfile($owner)->create([
            'code' => 'KOLAB-TEST',
        ]);

        $response = $this->actingAs($profile)->postJson('/api/v1/referrals/validate', [
            'referral_code' => '  kolab-test  ',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.referral_code', 'KOLAB-TEST');
    }

    public function test_validate_returns_422_for_invalid_code(): void
    {
        $profile = $this->createBusinessProfile();

        $response = $this->actingAs($profile)->postJson('/api/v1/referrals/validate', [
            'referral_code' => 'KOLAB-MISS',
        ]);

        $this->assertInvalidReferralResponse($response);
    }

    public function test_validate_returns_422_for_expired_code(): void
    {
        $profile = $this->createBusinessProfile();
        $owner = Profile::factory()->community()->create();
        ReferralCode::factory()->forProfile($owner)->create([
            'code' => 'KOLAB-OLD1',
            'expires_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($profile)->postJson('/api/v1/referrals/validate', [
            'referral_code' => 'KOLAB-OLD1',
        ]);

        $this->assertInvalidReferralResponse($response);
    }

    public function test_validate_returns_422_for_self_referral(): void
    {
        $profile = $this->createBusinessProfile();
        ReferralCode::factory()->forProfile($profile)->create([
            'code' => 'KOLAB-SELF',
        ]);

        $response = $this->actingAs($profile)->postJson('/api/v1/referrals/validate', [
            'referral_code' => 'KOLAB-SELF',
        ]);

        $this->assertInvalidReferralResponse($response);
    }

    public function test_validate_returns_422_for_already_used_code(): void
    {
        $profile = $this->createBusinessProfile();
        $owner = Profile::factory()->community()->create();
        $referralCode = ReferralCode::factory()->forProfile($owner)->create([
            'code' => 'KOLAB-USED',
        ]);
        $subscription = BusinessSubscription::factory()->create([
            'profile_id' => $profile->id,
        ]);

        DB::table('referral_redemptions')->insert([
            'id' => (string) Str::uuid(),
            'referral_code_id' => $referralCode->id,
            'referred_profile_id' => $profile->id,
            'business_subscription_id' => $subscription->id,
            'rewarded_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($profile)->postJson('/api/v1/referrals/validate', [
            'referral_code' => 'KOLAB-USED',
        ]);

        $this->assertInvalidReferralResponse($response);
    }

    private function createBusinessProfile(): Profile
    {
        $profile = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $profile->id]);

        return $profile;
    }

    private function assertInvalidReferralResponse(\Illuminate\Testing\TestResponse $response): void
    {
        $response->assertStatus(422)
            ->assertExactJson([
                'message' => 'The given data was invalid.',
                'errors' => [
                    'referral_code' => ['The selected referral code is invalid.'],
                ],
            ]);
    }
}
