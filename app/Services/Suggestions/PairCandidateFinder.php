<?php

declare(strict_types=1);

namespace App\Services\Suggestions;

use App\Enums\ApplicationStatus;
use App\Enums\CollaborationStatus;
use App\Enums\SuggestionAudience;
use App\Enums\UserType;
use App\Models\BusinessPartnerStatus;
use App\Models\BusinessProfile;
use App\Models\CollaborationFeedback;
use App\Models\CollaborationReview;
use App\Models\CommunityProfile;
use App\Models\Event;
use App\Models\EventSeries;
use App\Models\Kolab;
use App\Models\Profile;
use App\Support\Matching\CategoryFitMatrix;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * The only class in the suggestion engine allowed to touch the database.
 *
 * It narrows the candidate pool in SQL, then loads every aggregate the scorer
 * and the format suggester need in a fixed handful of batched queries keyed on
 * the surviving counterpart ids, and assembles the `PairContext` objects in
 * PHP. The query count is therefore a function of the *audience*, not of the
 * number of candidates: the nightly pass runs this for every business and
 * community on the platform, so one query per pair would be one query per pair
 * per night, forever. A test pins the ceiling.
 *
 * Nothing is aggregated in SQL that Postgres and SQLite would disagree about
 * (BE-FX-12): no aggregate over a `uuid`, no arithmetic on a boolean, and no
 * Haversine in the query. The rows come back narrow and typed through the
 * models' casts, and the medians, ratios and distances are computed in PHP —
 * which is also the only way `would_collaborate_again` can be read as a boolean
 * on both engines.
 */
class PairCandidateFinder
{
    /**
     * How far back attendance figures still describe a community. Two years
     * covers a seasonal cadence twice over without letting a group's 2019 turnout
     * set expectations for next month.
     */
    private const ATTENDANCE_WINDOW_MONTHS = 24;

    /**
     * @return array<int, PairContext>
     *
     * @throws InvalidArgumentException
     */
    public function candidatesFor(Profile $viewer, SuggestionAudience $audience): array
    {
        $this->assertAudienceMatchesViewer($viewer, $audience);

        $viewer->loadMissing($viewer->user_type === UserType::Business ? 'businessProfile' : 'communityProfile');

        $cityIds = $this->viewerCityIds($viewer);

        if ($cityIds === []) {
            return [];
        }

        $counterpartType = $audience === SuggestionAudience::Business
            ? UserType::Community
            : UserType::Business;

        $counterparts = $this->counterpartQuery($viewer, $counterpartType, $cityIds)
            ->get()
            ->filter(fn (Profile $counterpart): bool => $this->isProposable($counterpart, $counterpartType))
            ->values();

        if ($counterparts->isEmpty()) {
            return [];
        }

        /** @var array<int, string> $counterpartIds */
        $counterpartIds = $counterparts->modelKeys();

        /**
         * The viewer is included in the event, series and Kolab batches because
         * half of every context describes it: a community viewer supplies the
         * attendance and cadence of the event being proposed, and both sides
         * supply one of the four offer/need vocabularies.
         */
        $bothSideIds = array_values(array_unique([...$counterpartIds, $viewer->getKey()]));

        $events = $this->eventAggregates($bothSideIds);
        $series = $this->seriesAggregates($bothSideIds);
        $offers = $this->offerVocabularies($bothSideIds);
        $reviews = $this->reviewAggregates($counterpartIds);

        $contentDelivered = $audience === SuggestionAudience::Business
            ? $this->contentDelivered($counterpartIds)
            : [];
        $completedCollaborations = $audience === SuggestionAudience::Community
            ? $this->completedCollaborations($counterpartIds)
            : [];

        return $counterparts
            ->map(fn (Profile $counterpart): PairContext => $this->context(
                $viewer,
                $counterpart,
                $audience,
                $events,
                $series,
                $offers,
                $reviews,
                $contentDelivered,
                $completedCollaborations,
            ))
            ->all();
    }

