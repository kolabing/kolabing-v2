<?php

declare(strict_types=1);

namespace Tests\Feature\Incentives;

use App\Enums\OrganiserLevel;
use App\Models\BusinessProfile;
use App\Models\Community;
use App\Models\Kolab;
use App\Models\OrganiserLevelSnapshot;
use App\Models\Profile;
use App\Models\User;
use App\Services\Incentives\OrganiserLevelService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OrganiserLevelTest extends TestCase
{
    use BuildsIncentiveFixtures;
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Sat 19 Sep 2026, 10:00 UTC → 12 days left in September (Madrid).
        Carbon::setTestNow(Carbon::parse('2026-09-19 10:00:00', 'UTC'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function evaluate(Profile $organiser): OrganiserLevelSnapshot
    {
        return app(OrganiserLevelService::class)->evaluate($organiser);
    }

    public function test_new_organiser_gets_the_contract_payload(): void
    {
        $organiser = $this->organiser();

        $response = $this->actingAs($organiser)->getJson('/api/v1/me/organiser-level');

        $response->assertOk()
            ->assertJsonStructure(['data' => [
                'level', 'next_level', 'window', 'window_days', 'month', 'days_left',
                'criteria' => [['key', 'label', 'value', 'target', 'met', 'required']],
                'perks', 'next_perks', 'next_reward' => ['type', 'level', 'message', 'remaining', 'days_left'],
                'evaluated_at',
            ]])
            ->assertJsonPath('data.level', 'new')
            ->assertJsonPath('data.next_level', 'rising')
            ->assertJsonPath('data.window', 'calendar_month')
            ->assertJsonPath('data.window_days', 30)
            ->assertJsonPath('data.month', '2026-09')
            ->assertJsonPath('data.days_left', 12)
            ->assertJsonPath('data.perks', [])
            ->assertJsonPath('data.next_perks', ['Badge on your applications'])
            ->assertJsonPath('data.next_reward.level', 'rising')
            ->assertJsonPath('data.evaluated_at', '2026-09-19T10:00:00Z');

        $this->assertSame(
            ['kolabs_completed', 'monthly_kolab', 'kolab_checkins', 'league_rank'],
            array_column($response->json('data.criteria'), 'key'),
        );
        $this->assertDatabaseCount('organiser_level_snapshots', 1);
    }

    public function test_any_completed_kolab_makes_an_organiser_rising(): void
    {
        $organiser = $this->organiser();
        $this->kolab($organiser, '2026-07-10 18:00:00', 30);

        $snapshot = $this->evaluate($organiser);
        $this->assertSame(OrganiserLevel::Rising, $snapshot->level);

        $this->actingAs($organiser)->getJson('/api/v1/me/organiser-level')
            ->assertOk()
            ->assertJsonPath('data.level', 'rising')
            ->assertJsonPath('data.next_perks', ['Listed and shown to venues', 'Ranked higher where venues find communities'])
            ->assertJsonPath('data.next_reward.type', 'level')
            ->assertJsonPath('data.next_reward.level', 'trusted')
            ->assertJsonPath('data.next_reward.message', 'Complete 1 kolab this month with 20+ attendees to reach Trusted: listed and shown to venues. 12 days left')
            ->assertJsonPath('data.next_reward.remaining', ['kolabs_completed' => 1, 'checkins' => 20]);
    }

    public function test_trusted_needs_a_kolab_this_month_with_twenty_check_ins(): void
    {
        // Three busier organisers hold the league's top 3, so Trusted stays Trusted.
        foreach (['A', 'B', 'C'] as $name) {
            $this->eventWithCheckins($this->organiser(name: "Busy {$name}"), 45, '2026-09-10 19:00:00');
        }

        $short = $this->organiser();
        $this->kolab($short, '2026-09-05 19:00:00', 19);
        $this->assertSame(OrganiserLevel::Rising, $this->evaluate($short)->level);

        $full = $this->organiser();
        $this->kolab($full, '2026-09-05 19:00:00', 20);
        $snapshot = $this->evaluate($full);

        $this->assertSame(OrganiserLevel::Trusted, $snapshot->level);
        $criteria = collect($snapshot->criteria['criteria'])->keyBy('key');
        $this->assertSame(1, $criteria['monthly_kolab']['value']);
        $this->assertTrue($criteria['monthly_kolab']['met']);
        $this->assertSame(20, $criteria['kolab_checkins']['value']);
    }

    public function test_last_months_kolab_keeps_trusted_this_month_then_drops_to_rising(): void
    {
        $organiser = $this->organiser();
        $this->kolab($organiser, '2026-08-20 19:00:00', 25);

        $this->evaluate($organiser);
        $this->actingAs($organiser)->getJson('/api/v1/me/organiser-level')
            ->assertOk()
            ->assertJsonPath('data.level', 'trusted')
            ->assertJsonPath('data.next_reward.type', 'keep')
            ->assertJsonPath('data.next_reward.level', 'trusted')
            ->assertJsonPath('data.next_reward.message', 'Complete 1 kolab this month with 20+ attendees to stay Trusted. 12 days left');

        // A September without a qualifying kolab: Rising from 1 October.
        Carbon::setTestNow(Carbon::parse('2026-10-01 00:30:00', 'UTC'));
        $this->artisan('app:evaluate-organiser-levels')
            ->expectsOutputToContain('1 down')
            ->assertSuccessful();

        $latest = OrganiserLevelSnapshot::latestFor($organiser->id);
        $this->assertSame(OrganiserLevel::Rising, $latest->level);
        $this->assertSame(OrganiserLevel::Trusted, $latest->previous_level);
    }

    public function test_top_needs_trusted_and_top_three_of_the_city_league(): void
    {
        $leader = $this->organiser(name: 'Leader Run Club');
        $this->kolab($leader, '2026-09-05 19:00:00', 30); // 40 + 60 = 100 pts

        $fifth = $this->organiser(name: 'Fifth Run Club');
        $this->kolab($fifth, '2026-09-06 19:00:00', 20); // 40 + 40 = 80 pts

        foreach (['A', 'B'] as $name) {
            $this->eventWithCheckins($this->organiser(name: "Busy {$name}"), 45, '2026-09-10 19:00:00'); // 90 pts
        }
        $this->eventWithCheckins($this->organiser(name: 'Busy C'), 42, '2026-09-10 19:00:00'); // 84 pts

        $top = $this->evaluate($leader);
        $this->assertSame(OrganiserLevel::Top, $top->level);
        $this->assertTrue($top->isIntroDue());
        // 8 (Top) + min(10, round(0.02 × 100)) = 10
        $this->assertSame(10, $top->discovery_score);

        $trusted = $this->evaluate($fifth);
        $this->assertSame(OrganiserLevel::Trusted, $trusted->level);

        $this->actingAs($fifth)->getJson('/api/v1/me/organiser-level')
            ->assertOk()
            ->assertJsonPath('data.next_reward.type', 'level')
            ->assertJsonPath('data.next_reward.level', 'top')
            // 5 ranked communities → top 2 slots.
            ->assertJsonPath('data.next_reward.message', "You're #5. Reach the top 2 of your city league for Top: personal intros to sports brands, fashion brands and venues")
            ->assertJsonPath('data.next_reward.remaining', ['ranks' => 3]);

        $this->actingAs($leader)->getJson('/api/v1/me/organiser-level')
            ->assertOk()
            ->assertJsonPath('data.level', 'top')
            ->assertJsonPath('data.next_level', null)
            ->assertJsonPath('data.next_perks', [])
            ->assertJsonPath('data.next_reward.type', 'keep')
            ->assertJsonFragment(['Personal introductions to sports brands, fashion brands and venues']);
    }

    /**
     * @return array<string, array{0: int, 1: int, 2: OrganiserLevel, 3: int, 4: string}>
     */
    public static function smallLeagueTopSlots(): array
    {
        $perk = 'personal intros to sports brands, fashion brands and venues';

        return [
            '3 communities, #1 is Top' => [3, 1, OrganiserLevel::Top, 1, "You're #1. Stay #1 in your city league to keep Top: {$perk}"],
            '3 communities, #2 is not Top' => [3, 2, OrganiserLevel::Trusted, 1, "You're #2. Reach #1 of your city league for Top: {$perk}"],
            '7 communities, #2 is Top' => [7, 2, OrganiserLevel::Top, 2, "You're #2. Stay in the top 2 of your city league to keep Top: {$perk}"],
            '7 communities, #3 is not Top' => [7, 3, OrganiserLevel::Trusted, 2, "You're #3. Reach the top 2 of your city league for Top: {$perk}"],
            '12 communities, #3 is Top' => [12, 3, OrganiserLevel::Top, 3, "You're #3. Stay in the top 3 of your city league to keep Top: {$perk}"],
            '12 communities, #4 is not Top' => [12, 4, OrganiserLevel::Trusted, 3, "You're #4. Reach the top 3 of your city league for Top: {$perk}"],
        ];
    }

    #[DataProvider('smallLeagueTopSlots')]
    public function test_top_slots_scale_with_the_number_of_ranked_communities(int $communities, int $rank, OrganiserLevel $level, int $slots, string $message): void
    {
        $organiser = $this->organiser(name: 'Target Run Club');
        $this->kolab($organiser, '2026-09-05 19:00:00', 20); // 40 + 40 = 80 pts, Trusted

        for ($i = 1; $i < $rank; $i++) {
            $this->eventWithCheckins($this->organiser(name: "Above {$i}"), 45, '2026-09-10 19:00:00'); // 90 pts
        }
        for ($i = 1; $i <= $communities - $rank; $i++) {
            $this->eventWithCheckins($this->organiser(name: "Below {$i}"), 10, '2026-09-10 19:00:00'); // 20 pts
        }

        $snapshot = $this->evaluate($organiser);
        $this->assertSame($level, $snapshot->level);
        $this->assertSame($rank, $snapshot->criteria['metrics']['league_rank']);
        $this->assertSame($slots, $snapshot->criteria['metrics']['league_threshold']);
        $this->assertSame($level === OrganiserLevel::Top, $snapshot->isIntroDue());
        // Top adds 8 venue-side ranking points, Trusted 5; league 0.02 × 80 = 2.
        $this->assertSame(($level === OrganiserLevel::Top ? 8 : 5) + 2, $snapshot->discovery_score);

        $this->actingAs($organiser)->getJson('/api/v1/me/organiser-level')
            ->assertOk()
            ->assertJsonPath('data.level', $level->value)
            ->assertJsonPath('data.next_reward.message', $message);

        $this->actingAs($organiser)->getJson('/api/v1/me/community-rank')
            ->assertOk()
            ->assertJsonPath('data.total', $communities)
            ->assertJsonPath('data.top_slots', $slots);
    }

    public function test_the_nightly_job_scores_each_city_once_not_once_per_organiser(): void
    {
        // The league table used to be memoised per viewer, so the nightly job
        // rebuilt the city-wide scores (and its event_checkins scan) once for
        // every organiser in the city.
        foreach (['A', 'B', 'C'] as $name) {
            $this->eventWithCheckins($this->organiser(name: "Busy {$name}"), 20, '2026-09-10 19:00:00');
        }
        $this->organiser(name: 'Quiet D'); // no points yet: gets its own table row

        $checkinScans = 0;
        DB::listen(function ($query) use (&$checkinScans): void {
            if (str_contains($query->sql, 'event_checkins') && str_contains($query->sql, 'checked_in_at')) {
                $checkinScans++;
            }
        });

        $this->artisan('app:evaluate-organiser-levels')->assertSuccessful();

        $this->assertSame(1, $checkinScans);
        $this->assertSame(4, OrganiserLevelSnapshot::query()->count());
    }

    public function test_a_league_leader_without_a_qualifying_kolab_is_not_top(): void
    {
        $organiser = $this->organiser();
        $this->kolab($organiser, '2026-06-01 19:00:00');
        $this->eventWithCheckins($organiser, 60, '2026-09-10 19:00:00');

        $this->assertSame(OrganiserLevel::Rising, $this->evaluate($organiser)->level);
    }

    public function test_only_community_profiles_can_read_their_level(): void
    {
        $this->actingAs(Profile::factory()->attendee()->create())
            ->getJson('/api/v1/me/organiser-level')
            ->assertForbidden();

        $this->actingAs(Profile::factory()->business()->create())
            ->getJson('/api/v1/me/organiser-level')
            ->assertForbidden();
    }

    public function test_unauthenticated_gets_401(): void
    {
        $this->getJson('/api/v1/me/organiser-level')->assertUnauthorized();
    }

    public function test_admin_sees_intro_due_for_top_organisers_and_can_clear_it(): void
    {
        $organiser = $this->organiser();
        $this->kolab($organiser, '2026-09-05 19:00:00', 25);
        $this->assertSame(OrganiserLevel::Top, $this->evaluate($organiser)->level);

        $admin = User::factory()->create(['is_maintainer' => true]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.users.edit', $organiser))
            ->assertOk()
            ->assertSee('Intro due')
            ->assertSee('Mark intro done');

        $this->actingAs($admin, 'admin')
            ->post(route('admin.users.organiser-intro.done', $organiser))
            ->assertRedirect();

        $this->assertFalse(OrganiserLevelSnapshot::latestFor($organiser->id)->isIntroDue());

        // Still Top the next night: the intro stays done.
        Carbon::setTestNow(now()->addDay());
        $next = $this->evaluate($organiser);
        $this->assertSame(OrganiserLevel::Top, $next->level);
        $this->assertFalse($next->isIntroDue());

        $this->actingAs($admin, 'admin')
            ->get(route('admin.users.edit', $organiser))
            ->assertOk()
            ->assertDontSee('Mark intro done');
    }

    public function test_venues_see_higher_scoring_organisers_first_and_lower_ones_are_not_hidden(): void
    {
        $viewer = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $viewer->id]);

        $plain = $this->organiser();
        $strong = $this->organiser();

        $attributes = [
            'intent_type' => 'community_seeking',
            'preferred_city' => 'Barcelona',
            'offer_headline' => 'Run with us',
            'availability_start' => now()->addDays(10),
            'availability_end' => now()->addDays(12),
        ];
        $plainKolab = Kolab::factory()->published()->forCreator($plain)->create($attributes + ['title' => 'Plain']);
        $strongKolab = Kolab::factory()->published()->forCreator($strong)->create($attributes + ['title' => 'Strong']);

        OrganiserLevelSnapshot::query()->create([
            'profile_id' => $plain->id,
            'level' => OrganiserLevel::Rising,
            'criteria' => ['criteria' => []],
            'evaluated_at' => now(),
            'discovery_score' => 0,
        ]);
        OrganiserLevelSnapshot::query()->create([
            'profile_id' => $strong->id,
            'level' => OrganiserLevel::Top,
            'criteria' => ['criteria' => []],
            'evaluated_at' => now(),
            'discovery_score' => 15,
        ]);

        $fetch = fn () => collect($this->actingAs($viewer)
            ->getJson('/api/v1/discovery/opportunities?feed=all&sort=recommended')
            ->assertOk()
            ->json('data.data'))->keyBy('id');

        $on = $fetch();
        $this->assertSame('top', $on[$strongKolab->id]['match']['organiser_level_boost']['level']);
        $this->assertSame(15, $on[$strongKolab->id]['match']['organiser_level_boost']['points']);
        $this->assertTrue($on->has($plainKolab->id), 'Rising organisers stay visible to venues by default');

        config(['incentives.organiser_levels.discovery.enabled' => false]);
        $off = $fetch();
        $this->assertSame(0, $off[$strongKolab->id]['match']['organiser_level_boost']['points']);
        // Same kolab, same viewer: the boost is exactly the stored discovery_score.
        $this->assertSame(min(100, $off[$strongKolab->id]['match']['score'] + 15), $on[$strongKolab->id]['match']['score']);

        config([
            'incentives.organiser_levels.discovery.enabled' => true,
            'incentives.organiser_levels.discovery.hide_below_trusted' => true,
        ]);
        $hidden = $fetch();
        $this->assertTrue($hidden->has($strongKolab->id));
        $this->assertFalse($hidden->has($plainKolab->id));
    }

    public function test_venues_browsing_communities_see_higher_scoring_organisers_first(): void
    {
        $viewer = Profile::factory()->business()->create();
        BusinessProfile::factory()->create(['profile_id' => $viewer->id]);

        $low = $this->organiser();
        $high = $this->organiser();
        $lowCommunity = Community::factory()->forOwner($low)->create(['name' => 'Aaa Low', 'is_featured' => false]);
        $highCommunity = Community::factory()->forOwner($high)->create(['name' => 'Zzz High', 'is_featured' => false]);

        OrganiserLevelSnapshot::query()->create([
            'profile_id' => $high->id,
            'level' => OrganiserLevel::Trusted,
            'criteria' => ['criteria' => []],
            'evaluated_at' => now(),
            'discovery_score' => 9,
        ]);

        $ids = array_column($this->actingAs($viewer)->getJson('/api/v1/communities/discover')->assertOk()->json('data'), 'id');

        $this->assertSame([$highCommunity->id, $lowCommunity->id], array_values(array_intersect($ids, [$highCommunity->id, $lowCommunity->id])));
    }
}
