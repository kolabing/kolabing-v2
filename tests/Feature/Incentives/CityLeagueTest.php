<?php

declare(strict_types=1);

namespace Tests\Feature\Incentives;

use App\Models\CommunityProfile;
use App\Models\LeagueSeason;
use App\Models\LeagueStanding;
use App\Models\Profile;
use App\Models\User;
use App\Services\Incentives\CityLeagueService;
use App\Services\Incentives\OrganiserLevelService;
use App\Support\PublicProfileLink;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CityLeagueTest extends TestCase
{
    use BuildsIncentiveFixtures;
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-09-19 10:00:00', 'UTC'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Small thresholds so divisions can be exercised with a handful of organisers. */
    private function smallDivisionThresholds(): void
    {
        config([
            'incentives.city_league.divisions.medium_from' => 2,
            'incentives.city_league.divisions.large_from' => 50,
            'incentives.city_league.divisions.min_active_per_division' => 4,
        ]);
    }

    public function test_score_counts_only_in_app_verified_actions_this_month(): void
    {
        $organiser = $this->organiser(name: 'Real Run Club');

        // 1 kolab completed in September with 10 check-ins at it: 40 + 20.
        $this->kolab($organiser, '2026-09-05 19:00:00', 10);
        // An own event: 5 check-ins (attendees 1-5 again → 5 repeat attendees): 10 + 25.
        $event = $this->eventWithCheckins($organiser, 5, '2026-09-12 19:00:00');
        // August activity does not count toward September.
        $this->kolab($organiser, '2026-08-20 19:00:00', 0);
        $this->eventWithCheckins($organiser, 3, '2026-08-25 19:00:00');
        // One 5-star venue review (20), one 4-star (0).
        $this->venueReview($organiser, 5, '2026-09-08 12:00:00');
        $this->venueReview($organiser, 4, '2026-09-09 12:00:00');

        $this->actingAs($organiser)->getJson('/api/v1/me/community-rank')
            ->assertOk()
            ->assertJsonPath('data.score_breakdown', [
                'kolabs_completed' => 1,
                'checkins' => 15,
                'repeat_attendees' => 5,
                'venue_reviews_5star' => 1,
            ])
            // 40 + 15×2 + 5×5 + 20
            ->assertJsonPath('data.points', 115)
            ->assertJsonPath('data.rank', 1);
    }

    public function test_community_rank_payload_preview_and_names(): void
    {
        $city = $this->city();
        $organisers = [];
        foreach (range(1, 8) as $i) {
            $organisers[$i] = $this->organiser($city, "Club {$i}");
            $this->eventWithCheckins($organisers[$i], 20 - $i, '2026-09-10 19:00:00');
        }
        // Another city never shows up.
        $this->eventWithCheckins($this->organiser($this->city('Madrid'), 'Madrid Club'), 30, '2026-09-10 19:00:00');

        $response = $this->actingAs($organisers[6])->getJson('/api/v1/me/community-rank')->assertOk();

        $response->assertJsonStructure(['data' => [
            'city', 'city_id', 'month', 'season_ends_at', 'division', 'division_label', 'rank', 'total', 'points',
            'score_breakdown' => ['kolabs_completed', 'checkins', 'repeat_attendees', 'venue_reviews_5star'],
            'preview' => [['rank', 'profile_id', 'display_name', 'points', 'is_viewer']],
            'promotion_zone', 'relegation_zone', 'next_reward' => ['type', 'level', 'message', 'remaining'], 'top_reward',
        ]])
            ->assertJsonPath('data.city', 'Barcelona')
            ->assertJsonPath('data.month', '2026-09')
            ->assertJsonPath('data.season_ends_at', '2026-09-30T21:59:59Z')
            ->assertJsonPath('data.division', 'city')
            ->assertJsonPath('data.rank', 6)
            ->assertJsonPath('data.total', 8)
            ->assertJsonPath('data.points', 28)
            ->assertJsonPath('data.promotion_zone', false)
            ->assertJsonPath('data.relegation_zone', false)
            ->assertJsonPath('data.top_reward', 'Top organisers get personal intros to sports brands, fashion brands and venues');

        $this->assertSame([1, 2, 3, 5, 6, 7], array_column($response->json('data.preview'), 'rank'));
        $this->assertSame('Club 1', $response->json('data.preview.0.display_name'));
        $this->assertSame([false, false, false, false, true, false], array_column($response->json('data.preview'), 'is_viewer'));
        $this->assertStringNotContainsString('@', $response->getContent());
    }

    public function test_organiser_without_points_or_city_still_gets_a_payload(): void
    {
        $organiser = $this->organiser();

        $this->actingAs($organiser)->getJson('/api/v1/me/community-rank')
            ->assertOk()
            ->assertJsonPath('data.rank', null)
            ->assertJsonPath('data.points', 0)
            ->assertJsonPath('data.next_reward.level', 'rising');
    }

    public function test_one_division_until_two_divisions_have_enough_active_organisers(): void
    {
        $this->smallDivisionThresholds();
        $city = $this->city();

        foreach (range(1, 4) as $i) {
            $this->eventWithCheckins($this->organiser($city, "Small {$i}"), 10 + $i, '2026-09-10 19:00:00');
        }
        $bigs = [];
        foreach (range(1, 3) as $i) {
            $bigs[$i] = $this->organiser($city, "Big {$i}", members: 3);
            $this->eventWithCheckins($bigs[$i], 5 + $i, '2026-09-10 19:00:00');
        }

        $league = app(CityLeagueService::class);
        $single = $league->table($city, '2026-09');
        $this->assertFalse($single['divisions_enabled']);
        $this->assertSame(['city'], array_values(array_unique(array_column($single['rows'], 'division'))));

        $bigs[4] = $this->organiser($city, 'Big 4', members: 3);
        $this->eventWithCheckins($bigs[4], 1, '2026-09-10 19:00:00');

        $multi = app(CityLeagueService::class)->table($city, '2026-09');
        $this->assertTrue($multi['divisions_enabled']);
        $byName = collect($multi['rows'])->keyBy('display_name');

        $this->assertSame('medium', $byName['Big 1']['division']);
        $this->assertSame('small', $byName['Small 1']['division']);
        // Ranked inside their own division.
        $this->assertSame(1, $byName['Small 4']['rank']);
        $this->assertSame(1, $byName['Big 3']['rank']);
        // Top 3 of the lower division are in the promotion zone; bottom of the upper in relegation.
        $this->assertTrue($byName['Small 4']['promotion_zone']);
        $this->assertFalse($byName['Small 1']['promotion_zone']);
        $this->assertFalse($byName['Big 3']['promotion_zone']);
        $this->assertTrue($byName['Big 4']['relegation_zone']);
        $this->assertFalse($byName['Small 1']['relegation_zone']);

        $this->actingAs($bigs[4])->getJson('/api/v1/me/community-rank')
            ->assertOk()
            ->assertJsonPath('data.division', 'medium')
            ->assertJsonPath('data.total', 4)
            ->assertJsonPath('data.relegation_zone', true);
    }

    public function test_season_close_freezes_standings_badges_movement_and_carries_divisions(): void
    {
        $this->smallDivisionThresholds();
        $city = $this->city();

        $smalls = [];
        foreach (range(1, 4) as $i) {
            $smalls[$i] = $this->organiser($city, "Small {$i}");
            $this->eventWithCheckins($smalls[$i], 10 + $i, '2026-09-10 19:00:00');
        }
        foreach (range(1, 4) as $i) {
            $this->eventWithCheckins($this->organiser($city, "Big {$i}", members: 3), 5 + $i, '2026-09-10 19:00:00');
        }

        // 1 October 00:30 Madrid.
        Carbon::setTestNow(Carbon::parse('2026-09-30 22:30:00', 'UTC'));
        $this->artisan('app:close-league-seasons')->expectsOutputToContain('Closed 1 city seasons for 2026-09')->assertSuccessful();
        $this->artisan('app:close-league-seasons')->assertSuccessful(); // idempotent

        $season = LeagueSeason::query()->where('city_id', $city->id)->where('month', '2026-09')->sole();
        $this->assertTrue($season->divisions_enabled);
        $this->assertNotNull($season->closed_at);
        $this->assertSame(8, $season->standings()->count());

        $standing = fn (Profile $p): LeagueStanding => LeagueStanding::query()->where('profile_id', $p->id)->sole();
        $this->assertSame(LeagueStanding::BADGE_CHAMPION, $standing($smalls[4])->badge);
        $this->assertSame('promoted', $standing($smalls[4])->movement);
        $this->assertSame(LeagueStanding::BADGE_TOP3, $standing($smalls[2])->badge);
        $this->assertNull($standing($smalls[1])->badge);
        $this->assertTrue($standing($smalls[4])->isIntroDue());
        $this->assertSame(2, LeagueStanding::query()->where('badge', 'champion')->count());
        $this->assertSame(1, LeagueStanding::query()->where('movement', 'relegated')->count());

        // October: the promoted small club plays in the upper division
        // (two newcomers keep the lower division above the minimum).
        $newcomers = [$this->organiser($city, 'New 1'), $this->organiser($city, 'New 2')];
        foreach ([...$smalls, ...$newcomers, ...LeagueStanding::query()->where('division', 'medium')->with('profile')->get()->pluck('profile')] as $p) {
            $this->eventWithCheckins($p, 3, '2026-10-05 19:00:00');
        }
        $october = collect(app(CityLeagueService::class)->table($city, '2026-10')['rows'])->keyBy('profile_id');
        $this->assertSame('medium', $october[$smalls[4]->id]['division']);
        $this->assertSame('small', $october[$smalls[1]->id]['division']);

        // Admin flag + public badge.
        $admin = User::factory()->create(['is_maintainer' => true]);
        $this->actingAs($admin, 'admin')
            ->get(route('admin.users.edit', $smalls[4]))
            ->assertOk()
            ->assertSee('Intro due')
            ->assertSee('Mark league intro done');
        $this->actingAs($admin, 'admin')
            ->post(route('admin.users.league-intro.done', [$smalls[4], $standing($smalls[4])]))
            ->assertRedirect();
        $this->assertFalse($standing($smalls[4])->isIntroDue());

        $this->get('/p/'.PublicProfileLink::slugFor($smalls[4]))
            ->assertOk()
            ->assertSee('Champion · Barcelona league Sep 2026');
    }

    public function test_top_threshold_grows_to_ten_percent_in_large_divisions(): void
    {
        $league = app(CityLeagueService::class);

        $this->assertSame(3, $league->topThreshold(12));
        $this->assertSame(3, $league->topThreshold(30));
        $this->assertSame(5, $league->topThreshold(45));
    }

    public function test_city_table_is_for_organisers_of_that_city_and_shows_names_only(): void
    {
        $city = $this->city();
        $viewer = $this->organiser($city, 'Viewer Club');
        $nameless = Profile::factory()->community()->create(['name' => null, 'email' => 'nameless.club@example.com']);
        CommunityProfile::factory()->create(['profile_id' => $nameless->id, 'name' => '', 'city_id' => $city->id]);
        $this->eventWithCheckins($nameless, 4, '2026-09-10 19:00:00');
        $this->eventWithCheckins($viewer, 2, '2026-09-10 19:00:00');

        $response = $this->actingAs($viewer)->getJson("/api/v1/leagues/{$city->id}/current")
            ->assertOk()
            ->assertJsonPath('data.city', 'Barcelona')
            ->assertJsonPath('data.divisions_enabled', false)
            ->assertJsonPath('data.divisions.0.key', 'city')
            ->assertJsonPath('data.divisions.0.rows.0.display_name', 'nameless.club')
            ->assertJsonPath('data.divisions.0.rows.1.is_viewer', true);
        $this->assertStringNotContainsString('@', $response->getContent());

        $this->actingAs($this->organiser($this->city('Madrid')))->getJson("/api/v1/leagues/{$city->id}/current")->assertForbidden();
        $this->actingAs(Profile::factory()->attendee()->create())->getJson("/api/v1/leagues/{$city->id}/current")->assertForbidden();
        $this->actingAs(Profile::factory()->business()->create())->getJson("/api/v1/leagues/{$city->id}/current")->assertForbidden();
        $this->actingAs(Profile::factory()->attendee()->create())->getJson('/api/v1/me/community-rank')->assertForbidden();
        $this->actingAs(Profile::factory()->business()->create())->getJson('/api/v1/me/community-rank')->assertForbidden();

        config(['incentives.city_league.enabled' => false]);
        $this->actingAs($viewer)->getJson("/api/v1/leagues/{$city->id}/current")->assertNotFound();
    }

    public function test_last_seasons_champion_gets_a_venue_ranking_boost(): void
    {
        $city = $this->city();
        $organiser = $this->organiser($city);
        $season = LeagueSeason::query()->create([
            'city_id' => $city->id,
            'month' => '2026-08',
            'starts_at' => '2026-07-31 22:00:00',
            'ends_at' => '2026-08-31 21:59:59',
            'divisions_enabled' => false,
            'closed_at' => '2026-09-01 00:30:00',
        ]);
        LeagueStanding::query()->create([
            'season_id' => $season->id,
            'profile_id' => $organiser->id,
            'division' => 'city',
            'rank' => 1,
            'points' => 300,
            'score_breakdown' => [],
            'badge' => LeagueStanding::BADGE_CHAMPION,
        ]);

        $snapshot = app(OrganiserLevelService::class)->evaluate($organiser);

        // New (no kolab) 0 + league round(0.02 × (0 + 0.5 × 300)) = 3 + champion 5.
        $this->assertSame(['level' => 0, 'league' => 3, 'champion' => 5], $snapshot->criteria['discovery']);
        $this->assertSame(8, $snapshot->discovery_score);
    }
}
