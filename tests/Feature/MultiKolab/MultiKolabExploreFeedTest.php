<?php

declare(strict_types=1);

namespace Tests\Feature\MultiKolab;

use App\Enums\MultiKolabEligibleAccountType;
use App\Enums\MultiKolabEventStatus;
use App\Enums\MultiKolabRoleStatus;
use App\Models\MultiKolabEvent;
use App\Models\MultiKolabRole;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Covers Task 9's correction: Multi-Kolab roles surface as ordinary
 * `item_type: "multi_kolab_role"` items inside the existing
 * `GET /api/v1/discovery/opportunities` feed, not a separate endpoint/screen.
 * See `docs/superpowers/specs/2026-08-12-multi-kolab-event-api-contract.md` §13.
 */
class MultiKolabExploreFeedTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function makeRecruitingEvent(array $eventOverrides = []): MultiKolabEvent
    {
        return MultiKolabEvent::factory()->recruiting()->create($eventOverrides);
    }

    private function makeOpenRole(MultiKolabEvent $event, array $roleOverrides = []): MultiKolabRole
    {
        return MultiKolabRole::factory()->create(array_merge([
            'multi_kolab_event_id' => $event->id,
        ], $roleOverrides));
    }

    // 1. Community viewers receive Community and `either` roles, never Business-only.
    public function test_community_viewer_receives_community_and_either_roles_never_business_only(): void
    {
        $viewer = Profile::factory()->community()->create();
        $event = $this->makeRecruitingEvent();
        $communityRole = $this->makeOpenRole($event, ['title' => 'Community role', 'eligible_account_type' => MultiKolabEligibleAccountType::Community]);
        $eitherRole = $this->makeOpenRole($event, ['title' => 'Either role', 'eligible_account_type' => MultiKolabEligibleAccountType::Either]);
        $businessOnlyRole = $this->makeOpenRole($event, ['title' => 'Business only role', 'eligible_account_type' => MultiKolabEligibleAccountType::Business]);

        $response = $this->actingAs($viewer)
            ->getJson('/api/v1/discovery/opportunities?feed=all')
            ->assertOk();

        $ids = collect($response->json('data.data'))
            ->where('item_type', 'multi_kolab_role')
            ->pluck('id');

        $this->assertTrue($ids->contains($communityRole->id));
        $this->assertTrue($ids->contains($eitherRole->id));
        $this->assertFalse($ids->contains($businessOnlyRole->id));
    }

    // 2. Business viewers receive Business and `either` roles, never Community-only.
    public function test_business_viewer_receives_business_and_either_roles_never_community_only(): void
    {
        $viewer = Profile::factory()->business()->create();
        $event = $this->makeRecruitingEvent();
        $businessRole = $this->makeOpenRole($event, ['title' => 'Business role', 'eligible_account_type' => MultiKolabEligibleAccountType::Business]);
        $eitherRole = $this->makeOpenRole($event, ['title' => 'Either role', 'eligible_account_type' => MultiKolabEligibleAccountType::Either]);
        $communityOnlyRole = $this->makeOpenRole($event, ['title' => 'Community only role', 'eligible_account_type' => MultiKolabEligibleAccountType::Community]);

        $response = $this->actingAs($viewer)
            ->getJson('/api/v1/discovery/opportunities?feed=all')
            ->assertOk();

        $ids = collect($response->json('data.data'))
            ->where('item_type', 'multi_kolab_role')
            ->pluck('id');

        $this->assertTrue($ids->contains($businessRole->id));
        $this->assertTrue($ids->contains($eitherRole->id));
        $this->assertFalse($ids->contains($communityOnlyRole->id));
    }

    // 3. Each role = one feed item.
    public function test_each_role_is_exactly_one_feed_item(): void
    {
        $viewer = Profile::factory()->community()->create();
        $event = $this->makeRecruitingEvent();
        $roleOne = $this->makeOpenRole($event, ['eligible_account_type' => MultiKolabEligibleAccountType::Community]);
        $roleTwo = $this->makeOpenRole($event, ['eligible_account_type' => MultiKolabEligibleAccountType::Community]);

        $response = $this->actingAs($viewer)
            ->getJson('/api/v1/discovery/opportunities?feed=all')
            ->assertOk();

        $multiKolabItems = collect($response->json('data.data'))->where('item_type', 'multi_kolab_role');

        $this->assertCount(1, $multiKolabItems->where('id', $roleOne->id));
        $this->assertCount(1, $multiKolabItems->where('id', $roleTwo->id));
    }

    // 4. Multi-position role = one item with correct remaining count.
    public function test_multi_position_role_is_one_item_with_correct_remaining_count(): void
    {
        $viewer = Profile::factory()->community()->create();
        $event = $this->makeRecruitingEvent();
        $role = $this->makeOpenRole($event, [
            'eligible_account_type' => MultiKolabEligibleAccountType::Community,
            'positions_needed' => 5,
            'positions_filled' => 2,
        ]);

        $response = $this->actingAs($viewer)
            ->getJson('/api/v1/discovery/opportunities?feed=all')
            ->assertOk();

        $items = collect($response->json('data.data'))->where('item_type', 'multi_kolab_role')->where('id', $role->id);

        $this->assertCount(1, $items);
        $this->assertSame(3, $items->first()['positions_remaining']);
    }

    // 5. Draft/cancelled/completed/non-recruiting events excluded.
    public function test_non_recruiting_event_statuses_are_excluded(): void
    {
        $viewer = Profile::factory()->community()->create();

        $draftEvent = MultiKolabEvent::factory()->create(['status' => MultiKolabEventStatus::Draft]);
        $confirmedEvent = MultiKolabEvent::factory()->recruiting()->create(['status' => MultiKolabEventStatus::Confirmed]);
        $completedEvent = MultiKolabEvent::factory()->recruiting()->create(['status' => MultiKolabEventStatus::Completed]);
        $cancelledEvent = MultiKolabEvent::factory()->cancelled()->create();

        $excludedRoles = collect([$draftEvent, $confirmedEvent, $completedEvent, $cancelledEvent])
            ->map(fn (MultiKolabEvent $event): MultiKolabRole => $this->makeOpenRole($event, [
                'eligible_account_type' => MultiKolabEligibleAccountType::Community,
            ]));

        $response = $this->actingAs($viewer)
            ->getJson('/api/v1/discovery/opportunities?feed=all')
            ->assertOk();

        $ids = collect($response->json('data.data'))->where('item_type', 'multi_kolab_role')->pluck('id');

        foreach ($excludedRoles as $role) {
            $this->assertFalse($ids->contains($role->id));
        }
    }

    // 6. Filled/closed roles excluded.
    public function test_filled_and_closed_roles_are_excluded(): void
    {
        $viewer = Profile::factory()->community()->create();
        $event = $this->makeRecruitingEvent();

        $filledRole = $this->makeOpenRole($event, [
            'eligible_account_type' => MultiKolabEligibleAccountType::Community,
            'status' => MultiKolabRoleStatus::Filled,
            'positions_needed' => 1,
            'positions_filled' => 1,
        ]);
        $closedRole = $this->makeOpenRole($event, [
            'eligible_account_type' => MultiKolabEligibleAccountType::Community,
            'status' => MultiKolabRoleStatus::Closed,
        ]);

        $response = $this->actingAs($viewer)
            ->getJson('/api/v1/discovery/opportunities?feed=all')
            ->assertOk();

        $ids = collect($response->json('data.data'))->where('item_type', 'multi_kolab_role')->pluck('id');

        $this->assertFalse($ids->contains($filledRole->id));
        $this->assertFalse($ids->contains($closedRole->id));
    }

    // 7. Organizer-owned roles excluded.
    public function test_organizer_owned_roles_are_excluded_for_the_organizer(): void
    {
        $organizer = Profile::factory()->community()->create();
        $event = $this->makeRecruitingEvent(['creator_profile_id' => $organizer->id]);
        $role = $this->makeOpenRole($event, ['eligible_account_type' => MultiKolabEligibleAccountType::Community]);

        $response = $this->actingAs($organizer)
            ->getJson('/api/v1/discovery/opportunities?feed=all')
            ->assertOk();

        $ids = collect($response->json('data.data'))->where('item_type', 'multi_kolab_role')->pluck('id');

        $this->assertFalse($ids->contains($role->id));
    }

    // 8. Search matches structured role/event fields.
    public function test_search_matches_structured_role_and_event_fields(): void
    {
        $viewer = Profile::factory()->community()->create();
        $event = $this->makeRecruitingEvent(['title' => 'Kolabing Launch Weekend']);
        $matchingRole = $this->makeOpenRole($event, [
            'title' => 'Run Club Partner',
            'eligible_account_type' => MultiKolabEligibleAccountType::Community,
        ]);
        $otherEvent = $this->makeRecruitingEvent(['title' => 'Unrelated Gathering']);
        $nonMatchingRole = $this->makeOpenRole($otherEvent, [
            'title' => 'Photography Partner',
            'eligible_account_type' => MultiKolabEligibleAccountType::Community,
        ]);

        $response = $this->actingAs($viewer)
            ->getJson('/api/v1/discovery/opportunities?feed=all&search=Run+Club')
            ->assertOk();

        $ids = collect($response->json('data.data'))->where('item_type', 'multi_kolab_role')->pluck('id');

        $this->assertTrue($ids->contains($matchingRole->id));
        $this->assertFalse($ids->contains($nonMatchingRole->id));
    }

    // 9. City and applicable filters work.
    public function test_city_filter_applies_to_multi_kolab_roles(): void
    {
        $viewer = Profile::factory()->community()->create();
        $barcelonaEvent = $this->makeRecruitingEvent(['city' => 'Barcelona']);
        $barcelonaRole = $this->makeOpenRole($barcelonaEvent, ['eligible_account_type' => MultiKolabEligibleAccountType::Community]);
        $madridEvent = $this->makeRecruitingEvent(['city' => 'Madrid']);
        $madridRole = $this->makeOpenRole($madridEvent, ['eligible_account_type' => MultiKolabEligibleAccountType::Community]);

        $response = $this->actingAs($viewer)
            ->getJson('/api/v1/discovery/opportunities?feed=all&city=Barcelona')
            ->assertOk();

        $ids = collect($response->json('data.data'))->where('item_type', 'multi_kolab_role')->pluck('id');

        $this->assertTrue($ids->contains($barcelonaRole->id));
        $this->assertFalse($ids->contains($madridRole->id));
    }

    // 10. Pagination deterministic, no dup/omit.
    public function test_pagination_across_mixed_items_is_deterministic_with_no_duplicates_or_omissions(): void
    {
        $viewer = Profile::factory()->community()->create();

        $expectedIds = [];

        for ($i = 0; $i < 6; $i++) {
            $event = $this->makeRecruitingEvent();
            $role = $this->makeOpenRole($event, ['eligible_account_type' => MultiKolabEligibleAccountType::Community]);
            $expectedIds[] = $role->id;
        }

        $collectedIds = [];

        for ($page = 1; $page <= 3; $page++) {
            $response = $this->actingAs($viewer)
                ->getJson("/api/v1/discovery/opportunities?feed=all&per_page=2&page={$page}")
                ->assertOk();

            $collectedIds = array_merge(
                $collectedIds,
                collect($response->json('data.data'))->where('item_type', 'multi_kolab_role')->pluck('id')->all()
            );
        }

        $this->assertCount(6, $collectedIds);
        $this->assertCount(6, array_unique($collectedIds));
        foreach ($expectedIds as $expectedId) {
            $this->assertContains($expectedId, $collectedIds);
        }
    }

    // 11. No N+1 regression (assert query count).
    public function test_mixed_feed_does_not_trigger_n_plus_1_queries(): void
    {
        $viewer = Profile::factory()->community()->create();

        for ($i = 0; $i < 5; $i++) {
            $event = $this->makeRecruitingEvent();
            $this->makeOpenRole($event, ['eligible_account_type' => MultiKolabEligibleAccountType::Community]);
        }

        $queryCount = 0;
        DB::listen(function () use (&$queryCount): void {
            $queryCount++;
        });

        $this->actingAs($viewer)
            ->getJson('/api/v1/discovery/opportunities?feed=all')
            ->assertOk();

        DB::flushQueryLog();

        // A handful of fixed queries regardless of role count (roles + event +
        // creator + business/community profile eager loads, viewer profile
        // loads, blocked-ids lookup) — not one query per role.
        $this->assertLessThan(30, $queryCount, "Expected a bounded query count, got {$queryCount} (possible N+1).");
    }

    // 12. Ordinary Explore payloads remain backward compatible.
    public function test_ordinary_kolab_items_remain_backward_compatible(): void
    {
        $viewer = Profile::factory()->community()->create();

        $response = $this->actingAs($viewer)
            ->getJson('/api/v1/discovery/opportunities?feed=all')
            ->assertOk();

        $response->assertJsonStructure([
            'success',
            'data' => ['data', 'meta'],
            'meta',
        ]);
    }

    // 13. No subscription/Event Creator entitlement gate applied to applicants browsing eligible roles.
    public function test_browsing_multi_kolab_roles_never_requires_subscription_or_entitlement(): void
    {
        $viewer = Profile::factory()->community()->create();
        $this->assertFalse($viewer->hasEventCreatorEntitlement());

        $event = $this->makeRecruitingEvent();
        $role = $this->makeOpenRole($event, ['eligible_account_type' => MultiKolabEligibleAccountType::Community]);

        $response = $this->actingAs($viewer)
            ->getJson('/api/v1/discovery/opportunities?feed=all')
            ->assertOk();

        $ids = collect($response->json('data.data'))->where('item_type', 'multi_kolab_role')->pluck('id');
        $this->assertTrue($ids->contains($role->id));
    }

    public function test_feed_item_shape_includes_required_role_fields(): void
    {
        $viewer = Profile::factory()->community()->create();
        $event = $this->makeRecruitingEvent([
            'title' => 'Kolabing Launch Weekend',
            'city' => 'Barcelona',
            'rsvp_url' => 'https://lu.ma/kolabing-launch',
        ]);
        $role = $this->makeOpenRole($event, [
            'title' => 'Run Club Partner',
            'eligible_account_type' => MultiKolabEligibleAccountType::Community,
            'positions_needed' => 2,
            'positions_filled' => 1,
        ]);

        $response = $this->actingAs($viewer)
            ->getJson('/api/v1/discovery/opportunities?feed=all')
            ->assertOk();

        $item = collect($response->json('data.data'))->firstWhere('id', $role->id);

        $this->assertSame('multi_kolab_role', $item['item_type']);
        $this->assertSame('Run Club Partner', $item['role_title']);
        $this->assertSame('Kolabing Launch Weekend', $item['event_title']);
        $this->assertSame('Barcelona', $item['city']);
        $this->assertSame(1, $item['positions_remaining']);
        $this->assertSame('https://lu.ma/kolabing-launch', $item['rsvp']['url']);
        $this->assertArrayHasKey('match_score', $item);
        $this->assertArrayHasKey('target_date', $item);
        $this->assertArrayHasKey('compensation', $item);
    }
}
