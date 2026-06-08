<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Event;
use App\Models\EventSignup;
use App\Models\Profile;
use App\Services\CommunityService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class EventSeriesChatTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function community(Profile $leader): Community
    {
        return app(CommunityService::class)->create($leader, [
            'name' => 'Run Club', 'type' => 'greek',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** chat_mode=series → ONE thread for the whole series, reachable from any occurrence. */
    public function test_series_chat_is_one_shared_thread_for_all_occurrences(): void
    {
        Carbon::setTestNow('2026-07-01 08:00:00');
        $leader = Profile::factory()->community()->create();
        $community = $this->community($leader);

        $seriesId = $this->actingAs($leader)->postJson('/api/v1/events', [
            'community_id' => $community->id,
            'name' => 'Sunday Run',
            'starts_at' => '2026-07-05T09:00:00+00:00',
            'recurrence' => [
                'frequency' => 'weekly', 'byweekday' => [0],
                'ends_mode' => 'count', 'ends_count' => 4, 'chat_mode' => 'series',
            ],
        ])->json('series_id');

        $occ = Event::query()->where('series_id', $seriesId)->orderBy('starts_at')->get();
        $this->assertCount(4, $occ);

        // Leader opens the chat from occurrence #1, then #3 → same thread, keyed by series.
        $t1 = $this->actingAs($leader)->postJson("/api/v1/events/{$occ[0]->id}/chat")
            ->assertStatus(201)->json('data');
        $t3 = $this->actingAs($leader)->postJson("/api/v1/events/{$occ[2]->id}/chat")
            ->assertStatus(201)->json('data');

        $this->assertSame($t1['id'], $t3['id']);          // one shared thread
        $this->assertNull($t1['event_id']);                // not per-occurrence
        $this->assertSame($seriesId, $t1['series_id']);

        // A member going to occurrence #2 sees the shared thread in their inbox.
        $member = Profile::factory()->attendee()->create();
        CommunityMember::factory()->forCommunity($community)->create([
            'profile_id' => $member->id, 'tier_id' => $community->defaultTier->id, 'status' => 'active',
        ]);
        EventSignup::query()->create([
            'event_id' => $occ[1]->id, 'profile_id' => $member->id, 'status' => 'going',
        ]);

        $threads = $this->actingAs($member)->getJson('/api/v1/chats')
            ->assertStatus(200)->json('data');
        $ids = array_column($threads, 'id');
        $this->assertContains($t1['id'], $ids);

        // The member can post to the shared thread.
        $this->actingAs($member)->postJson("/api/v1/chats/{$t1['id']}/messages", [
            'content' => 'See you Sunday!',
        ])->assertStatus(201);
    }

    /** Default chat_mode=per_event → a distinct thread per occurrence. */
    public function test_per_event_chat_is_distinct_per_occurrence(): void
    {
        Carbon::setTestNow('2026-07-01 08:00:00');
        $leader = Profile::factory()->community()->create();
        $community = $this->community($leader);

        $seriesId = $this->actingAs($leader)->postJson('/api/v1/events', [
            'community_id' => $community->id,
            'name' => 'Track',
            'starts_at' => '2026-07-07T18:00:00+00:00',
            'recurrence' => [
                'frequency' => 'weekly', 'byweekday' => [2],
                'ends_mode' => 'count', 'ends_count' => 3, // chat_mode defaults per_event
            ],
        ])->json('series_id');

        $occ = Event::query()->where('series_id', $seriesId)->orderBy('starts_at')->get();
        $a = $this->actingAs($leader)->postJson("/api/v1/events/{$occ[0]->id}/chat")->json('data');
        $b = $this->actingAs($leader)->postJson("/api/v1/events/{$occ[1]->id}/chat")->json('data');

        $this->assertNotSame($a['id'], $b['id']);
        $this->assertSame($occ[0]->id, $a['event_id']);
        $this->assertNull($a['series_id']);
    }
}
