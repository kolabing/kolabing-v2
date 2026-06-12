<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Event;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EventPhotoTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['filesystems.uploads_disk' => 'public']);
        Storage::fake('public');
    }

    private function image(): UploadedFile
    {
        return UploadedFile::fake()->image('photo.jpg', 600, 400);
    }

    public function test_creator_can_add_photos_to_an_event(): void
    {
        $owner = Profile::factory()->community()->create();
        $event = Event::factory()->create(['profile_id' => $owner->id]);

        $this->actingAs($owner)
            ->postJson("/api/v1/events/{$event->id}/photos", ['photos' => [$this->image(), $this->image()]])
            ->assertStatus(201)
            ->assertJsonCount(2, 'data.photos');

        $this->assertSame(2, $event->photos()->count());
    }

    public function test_per_request_cap_of_five_is_enforced(): void
    {
        $owner = Profile::factory()->community()->create();
        $event = Event::factory()->create(['profile_id' => $owner->id]);

        // 6 in a single request exceeds the per-request max of 5 (validation).
        $this->actingAs($owner)->postJson("/api/v1/events/{$event->id}/photos", [
            'photos' => [
                $this->image(), $this->image(), $this->image(),
                $this->image(), $this->image(), $this->image(),
            ],
        ])->assertStatus(422)->assertJsonValidationErrors(['photos']);

        $this->assertSame(0, $event->photos()->count());
    }

    public function test_total_photo_cap_of_twenty_is_enforced_across_requests(): void
    {
        $owner = Profile::factory()->community()->create();
        $event = Event::factory()->create(['profile_id' => $owner->id]);

        // Fill to 20 via four 5-photo requests (all accepted).
        for ($i = 0; $i < 4; $i++) {
            $this->actingAs($owner)->postJson("/api/v1/events/{$event->id}/photos", [
                'photos' => [$this->image(), $this->image(), $this->image(), $this->image(), $this->image()],
            ])->assertStatus(201);
        }

        $this->assertSame(20, $event->photos()->count());

        // 20 existing + 1 more = 21 > 20 → rejected.
        $this->actingAs($owner)->postJson("/api/v1/events/{$event->id}/photos", [
            'photos' => [$this->image()],
        ])->assertStatus(422)->assertJsonPath('error', 'photo_limit_reached');

        $this->assertSame(20, $event->photos()->count());
    }

    public function test_creator_can_delete_a_photo(): void
    {
        $owner = Profile::factory()->community()->create();
        $event = Event::factory()->create(['profile_id' => $owner->id]);

        $photoId = $this->actingAs($owner)
            ->postJson("/api/v1/events/{$event->id}/photos", ['photos' => [$this->image()]])
            ->json('data.photos.0.id');

        $this->actingAs($owner)
            ->deleteJson("/api/v1/events/{$event->id}/photos/{$photoId}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('event_photos', ['id' => $photoId]);
    }

    public function test_cannot_delete_a_photo_from_another_event(): void
    {
        $owner = Profile::factory()->community()->create();
        $eventA = Event::factory()->create(['profile_id' => $owner->id]);
        $eventB = Event::factory()->create(['profile_id' => $owner->id]);

        $photoId = $this->actingAs($owner)
            ->postJson("/api/v1/events/{$eventA->id}/photos", ['photos' => [$this->image()]])
            ->json('data.photos.0.id');

        $this->actingAs($owner)
            ->deleteJson("/api/v1/events/{$eventB->id}/photos/{$photoId}")
            ->assertStatus(404);
    }

    public function test_non_manager_cannot_add_photos(): void
    {
        $owner = Profile::factory()->community()->create();
        $event = Event::factory()->create(['profile_id' => $owner->id]);
        $outsider = Profile::factory()->community()->create();

        $this->actingAs($outsider)
            ->postJson("/api/v1/events/{$event->id}/photos", ['photos' => [$this->image()]])
            ->assertStatus(403);
    }
}