    /**
     * `kolab_suggestions.audience` always mirrors the `user_type` of its
     * `viewer_profile_id`, and nothing downstream reads both fields, so a
     * mismatch would score a business against businesses and label the row
     * `community` without a single assertion firing. Attendees are never an
     * audience.
     *
     * @throws InvalidArgumentException
     */
    private function assertAudienceMatchesViewer(Profile $viewer, SuggestionAudience $audience): void
    {
        $expected = $audience === SuggestionAudience::Business
            ? UserType::Business
            : UserType::Community;

        if ($viewer->user_type !== $expected) {
            throw new InvalidArgumentException(sprintf(
                'PairCandidateFinder was asked for [%s] suggestions for a [%s] profile; the audience always mirrors the viewer.',
                $audience->value,
                $viewer->user_type->value,
            ));
        }
    }

    /**
     * The cities a counterpart may sit in to be a candidate.
     *
     * Business and community users keep their city on their *extended* profile —
     * `profiles.city_id` was added for attendees, who have no extended profile,
     * and was never backfilled (see add_city_id_to_profiles_table). Scoping on
     * `profiles.city_id` alone would therefore match almost nothing in
     * production, so both columns are consulted on both sides of the pair.
     *
     * A business additionally declares the cities it wants to work in
     * (`target_city_ids`), which widens its own view of the map.
     *
     * @return array<int, string>
     */
    private function viewerCityIds(Profile $viewer): array
    {
        $extended = $this->extendedProfile($viewer);

        $ids = [
            $viewer->city_id,
            $extended?->city_id,
            ...($extended instanceof BusinessProfile ? $extended->target_city_ids ?? [] : []),
        ];

        return array_values(array_unique(array_filter(
            $ids,
            static fn (mixed $id): bool => is_string($id) && trim($id) !== ''
        )));
    }

    /**
     * The extended profile for the side's own `user_type`, and only that one.
     * Reaching for the other relation "just in case" would lazily load it —
     * returning null, once per candidate, which is precisely the per-pair query
     * this class exists to avoid and which nothing about the result would reveal.
     */
    private function extendedProfile(Profile $profile): BusinessProfile|CommunityProfile|null
    {
        return $profile->user_type === UserType::Business
            ? $profile->businessProfile
            : $profile->communityProfile;
    }

    /**
     * @param  array<int, string>  $cityIds
     * @return Builder<Profile>
     */
    private function counterpartQuery(Profile $viewer, UserType $counterpartType, array $cityIds): Builder
    {
        $relation = $counterpartType === UserType::Business ? 'businessProfile' : 'communityProfile';
        $viewerId = (string) $viewer->getKey();
        $cooldownSince = Carbon::now()->subDays((int) config('suggestions.dismissal_cooldown_days'));

        return Profile::query()
            ->with($relation)
            ->withCount('events')
            ->where('profiles.user_type', $counterpartType->value)
            ->whereKeyNot($viewerId)
            ->where(function (Builder $inScope) use ($relation, $cityIds): void {
                $inScope->whereIn('profiles.city_id', $cityIds)
                    ->orWhereHas($relation, fn (Builder $extended): Builder => $extended->whereIn('city_id', $cityIds));
            })
            ->where(fn (Builder $complete): Builder => $this->applyCompleteness($complete, $relation, $counterpartType))
            ->whereNotExists(fn (QueryBuilder $blocks) => $this->blockExists($blocks, $viewerId))
            ->whereNotExists(fn (QueryBuilder $applications) => $this->openApplicationExists($applications, $viewerId))
            ->whereNotExists(fn (QueryBuilder $collaborations) => $this->activeCollaborationExists($collaborations, $viewerId))
            ->whereNotExists(fn (QueryBuilder $dismissals) => $this->freshDismissalExists($dismissals, $viewerId, $cooldownSince));
    }

