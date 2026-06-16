<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\FriendshipStatus;
use App\Models\Friendship;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class FriendshipTest extends TestCase
{
    use LazilyRefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | Auth
    |--------------------------------------------------------------------------
    */

    public function test_send_request_requires_authentication(): void
    {
        $other = Profile::factory()->attendee()->create();

        $this->postJson("/api/v1/friends/{$other->id}")->assertStatus(401);
    }

    /*
    |--------------------------------------------------------------------------
    | Request -> accept -> list
    |--------------------------------------------------------------------------
    */

    public function test_request_then_accept_then_list(): void
    {
        $me = Profile::factory()->attendee()->create();
        $other = Profile::factory()->attendee()->create();

        // me -> other
        $this->actingAs($me)
            ->postJson("/api/v1/friends/{$other->id}")
            ->assertStatus(201)
            ->assertJsonPath('data.friend_status', 'pending_outgoing');

        $this->assertDatabaseHas('friendships', [
            'requester_profile_id' => $me->id,
            'addressee_profile_id' => $other->id,
            'status' => FriendshipStatus::Pending->value,
        ]);

        // other sees an incoming request
        $this->actingAs($other)
            ->getJson('/api/v1/me/friend-requests')
            ->assertStatus(200)
            ->assertJsonPath('count', 1)
            ->assertJsonPath('data.0.profile_id', $me->id);

        // other accepts
        $this->actingAs($other)
            ->postJson("/api/v1/friends/{$me->id}/accept")
            ->assertStatus(200)
            ->assertJsonPath('data.friend_status', 'friends');

        // both list each other as friends
        $this->actingAs($me)
            ->getJson('/api/v1/me/friends')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.profile_id', $other->id);

        $this->actingAs($other)
            ->getJson('/api/v1/me/friends')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.profile_id', $me->id);
    }

    /*
    |--------------------------------------------------------------------------
    | Decline
    |--------------------------------------------------------------------------
    */

    public function test_decline_removes_request(): void
    {
        $me = Profile::factory()->attendee()->create();
        $other = Profile::factory()->attendee()->create();

        $this->actingAs($me)->postJson("/api/v1/friends/{$other->id}")->assertStatus(201);

        $this->actingAs($other)
            ->postJson("/api/v1/friends/{$me->id}/decline")
            ->assertStatus(200)
            ->assertJsonPath('data.friend_status', 'none');

        $this->assertDatabaseMissing('friendships', [
            'requester_profile_id' => $me->id,
            'addressee_profile_id' => $other->id,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Remove / cancel
    |--------------------------------------------------------------------------
    */

    public function test_remove_friend(): void
    {
        $me = Profile::factory()->attendee()->create();
        $other = Profile::factory()->attendee()->create();

        Friendship::factory()->accepted()->create([
            'requester_profile_id' => $me->id,
            'addressee_profile_id' => $other->id,
        ]);

        $this->actingAs($me)
            ->deleteJson("/api/v1/friends/{$other->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.friend_status', 'none');

        $this->assertDatabaseMissing('friendships', [
            'requester_profile_id' => $me->id,
            'addressee_profile_id' => $other->id,
        ]);
    }

    public function test_cancel_outgoing_request(): void
    {
        $me = Profile::factory()->attendee()->create();
        $other = Profile::factory()->attendee()->create();

        $this->actingAs($me)->postJson("/api/v1/friends/{$other->id}")->assertStatus(201);

        // DELETE cancels my outgoing pending request
        $this->actingAs($me)
            ->deleteJson("/api/v1/friends/{$other->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.friend_status', 'none');

        $this->assertDatabaseCount('friendships', 0);
    }

    /*
    |--------------------------------------------------------------------------
    | Error codes
    |--------------------------------------------------------------------------
    */

    public function test_cannot_friend_self(): void
    {
        $me = Profile::factory()->attendee()->create();

        $this->actingAs($me)
            ->postJson("/api/v1/friends/{$me->id}")
            ->assertStatus(422)
            ->assertJsonPath('error', 'cannot_friend_self');
    }

    public function test_already_friends_returns_422(): void
    {
        $me = Profile::factory()->attendee()->create();
        $other = Profile::factory()->attendee()->create();

        Friendship::factory()->accepted()->create([
            'requester_profile_id' => $me->id,
            'addressee_profile_id' => $other->id,
        ]);

        $this->actingAs($me)
            ->postJson("/api/v1/friends/{$other->id}")
            ->assertStatus(422)
            ->assertJsonPath('error', 'already_friends');
    }

    public function test_duplicate_request_returns_422(): void
    {
        $me = Profile::factory()->attendee()->create();
        $other = Profile::factory()->attendee()->create();

        $this->actingAs($me)->postJson("/api/v1/friends/{$other->id}")->assertStatus(201);

        $this->actingAs($me)
            ->postJson("/api/v1/friends/{$other->id}")
            ->assertStatus(422)
            ->assertJsonPath('error', 'request_exists');
    }

    /*
    |--------------------------------------------------------------------------
    | Mutual auto-accept
    |--------------------------------------------------------------------------
    */

    public function test_mutual_request_auto_accepts(): void
    {
        $a = Profile::factory()->attendee()->create();
        $b = Profile::factory()->attendee()->create();

        // a -> b (pending)
        $this->actingAs($a)->postJson("/api/v1/friends/{$b->id}")->assertStatus(201);

        // b -> a should auto-accept (reverse pending exists)
        $this->actingAs($b)
            ->postJson("/api/v1/friends/{$a->id}")
            ->assertStatus(201)
            ->assertJsonPath('data.friend_status', 'friends')
            ->assertJsonPath('data.accepted', true);

        // exactly one row, accepted
        $this->assertDatabaseCount('friendships', 1);
        $this->assertDatabaseHas('friendships', [
            'requester_profile_id' => $a->id,
            'addressee_profile_id' => $b->id,
            'status' => FriendshipStatus::Accepted->value,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | friend_status transitions on the public profile payload
    |--------------------------------------------------------------------------
    */

    public function test_friend_status_transitions_on_public_profile(): void
    {
        $me = Profile::factory()->attendee()->create();
        $other = Profile::factory()->attendee()->create();

        // none
        $this->actingAs($me)
            ->getJson("/api/v1/profiles/{$other->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.friend_status', 'none')
            ->assertJsonPath('data.friends_count', 0);

        // pending_outgoing (me viewing other)
        $this->actingAs($me)->postJson("/api/v1/friends/{$other->id}")->assertStatus(201);
        $this->actingAs($me)
            ->getJson("/api/v1/profiles/{$other->id}")
            ->assertJsonPath('data.friend_status', 'pending_outgoing');

        // pending_incoming (other viewing me)
        $this->actingAs($other)
            ->getJson("/api/v1/profiles/{$me->id}")
            ->assertJsonPath('data.friend_status', 'pending_incoming');

        // friends, with friends_count = 1
        $this->actingAs($other)->postJson("/api/v1/friends/{$me->id}/accept")->assertStatus(200);
        $this->actingAs($me)
            ->getJson("/api/v1/profiles/{$other->id}")
            ->assertJsonPath('data.friend_status', 'friends')
            ->assertJsonPath('data.friends_count', 1);
    }

    public function test_friend_status_is_self_on_own_profile(): void
    {
        $me = Profile::factory()->attendee()->create();

        $this->actingAs($me)
            ->getJson("/api/v1/profiles/{$me->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.friend_status', 'self');
    }
}
