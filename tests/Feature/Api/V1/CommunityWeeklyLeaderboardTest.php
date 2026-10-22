<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Event;
use App\Models\EventCheckin;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CommunityWeeklyLeaderboardTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function member(Community $community, ?string $name = null): Profile
    {
        $profile = Profile::factory()->attendee()->create($name !== null ? ['name' => $name] : []);
        CommunityMember::factory()->forCommunity($community)->create(['profile_id' => $profile->id]);

        return $profile;
    }

    private function eventFor(Community $community, Carbon $date): Event
    {
        return Event::factory()->create([
            'community_id' => $community->id,
            'event_date' => $date->toDateString(),
        ]);
    }

    public function test_ranks_active_members_by_this_weeks_checkins_only(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-07 10:00:00', 'Europe/Madrid')); // a Wednesday

        $community = Community::factory()->create();
        $memberA = $this->member($community);
        $memberB = $this->member($community);
        $memberC = $this->member($community); // no check-ins this week

        $eventThisWeek1 = $this->eventFor($community, Carbon::parse('2026-10-05'));
        $eventThisWeek2 = $this->eventFor($community, Carbon::parse('2026-10-06'));
        $eventLastWeek = $this->eventFor($community, Carbon::parse('2026-09-28'));

        // memberA: 2 check-ins this week across two different events.
        EventCheckin::factory()->forEvent($eventThisWeek1)->forProfile($memberA)
            ->create(['checked_in_at' => Carbon::parse('2026-10-05 12:00:00')]);
        EventCheckin::factory()->forEvent($eventThisWeek2)->forProfile($memberA)
            ->create(['checked_in_at' => Carbon::parse('2026-10-06 12:00:00')]);

        // memberB: 1 check-in this week, plus an old one from last week that
        // must not count.
        EventCheckin::factory()->forEvent($eventThisWeek1)->forProfile($memberB)
            ->create(['checked_in_at' => Carbon::parse('2026-10-05 09:00:00')]);
        EventCheckin::factory()->forEvent($eventLastWeek)->forProfile($memberB)
            ->create(['checked_in_at' => Carbon::parse('2026-09-28 09:00:00')]);

        $response = $this->actingAs($memberA)
            ->getJson("/api/v1/communities/{$community->id}/leaderboard/weekly");

        $response->assertOk()
            ->assertJsonCount(2, 'data.leaderboard')
            ->assertJsonPath('data.leaderboard.0.profile_id', $memberA->id)
            ->assertJsonPath('data.leaderboard.0.checkins', 2)
            ->assertJsonPath('data.leaderboard.0.rank', 1)
            ->assertJsonPath('data.leaderboard.1.profile_id', $memberB->id)
            ->assertJsonPath('data.leaderboard.1.checkins', 1)
            ->assertJsonPath('data.leaderboard.1.rank', 2)
            ->assertJsonPath('data.my_rank.checkins', 2)
            ->assertJsonPath('data.my_rank.rank', 1);

        $ids = array_column($response->json('data.leaderboard'), 'profile_id');
        $this->assertNotContains($memberC->id, $ids);

        Carbon::setTestNow();
    }

    public function test_repeated_checkins_at_the_same_event_count_once(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-07 10:00:00', 'Europe/Madrid'));

        $community = Community::factory()->create();
        $member = $this->member($community);
        $event = $this->eventFor($community, Carbon::parse('2026-10-05'));

        // A unique(event_id, profile_id) constraint means one check-in row,
        // but this proves the count is DISTINCT event_id, not row count,
        // should a second verified touch ever be recorded for the same event.
        EventCheckin::factory()->forEvent($event)->forProfile($member)
            ->create(['checked_in_at' => Carbon::parse('2026-10-05 12:00:00')]);

        $this->actingAs($member)
            ->getJson("/api/v1/communities/{$community->id}/leaderboard/weekly")
            ->assertOk()
            ->assertJsonPath('data.leaderboard.0.checkins', 1);

        Carbon::setTestNow();
    }

    public function test_non_member_cannot_read_the_weekly_leaderboard(): void
    {
        $community = Community::factory()->create();
        $member = $this->member($community);
        $outsider = Profile::factory()->attendee()->create();

        $this->actingAs($outsider)
            ->getJson("/api/v1/communities/{$community->id}/leaderboard/weekly")
            ->assertForbidden();

        $this->actingAs($community->owner)
            ->getJson("/api/v1/communities/{$community->id}/leaderboard/weekly")
            ->assertOk();
    }

    public function test_empty_week_returns_an_empty_board_not_an_error(): void
    {
        $community = Community::factory()->create();
        $member = $this->member($community);

        $this->actingAs($member)
            ->getJson("/api/v1/communities/{$community->id}/leaderboard/weekly")
            ->assertOk()
            ->assertJsonCount(0, 'data.leaderboard')
            ->assertJsonPath('data.my_rank', null);
    }

    public function test_display_name_never_leaks_email(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-07 10:00:00', 'Europe/Madrid'));

        $community = Community::factory()->create();
        $hidden = Profile::factory()->attendee()->create(['name' => null, 'email' => 'hidden.name@example.com']);
        CommunityMember::factory()->forCommunity($community)->create(['profile_id' => $hidden->id]);
        $event = $this->eventFor($community, Carbon::parse('2026-10-05'));
        EventCheckin::factory()->forEvent($event)->forProfile($hidden)
            ->create(['checked_in_at' => Carbon::parse('2026-10-05 12:00:00')]);

        $response = $this->actingAs($hidden)
            ->getJson("/api/v1/communities/{$community->id}/leaderboard/weekly")
            ->assertOk()
            ->assertJsonPath('data.leaderboard.0.display_name', 'hidden.name');
        $this->assertStringNotContainsString('@', $response->getContent());

        Carbon::setTestNow();
    }
}