    /**
     * A counterpart with nothing to match on is not proposable: no declared type
     * or categories *and* no event history means a card would be built entirely
     * out of absences.
     *
     * Only the portable half of the test is expressible here. `categories` is a
     * jsonb document, so `[]` cannot be told from `['cafe']` in SQL without
     * engine-specific syntax — `isProposable()` finishes the job in PHP once the
     * extended profile is hydrated and its array cast has run.
     *
     * @param  Builder<Profile>  $query
     * @return Builder<Profile>
     */
    private function applyCompleteness(Builder $query, string $relation, UserType $counterpartType): Builder
    {
        $typeColumn = $counterpartType === UserType::Business ? 'business_type' : 'community_type';

        $query->whereHas($relation, fn (Builder $extended): Builder => $extended->whereNotNull($typeColumn));

        if ($counterpartType === UserType::Business) {
            $query->orWhereHas($relation, fn (Builder $extended): Builder => $extended->whereNotNull('categories'));
        }

        return $query->orWhereHas('events');
    }

    /**
     * The PHP half of the completeness filter, rejecting the one row shape SQL
     * cannot: a business whose `categories` is a non-null but *empty* jsonb array
     * and whose `business_type` is null. `events_count` comes from the
     * `withCount` on the candidate query, so this costs no query of its own —
     * `$counterpart->events()->exists()` here would be exactly the per-pair query
     * this class exists to avoid.
     */
    private function isProposable(Profile $counterpart, UserType $counterpartType): bool
    {
        if ($counterpartType === UserType::Community) {
            return true;
        }

        return ($counterpart->businessProfile?->normalizedCategories() ?? []) !== []
            || (int) ($counterpart->events_count ?? 0) > 0;
    }

    private function blockExists(QueryBuilder $query, string $viewerId): QueryBuilder
    {
        return $query->from('user_blocks')
            ->where(function (QueryBuilder $eitherDirection) use ($viewerId): void {
                $eitherDirection
                    ->where(fn (QueryBuilder $outgoing): QueryBuilder => $outgoing
                        ->where('user_blocks.blocker_profile_id', $viewerId)
                        ->whereColumn('user_blocks.blocked_profile_id', 'profiles.id'))
                    ->orWhere(fn (QueryBuilder $incoming): QueryBuilder => $incoming
                        ->where('user_blocks.blocked_profile_id', $viewerId)
                        ->whereColumn('user_blocks.blocker_profile_id', 'profiles.id'));
            });
    }

    /**
     * A pending application in either direction means the pair is already talking
     * and does not need introducing. Joined through `kolabs` because
     * `applications` carries no counterpart column of its own — only the
     * applicant and the Kolab it was sent to.
     */
    private function openApplicationExists(QueryBuilder $query, string $viewerId): QueryBuilder
    {
        return $query->from('applications')
            ->join('kolabs', 'kolabs.id', '=', 'applications.kolab_id')
            ->where('applications.status', ApplicationStatus::Pending->value)
            ->where(function (QueryBuilder $eitherDirection) use ($viewerId): void {
                $eitherDirection
                    ->where(fn (QueryBuilder $viewerApplied): QueryBuilder => $viewerApplied
                        ->where('applications.applicant_profile_id', $viewerId)
                        ->whereColumn('kolabs.creator_profile_id', 'profiles.id'))
                    ->orWhere(fn (QueryBuilder $counterpartApplied): QueryBuilder => $counterpartApplied
                        ->whereColumn('applications.applicant_profile_id', 'profiles.id')
                        ->where('kolabs.creator_profile_id', $viewerId));
            });
    }

