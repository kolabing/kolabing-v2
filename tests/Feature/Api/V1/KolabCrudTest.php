<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Kolab;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class KolabCrudTest extends TestCase
{
    use LazilyRefreshDatabase;

    // ── Show ────────────────────────────────────────────────────────────

    public function test_creator_can_view_own_draft_kolab(): void
    {
        $creator = Profile::factory()->business()->create();
        $kolab = Kolab::factory()->forCreator($creator)->create(); // draft

        $response = $this->actingAs($creator)
            ->getJson("/api/v1/kolabs/{$kolab->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $kolab->id)
            ->assertJsonPath('data.status', 'draft');
    }

    public function test_other_user_cannot_view_draft_kolab(): void
    {
        $creator = Profile::factory()->business()->create();
        $other = Profile::factory()->community()->create();
        $kolab = Kolab::factory()->forCreator($creator)->create(); // draft

        $response = $this->actingAs($other)
            ->getJson("/api/v1/kolabs/{$kolab->id}");

        $response->assertStatus(403);
    }

    public function test_any_user_can_view_published_kolab(): void
    {
        $creator = Profile::factory()->business()->create();
        $viewer = Profile::factory()->community()->create();
        $kolab = Kolab::factory()->published()->forCreator($creator)->create();

        $response = $this->actingAs($viewer)
            ->getJson("/api/v1/kolabs/{$kolab->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $kolab->id);
    }

    public function test_show_normalizes_media_and_past_event_photos_for_editing(): void
    {
        $creator = Profile::factory()->business()->create();
        $kolab = Kolab::factory()->forCreator($creator)->create([
            'media' => [
                [
                    'url' => 'https://example.com/hero.jpg',
                    'type' => 'photo',
                ],
            ],
            'past_events' => [
                [
                    'name' => 'Spring Social',
                    'date' => '2026-03-14',
                    'partner_name' => 'Cafe Sol',
                    'photos' => [
                        'https://example.com/legacy-photo-1.jpg',
                        'https://example.com/legacy-photo-2.jpg',
                    ],
                ],
                [
                    'name' => 'Rooftop Mixer',
                    'date' => '2026-02-10',
                    'media' => [
                        [
                            'url' => 'https://example.com/editable-photo.jpg',
                            'type' => 'photo',
                            'thumbnail_url' => 'https://example.com/editable-thumb.jpg',
                        ],
                    ],
                ],
            ],
        ]);

        $response = $this->actingAs($creator)
            ->getJson("/api/v1/kolabs/{$kolab->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.media.0.url', 'https://example.com/hero.jpg')
            ->assertJsonPath('data.media.0.type', 'photo')
            ->assertJsonPath('data.media.0.thumbnail_url', null)
            ->assertJsonPath('data.past_events.0.media.0.url', 'https://example.com/legacy-photo-1.jpg')
            ->assertJsonPath('data.past_events.0.media.0.type', 'photo')
            ->assertJsonPath('data.past_events.0.media.0.thumbnail_url', null)
            ->assertJsonPath('data.past_events.1.media.0.thumbnail_url', 'https://example.com/editable-thumb.jpg')
            ->assertJsonMissingPath('data.past_events.0.photos');
    }

    // ── Update ──────────────────────────────────────────────────────────

    public function test_creator_can_update_kolab(): void
    {
        $creator = Profile::factory()->business()->create();
        $kolab = Kolab::factory()->forCreator($creator)->create();

        $response = $this->actingAs($creator)
            ->putJson("/api/v1/kolabs/{$kolab->id}", [
                'title' => 'Updated Kolab Title',
                'community_size' => 500,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.title', 'Updated Kolab Title')
            ->assertJsonPath('data.community_size', 500);
    }

    public function test_creator_cannot_update_kolab_with_invalid_past_event_media_type(): void
    {
        $creator = Profile::factory()->business()->create();
        $kolab = Kolab::factory()->forCreator($creator)->create();

        $response = $this->actingAs($creator)
            ->putJson("/api/v1/kolabs/{$kolab->id}", [
                'past_events' => [
                    [
                        'name' => 'Launch Night',
                        'date' => '2026-03-08',
                        'media' => [
                            [
                                'url' => 'https://example.com/brochure.pdf',
                                'type' => 'document',
                            ],
                        ],
                    ],
                ],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['past_events.0.media.0.type']);
    }

    public function test_other_user_cannot_update_kolab(): void
    {
        $creator = Profile::factory()->business()->create();
        $other = Profile::factory()->community()->create();
        $kolab = Kolab::factory()->forCreator($creator)->create();

        $response = $this->actingAs($other)
            ->putJson("/api/v1/kolabs/{$kolab->id}", [
                'title' => 'Hijacked Title',
            ]);

        $response->assertStatus(403);
    }

    // ── Delete ──────────────────────────────────────────────────────────

    public function test_creator_can_delete_draft_kolab(): void
    {
        $creator = Profile::factory()->business()->create();
        $kolab = Kolab::factory()->forCreator($creator)->create(); // draft

        $response = $this->actingAs($creator)
            ->deleteJson("/api/v1/kolabs/{$kolab->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('kolabs', [
            'id' => $kolab->id,
        ]);
    }

    public function test_creator_cannot_delete_published_kolab(): void
    {
        $creator = Profile::factory()->business()->create();
        $kolab = Kolab::factory()->published()->forCreator($creator)->create();

        $response = $this->actingAs($creator)
            ->deleteJson("/api/v1/kolabs/{$kolab->id}");

        $response->assertStatus(403);
    }

    public function test_other_user_cannot_delete_kolab(): void
    {
        $creator = Profile::factory()->business()->create();
        $other = Profile::factory()->community()->create();
        $kolab = Kolab::factory()->forCreator($creator)->create();

        $response = $this->actingAs($other)
            ->deleteJson("/api/v1/kolabs/{$kolab->id}");

        $response->assertStatus(403);
    }
}
