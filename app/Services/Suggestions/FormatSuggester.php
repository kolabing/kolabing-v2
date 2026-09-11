<?php

declare(strict_types=1);

namespace App\Services\Suggestions;

use App\Enums\IntentType;
use App\Enums\SuggestionAudience;
use App\Support\Matching\CategoryFitMatrix;
use App\Support\Matching\OfferTypeAliases;
use Illuminate\Support\Facades\Lang;
use InvalidArgumentException;

/**
 * Turns a scored pair into the event we are actually proposing — the
 * `kolab_suggestions.suggested_format` payload, which the card renders and
 * which later pre-fills the Kolab create form.
 *
 * Pure, like the scorer: no database access, no clock, no randomness, and no
 * rendered copy — the lang catalogue is consulted only to check that a title
 * key exists, never to turn one into a sentence. Every field is either derived
 * from real history or absent. There is no "typical" weekday, no rounded-up
 * attendance and no invented headline number anywhere in here: each value on
 * the card is a claim made to a user about a partner, so an unknown is a `null`
 * and the copy degrades to something that stays true
 * (`suggestions.reason.no_history`).
 *
 * `title_key` + `title_params` rather than a finished title, for the reason
 * SignalScorer stores `reason_key` + `reason_params`: generation runs in a
 * nightly command under the app's default locale, so a rendered title would
 * reach every reader in that one language. SignalReasonRenderer::renderTitle()
 * is the read-time half, shared by SuggestionResource and the weekly digest.
 *
 * The two numbers this class and the scorer would otherwise each compute — the
 * expected-attendance median and the offer/need overlap — are read back off
 * SignalScorer instead of reimplemented. They appear twice on the same card
 * (once as a reason line, once as a proposed format), and two implementations
 * of the same rule would eventually let the card contradict itself.
 */
class FormatSuggester
{
    public function __construct(
        private readonly SignalScorer $scorer,
    ) {}

    /**
     * @param  array<int, int>  $seriesWeekdays  `event_series.byweekday`: 0 = Sunday .. 6 = Saturday, one or more days
     * @param  string|null  $seriesTime  `event_series.time_of_day` ("HH:MM")
     * @param  array<int, int>  $pastEventWeekdays  weekday of each past event, same 0..6 convention
     * @return array{
     *     title_key: string,
     *     title_params: array<string, string>,
     *     intent_type: string,
     *     weekday: int|null,
     *     time_of_day: string|null,
     *     expected_attendance: int|null,
     *     offer: array<int, string>,
     *     expects: array<int, string>,
     *     notes: array<int, array{reason_key: string, reason_params: array<string, mixed>}>,
     *     attendance_basis: string,
     *     weekday_basis: string
     * }
     *
     * @throws InvalidArgumentException
     */
    public function suggest(
        PairContext $context,
        array $seriesWeekdays = [],
        ?string $seriesTime = null,
        array $pastEventWeekdays = [],
    ): array {
        [$weekday, $weekdayBasis] = $this->weekday($seriesWeekdays, $pastEventWeekdays);

        $capacity = $context->venueCapacity !== null && $context->venueCapacity > 0
            ? $context->venueCapacity
            : null;

        $expected = $this->scorer->expectedAttendance($context);
        $attendance = $expected;
        $notes = [];

        if ($expected !== null && $capacity !== null && $expected > $capacity) {
            $attendance = $capacity;
            $notes[] = [
                'reason_key' => 'scale_fit',
                'reason_params' => ['expected' => $expected, 'capacity' => $capacity],
            ];
        }

        if ($this->hasNoEventHistory($context)) {
            $notes[] = ['reason_key' => 'no_history', 'reason_params' => []];
        }

        $communityType = $context->communityType !== null
            ? CategoryFitMatrix::normalize($context->communityType)
            : null;

        return [
            'title_key' => $this->titleKey($communityType),
            'title_params' => $communityType !== null ? ['community_type' => $communityType] : [],
            'intent_type' => $this->intentType($context)->value,
            'weekday' => $weekday,
            'time_of_day' => $this->timeOfDay($seriesTime),
            'expected_attendance' => $attendance,
            'offer' => $this->scorer->offerOverlap($context),
            'expects' => $this->expects($context),
            'notes' => $notes,
            'attendance_basis' => $this->attendanceBasis($context, $expected),
            'weekday_basis' => $weekdayBasis,
        ];
    }

    /**
     * The weekday to propose, in the ISO convention `Kolab.recurring_days`
     * stores and `CreateKolabRequest` validates (`between:1,7`, compared against
     * `Carbon::dayOfWeekIso`) — not the 0..6 convention `event_series.byweekday`
     * and `Carbon::dayOfWeek` use, which is what arrives here. The two agree on
     * Monday..Saturday and differ only on Sunday, so a caller that hands over an
     * already-ISO weekday would be wrong exactly once a week and silently: hence
     * the range check, which is the only point at which that mistake is visible.
     *
     * @param  array<int, int>  $seriesWeekdays
     * @param  array<int, int>  $pastEventWeekdays
     * @return array{0: int|null, 1: string}
     */
    private function weekday(array $seriesWeekdays, array $pastEventWeekdays): array
    {
        $series = $this->toIsoWeekdays($seriesWeekdays, 'seriesWeekdays');

        if ($series !== []) {
            return [$this->pickWeekday($series), 'series'];
        }

        $past = $this->toIsoWeekdays($pastEventWeekdays, 'pastEventWeekdays');

        if ($past === []) {
            return [null, 'none'];
        }

        return [$this->pickWeekday($past), 'past_events'];
    }

