<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Kolab;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class KolabBrowseTest extends TestCase
{
    use LazilyRefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | Browse Published Kolabs (GET /api/v1/kolabs)
    |--------------------------------------------------------------------------
    */

    public function test_browse_returns_only_published_kolabs(): void
    {
        $viewer = Profile::factory()->business()->create();

        Kolab::factory()->published()->count(2)->create();
        Kolab::factory()->count(3)->create(); // draft

        $response = $this->actingAs($viewer)
            ->getJson('/api/v1/kolabs');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.total', 2);
    }

    public function test_browse_filters_by_intent_type(): void
    {
        $viewer = Profile::factory()->business()->create();

        Kolab::factory()->published()->count(2)->create(); // community_seeking (default)
        Kolab::factory()->published()->venuePromotion()->count(1)->create();

        $response = $this->actingAs($viewer)
            ->getJson('/api/v1/kolabs?intent_type=venue_promotion');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_browse_filters_by_city(): void
    {
        $viewer = Profile::factory()->business()->create();

        Kolab::factory()->published()->create(['preferred_city' => 'Barcelona']);
        Kolab::factory()->published()->create(['preferred_city' => 'Barcelona']);
        Kolab::factory()->published()->create(['preferred_city' => 'Madrid']);

        $response = $this->actingAs($viewer)
            ->getJson('/api/v1/kolabs?city=Barcelona');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.total', 2);
    }

    public function test_browse_includes_creator_profile(): void
    {
        $viewer = Profile::factory()->business()->create();

        Kolab::factory()->published()->create();

        $response = $this->actingAs($viewer)
            ->getJson('/api/v1/kolabs');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'data' => [
                        '*' => [
                            'creator_profile',
                        ],
                    ],
                ],
                'meta',
            ]);
    }

    public function test_browse_paginates_results(): void
    {
        $viewer = Profile::factory()->business()->create();

        Kolab::factory()->published()->count(20)->create();

        $response = $this->actingAs($viewer)
            ->getJson('/api/v1/kolabs?per_page=5');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.per_page', 5)
            ->assertJsonPath('meta.total', 20);
    }

    /*
    |--------------------------------------------------------------------------
    | My Kolabs (GET /api/v1/kolabs/me)
    |--------------------------------------------------------------------------
    */

    public function test_my_kolabs_returns_only_own_kolabs(): void
    {
        $owner = Profile::factory()->business()->create();
        $other = Profile::factory()->business()->create();

        Kolab::factory()->forCreator($owner)->count(3)->create();
        Kolab::factory()->forCreator($other)->count(2)->create();

        $response = $this->actingAs($owner)
            ->getJson('/api/v1/kolabs/me');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.total', 3);
    }

    public function test_my_kolabs_filters_by_status(): void
    {
        $owner = Profile::factory()->business()->create();

        Kolab::factory()->forCreator($owner)->count(2)->create(); // draft (default)
        Kolab::factory()->forCreator($owner)->published()->count(1)->create();

        $response = $this->actingAs($owner)
            ->getJson('/api/v1/kolabs/me?status=draft');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.total', 2);
    }

    public function test_unauthenticated_user_cannot_browse(): void
    {
        $response = $this->getJson('/api/v1/kolabs');

        $response->assertStatus(401);
    }

    public function test_browse_excludes_kolabs_whose_dates_have_all_passed(): void
    {
        $viewer = Profile::factory()->business()->create();

        $live = Kolab::factory()->published()->create([
            'availability_start' => now()->addDay(),
            'availability_end' => now()->addMonth(),
        ]);
        Kolab::factory()->published()->create([
            'availability_start' => now()->subDays(30),
            'availability_end' => now()->subDay(), // window fully in the past
        ]);

        $response = $this->actingAs($viewer)->getJson('/api/v1/kolabs');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.data.0.id', $live->id);
    }

    public function test_browse_keeps_open_ended_kolabs(): void
    {
        $viewer = Profile::factory()->business()->create();

        Kolab::factory()->published()->create([
            'availability_start' => now(),
            'availability_end' => null, // open-ended → always selectable
        ]);

        $response = $this->actingAs($viewer)->getJson('/api/v1/kolabs');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_saved_list_still_shows_date_exhausted_kolabs(): void
    {
        $viewer = Profile::factory()->business()->create();

        $expired = Kolab::factory()->published()->create([
            'availability_start' => now()->subDays(30),
            'availability_end' => now()->subDay(),
        ]);
        $this->actingAs($viewer)->postJson(route('api.v1.kolabs.save', $expired))->assertOk();

        $response = $this->actingAs($viewer)->getJson('/api/v1/kolabs?saved=1');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.data.0.id', $expired->id);
    }
}
