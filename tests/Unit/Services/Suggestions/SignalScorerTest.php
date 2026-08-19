<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Suggestions;

use App\Enums\SuggestionAudience;
use App\Services\Suggestions\PairContext;
use App\Services\Suggestions\SignalScorer;
use LogicException;
use Tests\TestCase;

class SignalScorerTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $overrides
     */
    private function context(array $overrides = []): PairContext
    {
        return new PairContext(...array_merge([
            'audience' => SuggestionAudience::Business,
            'viewerProfileId' => 'viewer',
            'counterpartProfileId' => 'counterpart',
            'communityType' => 'food_community',
            'businessCategories' => ['cafe'],
            'viewerCityId' => 'city-1',
            'counterpartCityId' => 'city-1',
            'distanceKm' => 2.0,
            'pastAttendance' => [40, 45, 50],
            'communitySize' => 120,
            'venueCapacity' => 45,
            'viewerOffers' => ['food_drink', 'venue'],
            'counterpartNeeds' => ['food_drink'],
            'counterpartOffers' => ['social_media'],
            'viewerNeeds' => ['social_media', 'ugc_content'],
            'averageRating' => 4.6,
            'repeatRatio' => 0.9,
            'contentDelivered' => 5,
            'completedCollaborations' => 0,
            'reviewCount' => 4,
            'recentEventCount' => 3,
            'hasActiveSeries' => true,
        ], $overrides));
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>|null
     */
    private function signal(array $result, string $key): ?array
    {
        foreach ($result['signals'] as $signal) {
            if ($signal['key'] === $key) {
                return $signal;
            }
        }

        return null;
    }

    public function test_category_fit_uses_the_shared_matrix(): void
    {
        $result = (new SignalScorer)->score($this->context());

        $signal = $this->signal($result, 'category_fit');

        $this->assertNotNull($signal);
        $this->assertSame(1.0, $signal['score']);
        $this->assertSame('category_fit', $signal['reason_key']);
        $this->assertSame([
            'community_type' => 'food_community',
            'business_category' => 'cafe',
        ], $signal['reason_params']);
    }

    public function test_location_fit_prefers_near_over_far(): void
    {
        $scorer = new SignalScorer;

        $near = $this->signal($scorer->score($this->context(['distanceKm' => 1.0])), 'location_fit');
        $far = $this->signal($scorer->score($this->context(['distanceKm' => 55.0])), 'location_fit');

        $this->assertNotNull($near);
        $this->assertNotNull($far);
        $this->assertGreaterThan($far['score'], $near['score']);
    }

    public function test_location_fit_persists_the_raw_distance_rather_than_a_formatted_one(): void
    {
        $signal = $this->signal(
            (new SignalScorer)->score($this->context(['distanceKm' => 2.5])),
            'location_fit'
        );

        $this->assertNotNull($signal);
        $this->assertSame('location_distance', $signal['reason_key']);
        $this->assertSame(['km' => 2.5], $signal['reason_params']);
    }

    public function test_location_fit_falls_back_to_city_equality_when_distance_is_unknown(): void
    {
        $scorer = new SignalScorer;

        $same = $this->signal($scorer->score($this->context([
            'distanceKm' => null,
            'viewerCityId' => 'city-1',
            'counterpartCityId' => 'city-1',
        ])), 'location_fit');

        $other = $this->signal($scorer->score($this->context([
            'distanceKm' => null,
            'viewerCityId' => 'city-1',
            'counterpartCityId' => 'city-2',
        ])), 'location_fit');

        $this->assertNotNull($same);
        $this->assertNotNull($other);
        $this->assertSame(1.0, $same['score']);
        $this->assertSame('location_same_city', $same['reason_key']);
        $this->assertSame(0.0, $other['score']);
        $this->assertSame('location_other_city', $other['reason_key']);
    }

    public function test_scale_fit_is_perfect_when_expected_attendance_fills_the_venue(): void
    {
        $result = (new SignalScorer)->score($this->context([
            'pastAttendance' => [40],
            'venueCapacity' => 40,
        ]));

        $signal = $this->signal($result, 'scale_fit');

        $this->assertNotNull($signal);
        $this->assertSame(1.0, $signal['score']);
    }

    public function test_scale_fit_penalises_overflow_and_names_the_constraint(): void
    {
        $result = (new SignalScorer)->score($this->context([
            'pastAttendance' => [90],
            'venueCapacity' => 30,
        ]));

        $signal = $this->signal($result, 'scale_fit');

        $this->assertNotNull($signal);
        $this->assertLessThan(0.5, $signal['score']);
        $this->assertSame('scale_fit', $signal['reason_key']);
        $this->assertSame(['expected' => 90, 'capacity' => 30], $signal['reason_params']);
    }

    public function test_scale_fit_falls_back_to_a_quarter_of_community_size_without_event_history(): void
    {
        $result = (new SignalScorer)->score($this->context([
            'pastAttendance' => [],
            'communitySize' => 120,
            'venueCapacity' => 30,
        ]));

        $signal = $this->signal($result, 'scale_fit');

        $this->assertNotNull($signal);
        $this->assertSame(1.0, $signal['score']);
        $this->assertSame(['expected' => 30, 'capacity' => 30], $signal['reason_params']);
    }

    /**
     * The fraction sets a number a user reads, so it is tunable config rather
     * than a literal — and the tuning has to actually reach the reason line.
     */
    public function test_the_community_size_fallback_reads_its_fraction_from_config(): void
    {
        config(['suggestions.community_size_attendance_fraction' => 0.5]);

        $result = (new SignalScorer)->score($this->context([
            'pastAttendance' => [],
            'communitySize' => 120,
            'venueCapacity' => 60,
        ]));

        $signal = $this->signal($result, 'scale_fit');

        $this->assertNotNull($signal);
        $this->assertSame(['expected' => 60, 'capacity' => 60], $signal['reason_params']);
    }

    /**
     * A quarter of a one-member community rounds to zero, and "expect around 0
     * people; the space holds 45" is a claim, not the absence of one. The signal
     * has to drop out entirely, the way every other signal with no data does.
     */
    public function test_a_community_too_small_to_round_to_a_person_has_no_scale_signal(): void
    {
        $result = (new SignalScorer)->score($this->context([
            'pastAttendance' => [],
            'communitySize' => 1,
        ]));

        $this->assertNull($this->signal($result, 'scale_fit'));
    }

    public function test_offer_need_fit_scores_the_share_of_needs_covered(): void
    {
        $result = (new SignalScorer)->score($this->context([
            'viewerOffers' => ['venue'],
            'counterpartNeeds' => ['venue', 'food_drink'],
        ]));

        $signal = $this->signal($result, 'offer_need_fit');

        $this->assertNotNull($signal);
        $this->assertSame(0.5, $signal['score']);
        $this->assertSame('offer_need_overlap', $signal['reason_key']);
        $this->assertSame(['items' => ['venue']], $signal['reason_params']);
    }

    /**
     * @return array<string, mixed>
     */
    private function coldStartOverrides(): array
    {
        return [
            'communityType' => null,
            'distanceKm' => null,
            'viewerCityId' => null,
            'counterpartCityId' => null,
            'reviewCount' => 0,
            'contentDelivered' => 0,
            'recentEventCount' => 0,
            'hasActiveSeries' => false,
        ];
    }

    public function test_signals_without_data_are_dropped_and_weights_renormalised(): void
    {
        $result = (new SignalScorer)->score($this->context($this->coldStartOverrides()));

        $this->assertCount(2, $result['signals']);
        $this->assertSame(100, $result['score']);
        $this->assertSame(
            ['scale_fit', 'offer_need_fit'],
            array_column($result['signals'], 'key')
        );
    }

    public function test_confidence_is_low_when_most_signal_weight_is_missing(): void
    {
        $result = (new SignalScorer)->score($this->context($this->coldStartOverrides()));

        $this->assertSame('low', $result['confidence']);
    }

    public function test_confidence_is_high_when_every_signal_has_data(): void
    {
        $result = (new SignalScorer)->score($this->context());

        $this->assertCount(6, $result['signals']);
        $this->assertSame('high', $result['confidence']);
    }

    /**
     * `location_fit + scale_fit + delivery_proof` is 0.45 in decimal, which is
     * exactly the `medium` threshold — but accumulating those three doubles
     * yields 0.44999999999999995559, so a float comparison lands on `low`. Three
     * further subsets of the shipped weights do the same. Confidence therefore
     * compares integer basis points.
     */
    public function test_confidence_is_medium_when_the_available_weight_lands_exactly_on_the_threshold(): void
    {
        $result = (new SignalScorer)->score($this->context([
            'communityType' => null,
            'viewerOffers' => [],
            'recentEventCount' => 0,
            'hasActiveSeries' => false,
        ]));

        $this->assertSame(
            ['location_fit', 'scale_fit', 'delivery_proof'],
            array_column($result['signals'], 'key')
        );
        $this->assertSame('medium', $result['confidence']);
    }

    public function test_a_totally_unknown_pair_scores_zero_rather_than_throwing(): void
    {
        $result = (new SignalScorer)->score($this->context([
            'communityType' => null,
            'businessCategories' => [],
            'viewerCityId' => null,
            'counterpartCityId' => null,
            'distanceKm' => null,
            'pastAttendance' => [],
            'communitySize' => null,
            'venueCapacity' => null,
            'viewerOffers' => [],
            'counterpartNeeds' => [],
            'averageRating' => null,
            'repeatRatio' => null,
            'contentDelivered' => 0,
            'completedCollaborations' => 0,
            'reviewCount' => 0,
            'recentEventCount' => 0,
            'hasActiveSeries' => false,
        ]));

        $this->assertSame(0, $result['score']);
        $this->assertSame([], $result['signals']);
        $this->assertSame('low', $result['confidence']);
    }

    public function test_category_fit_takes_the_best_mapped_category_and_ignores_unmapped_ones(): void
    {
        $result = (new SignalScorer)->score($this->context([
            'businessCategories' => ['coworking', 'barbershop', 'cafe'],
        ]));

        $signal = $this->signal($result, 'category_fit');

        $this->assertNotNull($signal);
        $this->assertSame(1.0, $signal['score']);
        $this->assertSame('cafe', $signal['reason_params']['business_category']);
    }

    public function test_category_fit_is_dropped_when_no_declared_category_is_in_the_matrix(): void
    {
        $result = (new SignalScorer)->score($this->context([
            'businessCategories' => ['barbershop', 'car_wash'],
        ]));

        $this->assertNull($this->signal($result, 'category_fit'));
        $this->assertCount(5, $result['signals']);
    }

    public function test_offer_need_fit_reports_zero_when_nothing_overlaps(): void
    {
        $result = (new SignalScorer)->score($this->context([
            'viewerOffers' => ['venue'],
            'counterpartNeeds' => ['discount', 'sponsorship'],
        ]));

        $signal = $this->signal($result, 'offer_need_fit');

        $this->assertNotNull($signal);
        $this->assertSame(0.0, $signal['score']);
        $this->assertSame('offer_need_none', $signal['reason_key']);
        $this->assertSame([], $signal['reason_params']);
    }

    public function test_delivery_proof_speaks_about_the_business_for_a_community_audience(): void
    {
        $result = (new SignalScorer)->score($this->context([
            'audience' => SuggestionAudience::Community,
            'reviewCount' => 4,
            'averageRating' => 4.6,
        ]));

        $signal = $this->signal($result, 'delivery_proof');

        $this->assertNotNull($signal);
        $this->assertSame('delivery_proof_business', $signal['reason_key']);
        $this->assertSame(['reviews' => 4, 'rating' => 4.6], $signal['reason_params']);
    }

    public function test_delivery_proof_speaks_about_the_community_for_a_business_audience(): void
    {
        $result = (new SignalScorer)->score($this->context([
            'audience' => SuggestionAudience::Business,
            'contentDelivered' => 5,
            'averageRating' => 4.6,
        ]));

        $signal = $this->signal($result, 'delivery_proof');

        $this->assertNotNull($signal);
        $this->assertSame('delivery_proof_community', $signal['reason_key']);
        $this->assertSame(['content' => 5, 'rating' => 4.6], $signal['reason_params']);
    }

    public function test_expected_attendance_averages_the_two_middle_values_of_an_even_history(): void
    {
        $result = (new SignalScorer)->score($this->context([
            'pastAttendance' => [40, 10, 30, 20],
            'venueCapacity' => 25,
        ]));

        $signal = $this->signal($result, 'scale_fit');

        $this->assertNotNull($signal);
        $this->assertSame(1.0, $signal['score']);
        $this->assertSame(['expected' => 25, 'capacity' => 25], $signal['reason_params']);
    }

    public function test_momentum_persists_the_raw_count_and_the_configured_window(): void
    {
        $signal = $this->signal(
            (new SignalScorer)->score($this->context(['recentEventCount' => 3])),
            'momentum'
        );

        $this->assertNotNull($signal);
        $this->assertSame('momentum', $signal['reason_key']);
        $this->assertSame([
            'count' => 3,
            'days' => (int) config('suggestions.momentum_window_days'),
        ], $signal['reason_params']);
    }

    /**
     * Generation runs in a nightly command under the app's default locale, so a
     * rendered sentence would reach every reader in that one language. Nothing
     * the scorer emits may therefore be a finished label or reason — every
     * signal is keys plus raw params, and SignalReasonRenderer does the rest.
     */
    public function test_no_signal_carries_rendered_text(): void
    {
        $result = (new SignalScorer)->score($this->context());

        $this->assertCount(6, $result['signals']);

        foreach ($result['signals'] as $signal) {
            $this->assertSame(
                ['key', 'reason_key', 'reason_params', 'weight', 'score'],
                array_keys($signal)
            );
        }
    }

    public function test_category_fit_normalises_stored_values_before_the_exact_match_lookup(): void
    {
        $result = (new SignalScorer)->score($this->context([
            'communityType' => ' Run Club ',
            'businessCategories' => ['Sports-Facility'],
        ]));

        $signal = $this->signal($result, 'category_fit');

        $this->assertNotNull($signal);
        $this->assertSame(1.0, $signal['score']);
        $this->assertSame([
            'community_type' => 'run_club',
            'business_category' => 'sports_facility',
        ], $signal['reason_params']);
    }

    public function test_offer_need_fit_ignores_duplicates_on_both_sides(): void
    {
        $result = (new SignalScorer)->score($this->context([
            'viewerOffers' => ['venue', 'venue'],
            'counterpartNeeds' => ['venue'],
        ]));

        $signal = $this->signal($result, 'offer_need_fit');

        $this->assertNotNull($signal);
        $this->assertSame(1.0, $signal['score']);
        $this->assertSame(['items' => ['venue']], $signal['reason_params']);

        $duplicatedNeeds = $this->signal((new SignalScorer)->score($this->context([
            'viewerOffers' => ['venue'],
            'counterpartNeeds' => ['venue', 'venue', 'food_drink'],
        ])), 'offer_need_fit');

        $this->assertNotNull($duplicatedNeeds);
        $this->assertSame(0.5, $duplicatedNeeds['score']);
    }

    /**
     * `(float) config(null)` is 0.0 and PHP 8 throws DivisionByZeroError on float
     * division by zero — which would kill the whole nightly batch, not one pair.
     */
    public function test_location_fit_falls_back_to_city_equality_when_the_max_distance_is_unusable(): void
    {
        config()->set('suggestions.max_distance_km', 0);

        $signal = $this->signal(
            (new SignalScorer)->score($this->context(['distanceKm' => 2.0])),
            'location_fit'
        );

        $this->assertNotNull($signal);
        $this->assertSame(1.0, $signal['score']);
        $this->assertSame('location_same_city', $signal['reason_key']);
    }

    public function test_delivery_proof_names_only_the_content_when_there_are_no_reviews_yet(): void
    {
        $signal = $this->signal((new SignalScorer)->score($this->context([
            'audience' => SuggestionAudience::Business,
            'contentDelivered' => 6,
            'reviewCount' => 0,
            'averageRating' => null,
        ])), 'delivery_proof');

        $this->assertNotNull($signal);
        $this->assertSame('delivery_proof_content', $signal['reason_key']);
        $this->assertSame(['content' => 6], $signal['reason_params']);
    }

    public function test_delivery_proof_names_only_the_rating_when_nothing_was_delivered(): void
    {
        $signal = $this->signal((new SignalScorer)->score($this->context([
            'audience' => SuggestionAudience::Business,
            'contentDelivered' => 0,
            'reviewCount' => 3,
            'averageRating' => 4.6,
        ])), 'delivery_proof');

        $this->assertNotNull($signal);
        $this->assertSame('delivery_proof_rating', $signal['reason_key']);
        $this->assertSame(['rating' => 4.6], $signal['reason_params']);
    }

    public function test_delivery_proof_names_only_the_reviews_when_no_rating_landed(): void
    {
        $signal = $this->signal((new SignalScorer)->score($this->context([
            'audience' => SuggestionAudience::Community,
            'contentDelivered' => 0,
            'reviewCount' => 3,
            'averageRating' => null,
        ])), 'delivery_proof');

        $this->assertNotNull($signal);
        $this->assertSame('delivery_proof_reviews', $signal['reason_key']);
        $this->assertSame(['reviews' => 3], $signal['reason_params']);
    }

    /**
     * The renormalisation divides by the weight of the signals that had data, so
     * the weights must be a partition of 1.0 for a full-data score to mean
     * "percent of the best possible pair".
     */
    public function test_the_configured_weights_sum_to_one(): void
    {
        $this->assertSame(1.0, array_sum(config('suggestions.weights')));
    }

    public function test_a_missing_weight_key_is_an_error_rather_than_a_silent_zero(): void
    {
        $weights = config('suggestions.weights');
        unset($weights['momentum']);
        config()->set('suggestions.weights', $weights);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('missing [momentum]');

        (new SignalScorer)->score($this->context());
    }

    public function test_an_unrecognised_weight_key_is_an_error_rather_than_silently_ignored(): void
    {
        config()->set('suggestions.weights', array_merge(
            config('suggestions.weights'),
            ['vibe_fit' => 0.1]
        ));

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('unexpected [vibe_fit]');

        (new SignalScorer)->score($this->context());
    }

    public function test_a_weights_config_that_is_not_an_array_names_the_problem(): void
    {
        config()->set('suggestions.weights', null);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('config(suggestions.weights) must be an array');

        (new SignalScorer)->score($this->context());
    }

    /**
     * The sibling of the weights failure, and worse: `(float) null` makes every
     * threshold 0.0, so every suggestion in the batch would be labelled `high`.
     */
    public function test_a_missing_confidence_threshold_is_an_error_rather_than_a_silent_high(): void
    {
        $thresholds = config('suggestions.confidence_thresholds');
        unset($thresholds['medium']);
        config()->set('suggestions.confidence_thresholds', $thresholds);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('missing [medium]');

        (new SignalScorer)->score($this->context());
    }

    public function test_an_unrecognised_confidence_threshold_is_an_error(): void
    {
        config()->set('suggestions.confidence_thresholds', array_merge(
            config('suggestions.confidence_thresholds'),
            ['certain' => 0.95]
        ));

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('unexpected [certain]');

        (new SignalScorer)->score($this->context());
    }

    public function test_a_confidence_thresholds_config_that_is_not_an_array_names_the_problem(): void
    {
        config()->set('suggestions.confidence_thresholds', null);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('config(suggestions.confidence_thresholds) must be an array');

        (new SignalScorer)->score($this->context());
    }

    /**
     * The no-data guard is audience-correct, not just the volume term: content
     * delivered is a community-side metric (spec 3.3), so a business with no
     * review and no completed Kolab has no reliability record — however much
     * content sits in the arm its audience does not read.
     */
    public function test_delivery_proof_is_dropped_for_a_community_audience_with_no_reliability_record(): void
    {
        $result = (new SignalScorer)->score($this->context([
            'audience' => SuggestionAudience::Community,
            'contentDelivered' => 6,
            'completedCollaborations' => 0,
            'reviewCount' => 0,
            'averageRating' => null,
        ]));

        $this->assertNull($this->signal($result, 'delivery_proof'));
        $this->assertCount(5, $result['signals']);
    }

    /**
     * The community audience's volume term is completed Kolabs, saturating at the
     * 8 that earns the top partner tier. A business does not deliver posts.
     */
    public function test_delivery_proof_reads_completed_collaborations_for_a_community_audience(): void
    {
        $signal = $this->signal((new SignalScorer)->score($this->context([
            'audience' => SuggestionAudience::Community,
            'contentDelivered' => 0,
            'completedCollaborations' => 8,
            'reviewCount' => 0,
            'averageRating' => null,
            'repeatRatio' => 0.0,
        ])), 'delivery_proof');

        $this->assertNotNull($signal);
        $this->assertSame(0.3, $signal['score']);
        $this->assertSame('delivery_proof_collaborations', $signal['reason_key']);
        $this->assertSame(['collaborations' => 8], $signal['reason_params']);
    }

    public function test_the_community_volume_term_saturates_at_the_top_partner_tier(): void
    {
        $scorer = new SignalScorer;

        $overrides = [
            'audience' => SuggestionAudience::Community,
            'contentDelivered' => 0,
            'reviewCount' => 0,
            'averageRating' => null,
            'repeatRatio' => 0.0,
        ];

        $trusted = $this->signal($scorer->score($this->context(
            array_merge($overrides, ['completedCollaborations' => 3])
        )), 'delivery_proof');

        $topTier = $this->signal($scorer->score($this->context(
            array_merge($overrides, ['completedCollaborations' => 8])
        )), 'delivery_proof');

        $wellPast = $this->signal($scorer->score($this->context(
            array_merge($overrides, ['completedCollaborations' => 40])
        )), 'delivery_proof');

        $this->assertNotNull($trusted);
        $this->assertNotNull($topTier);
        $this->assertNotNull($wellPast);
        $this->assertLessThan($topTier['score'], $trusted['score']);
        $this->assertSame(0.3, $topTier['score']);
        $this->assertSame($topTier['score'], $wellPast['score']);
    }

    /**
     * Task 5 populates the arm its audience uses and leaves the other 0. The two
     * counts are indistinguishable by range, so nothing but the scorer's audience
     * check keeps them apart: if a business audience ever read
     * `completedCollaborations`, business scores would inflate silently.
     */
    public function test_a_business_audience_never_reads_completed_collaborations(): void
    {
        $scorer = new SignalScorer;

        $overrides = [
            'audience' => SuggestionAudience::Business,
            'contentDelivered' => 3,
            'reviewCount' => 4,
            'averageRating' => 4.6,
        ];

        $clean = $scorer->score($this->context(
            array_merge($overrides, ['completedCollaborations' => 0])
        ));

        $overPopulated = $scorer->score($this->context(
            array_merge($overrides, ['completedCollaborations' => 40])
        ));

        $this->assertSame($clean['score'], $overPopulated['score']);
        $this->assertSame(
            $this->signal($clean, 'delivery_proof'),
            $this->signal($overPopulated, 'delivery_proof')
        );
        $this->assertStringNotContainsString(
            'collaborations',
            $this->signal($overPopulated, 'delivery_proof')['reason_key']
        );
    }
}
