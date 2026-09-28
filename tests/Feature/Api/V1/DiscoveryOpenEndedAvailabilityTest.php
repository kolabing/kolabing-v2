<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Kolab;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * A kolab with an `availability_start` and no `availability_end` is
 * open-ended. Discovery used to read it as a one-day window
 * (`COALESCE(end, start) >= today`), so every business's onboarding kolab —
 * `flexible`, starting tomorrow, no end — left Explore the day after it started,
 * and communities in a city of freshly onboarded businesses saw an empty feed
 * (BE-FX-74). The apply guard, Kolab::hasSelectableDatesFrom(), already read it
 * as open-ended; these tests hold discovery to the same reading.
 */
class DiscoveryOpenEndedAvailabilityTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** A Monday, so every weekday in the assertions below is unambiguous. */
    private const MONDAY = '2026-09-28 09:00:00';

    private const ISO_WEDNESDAY = 3;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_an_onboarding_kolab_whose_start_has_passed_is_still_in_the_community_feed(): void
    {
        // The production row: MAUI Beach Coworking, published 24 Sep, starting
        // 25 Sep, no end — hidden from every community from 26 Sep on.
        Carbon::setTestNow(self::MONDAY);

        $viewer = Profile::factory()->community()->create();
        $kolab = $this->publishedBusinessKolab([
            'availability_mode' => 'flexible',
            'availability_start' => Carbon::today()->subDays(3),
            'availability_end' => null,
            'recurring_days' => null,
        ]);

        $response = $this->feedFor($viewer);

        $this->assertContains($kolab->id, $this->kolabIds($response));
        $this->assertSame(1, $response->json('data.meta.total'));
    }

    public function test_the_feed_agrees_with_the_apply_guard_on_an_open_ended_kolab(): void
    {
        Carbon::setTestNow(self::MONDAY);

        $viewer = Profile::factory()->community()->create();
        $kolab = $this->publishedBusinessKolab([
            'availability_mode' => 'flexible',
            'availability_start' => Carbon::today()->subDays(10),
            'availability_end' => null,
        ]);

        $this->assertTrue($kolab->fresh()->hasSelectableDatesFrom(Carbon::today()));
        $this->assertContains($kolab->id, $this->kolabIds($this->feedFor($viewer)));
    }

    public function test_an_open_ended_recurring_kolab_whose_start_has_passed_is_in_the_feed(): void
    {
        Carbon::setTestNow(self::MONDAY);

        $viewer = Profile::factory()->community()->create();
        $kolab = $this->publishedBusinessKolab([
            'availability_mode' => 'recurring',
            'availability_start' => Carbon::today()->subDays(20),
            'availability_end' => null,
            'recurring_days' => [self::ISO_WEDNESDAY],
        ]);

        $this->assertContains($kolab->id, $this->kolabIds($this->feedFor($viewer)));
    }

    public function test_a_kolab_whose_end_has_passed_is_still_excluded(): void
    {
        Carbon::setTestNow(self::MONDAY);

        $viewer = Profile::factory()->community()->create();
        $kolab = $this->publishedBusinessKolab([
            'availability_mode' => 'one_time',
            'availability_start' => Carbon::today()->subDays(10),
            'availability_end' => Carbon::today()->subDay(),
        ]);

        $this->assertNotContains($kolab->id, $this->kolabIds($this->feedFor($viewer)));
    }

    public function test_an_open_ended_kolab_passes_the_availability_from_filter(): void
    {
        Carbon::setTestNow(self::MONDAY);

        $viewer = Profile::factory()->community()->create();
        $openEnded = $this->publishedBusinessKolab([
            'availability_mode' => 'flexible',
            'availability_start' => Carbon::today()->subDays(3),
            'availability_end' => null,
        ]);
        $endsTooSoon = $this->publishedBusinessKolab([
            'availability_mode' => 'one_time',
            'availability_start' => Carbon::today(),
            'availability_end' => Carbon::today()->addDays(2),
        ]);

        $from = Carbon::today()->addDays(10)->toDateString();
        $ids = $this->kolabIds($this->feedFor($viewer, "&availability_from={$from}"));

        $this->assertContains($openEnded->id, $ids);
        $this->assertNotContains($endsTooSoon->id, $ids);
    }

    public function test_an_open_ended_kolab_sorts_after_dated_ones_under_ending_soon(): void
    {
        Carbon::setTestNow(self::MONDAY);

        $viewer = Profile::factory()->community()->create();
        $openEnded = $this->publishedBusinessKolab([
            'availability_mode' => 'flexible',
            'availability_start' => Carbon::today()->subDays(3),
            'availability_end' => null,
        ]);
        $dated = $this->publishedBusinessKolab([
            'availability_mode' => 'one_time',
            'availability_start' => Carbon::today(),
            'availability_end' => Carbon::today()->addDays(20),
        ]);

        $ids = $this->kolabIds($this->feedFor($viewer, '&sort=ending_soon'));

        $this->assertSame([$dated->id, $openEnded->id], $ids);
    }

    // ------------------------------------------------------------ helpers

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function publishedBusinessKolab(array $attributes): Kolab
    {
        $creator = Profile::factory()->business()->create();

        return Kolab::factory()
            ->published()
            ->venuePromotion()
            ->forCreator($creator)
            ->create($attributes);
    }

    private function feedFor(Profile $viewer, string $query = ''): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($viewer)
            ->getJson('/api/v1/discovery/opportunities?feed=all&per_page=50'.$query)
            ->assertOk();
    }

    /**
     * @return list<string>
     */
    private function kolabIds(\Illuminate\Testing\TestResponse $response): array
    {
        return collect($response->json('data.data'))
            ->where('item_type', '!=', 'multi_kolab_role')
            ->pluck('id')
            ->values()
            ->all();
    }
}
