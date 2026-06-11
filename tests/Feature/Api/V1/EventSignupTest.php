<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\EventSignupStatus;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\CommunityTier;
use App\Models\Event;
use App\Models\EventSignup;
use App\Models\Profile;
use App\Services\CommunityService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class EventSignupTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function community(Profile $leader): Community
    {
        return app(CommunityService::class)->create($leader, [
            'name' => 'Barcelona Run Club',
            'type' => 'greek',
        ]);
    }

    private function upcomingEvent(Community $community, array $overrides = []): Event
    {
        return Event::factory()->create(array_merge([
            'profile_id' => $community->owner_profile_id,
            'community_id' => $community->id,
            'starts_at' => now()->addDays(2),
            'ends_at' => now()->addDays(2)->addHours(3),
        ], $overrides));
    }

    private function member(Community $community, ?string $tierId = null): Profile
    {
        $profile = Profile::factory()->attendee()->create();
        CommunityMember::factory()->forCommunity($community)->create([
            'profile_id' => $profile->id,
            'tier_id' => $tierId ?? $community->defaultTier->id,
            'status' => 'active',
        ]);

        return $profile;
    }

    public function test_member_can_sign_up_to_upcoming_event(): void
    {
        $leader = Profile::factory()->community()->create();
        $community = $this->community($leader);
        $event = $this->upcomingEvent($community);
        $member = $this->member($community);

        $this->actingAs($member)
            ->postJson("/api/v1/events/{$event->id}/signup")
            ->assertStatus(200)
            ->assertJsonPath('data.my_signup.status', 'going')
            ->assertJsonPath('data.going_count', 1);
    }

    public function test_over_capacity_goes_to_waitlist(): void
    {
        $leader = Profile::factory()->community()->create();
        $community = $this->community($leader);
        $event = $this->upcomingEvent($community, ['capacity' => 1]);

        $a = $this->member($community);
        $b = $this->member($community);

        $this->actingAs($a)->postJson("/api/v1/events/{$event->id}/signup")
            ->assertStatus(200)->assertJsonPath('data.my_signup.status', 'going');

        $this->actingAs($b)->postJson("/api/v1/events/{$event->id}/signup")
            ->assertStatus(200)
            ->assertJsonPath('data.my_signup.status', 'waitlisted')
            ->assertJsonPath('data.my_signup.waitlist_position', 1)
            ->assertJsonPath('data.going_count', 1)
            ->assertJsonPath('data.waitlist_count', 1);
    }

    public function test_cancel_auto_promotes_head_of_waitlist_and_notifies(): void
    {
        $leader = Profile::factory()->community()->create();
        $community = $this->community($leader);
        $event = $this->upcomingEvent($community, ['capacity' => 1]);

        $a = $this->member($community);
        $b = $this->member($community);

        $this->actingAs($a)->postJson("/api/v1/events/{$event->id}/signup")->assertStatus(200);
        $this->actingAs($b)->postJson("/api/v1/events/{$event->id}/signup")->assertStatus(200);

        // A leaves → B (head of waitlist) is promoted to going.
        $this->actingAs($a)->deleteJson("/api/v1/events/{$event->id}/signup")->assertStatus(200);

        $this->assertTrue(
            EventSignup::query()->where('event_id', $event->id)->where('profile_id', $b->id)
                ->where('status', EventSignupStatus::Going->value)->exists()
        );

        $this->assertDatabaseHas('notifications', [
            'profile_id' => $b->id,
            'type' => 'waitlist_promoted',
        ]);
    }

    public function test_tier_gated_event_blocks_other_tiers(): void
    {
        $leader = Profile::factory()->community()->create();
        $community = $this->community($leader);
        $gatedTier = CommunityTier::factory()->forCommunity($community)->create(['rank' => 3]);
        $event = $this->upcomingEvent($community, ['tier_gate' => [$gatedTier->id]]);

        $wrongTier = $this->member($community, $community->defaultTier->id);
        $this->actingAs($wrongTier)->postJson("/api/v1/events/{$event->id}/signup")
            ->assertStatus(422)->assertJsonPath('error', 'tier_not_permitted');

        $allowed = $this->member($community, $gatedTier->id);
        $this->actingAs($allowed)->postJson("/api/v1/events/{$event->id}/signup")
            ->assertStatus(200)->assertJsonPath('data.my_signup.status', 'going');
    }

    public function test_non_member_cannot_sign_up(): void
    {
        $leader = Profile::factory()->community()->create();
        $community = $this->community($leader);
        $event = $this->upcomingEvent($community);
        $outsider = Profile::factory()->attendee()->create();

        $this->actingAs($outsider)->postJson("/api/v1/events/{$event->id}/signup")
            ->assertStatus(422)->assertJsonPath('error', 'not_a_member');
    }

    public function test_non_member_can_sign_up_to_public_event(): void
    {
        $leader = Profile::factory()->community()->create();
        $community = $this->community($leader);
        $event = $this->upcomingEvent($community, ['visibility' => 'public']);
        $outsider = Profile::factory()->attendee()->create();

        // Public events are open to everyone — no membership required.
        $this->actingAs($outsider)->postJson("/api/v1/events/{$event->id}/signup")
            ->assertStatus(200)->assertJsonPath('data.my_signup.status', 'going');
    }

    public function test_cannot_sign_up_to_past_event(): void
    {
        $leader = Profile::factory()->community()->create();
        $community = $this->community($leader);
        $past = Event::factory()->create([
            'profile_id' => $leader->id,
            'community_id' => $community->id,
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->subDays(2)->addHours(2),
        ]);
        $member = $this->member($community);

        $this->actingAs($member)->postJson("/api/v1/events/{$past->id}/signup")
            ->assertStatus(422)->assertJsonPath('error', 'event_not_upcoming');
    }

    public function test_leader_lists_going_and_waitlist(): void
    {
        $leader = Profile::factory()->community()->create();
        $community = $this->community($leader);
        $event = $this->upcomingEvent($community, ['capacity' => 1]);

        $a = $this->member($community);
        $b = $this->member($community);
        $this->actingAs($a)->postJson("/api/v1/events/{$event->id}/signup")->assertStatus(200);
        $this->actingAs($b)->postJson("/api/v1/events/{$event->id}/signup")->assertStatus(200);

        $this->actingAs($leader)->getJson("/api/v1/events/{$event->id}/signups")
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.going')
            ->assertJsonCount(1, 'data.waitlist')
            ->assertJsonPath('data.going.0.profile_id', $a->id)
            ->assertJsonPath('data.waitlist.0.profile_id', $b->id);

        // A non-manager member cannot view the roster.
        $this->actingAs($a)->getJson("/api/v1/events/{$event->id}/signups")->assertStatus(403);
    }
}
