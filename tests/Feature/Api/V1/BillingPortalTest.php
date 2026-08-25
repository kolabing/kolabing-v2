<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\BusinessProfile;
use App\Models\BusinessSubscription;
use App\Models\CommunityProfile;
use App\Models\Profile;
use App\Services\StripeService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class BillingPortalTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function business(): Profile
    {
        $profile = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $profile->id]);

        return $profile;
    }

    private const RETURN_URL = 'https://app.kolabing.com/subscription';

    public function test_portal_requires_authentication(): void
    {
        $this->postJson('/api/v1/me/subscription/portal', ['return_url' => self::RETURN_URL])
            ->assertStatus(401);
    }

    public function test_portal_forbidden_for_community_user(): void
    {
        $profile = Profile::factory()->community()->create();
        CommunityProfile::factory()->create(['profile_id' => $profile->id]);

        $this->actingAs($profile)
            ->postJson('/api/v1/me/subscription/portal', ['return_url' => self::RETURN_URL])
            ->assertStatus(403);
    }

    public function test_portal_conflict_when_no_stripe_subscription(): void
    {
        $profile = $this->business();

        // No subscription row at all → nothing to manage in Stripe.
        $this->actingAs($profile)
            ->postJson('/api/v1/me/subscription/portal', ['return_url' => self::RETURN_URL])
            ->assertStatus(409);
    }

    public function test_portal_rejects_a_non_allowlisted_return_url(): void
    {
        $profile = $this->business();
        BusinessSubscription::factory()->active()->create([
            'profile_id' => $profile->id,
            'stripe_customer_id' => 'cus_test_123',
        ]);

        $this->actingAs($profile)
            ->postJson('/api/v1/me/subscription/portal', ['return_url' => 'https://evil.example.com/x'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('return_url');
    }

    public function test_business_with_stripe_subscription_gets_a_portal_url(): void
    {
        $profile = $this->business();
        BusinessSubscription::factory()->active()->create([
            'profile_id' => $profile->id,
            'stripe_customer_id' => 'cus_test_123',
        ]);

        $this->mock(StripeService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('createBillingPortalSession')
                ->once()
                ->with('cus_test_123', self::RETURN_URL)
                ->andReturn('https://billing.stripe.com/p/session/test_123');
        });

        $this->actingAs($profile)
            ->postJson('/api/v1/me/subscription/portal', ['return_url' => self::RETURN_URL])
            ->assertOk()
            ->assertJsonPath('data.portal_url', 'https://billing.stripe.com/p/session/test_123');
    }
}