    /**
     * `creator_profile_id` / `applicant_profile_id`, not
     * `business_profile_id` / `community_profile_id`: the latter two are foreign
     * keys to `business_profiles.id` and `community_profiles.id` — extended
     * profile ids, not profile ids — and both are nullable, filled from a
     * `?->id`. Compared against `profiles.id` they would match nothing and the
     * exclusion would silently never fire.
     */
    private function activeCollaborationExists(QueryBuilder $query, string $viewerId): QueryBuilder
    {
        return $query->from('collaborations')
            ->whereIn('collaborations.status', [
                CollaborationStatus::Scheduled->value,
                CollaborationStatus::Active->value,
            ])
            ->where(function (QueryBuilder $eitherDirection) use ($viewerId): void {
                $eitherDirection
                    ->where(fn (QueryBuilder $viewerCreated): QueryBuilder => $viewerCreated
                        ->where('collaborations.creator_profile_id', $viewerId)
                        ->whereColumn('collaborations.applicant_profile_id', 'profiles.id'))
                    ->orWhere(fn (QueryBuilder $counterpartCreated): QueryBuilder => $counterpartCreated
                        ->where('collaborations.applicant_profile_id', $viewerId)
                        ->whereColumn('collaborations.creator_profile_id', 'profiles.id'));
            });
    }

    /**
     * A dismissal suppresses the pair for `dismissal_cooldown_days`, then stops:
     * a pair dismissed longer ago than the window is a candidate again, and the
     * generator clears `dismissed_at` when it refreshes the row. That is what
     * makes this a cooldown rather than a permanent block.
     */
    private function freshDismissalExists(QueryBuilder $query, string $viewerId, Carbon $cooldownSince): QueryBuilder
    {
        return $query->from('kolab_suggestions')
            ->where('kolab_suggestions.viewer_profile_id', $viewerId)
            ->whereColumn('kolab_suggestions.counterpart_profile_id', 'profiles.id')
            ->whereNotNull('kolab_suggestions.dismissed_at')
            ->where('kolab_suggestions.dismissed_at', '>=', $cooldownSince);
    }

    /**
     * Attendance figures, the momentum count, the weekdays a group actually meets
     * on and the coordinates of its most recent located event — one query, split
     * per profile in PHP.
     *
     * Weekdays stay in the `event_series.byweekday` convention (0 = Sunday),
     * which is what `Carbon::dayOfWeek` returns and what FormatSuggester
     * validates. Converting to ISO here would be wrong exactly once a week, on
     * Sundays, and the suggester rejects an already-ISO value rather than
     * shifting it.
     *
     * @param  array<int, string>  $profileIds
     * @return array<string, array{attendance: array<int, int>, recent: int, weekdays: array<int, int>, lat: float|null, lng: float|null}>
     */
    private function eventAggregates(array $profileIds): array
    {
        $today = Carbon::today();
        $attendanceSince = $today->copy()->subMonths(self::ATTENDANCE_WINDOW_MONTHS);
        $momentumSince = $today->copy()->subDays((int) config('suggestions.momentum_window_days'));

        $rows = Event::query()
            ->select(['profile_id', 'event_date', 'attendee_count', 'location_lat', 'location_lng'])
            ->whereIn('profile_id', $profileIds)
            ->whereBetween('event_date', [$attendanceSince->toDateString(), $today->toDateString()])
            ->orderBy('event_date')
            ->get();

        $aggregates = [];

        foreach ($rows as $event) {
            $profileId = (string) $event->profile_id;

            $aggregates[$profileId] ??= [
                'attendance' => [],
                'recent' => 0,
                'weekdays' => [],
                'lat' => null,
                'lng' => null,
            ];

            $attendees = (int) $event->attendee_count;

            if ($attendees > 0) {
                $aggregates[$profileId]['attendance'][] = $attendees;
            }

            $date = $event->event_date;

            if ($date !== null) {
                $aggregates[$profileId]['weekdays'][] = (int) $date->dayOfWeek;

                if ($date->greaterThanOrEqualTo($momentumSince)) {
                    $aggregates[$profileId]['recent']++;
                }
            }

            if ($event->location_lat !== null && $event->location_lng !== null) {
                $aggregates[$profileId]['lat'] = (float) $event->location_lat;
                $aggregates[$profileId]['lng'] = (float) $event->location_lng;
            }
        }

        return $aggregates;
    }

