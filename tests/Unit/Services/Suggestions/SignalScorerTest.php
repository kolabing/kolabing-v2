<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Suggestions;

use App\Enums\SuggestionAudience;
use App\Services\Suggestions\PairContext;
use App\Services\Suggestions\SignalScorer;
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
            'averageRating' => 4.6,
            'repeatRatio' => 0.9,
            'contentDelivered' => 5,
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
        $this->assertSame('high', $result['confidence']);
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
}
