<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class DeviceTokenControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_store_device_token_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/me/device-token', [
            'token' => 'fcm-token-here',
            'platform' => 'ios',
        ]);

        $response->assertStatus(401)
            ->assertJsonPath('success', false);
    }

    public function test_store_device_token_requires_token(): void
    {
        $profile = Profile::factory()->business()->create();

        $response = $this->actingAs($profile)
            ->postJson('/api/v1/me/device-token', [
                'platform' => 'ios',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Validation failed')
            ->assertJsonStructure([
                'success',
                'message',
                'errors' => ['token'],
            ]);
    }

    public function test_store_device_token_requires_platform(): void
    {
        $profile = Profile::factory()->business()->create();

        $response = $this->actingAs($profile)
            ->postJson('/api/v1/me/device-token', [
                'token' => 'fcm-token-here',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure([
                'success',
                'message',
                'errors' => ['platform'],
            ]);
    }

    public function test_store_device_token_validates_platform_values(): void
    {
        $profile = Profile::factory()->business()->create();

        $response = $this->actingAs($profile)
            ->postJson('/api/v1/me/device-token', [
                'token' => 'fcm-token-here',
                'platform' => 'windows',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonStructure([
                'success',
                'message',
                'errors' => ['platform'],
            ]);
    }

    public function test_store_device_token_stores_ios_token(): void
    {
        $profile = Profile::factory()->business()->create();

        $response = $this->actingAs($profile)
            ->postJson('/api/v1/me/device-token', [
                'token' => 'fcm-ios-token-abc123',
                'platform' => 'ios',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Device token registered successfully');

        $this->assertDatabaseHas('profiles', [
            'id' => $profile->id,
            'device_token' => 'fcm-ios-token-abc123',
            'device_platform' => 'ios',
        ]);
    }

    public function test_store_device_token_stores_android_token(): void
    {
        $profile = Profile::factory()->community()->create();

        $response = $this->actingAs($profile)
            ->postJson('/api/v1/me/device-token', [
                'token' => 'fcm-android-token-xyz789',
                'platform' => 'android',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Device token registered successfully');

        $this->assertDatabaseHas('profiles', [
            'id' => $profile->id,
            'device_token' => 'fcm-android-token-xyz789',
            'device_platform' => 'android',
        ]);
    }

    public function test_store_device_token_updates_existing_token(): void
    {
        $profile = Profile::factory()->business()->create([
            'device_token' => 'old-token',
            'device_platform' => 'ios',
        ]);

        $response = $this->actingAs($profile)
            ->postJson('/api/v1/me/device-token', [
                'token' => 'new-refreshed-token',
                'platform' => 'ios',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('profiles', [
            'id' => $profile->id,
            'device_token' => 'new-refreshed-token',
        ]);
    }
}
