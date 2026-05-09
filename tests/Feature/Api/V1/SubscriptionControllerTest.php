<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\BusinessProfile;
use App\Models\BusinessSubscription;
use App\Models\CommunityProfile;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class SubscriptionControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_show_subscription_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/me/subscription');

        $response->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Unauthenticated');
    }

    public function test_show_subscription_forbidden_for_community_user(): void
    {
        $profile = Profile::factory()->community()->create();
        CommunityProfile::factory()->create(['profile_id' => $profile->id]);

        $response = $this->actingAs($profile)
            ->getJson('/api/v1/me/subscription');

        $response->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Only business users can have subscriptions');
    }

    public function test_show_subscription_returns_null_when_no_subscription(): void
    {
        $profile = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $profile->id]);

        $response = $this->actingAs($profile)
            ->getJson('/api/v1/me/subscription');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data', null)
            ->assertJsonPath('message', 'No subscription found');
    }

    public function test_show_subscription_returns_active_apple_subscription(): void
    {
        $profile = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $profile->id]);
        BusinessSubscription::factory()->apple()->create([
            'profile_id' => $profile->id,
            'cancel_at_period_end' => true,
        ]);

        $response = $this->actingAs($profile)
            ->getJson('/api/v1/me/subscription');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.source', 'apple_iap')
            ->assertJsonPath('data.cancel_at_period_end', true)
            ->assertJsonPath('data.is_active', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'status',
                    'status_label',
                    'source',
                    'current_period_start',
                    'current_period_end',
                    'cancel_at_period_end',
                    'is_active',
                    'days_remaining',
                    'apple_product_id',
                ],
            ]);
    }

    public function test_legacy_stripe_subscription_endpoints_are_removed(): void
    {
        $profile = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $profile->id]);

        $this->actingAs($profile)
            ->postJson('/api/v1/me/subscription/checkout', [
                'success_url' => 'https://example.com/success',
                'cancel_url' => 'https://example.com/cancel',
            ])
            ->assertStatus(404);

        $this->actingAs($profile)
            ->getJson('/api/v1/me/subscription/portal')
            ->assertStatus(404);

        $this->actingAs($profile)
            ->postJson('/api/v1/me/subscription/cancel')
            ->assertStatus(404);

        $this->actingAs($profile)
            ->postJson('/api/v1/me/subscription/reactivate')
            ->assertStatus(404);

        $this->postJson('/api/v1/webhooks/stripe')
            ->assertStatus(404);
    }
}
