<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\CommunityMemberStatus;
use App\Enums\GamificationBadgeSlug;
use App\Models\AttendeeProfile;
use App\Models\BusinessProfile;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\CommunityTier;
use App\Models\EarnedBadge;
use App\Models\Event;
use App\Models\EventCheckin;
use App\Models\Profile;
use App\Models\XpLevel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AttendeePublicProfileTest extends TestCase
{
    use LazilyRefreshDatabase;

    /*
    |--------------------------------------------------------------------------
    | Attendee aggregate (GET /api/v1/profiles/{profile}/attendee)
    |--------------------------------------------------------------------------
    */

    public function test_aggregate_returns_expected_keys(): void
    {
        $viewer = Profile::factory()->attendee()->create();
        $attendee = Profile::factory()->attendee()->create();
        AttendeeProfile::factory()->create([
            'profile_id' => $attendee->id,
            'total_points' => 120,
            'total_events_attended' => 2,
        ]);

        $response = $this->actingAs($viewer)
            ->getJson("/api/v1/profiles/{$attendee->id}/attendee");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'identity' => ['id', 'name', 'avatar_url'],
                    'gamification' => [
                        'points',
                        'level',
                        'badges' => ['count', 'items'],
                    ],
                    'communities',
                    'events_attended' => ['total', 'recent'],
                    'friends_count',
                ],
            ])
            ->assertJsonPath('data.identity.id', $attendee->id)
            ->assertJsonPath('data.gamification.points', 120);
    }

    public function test_aggregate_includes_checkins_badges_and_membership(): void
    {
        $attendee = Profile::factory()->attendee()->create();
        AttendeeProfile::factory()->create([
            'profile_id' => $attendee->id,
            'total_points' => 80,
            'total_events_attended' => 2,
        ]);

        // Two badges.
        EarnedBadge::factory()->forProfile($attendee)->slug(GamificationBadgeSlug::FirstKolab)->create();
        EarnedBadge::factory()->forProfile($attendee)->slug(GamificationBadgeSlug::ContentCreator)->create();

        // A community membership with a tier and can_manage.
        $community = Community::factory()->create(['name' => 'Sunrise Runners']);
        $tier = CommunityTier::factory()->forCommunity($community)->create(['name' => 'Active']);
        CommunityMember::factory()->forCommunity($community)->create([
            'profile_id' => $attendee->id,
            'tier_id' => $tier->id,
            'can_manage' => true,
            'status' => CommunityMemberStatus::Active->value,
        ]);

        // Two check-ins, one linked to a community event.
        $event1 = Event::factory()->create(['name' => 'Morning 5K', 'community_id' => $community->id]);
        $event2 = Event::factory()->create(['name' => 'Park Yoga']);
        EventCheckin::factory()->forEvent($event1)->forProfile($attendee)->create(['checked_in_at' => now()->subDay()]);
        EventCheckin::factory()->forEvent($event2)->forProfile($attendee)->create(['checked_in_at' => now()]);

        $response = $this->actingAs($attendee)
            ->getJson("/api/v1/profiles/{$attendee->id}/attendee");

        $response->assertStatus(200)
            ->assertJsonPath('data.gamification.badges.count', 2)
            ->assertJsonPath('data.events_attended.total', 2)
            ->assertJsonCount(2, 'data.events_attended.recent')
            ->assertJsonCount(1, 'data.communities')
            ->assertJsonPath('data.communities.0.name', 'Sunrise Runners')
            ->assertJsonPath('data.communities.0.tier_name', 'Active')
            ->assertJsonPath('data.communities.0.can_manage', true);

        // Most recent check-in is first; its event carries the community summary.
        $response->assertJsonPath('data.events_attended.recent.0.event_name', 'Park Yoga')
            ->assertJsonPath('data.events_attended.recent.1.event_name', 'Morning 5K')
            ->assertJsonPath('data.events_attended.recent.1.community.name', 'Sunrise Runners')
            ->assertJsonPath('data.events_attended.recent.0.community', null);
    }

    public function test_aggregate_omits_inactive_memberships(): void
    {
        $attendee = Profile::factory()->attendee()->create();
        AttendeeProfile::factory()->create(['profile_id' => $attendee->id]);

        $community = Community::factory()->create();
        CommunityMember::factory()->forCommunity($community)->create([
            'profile_id' => $attendee->id,
            'status' => CommunityMemberStatus::Removed->value,
        ]);

        $this->actingAs($attendee)
            ->getJson("/api/v1/profiles/{$attendee->id}/attendee")
            ->assertStatus(200)
            ->assertJsonCount(0, 'data.communities');
    }

    public function test_aggregate_resolves_level_from_xp_levels_when_configured(): void
    {
        $this->seedLevels();

        $attendee = Profile::factory()->attendee()->create();
        AttendeeProfile::factory()->create([
            'profile_id' => $attendee->id,
            'total_points' => 150,
        ]);

        $this->actingAs($attendee)
            ->getJson("/api/v1/profiles/{$attendee->id}/attendee")
            ->assertStatus(200)
            ->assertJsonPath('data.gamification.level.number', 2)
            ->assertJsonPath('data.gamification.level.title', 'Climber');
    }

    public function test_aggregate_level_is_null_when_no_level_rule_exists(): void
    {
        $attendee = Profile::factory()->attendee()->create();
        AttendeeProfile::factory()->create([
            'profile_id' => $attendee->id,
            'total_points' => 150,
        ]);

        $this->actingAs($attendee)
            ->getJson("/api/v1/profiles/{$attendee->id}/attendee")
            ->assertStatus(200)
            ->assertJsonPath('data.gamification.level', null)
            ->assertJsonPath('data.gamification.points', 150);
    }

    public function test_friends_count_reflects_accepted_friendships(): void
    {
        // With the friendships table integrated, friends_count is a real count
        // (0 when the attendee has no accepted friendships) rather than null.
        $attendee = Profile::factory()->attendee()->create();
        AttendeeProfile::factory()->create(['profile_id' => $attendee->id]);

        $this->actingAs($attendee)
            ->getJson("/api/v1/profiles/{$attendee->id}/attendee")
            ->assertStatus(200)
            ->assertJsonPath('data.friends_count', 0);
    }

    public function test_aggregate_for_non_attendee_returns_empty_gamification(): void
    {
        $viewer = Profile::factory()->attendee()->create();
        $business = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $business->id]);

        $this->actingAs($viewer)
            ->getJson("/api/v1/profiles/{$business->id}/attendee")
            ->assertStatus(200)
            ->assertJsonPath('data.gamification.points', 0)
            ->assertJsonPath('data.gamification.badges.count', 0)
            ->assertJsonPath('data.events_attended.total', 0)
            ->assertJsonCount(0, 'data.communities');
    }

    public function test_aggregate_returns_404_for_unknown_profile(): void
    {
        $viewer = Profile::factory()->attendee()->create();

        $this->actingAs($viewer)
            ->getJson('/api/v1/profiles/00000000-0000-0000-0000-000000000000/attendee')
            ->assertStatus(404);
    }

    public function test_aggregate_requires_authentication(): void
    {
        $attendee = Profile::factory()->attendee()->create();

        $this->getJson("/api/v1/profiles/{$attendee->id}/attendee")
            ->assertStatus(401);
    }

    /*
    |--------------------------------------------------------------------------
    | Events attended history (GET /api/v1/me/events-attended)
    |--------------------------------------------------------------------------
    */

    public function test_events_attended_returns_history_ordered_desc(): void
    {
        $attendee = Profile::factory()->attendee()->create();
        AttendeeProfile::factory()->create(['profile_id' => $attendee->id]);

        $community = Community::factory()->create(['name' => 'Trail Club']);
        $older = Event::factory()->create(['name' => 'Old Hike']);
        $newer = Event::factory()->create(['name' => 'New Hike', 'community_id' => $community->id]);

        EventCheckin::factory()->forEvent($older)->forProfile($attendee)->create(['checked_in_at' => now()->subWeek()]);
        EventCheckin::factory()->forEvent($newer)->forProfile($attendee)->create(['checked_in_at' => now()]);

        $response = $this->actingAs($attendee)
            ->getJson('/api/v1/me/events-attended');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.event_name', 'New Hike')
            ->assertJsonPath('data.0.community.name', 'Trail Club')
            ->assertJsonPath('data.1.event_name', 'Old Hike')
            ->assertJsonPath('data.1.community', null);
    }

    public function test_events_attended_is_paginated(): void
    {
        $attendee = Profile::factory()->attendee()->create();
        AttendeeProfile::factory()->create(['profile_id' => $attendee->id]);

        foreach (range(1, 3) as $i) {
            $event = Event::factory()->create();
            EventCheckin::factory()->forEvent($event)->forProfile($attendee)
                ->create(['checked_in_at' => now()->subDays($i)]);
        }

        $response = $this->actingAs($attendee)
            ->getJson('/api/v1/me/events-attended?per_page=2');

        $response->assertStatus(200)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonCount(2, 'data');
    }

    public function test_events_attended_only_returns_own_checkins(): void
    {
        $attendee = Profile::factory()->attendee()->create();
        AttendeeProfile::factory()->create(['profile_id' => $attendee->id]);
        $other = Profile::factory()->attendee()->create();

        $event = Event::factory()->create();
        EventCheckin::factory()->forEvent($event)->forProfile($attendee)->create();
        EventCheckin::factory()->forEvent($event)->forProfile($other)->create();

        $this->actingAs($attendee)
            ->getJson('/api/v1/me/events-attended')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_events_attended_requires_authentication(): void
    {
        $this->getJson('/api/v1/me/events-attended')
            ->assertStatus(401);
    }

    private function seedLevels(): void
    {
        XpLevel::create([
            'number' => 1, 'title' => 'Rookie', 'min_xp' => 0, 'max_xp' => 99, 'color' => '#cccccc', 'position' => 1,
        ]);
        XpLevel::create([
            'number' => 2, 'title' => 'Climber', 'min_xp' => 100, 'max_xp' => 299, 'color' => '#33aa33', 'position' => 2,
        ]);
        XpLevel::create([
            'number' => 3, 'title' => 'Champion', 'min_xp' => 300, 'max_xp' => null, 'color' => '#ffaa00', 'position' => 3,
        ]);
    }
}