    /**
     * A live recurring cadence, plus the days and time it runs on — the strongest
     * evidence there is for what to propose, because it is a rule the community
     * already committed to rather than a pattern inferred from history.
     *
     * "Live" means the rule has started and has not run out: only `ends_mode`
     * `until` can expire on a date, `count` is bounded by occurrences instead and
     * `never` not at all.
     *
     * @param  array<int, string>  $profileIds
     * @return array<string, array{weekdays: array<int, int>, time: string|null, present: bool}>
     */
    private function seriesAggregates(array $profileIds): array
    {
        $today = Carbon::today();

        $rows = EventSeries::query()
            ->select(['profile_id', 'byweekday', 'time_of_day', 'starts_on', 'ends_mode', 'ends_on'])
            ->whereIn('profile_id', $profileIds)
            ->where('starts_on', '<=', $today->toDateString())
            ->where(function (Builder $live) use ($today): void {
                $live->where('ends_mode', '!=', 'until')
                    ->orWhereNull('ends_on')
                    ->orWhere('ends_on', '>=', $today->toDateString());
            })
            ->orderBy('starts_on')
            ->get();

        $aggregates = [];

        foreach ($rows as $series) {
            $profileId = (string) $series->profile_id;

            $aggregates[$profileId] ??= ['weekdays' => [], 'time' => null, 'present' => true];

            foreach (is_array($series->byweekday) ? $series->byweekday : [] as $weekday) {
                if (is_int($weekday) || (is_string($weekday) && $weekday !== '' && ctype_digit($weekday))) {
                    $aggregates[$profileId]['weekdays'][] = (int) $weekday;
                }
            }

            $aggregates[$profileId]['time'] ??= $series->time_of_day;
        }

        return $aggregates;
    }

    /**
     * The reviews a profile has *received*, reduced to the three numbers
     * `delivery_proof` reads. Aggregated in PHP rather than with a GROUP BY
     * because the repeat ratio counts a boolean, and `sum(case when bool ...)`
     * cannot be written once for both Postgres and SQLite — Postgres refuses
     * `boolean = 1` and SQLite has no `true`. The model's cast settles it.
     *
     * @param  array<int, string>  $profileIds
     * @return array<string, array{rating: float|null, repeat: float|null, count: int}>
     */
    private function reviewAggregates(array $profileIds): array
    {
        $rows = CollaborationReview::query()
            ->select(['reviewed_profile_id', 'rating', 'would_collaborate_again'])
            ->whereIn('reviewed_profile_id', $profileIds)
            ->get()
            ->groupBy(fn (CollaborationReview $review): string => (string) $review->reviewed_profile_id);

        $aggregates = [];

        foreach ($rows as $profileId => $reviews) {
            /** @var Collection<int, CollaborationReview> $reviews */
            $ratings = $reviews
                ->map(fn (CollaborationReview $review): ?int => $review->rating)
                ->filter(static fn (?int $rating): bool => $rating !== null);

            $answered = $reviews->filter(
                static fn (CollaborationReview $review): bool => $review->would_collaborate_again !== null
            );

            $aggregates[(string) $profileId] = [
                'rating' => $ratings->isEmpty() ? null : round((float) $ratings->avg(), 4),
                'repeat' => $answered->isEmpty()
                    ? null
                    : round(
                        $answered->filter(
                            static fn (CollaborationReview $review): bool => $review->would_collaborate_again === true
                        )->count() / $answered->count(),
                        4
                    ),
                'count' => $reviews->count(),
            ];
        }

        return $aggregates;
    }

