<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\SubscriptionStatus;
use App\Jobs\SendPostHogEvent;
use App\Models\BusinessProfile;
use App\Models\BusinessSubscription;
use App\Models\City;
use App\Models\Profile;
use App\Services\AppleIAPService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class PostHogInstrumentationTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('posthog.enabled', true);
        config()->set('posthog.project_api_key', 'phc_test');
    }

    public function test_attendee_registration_queues_user_registered_event(): void
    {
        Queue::fake();

        $response = $this->postJson('/api/v1/auth/register/attendee', [
            'email' => 'attendee@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertCreated();

        Queue::assertPushed(SendPostHogEvent::class, function (SendPostHogEvent $job): bool {
            return $job->event === 'user_registered'
                && $job->distinctId === Profile::query()->where('email', 'attendee@example.com')->value('id')
                && $job->properties['user_type'] === 'attendee'
                && $job->properties['method'] === 'password';
        });
    }

    public function test_password_login_queues_login_completed_event(): void
    {
        Queue::fake();

        $profile = Profile::factory()->business()->create([
            'email' => 'business@example.com',
            'password' => 'password123',
        ]);
        BusinessProfile::factory()->create(['profile_id' => $profile->id]);
        BusinessSubscription::factory()->create(['profile_id' => $profile->id]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'business@example.com',
            'password' => 'password123',
        ]);

        $response->assertOk();

        Queue::assertPushed(SendPostHogEvent::class, function (SendPostHogEvent $job) use ($profile): bool {
            return $job->event === 'login_completed'
                && $job->distinctId === $profile->id
                && $job->properties['user_type'] === 'business'
                && $job->properties['method'] === 'password';
        });
    }

    public function test_business_onboarding_queues_onboarding_completed_event(): void
    {
        Queue::fake();

        $city = City::factory()->create(['name' => 'Barcelona']);
        $profile = Profile::factory()->business()->create();
        BusinessProfile::factory()->incomplete()->create(['profile_id' => $profile->id]);
        BusinessSubscription::factory()->create(['profile_id' => $profile->id]);

        $response = $this->actingAs($profile)->putJson('/api/v1/onboarding/business', [
            'name' => 'Cafe Barcelona',
            'business_type' => 'cafe',
            'city_id' => $city->id,
            'primary_venue' => [
                'name' => 'Cafe Barcelona Terrace',
                'venue_type' => 'cafe',
                'capacity' => 80,
                'formatted_address' => 'Passeig de Gracia 1, Barcelona',
                'city' => 'Barcelona',
                'country' => 'Spain',
                'photos' => [],
            ],
        ]);

        $response->assertOk();

        Queue::assertPushed(SendPostHogEvent::class, function (SendPostHogEvent $job) use ($profile, $city): bool {
            return $job->event === 'onboarding_completed'
                && $job->distinctId === $profile->id
                && $job->properties['user_type'] === 'business'
                && $job->properties['city_id'] === $city->id;
        });
    }

    public function test_apple_subscription_verification_queues_subscription_verified_event(): void
    {
        Queue::fake();

        $profile = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $profile->id]);
        BusinessSubscription::factory()->create([
            'profile_id' => $profile->id,
            'status' => SubscriptionStatus::Inactive,
        ]);

        $mock = Mockery::mock(AppleIAPService::class)->makePartial();
        $mock->shouldReceive('verifyTransaction')->once()->andReturn([
            'transactionId' => '2000000111111111',
            'originalTransactionId' => '2000000000000001',
            'productId' => 'com.kolabing.app.subscription.monthly',
            'bundleId' => 'com.serragcvc.kolabing',
            'purchaseDate' => now()->subMinute()->getTimestampMs(),
            'expiresDate' => now()->addMonth()->getTimestampMs(),
        ]);
        $this->app->instance(AppleIAPService::class, $mock);

        $response = $this->actingAs($profile)->postJson('/api/v1/me/subscription/apple-verify', [
            'transaction_id' => '2000000111111111',
            'original_transaction_id' => '2000000000000001',
            'product_id' => 'com.kolabing.app.subscription.monthly',
        ]);

        $response->assertOk();

        Queue::assertPushed(SendPostHogEvent::class, function (SendPostHogEvent $job) use ($profile): bool {
            return $job->event === 'subscription_verified'
                && $job->distinctId === $profile->id
                && $job->properties['user_type'] === 'business'
                && $job->properties['source'] === 'apple_iap'
                && $job->properties['product_id'] === 'com.kolabing.app.subscription.monthly';
        });
    }
}
