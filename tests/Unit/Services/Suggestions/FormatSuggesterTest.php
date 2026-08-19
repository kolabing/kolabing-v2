<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Suggestions;

use App\Enums\IntentType;
use App\Enums\SuggestionAudience;
use App\Services\Suggestions\FormatSuggester;
use App\Services\Suggestions\PairContext;
use App\Services\Suggestions\SignalReasonRenderer;
use App\Services\Suggestions\SignalScorer;
use Database\Factories\KolabSuggestionFactory;
use Illuminate\Support\Facades\App;
use InvalidArgumentException;
use Tests\TestCase;

class FormatSuggesterTest extends TestCase
{
    private function suggester(): FormatSuggester
    {
        return new FormatSuggester(new SignalScorer);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function context(array $overrides = []): PairContext
    {
        return new PairContext(...array_merge([
            'audience' => SuggestionAudience::Business,
            'viewerProfileId' => 'viewer',
            'counterpartProfileId' => 'counterpart',
            'communityType' => 'run_club',
            'businessCategories' => ['cafe'],
            'viewerCityId' => 'city-1',
            'counterpartCityId' => 'city-1',
            'distanceKm' => 2.0,
            'pastAttendance' => [40, 45, 50],
            'communitySize' => 120,
            'venueCapacity' => 60,
            'viewerOffers' => ['food_drink', 'venue'],
            'counterpartNeeds' => ['food_drink', 'discount'],
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
     * @param  array<string, mixed>  $format
     * @return array<string, mixed>|null
     */
    private function note(array $format, string $reasonKey): ?array
    {
        foreach ($format['notes'] as $note) {
            if ($note['reason_key'] === $reasonKey) {
                return $note;
            }
        }

        return null;
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

    public function test_weekday_and_time_come_from_the_active_series(): void
    {
        $format = $this->suggester()->suggest(
            $this->context(),
            seriesWeekdays: [0],
            seriesTime: '10:30',
            pastEventWeekdays: [2, 2, 2],
        );

        $this->assertSame(7, $format['weekday']);
        $this->assertSame('10:30', $format['time_of_day']);
        $this->assertSame('series', $format['weekday_basis']);
    }

    /**
     * `event_series.byweekday` keeps the order the community typed, so the same
     * Tue + Thu cadence arrives as [2,4] or [4,2]. The proposed day must not
     * depend on which.
     */
    public function test_a_multi_day_series_proposes_the_same_day_in_either_order(): void
    {
        $forward = $this->suggester()->suggest($this->context(), seriesWeekdays: [2, 4]);
        $reversed = $this->suggester()->suggest($this->context(), seriesWeekdays: [4, 2]);

        $this->assertSame(2, $forward['weekday']);
        $this->assertSame($forward['weekday'], $reversed['weekday']);
        $this->assertSame('series', $reversed['weekday_basis']);
    }

    /**
     * Sunday is 0 in the stored convention and 7 in the ISO one the Kolab form
     * wants, so a Sunday + Wednesday series must not propose Sunday merely
     * because 0 sorts first.
     */
    public function test_a_multi_day_series_including_sunday_sorts_in_iso_order(): void
    {
        $format = $this->suggester()->suggest($this->context(), seriesWeekdays: [0, 3]);

        $this->assertSame(3, $format['weekday']);
    }

    public function test_weekday_falls_back_to_the_modal_weekday_of_past_events(): void
    {
        $format = $this->suggester()->suggest(
            $this->context(),
            pastEventWeekdays: [6, 3, 6],
        );

        $this->assertSame(6, $format['weekday']);
        $this->assertNull($format['time_of_day']);
        $this->assertSame('past_events', $format['weekday_basis']);
    }

    public function test_expected_attendance_is_capped_by_venue_capacity(): void
    {
        $format = $this->suggester()->suggest($this->context(['venueCapacity' => 40]));

        $this->assertSame(40, $format['expected_attendance']);
        $this->assertSame('past_events', $format['attendance_basis']);

        $note = $this->note($format, 'scale_fit');

        $this->assertNotNull($note);
        $this->assertSame(['expected' => 45, 'capacity' => 40], $note['reason_params']);
    }

    public function test_without_history_the_copy_makes_no_numeric_claim(): void
    {
        $format = $this->suggester()->suggest($this->context([
            'pastAttendance' => [],
            'communitySize' => null,
            'recentEventCount' => 0,
            'hasActiveSeries' => false,
            'venueCapacity' => 40,
        ]));

        $this->assertNull($format['expected_attendance']);
        $this->assertSame('profile_only', $format['attendance_basis']);
        $this->assertNotNull($this->note($format, 'no_history'));
        $this->assertNull($this->note($format, 'scale_fit'));

        $renderer = new SignalReasonRenderer;
        $copy = $renderer->renderTitle($format);

        foreach ($format['notes'] as $note) {
            $copy .= ' '.$renderer->render($note)['reason'];
        }

        $this->assertNotSame('', trim($copy));
        $this->assertDoesNotMatchRegularExpression('/\d/', $copy);
    }

    public function test_the_community_audience_proposes_a_community_seeking_kolab(): void
    {
        $format = $this->suggester()->suggest($this->context([
            'audience' => SuggestionAudience::Community,
        ]));

        $this->assertSame(IntentType::CommunitySeeking->value, $format['intent_type']);
    }

    public function test_a_business_with_a_venue_promotes_the_venue(): void
    {
        $format = $this->suggester()->suggest($this->context(['venueCapacity' => 60]));

        $this->assertSame(IntentType::VenuePromotion->value, $format['intent_type']);
    }

    public function test_a_business_without_a_venue_promotes_a_product(): void
    {
        $format = $this->suggester()->suggest($this->context(['venueCapacity' => null]));

        $this->assertSame(IntentType::ProductPromotion->value, $format['intent_type']);
        $this->assertSame(45, $format['expected_attendance']);
    }

    public function test_attendance_falls_back_to_a_quarter_of_the_community_size(): void
    {
        $format = $this->suggester()->suggest($this->context([
            'pastAttendance' => [],
            'communitySize' => 120,
        ]));

        $this->assertSame(30, $format['expected_attendance']);
        $this->assertSame('community_size', $format['attendance_basis']);
        $this->assertNull($this->note($format, 'no_history'));
    }

    /**
     * A quarter of a one-member community rounds to zero, and "expect around 0
     * people" is a claim, not the absence of one.
     */
    public function test_a_community_too_small_to_round_to_a_person_claims_nothing(): void
    {
        $format = $this->suggester()->suggest($this->context([
            'pastAttendance' => [],
            'communitySize' => 1,
        ]));

        $this->assertNull($format['expected_attendance']);
        $this->assertSame('profile_only', $format['attendance_basis']);
    }

    public function test_the_title_falls_back_to_a_generic_key_for_an_unmapped_community_type(): void
    {
        $unmapped = $this->suggester()->suggest($this->context(['communityType' => 'Book Club']));

        $this->assertSame('generic', $unmapped['title_key']);
        $this->assertSame(['community_type' => 'book_club'], $unmapped['title_params']);

        $unknown = $this->suggester()->suggest($this->context(['communityType' => null]));

        $this->assertSame('generic', $unknown['title_key']);
        $this->assertSame([], $unknown['title_params']);
    }

    public function test_the_title_is_keyed_on_the_normalised_community_type(): void
    {
        $format = $this->suggester()->suggest($this->context(['communityType' => 'Run Club']));

        $this->assertSame('run_club', $format['title_key']);
        $this->assertSame(['community_type' => 'run_club'], $format['title_params']);
    }

    public function test_the_title_renders_in_the_readers_locale(): void
    {
        $format = $this->suggester()->suggest($this->context());
        $renderer = new SignalReasonRenderer;

        $this->assertSame(__('suggestions.format.title.run_club'), $renderer->renderTitle($format));

        App::setLocale('es');
        $spanish = $renderer->renderTitle($format);

        App::setLocale('ca');
        $catalan = $renderer->renderTitle($format);

        $this->assertNotSame('', $spanish);
        $this->assertNotSame('', $catalan);
        $this->assertNotSame($spanish, $catalan);
    }

    public function test_the_proposed_offer_is_the_overlap_the_scorer_already_reports(): void
    {
        $context = $this->context();

        $format = $this->suggester()->suggest($context);
        $signal = $this->signal((new SignalScorer)->score($context), 'offer_need_fit');

        $this->assertNotNull($signal);
        $this->assertSame(['food_drink'], $format['offer']);
        $this->assertSame($signal['reason_params']['items'], $format['offer']);
    }

    public function test_the_capped_note_repeats_the_scorers_scale_fit_reason(): void
    {
        $context = $this->context(['venueCapacity' => 40]);

        $note = $this->note($this->suggester()->suggest($context), 'scale_fit');
        $signal = $this->signal((new SignalScorer)->score($context), 'scale_fit');

        $this->assertNotNull($note);
        $this->assertNotNull($signal);
        $this->assertSame($signal['reason_key'], $note['reason_key']);
        $this->assertSame($signal['reason_params'], $note['reason_params']);
    }

    public function test_an_iso_weekday_is_rejected_rather_than_silently_shifted(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->suggester()->suggest($this->context(), seriesWeekdays: [7]);
    }

    public function test_a_past_weekday_outside_the_stored_convention_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->suggester()->suggest($this->context(), pastEventWeekdays: [2, 7]);
    }

    public function test_a_weekday_tie_resolves_to_the_earliest_day_of_the_iso_week(): void
    {
        $format = $this->suggester()->suggest($this->context(), pastEventWeekdays: [0, 3]);

        $this->assertSame(3, $format['weekday']);
    }

    public function test_a_malformed_series_time_is_dropped_rather_than_pre_filling_an_invalid_kolab(): void
    {
        $format = $this->suggester()->suggest($this->context(), seriesTime: 'evening');

        $this->assertNull($format['time_of_day']);

        $padded = $this->suggester()->suggest($this->context(), seriesTime: '9:05:00');

        $this->assertSame('09:05', $padded['time_of_day']);
    }

    public function test_weekday_is_null_when_there_is_no_cadence_at_all(): void
    {
        $format = $this->suggester()->suggest($this->context());

        $this->assertNull($format['weekday']);
        $this->assertNull($format['time_of_day']);
        $this->assertSame('none', $format['weekday_basis']);
    }

    public function test_it_proposes_no_ask_it_cannot_derive(): void
    {
        $format = $this->suggester()->suggest($this->context());

        $this->assertSame([], $format['expects']);
    }

    /**
     * Tasks 7, 12 and 15 build their fixtures from KolabSuggestionFactory, so a
     * factory row in a shape the producers cannot write would let the read side
     * test against a fiction and pass while production rendered blanks. Pin both
     * jsonb payloads against what actually writes them.
     */
    public function test_the_factory_fixture_matches_the_shape_the_producers_write(): void
    {
        $context = $this->context();
        $definition = (new KolabSuggestionFactory)->definition();

        $this->assertSame(
            array_keys($this->suggester()->suggest($context, seriesWeekdays: [0], seriesTime: '09:00')),
            array_keys($definition['suggested_format'])
        );

        $this->assertSame(
            array_keys((new SignalScorer)->score($context)['signals'][0]),
            array_keys($definition['signals'][0])
        );
    }
}
