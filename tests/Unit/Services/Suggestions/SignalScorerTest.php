<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Suggestions;

use App\Enums\SuggestionAudience;
use App\Services\Suggestions\PairContext;
use App\Services\Suggestions\SignalScorer;
use App\Support\Matching\CategoryFitMatrix;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Lang;
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
        $this->assertStringContainsString('café', mb_strtolower($signal['reason']));
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
        $this->assertSame(0.0, $other['score']);
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
        $this->assertStringContainsString('90', $signal['reason']);
        $this->assertStringContainsString('30', $signal['reason']);
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
        $this->assertStringContainsString('30', $signal['reason']);
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
        $this->assertStringContainsString('venue', $signal['reason']);
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
        $this->assertStringContainsString('café', mb_strtolower($signal['reason']));
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
        $this->assertSame(__('suggestions.reason.offer_need_none'), $signal['reason']);
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
        $this->assertSame(__('suggestions.reason.delivery_proof_business', [
            'reviews' => 4,
            'rating' => '4.6',
        ]), $signal['reason']);
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
        $this->assertStringContainsString('25', $signal['reason']);
    }

    /**
     * The vocabulary map covers every matrix key by construction, which is what
     * makes the scorer's slug fallback unreachable through real data. Assert the
     * invariant instead of contorting a test to reach the fallback: this fails
     * the moment a matrix column is added without a translation, which is the
     * only way that fallback could ever fire in production.
     */
    public function test_every_matrix_key_has_a_vocabulary_entry_in_every_locale(): void
    {
        $communityTypes = array_keys(CategoryFitMatrix::MATRIX);
        $businessCategories = array_keys(array_merge(...array_values(CategoryFitMatrix::MATRIX)));

        $this->assertNotEmpty($communityTypes);
        $this->assertNotEmpty($businessCategories);

        foreach (['en', 'es', 'ca'] as $locale) {
            foreach ($communityTypes as $communityType) {
                $key = 'suggestions.vocabulary.community_type.'.$communityType;

                $this->assertTrue(
                    Lang::has($key, $locale, false),
                    "Missing translation [{$key}] for locale [{$locale}]."
                );
            }

            foreach ($businessCategories as $businessCategory) {
                $key = 'suggestions.vocabulary.business_category.'.$businessCategory;

                $this->assertTrue(
                    Lang::has($key, $locale, false),
                    "Missing translation [{$key}] for locale [{$locale}]."
                );
            }
        }
    }

    public function test_the_category_fit_reason_is_localised_on_both_sides_of_the_interpolation(): void
    {
        App::setLocale('es');

        $result = (new SignalScorer)->score($this->context());

        $signal = $this->signal($result, 'category_fit');

        $this->assertNotNull($signal);
        $this->assertStringContainsString('gastronomía', $signal['reason']);
        $this->assertStringContainsString('cafetería', $signal['reason']);
    }
}
