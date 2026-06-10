<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\SubscriptionStatus;
use App\Models\BusinessProfile;
use App\Models\BusinessSubscription;
use App\Models\Profile;
use App\Services\AppleIAPService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Mockery;
use Tests\TestCase;

class AppleWebhookControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function partialAppleIAPService(): \Mockery\MockInterface
    {
        $mock = Mockery::mock(AppleIAPService::class)->makePartial();
        $this->app->instance(AppleIAPService::class, $mock);

        return $mock;
    }

    private function fakeTransactionData(array $overrides = []): array
    {
        return array_merge([
            'transactionId' => '2000000111111111',
            'originalTransactionId' => '2000000000000001',
            'productId' => (string) config('subscriptions.business.apple.monthly.apple_product_id'),
            'purchaseDate' => now()->subMinute()->getTimestampMs(),
            'expiresDate' => now()->addMonth()->getTimestampMs(),
        ], $overrides);
    }

    public function test_webhook_returns_200_for_missing_payload(): void
    {
        $response = $this->postJson('/api/v1/webhooks/apple', []);

        $response->assertStatus(200);
    }

    public function test_webhook_returns_200_when_jws_decode_fails(): void
    {
        $mock = $this->partialAppleIAPService();
        $mock->shouldReceive('decodeSignedJwt')->once()->andThrow(new \RuntimeException('Bad JWS'));

        $response = $this->postJson('/api/v1/webhooks/apple', [
            'signedPayload' => 'invalid.jws.payload',
        ]);

        $response->assertStatus(200);
    }

    public function test_webhook_handles_did_renew_notification(): void
    {
        $subscription = $this->createAppleSubscription();
        $transactionData = $this->fakeTransactionData([
            'transactionId' => '2000000222222222',
            'originalTransactionId' => $subscription->apple_original_transaction_id,
            'expiresDate' => now()->addMonths(2)->getTimestampMs(),
        ]);

        $mock = $this->partialAppleIAPService();
        $mock->shouldReceive('decodeSignedJwt')
            ->once()
            ->with('outer.jws.payload')
            ->andReturn([
                'notificationType' => 'DID_RENEW',
                'subtype' => '',
                'data' => ['signedTransactionInfo' => 'inner.jws.transaction'],
            ]);
        $mock->shouldReceive('decodeSignedJwt')
            ->once()
            ->with('inner.jws.transaction')
            ->andReturn($transactionData);

        $this->postJson('/api/v1/webhooks/apple', [
            'signedPayload' => 'outer.jws.payload',
        ])->assertOk();

        $this->assertDatabaseHas('business_subscriptions', [
            'id' => $subscription->id,
            'status' => 'active',
            'apple_transaction_id' => '2000000222222222',
        ]);
    }

    public function test_webhook_handles_auto_renew_disabled_notification(): void
    {
        $subscription = $this->createAppleSubscription();
        $transactionData = $this->fakeTransactionData([
            'originalTransactionId' => $subscription->apple_original_transaction_id,
        ]);

        $mock = $this->partialAppleIAPService();
        $mock->shouldReceive('decodeSignedJwt')
            ->once()
            ->with('outer.jws.payload')
            ->andReturn([
                'notificationType' => 'DID_CHANGE_RENEWAL_STATUS',
                'subtype' => 'AUTO_RENEW_DISABLED',
                'data' => ['signedTransactionInfo' => 'inner.jws.transaction'],
            ]);
        $mock->shouldReceive('decodeSignedJwt')
            ->once()
            ->with('inner.jws.transaction')
            ->andReturn($transactionData);

        $this->postJson('/api/v1/webhooks/apple', [
            'signedPayload' => 'outer.jws.payload',
        ])->assertOk();

        $this->assertDatabaseHas('business_subscriptions', [
            'id' => $subscription->id,
            'cancel_at_period_end' => true,
        ]);
    }

    public function test_webhook_keeps_subscription_active_during_grace_period(): void
    {
        $subscription = $this->createAppleSubscription([
            'status' => SubscriptionStatus::PastDue,
        ]);
        $gracePeriodEndsAt = now()->addDays(5);

        $mock = $this->partialAppleIAPService();
        $mock->shouldReceive('decodeSignedJwt')
            ->once()
            ->with('outer.jws.payload')
            ->andReturn([
                'notificationType' => 'DID_FAIL_TO_RENEW',
                'subtype' => 'GRACE_PERIOD',
                'data' => [
                    'signedTransactionInfo' => 'inner.jws.transaction',
                    'signedRenewalInfo' => 'inner.jws.renewal',
                ],
            ]);
            $mock->shouldReceive('decodeSignedJwt')
                ->once()
                ->with('inner.jws.transaction')
                ->andReturn($this->fakeTransactionData([
                    'originalTransactionId' => $subscription->apple_original_transaction_id,
                    'transactionId' => $subscription->apple_transaction_id,
                ]));
        $mock->shouldReceive('decodeSignedJwt')
            ->once()
            ->with('inner.jws.renewal')
            ->andReturn([
                'gracePeriodExpiresDate' => $gracePeriodEndsAt->getTimestampMs(),
            ]);

        $this->postJson('/api/v1/webhooks/apple', [
            'signedPayload' => 'outer.jws.payload',
        ])->assertOk();

        $subscription->refresh();

        $this->assertSame('active', $subscription->status->value);
        $this->assertSame(
            $gracePeriodEndsAt->getTimestamp(),
            $subscription->current_period_end?->getTimestamp(),
        );
    }

    public function test_webhook_sets_subscription_to_past_due_for_billing_retry(): void
    {
        $subscription = $this->createAppleSubscription();

        $mock = $this->partialAppleIAPService();
        $mock->shouldReceive('decodeSignedJwt')
            ->once()
            ->with('outer.jws.payload')
            ->andReturn([
                'notificationType' => 'DID_FAIL_TO_RENEW',
                'subtype' => '',
                'data' => ['signedTransactionInfo' => 'inner.jws.transaction'],
            ]);
        $mock->shouldReceive('decodeSignedJwt')
            ->once()
            ->with('inner.jws.transaction')
            ->andReturn($this->fakeTransactionData([
                'originalTransactionId' => $subscription->apple_original_transaction_id,
            ]));

        $this->postJson('/api/v1/webhooks/apple', [
            'signedPayload' => 'outer.jws.payload',
        ])->assertOk();

        $this->assertDatabaseHas('business_subscriptions', [
            'id' => $subscription->id,
            'status' => 'past_due',
        ]);
    }

    public function test_webhook_sets_subscription_inactive_for_expired_notification(): void
    {
        $this->assertInactiveNotificationDeactivatesSubscription('EXPIRED');
    }

    public function test_webhook_sets_subscription_inactive_for_refund_notification(): void
    {
        $this->assertInactiveNotificationDeactivatesSubscription('REFUND');
    }

    public function test_webhook_sets_subscription_inactive_for_revoke_notification(): void
    {
        $this->assertInactiveNotificationDeactivatesSubscription('REVOKE');
    }

    public function test_webhook_ignores_notifications_without_transaction_info(): void
    {
        $mock = $this->partialAppleIAPService();
        $mock->shouldReceive('decodeSignedJwt')
            ->once()
            ->andReturn([
                'notificationType' => 'TEST',
                'subtype' => '',
                'data' => [],
            ]);

        $response = $this->postJson('/api/v1/webhooks/apple', [
            'signedPayload' => 'some.jws.payload',
        ]);

        $response->assertStatus(200);
    }

    private function createAppleSubscription(array $overrides = []): BusinessSubscription
    {
        $profile = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $profile->id]);

        return BusinessSubscription::factory()->apple()->create(array_merge([
            'profile_id' => $profile->id,
        ], $overrides));
    }

    private function assertInactiveNotificationDeactivatesSubscription(string $notificationType): void
    {
        $subscription = $this->createAppleSubscription();

        $mock = $this->partialAppleIAPService();
        $mock->shouldReceive('decodeSignedJwt')
            ->once()
            ->with('outer.jws.payload')
            ->andReturn([
                'notificationType' => $notificationType,
                'subtype' => '',
                'data' => ['signedTransactionInfo' => 'inner.jws.transaction'],
            ]);
        $mock->shouldReceive('decodeSignedJwt')
            ->once()
            ->with('inner.jws.transaction')
            ->andReturn($this->fakeTransactionData([
                'originalTransactionId' => $subscription->apple_original_transaction_id,
                'transactionId' => $subscription->apple_transaction_id,
            ]));

        $this->postJson('/api/v1/webhooks/apple', [
            'signedPayload' => 'outer.jws.payload',
        ])->assertOk();

        $this->assertDatabaseHas('business_subscriptions', [
            'id' => $subscription->id,
            'status' => 'inactive',
            'cancel_at_period_end' => false,
        ]);
    }
}
