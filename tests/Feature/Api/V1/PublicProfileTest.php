<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Collaboration;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class PublicProfileTest extends TestCase
{
    use LazilyRefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | Public Profile (GET /api/v1/profiles/{profile})
    |--------------------------------------------------------------------------
    */

    public function test_public_profile_requires_authentication(): void
    {
        $profile = Profile::factory()->business()->create();

        $response = $this->getJson("/api/v1/profiles/{$profile->id}");

        $response->assertStatus(401);
    }

    public function test_public_profile_returns_business_profile(): void
    {
        $viewer = Profile::factory()->community()->create();
        $business = Profile::factory()->business()->create();

        $response = $this->actingAs($viewer)
            ->getJson("/api/v1/profiles/{$business->id}");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $business->id)
            ->assertJsonPath('data.user_type', 'business')
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'user_type',
                    'display_name',
                    'avatar_url',
                    'about',
                    'type',
                    'city_name',
                    'instagram',
                    'tiktok',
                    'website',
                    'profile_photo',
                ],
            ]);
    }

    public function test_public_profile_returns_community_profile(): void
    {
        $viewer = Profile::factory()->business()->create();
        $community = Profile::factory()->community()->create();

        $response = $this->actingAs($viewer)
            ->getJson("/api/v1/profiles/{$community->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.user_type', 'community');
    }

    public function test_public_profile_does_not_expose_sensitive_data(): void
    {
        $viewer = Profile::factory()->business()->create();
        $target = Profile::factory()->community()->create();

        $response = $this->actingAs($viewer)
            ->getJson("/api/v1/profiles/{$target->id}");

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertArrayNotHasKey('email', $data);
        $this->assertArrayNotHasKey('phone_number', $data);
        $this->assertArrayNotHasKey('google_id', $data);
        $this->assertArrayNotHasKey('password', $data);
    }

    public function test_public_profile_returns_404_for_nonexistent(): void
    {
        $viewer = Profile::factory()->business()->create();

        $response = $this->actingAs($viewer)
            ->getJson('/api/v1/profiles/00000000-0000-0000-0000-000000000000');

        $response->assertStatus(404);
    }

    public function test_business_tiktok_is_null(): void
    {
        $viewer = Profile::factory()->community()->create();
        $business = Profile::factory()->business()->create();

        $response = $this->actingAs($viewer)
            ->getJson("/api/v1/profiles/{$business->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.tiktok', null);
    }

    /*
    |--------------------------------------------------------------------------
    | Profile Collaborations (GET /api/v1/profiles/{profile}/collaborations)
    |--------------------------------------------------------------------------
    */

    public function test_profile_collaborations_requires_authentication(): void
    {
        $profile = Profile::factory()->business()->create();

        $response = $this->getJson("/api/v1/profiles/{$profile->id}/collaborations");

        $response->assertStatus(401);
    }

    public function test_profile_collaborations_returns_only_completed(): void
    {
        $viewer = Profile::factory()->community()->create();
        $target = Profile::factory()->business()->create();

        // Completed collaborations (as creator)
        Collaboration::factory()->count(2)->completed()->forCreator($target)->create();

        // Non-completed (should not appear)
        Collaboration::factory()->scheduled()->forCreator($target)->create();
        Collaboration::factory()->active()->forCreator($target)->create();
        Collaboration::factory()->cancelled()->forCreator($target)->create();

        $response = $this->actingAs($viewer)
            ->getJson("/api/v1/profiles/{$target->id}/collaborations");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.total', 2);
    }

    public function test_profile_collaborations_includes_applicant_role(): void
    {
        $viewer = Profile::factory()->community()->create();
        $target = Profile::factory()->community()->create();

        // Collaboration where target is the applicant
        Collaboration::factory()->completed()->forApplicant($target)->create();

        $response = $this->actingAs($viewer)
            ->getJson("/api/v1/profiles/{$target->id}/collaborations");

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_profile_collaborations_returns_correct_structure(): void
    {
        $viewer = Profile::factory()->community()->create();
        $target = Profile::factory()->business()->create();

        Collaboration::factory()->completed()->forCreator($target)->create();

        $response = $this->actingAs($viewer)
            ->getJson("/api/v1/profiles/{$target->id}/collaborations");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'title',
                            'partner_name',
                            'partner_avatar_url',
                            'completed_at',
                            'status',
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

    public function test_profile_collaborations_returns_empty_when_none(): void
    {
        $viewer = Profile::factory()->community()->create();
        $target = Profile::factory()->business()->create();

        $response = $this->actingAs($viewer)
            ->getJson("/api/v1/profiles/{$target->id}/collaborations");

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 0);
    }

    public function test_profile_collaborations_supports_pagination(): void
    {
        $viewer = Profile::factory()->community()->create();
        $target = Profile::factory()->business()->create();

        Collaboration::factory()->count(5)->completed()->forCreator($target)->create();

        $response = $this->actingAs($viewer)
            ->getJson("/api/v1/profiles/{$target->id}/collaborations?per_page=2");

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 5)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonCount(2, 'data.data');
    }

    public function test_profile_collaborations_status_is_always_completed(): void
    {
        $viewer = Profile::factory()->community()->create();
        $target = Profile::factory()->business()->create();

        Collaboration::factory()->completed()->forCreator($target)->create();

        $response = $this->actingAs($viewer)
            ->getJson("/api/v1/profiles/{$target->id}/collaborations");

        $response->assertStatus(200);

        $items = $response->json('data.data');
        foreach ($items as $item) {
            $this->assertEquals('completed', $item['status']);
        }
    }
}
