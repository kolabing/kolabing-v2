<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Services\AppleIAPService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Mockery;
use Tests\TestCase;

class AppleWebhookControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function mockAppleIAPService(): \Mockery\MockInterface
    {
        $mock = Mockery::mock(AppleIAPService::class);
        $this->app->instance(AppleIAPService::class, $mock);

        return $mock;
    }

    private function fakeTransactionData(array $overrides = []): array
    {
        return array_merge([
            'transactionId' => '2000000111111111',
            'originalTransactionId' => '2000000000000001',
            'productId' => 'com.kolabing.app.subscription.monthly',
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
        $mock = $this->mockAppleIAPService();
        $mock->shouldReceive('decodeSignedJwt')->once()->andThrow(new \RuntimeException('Bad JWS'));

        $response = $this->postJson('/api/v1/webhooks/apple', [
            'signedPayload' => 'invalid.jws.payload',
        ]);

        $response->assertStatus(200);
    }

    public function test_webhook_handles_did_renew_notification(): void
    {
        $transactionData = $this->fakeTransactionData();

        $mock = $this->mockAppleIAPService();
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
        $mock->shouldReceive('handleNotification')
            ->once()
            ->with('DID_RENEW', $transactionData, '');

        $response = $this->postJson('/api/v1/webhooks/apple', [
            'signedPayload' => 'outer.jws.payload',
        ]);

        $response->assertStatus(200);
    }

    public function test_webhook_handles_expired_notification(): void
    {
        $transactionData = $this->fakeTransactionData();

        $mock = $this->mockAppleIAPService();
        $mock->shouldReceive('decodeSignedJwt')
            ->once()
            ->with('outer.jws.payload')
            ->andReturn([
                'notificationType' => 'EXPIRED',
                'subtype' => '',
                'data' => ['signedTransactionInfo' => 'inner.jws.transaction'],
            ]);
        $mock->shouldReceive('decodeSignedJwt')
            ->once()
            ->with('inner.jws.transaction')
            ->andReturn($transactionData);
        $mock->shouldReceive('handleNotification')
            ->once()
            ->with('EXPIRED', $transactionData, '');

        $response = $this->postJson('/api/v1/webhooks/apple', [
            'signedPayload' => 'outer.jws.payload',
        ]);

        $response->assertStatus(200);
    }

    public function test_webhook_ignores_notifications_without_transaction_info(): void
    {
        $mock = $this->mockAppleIAPService();
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
}
