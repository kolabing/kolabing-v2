<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\CommunityTier;
use App\Models\Event;
use App\Models\Profile;
use App\Services\CommunityService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CreateUpcomingEventTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function community(Profile $leader): Community
    {
        return app(CommunityService::class)->create($leader, [
            'name' => 'Barcelona Run Club',
            'type' => 'greek',
        ]);
    }

    public function test_leader_creates_upcoming_event_without_photos(): void
    {
        $leader = Profile::factory()->community()->create();
        $community = $this->community($leader);

        $response = $this->actingAs($leader)->postJson('/api/v1/events', [
            'community_id' => $community->id,
            'name' => 'Saturday Long Run',
            'starts_at' => now()->addDays(3)->toIso8601String(),
            'ends_at' => now()->addDays(3)->addHours(2)->toIso8601String(),
            'location' => 'Parc de la Ciutadella',
            'capacity' => 20,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.is_upcoming', true)
            ->assertJsonPath('data.community_id', $community->id)
            ->assertJsonPath('data.capacity', 20)
            ->assertJsonPath('data.going_count', 0);

        $eventId = $response->json('data.id');

        // Appears in the upcoming list for the community.
        $this->actingAs($leader)
            ->getJson("/api/v1/events?community_id={$community->id}&time=upcoming")
            ->assertStatus(200)
            ->assertJsonPath('data.events.0.id', $eventId);
    }

    public function test_upcoming_event_can_be_tier_gated(): void
    {
        $leader = Profile::factory()->community()->create();
        $community = $this->community($leader);
        $tier = CommunityTier::factory()->forCommunity($community)->create(['rank' => 3]);

        $response = $this->actingAs($leader)->postJson('/api/v1/events', [
            'community_id' => $community->id,
            'name' => 'VIP Track Day',
            'starts_at' => now()->addDays(5)->toIso8601String(),
            'tier_gate' => [$tier->id],
        ])->assertStatus(201)->assertJsonPath('data.tier_gate', [$tier->id]);

        // A member not on the gated tier cannot sign up (existing signup logic).
        $eventId = $response->json('data.id');
        $outsiderTierMember = Profile::factory()->attendee()->create();
        CommunityMember::factory()->forCommunity($community)->create([
            'profile_id' => $outsiderTierMember->id,
            'tier_id' => $community->defaultTier->id,
            'status' => 'active',
        ]);

        $this->actingAs($outsiderTierMember)
            ->postJson("/api/v1/events/{$eventId}/signup")
            ->assertStatus(422)->assertJsonPath('error', 'tier_not_permitted');
    }

    public function test_non_manager_cannot_create_upcoming_event(): void
    {
        $leader = Profile::factory()->community()->create();
        $community = $this->community($leader);
        $outsider = Profile::factory()->community()->create();

        $this->actingAs($outsider)->postJson('/api/v1/events', [
            'community_id' => $community->id,
            'name' => 'Sneaky Event',
            'starts_at' => now()->addDays(2)->toIso8601String(),
        ])->assertStatus(403);
    }

    // Legacy retroactive past-event create (photos + date<=today) is covered by
    // EventTest::test_create_event_with_valid_data — kept green by this change.
}