    /**
     * Reels and stories a community actually posted for past Kolabs — the
     * business audience's half of `delivery_proof`'s volume term, reported by the
     * *business* side of each collaboration.
     *
     * The community sits on whichever of `creator_profile_id` /
     * `applicant_profile_id` it happens to occupy, so there is no single column
     * to GROUP BY; the rows come back narrow and are summed against the id set in
     * PHP. Exactly one of the two columns can be in that set, so nothing is
     * counted twice.
     *
     * @param  array<int, string>  $profileIds
     * @return array<string, int>
     */
    private function contentDelivered(array $profileIds): array
    {
        $rows = CollaborationFeedback::query()
            ->join('collaborations', 'collaborations.id', '=', 'collaboration_feedback.collaboration_id')
            ->where('collaboration_feedback.reviewer_type', UserType::Business->value)
            ->where(function (Builder $eitherSide) use ($profileIds): void {
                $eitherSide->whereIn('collaborations.creator_profile_id', $profileIds)
                    ->orWhereIn('collaborations.applicant_profile_id', $profileIds);
            })
            ->select([
                'collaborations.creator_profile_id',
                'collaborations.applicant_profile_id',
                'collaboration_feedback.posts_reels',
                'collaboration_feedback.stories_posted',
            ])
            ->toBase()
            ->get();

        $wanted = array_fill_keys($profileIds, true);
        $totals = [];

        foreach ($rows as $row) {
            $delivered = (int) ($row->posts_reels ?? 0) + (int) ($row->stories_posted ?? 0);

            foreach ([$row->creator_profile_id, $row->applicant_profile_id] as $side) {
                if (is_string($side) && isset($wanted[$side])) {
                    $totals[$side] = ($totals[$side] ?? 0) + $delivered;
                }
            }
        }

        return $totals;
    }

    /**
     * `business_partner_statuses.completed_kolabs_count` — the community
     * audience's half of the volume term. A business delivers completed Kolabs,
     * not posts, which is why this is loaded only for that audience and the other
     * half of the pair stays at zero.
     *
     * @param  array<int, string>  $profileIds
     * @return array<string, int>
     */
    private function completedCollaborations(array $profileIds): array
    {
        return BusinessPartnerStatus::query()
            ->whereIn('profile_id', $profileIds)
            ->pluck('completed_kolabs_count', 'profile_id')
            ->map(static fn (mixed $count): int => (int) $count)
            ->all();
    }

    /**
     * The four offer/need vocabularies, per profile, from the Kolabs it has
     * written. There is nowhere else to read them from: `business_profiles.offering`
     * is a free-text sentence, not a slug list, and a community profile has no
     * needs column at all.
     *
     * Both list-of-slugs (`['venue']`, what the request validators accept today)
     * and the legacy keyed-boolean shape (`['venue' => true]`) appear in these
     * columns, so both are read — the same tolerance
     * DiscoveryOpportunityService applies to `kolabs.offering`.
     *
     * @param  array<int, string>  $profileIds
     * @return array<string, array{offering: array<int, string>, expects: array<int, string>, offers_in_return: array<int, string>, needs: array<int, string>}>
     */
    private function offerVocabularies(array $profileIds): array
    {
        $rows = Kolab::query()
            ->select(['creator_profile_id', 'offering', 'expects', 'offers_in_return', 'needs'])
            ->whereIn('creator_profile_id', $profileIds)
            ->get();

        $vocabularies = [];

        foreach ($rows as $kolab) {
            $profileId = (string) $kolab->creator_profile_id;

            $vocabularies[$profileId] ??= [
                'offering' => [],
                'expects' => [],
                'offers_in_return' => [],
                'needs' => [],
            ];

            foreach (['offering', 'expects', 'offers_in_return', 'needs'] as $column) {
                $vocabularies[$profileId][$column] = array_values(array_unique([
                    ...$vocabularies[$profileId][$column],
                    ...$this->slugList($kolab->{$column}),
                ]));
            }
        }

        return $vocabularies;
    }

    /**
     * @return array<int, string>
     */
    private function slugList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $slugs = [];

        foreach ($value as $key => $entry) {
            if (is_int($key) && is_string($entry) && trim($entry) !== '') {
                $slugs[] = mb_strtolower(trim($entry));

                continue;
            }

            if (is_string($key) && filter_var($entry, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) === true) {
                $slugs[] = mb_strtolower(trim($key));
            }
        }

