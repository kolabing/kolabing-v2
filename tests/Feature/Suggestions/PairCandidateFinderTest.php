<?php

declare(strict_types=1);

namespace Tests\Feature\Suggestions;

use App\Enums\SuggestionAudience;
use App\Models\Application;
use App\Models\BusinessPartnerStatus;
use App\Models\BusinessProfile;
use App\Models\City;
use App\Models\Collaboration;
use App\Models\CollaborationFeedback;
use App\Models\CollaborationReview;
use App\Models\CommunityProfile;
use App\Models\Event;
use App\Models\EventSeries;
use App\Models\Kolab;
use App\Models\KolabSuggestion;
use App\Models\OfferOption;
use App\Models\Profile;
use App\Models\UserBlock;
use App\Services\Suggestions\PairCandidateFinder;
use App\Services\Suggestions\PairContext;
use App\Support\Matching\OfferTypeAliases;
use Database\Seeders\OfferOptionSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

class PairCandidateFinderTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function finder(): PairCandidateFinder
    {
        return app(PairCandidateFinder::class);
    }

    private function business(City $city, ?array $venue = null, array $categories = ['cafe'], bool $hasVenue = true): Profile
    {
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
     * The query log rather than `DB::listen`, because this is called twice in one
     * test and a listener cannot be removed once registered — the second call
     * would be counted by both.
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
     * @return array<int, string>
     */
    private function counterpartIds(array $contexts): array
    {
        return array_map(
            static fn (PairContext $context): string => $context->counterpartProfileId,
            $contexts
        );
    }

    public function test_returns_counterparts_in_the_same_city(): void
    {
        $city = City::factory()->create();
        $otherCity = City::factory()->create();

        $viewer = $this->business($city);
        $sameCity = $this->community($city);
        $elsewhere = $this->community($otherCity);

        $contexts = $this->finder()->candidatesFor($viewer, SuggestionAudience::Business);

        $this->assertSame([$sameCity->id], $this->counterpartIds($contexts));
        $this->assertNotContains($elsewhere->id, $this->counterpartIds($contexts));
        $this->assertSame($city->id, $contexts[0]->counterpartCityId);
        $this->assertSame($city->id, $contexts[0]->viewerCityId);
    }

    /**
     * A business declares the cities it wants to work in beyond its own, and a
     * community sitting in one of those is a legitimate candidate.
     */
    public function test_widens_to_business_target_cities(): void
    {
        $home = City::factory()->create();
        $target = City::factory()->create();
        $unrelated = City::factory()->create();

        $viewer = Profile::factory()->business()->create();
        BusinessProfile::factory()->create([
            'profile_id' => $viewer->id,
            'city_id' => $home->id,
            'business_type' => 'cafe',
            'categories' => ['cafe'],
            'target_city_ids' => [$target->id],
        ]);

        $inTarget = $this->community($target);
        $inUnrelated = $this->community($unrelated);

        $ids = $this->counterpartIds(
            $this->finder()->candidatesFor($viewer->fresh(), SuggestionAudience::Business)
        );

        $this->assertContains($inTarget->id, $ids);
        $this->assertNotContains($inUnrelated->id, $ids);
    }

    public function test_excludes_blocked_pairs_in_either_direction(): void
    {
        $city = City::factory()->create();
        $viewer = $this->business($city);

        $blockedByViewer = $this->community($city);
        $blockedTheViewer = $this->community($city);
        $untouched = $this->community($city);

        UserBlock::factory()->create([
            'blocker_profile_id' => $viewer->id,
            'blocked_profile_id' => $blockedByViewer->id,
        ]);

        UserBlock::factory()->create([
            'blocker_profile_id' => $blockedTheViewer->id,
            'blocked_profile_id' => $viewer->id,
        ]);

        $ids = $this->counterpartIds($this->finder()->candidatesFor($viewer, SuggestionAudience::Business));

        $this->assertSame([$untouched->id], $ids);
    }

    public function test_excludes_pairs_with_an_open_application_or_active_collaboration(): void
    {
        $city = City::factory()->create();
        $viewer = $this->business($city);

        $applied = $this->community($city);
        $collaborating = $this->community($city);
        $declined = $this->community($city);
        $finished = $this->community($city);
        $available = $this->community($city);

        $kolab = Kolab::factory()->published()->create(['creator_profile_id' => $viewer->id]);

        Application::factory()->pending()->create([
            'kolab_id' => $kolab->id,
            'applicant_profile_id' => $applied->id,
        ]);

        Application::factory()->declined()->create([
            'kolab_id' => $kolab->id,
            'applicant_profile_id' => $declined->id,
        ]);

        Collaboration::factory()->active()->create([
            'creator_profile_id' => $viewer->id,
            'applicant_profile_id' => $collaborating->id,
        ]);

        Collaboration::factory()->completed()->create([
            'creator_profile_id' => $viewer->id,
            'applicant_profile_id' => $finished->id,
        ]);

        $ids = $this->counterpartIds($this->finder()->candidatesFor($viewer, SuggestionAudience::Business));

        $this->assertNotContains($applied->id, $ids);
        $this->assertNotContains($collaborating->id, $ids);
        $this->assertContains($declined->id, $ids, 'a declined application is not an open conversation');
        $this->assertContains($finished->id, $ids, 'a finished collaboration is a reason to suggest, not to exclude');
        $this->assertContains($available->id, $ids);
    }

    /**
     * The exclusion also has to fire when the *viewer* is the applicant, which is
     * the direction a community-audience pass sees.
     */
    public function test_excludes_a_pair_where_the_viewer_applied_to_the_counterpart(): void
    {
        $city = City::factory()->create();
        $viewer = $this->community($city);
        $applicable = $this->business($city);
        $available = $this->business($city);

        $kolab = Kolab::factory()->published()->create(['creator_profile_id' => $applicable->id]);

        Application::factory()->pending()->create([
            'kolab_id' => $kolab->id,
            'applicant_profile_id' => $viewer->id,
        ]);

        $ids = $this->counterpartIds($this->finder()->candidatesFor($viewer, SuggestionAudience::Community));

        $this->assertNotContains($applicable->id, $ids);
        $this->assertContains($available->id, $ids);
    }

    /**
     * The mirror of the case above, and the *common* direction on a
     * community-audience pass: the community applied, so the business created the
     * Kolab and the community is the applicant. Without the second half of the
     * `orWhere` this pair would be suggested while the two are actively working
     * together.
     */
    public function test_excludes_a_pair_whose_live_collaboration_the_counterpart_created(): void
    {
        $city = City::factory()->create();
        $viewer = $this->community($city);

        $collaborating = $this->business($city);
        $available = $this->business($city);

        Collaboration::factory()->scheduled()->create([
            'creator_profile_id' => $collaborating->id,
            'applicant_profile_id' => $viewer->id,
        ]);

        $ids = $this->counterpartIds($this->finder()->candidatesFor($viewer, SuggestionAudience::Community));

        $this->assertNotContains($collaborating->id, $ids);
        $this->assertContains($available->id, $ids);
    }

    /**
     * "Both profiles active" is enforced entirely by the `SoftDeletes` global
     * scope on `Profile`, which means a `withTrashed()` added anywhere in this
     * chain would silently reopen deleted accounts as suggestion targets with no
     * other assertion noticing.
     */
    public function test_excludes_a_soft_deleted_counterpart(): void
    {
        $city = City::factory()->create();
        $viewer = $this->business($city);

        $deleted = $this->community($city);
        $active = $this->community($city);

        $deleted->delete();

        $this->assertSoftDeleted('profiles', ['id' => $deleted->id]);

        $ids = $this->counterpartIds($this->finder()->candidatesFor($viewer, SuggestionAudience::Business));

        $this->assertSame([$active->id], $ids);
    }

    public function test_excludes_pairs_dismissed_within_the_cooldown_window(): void
    {
        config()->set('suggestions.dismissal_cooldown_days', 60);

        $city = City::factory()->create();
        $viewer = $this->business($city);

        $justDismissed = $this->community($city);
        $dismissedLongAgo = $this->community($city);
        $shownButKept = $this->community($city);

        KolabSuggestion::factory()->create([
            'viewer_profile_id' => $viewer->id,
            'counterpart_profile_id' => $justDismissed->id,
            'dismissed_at' => now()->subDays(10),
        ]);

        KolabSuggestion::factory()->create([
            'viewer_profile_id' => $viewer->id,
            'counterpart_profile_id' => $dismissedLongAgo->id,
            'dismissed_at' => now()->subDays(90),
        ]);

        KolabSuggestion::factory()->create([
            'viewer_profile_id' => $viewer->id,
            'counterpart_profile_id' => $shownButKept->id,
            'shown_at' => now()->subDay(),
        ]);

        $ids = $this->counterpartIds($this->finder()->candidatesFor($viewer, SuggestionAudience::Business));

        $this->assertNotContains($justDismissed->id, $ids);
        $this->assertContains($dismissedLongAgo->id, $ids, 'a cooldown that never expires is a permanent block');
        $this->assertContains($shownButKept->id, $ids);
    }

    public function test_excludes_counterparts_with_no_categories_and_no_history(): void
    {
        $city = City::factory()->create();
        $viewer = $this->community($city);

        $blank = Profile::factory()->business()->create();
        BusinessProfile::factory()->create([
            'profile_id' => $blank->id,
            'city_id' => $city->id,
            'business_type' => null,
            'categories' => [],
        ]);

        $categorised = $this->business($city);

        $historyOnly = Profile::factory()->business()->create();
        BusinessProfile::factory()->create([
            'profile_id' => $historyOnly->id,
            'city_id' => $city->id,
            'business_type' => null,
            'categories' => [],
        ]);
        Event::factory()->forProfile($historyOnly)->create();

        $ids = $this->counterpartIds($this->finder()->candidatesFor($viewer, SuggestionAudience::Community));

        $this->assertNotContains($blank->id, $ids);
        $this->assertContains($categorised->id, $ids);
        $this->assertContains($historyOnly->id, $ids, 'a real event history is enough to be proposable');
    }

    /**
     * The point of the ceiling is to fail the day a per-pair query is
     * reintroduced, not to pin an exact number: the batch count is a function of
     * the audience, so it must not move when the number of candidates does.
     */
    public function test_loads_past_attendance_and_delivery_aggregates_without_n_plus_one(): void
    {
        $city = City::factory()->create();
        $viewer = $this->business($city, ['capacity' => 60, 'latitude' => 37.3891, 'longitude' => -5.9845]);

        $communities = [];

        for ($index = 0; $index < 6; $index++) {
            $community = $this->community($city);
            $communities[] = $community;

            Event::factory()->forProfile($community)->create([
                'event_date' => now()->subDays(10)->toDateString(),
                'attendee_count' => 30 + $index,
                'location_lat' => 37.3826,
                'location_lng' => -5.9963,
            ]);

            EventSeries::factory()->forProfile($community)->create();

            $collaboration = Collaboration::factory()->completed()->create([
                'creator_profile_id' => $viewer->id,
                'applicant_profile_id' => $community->id,
            ]);

            CollaborationFeedback::factory()->business()->create([
                'collaboration_id' => $collaboration->id,
                'reviewer_profile_id' => $viewer->id,
                'posts_reels' => 2,
                'stories_posted' => 3,
            ]);

            CollaborationReview::factory()->create([
                'collaboration_id' => $collaboration->id,
                'reviewer_profile_id' => $viewer->id,
                'reviewed_profile_id' => $community->id,
                'rating' => 4,
                'would_collaborate_again' => true,
            ]);
        }

        $contexts = [];
        $queries = $this->countQueries(function () use ($viewer, &$contexts): void {
            $contexts = $this->finder()->candidatesFor($viewer, SuggestionAudience::Business);
        });

        $this->assertCount(6, $contexts);
        $this->assertLessThan(12, $queries, "the finder ran {$queries} queries for 6 candidates");

        // The same pass over a pool of exactly one, in a city of its own — a
        // second viewer in *this* city would see the same six communities and a
        // per-pair query would add six to both measurements, leaving the
        // comparison true and guarding nothing.
        $otherCity = City::factory()->create();
        $lonelyViewer = $this->business($otherCity);
        $onlyCandidate = $this->community($otherCity);

        $onePool = [];
        $onePoolQueries = $this->countQueries(function () use ($lonelyViewer, &$onePool): void {
            $onePool = $this->finder()->candidatesFor($lonelyViewer, SuggestionAudience::Business);
        });

        $this->assertSame([$onlyCandidate->id], $this->counterpartIds($onePool));
        $this->assertSame(
            $onePoolQueries,
            $queries,
            "one candidate cost {$onePoolQueries} queries and six cost {$queries}; the batch count must not grow with the pool"
        );

        $first = $contexts[0];
        $this->assertNotEmpty($first->pastAttendance);
        $this->assertSame(5, $first->contentDelivered);
        $this->assertSame(0, $first->completedCollaborations);
        $this->assertSame(1, $first->reviewCount);
        $this->assertSame(4.0, $first->averageRating);
        $this->assertSame(1.0, $first->repeatRatio);
        $this->assertSame(1, $first->recentEventCount);
        $this->assertTrue($first->hasActiveSeries);
        $this->assertSame(60, $first->venueCapacity);
        $this->assertNotNull($first->distanceKm);
        $this->assertGreaterThan(0.0, $first->distanceKm);
        $this->assertLessThan(5.0, $first->distanceKm);
    }

    /**
     * The volume term of `delivery_proof` is audience-specific: a business does
     * not deliver posts, and a community does not accumulate
     * `completed_kolabs_count`. Populating both would defeat the scorer's
     * audience check, which is the only thing keeping the two counts apart.
     */
    public function test_a_community_audience_reads_completed_kolabs_and_not_content(): void
    {
        $city = City::factory()->create();
        $viewer = $this->community($city);
        $partner = $this->business($city);

        BusinessPartnerStatus::factory()->create([
            'profile_id' => $partner->id,
            'completed_kolabs_count' => 4,
        ]);

        $collaboration = Collaboration::factory()->completed()->create([
            'creator_profile_id' => $partner->id,
            'applicant_profile_id' => $viewer->id,
        ]);

        CollaborationFeedback::factory()->business()->create([
            'collaboration_id' => $collaboration->id,
            'reviewer_profile_id' => $partner->id,
            'posts_reels' => 9,
            'stories_posted' => 9,
        ]);

        $contexts = $this->finder()->candidatesFor($viewer, SuggestionAudience::Community);

        $this->assertCount(1, $contexts);
        $this->assertSame(4, $contexts[0]->completedCollaborations);
        $this->assertSame(0, $contexts[0]->contentDelivered);
    }

    /**
     * `event_series.byweekday` is 0 = Sunday .. 6 = Saturday and FormatSuggester
     * converts to ISO itself, throwing on a value that has already been
     * converted. The finder must therefore hand the stored values straight
     * through.
     */
    public function test_carries_the_series_cadence_through_in_the_stored_weekday_convention(): void
    {
        $city = City::factory()->create();
        $viewer = $this->business($city);
        $community = $this->community($city);

        EventSeries::factory()->forProfile($community)->onWeekdays([0, 3])->create([
            'time_of_day' => '18:30',
        ]);

        // The most recent Sunday that is genuinely in the PAST: `Carbon::dayOfWeek`
        // is 0 on a Sunday, which is the convention both `byweekday` and the
        // assertion below use — but `subDays(0)` on a Sunday lands on today, and
        // today is not a past event, so `past_event_weekdays` came back empty and
        // this test failed every Sunday. Step back a full week in that case.
        $daysBack = now()->dayOfWeek === 0 ? 7 : now()->dayOfWeek;

        Event::factory()->forProfile($community)->create([
            'event_date' => now()->subDays($daysBack)->toDateString(),
            'attendee_count' => 25,
        ]);

        $evidence = $this->finder()->candidatesFor($viewer, SuggestionAudience::Business)[0]->evidence;

        $this->assertSame([0, 3], $evidence['series_weekdays']);
        $this->assertSame('18:30', $evidence['series_time_of_day']);
        $this->assertSame([0], $evidence['past_event_weekdays']);
    }

    /**
     * A rule that has already run out is not a live cadence, so it must not read
     * as momentum.
     */
    public function test_an_expired_series_is_not_an_active_series(): void
    {
        $city = City::factory()->create();
        $viewer = $this->business($city);
        $community = $this->community($city);

        EventSeries::factory()->forProfile($community)->ended()->create();

        $context = $this->finder()->candidatesFor($viewer, SuggestionAudience::Business)[0];

        $this->assertFalse($context->hasActiveSeries);
        $this->assertSame([], $context->evidence['series_weekdays']);
    }

    /**
     * Both mirrored intersections have to be fillable, and from different
     * vocabularies: the business gives from `offering` and asks with `expects`,
     * the community gives with `offers_in_return` and asks with `needs`.
     */
    public function test_populates_both_mirrored_offer_and_need_pairs(): void
    {
        $city = City::factory()->create();
        $viewer = $this->business($city);
        $community = $this->community($city);

        Kolab::factory()->create([
            'creator_profile_id' => $viewer->id,
            'offering' => ['venue', 'food_drink'],
            'expects' => ['social_media', 'ugc_content'],
            'needs' => null,
            'offers_in_return' => null,
        ]);

        Kolab::factory()->create([
            'creator_profile_id' => $community->id,
            'needs' => ['venue'],
            'offers_in_return' => ['social_media'],
            'offering' => null,
            'expects' => null,
        ]);

        $context = $this->finder()->candidatesFor($viewer, SuggestionAudience::Business)[0];

        $this->assertEqualsCanonicalizing(['venue', 'food_drink'], $context->viewerOffers);
        $this->assertSame(['venue'], $context->counterpartNeeds);
        $this->assertSame(['social_media'], $context->counterpartOffers);
        $this->assertEqualsCanonicalizing(['social_media', 'ugc_content'], $context->viewerNeeds);
    }

    /**
     * Roughly 45% of live rows in these four columns are the legacy
     * keyed-boolean shape rather than a list of slugs. The fixture below is a
     * verbatim copy of a production `kolabs.offering` row (read-only,
     * 2026-08-19), nested `discount` included: an intersection against it would
     * otherwise match nothing and report a false 0.0 for half the corpus.
     */
    public function test_reads_the_legacy_keyed_boolean_offer_shape(): void
    {
        $city = City::factory()->create();
        $viewer = $this->business($city);
        $this->community($city);

        Kolab::factory()->create([
            'creator_profile_id' => $viewer->id,
            'offering' => [
                'venue' => true,
                'food_drink' => false,
                'social_media_exposure' => false,
                'content_creation' => false,
                'discount' => ['enabled' => false],
            ],
            'expects' => ['reviews' => true, 'ugc_content' => false],
            'needs' => null,
            'offers_in_return' => null,
        ]);

        $context = $this->finder()->candidatesFor($viewer, SuggestionAudience::Business)[0];

        $this->assertSame(['venue'], $context->viewerOffers);
        $this->assertSame(['reviews'], $context->viewerNeeds);
    }

    /**
     * `has_venue` is a column, not something capacity can stand in for: 18 of the
     * 62 live venue businesses have no `capacity` key in `primary_venue`.
     */
    public function test_carries_the_has_venue_flag_independently_of_capacity(): void
    {
        $city = City::factory()->create();

        $venueWithoutCapacity = $this->business($city, ['latitude' => 37.38, 'longitude' => -5.98]);
        $community = $this->community($city);

        $businessAudience = $this->finder()->candidatesFor($venueWithoutCapacity, SuggestionAudience::Business)[0];

        $this->assertTrue($businessAudience->viewerHasVenue);
        $this->assertNull($businessAudience->venueCapacity);
        $this->assertFalse($businessAudience->counterpartHasVenue, 'a community never promotes a venue');

        $communityAudience = collect($this->finder()->candidatesFor($community, SuggestionAudience::Community))
            ->firstWhere('counterpartProfileId', $venueWithoutCapacity->id);

        $this->assertNotNull($communityAudience);
        $this->assertTrue($communityAudience->counterpartHasVenue);
        $this->assertFalse($communityAudience->viewerHasVenue);
    }

    public function test_a_business_without_a_venue_reports_the_flag_as_false(): void
    {
        $city = City::factory()->create();
        $viewer = $this->business($city, null, ['cafe'], hasVenue: false);
        $this->community($city);

        $context = $this->finder()->candidatesFor($viewer, SuggestionAudience::Business)[0];

        $this->assertFalse($context->viewerHasVenue);
    }

    /**
     * A business counterpart proves momentum in Kolabs and collaborations, not
     * events, and only inside the configured window.
     */
    public function test_a_business_counterpart_reports_momentum_as_kolabs_and_collaborations(): void
    {
        config()->set('suggestions.momentum_window_days', 90);

        $city = City::factory()->create();
        $viewer = $this->community($city);
        $partner = $this->business($city);

        Kolab::factory()->published()->create([
            'creator_profile_id' => $partner->id,
            'published_at' => now()->subDays(5),
        ]);
        Kolab::factory()->published()->create([
            'creator_profile_id' => $partner->id,
            'published_at' => now()->subDays(200),
        ]);
        Kolab::factory()->create([
            'creator_profile_id' => $partner->id,
            'status' => 'draft',
            'published_at' => null,
        ]);

        Collaboration::factory()->completed()->create([
            'creator_profile_id' => $partner->id,
            'applicant_profile_id' => $viewer->id,
            'created_at' => now()->subDays(3),
        ]);
        Collaboration::factory()->cancelled()->create([
            'creator_profile_id' => $partner->id,
            'applicant_profile_id' => $viewer->id,
            'created_at' => now()->subDays(300),
        ]);

        $context = collect($this->finder()->candidatesFor($viewer, SuggestionAudience::Community))
            ->firstWhere('counterpartProfileId', $partner->id);

        $this->assertNotNull($context);
        $this->assertSame(2, $context->recentActivityCount, 'one live Kolab plus one recent collaboration');
        $this->assertSame(1, $context->evidence['recent_live_kolabs']);
        $this->assertSame(1, $context->evidence['recent_collaborations']);
    }

    /**
     * The business audience never needs partner activity, so it is not loaded —
     * the same discipline that keeps `completedCollaborations` out of it.
     */
    public function test_a_business_audience_does_not_load_partner_activity(): void
    {
        $city = City::factory()->create();
        $viewer = $this->business($city);
        $community = $this->community($city);

        Kolab::factory()->published()->create([
            'creator_profile_id' => $community->id,
            'published_at' => now()->subDay(),
        ]);

        $context = $this->finder()->candidatesFor($viewer, SuggestionAudience::Business)[0];

        $this->assertSame(0, $context->recentActivityCount);
    }

    /**
     * The alias table bridges live, admin-editable taxonomies, so every spelling
     * in it has to be a slug one of them actually holds — a typo would silently
     * bridge nothing, which is exactly the failure the table exists to prevent.
     *
     * Checked against every `OfferOption` kind rather than the three offer kinds:
     * `discount_code` is a `product_interaction` slug and `free_samples` a
     * `kolab_highlight` one. Both arrive with the map extracted from Explore,
     * which text-matches offer terms across fields drawing on those kinds. They
     * are inert for the offer/need comparison here and are kept so adopting this
     * class in Explore later does not lose behaviour.
     *
     * `commission` is the one exemption: it is in Explore's map and in no
     * taxonomy at all.
     */
    public function test_every_offer_type_alias_is_a_live_offer_options_slug(): void
    {
        // The reference-taxonomy migration deliberately skips the `testing`
        // environment, so the vocabulary has to be seeded explicitly here.
        $this->seed(OfferOptionSeeder::class);

        $seeded = OfferOption::query()->pluck('slug')->all();

        $this->assertNotEmpty($seeded, 'offer_options must be seeded by the reference-taxonomy migration');

        $unknown = [];

        foreach (OfferTypeAliases::ALIASES as $canonical => $spellings) {
            foreach ($spellings as $spelling) {
                if ($spelling !== 'commission' && ! in_array($spelling, $seeded, true)) {
                    $unknown[] = $canonical.' => '.$spelling;
                }
            }
        }

        $this->assertSame([], $unknown, 'aliases matching no live slug: '.implode(', ', $unknown));
    }

    /**
     * The live `business_profiles.categories` column carries Spanish slugs the
     * matrix has no column for. Canonicalising is what keeps `category_fit` from
     * silently dropping for those businesses.
     */
    public function test_canonicalises_stored_category_and_community_type_slugs(): void
    {
        $city = City::factory()->create();
        $viewer = $this->community($city, 'wellness-community');
        $this->business($city, null, ['tienda-de-deportes', 'Cafeteria']);

        $context = $this->finder()->candidatesFor($viewer, SuggestionAudience::Community)[0];

        $this->assertSame('wellness_community', $context->communityType);
        $this->assertSame(['sports_facility', 'cafe'], $context->businessCategories);
    }

    /**
     * Without coordinates on both sides there is no distance to report, and
     * inventing one would hand the scorer a number it treats as measured. The
     * scorer falls back to city equality instead.
     */
    public function test_leaves_distance_null_when_either_side_has_no_coordinates(): void
    {
        $city = City::factory()->create();
        $viewer = $this->business($city, ['capacity' => 40, 'latitude' => 37.3891, 'longitude' => -5.9845]);
        $community = $this->community($city);

        Event::factory()->forProfile($community)->create([
            'event_date' => now()->subDays(5)->toDateString(),
            'attendee_count' => 20,
            'location_lat' => null,
            'location_lng' => null,
        ]);

        $context = $this->finder()->candidatesFor($viewer, SuggestionAudience::Business)[0];

        $this->assertNull($context->distanceKm);
        $this->assertSame($context->viewerCityId, $context->counterpartCityId);
    }

    /**
     * An event that never reported an attendee count is not evidence of scale;
     * PairContext rejects it outright, so it must be filtered before it gets
     * there.
     */
    public function test_drops_events_with_no_reported_attendance_from_past_attendance(): void
    {
        $city = City::factory()->create();
        $viewer = $this->business($city);
        $community = $this->community($city);

        Event::factory()->forProfile($community)->create([
            'event_date' => now()->subDays(5)->toDateString(),
            'attendee_count' => 0,
        ]);
        Event::factory()->forProfile($community)->create([
            'event_date' => now()->subDays(4)->toDateString(),
            'attendee_count' => 40,
        ]);

        $context = $this->finder()->candidatesFor($viewer, SuggestionAudience::Business)[0];

        $this->assertSame([40], $context->pastAttendance);
        $this->assertSame(2, $context->recentEventCount);
    }

    /**
     * The audience always mirrors the viewer's `user_type`. Nothing downstream
     * reads both, so a mismatch would score a business against businesses and
     * label the row `community` in silence.
     */
    public function test_rejects_an_audience_that_does_not_match_the_viewer(): void
    {
        $city = City::factory()->create();
        $viewer = $this->business($city);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('the audience always mirrors the viewer');

        $this->finder()->candidatesFor($viewer, SuggestionAudience::Community);
    }

    /**
     * A viewer with no city has no pool to narrow, and the alternative — every
     * profile of the counterpart type on the platform, for every profile, every
     * night — is not a pool either.
     */
    public function test_a_viewer_without_a_city_has_no_candidates(): void
    {
        $city = City::factory()->create();
        $this->community($city);

        $viewer = Profile::factory()->business()->create(['city_id' => null]);
        BusinessProfile::factory()->create([
            'profile_id' => $viewer->id,
            'city_id' => null,
            'business_type' => 'cafe',
            'categories' => ['cafe'],
        ]);

        $this->assertSame([], $this->finder()->candidatesFor($viewer->fresh(), SuggestionAudience::Business));
    }

    /**
     * A collaboration only excludes the pair while it is live: a cancelled one
     * leaves the pair as unintroduced as it was before.
     */
    public function test_a_cancelled_collaboration_does_not_exclude_the_pair(): void
    {
        $city = City::factory()->create();
        $viewer = $this->business($city);
        $community = $this->community($city);

        Collaboration::factory()->cancelled()->create([
            'creator_profile_id' => $viewer->id,
            'applicant_profile_id' => $community->id,
        ]);

        $this->assertSame(
            [$community->id],
            $this->counterpartIds($this->finder()->candidatesFor($viewer, SuggestionAudience::Business))
        );
    }
}
