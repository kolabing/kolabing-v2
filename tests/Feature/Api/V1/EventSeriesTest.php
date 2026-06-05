<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Community;
use App\Models\Event;
use App\Models\EventSeries;
use App\Models\Profile;
use App\Services\CommunityService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class EventSeriesTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function community(Profile $leader): Community
    {
        return app(CommunityService::class)->create($leader, [
            'name' => 'Barcelona Run Club',
            'type' => 'greek',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_weekly_multiday_series_with_count_creates_exact_occurrences(): void
    {
        Carbon::setTestNow('2026-07-01 08:00:00'); // Wednesday
        $leader = Profile::factory()->community()->create();
        $community = $this->community($leader);

        $response = $this->actingAs($leader)->postJson('/api/v1/events', [
            'community_id' => $community->id,
            'name' => 'Track Session',
            'starts_at' => '2026-07-07T18:00:00+00:00', // Tuesday
            'ends_at' => '2026-07-07T19:30:00+00:00',
            'capacity' => 20,
            'recurrence' => [
                'frequency' => 'weekly',
                'byweekday' => [2, 4], // Tue + Thu = twice weekly
                'ends_mode' => 'count',
                'ends_count' => 6,
            ],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('occurrences_created', 6)
            ->assertJsonPath('data.series_id', fn ($v) => $v !== null);

        $seriesId = $response->json('series_id');
        $events = Event::query()->where('series_id', $seriesId)->orderBy('starts_at')->get();

        $this->assertCount(6, $events);
        foreach ($events as $e) {
            $this->assertContains($e->starts_at->dayOfWeek, [2, 4]);
            $this->assertSame(20, $e->capacity);
            $this->assertSame('18:00', $e->starts_at->format('H:i'));
        }
    }

    public function test_occurrences_show_in_upcoming_list(): void
    {
        Carbon::setTestNow('2026-07-01 08:00:00');
        $leader = Profile::factory()->community()->create();
        $community = $this->community($leader);

        $this->actingAs($leader)->postJson('/api/v1/events', [
            'community_id' => $community->id,
            'name' => 'Sunday Long Run',
            'starts_at' => '2026-07-05T09:00:00+00:00', // Sunday
            'recurrence' => [
                'frequency' => 'weekly', 'byweekday' => [0],
                'ends_mode' => 'count', 'ends_count' => 4,
            ],
        ])->assertStatus(201);

        $this->actingAs($leader)
            ->getJson("/api/v1/events?community_id={$community->id}&time=upcoming")
            ->assertStatus(200)
            ->assertJsonCount(4, 'data.events');
    }

    public function test_delete_series_scope_removes_all_occurrences(): void
    {
        Carbon::setTestNow('2026-07-01 08:00:00');
        $leader = Profile::factory()->community()->create();
        $community = $this->community($leader);

        $seriesId = $this->actingAs($leader)->postJson('/api/v1/events', [
            'community_id' => $community->id,
            'name' => 'Weekly Yoga',
            'starts_at' => '2026-07-06T07:00:00+00:00',
            'recurrence' => [
                'frequency' => 'weekly', 'byweekday' => [1],
                'ends_mode' => 'count', 'ends_count' => 5,
            ],
        ])->json('series_id');

        $first = Event::query()->where('series_id', $seriesId)->orderBy('starts_at')->first();

        $this->actingAs($leader)
            ->deleteJson("/api/v1/events/{$first->id}?scope=series")
            ->assertStatus(200)
            ->assertJsonPath('deleted_count', 5);

        $this->assertSame(0, Event::query()->where('series_id', $seriesId)->count());
        $this->assertDatabaseMissing('event_series', ['id' => $seriesId]);
    }

    public function test_delete_following_keeps_earlier_occurrences(): void
    {
        Carbon::setTestNow('2026-07-01 08:00:00');
        $leader = Profile::factory()->community()->create();
        $community = $this->community($leader);

        $seriesId = $this->actingAs($leader)->postJson('/api/v1/events', [
            'community_id' => $community->id,
            'name' => 'Weekly Yoga',
            'starts_at' => '2026-07-06T07:00:00+00:00',
            'recurrence' => [
                'frequency' => 'weekly', 'byweekday' => [1],
                'ends_mode' => 'count', 'ends_count' => 5,
            ],
        ])->json('series_id');

        $events = Event::query()->where('series_id', $seriesId)->orderBy('starts_at')->get();
        $third = $events[2];

        $this->actingAs($leader)
            ->deleteJson("/api/v1/events/{$third->id}?scope=following")
            ->assertStatus(200)
            ->assertJsonPath('deleted_count', 3); // 3rd, 4th, 5th

        $this->assertSame(2, Event::query()->where('series_id', $seriesId)->count());
        $this->assertDatabaseHas('event_series', ['id' => $seriesId]); // still alive
    }

    public function test_extend_materialises_more_as_time_passes(): void
    {
        Carbon::setTestNow('2026-07-01 08:00:00');
        $leader = Profile::factory()->community()->create();
        $community = $this->community($leader);

        // Never-ending weekly → first pass materialises ~3 months.
        $seriesId = $this->actingAs($leader)->postJson('/api/v1/events', [
            'community_id' => $community->id,
            'name' => 'Sunday Run',
            'starts_at' => '2026-07-05T09:00:00+00:00',
            'recurrence' => ['frequency' => 'weekly', 'byweekday' => [0], 'ends_mode' => 'never'],
        ])->json('series_id');

        $initial = Event::query()->where('series_id', $seriesId)->count();
        $this->assertGreaterThanOrEqual(12, $initial); // ~13 Sundays in 3 months

        // Move 3 months forward, extend → more occurrences appear.
        Carbon::setTestNow('2026-10-01 08:00:00');
        $series = EventSeries::query()->find($seriesId);
        $this->actingAs($leader)
            ->postJson("/api/v1/event-series/{$series->id}/extend")
            ->assertStatus(200)
            ->assertJsonPath('occurrences_created', fn ($v) => $v > 0);

        $this->assertGreaterThan($initial, Event::query()->where('series_id', $seriesId)->count());
    }

    public function test_non_manager_cannot_create_recurring_event(): void
    {
        $leader = Profile::factory()->community()->create();
        $community = $this->community($leader);
        $outsider = Profile::factory()->community()->create();

        $this->actingAs($outsider)->postJson('/api/v1/events', [
            'community_id' => $community->id,
            'name' => 'Sneaky Series',
            'starts_at' => now()->addDays(2)->toIso8601String(),
            'recurrence' => ['frequency' => 'weekly', 'byweekday' => [0], 'ends_mode' => 'count', 'ends_count' => 3],
        ])->assertStatus(403);
    }
}
