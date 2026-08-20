<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\KolabStatus;
use App\Models\Event;
use App\Models\EventPhoto;
use App\Models\Kolab;
use App\Models\Profile;
use App\Services\ProfileService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PastEventsMergeTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @return array<int, array<string, mixed>>
     */
    private function pastEvents(Profile $profile): array
    {
        return app(ProfileService::class)
            ->getPublicProfileDetail($profile)
            ->getAttribute('community_public_past_events');
    }

    private function eventFor(Profile $profile, string $name, string $date, int $photos = 0): Event
    {
        $event = Event::factory()->create([
            'profile_id' => $profile->id,
            'name' => $name,
            'event_date' => $date,
            'attendee_count' => 42,
        ]);

        for ($i = 0; $i < $photos; $i++) {
            EventPhoto::query()->create([
                'event_id' => $event->id,
                'url' => "https://example.com/{$event->id}-{$i}.jpg",
                'sort_order' => $i,
            ]);
        }

        return $event;
    }

    /**
     * @param  array<int, array<string, mixed>>  $pastEvents
     */
    private function kolabWith(Profile $profile, array $pastEvents): Kolab
    {
        return Kolab::factory()->create([
            'creator_profile_id' => $profile->id,
            'status' => KolabStatus::Published,
            'past_events' => $pastEvents,
        ]);
    }

    public function test_events_table_rows_now_appear_in_the_public_past_events(): void
    {
        $profile = Profile::factory()->community()->create();
        $this->eventFor($profile, 'Rooftop Session', '2026-05-01', photos: 2);

        $events = $this->pastEvents($profile);

        $this->assertCount(1, $events);
        $this->assertSame('event', $events[0]['source']);
        $this->assertSame('Rooftop Session', $events[0]['name']);
        $this->assertSame(42, $events[0]['attendee_count']);
        $this->assertNull($events[0]['source_kolab_id']);
        $this->assertNotNull($events[0]['source_event_id']);
        $this->assertCount(2, $events[0]['media']);
    }

    public function test_kolab_sourced_entries_still_appear_with_their_original_keys(): void
    {
        $profile = Profile::factory()->community()->create();
        $this->kolabWith($profile, [[
            'name' => 'Winter Market',
            'date' => '2026-01-10',
            'partner_name' => 'Cafe Nord',
            'photos' => ['https://example.com/winter.jpg'],
        ]]);

        $events = $this->pastEvents($profile);

        $this->assertSame('kolab', $events[0]['source']);
        $this->assertSame('Winter Market', $events[0]['name']);
        $this->assertSame('Cafe Nord', $events[0]['partner_name']);
        $this->assertNull($events[0]['source_event_id']);
        $this->assertNull($events[0]['attendee_count']);
        $this->assertNotNull($events[0]['source_kolab_id']);
    }

    public function test_both_sources_are_returned_newest_first(): void
    {
        $profile = Profile::factory()->community()->create();
        $this->eventFor($profile, 'Older Event', '2026-02-01');
        $this->kolabWith($profile, [['name' => 'Newer Kolab Entry', 'date' => '2026-06-01']]);

        $names = array_column($this->pastEvents($profile), 'name');

        $this->assertSame(['Newer Kolab Entry', 'Older Event'], $names);
    }

    public function test_entries_without_a_date_sort_last(): void
    {
        $profile = Profile::factory()->community()->create();
        $this->kolabWith($profile, [['name' => 'Undated', 'date' => null]]);
        $this->eventFor($profile, 'Dated', '2026-03-01');

        $names = array_column($this->pastEvents($profile), 'name');

        $this->assertSame(['Dated', 'Undated'], $names);
    }

    public function test_the_same_name_and_date_dedupes_to_the_event_sourced_copy(): void
    {
        // A leader who logged the evening in both places must not see it twice.
        $profile = Profile::factory()->community()->create();
        $this->eventFor($profile, 'Launch Party', '2026-04-04', photos: 1);
        $this->kolabWith($profile, [['name' => 'launch party', 'date' => '2026-04-04']]);

        $events = $this->pastEvents($profile);

        $this->assertCount(1, $events);
        $this->assertSame('event', $events[0]['source']);
    }

    public function test_upcoming_events_are_not_included(): void
    {
        $profile = Profile::factory()->community()->create();
        Event::factory()->create([
            'profile_id' => $profile->id,
            'name' => 'Next Month',
            'event_date' => now()->addMonth()->toDateString(),
        ]);

        $this->assertSame([], $this->pastEvents($profile));
    }

    public function test_another_profiles_events_are_not_included(): void
    {
        $profile = Profile::factory()->community()->create();
        $this->eventFor(Profile::factory()->community()->create(), 'Not Mine', '2026-05-01');

        $this->assertSame([], $this->pastEvents($profile));
    }

    public function test_past_events_count_follows_the_merged_list(): void
    {
        $profile = Profile::factory()->community()->create();
        $this->eventFor($profile, 'A', '2026-05-01');
        $this->eventFor($profile, 'B', '2026-05-02');

        $stats = app(ProfileService::class)
            ->getPublicProfileDetail($profile)
            ->getAttribute('community_public_stats');

        $this->assertSame(2, $stats['past_events_count']);
    }

    public function test_a_business_profile_gets_the_same_merge(): void
    {
        $profile = Profile::factory()->business()->create();
        $this->eventFor($profile, 'Tasting Night', '2026-05-01', photos: 1);

        $events = $this->pastEvents($profile);

        $this->assertCount(1, $events);
        $this->assertSame('event', $events[0]['source']);
    }

    public function test_the_merge_costs_the_same_number_of_queries_at_any_size(): void
    {
        $small = Profile::factory()->community()->create();
        $this->eventFor($small, 'S1', '2026-05-01', photos: 1);

        $large = Profile::factory()->community()->create();
        for ($i = 0; $i < 15; $i++) {
            $this->eventFor($large, "L{$i}", '2026-05-01', photos: 2);
        }

        $count = function (Profile $profile): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            app(ProfileService::class)->getPublicProfileDetail($profile);
            $queries = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $queries;
        };

        $this->assertSame($count($small), $count($large));
    }
}
