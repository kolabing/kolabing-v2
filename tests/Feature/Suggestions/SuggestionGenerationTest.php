<?php

declare(strict_types=1);

namespace Tests\Feature\Suggestions;

use App\Enums\SuggestionAudience;
use App\Jobs\GenerateSuggestionsForProfile;
use App\Models\BusinessProfile;
use App\Models\City;
use App\Models\CommunityProfile;
use App\Models\EventSeries;
use App\Models\KolabSuggestion;
use App\Models\Profile;
use App\Services\Suggestions\PairCandidateFinder;
use App\Services\Suggestions\SuggestionGenerator;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class SuggestionGenerationTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['suggestions.enabled' => true]);
    }

    private function generator(): SuggestionGenerator
    {
        return app(SuggestionGenerator::class);
    }

    private function business(
        City $city,
        array $categories = ['cafe'],
        ?array $venue = null,
        bool $hasVenue = true,
    ): Profile {
        $profile = Profile::factory()->business()->create();

        BusinessProfile::factory()->create([
            'profile_id' => $profile->id,
            'city_id' => $city->id,
            'business_type' => $categories[0] ?? null,
            'categories' => $categories,
            'primary_venue' => $venue,
            'has_venue' => $hasVenue,
        ]);

        return $profile->fresh();
    }

    private function community(City $city, string $type = 'food_community', ?int $size = 200): Profile
    {
        $profile = Profile::factory()->community()->create();

        CommunityProfile::factory()->create([
            'profile_id' => $profile->id,
            'city_id' => $city->id,
            'community_type' => $type,
            'community_size' => $size,
        ]);

        return $profile->fresh();
    }

    /**
     * @return array<int, string>
     */
    private function counterpartIdsFor(Profile $viewer): array
    {
        return KolabSuggestion::query()
            ->where('viewer_profile_id', $viewer->id)
            ->orderByDesc('score')
            ->pluck('counterpart_profile_id')
            ->all();
    }

    /**
     * The query log rather than `DB::listen`: this is called twice in one test
     * and a listener cannot be removed once registered, so the second call would
     * be counted by both.
     */
    private function countQueries(callable $callback): int
    {
        DB::enableQueryLog();
        DB::flushQueryLog();

        $callback();

        $count = count(DB::getQueryLog());

        DB::disableQueryLog();

        return $count;
    }

    /**
     * `cafe` against these seven community types produces seven distinct
     * category-fit scores (1.0, 0.87, 0.82, 0.80, 0.78, 0.74, 0.70), which with
     * same-city location fit and nothing else land at 100, 92, 89, 87, 86, 84
     * and 81. That spread is what makes "keeps the highest-scoring" a real
     * assertion rather than "keeps any five".
     */
    public function test_generates_up_to_the_configured_number_per_profile(): void
    {
        config(['suggestions.per_profile' => 5]);

        $city = City::factory()->create();
        $viewer = $this->business($city);

        $best = $this->community($city, 'food_community');          // 100
        $second = $this->community($city, 'run_club');              // 92
        $third = $this->community($city, 'fitness_community');      // 89
        $fourth = $this->community($city, 'student_community');     // 87
        $fifth = $this->community($city, 'wellness_community');     // 86
        $sixth = $this->community($city, 'professional_networking_community'); // 84
        $seventh = $this->community($city, 'tech_startup_community');          // 81

        $written = $this->generator()->generateFor($viewer);

        $this->assertSame(5, $written);
        $this->assertSame(
            [$best->id, $second->id, $third->id, $fourth->id, $fifth->id],
            $this->counterpartIdsFor($viewer)
        );
        $this->assertDatabaseMissing('kolab_suggestions', [
            'viewer_profile_id' => $viewer->id,
            'counterpart_profile_id' => $sixth->id,
        ]);
        $this->assertDatabaseMissing('kolab_suggestions', [
            'viewer_profile_id' => $viewer->id,
            'counterpart_profile_id' => $seventh->id,
        ]);
    }

    public function test_the_per_profile_cap_is_read_from_config(): void
    {
        config(['suggestions.per_profile' => 2]);

        $city = City::factory()->create();
        $viewer = $this->business($city);
        $this->community($city, 'food_community');
        $this->community($city, 'run_club');
        $this->community($city, 'fitness_community');

        $this->assertSame(2, $this->generator()->generateFor($viewer));
        $this->assertSame(2, KolabSuggestion::query()->count());
    }

    /**
     * Two identical communities score identically, and the cap has to drop one
     * of them. Which one cannot be left to the order the candidates came back
     * in: the same pair is re-scored in place every night, so an order-dependent
     * tie-break would move a card on and off the cap from night to night with
     * nothing behind it. Proven by feeding the same pool in both orders.
     */
    public function test_a_tie_is_broken_deterministically_whatever_order_the_candidates_arrive_in(): void
    {
        config(['suggestions.per_profile' => 1]);

        $city = City::factory()->create();
        $viewer = $this->business($city);
        $this->community($city, 'food_community');
        $this->community($city, 'food_community');

        $this->generator()->generateFor($viewer);
        $inOrder = $this->counterpartIdsFor($viewer);

        KolabSuggestion::query()->delete();

        $this->app->bind(PairCandidateFinder::class, fn (): PairCandidateFinder => new class extends PairCandidateFinder
        {
            public function candidatesFor(Profile $viewer, SuggestionAudience $audience): array
            {
                return array_reverse(parent::candidatesFor($viewer, $audience));
            }
        });

        app(SuggestionGenerator::class)->generateFor($viewer);

        $this->assertCount(1, $inOrder);
        $this->assertSame($inOrder, $this->counterpartIdsFor($viewer));
    }

    public function test_is_idempotent_within_a_batch(): void
    {
        $city = City::factory()->create();
        $viewer = $this->business($city);
        $this->community($city, 'food_community');
        $this->community($city, 'run_club');

        $this->generator()->generateFor($viewer);
        $firstPass = KolabSuggestion::query()->pluck('id')->sort()->values()->all();

        $this->generator()->generateFor($viewer);

        $this->assertCount(2, $firstPass);
        $this->assertSame(
            $firstPass,
            KolabSuggestion::query()->pluck('id')->sort()->values()->all()
        );
    }

    public function test_a_rerun_preserves_funnel_timestamps(): void
    {
        $city = City::factory()->create();
        $viewer = $this->business($city);
        $this->community($city, 'food_community');

        $this->generator()->generateFor($viewer);

        $shownAt = now()->subHours(3)->startOfSecond();
        $clickedAt = now()->subHours(2)->startOfSecond();

        KolabSuggestion::query()->update([
            'shown_at' => $shownAt,
            'clicked_at' => $clickedAt,
        ]);

        $this->generator()->generateFor($viewer);

        $row = KolabSuggestion::query()->sole();

        $this->assertTrue($shownAt->equalTo($row->shown_at), 'shown_at was overwritten by the re-score.');
        $this->assertTrue($clickedAt->equalTo($row->clicked_at), 'clicked_at was overwritten by the re-score.');
    }

    /**
     * The same-day re-run alone would pass even under the wrong unique key
     * (`viewer, counterpart, batch_key`), because the batch key would match. A
     * later night is the case that actually pins the shape: the row must be
     * refreshed in place with a new `batch_key`, not duplicated beside the old
     * one while both are still inside their 14-day expiry.
     */
    public function test_a_later_night_refreshes_the_same_row_rather_than_adding_one(): void
    {
        $city = City::factory()->create();
        $viewer = $this->business($city);
        $this->community($city, 'food_community');

        $this->generator()->generateFor($viewer);

        $original = KolabSuggestion::query()->sole();
        $firstBatchKey = $original->batch_key->toDateString();

        $original->forceFill(['shown_at' => now()->subMinutes(5)->startOfSecond()])->save();
        $shownAt = $original->fresh()->shown_at;

        $this->travel(3)->days();

        $this->generator()->generateFor($viewer);

        $this->assertSame(1, KolabSuggestion::query()->count());

        $refreshed = KolabSuggestion::query()->sole();

        $this->assertSame($original->id, $refreshed->id);
        $this->assertNotSame($firstBatchKey, $refreshed->batch_key->toDateString());
        $this->assertSame(now()->toDateString(), $refreshed->batch_key->toDateString());
        $this->assertTrue($shownAt->equalTo($refreshed->shown_at), 'shown_at did not survive a later batch.');
        $this->assertTrue($refreshed->expires_at->isAfter(now()), 'expires_at did not move forward.');
    }

    /**
     * A coworking viewer with a ten-person room against a two-thousand-strong
     * food community: category fit 0.22, location 1.0, scale 0.0 → 37, under the
     * shipped floor of 45. The tech community fills the same room exactly and
     * scores 100. Nothing here touches config, so this is the floor as shipped.
     */
    public function test_pairs_below_the_minimum_score_are_not_written(): void
    {
        $city = City::factory()->create();
        $viewer = $this->business($city, ['coworking'], ['capacity' => 10]);

        $kept = $this->community($city, 'tech_startup_community', 40);
        $dropped = $this->community($city, 'food_community', 2000);

        $written = $this->generator()->generateFor($viewer);

        $this->assertSame(1, $written);
        $this->assertSame([$kept->id], $this->counterpartIdsFor($viewer));
        $this->assertDatabaseMissing('kolab_suggestions', [
            'counterpart_profile_id' => $dropped->id,
        ]);
    }

    public function test_the_minimum_score_is_read_from_config(): void
    {
        $city = City::factory()->create();
        $viewer = $this->business($city);

        $best = $this->community($city, 'food_community');   // 100
        $middling = $this->community($city, 'run_club');     // 92

        config(['suggestions.min_score' => 95]);

        $written = $this->generator()->generateFor($viewer);

        $this->assertSame(1, $written);
        $this->assertSame([$best->id], $this->counterpartIdsFor($viewer));
        $this->assertDatabaseMissing('kolab_suggestions', [
            'counterpart_profile_id' => $middling->id,
        ]);
    }

    /**
     * The finder's half of the cooldown: a pair dismissed yesterday is not a
     * candidate, so the row keeps its dismissal and is not re-scored.
     */
    public function test_a_pair_dismissed_inside_the_cooldown_is_not_refreshed(): void
    {
        $city = City::factory()->create();
        $viewer = $this->business($city);
        $counterpart = $this->community($city, 'food_community');

        $dismissedAt = now()->subDay()->startOfSecond();

        $row = KolabSuggestion::factory()->forPair($viewer, $counterpart)->create([
            'batch_key' => now()->subDays(2)->toDateString(),
            'dismissed_at' => $dismissedAt,
            'score' => 51,
        ]);

        $written = $this->generator()->generateFor($viewer);

        $row->refresh();

        $this->assertSame(0, $written);
        $this->assertTrue($dismissedAt->equalTo($row->dismissed_at), 'A live dismissal was cleared.');
        $this->assertSame(51, $row->score, 'A pair inside the cooldown was re-scored.');
        $this->assertSame(now()->subDays(2)->toDateString(), $row->batch_key->toDateString());
    }

    /**
     * The generator's half: past the cooldown the pair is a candidate again, and
     * the refresh must clear `dismissed_at`. Leaving it set would make the
     * cooldown a permanent block — the row would be refreshed forever and never
     * be readable, because `scopeLive` filters on `dismissed_at`.
     */
    public function test_a_dismissal_older_than_the_cooldown_is_cleared_on_refresh(): void
    {
        $city = City::factory()->create();
        $viewer = $this->business($city);
        $counterpart = $this->community($city, 'food_community');

        $cooldownDays = (int) config('suggestions.dismissal_cooldown_days');

        $row = KolabSuggestion::factory()->forPair($viewer, $counterpart)->create([
            'batch_key' => now()->subDays($cooldownDays + 2)->toDateString(),
            'dismissed_at' => now()->subDays($cooldownDays + 1),
            'score' => 51,
        ]);

        $written = $this->generator()->generateFor($viewer);

        $row->refresh();

        $this->assertSame(1, $written);
        $this->assertSame(1, KolabSuggestion::query()->count());
        $this->assertNull($row->dismissed_at, 'The cooldown never expires — dismissed_at was not cleared.');
        $this->assertSame(100, $row->score);
    }

    /**
     * A community whose declared size is negative violates a `PairContext`
     * invariant, which throws. That pair must be dropped without taking the
     * profile's other suggestions with it.
     */
    public function test_a_pair_whose_context_fails_its_invariants_is_skipped(): void
    {
        $city = City::factory()->create();
        $viewer = $this->business($city);

        $poison = $this->community($city, 'food_community');
        CommunityProfile::query()->where('profile_id', $poison->id)->update(['community_size' => -5]);

        $healthy = $this->community($city, 'run_club');

        $written = $this->generator()->generateFor($viewer);

        $this->assertSame(1, $written);
        $this->assertSame([$healthy->id], $this->counterpartIdsFor($viewer));
    }

    /**
     * `FormatSuggester` rejects an already-ISO weekday rather than shifting it,
     * and `event_series.byweekday` is written by callers that could get the
     * convention wrong. A 7 in that column must cost one card, not a profile.
     */
    public function test_a_pair_whose_format_cannot_be_built_is_skipped(): void
    {
        $city = City::factory()->create();
        $viewer = $this->business($city);

        $poison = $this->community($city, 'food_community');
        EventSeries::factory()->forProfile($poison)->create(['byweekday' => [7]]);

        $healthy = $this->community($city, 'run_club');

        $written = $this->generator()->generateFor($viewer);

        $this->assertSame(1, $written);
        $this->assertSame([$healthy->id], $this->counterpartIdsFor($viewer));
    }

    public function test_one_failing_profile_does_not_abort_the_batch(): void
    {
        $city = City::factory()->create();

        $poison = $this->business($city);
        $healthy = $this->business($city);
        $this->community($city, 'food_community');

        $poisonId = (string) $poison->id;

        $this->app->bind(PairCandidateFinder::class, fn (): PairCandidateFinder => new class($poisonId) extends PairCandidateFinder
        {
            public function __construct(private readonly string $poisonId) {}

            public function candidatesFor(Profile $viewer, SuggestionAudience $audience): array
            {
                if ((string) $viewer->getKey() === $this->poisonId) {
                    throw new RuntimeException('candidate lookup exploded');
                }

                return parent::candidatesFor($viewer, $audience);
            }
        });

        $this->artisan('app:generate-suggestions')
            ->assertExitCode(0)
            ->run();

        $this->assertSame(0, KolabSuggestion::query()->where('viewer_profile_id', $poison->id)->count());
        $this->assertSame(1, KolabSuggestion::query()->where('viewer_profile_id', $healthy->id)->count());
    }

    public function test_writes_both_audiences(): void
    {
        $city = City::factory()->create();
        $business = $this->business($city);
        $community = $this->community($city, 'food_community');

        $this->artisan('app:generate-suggestions')->assertExitCode(0)->run();

        $this->assertDatabaseHas('kolab_suggestions', [
            'audience' => SuggestionAudience::Business->value,
            'viewer_profile_id' => $business->id,
            'counterpart_profile_id' => $community->id,
        ]);
        $this->assertDatabaseHas('kolab_suggestions', [
            'audience' => SuggestionAudience::Community->value,
            'viewer_profile_id' => $community->id,
            'counterpart_profile_id' => $business->id,
        ]);
    }

    public function test_the_command_is_a_noop_when_the_feature_flag_is_off(): void
    {
        config(['suggestions.enabled' => false]);

        $city = City::factory()->create();
        $this->business($city);
        $this->community($city, 'food_community');

        $this->artisan('app:generate-suggestions')
            ->expectsOutputToContain('suggestions.enabled')
            ->assertExitCode(0)
            ->run();

        $this->assertSame(0, KolabSuggestion::query()->count());
    }

    public function test_an_attendee_profile_produces_nothing(): void
    {
        $city = City::factory()->create();
        $attendee = Profile::factory()->attendee()->create(['city_id' => $city->id]);
        $this->business($city);
        $this->community($city, 'food_community');

        $this->assertSame(0, $this->generator()->generateFor($attendee));

        $this->artisan('app:generate-suggestions')->assertExitCode(0)->run();

        $this->assertSame(0, KolabSuggestion::query()->where('viewer_profile_id', $attendee->id)->count());
    }

    public function test_the_profile_option_restricts_the_batch_to_one_viewer(): void
    {
        $city = City::factory()->create();
        $chosen = $this->business($city);
        $other = $this->business($city);
        $this->community($city, 'food_community');

        $this->artisan('app:generate-suggestions', ['--profile' => $chosen->id])
            ->assertExitCode(0)
            ->run();

        $this->assertSame(1, KolabSuggestion::query()->where('viewer_profile_id', $chosen->id)->count());
        $this->assertSame(0, KolabSuggestion::query()->where('viewer_profile_id', $other->id)->count());
    }

    public function test_a_dry_run_reports_without_writing(): void
    {
        $city = City::factory()->create();
        $this->business($city);
        $this->community($city, 'food_community');

        $this->artisan('app:generate-suggestions', ['--dry-run' => true])
            ->assertExitCode(0)
            ->run();

        $this->assertSame(0, KolabSuggestion::query()->count());
    }

    /**
     * The generation cost must be a function of the audience, not of the size of
     * the candidate pool — the nightly pass runs it for every business and
     * community on the platform. `per_profile` is pinned at 1 so the number of
     * *writes* is constant between the two measurements and only the pool grows.
     */
    public function test_the_query_count_does_not_grow_with_the_candidate_pool(): void
    {
        config(['suggestions.per_profile' => 1]);

        $smallCity = City::factory()->create();
        $smallViewer = $this->business($smallCity);
        $this->community($smallCity, 'food_community');
        $this->community($smallCity, 'run_club');

        $largeCity = City::factory()->create();
        $largeViewer = $this->business($largeCity);
        foreach (['food_community', 'run_club', 'fitness_community', 'student_community', 'wellness_community', 'tech_startup_community'] as $type) {
            $this->community($largeCity, $type);
        }

        $small = $this->countQueries(fn () => $this->generator()->generateFor($smallViewer));
        $large = $this->countQueries(fn () => $this->generator()->generateFor($largeViewer));

        $this->assertSame(2, CommunityProfile::query()->where('city_id', $smallCity->id)->count());
        $this->assertSame(6, CommunityProfile::query()->where('city_id', $largeCity->id)->count());
        $this->assertSame(
            $small,
            $large,
            "Generation cost grew with the candidate pool: {$small} queries for 2 candidates, {$large} for 6."
        );
    }

    public function test_registration_dispatches_a_per_profile_generation_job(): void
    {
        Queue::fake();

        $city = City::factory()->create();

        $this->postJson('/api/v1/auth/register/business', [
            'accepted_terms' => true,
            'email' => 'cafe-central@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'name' => 'Cafe Central',
            'business_type' => 'cafe',
            'city_id' => $city->id,
            'primary_venue' => [
                'name' => 'Cafe Central',
                'venue_type' => 'cafe',
                'capacity' => 40,
                'formatted_address' => 'Gran Via 1, Madrid',
                'city' => $city->name,
                'country' => $city->country,
                'photos' => [],
            ],
        ])->assertCreated();

        Queue::assertPushed(GenerateSuggestionsForProfile::class);
    }

    public function test_the_job_generates_for_its_profile(): void
    {
        $city = City::factory()->create();
        $viewer = $this->business($city);
        $counterpart = $this->community($city, 'food_community');

        (new GenerateSuggestionsForProfile((string) $viewer->id))->handle($this->generator());

        $this->assertDatabaseHas('kolab_suggestions', [
            'viewer_profile_id' => $viewer->id,
            'counterpart_profile_id' => $counterpart->id,
        ]);
    }

    public function test_the_job_writes_nothing_when_the_feature_flag_is_off(): void
    {
        config(['suggestions.enabled' => false]);

        $city = City::factory()->create();
        $viewer = $this->business($city);
        $this->community($city, 'food_community');

        (new GenerateSuggestionsForProfile((string) $viewer->id))->handle($this->generator());

        $this->assertSame(0, KolabSuggestion::query()->count());
    }
}
