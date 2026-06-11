<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\EventVisibility;
use App\Enums\UserType;
use App\Models\Community;
use App\Models\Event;
use App\Models\EventSeries;
use App\Models\Profile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Recurring-event series (NF-16 recurring, Phase 1 — the "publish 3 months,
 * extend later" hybrid).
 *
 * A series stores the rule; concrete occurrences are ordinary `events` rows so
 * RSVP / waitlist / chat / tier-gate all keep working unchanged. We materialise
 * a rolling 3-month window and {@see extend()} it on demand. Weekday convention
 * matches Carbon::dayOfWeek and the app: 0=Sun .. 6=Sat; `byweekday` is a list
 * so Tue+Thu (= twice weekly) is just [2, 4].
 */
class EventSeriesService
{
    public function __construct(
        private readonly EventService $eventService,
    ) {}

    private const HORIZON_MONTHS = 3;

    /** Hard safety cap on occurrences generated in a single pass. */
    private const MAX_PER_PASS = 366;

    /**
     * Create a series from the recurrence block on an event-create request and
     * materialise its first window.
     *
     * @param  array<string, mixed>  $data  the validated POST /events payload
     */
    public function create(Profile $profile, array $data): EventSeries
    {
        $rec = $data['recurrence'];
        $startsAt = Carbon::parse($data['starts_at']);
        $endsAt = isset($data['ends_at']) ? Carbon::parse($data['ends_at']) : null;
        $duration = $endsAt !== null ? $startsAt->diffInMinutes($endsAt) : null;

        return DB::transaction(function () use ($profile, $data, $rec, $startsAt, $duration): EventSeries {
            $series = EventSeries::query()->create([
                'community_id' => $data['community_id'],
                'profile_id' => $profile->id,
                'name' => $data['name'],
                'frequency' => $rec['frequency'],
                'byweekday' => array_values(array_unique($rec['byweekday'])),
                'time_of_day' => $startsAt->format('H:i'),
                'duration_minutes' => $duration,
                'location' => $data['location'] ?? null,
                'capacity' => $data['capacity'] ?? null,
                'tier_gate' => $data['tier_gate'] ?? null,
                'visibility' => $data['visibility'] ?? EventVisibility::Members->value,
                'chat_mode' => $rec['chat_mode'] ?? 'per_event',
                'ends_mode' => $rec['ends_mode'] ?? 'never',
                'ends_count' => $rec['ends_count'] ?? null,
                'ends_on' => isset($rec['ends_on']) ? Carbon::parse($rec['ends_on'])->toDateString() : null,
                'starts_on' => $startsAt->toDateString(),
                'materialized_through' => null,
            ]);

            $this->materialize($series);

            return $series->load('events');
        });
    }

    /**
     * Generate any not-yet-created occurrences up to the rolling horizon (or the
     * end rule). Idempotent: only dates after `materialized_through` are created.
     *
     * @return int number of occurrences created
     */
    public function materialize(EventSeries $series): int
    {
        $cursor = $series->materialized_through !== null
            ? $series->materialized_through->copy()->addDay()->startOfDay()
            : $series->starts_on->copy()->startOfDay();

        $dateLimit = $this->dateLimit($series);
        if ($cursor->gt($dateLimit)) {
            return 0;
        }

        $existing = $series->events()->count();
        $remaining = $series->ends_mode === 'count'
            ? max(0, (int) $series->ends_count - $existing)
            : self::MAX_PER_PASS;
        if ($remaining <= 0) {
            return 0;
        }

        $community = Community::query()->find($series->community_id);
        [$h, $m] = array_map('intval', explode(':', $series->time_of_day));

        $created = 0;
        $index = $existing;
        $day = $cursor->copy();
        $lastGenerated = $series->materialized_through?->copy();

        while ($day->lte($dateLimit) && $created < $remaining && $created < self::MAX_PER_PASS) {
            if ($this->matches($series, $day)) {
                $startsAt = $day->copy()->setTime($h, $m);
                Event::query()->create([
                    'profile_id' => $series->profile_id,
                    'community_id' => $series->community_id,
                    'series_id' => $series->id,
                    'occurrence_index' => $index,
                    'name' => $series->name,
                    'partner_name' => $community?->name ?? $series->name,
                    'partner_type' => UserType::Community->value,
                    'event_date' => $startsAt->toDateString(),
                    'starts_at' => $startsAt,
                    'ends_at' => $series->duration_minutes !== null
                        ? $startsAt->copy()->addMinutes($series->duration_minutes)
                        : null,
                    'location' => $series->location,
                    'capacity' => $series->capacity,
                    'tier_gate' => $series->tier_gate,
                    // Occurrences inherit the series visibility so a public series
                    // surfaces in discover and a tier series stays tier-gated.
                    'visibility' => $series->visibility instanceof EventVisibility
                        ? $series->visibility->value
                        : ($series->visibility ?? EventVisibility::Members->value),
                    'attendee_count' => 0,
                ]);
                $created++;
                $index++;
                $lastGenerated = $day->copy();
            }
            $day->addDay();
        }

        // Advance the horizon marker to the scanned-through date (count mode stops
        // at the last actual occurrence so a later extend can continue cleanly).
        $through = $series->ends_mode === 'count'
            ? ($lastGenerated ?? $series->materialized_through)
            : $dateLimit;
        if ($through !== null) {
            $series->update(['materialized_through' => $through->toDateString()]);
        }

        return $created;
    }

    /**
     * Push the rolling window another HORIZON_MONTHS forward (the "trigger more
     * events later" half of the hybrid). No-op once an ended series is exhausted.
     *
     * @return int number of occurrences created
     */
    public function extend(EventSeries $series): int
    {
        return $this->materialize($series->fresh());
    }

    /**
     * Edit → make recurring: turn an existing one-off into a series. The event
     * becomes occurrence 0 (keeping its date + sign-ups); future occurrences are
     * materialised from its fields + the recurrence rule.
     *
     * @param  array<string, mixed>  $recurrence
     */
    public function convertToSeries(Event $event, array $recurrence): EventSeries
    {
        $startsAt = $event->starts_at ?? Carbon::parse($event->event_date);
        $duration = $event->ends_at !== null ? $startsAt->diffInMinutes($event->ends_at) : null;

        return DB::transaction(function () use ($event, $recurrence, $startsAt, $duration): EventSeries {
            $series = EventSeries::query()->create([
                'community_id' => $event->community_id,
                'profile_id' => $event->profile_id,
                'name' => $event->name,
                'frequency' => $recurrence['frequency'],
                'byweekday' => array_values(array_unique($recurrence['byweekday'])),
                'time_of_day' => $startsAt->format('H:i'),
                'duration_minutes' => $duration,
                'location' => $event->location,
                'capacity' => $event->capacity,
                'tier_gate' => $event->tier_gate,
                'visibility' => $event->visibility instanceof EventVisibility
                    ? $event->visibility->value
                    : ($event->visibility ?? EventVisibility::Members->value),
                'chat_mode' => $recurrence['chat_mode'] ?? 'per_event',
                'ends_mode' => $recurrence['ends_mode'] ?? 'never',
                'ends_count' => $recurrence['ends_count'] ?? null,
                'ends_on' => isset($recurrence['ends_on']) ? Carbon::parse($recurrence['ends_on'])->toDateString() : null,
                'starts_on' => $startsAt->toDateString(),
                // Seed date already "materialised" → generation continues AFTER it.
                'materialized_through' => $startsAt->toDateString(),
            ]);

            $event->update(['series_id' => $series->id, 'occurrence_index' => 0]);

            $this->materialize($series->fresh());

            return $series->fresh();
        });
    }

    /**
     * Scoped edit: apply field changes to this occurrence (scope=this) or to the
     * series template + matching occurrences (following / series). Each
     * occurrence keeps its own DATE; a changed start time shifts the time-of-day
     * across the scope, a changed end shifts the duration.
     *
     * @param  array<string, mixed>  $data
     * @param  'this'|'following'|'series'  $scope
     * @return int occurrences updated
     */
    public function updateScope(Event $event, array $data, string $scope): int
    {
        if ($event->series_id === null || $scope === 'this') {
            $this->eventService->update($event, $data);

            return 1;
        }

        $newTime = isset($data['starts_at']) ? Carbon::parse($data['starts_at']) : null;
        $newDuration = ($newTime !== null && array_key_exists('ends_at', $data) && $data['ends_at'] !== null)
            ? $newTime->diffInMinutes(Carbon::parse($data['ends_at']))
            : null;

        $series = EventSeries::query()->find($event->series_id);
        if ($series !== null) {
            $tpl = [];
            foreach (['name', 'location', 'capacity', 'tier_gate', 'visibility'] as $f) {
                if (array_key_exists($f, $data)) {
                    $tpl[$f] = $data[$f];
                }
            }
            if ($newTime !== null) {
                $tpl['time_of_day'] = $newTime->format('H:i');
            }
            if ($newDuration !== null) {
                $tpl['duration_minutes'] = $newDuration;
            }
            if ($tpl !== []) {
                $series->update($tpl);
            }
        }

        $query = Event::query()->where('series_id', $event->series_id);
        if ($scope === 'following') {
            $query->where('starts_at', '>=', $event->starts_at);
        }

        $count = 0;
        foreach ($query->get() as $occ) {
            $patch = [];
            foreach (['name', 'location', 'capacity', 'tier_gate', 'visibility'] as $f) {
                if (array_key_exists($f, $data)) {
                    $patch[$f] = $data[$f];
                }
            }
            if ($newTime !== null) {
                $occStart = $occ->starts_at ?? Carbon::parse($occ->event_date);
                $shifted = $occStart->copy()->setTime($newTime->hour, $newTime->minute);
                $patch['starts_at'] = $shifted->toIso8601String();
                if ($newDuration !== null) {
                    $patch['ends_at'] = $shifted->copy()->addMinutes($newDuration)->toIso8601String();
                }
            }
            $this->eventService->update($occ, $patch);
            $count++;
        }

        return $count;
    }

    /**
     * Delete an occurrence at one of three scopes. Each occurrence goes through
     * {@see EventService::delete} so attendees are notified + photos cleaned up.
     *
     * @param  'this'|'following'|'series'  $scope
     * @return int number of occurrences deleted
     */
    public function deleteScope(Event $event, string $scope): int
    {
        if ($event->series_id === null || $scope === 'this') {
            $this->eventService->delete($event);

            return 1;
        }

        $series = EventSeries::query()->find($event->series_id);

        $query = Event::query()->where('series_id', $event->series_id);
        if ($scope === 'following') {
            $query->where('starts_at', '>=', $event->starts_at);
        }

        $deleted = 0;
        foreach ($query->orderBy('starts_at')->get() as $occurrence) {
            $this->eventService->delete($occurrence);
            $deleted++;
        }

        // 'series' always removes the rule; 'following' removes it only if nothing
        // remains (so a future extend can't resurrect deleted occurrences).
        if ($series !== null && ($scope === 'series' || $series->events()->count() === 0)) {
            $series->delete();
        }

        return $deleted;
    }

    /**
     * The furthest date we will generate to in this pass.
     */
    private function dateLimit(EventSeries $series): Carbon
    {
        if ($series->ends_mode === 'until' && $series->ends_on !== null) {
            return $series->ends_on->copy()->endOfDay();
        }

        if ($series->ends_mode === 'count') {
            // Date-unbounded; the count cap stops generation.
            return $series->starts_on->copy()->addYears(5);
        }

        $horizon = Carbon::today()->addMonths(self::HORIZON_MONTHS);
        if ($series->starts_on->gt($horizon)) {
            $horizon = $series->starts_on->copy()->addMonths(self::HORIZON_MONTHS);
        }

        return $horizon->endOfDay();
    }

    /**
     * Does a candidate date fall on the recurrence rule?
     */
    private function matches(EventSeries $series, Carbon $day): bool
    {
        $weekdays = $series->byweekday;
        if (! in_array($day->dayOfWeek, $weekdays, true)) {
            return false;
        }

        return match ($series->frequency) {
            'weekly' => true,
            'biweekly' => $this->weekIndex($series->starts_on, $day) % 2 === 0,
            // Monthly = same ordinal-week-of-month as the start date (e.g. the
            // 2nd Sunday). Multi-day monthly keeps the same ordinal per weekday.
            'monthly' => $this->ordinalInMonth($day) === $this->ordinalInMonth($series->starts_on),
            default => false,
        };
    }

    /**
     * Whole weeks between two dates (by ISO week start), for the biweekly stride.
     */
    private function weekIndex(Carbon $start, Carbon $day): int
    {
        return (int) $start->copy()->startOfWeek()->diffInWeeks($day->copy()->startOfWeek());
    }

    /**
     * 1-based ordinal of this weekday within its month (1 = 1st Sunday, etc.).
     */
    private function ordinalInMonth(Carbon $day): int
    {
        return (int) ceil($day->day / 7);
    }
}
