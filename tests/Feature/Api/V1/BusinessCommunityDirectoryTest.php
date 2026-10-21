<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\BusinessProfile;
use App\Models\BusinessSubscription;
use App\Models\CommunityProfile;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * GET /api/v1/discovery/communities — every community, for a business to scroll
 * (SERR-201, ROLES §2.24). A free business sees the whole list blurred; the
 * identity is withheld by the server, never only painted over by the app.
 */
class BusinessCommunityDirectoryTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function business(bool $subscribed = false): Profile
    {
        $profile = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $profile->id]);

        if ($subscribed) {
            BusinessSubscription::factory()->active()->create(['profile_id' => $profile->id]);
        }

        return $profile->fresh();
    }

    private function community(string $name, array $profile = [], array $extended = []): Profile
    {
        $community = Profile::factory()->community()->create([
            'avatar_url' => 'https://cdn.test/community.png',
            ...$profile,
        ]);

        CommunityProfile::factory()->create([
            'profile_id' => $community->id,
            'name' => $name,
            ...$extended,
        ]);

        return $community->fresh();
    }

    private function list(Profile $viewer, string $query = ''): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($viewer)->getJson('/api/v1/discovery/communities'.$query);
    }

    public function test_a_free_business_sees_every_community_with_the_identity_withheld(): void
    {
        $this->community('Barcelona Run Club');
        $this->community('Gràcia Book Club');

        $response = $this->list($this->business())->assertOk();

        $response->assertJsonPath('meta.total', 2);
        $this->assertCount(2, $response->json('data'));

        foreach ($response->json('data') as $item) {
            $this->assertTrue($item['identity_masked']);
            $this->assertNull($item['display_name']);
            $this->assertNull($item['avatar_url']);
        }

        // The name is nowhere in the payload, not only blank in one field.
        $this->assertStringNotContainsString('Barcelona Run Club', $response->getContent());
        $this->assertStringNotContainsString('cdn.test/community.png', $response->getContent());
    }

    public function test_a_subscribed_business_sees_names_and_logos(): void
    {
        $this->community('Barcelona Run Club');

        $this->list($this->business(subscribed: true))
            ->assertOk()
            ->assertJsonPath('data.0.identity_masked', false)
            ->assertJsonPath('data.0.display_name', 'Barcelona Run Club')
            ->assertJsonPath('data.0.avatar_url', 'https://cdn.test/community.png');
    }

    public function test_the_blur_keeps_what_a_venue_needs_to_judge_the_fit(): void
    {
        $this->community('Barcelona Run Club', [], ['community_size' => 120]);

        $item = $this->list($this->business())->assertOk()->json('data.0');

        $this->assertSame(120, $item['community_size']);
        $this->assertArrayHasKey('community_type', $item);
        $this->assertArrayHasKey('city_name', $item);
        $this->assertArrayHasKey('published_kolabs_count', $item);
    }

    public function test_a_community_with_no_kolab_is_still_listed(): void
    {
        $community = $this->community('Quiet Club');

        $this->list($this->business())
            ->assertOk()
            ->assertJsonPath('data.0.id', $community->id)
            ->assertJsonPath('data.0.published_kolabs_count', 0);
    }

    public function test_the_list_is_paged_not_capped(): void
    {
        foreach (range(1, 25) as $i) {
            $this->community("Club {$i}");
        }

        $viewer = $this->business();

        $this->list($viewer, '?per_page=20')
            ->assertOk()
            ->assertJsonPath('meta.total', 25)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonCount(20, 'data');

        $this->list($viewer, '?per_page=20&page=2')
            ->assertOk()
            ->assertJsonCount(5, 'data');
    }

    public function test_deactivated_and_test_communities_are_left_out_for_a_real_venue(): void
    {
        $this->community('Visible Club');
        $this->community('Switched Off Club', ['is_active' => false]);
        $this->community('App Review Club', ['is_test_user' => true]);

        $this->list($this->business())
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_a_test_business_also_sees_test_communities(): void
    {
        $this->community('Visible Club');
        $this->community('App Review Club', ['is_test_user' => true]);

        $viewer = $this->business();
        $viewer->forceFill(['is_test_user' => true])->save();

        $this->list($viewer->fresh())
            ->assertOk()
            ->assertJsonPath('meta.total', 2);
    }

    public function test_businesses_are_not_listed(): void
    {
        $this->business();
        $this->community('Barcelona Run Club');

        $this->list($this->business())
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_a_community_or_attendee_cannot_call_it(): void
    {
        $this->list($this->community('Barcelona Run Club'))->assertForbidden();
        $this->list(Profile::factory()->attendee()->create())->assertForbidden();
    }

    public function test_a_guest_cannot_call_it(): void
    {
        $this->getJson('/api/v1/discovery/communities')->assertUnauthorized();
    }

    public function test_it_filters_by_community_type(): void
    {
        $this->community('Runners', [], ['community_type' => 'running']);
        $this->community('Readers', [], ['community_type' => 'book_club']);

        $this->list($this->business(), '?community_type=running')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.community_type', 'running');
    }
}
