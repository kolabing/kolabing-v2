<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\FriendshipStatus;
use App\Models\Event;
use App\Models\EventCheckin;
use App\Models\Friendship;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class FriendshipTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_can_send_friend_request(): void
    {
        $me = Profile::factory()->attendee()->create();
        $them = Profile::factory()->attendee()->create();

        $response = $this->actingAs($me)
            ->postJson('/api/v1/friends/requests', ['profile_id' => $them->id]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.direction', 'outgoing')
            ->assertJsonPath('data.profile.id', $them->id);

        $this->assertDatabaseHas('friendships', [
            'requester_profile_id' => $me->id,
            'addressee_profile_id' => $them->id,
            'status' => 'pending',
        ]);
    }

    public function test_cannot_friend_self(): void
    {
        $me = Profile::factory()->attendee()->create();

        $this->actingAs($me)
            ->postJson('/api/v1/friends/requests', ['profile_id' => $me->id])
            ->assertStatus(422)
            ->assertJsonPath('error', 'cannot_friend_self');
    }

    public function test_duplicate_request_is_idempotent(): void
    {
        $me = Profile::factory()->attendee()->create();
        $them = Profile::factory()->attendee()->create();

        $first = $this->actingAs($me)
            ->postJson('/api/v1/friends/requests', ['profile_id' => $them->id])
            ->assertCreated()
            ->json('data.id');

        $second = $this->actingAs($me)
            ->postJson('/api/v1/friends/requests', ['profile_id' => $them->id])
            ->assertCreated()
            ->json('data.id');

        $this->assertSame($first, $second);
        $this->assertSame(1, Friendship::query()->count());
    }

    public function test_reverse_direction_request_resurfaces_existing_row(): void
    {
        $me = Profile::factory()->attendee()->create();
        $them = Profile::factory()->attendee()->create();

        Friendship::factory()->between($them, $me)->create();

        $this->actingAs($me)
            ->postJson('/api/v1/friends/requests', ['profile_id' => $them->id])
            ->assertCreated();

        $this->assertSame(1, Friendship::query()->count());
    }

    public function test_addressee_can_accept_request(): void
    {
        $me = Profile::factory()->attendee()->create();
        $them = Profile::factory()->attendee()->create();
        $friendship = Friendship::factory()->between($them, $me)->create();

        $this->actingAs($me)
            ->postJson("/api/v1/friends/requests/{$friendship->id}/accept")
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted');

        $this->assertSame(FriendshipStatus::Accepted, $friendship->fresh()->status);
        $this->assertNotNull($friendship->fresh()->responded_at);
    }

    public function test_requester_cannot_accept_own_request(): void
    {
        $me = Profile::factory()->attendee()->create();
        $them = Profile::factory()->attendee()->create();
        $friendship = Friendship::factory()->between($me, $them)->create();

        $this->actingAs($me)
            ->postJson("/api/v1/friends/requests/{$friendship->id}/accept")
            ->assertStatus(403)
            ->assertJsonPath('error', 'forbidden');
    }

    public function test_addressee_can_decline_request(): void
    {
        $me = Profile::factory()->attendee()->create();
        $them = Profile::factory()->attendee()->create();
        $friendship = Friendship::factory()->between($them, $me)->create();

        $this->actingAs($me)
            ->postJson("/api/v1/friends/requests/{$friendship->id}/decline")
            ->assertOk();

        $this->assertDatabaseMissing('friendships', ['id' => $friendship->id]);
    }

    public function test_can_remove_accepted_friendship_from_either_side(): void
    {
        $me = Profile::factory()->attendee()->create();
        $them = Profile::factory()->attendee()->create();
        Friendship::factory()->between($them, $me)->accepted()->create();

        $this->actingAs($me)
            ->deleteJson("/api/v1/friends/{$them->id}")
            ->assertOk();

        $this->assertSame(0, Friendship::query()->count());
    }

    public function test_remove_non_friend_returns_404(): void
    {
        $me = Profile::factory()->attendee()->create();
        $them = Profile::factory()->attendee()->create();

        $this->actingAs($me)
            ->deleteJson("/api/v1/friends/{$them->id}")
            ->assertStatus(404)
            ->assertJsonPath('error', 'not_friends');
    }

    public function test_block_prevents_further_requests(): void
    {
        $me = Profile::factory()->attendee()->create();
        $them = Profile::factory()->attendee()->create();

        $this->actingAs($me)
            ->postJson("/api/v1/friends/{$them->id}/block")
            ->assertOk()
            ->assertJsonPath('data.status', 'blocked');

        $this->assertDatabaseHas('friendships', [
            'requester_profile_id' => $me->id,
            'addressee_profile_id' => $them->id,
            'status' => 'blocked',
        ]);

        // The blocked party cannot send a request through the block.
        $this->actingAs($them)
            ->postJson('/api/v1/friends/requests', ['profile_id' => $me->id])
            ->assertStatus(422)
            ->assertJsonPath('error', 'blocked');
    }

    public function test_block_replaces_existing_pending_row(): void
    {
        $me = Profile::factory()->attendee()->create();
        $them = Profile::factory()->attendee()->create();
        Friendship::factory()->between($me, $them)->create();

        $this->actingAs($me)
            ->postJson("/api/v1/friends/{$them->id}/block")
            ->assertOk();

        $this->assertSame(1, Friendship::query()->count());
        $this->assertSame(
            FriendshipStatus::Blocked,
            Friendship::query()->first()->status
        );
    }

    public function test_unblock_lifts_the_block(): void
    {
        $me = Profile::factory()->attendee()->create();
        $them = Profile::factory()->attendee()->create();
        Friendship::factory()->between($me, $them)->blocked()->create();

        $this->actingAs($me)
            ->postJson("/api/v1/friends/{$them->id}/unblock")
            ->assertOk();

        $this->assertSame(0, Friendship::query()->count());
    }

    public function test_friends_list_returns_only_accepted(): void
    {
        $me = Profile::factory()->attendee()->create();
        $friendA = Profile::factory()->attendee()->create();
        $friendB = Profile::factory()->attendee()->create();
        $pending = Profile::factory()->attendee()->create();

        Friendship::factory()->between($me, $friendA)->accepted()->create();
        Friendship::factory()->between($friendB, $me)->accepted()->create();
        Friendship::factory()->between($me, $pending)->create();

        $response = $this->actingAs($me)
            ->getJson('/api/v1/me/friends')
            ->assertOk();

        $this->assertSame(2, $response->json('data.pagination.total_count'));
        $ids = collect($response->json('data.friends'))->pluck('profile.id')->all();
        $this->assertContains($friendA->id, $ids);
        $this->assertContains($friendB->id, $ids);
        $this->assertNotContains($pending->id, $ids);
    }

    public function test_requests_endpoint_splits_incoming_and_sent(): void
    {
        $me = Profile::factory()->attendee()->create();
        $incomingFrom = Profile::factory()->attendee()->create();
        $sentTo = Profile::factory()->attendee()->create();

        Friendship::factory()->between($incomingFrom, $me)->create();
        Friendship::factory()->between($me, $sentTo)->create();

        $response = $this->actingAs($me)
            ->getJson('/api/v1/me/friends/requests')
            ->assertOk();

        $this->assertCount(1, $response->json('data.incoming'));
        $this->assertCount(1, $response->json('data.sent'));
        $this->assertSame($incomingFrom->id, $response->json('data.incoming.0.profile.id'));
        $this->assertSame($sentTo->id, $response->json('data.sent.0.profile.id'));
    }

    public function test_suggested_returns_only_profiles_sharing_at_least_three_events(): void
    {
        $me = Profile::factory()->attendee()->create();
        $strong = Profile::factory()->attendee()->create();   // shares 3 events
        $weak = Profile::factory()->attendee()->create();      // shares 2 events
        $existingFriend = Profile::factory()->attendee()->create(); // shares 3 but already a friend

        $events = Event::factory()->count(3)->create();

        foreach ($events as $event) {
            EventCheckin::factory()->forEvent($event)->forProfile($me)->create();
            EventCheckin::factory()->forEvent($event)->forProfile($strong)->create();
            EventCheckin::factory()->forEvent($event)->forProfile($existingFriend)->create();
        }

        // $weak only shares two of the events.
        EventCheckin::factory()->forEvent($events[0])->forProfile($weak)->create();
        EventCheckin::factory()->forEvent($events[1])->forProfile($weak)->create();

        Friendship::factory()->between($me, $existingFriend)->accepted()->create();

        $response = $this->actingAs($me)
            ->getJson('/api/v1/me/friends/suggested')
            ->assertOk();

        $ids = collect($response->json('data.suggestions'))->pluck('id')->all();

        $this->assertContains($strong->id, $ids);
        $this->assertNotContains($weak->id, $ids);
        $this->assertNotContains($existingFriend->id, $ids);
        $this->assertNotContains($me->id, $ids);
    }

    public function test_suggested_excludes_pending_and_blocked_profiles(): void
    {
        $me = Profile::factory()->attendee()->create();
        $pending = Profile::factory()->attendee()->create();
        $blocked = Profile::factory()->attendee()->create();

        $events = Event::factory()->count(3)->create();

        foreach ($events as $event) {
            EventCheckin::factory()->forEvent($event)->forProfile($me)->create();
            EventCheckin::factory()->forEvent($event)->forProfile($pending)->create();
            EventCheckin::factory()->forEvent($event)->forProfile($blocked)->create();
        }

        Friendship::factory()->between($me, $pending)->create();
        Friendship::factory()->between($me, $blocked)->blocked()->create();

        $response = $this->actingAs($me)
            ->getJson('/api/v1/me/friends/suggested')
            ->assertOk();

        $ids = collect($response->json('data.suggestions'))->pluck('id')->all();

        $this->assertNotContains($pending->id, $ids);
        $this->assertNotContains($blocked->id, $ids);
    }

    public function test_friends_endpoints_require_authentication(): void
    {
        $this->getJson('/api/v1/me/friends')->assertUnauthorized();
    }
}
