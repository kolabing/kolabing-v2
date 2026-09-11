<?php

declare(strict_types=1);

namespace Tests\Feature\Suggestions;

use App\Jobs\SendPostHogEvent;
use App\Models\BusinessProfile;
use App\Models\KolabSuggestion;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * The funnel telemetry (BE-NF-39 §3.9). The chain the business case rests on is
 * `suggestion_shown → suggestion_clicked → suggestion_converted`, plus
 * `suggestion_dismissed` as the negative branch, and it is only readable if two
 * things hold:
 *
 * 1. **Every event carries `audience`.** Without it a business-side win and a
 *    community-side flop average into one meaningless number, which is the whole
 *    reason a two-sided launch needs the tag.
 * 2. **No event carries the counterpart's identity.** A blurred card withholds
 *    the counterpart's name *and* id on purpose (SuggestionResource); telemetry
 *    must not become the second way out.
 *
 * Volume is asserted too: `suggestion_shown` fires on a card's *first*
 * impression only, matching what `shown_at` means, so re-serving a page emits
 * nothing.
 */
class SuggestionTelemetryTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        config([
            'suggestions.enabled' => true,
            'posthog.enabled' => true,
            'posthog.project_api_key' => 'phc_test',
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function eventsNamed(string $event): array
    {
        return Queue::pushed(SendPostHogEvent::class)
            ->filter(fn (SendPostHogEvent $job): bool => $job->event === $event)
            ->map(fn (SendPostHogEvent $job): array => $job->properties)
            ->values()
            ->all();
    }

    /**
     * @return array<int, SendPostHogEvent>
     */
    private function suggestionJobs(): array
    {
        return Queue::pushed(SendPostHogEvent::class)
            ->filter(fn (SendPostHogEvent $job): bool => str_starts_with($job->event, 'suggestion_'))
            ->values()
            ->all();
    }

    private function suggestion(Profile $viewer, ?Profile $counterpart = null, array $attributes = []): KolabSuggestion
    {
        return KolabSuggestion::factory()
            ->forPair($viewer, $counterpart ?? Profile::factory()->community()->create())
            ->create($attributes);
    }

    /**
     * @return array<string, mixed>
     */
    private function kolabPayload(array $overrides = []): array
    {
        return array_merge([
            'intent_type' => 'community_seeking',
            'title' => 'Sunday run club looking for a coffee stop',
            'description' => 'We finish every Sunday route near Gracia and want a cafe to host the post-run coffee.',
            'preferred_city' => 'Barcelona',
            'needs' => ['venue'],
            'typical_attendance' => 40,
            'offers_in_return' => ['social_media'],
        ], $overrides);
    }

    /*
    |--------------------------------------------------------------------------
    | shown
    |--------------------------------------------------------------------------
    */

    public function test_serving_the_feed_emits_one_shown_event_per_card_tagged_with_audience(): void
    {
        $business = Profile::factory()->business()->create();
        $this->suggestion($business);
        $this->suggestion($business);

        $this->actingAs($business)->getJson('/api/v1/suggestions')->assertOk();

        $events = $this->eventsNamed('suggestion_shown');

        $this->assertCount(2, $events);

        foreach ($events as $properties) {
            $this->assertSame('business', $properties['audience']);
            $this->assertArrayHasKey('suggestion_id', $properties);
            $this->assertIsInt($properties['score']);
            $this->assertContains($properties['confidence'], ['low', 'medium', 'high']);
            $this->assertSame(['category_fit'], $properties['signal_keys']);
        }
    }

    public function test_a_community_viewer_is_tagged_with_the_community_audience(): void
    {
        $community = Profile::factory()->community()->create();
        $this->suggestion($community, Profile::factory()->business()->create());

        $this->actingAs($community)->getJson('/api/v1/suggestions')->assertOk();

        $events = $this->eventsNamed('suggestion_shown');

        $this->assertCount(1, $events);
        $this->assertSame('community', $events[0]['audience']);
    }

    /**
     * The volume guard. `shown_at` records the first impression, so the event has
     * to mean the same thing — otherwise the funnel has two different
     * denominators for one step, and a card scrolled past ten times inflates the
     * top of the funnel tenfold.
     */
    public function test_re_serving_the_same_card_emits_no_further_shown_event(): void
    {
        $business = Profile::factory()->business()->create();
        $this->suggestion($business);

        $this->actingAs($business)->getJson('/api/v1/suggestions')->assertOk();
        $this->actingAs($business)->getJson('/api/v1/suggestions')->assertOk();

        $this->assertCount(1, $this->eventsNamed('suggestion_shown'));
    }

    /*
    |--------------------------------------------------------------------------
    | clicked
    |--------------------------------------------------------------------------
    */

    public function test_opening_a_suggestion_emits_a_clicked_event_once(): void
    {
        $business = Profile::factory()->business()->create();
        $suggestion = $this->suggestion($business);

        $this->actingAs($business)->getJson("/api/v1/suggestions/{$suggestion->id}")->assertOk();
        $this->actingAs($business)->getJson("/api/v1/suggestions/{$suggestion->id}")->assertOk();

        $events = $this->eventsNamed('suggestion_clicked');

        $this->assertCount(1, $events);
        $this->assertSame('business', $events[0]['audience']);
        $this->assertSame($suggestion->id, $events[0]['suggestion_id']);
    }

    /*
    |--------------------------------------------------------------------------
    | dismissed
    |--------------------------------------------------------------------------
    */

    public function test_dismissing_records_whether_the_card_had_been_opened(): void
    {
        $business = Profile::factory()->business()->create();
        $glanced = $this->suggestion($business);
        $read = $this->suggestion($business, null, ['clicked_at' => now()->subMinute()]);

        $this->actingAs($business)->postJson("/api/v1/suggestions/{$glanced->id}/dismiss")->assertNoContent();
        $this->actingAs($business)->postJson("/api/v1/suggestions/{$read->id}/dismiss")->assertNoContent();

        $events = collect($this->eventsNamed('suggestion_dismissed'))->keyBy('suggestion_id');

        $this->assertCount(2, $events);
        $this->assertFalse($events[$glanced->id]['was_clicked']);
        $this->assertTrue($events[$read->id]['was_clicked']);
    }

    public function test_dismissing_twice_emits_a_single_dismissed_event(): void
    {
        $business = Profile::factory()->business()->create();
        $suggestion = $this->suggestion($business);

        $this->actingAs($business)->postJson("/api/v1/suggestions/{$suggestion->id}/dismiss")->assertNoContent();
        $this->actingAs($business)->postJson("/api/v1/suggestions/{$suggestion->id}/dismiss")->assertNoContent();

        $this->assertCount(1, $this->eventsNamed('suggestion_dismissed'));
    }

    /*
    |--------------------------------------------------------------------------
    | converted — the step the business case rests on
    |--------------------------------------------------------------------------
    */

    public function test_converting_a_suggestion_emits_a_converted_event_naming_the_kolab(): void
    {
        $community = Profile::factory()->community()->create();
        $suggestion = $this->suggestion($community, Profile::factory()->business()->create());

        $response = $this->actingAs($community)
            ->postJson('/api/v1/kolabs', $this->kolabPayload(['suggestion_id' => $suggestion->id]));

        $response->assertStatus(201);

        $events = $this->eventsNamed('suggestion_converted');

        $this->assertCount(1, $events);
        $this->assertSame('community', $events[0]['audience']);
        $this->assertSame($suggestion->id, $events[0]['suggestion_id']);
        $this->assertSame($response->json('data.id'), $events[0]['kolab_id']);
        $this->assertSame('community_seeking', $events[0]['intent_type']);
    }

    public function test_creating_a_kolab_without_a_suggestion_emits_no_converted_event(): void
    {
        $community = Profile::factory()->community()->create();
        $this->suggestion($community, Profile::factory()->business()->create());

        $this->actingAs($community)
            ->postJson('/api/v1/kolabs', $this->kolabPayload())
            ->assertStatus(201);

        $this->assertSame([], $this->eventsNamed('suggestion_converted'));
    }

    /*
    |--------------------------------------------------------------------------
    | What must never reach PostHog
    |--------------------------------------------------------------------------
    */

    /**
     * The identity the blurred card withholds must not leak through the side
     * door. Asserted over every property *value* of all four events, not just
     * over the key names, so a counterpart name smuggled in under an innocent
     * key still fails.
     */
    public function test_no_suggestion_event_carries_the_counterpart_identity(): void
    {
        $community = Profile::factory()->community()->create();
        $counterpart = Profile::factory()->business()->create([
            'avatar_url' => 'https://cdn.test/business-avatar.png',
        ]);
        BusinessProfile::factory()->create([
            'profile_id' => $counterpart->id,
            'name' => 'Cafe Central Barcelona',
        ]);

        /* One row, all four steps — `kolab_suggestions` allows one row per pair. */
        $suggestion = $this->suggestion($community, $counterpart);

        $this->actingAs($community)->getJson('/api/v1/suggestions')->assertOk();
        $this->actingAs($community)->getJson("/api/v1/suggestions/{$suggestion->id}")->assertOk();
        $this->actingAs($community)
            ->postJson('/api/v1/kolabs', $this->kolabPayload(['suggestion_id' => $suggestion->id]))
            ->assertStatus(201);
        $this->actingAs($community)->postJson("/api/v1/suggestions/{$suggestion->id}/dismiss")->assertNoContent();

        $jobs = $this->suggestionJobs();

        $this->assertCount(4, $jobs, 'all four funnel steps should have fired');

        foreach ($jobs as $job) {
            $this->assertArrayNotHasKey('counterpart_profile_id', $job->properties);

            $flat = json_encode($job->properties, JSON_THROW_ON_ERROR);

            $this->assertStringNotContainsString($counterpart->id, $flat);
            $this->assertStringNotContainsString('Cafe Central', $flat);
            $this->assertStringNotContainsString('cdn.test', $flat);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | The telemetry must never cost the request anything, nor override consent
    |--------------------------------------------------------------------------
    */

    /**
     * Queued, never inline — a feed serve must not wait on PostHog's HTTP API.
     *
     * The default queue connection here is `sync` (phpunit.xml), which is exactly
     * the case worth asserting: SendPostHogEvent overrides it to `database`, so
     * even a sync deployment cannot end up making the API call inside the
     * request. Asserting merely "not sync" would pass on a null connection and
     * prove nothing, so the override is pinned by name.
     */
    public function test_suggestion_events_are_queued_rather_than_sent_inline(): void
    {
        $this->assertSame('sync', config('queue.default'));

        $business = Profile::factory()->business()->create();
        $this->suggestion($business);

        $this->actingAs($business)->getJson('/api/v1/suggestions')->assertOk();

        $jobs = $this->suggestionJobs();

        $this->assertNotEmpty($jobs);

        foreach ($jobs as $job) {
            $this->assertInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class, $job);
            $this->assertSame('database', $job->connection);
        }
    }

    /**
     * Consent is not the telemetry's decision to make, but it is the telemetry's
     * decision to *route around*: PostHogService only honours
     * `analytics_opt_out` when it is handed a Profile, so an event captured
     * against a bare id string would silently ignore the opt-out.
     */
    public function test_a_viewer_who_opted_out_of_analytics_gets_no_events(): void
    {
        $business = Profile::factory()->business()->create(['analytics_opt_out' => true]);
        $suggestion = $this->suggestion($business);

        $this->actingAs($business)->getJson('/api/v1/suggestions')->assertOk();
        $this->actingAs($business)->getJson("/api/v1/suggestions/{$suggestion->id}")->assertOk();
        $this->actingAs($business)->postJson("/api/v1/suggestions/{$suggestion->id}/dismiss")->assertNoContent();

        $this->assertSame([], $this->suggestionJobs());
    }

    public function test_the_feed_still_serves_when_posthog_is_not_configured(): void
    {
        config(['posthog.enabled' => false]);

        $business = Profile::factory()->business()->create();
        $suggestion = $this->suggestion($business);

        $this->actingAs($business)->getJson('/api/v1/suggestions')
            ->assertOk()
            ->assertJsonPath('data.meta.total', 1);

        $this->assertNotNull($suggestion->fresh()->shown_at);
        $this->assertSame([], $this->suggestionJobs());
    }
}