    /**
     * The most frequent weekday, ties resolved to the earliest day of the ISO
     * week. A tie-break has to be deterministic rather than merely reasonable:
     * the nightly pass re-scores the same pair in place, so a rule that depended
     * on input order would move the proposed day around from night to night.
     *
     * Both callers need that. A multi-day series is stored as
     * `array_values(array_unique($submitted))` (EventSeriesService), which keeps
     * whatever order the community typed — so a Tue + Thu series arrives as
     * `[2,4]` or `[4,2]` for the same cadence. With all counts equal this
     * reduces to "the earliest day of the week the series runs", which is a
     * defensible proposal and, more importantly, always the same one.
     *
     * @param  array<int, int>  $isoWeekdays
     */
    private function pickWeekday(array $isoWeekdays): int
    {
        $counts = array_count_values($isoWeekdays);
        ksort($counts);

        return (int) array_keys($counts, max($counts), true)[0];
    }

    /**
     * @param  array<int, int>  $weekdays
     * @return array<int, int>
     *
     * @throws InvalidArgumentException
     */
    private function toIsoWeekdays(array $weekdays, string $field): array
    {
        return array_map(
            fn (int $weekday): int => $this->toIsoWeekday($weekday, $field),
            array_values($weekdays)
        );
    }

    /**
     * @throws InvalidArgumentException
     */
    private function toIsoWeekday(int $weekday, string $field): int
    {
        if ($weekday < 0 || $weekday > 6) {
            throw new InvalidArgumentException(sprintf(
                'FormatSuggester [%s] must be an `event_series.byweekday` weekday (0 = Sunday .. 6 = Saturday), got [%d]. An ISO weekday is rejected here rather than shifted, because the two conventions differ only on Sunday.',
                $field,
                $weekday
            ));
        }

        return $weekday === 0 ? 7 : $weekday;
    }

    /**
     * `event_series.time_of_day` is a `string(5)` holding "HH:MM", while
     * `selected_time` is validated as `date_format:H:i`. Anything that does not
     * normalise into that format is dropped rather than passed through: a
     * pre-filled form that cannot be submitted is worse than one field left for
     * the user, and a proposal is never worth inventing a time for.
     */
    private function timeOfDay(?string $seriesTime): ?string
    {
        if ($seriesTime === null) {
            return null;
        }

        if (preg_match('/^(\d{1,2}):([0-5]\d)(?::[0-5]\d)?$/', trim($seriesTime), $matches) !== 1) {
            return null;
        }

        $hour = (int) $matches[1];

        if ($hour > 23) {
            return null;
        }

        return sprintf('%02d:%02d', $hour, (int) $matches[2]);
    }

    /**
     * The Kolab the viewer would create. A community always creates a
     * `community_seeking` Kolab; a business promotes its venue when it has one
     * and its product otherwise.
     *
     * Read off `viewerHasVenue`, never inferred from a positive `venueCapacity`.
     * The onboarding requests do make capacity required alongside a venue, but
     * the live table disagrees with the form: 62 businesses carry
     * `has_venue = true` and only 44 of them have a `capacity` key in
     * `primary_venue` (checked read-only, 2026-08-19). Deriving the flag would
     * file the other 18 real venues as product promotions and propose the wrong
     * kind of Kolab to each of them.
     */
    private function intentType(PairContext $context): IntentType
    {
        if ($context->audience === SuggestionAudience::Community) {
            return IntentType::CommunitySeeking;
        }

        return $context->viewerHasVenue
            ? IntentType::VenuePromotion
            : IntentType::ProductPromotion;
    }

    /**
     * What the viewer would ask for in return — `expects` on a business Kolab,
     * the `required_if` `needs` on a `community_seeking` one. The mirror image of
     * `offer`: the viewer's own asks, kept only where the counterpart can
     * actually supply them, so the pre-filled field is a claim the partner's own
     * profile supports.
     *
     * `viewerNeeds` is the side that is kept, exactly as `viewerOffers` is for
     * `offer`. Both fields belong to the viewer's form and are validated against
     * the viewer's taxonomy — `KIND_DELIVERABLE` for a business `expects`,
     * `KIND_NEED` for a community `needs` — so returning the counterpart's
     * spelling would pre-fill a value the form rejects.
     *
     * @return array<int, string>
     */
    private function expects(PairContext $context): array
    {
        return OfferTypeAliases::intersect($context->viewerNeeds, $context->counterpartOffers);
    }

    /**
     * Which body of evidence the attendance number rests on, so the card and the
     * digest can say how much to trust it. `profile_only` is the honest empty
     * case: no events, no declared size, and therefore no number at all.
     *
     * Named `attendance_basis` rather than nested under an `evidence` key: the
     * row already has an `evidence` column of its own, and Task 7's resource and
     * Task 15's digest read both.
     */
    private function attendanceBasis(PairContext $context, ?int $expected): string
    {
        return match (true) {
            $context->pastAttendance !== [] => 'past_events',
            $expected !== null => 'community_size',
            default => 'profile_only',
        };
    }

    /**
     * True only when there is genuinely nothing: no attendance figures, nothing
     * inside the momentum window and no live series. `reason.no_history` says
     * "no past events yet", so a community whose events simply never reported an
     * attendee count must not trigger it.
     */
    private function hasNoEventHistory(PairContext $context): bool
    {
        return $context->pastAttendance === []
            && $context->recentEventCount === 0
            && ! $context->hasActiveSeries;
    }

    /**
     * The persisted key is chosen once, at generation time, so it must not
     * depend on the locale the command happened to run under. `Lang::has()`
     * falls back to the fallback locale, and a test pins that all three locales
     * carry the same `format.title.*` key set, which together make the choice
     * locale-independent.
     */
    private function titleKey(?string $communityType): string
    {
        if ($communityType !== null && Lang::has('suggestions.format.title.'.$communityType)) {
            return $communityType;
        }

        return 'generic';
    }
}
