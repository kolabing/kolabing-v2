<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\KolabStatus;
use App\Models\Application;
use App\Models\Kolab;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class OpportunityListingTest extends TestCase
{
    use LazilyRefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | My Opportunities (GET /api/v1/kolabs/me)
    |--------------------------------------------------------------------------
    */

    public function test_my_opportunities_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/kolabs/me');

        $response->assertStatus(401);
    }

    public function test_my_opportunities_returns_only_own_opportunities(): void
    {
        // Phase 2: /me/opportunities reads the viewer's KOLABS, not collab_opportunities.
        $owner = Profile::factory()->business()->create();
        $other = Profile::factory()->business()->create();

        Kolab::factory()->count(3)->create(['creator_profile_id' => $owner->id, 'status' => KolabStatus::Published, 'published_at' => now()]);
        Kolab::factory()->count(2)->create(['creator_profile_id' => $other->id, 'status' => KolabStatus::Published, 'published_at' => now()]);

        $response = $this->actingAs($owner)
            ->getJson('/api/v1/kolabs/me');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.total', 3);
    }

    public function test_my_opportunities_returns_all_statuses(): void
    {
        $owner = Profile::factory()->business()->create();

        Kolab::factory()->create(['creator_profile_id' => $owner->id, 'status' => KolabStatus::Draft]);
        Kolab::factory()->create(['creator_profile_id' => $owner->id, 'status' => KolabStatus::Published, 'published_at' => now()]);
        Kolab::factory()->create(['creator_profile_id' => $owner->id, 'status' => KolabStatus::Closed]);

        $response = $this->actingAs($owner)
            ->getJson('/api/v1/kolabs/me');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 3);
    }

    public function test_my_opportunities_filters_by_status(): void
    {
        $owner = Profile::factory()->business()->create();

        Kolab::factory()->create(['creator_profile_id' => $owner->id, 'status' => KolabStatus::Draft]);
        Kolab::factory()->count(2)->create(['creator_profile_id' => $owner->id, 'status' => KolabStatus::Published, 'published_at' => now()]);

        $response = $this->actingAs($owner)
            ->getJson('/api/v1/kolabs/me?status=published');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 2);
    }

    public function test_my_opportunities_returns_correct_structure(): void
    {
        $owner = Profile::factory()->business()->create();
        Kolab::factory()->create(['creator_profile_id' => $owner->id, 'status' => KolabStatus::Published, 'published_at' => now()]);

        $response = $this->actingAs($owner)
            ->getJson('/api/v1/kolabs/me');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'intent_type',
                            'title',
                            'description',
                            'status',
                            'creator_profile',
                            'availability_mode',
                            'availability_start',
                            'availability_end',
                            'preferred_city',
                            'created_at',
                            'updated_at',
                        ],
                    ],
                ],
                'meta' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                ],
            ]);
    }

    public function test_my_opportunities_returns_empty_when_none_exist(): void
    {
        $owner = Profile::factory()->business()->create();

        $response = $this->actingAs($owner)
            ->getJson('/api/v1/kolabs/me');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.total', 0);
    }

    /*
    |--------------------------------------------------------------------------
    | Browse Published Opportunities (GET /api/v1/kolabs)
    |--------------------------------------------------------------------------
    */

    public function test_browse_opportunities_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/kolabs');

        $response->assertStatus(401);
    }

    public function test_browse_opportunities_returns_only_published(): void
    {
        $viewer = Profile::factory()->business()->create();
        $communityCreator = Profile::factory()->community()->create();

        Kolab::factory()->create(['creator_profile_id' => $communityCreator->id, 'status' => KolabStatus::Draft]);
        Kolab::factory()->count(2)->create(['creator_profile_id' => $communityCreator->id, 'status' => KolabStatus::Published, 'published_at' => now()]);
        Kolab::factory()->create(['creator_profile_id' => $communityCreator->id, 'status' => KolabStatus::Closed]);

        $response = $this->actingAs($viewer)
            ->getJson('/api/v1/kolabs');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.total', 2);
    }

    public function test_browse_opportunities_shows_opposite_user_type(): void
    {
        $businessViewer = Profile::factory()->business()->create();
        $communityViewer = Profile::factory()->community()->create();
        $businessCreator = Profile::factory()->business()->create();
        $communityCreator = Profile::factory()->community()->create();

        Kolab::factory()->count(2)->create(['creator_profile_id' => $businessCreator->id, 'status' => KolabStatus::Published, 'published_at' => now()]);
        Kolab::factory()->count(3)->create(['creator_profile_id' => $communityCreator->id, 'status' => KolabStatus::Published, 'published_at' => now()]);

        $response = $this->actingAs($businessViewer)
            ->getJson('/api/v1/kolabs');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 5);

        $response = $this->actingAs($communityViewer)
            ->getJson('/api/v1/kolabs');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 5);
    }

    public function test_browse_opportunities_explicit_creator_type_overrides_default(): void
    {
        $businessViewer = Profile::factory()->business()->create();
        $businessCreator = Profile::factory()->business()->create();

        Kolab::factory()->count(2)->create(['creator_profile_id' => $businessCreator->id, 'status' => KolabStatus::Published, 'published_at' => now()]);

        $response = $this->actingAs($businessViewer)
            ->getJson('/api/v1/kolabs?creator_type=business');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 2);
    }

    /*
    |--------------------------------------------------------------------------
    | Browse: Hide Already Applied Opportunities
    |--------------------------------------------------------------------------
    */

    public function test_browse_excludes_opportunities_user_has_applied_to(): void
    {
        $businessViewer = Profile::factory()->business()->create();
        $communityCreator = Profile::factory()->community()->create();

        $opportunity1 = Kolab::factory()->create(['creator_profile_id' => $communityCreator->id, 'status' => KolabStatus::Published, 'published_at' => now()]);
        $opportunity2 = Kolab::factory()->create(['creator_profile_id' => $communityCreator->id, 'status' => KolabStatus::Published, 'published_at' => now()]);
        Kolab::factory()->create(['creator_profile_id' => $communityCreator->id, 'status' => KolabStatus::Published, 'published_at' => now()]); // not applied

        Application::factory()->forKolab($opportunity1)->forApplicant($businessViewer)->pending()->create();
        Application::factory()->forKolab($opportunity2)->forApplicant($businessViewer)->declined()->create();

        $response = $this->actingAs($businessViewer)
            ->getJson('/api/v1/kolabs');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_browse_excludes_withdrawn_applications(): void
    {
        $businessViewer = Profile::factory()->business()->create();
        $communityCreator = Profile::factory()->community()->create();

        $opportunity = Kolab::factory()->create(['creator_profile_id' => $communityCreator->id, 'status' => KolabStatus::Published, 'published_at' => now()]);
        Kolab::factory()->create(['creator_profile_id' => $communityCreator->id, 'status' => KolabStatus::Published, 'published_at' => now()]); // not applied

        Application::factory()->forKolab($opportunity)->forApplicant($businessViewer)->withdrawn()->create();

        $response = $this->actingAs($businessViewer)
            ->getJson('/api/v1/kolabs');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_browse_excludes_accepted_applications(): void
    {
        $businessViewer = Profile::factory()->business()->create();
        $communityCreator = Profile::factory()->community()->create();

        $opportunity = Kolab::factory()->create(['creator_profile_id' => $communityCreator->id, 'status' => KolabStatus::Published, 'published_at' => now()]);
        Kolab::factory()->count(2)->create(['creator_profile_id' => $communityCreator->id, 'status' => KolabStatus::Published, 'published_at' => now()]);

        Application::factory()->forKolab($opportunity)->forApplicant($businessViewer)->accepted()->create();

        $response = $this->actingAs($businessViewer)
            ->getJson('/api/v1/kolabs');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 2);
    }

    public function test_browse_shows_opportunities_other_users_applied_to(): void
    {
        $businessViewer = Profile::factory()->business()->create();
        $otherBusiness = Profile::factory()->business()->create();
        $communityCreator = Profile::factory()->community()->create();

        $opportunity = Kolab::factory()->create(['creator_profile_id' => $communityCreator->id, 'status' => KolabStatus::Published, 'published_at' => now()]);

        Application::factory()->forKolab($opportunity)->forApplicant($otherBusiness)->pending()->create();

        $response = $this->actingAs($businessViewer)
            ->getJson('/api/v1/kolabs');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 1);
    }
}