        return array_values(array_unique($slugs));
    }

    /**
     * Assemble one pair. Which side supplies which field is the whole point of
     * this method, so it is spelled out rather than parameterised:
     *
     * - the *business* side supplies the categories and the venue capacity;
     * - the *community* side supplies the community type, declared size, past
     *   attendance and the cadence the proposed event would follow — for a
     *   community audience that side is the viewer, which is why the batches
     *   above include it;
     * - the *counterpart* always supplies `delivery_proof` and `momentum`,
     *   because those are claims the card makes about the partner. A business
     *   counterpart normally has no events, so `momentum` drops out, its weight
     *   is renormalised away and confidence falls a band — the honest outcome,
     *   not a borrowed number describing the reader back to themselves.
     *
     * @param  array<string, array{attendance: array<int, int>, recent: int, weekdays: array<int, int>, lat: float|null, lng: float|null}>  $events
     * @param  array<string, array{weekdays: array<int, int>, time: string|null, present: bool}>  $series
     * @param  array<string, array{offering: array<int, string>, expects: array<int, string>, offers_in_return: array<int, string>, needs: array<int, string>}>  $offers
     * @param  array<string, array{rating: float|null, repeat: float|null, count: int}>  $reviews
     * @param  array<string, int>  $contentDelivered
     * @param  array<string, int>  $completedCollaborations
     */
    private function context(
        Profile $viewer,
        Profile $counterpart,
        SuggestionAudience $audience,
        array $events,
        array $series,
        array $offers,
        array $reviews,
        array $contentDelivered,
        array $completedCollaborations,
    ): PairContext {
        $viewerId = (string) $viewer->getKey();
        $counterpartId = (string) $counterpart->getKey();

        $isBusinessAudience = $audience === SuggestionAudience::Business;
        $businessSide = $isBusinessAudience ? $viewer : $counterpart;
        $communitySide = $isBusinessAudience ? $counterpart : $viewer;
        $communitySideId = (string) $communitySide->getKey();

        $venue = $businessSide->businessProfile?->primary_venue;
        $venueCapacity = is_array($venue) && isset($venue['capacity']) && (int) $venue['capacity'] > 0
            ? (int) $venue['capacity']
            : null;

        $communityEvents = $events[$communitySideId] ?? ['attendance' => [], 'recent' => 0, 'weekdays' => [], 'lat' => null, 'lng' => null];
        $counterpartEvents = $events[$counterpartId] ?? ['attendance' => [], 'recent' => 0, 'weekdays' => [], 'lat' => null, 'lng' => null];
        $communitySeries = $series[$communitySideId] ?? ['weekdays' => [], 'time' => null, 'present' => false];
        $counterpartSeries = $series[$counterpartId] ?? ['weekdays' => [], 'time' => null, 'present' => false];
        $counterpartReviews = $reviews[$counterpartId] ?? ['rating' => null, 'repeat' => null, 'count' => 0];

        $viewerVocabulary = $offers[$viewerId] ?? ['offering' => [], 'expects' => [], 'offers_in_return' => [], 'needs' => []];
        $counterpartVocabulary = $offers[$counterpartId] ?? ['offering' => [], 'expects' => [], 'offers_in_return' => [], 'needs' => []];

        [$viewerGives, $viewerWants] = $isBusinessAudience
            ? [$viewerVocabulary['offering'], $viewerVocabulary['expects']]
            : [$viewerVocabulary['offers_in_return'], $viewerVocabulary['needs']];

        [$counterpartGives, $counterpartWants] = $isBusinessAudience
            ? [$counterpartVocabulary['offers_in_return'], $counterpartVocabulary['needs']]
            : [$counterpartVocabulary['offering'], $counterpartVocabulary['expects']];

        $communityType = $communitySide->communityProfile?->community_type;
        $viewerCityId = $this->cityIdFor($viewer);
        $counterpartCityId = $this->cityIdFor($counterpart);

        return new PairContext(
            audience: $audience,
            viewerProfileId: $viewerId,
            counterpartProfileId: $counterpartId,
            communityType: $communityType !== null ? CategoryFitMatrix::canonicalise($communityType) : null,
            businessCategories: array_values(array_unique(array_map(
                static fn (string $category): string => CategoryFitMatrix::canonicalise($category),
                $businessSide->businessProfile?->normalizedCategories() ?? []
            ))),
            viewerCityId: $viewerCityId,
            counterpartCityId: $counterpartCityId,
            distanceKm: $this->distanceKm($businessSide, $communityEvents),
            pastAttendance: $communityEvents['attendance'],
            communitySize: $communitySide->communityProfile?->community_size,
            venueCapacity: $venueCapacity,
            viewerOffers: $viewerGives,
            counterpartNeeds: $counterpartWants,
            counterpartOffers: $counterpartGives,
            viewerNeeds: $viewerWants,
            averageRating: $counterpartReviews['rating'],
            repeatRatio: $counterpartReviews['repeat'],
            contentDelivered: $contentDelivered[$counterpartId] ?? 0,
            completedCollaborations: $completedCollaborations[$counterpartId] ?? 0,
            reviewCount: $counterpartReviews['count'],
            recentEventCount: $counterpartEvents['recent'],
            hasActiveSeries: $counterpartSeries['present'],
            evidence: [
                'series_weekdays' => $communitySeries['weekdays'],
                'series_time_of_day' => $communitySeries['time'],
                'past_event_weekdays' => $communityEvents['weekdays'],
                'community_side_profile_id' => $communitySideId,
                'business_side_profile_id' => (string) $businessSide->getKey(),
                'past_attendance' => $communityEvents['attendance'],
                'recent_event_count' => $counterpartEvents['recent'],
                'review_count' => $counterpartReviews['count'],
            ],
        );
    }

    /**
     * The city a business or community user actually declares, which lives on the
     * extended profile; `profiles.city_id` is the attendee column and is only a
     * fallback here.
     */
    private function cityIdFor(Profile $profile): ?string
    {
        return $this->extendedProfile($profile)?->city_id ?? $profile->city_id;
    }

    /**
     * Kilometres between the business's venue and the community's most recent
     * located event, or null when either side has no coordinates — in which case
     * the scorer falls back to comparing city ids.
     *
     * Computed in PHP. The equivalent SQL needs `acos`/`radians`, which SQLite
     * only exposes with a build flag the test suite cannot rely on, and the
     * result would then differ between the suite and production (BE-FX-12).
     * EventDiscoveryService already keeps a PHP fallback for the same reason.
     *
     * @param  array{attendance: array<int, int>, recent: int, weekdays: array<int, int>, lat: float|null, lng: float|null}  $communityEvents
     */
    private function distanceKm(Profile $businessSide, array $communityEvents): ?float
    {
        $venue = $businessSide->businessProfile?->primary_venue;

        if (! is_array($venue)) {
            return null;
        }

        $venueLat = $venue['latitude'] ?? null;
        $venueLng = $venue['longitude'] ?? null;

        if (! is_numeric($venueLat) || ! is_numeric($venueLng)) {
            return null;
        }

        if ($communityEvents['lat'] === null || $communityEvents['lng'] === null) {
            return null;
        }

        return round($this->haversineDistance(
            (float) $venueLat,
            (float) $venueLng,
            $communityEvents['lat'],
            $communityEvents['lng'],
        ), 3);
    }

    private function haversineDistance(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371.0;

        $latDelta = deg2rad($lat2 - $lat1);
        $lngDelta = deg2rad($lng2 - $lng1);

        $a = sin($latDelta / 2) * sin($latDelta / 2)
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
            * sin($lngDelta / 2) * sin($lngDelta / 2);

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
