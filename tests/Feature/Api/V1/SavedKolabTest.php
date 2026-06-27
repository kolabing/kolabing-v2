<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Application;
use App\Models\Kolab;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class SavedKolabTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_save_bookmarks_a_kolab_and_marks_it_saved(): void
    {
        $viewer = Profile::factory()->business()->create();
        $kolab = Kolab::factory()->published()->create();

        $this->actingAs($viewer)
            ->postJson(route('api.v1.kolabs.save', $kolab))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.is_saved', true);

        $this->assertDatabaseHas('saved_kolabs', [
            'profile_id' => $viewer->id,
            'kolab_id' => $kolab->id,
        ]);
    }

    public function test_save_is_idempotent(): void
    {
        $viewer = Profile::factory()->business()->create();
        $kolab = Kolab::factory()->published()->create();

        $this->actingAs($viewer)->postJson(route('api.v1.kolabs.save', $kolab))->assertOk();
        $this->actingAs($viewer)->postJson(route('api.v1.kolabs.save', $kolab))->assertOk();

        $this->assertDatabaseCount('saved_kolabs', 1);
    }

    public function test_unsave_removes_the_bookmark(): void
    {
        $viewer = Profile::factory()->business()->create();
        $kolab = Kolab::factory()->published()->create();

        $this->actingAs($viewer)->postJson(route('api.v1.kolabs.save', $kolab))->assertOk();

        $this->actingAs($viewer)
            ->deleteJson(route('api.v1.kolabs.unsave', $kolab))
            ->assertNoContent();

        $this->assertDatabaseMissing('saved_kolabs', [
            'profile_id' => $viewer->id,
            'kolab_id' => $kolab->id,
        ]);
    }

    public function test_unsave_is_idempotent_when_not_saved(): void
    {
        $viewer = Profile::factory()->business()->create();
        $kolab = Kolab::factory()->published()->create();

        $this->actingAs($viewer)
            ->deleteJson(route('api.v1.kolabs.unsave', $kolab))
            ->assertNoContent();

        $this->assertDatabaseCount('saved_kolabs', 0);
    }

    public function test_saved_list_returns_only_the_viewers_saved_kolabs(): void
    {
        $viewer = Profile::factory()->business()->create();
        $saved = Kolab::factory()->published()->create();
        Kolab::factory()->published()->create(); // not saved

        $this->actingAs($viewer)->postJson(route('api.v1.kolabs.save', $saved))->assertOk();

        $response = $this->actingAs($viewer)->getJson('/api/v1/kolabs?saved=1');

        $response->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.data.0.id', $saved->id)
            ->assertJsonPath('data.data.0.is_saved', true);
    }

    public function test_is_saved_is_per_viewer_in_list_and_detail(): void
    {
        $saver = Profile::factory()->business()->create();
        $other = Profile::factory()->business()->create();
        $kolab = Kolab::factory()->published()->create();

        $this->actingAs($saver)->postJson(route('api.v1.kolabs.save', $kolab))->assertOk();

        // Detail: saver sees true, other sees false.
        $this->actingAs($saver)
            ->getJson(route('api.v1.kolabs.show', $kolab))
            ->assertOk()
            ->assertJsonPath('data.is_saved', true);

        $this->actingAs($other)
            ->getJson(route('api.v1.kolabs.show', $kolab))
            ->assertOk()
            ->assertJsonPath('data.is_saved', false);

        // List: other sees the kolab with is_saved=false.
        $this->actingAs($other)
            ->getJson('/api/v1/kolabs')
            ->assertOk()
            ->assertJsonPath('data.data.0.is_saved', false);
    }

    public function test_saved_list_includes_kolabs_the_viewer_already_applied_to(): void
    {
        $viewer = Profile::factory()->community()->create();
        $kolab = Kolab::factory()->published()->create();

        $this->actingAs($viewer)->postJson(route('api.v1.kolabs.save', $kolab))->assertOk();

        Application::factory()->create([
            'kolab_id' => $kolab->id,
            'applicant_profile_id' => $viewer->id,
        ]);

        // Normal browse hides applied kolabs; the saved list must still show it.
        $this->actingAs($viewer)
            ->getJson('/api/v1/kolabs')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->actingAs($viewer)
            ->getJson('/api/v1/kolabs?saved=1')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.data.0.id', $kolab->id);
    }

    public function test_cannot_save_a_kolab_the_viewer_cannot_view(): void
    {
        $viewer = Profile::factory()->business()->create();
        $hidden = Kolab::factory()->create(); // draft, owned by someone else

        $this->actingAs($viewer)
            ->postJson(route('api.v1.kolabs.save', $hidden))
            ->assertStatus(403);

        $this->assertDatabaseCount('saved_kolabs', 0);
    }
}
