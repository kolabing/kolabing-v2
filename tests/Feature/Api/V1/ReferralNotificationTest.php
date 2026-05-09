<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\BusinessProfile;
use App\Models\City;
use App\Models\CommunityProfile;
use App\Models\Notification;
use App\Models\PointLedger;
use App\Models\Profile;
use App\Models\ReferralCode;
use App\Services\GoogleAuthService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class ReferralNotificationTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'notifications.enabled_types.referral_reward_earned' => true,
        ]);
    }

    public function test_business_registration_with_referral_code_rewards_referrer_and_creates_notification(): void
    {
        $city = City::factory()->create(['name' => 'Barcelona', 'country' => 'Spain']);
        $referrer = Profile::factory()->business()->create();
        BusinessProfile::factory()->create([
            'profile_id' => $referrer->id,
            'name' => 'Referring Business',
            'city_id' => $city->id,
            'city_name' => $city->name,
        ]);
        $referralCode = ReferralCode::factory()->forProfile($referrer)->create([
            'code' => 'KOLAB-ABCD',
        ]);

        $response = $this->postJson('/api/v1/auth/register/business', $this->businessPayload($city, [
            'email' => 'converted@example.com',
            'referral_code' => $referralCode->code,
        ]));

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', 'converted@example.com');

        $convertedProfileId = (string) $response->json('data.user.id');

        $this->assertDatabaseHas('point_ledger', [
            'profile_id' => $referrer->id,
            'event_type' => 'referral_conversion',
            'reference_id' => $convertedProfileId,
            'points' => 50,
        ]);

        $this->assertDatabaseHas('referral_codes', [
            'id' => $referralCode->id,
            'total_conversions' => 1,
            'total_points_earned' => 50,
        ]);

        $this->assertDatabaseHas('notifications', [
            'profile_id' => $referrer->id,
            'type' => 'referral_reward_earned',
        ]);
    }

    public function test_google_registration_with_referral_code_is_idempotent_for_repeat_logins(): void
    {
        $city = City::factory()->create(['name' => 'Barcelona', 'country' => 'Spain']);
        $referrer = Profile::factory()->community()->create();
        CommunityProfile::factory()->create([
            'profile_id' => $referrer->id,
            'name' => 'Community Referrer',
            'city_id' => $city->id,
        ]);
        $referralCode = ReferralCode::factory()->forProfile($referrer)->create([
            'code' => 'KOLAB-GOOG',
        ]);

        $this->mock(GoogleAuthService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('verifyIdToken')
                ->twice()
                ->andReturn([
                    'google_id' => 'google-referral-123',
                    'email' => 'google-converted@example.com',
                    'avatar_url' => 'https://example.com/avatar.jpg',
                    'email_verified' => true,
                ]);
        });

        $payload = [
            'id_token' => 'valid-token',
            'user_type' => 'community',
            'referral_code' => $referralCode->code,
        ];

        $this->postJson('/api/v1/auth/google', $payload)->assertOk();
        $this->postJson('/api/v1/auth/google', $payload)->assertOk()->assertJsonPath('data.is_new_user', false);

        $this->assertSame(1, PointLedger::query()
            ->where('profile_id', $referrer->id)
            ->where('event_type', 'referral_conversion')
            ->count());

        $this->assertSame(1, Notification::query()
            ->where('profile_id', $referrer->id)
            ->where('type', 'referral_reward_earned')
            ->count());
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function businessPayload(City $city, array $overrides = []): array
    {
        return array_replace_recursive([
            'email' => 'newbusiness@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'name' => 'Test Business',
            'about' => 'A test business description',
            'business_type' => 'cafe',
            'city_id' => $city->id,
            'phone_number' => '+34612345678',
            'instagram' => '@testbusiness',
            'website' => 'https://testbusiness.com',
            'primary_venue' => [
                'name' => 'Test Business Rooftop',
                'venue_type' => 'cafe',
                'capacity' => 100,
                'formatted_address' => 'Carrer de Mallorca 1, Barcelona',
                'city' => $city->name,
                'country' => $city->country,
                'photos' => [],
            ],
        ], $overrides);
    }
}
