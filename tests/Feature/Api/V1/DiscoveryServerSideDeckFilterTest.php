<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\MultiKolabEligibleAccountType;
use App\Models\Kolab;
use App\Models\MultiKolabEvent;
use App\Models\MultiKolabRole;
use App\Models\Profile;
use App\Models\UserBlock;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * `GET /api/v1/discovery/opportunities` must be the ONLY filter the Explore
 * deck goes through, so a client that renders the response verbatim draws
 * exactly `meta.total` cards (kolabing-v2#316, kolabing-app#208).
 *
 * Two rules were enforced only by the app's own `filterExploreDeckItems`:
 * a blocked Multi-Kolab organiser, and a recurring kolab whose remaining window
 * holds none of its bookable weekdays. Both are covered here.
 */
class DiscoveryServerSideDeckFilterTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** A Monday, so every weekday in the assertions below is unambiguous. */
    private const MONDAY = '2026-09-28 09:00:00';

    private const ISO_WEDNESDAY = 3;

    private const ISO_SATURDAY = 6;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    // ---------------------------------------------------------------- BE-1

    public function test_a_role_whose_organiser_the_viewer_blocked_is_not_in_the_feed(): void
    {
        $viewer = Profile::factory()->community()->create();
        $organiser = Profile::factory()->business()->create();

        $event = MultiKolabEvent::factory()->recruiting()->create([
            'creator_profile_id' => $organiser->id,
        ]);
        $role = MultiKolabRole::factory()->create([
            'multi_kolab_event_id' => $event->id,
            'eligible_account_type' => MultiKolabEligibleAccountType::Community,
        ]);

        UserBlock::create([
            'blocker_profile_id' => $viewer->id,
            'blocked_profile_id' => $organiser->id,
        ]);

        $this->assertFalse($this->roleIdsFor($viewer)->contains($role->id));
    }

    public function test_a_role_whose_organiser_blocked_the_viewer_is_not_in_the_feed(): void
    {
        $viewer = Profile::factory()->community()->create();
        $organiser = Profile::factory()->business()->create();

        $event = MultiKolabEvent::factory()->recruiting()->create([
            'creator_profile_id' => $organiser->id,
        ]);
        $role = MultiKolabRole::factory()->create([
            'multi_kolab_event_id' => $event->id,
            'eligible_account_type' => MultiKolabEligibleAccountType::Either,
        ]);

        UserBlock::create([
            'blocker_profile_id' => $organiser->id,
            'blocked_profile_id' => $viewer->id,
        ]);

        $this->assertFalse($this->roleIdsFor($viewer)->contains($role->id));
    }

    public function test_an_unblocked_organisers_role_is_still_in_the_feed(): void
    {
        $viewer = Profile::factory()->community()->create();
        $blockedOrganiser = Profile::factory()->business()->create();
        $otherOrganiser = Profile::factory()->business()->create();

        $blockedEvent = MultiKolabEvent::factory()->recruiting()->create([
            'creator_profile_id' => $blockedOrganiser->id,
        ]);
        MultiKolabRole::factory()->create([
            'multi_kolab_event_id' => $blockedEvent->id,
            'eligible_account_type' => MultiKolabEligibleAccountType::Community,
        ]);

        $visibleEvent = MultiKolabEvent::factory()->recruiting()->create([
            'creator_profile_id' => $otherOrganiser->id,
        ]);
        $visibleRole = MultiKolabRole::factory()->create([
            'multi_kolab_event_id' => $visibleEvent->id,
            'eligible_account_type' => MultiKolabEligibleAccountType::Community,
        ]);

        UserBlock::create([
            'blocker_profile_id' => $viewer->id,
            'blocked_profile_id' => $blockedOrganiser->id,
        ]);

        $this->assertTrue($this->roleIdsFor($viewer)->contains($visibleRole->id));
    }

    // ---------------------------------------------------------------- BE-2

    public function test_a_recurring_kolab_with_no_bookable_weekday_left_is_not_in_the_feed(): void
    {
        Carbon::setTestNow(self::MONDAY);

        $viewer = Profile::factory()->business()->create();
        $kolab = $this->publishedCommunityKolab([
            'availability_mode' => 'recurring',
            // Monday .. Thursday holds no Saturday.
            'availability_start' => Carbon::today(),
            'availability_end' => Carbon::today()->addDays(3),
            'recurring_days' => [self::ISO_SATURDAY],
        ]);

        $this->assertFalse($this->kolabIdsFor($viewer)->contains($kolab->id));
    }

    public function test_a_recurring_kolab_that_still_has_a_bookable_weekday_is_in_the_feed(): void
    {
        Carbon::setTestNow(self::MONDAY);

        $viewer = Profile::factory()->business()->create();
        $kolab = $this->publishedCommunityKolab([
            'availability_mode' => 'recurring',
            // Monday .. Thursday holds exactly one Wednesday.
            'availability_start' => Carbon::today(),
            'availability_end' => Carbon::today()->addDays(3),
            'recurring_days' => [self::ISO_WEDNESDAY],
        ]);

        $this->assertTrue($this->kolabIdsFor($viewer)->contains($kolab->id));
    }

    public function test_a_recurring_kolab_whose_window_has_not_started_is_judged_from_its_start(): void
    {
        Carbon::setTestNow(self::MONDAY);

        $viewer = Profile::factory()->business()->create();
        // Starts next Friday and runs three days, so it contains a Saturday
        // even though there is no Saturday between today and its start.
        $kolab = $this->publishedCommunityKolab([
            'availability_mode' => 'recurring',
            'availability_start' => Carbon::today()->addDays(11),
            'availability_end' => Carbon::today()->addDays(13),
            'recurring_days' => [self::ISO_SATURDAY],
        ]);

        $this->assertTrue($this->kolabIdsFor($viewer)->contains($kolab->id));
    }

    public function test_a_non_recurring_kolab_in_an_open_window_is_untouched(): void
    {
        Carbon::setTestNow(self::MONDAY);

        $viewer = Profile::factory()->business()->create();
        $kolab = $this->publishedCommunityKolab([
            'availability_mode' => 'specific_dates',
            'availability_start' => Carbon::today(),
            'availability_end' => Carbon::today()->addDays(3),
            'recurring_days' => null,
        ]);

        $this->assertTrue($this->kolabIdsFor($viewer)->contains($kolab->id));
    }

    public function test_an_open_ended_kolab_starting_tomorrow_is_still_in_the_feed(): void
    {
        // FX-57's production row, guarded from the other side: the new weekday
        // rule must not re-hide what that fix made visible.
        Carbon::setTestNow(self::MONDAY);

        $viewer = Profile::factory()->business()->create();
        $kolab = $this->publishedCommunityKolab([
            'availability_mode' => 'flexible',
            'availability_start' => Carbon::today()->addDay(),
            'availability_end' => null,
            'recurring_days' => null,
        ]);

        $this->assertTrue($this->kolabIdsFor($viewer)->contains($kolab->id));
    }

    // ---------------------------------------------- the count is the deck

    public function test_meta_total_equals_the_number_of_items_the_client_would_draw(): void
    {
        Carbon::setTestNow(self::MONDAY);

        $viewer = Profile::factory()->business()->create();

        $this->publishedCommunityKolab([
            'availability_mode' => 'recurring',
            'availability_start' => Carbon::today(),
            'availability_end' => Carbon::today()->addDays(3),
            'recurring_days' => [self::ISO_SATURDAY],
        ]);
        $this->publishedCommunityKolab([
            'availability_mode' => 'recurring',
            'availability_start' => Carbon::today(),
            'availability_end' => Carbon::today()->addDays(3),
            'recurring_days' => [self::ISO_WEDNESDAY],
        ]);
        $this->publishedCommunityKolab([
            'availability_mode' => 'specific_dates',
            'availability_start' => Carbon::today(),
            'availability_end' => Carbon::today()->addDays(3),
            'recurring_days' => null,
        ]);

        $blockedOrganiser = Profile::factory()->business()->create();
        $blockedEvent = MultiKolabEvent::factory()->recruiting()->create([
            'creator_profile_id' => $blockedOrganiser->id,
        ]);
        MultiKolabRole::factory()->create([
            'multi_kolab_event_id' => $blockedEvent->id,
            'eligible_account_type' => MultiKolabEligibleAccountType::Business,
        ]);
        UserBlock::create([
            'blocker_profile_id' => $viewer->id,
            'blocked_profile_id' => $blockedOrganiser->id,
        ]);

        $response = $this->actingAs($viewer)
            ->getJson('/api/v1/discovery/opportunities?feed=all&per_page=50')
            ->assertOk();

        $this->assertSame(2, $response->json('data.meta.total'));
        $this->assertCount(2, $response->json('data.data'));
    }

    // ------------------------------------------------------------ helpers

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function publishedCommunityKolab(array $attributes): Kolab
    {
        $creator = Profile::factory()->community()->create();

        return Kolab::factory()
            ->published()
            ->forCreator($creator)
            ->create(array_merge(['intent_type' => 'community_seeking'], $attributes));
    }

    /**
     * @return \Illuminate\Support\Collection<int, string>
     */
    private function roleIdsFor(Profile $viewer): \Illuminate\Support\Collection
    {
        $response = $this->actingAs($viewer)
            ->getJson('/api/v1/discovery/opportunities?feed=all&per_page=50')
            ->assertOk();

        return collect($response->json('data.data'))
            ->where('item_type', 'multi_kolab_role')
            ->pluck('id');
    }

    /**
     * @return \Illuminate\Support\Collection<int, string>
     */
    private function kolabIdsFor(Profile $viewer): \Illuminate\Support\Collection
    {
        $response = $this->actingAs($viewer)
            ->getJson('/api/v1/discovery/opportunities?feed=all&per_page=50')
            ->assertOk();

        return collect($response->json('data.data'))
            ->where('item_type', '!=', 'multi_kolab_role')
            ->pluck('id');
    }
}
