<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\EventSignupStatus;
use App\Enums\EventVisibility;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Event;
use App\Models\Profile;
use App\Services\CommunityService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * PUBLIC EVENTS lane (Batch 3): visibility column + attendee discovery feed +
 * public vs members_only RSVP rules.
 */
class PublicEventTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function community(?Profile $leader = null): Community
    {
        $leader ??= Profile::factory()->community()->create();

        return app(CommunityService::class)->create($leader, [
            'name' => 'Barcelona Run Club',
            'type' => 'greek',
        ]);
    }

    private function upcomingEvent(Community $community, array $overrides = []): Event
    {
        return Event::factory()->upcoming()->create(array_merge([
            'profile_id' => $community->owner_profile_id,
            'community_id' => $community->id,
        ], $overrides));
    }

    private function member(Community $community): Profile
    {
        $profile = Profile::factory()->attendee()->create();
        CommunityMember::factory()->forCommunity($community)->create([
            'profile_id' => $profile->id,
            'tier_id' => $community->defaultTier->id,
            'status' => 'active',
        ]);

        return $profile;
    }

    public function test_visibility_defaults_to_members_only(): void
    {
        $community = $this->community();
        $event = $this->upcomingEvent($community);

        $this->assertSame(EventVisibility::MembersOnly, $event->fresh()->visibility);
    }

    public function test_visibility_persists_when_set_public(): void
    {
        $community = $this->community();
        $event = $this->upcomingEvent($community, ['visibility' => EventVisibility::Public->value]);

        $this->assertSame(EventVisibility::Public, $event->fresh()->visibility);
        $this->assertTrue($event->fresh()->isPublic());
    }

    public function test_discovery_returns_only_public_upcoming_events(): void
    {
        $community = $this->community();

        $public = $this->upcomingEvent($community, ['visibility' => EventVisibility::Public->value, 'name' => 'Public Run']);
        // members_only upcoming — excluded.
        $this->upcomingEvent($community, ['visibility' => EventVisibility::MembersOnly->value, 'name' => 'Members Run']);
        // public but PAST — excluded.
        Event::factory()->publicVisibility()->create([
            'profile_id' => $community->owner_profile_id,
            'community_id' => $community->id,
            'starts_at' => now()->subDays(3),
            'ends_at' => now()->subDays(3)->addHours(2),
            'name' => 'Past Public',
        ]);

        $attendee = Profile::factory()->attendee()->create();

        $response = $this->actingAs($attendee)
            ->getJson('/api/v1/events/discovery')
            ->assertOk()
            ->assertJsonPath('data.pagination.total_count', 1)
            ->assertJsonCount(1, 'data.events')
            ->assertJsonPath('data.events.0.id', $public->id)
            ->assertJsonPath('data.events.0.visibility', 'public')
            ->assertJsonPath('data.events.0.community.id', $community->id)
            ->assertJsonPath('data.events.0.going_count', 0);

        $this->assertSame('Barcelona Run Club', $response->json('data.events.0.community.name'));
    }

    public function test_public_event_rsvp_works_for_non_member_attendee(): void
    {
        $community = $this->community();
        $event = $this->upcomingEvent($community, ['visibility' => EventVisibility::Public->value]);
        $outsider = Profile::factory()->attendee()->create();

        $this->actingAs($outsider)
            ->postJson("/api/v1/events/{$event->id}/signup")
            ->assertOk()
            ->assertJsonPath('data.my_signup.status', 'going')
            ->assertJsonPath('data.going_count', 1);

        $this->assertDatabaseHas('event_signups', [
            'event_id' => $event->id,
            'profile_id' => $outsider->id,
            'status' => EventSignupStatus::Going->value,
        ]);
    }

    public function test_members_only_event_rejects_non_member_with_403(): void
    {
        $community = $this->community();
        $event = $this->upcomingEvent($community, ['visibility' => EventVisibility::MembersOnly->value]);
        $outsider = Profile::factory()->attendee()->create();

        $this->actingAs($outsider)
            ->postJson("/api/v1/events/{$event->id}/signup")
            ->assertStatus(403)
            ->assertJsonPath('error', 'community_membership_required');

        $this->assertDatabaseMissing('event_signups', [
            'event_id' => $event->id,
            'profile_id' => $outsider->id,
        ]);
    }

    public function test_members_only_event_allows_active_member(): void
    {
        $community = $this->community();
        $event = $this->upcomingEvent($community, ['visibility' => EventVisibility::MembersOnly->value]);
        $member = $this->member($community);

        $this->actingAs($member)
            ->postJson("/api/v1/events/{$event->id}/signup")
            ->assertOk()
            ->assertJsonPath('data.my_signup.status', 'going');
    }
}
