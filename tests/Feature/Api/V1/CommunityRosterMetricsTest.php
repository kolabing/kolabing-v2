<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\CommunityPointLedger;
use App\Models\CommunityPoints;
use App\Models\Event;
use App\Models\EventCheckin;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CommunityRosterMetricsTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function ledgerAt(string $communityId, string $profileId, \DateTimeInterface $when): void
    {
        $row = CommunityPointLedger::query()->create([
            'community_id' => $communityId,
            'profile_id' => $profileId,
            'points' => 10,
            'source' => 'event_check_in',
        ]);

        // created_at is not fillable; assign it directly.
        $row->created_at = $when;
        $row->save();
    }

    public function test_roster_reports_points_events_attended_last_active_and_tenure(): void
    {
        $community = Community::factory()->create();
        $profile = Profile::factory()->attendee()->create(['name' => 'Ada']);
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'profile_id' => $profile->id,
            'joined_at' => now()->subDays(10),
        ]);

        CommunityPoints::query()->create([
            'community_id' => $community->id,
            'profile_id' => $profile->id,
            'points' => 340,
        ]);

        // Two check-ins on THIS community's events, one on an unrelated event —
        // only the community's own events count (ROLES §8.6).
        $ours = Event::factory()->create(['community_id' => $community->id]);
        $alsoOurs = Event::factory()->create(['community_id' => $community->id]);
        $theirs = Event::factory()->create(['community_id' => null]);

        foreach ([$ours, $alsoOurs, $theirs] as $event) {
            EventCheckin::query()->create([
                'event_id' => $event->id,
                'profile_id' => $profile->id,
                'checked_in_at' => now()->subDays(2),
            ]);
        }

        $this->ledgerAt($community->id, $profile->id, now()->subDays(2));

        $row = $this->actingAs($community->owner)
            ->getJson("/api/v1/communities/{$community->id}/members")
            ->assertOk()
            ->json('data.members.0');

        $this->assertSame(340, $row['points']);
        $this->assertSame(2, $row['events_attended']);
        $this->assertSame(10, $row['tenure_days']);
        $this->assertNotNull($row['last_active_at']);
    }

    public function test_a_member_with_no_activity_reports_zeroes_and_falls_back_to_joined_at(): void
    {
        $community = Community::factory()->create();
        $member = CommunityMember::factory()->create([
            'community_id' => $community->id,
            'joined_at' => now()->subDays(3),
        ]);

        $row = $this->actingAs($community->owner)
            ->getJson("/api/v1/communities/{$community->id}/members")
            ->assertOk()
            ->json('data.members.0');

        $this->assertSame(0, $row['points']);
        $this->assertSame(0, $row['events_attended']);
        $this->assertSame(3, $row['tenure_days']);
        $this->assertNotNull($row['last_active_at']);
    }

    public function test_the_roster_costs_the_same_number_of_queries_at_any_size(): void
    {
        // BACKLOG BE-NF-15: list endpoints in this codebase have a habit of
        // going O(N) per row. Lock it — 3 members and 30 members must cost the
        // same number of queries.
        $small = Community::factory()->create();
        CommunityMember::factory()->count(3)->create(['community_id' => $small->id]);

        $large = Community::factory()->create();
        CommunityMember::factory()->count(30)->create(['community_id' => $large->id]);

        $count = function (Community $community): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($community->owner)
                ->getJson("/api/v1/communities/{$community->id}/members?limit=100")
                ->assertOk();
            $queries = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $queries;
        };

        $this->assertSame($count($small), $count($large));
    }
}
