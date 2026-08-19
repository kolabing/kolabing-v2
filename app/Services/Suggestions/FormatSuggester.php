<?php

declare(strict_types=1);

namespace App\Services\Suggestions;

use App\Enums\IntentType;
use App\Enums\SuggestionAudience;
use App\Support\Matching\CategoryFitMatrix;
use Illuminate\Support\Facades\Lang;
use InvalidArgumentException;

/**
 * Turns a scored pair into the event we are actually proposing — the
 * `kolab_suggestions.suggested_format` payload, which the card renders and
 * which later pre-fills the Kolab create form.
 *
 * Pure, like the scorer: no database access, no clock, no randomness, no
 * localisation. Every field is either derived from real history or absent.
 * There is no "typical" weekday, no rounded-up attendance and no invented
 * headline number anywhere in here: each value on the card is a claim made to
 * a user about a partner, so an unknown is a `null` and the copy degrades to
 * something that stays true (`suggestions.reason.no_history`).
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
     * @param  int|null  $seriesWeekday  `event_series.byweekday` entry: 0 = Sunday .. 6 = Saturday
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
     *     evidence: array{basis: string, weekday_basis: string}
     * }
     *
     * @throws InvalidArgumentException
     */
    public function suggest(
        PairContext $context,
        ?int $seriesWeekday = null,
        ?string $seriesTime = null,
        array $pastEventWeekdays = [],
    ): array {
        [$weekday, $weekdayBasis] = $this->weekday($seriesWeekday, $pastEventWeekdays);

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
            'intent_type' => $this->intentType($context, $capacity)->value,
            'weekday' => $weekday,
            'time_of_day' => $this->timeOfDay($seriesTime),
            'expected_attendance' => $attendance,
            'offer' => $this->scorer->offerOverlap($context),
            'expects' => $this->expects(),
            'notes' => $notes,
            'evidence' => [
                'basis' => $this->attendanceBasis($context, $expected),
                'weekday_basis' => $weekdayBasis,
            ],
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
     * @param  array<int, int>  $pastEventWeekdays
     * @return array{0: int|null, 1: string}
     */
    private function weekday(?int $seriesWeekday, array $pastEventWeekdays): array
    {
        $past = array_map(
            fn (int $weekday): int => $this->toIsoWeekday($weekday, 'pastEventWeekdays'),
            array_values($pastEventWeekdays)
        );

        if ($seriesWeekday !== null) {
            return [$this->toIsoWeekday($seriesWeekday, 'seriesWeekday'), 'series'];
        }

        if ($past === []) {
            return [null, 'none'];
        }

        return [$this->modalWeekday($past), 'past_events'];
    }

    /**
     * The most frequent weekday, ties resolved to the earliest day of the ISO
     * week. A tie-break has to be deterministic rather than merely reasonable:
     * the nightly pass re-scores the same pair in place, so a mode that depended
     * on input order would move the proposed day around from night to night.
     *
     * @param  array<int, int>  $isoWeekdays
     */
    private function modalWeekday(array $isoWeekdays): int
    {
        $counts = array_count_values($isoWeekdays);
        ksort($counts);

        return (int) array_keys($counts, max($counts), true)[0];
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
     * `has_venue` is not on PairContext, but it does not need to be: the column
     * makes `primary_venue` required, which makes `primary_venue.capacity`
     * required and `min:1` (RegisterBusinessRequest / BusinessOnboardingRequest),
     * so a positive `venueCapacity` *is* `has_venue = true`. The capacity here is
     * always the business side of the pair — the viewer's own on the business
     * audience, which is the only audience that consults it.
     */
    private function intentType(PairContext $context, ?int $venueCapacity): IntentType
    {
        if ($context->audience === SuggestionAudience::Community) {
            return IntentType::CommunitySeeking;
        }

        return $venueCapacity !== null
            ? IntentType::VenuePromotion
            : IntentType::ProductPromotion;
    }

    /**
     * What the viewer would ask for in return — `expects` on a business Kolab,
     * `needs` on a community one. PairContext carries "what the viewer can give"
     * and "what the counterpart wants", which is one intersection: the offer. The
     * mirrored pair (what the counterpart can give, what the viewer wants) is not
     * in the context, so there is nothing here to derive an ask from, and a
     * plausible-looking guess would be a claim about a partner we never checked.
     * The key stays in the payload so the jsonb shape does not change on the day
     * PairContext grows that pair.
     *
     * @return array<int, string>
     */
    private function expects(): array
    {
        return [];
    }

    /**
     * Which body of evidence the attendance number rests on, so the card and the
     * digest can say how much to trust it. `profile_only` is the honest empty
     * case: no events, no declared size, and therefore no number at all.
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
