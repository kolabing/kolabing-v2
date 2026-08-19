<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\BusinessProfile;
use App\Models\CommunityProfile;
use App\Models\Kolab;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * `GET /api/v1/profiles/{profile}/public-profile` — the rich public profile for
 * either role.
 *
 * The community-scoped route has served this shape for a while, but it 404s for a
 * business, which made past events look community-only. They never were:
 * `kolabs.past_events` is written by whoever creates the Kolab.
 */
class ProfilePublicDetailTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function viewer(): Profile
    {
        return Profile::factory()->community()->create();
    }

    /**
     * @param  array<int, array<string, mixed>>  $pastEvents
     */
    private function withPastEvents(Profile $creator, array $pastEvents): Kolab
    {
        return Kolab::factory()->published()->forCreator($creator)->create([
            'past_events' => $pastEvents,
        ]);
    }

    public function test_a_business_gets_its_past_events(): void
    {
        $business = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $business->id, 'name' => 'Cafe Luna']);

        $this->withPastEvents($business, [[
            'name' => 'Latte art night',
            'date' => '2026-05-04',
            'partner_name' => 'Barcelona Runners',
            'photos' => ['https://cdn.example/latte.jpg'],
        ]]);

        $response = $this->actingAs($this->viewer())
            ->getJson("/api/v1/profiles/{$business->id}/public-profile");

        $response->assertOk()
            ->assertJsonPath('data.user_type', 'business')
            ->assertJsonPath('data.display_name', 'Cafe Luna')
            ->assertJsonPath('data.past_events.0.name', 'Latte art night')
            ->assertJsonPath('data.past_events.0.partner_name', 'Barcelona Runners')
            ->assertJsonPath('data.public_stats.past_events_count', 1)
            // Business-shaped identity fields ride along; community ones are null.
            ->assertJsonPath('data.community_type', null)
            ->assertJsonStructure(['data' => [
                'business_type', 'categories', 'gallery', 'photos',
                'past_events', 'past_collaborations', 'public_stats', 'public_url',
            ]]);
    }

    public function test_a_community_gets_the_same_shape(): void
    {
        $community = Profile::factory()->community()->create();
        CommunityProfile::factory()->create([
            'profile_id' => $community->id,
            'name' => 'Barcelona Runners',
            'community_type' => 'sports',
        ]);

        $this->withPastEvents($community, [[
            'name' => 'Sunday beach run',
            'date' => '2026-06-01',
        ]]);

        $this->actingAs($this->viewer())
            ->getJson("/api/v1/profiles/{$community->id}/public-profile")
            ->assertOk()
            ->assertJsonPath('data.user_type', 'community')
            ->assertJsonPath('data.display_name', 'Barcelona Runners')
            ->assertJsonPath('data.community_type', 'sports')
            ->assertJsonPath('data.past_events.0.name', 'Sunday beach run');
    }

    public function test_the_shareable_public_url_is_returned(): void
    {
        $community = Profile::factory()->community()->create();
        CommunityProfile::factory()->create(['profile_id' => $community->id, 'name' => 'Barcelona Runners']);

        $response = $this->actingAs($this->viewer())
            ->getJson("/api/v1/profiles/{$community->id}/public-profile");

        $url = $response->assertOk()->json('data.public_url');

        $this->assertStringContainsString('/p/barcelona-runners-', $url);
        $this->assertStringEndsWith(substr(str_replace('-', '', $community->id), -6), $url);
    }

    public function test_attendees_have_no_public_profile(): void
    {
        $attendee = Profile::factory()->attendee()->create();

        $this->actingAs($this->viewer())
            ->getJson("/api/v1/profiles/{$attendee->id}/public-profile")
            ->assertNotFound();
    }

    public function test_the_endpoint_requires_authentication(): void
    {
        $community = Profile::factory()->community()->create();

        $this->getJson("/api/v1/profiles/{$community->id}/public-profile")->assertUnauthorized();
    }

    public function test_the_community_scoped_route_still_refuses_a_business(): void
    {
        // Existing clients rely on that 404; generalising must not have changed it.
        $business = Profile::factory()->business()->create();

        $this->actingAs($this->viewer())
            ->getJson("/api/v1/communities/{$business->id}/public-profile")
            ->assertNotFound();
    }
}
