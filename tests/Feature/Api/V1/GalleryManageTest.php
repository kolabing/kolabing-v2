<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Profile;
use App\Models\ProfileGalleryPhoto;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class GalleryManageTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function photoFor(Profile $profile, int $order, ?string $caption = null): ProfileGalleryPhoto
    {
        return ProfileGalleryPhoto::query()->create([
            'profile_id' => $profile->id,
            'url' => "https://example.com/{$profile->id}-{$order}.jpg",
            'caption' => $caption,
            'sort_order' => $order,
        ]);
    }

    public function test_a_caption_can_be_set_and_cleared(): void
    {
        $profile = Profile::factory()->community()->create();
        $photo = $this->photoFor($profile, 0);

        $this->actingAs($profile)
            ->patchJson("/api/v1/me/gallery/{$photo->id}", ['caption' => 'Opening night'])
            ->assertOk()
            ->assertJsonPath('data.caption', 'Opening night');

        $this->actingAs($profile)
            ->patchJson("/api/v1/me/gallery/{$photo->id}", ['caption' => null])
            ->assertOk()
            ->assertJsonPath('data.caption', null);
    }

    public function test_a_caption_longer_than_the_column_is_rejected(): void
    {
        $profile = Profile::factory()->community()->create();
        $photo = $this->photoFor($profile, 0);

        $this->actingAs($profile)
            ->patchJson("/api/v1/me/gallery/{$photo->id}", ['caption' => str_repeat('x', 501)])
            ->assertStatus(422)
            ->assertJsonValidationErrors('caption');
    }

    public function test_a_stranger_cannot_edit_a_caption(): void
    {
        $owner = Profile::factory()->community()->create();
        $photo = $this->photoFor($owner, 0);

        $this->actingAs(Profile::factory()->community()->create())
            ->patchJson("/api/v1/me/gallery/{$photo->id}", ['caption' => 'mine now'])
            ->assertForbidden();

        $this->assertNull($photo->fresh()->caption);
    }

    public function test_reorder_writes_sort_order_and_returns_the_full_gallery(): void
    {
        $profile = Profile::factory()->community()->create();
        $a = $this->photoFor($profile, 0);
        $b = $this->photoFor($profile, 1);
        $c = $this->photoFor($profile, 2);

        $ids = $this->actingAs($profile)
            ->putJson('/api/v1/me/gallery/order', ['ids' => [$c->id, $a->id, $b->id]])
            ->assertOk()
            ->json('data.*.id');

        $this->assertSame([$c->id, $a->id, $b->id], $ids);
        $this->assertSame(0, $c->fresh()->sort_order);
        $this->assertSame(1, $a->fresh()->sort_order);
        $this->assertSame(2, $b->fresh()->sort_order);
    }

    public function test_reorder_ignores_ids_that_belong_to_someone_else(): void
    {
        $profile = Profile::factory()->community()->create();
        $mine = $this->photoFor($profile, 0);
        $theirs = $this->photoFor(Profile::factory()->community()->create(), 0);

        $ids = $this->actingAs($profile)
            ->putJson('/api/v1/me/gallery/order', ['ids' => [$theirs->id, $mine->id]])
            ->assertOk()
            ->json('data.*.id');

        $this->assertSame([$mine->id], $ids);
        $this->assertSame(0, $theirs->fresh()->sort_order);
    }

    public function test_photos_omitted_from_the_request_keep_their_relative_order_at_the_end(): void
    {
        $profile = Profile::factory()->community()->create();
        $a = $this->photoFor($profile, 0);
        $b = $this->photoFor($profile, 1);
        $c = $this->photoFor($profile, 2);

        $ids = $this->actingAs($profile)
            ->putJson('/api/v1/me/gallery/order', ['ids' => [$c->id]])
            ->assertOk()
            ->json('data.*.id');

        $this->assertSame([$c->id, $a->id, $b->id], $ids);
    }

    public function test_the_gallery_index_returns_photos_in_sort_order(): void
    {
        $profile = Profile::factory()->community()->create();
        $a = $this->photoFor($profile, 0);
        $b = $this->photoFor($profile, 1);

        $this->actingAs($profile)
            ->putJson('/api/v1/me/gallery/order', ['ids' => [$b->id, $a->id]])
            ->assertOk();

        $this->assertSame(
            [$b->id, $a->id],
            $this->actingAs($profile)->getJson('/api/v1/me/gallery')->assertOk()->json('data.*.id'),
        );
    }

    public function test_an_empty_id_list_is_rejected(): void
    {
        $profile = Profile::factory()->community()->create();

        $this->actingAs($profile)
            ->putJson('/api/v1/me/gallery/order', ['ids' => []])
            ->assertStatus(422)
            ->assertJsonValidationErrors('ids');
    }
}
