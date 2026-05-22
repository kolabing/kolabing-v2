<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Application;
use App\Models\CollabOpportunity;
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
        $kolab = Kolab::factory()->published()->venuePromotion()->forCreator($creator)->create([
            'offer_headline' => 'Free coffee tastings for local food groups',
            'base_offer' => 'Complimentary tasting flights for food communities visiting on weekdays.',
            'negotiation_triggers' => [
                [
                    'condition' => 'Groups of 20+',
                    'additional_offer' => 'Add a dessert pairing for the first visit.',
                ],
            ],
        ]);

        $response = $this->actingAs($viewer)
            ->getJson("/api/v1/kolabs/{$kolab->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $kolab->id)
            ->assertJsonPath('data.offer_headline', 'Free coffee tastings for local food groups')
            ->assertJsonPath('data.base_offer', 'Complimentary tasting flights for food communities visiting on weekdays.')
            ->assertJsonMissingPath('data.negotiation_triggers');
    }

    public function test_published_kolab_shows_negotiation_triggers_after_viewer_has_applied(): void
    {
        $creator = Profile::factory()->business()->create();
        $viewer = Profile::factory()->community()->create();

        $kolab = Kolab::factory()->published()->venuePromotion()->forCreator($creator)->create([
            'offer_headline' => 'Free coffee tastings for local food groups',
            'base_offer' => 'Complimentary tasting flights for food communities visiting on weekdays.',
            'negotiation_triggers' => [
                [
                    'condition' => 'Groups of 20+',
                    'additional_offer' => 'Add a dessert pairing for the first visit.',
                ],
            ],
        ]);

        $opportunity = CollabOpportunity::factory()->published()->forCreator($creator)->create([
            'id' => $kolab->id,
        ]);

        Application::factory()
            ->forOpportunity($opportunity)
            ->forApplicant($viewer)
            ->create();

        $response = $this->actingAs($viewer)
            ->getJson("/api/v1/kolabs/{$kolab->id}");

        $response->assertOk()
            ->assertJsonPath('data.negotiation_triggers.0.condition', 'Groups of 20+')
            ->assertJsonPath('data.negotiation_triggers.0.additional_offer', 'Add a dessert pairing for the first visit.');
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
            ->assertJsonPath('data.media.0.type', 'image')
            ->assertJsonPath('data.media.0.thumbnail_url', null)
            ->assertJsonPath('data.past_events.0.media.0.url', 'https://example.com/legacy-photo-1.jpg')
            ->assertJsonPath('data.past_events.0.media.0.type', 'image')
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

    public function test_community_user_cannot_update_kolab_to_business_only_intent(): void
    {
        $creator = Profile::factory()->community()->create();
        $kolab = Kolab::factory()->forCreator($creator)->create([
            'intent_type' => 'community_seeking',
        ]);

        $response = $this->actingAs($creator)
            ->putJson("/api/v1/kolabs/{$kolab->id}", [
                'intent_type' => 'product_promotion',
                'product_name' => 'Peak Fuel Bar',
                'product_type' => 'food_product',
                'offering' => ['products'],
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['intent_type']);
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
