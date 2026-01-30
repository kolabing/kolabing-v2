<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Profile;
use App\Models\ProfileGalleryPhoto;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GalleryTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['filesystems.uploads_disk' => 'public']);
        Storage::fake('public');
    }

    /*
    |--------------------------------------------------------------------------
    | List Own Gallery (GET /api/v1/me/gallery)
    |--------------------------------------------------------------------------
    */

    public function test_gallery_index_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/me/gallery');

        $response->assertStatus(401);
    }

    public function test_gallery_index_returns_own_photos(): void
    {
        $profile = Profile::factory()->business()->create();
        ProfileGalleryPhoto::factory()->count(3)->forProfile($profile)->create();

        // Another user's photos
        $other = Profile::factory()->community()->create();
        ProfileGalleryPhoto::factory()->count(2)->forProfile($other)->create();

        $response = $this->actingAs($profile)
            ->getJson('/api/v1/me/gallery');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(3, 'data');
    }

    public function test_gallery_index_returns_empty_when_no_photos(): void
    {
        $profile = Profile::factory()->business()->create();

        $response = $this->actingAs($profile)
            ->getJson('/api/v1/me/gallery');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(0, 'data');
    }

    public function test_gallery_index_returns_correct_structure(): void
    {
        $profile = Profile::factory()->business()->create();
        ProfileGalleryPhoto::factory()->forProfile($profile)->create();

        $response = $this->actingAs($profile)
            ->getJson('/api/v1/me/gallery');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'url',
                        'caption',
                        'sort_order',
                        'created_at',
                    ],
                ],
            ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Upload Gallery Photo (POST /api/v1/me/gallery)
    |--------------------------------------------------------------------------
    */

    public function test_upload_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/me/gallery');

        $response->assertStatus(401);
    }

    public function test_upload_requires_photo(): void
    {
        $profile = Profile::factory()->business()->create();

        $response = $this->actingAs($profile)
            ->postJson('/api/v1/me/gallery', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['photo']);
    }

    public function test_upload_rejects_non_image_file(): void
    {
        $profile = Profile::factory()->business()->create();

        $response = $this->actingAs($profile)
            ->postJson('/api/v1/me/gallery', [
                'photo' => UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'),
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['photo']);
    }

    public function test_upload_rejects_file_exceeding_5mb(): void
    {
        $profile = Profile::factory()->business()->create();

        $response = $this->actingAs($profile)
            ->postJson('/api/v1/me/gallery', [
                'photo' => UploadedFile::fake()->image('large.jpg')->size(6000),
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['photo']);
    }

    public function test_business_user_can_upload_gallery_photo(): void
    {
        $profile = Profile::factory()->business()->create();

        $response = $this->actingAs($profile)
            ->postJson('/api/v1/me/gallery', [
                'photo' => UploadedFile::fake()->image('test.jpg', 800, 600),
                'caption' => 'My business photo',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.caption', 'My business photo')
            ->assertJsonStructure([
                'success',
                'message',
                'data' => ['id', 'url', 'caption', 'sort_order', 'created_at'],
            ]);

        $this->assertDatabaseHas('profile_gallery_photos', [
            'profile_id' => $profile->id,
            'caption' => 'My business photo',
        ]);
    }

    public function test_community_user_can_upload_gallery_photo(): void
    {
        $profile = Profile::factory()->community()->create();

        $response = $this->actingAs($profile)
            ->postJson('/api/v1/me/gallery', [
                'photo' => UploadedFile::fake()->image('community.png', 800, 600),
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('profile_gallery_photos', [
            'profile_id' => $profile->id,
        ]);
    }

    public function test_upload_enforces_max_10_photos_limit(): void
    {
        $profile = Profile::factory()->business()->create();
        ProfileGalleryPhoto::factory()->count(10)->forProfile($profile)->create();

        $response = $this->actingAs($profile)
            ->postJson('/api/v1/me/gallery', [
                'photo' => UploadedFile::fake()->image('extra.jpg', 800, 600),
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    public function test_upload_caption_is_optional(): void
    {
        $profile = Profile::factory()->business()->create();

        $response = $this->actingAs($profile)
            ->postJson('/api/v1/me/gallery', [
                'photo' => UploadedFile::fake()->image('no-caption.jpg', 800, 600),
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.caption', null);
    }

    /*
    |--------------------------------------------------------------------------
    | Delete Gallery Photo (DELETE /api/v1/me/gallery/{photo})
    |--------------------------------------------------------------------------
    */

    public function test_delete_requires_authentication(): void
    {
        $photo = ProfileGalleryPhoto::factory()->create();

        $response = $this->deleteJson("/api/v1/me/gallery/{$photo->id}");

        $response->assertStatus(401);
    }

    public function test_owner_can_delete_own_photo(): void
    {
        $profile = Profile::factory()->business()->create();
        $photo = ProfileGalleryPhoto::factory()->forProfile($profile)->create();

        $response = $this->actingAs($profile)
            ->deleteJson("/api/v1/me/gallery/{$photo->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('profile_gallery_photos', [
            'id' => $photo->id,
        ]);
    }

    public function test_cannot_delete_other_users_photo(): void
    {
        $profile = Profile::factory()->business()->create();
        $other = Profile::factory()->community()->create();
        $photo = ProfileGalleryPhoto::factory()->forProfile($other)->create();

        $response = $this->actingAs($profile)
            ->deleteJson("/api/v1/me/gallery/{$photo->id}");

        $response->assertStatus(403);

        $this->assertDatabaseHas('profile_gallery_photos', [
            'id' => $photo->id,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | View Profile Gallery (GET /api/v1/profiles/{profile}/gallery)
    |--------------------------------------------------------------------------
    */

    public function test_view_profile_gallery_requires_authentication(): void
    {
        $profile = Profile::factory()->business()->create();

        $response = $this->getJson("/api/v1/profiles/{$profile->id}/gallery");

        $response->assertStatus(401);
    }

    public function test_can_view_other_profile_gallery(): void
    {
        $viewer = Profile::factory()->business()->create();
        $target = Profile::factory()->community()->create();
        ProfileGalleryPhoto::factory()->count(4)->forProfile($target)->create();

        $response = $this->actingAs($viewer)
            ->getJson("/api/v1/profiles/{$target->id}/gallery");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(4, 'data');
    }

    public function test_view_profile_gallery_returns_empty_for_no_photos(): void
    {
        $viewer = Profile::factory()->business()->create();
        $target = Profile::factory()->community()->create();

        $response = $this->actingAs($viewer)
            ->getJson("/api/v1/profiles/{$target->id}/gallery");

        $response->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }
}
