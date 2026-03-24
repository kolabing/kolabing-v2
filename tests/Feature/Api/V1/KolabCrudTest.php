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
