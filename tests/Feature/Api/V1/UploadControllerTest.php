<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UploadControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['filesystems.uploads_disk' => 'public']);
        Storage::fake('public');
    }

    public function test_upload_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/uploads', []);

        $response->assertStatus(401);
    }

    public function test_upload_returns_canonical_media_shape_for_profile_usage(): void
    {
        $profile = Profile::factory()->business()->create();

        $response = $this->actingAs($profile)
            ->post('/api/v1/uploads', [
                'file' => UploadedFile::fake()->image('avatar.jpg', 800, 800),
                'folder' => 'profiles',
            ], [
                'Accept' => 'application/json',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'url',
                    'type',
                    'thumbnail_url',
                ],
            ])
            ->assertJsonPath('data.type', 'photo');

        $this->assertStringContainsString('profiles/', (string) $response->json('data.url'));
    }

    public function test_kolab_upload_accepts_video_and_returns_video_type(): void
    {
        $profile = Profile::factory()->business()->create();

        $response = $this->actingAs($profile)
            ->post('/api/v1/uploads', [
                'file' => UploadedFile::fake()->create('promo.mp4', 2048, 'video/mp4'),
                'folder' => 'kolabs',
            ], [
                'Accept' => 'application/json',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.type', 'video')
            ->assertJsonPath('data.thumbnail_url', null);

        $this->assertStringContainsString('kolabs/', (string) $response->json('data.url'));
    }
}
