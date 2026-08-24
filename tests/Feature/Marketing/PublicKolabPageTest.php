<?php

declare(strict_types=1);

namespace Tests\Feature\Marketing;

use App\Enums\IntentType;
use App\Enums\KolabStatus;
use App\Models\BusinessProfile;
use App\Models\City;
use App\Models\CommunityProfile;
use App\Models\Kolab;
use App\Models\OfferOption;
use App\Models\Profile;
use App\Support\OfferOptionLabels;
use App\Support\PublicKolabLink;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * The marketplace on the open web — `kolabing.com/kolabs`.
 *
 * Two families of assertion carry the weight here.
 *
 * The first is the gate: a draft or date-exhausted Kolab must be impossible to list
 * *and* impossible to reach by guessing a URL, including by full UUID. ROLES §3.3
 * hides date-exhausted Kolabs from Explore so nobody lands on an empty date picker,
 * and a stranger arriving from Google gets that same guarantee.
 *
 * The second is the identity rule. A community's name is blurred from a free business
 * on Explore (§2.5); if this page printed it next to the community's Kolab, that blur
 * could be defeated by logging out. A business name is never blurred from anyone
 * (§3.3), so it is printed. Those two facts are asserted directly, because a
 * well-meaning "let's show who posted it" edit would silently undo the first.
 */
class PublicKolabPageTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Labels are memoised per request; the memo must not survive between tests.
        OfferOptionLabels::flush();
    }

    private function communityCreator(string $name = 'Barcelona Runners', string $type = 'run_club'): Profile
    {
        $profile = Profile::factory()->community()->create();
        CommunityProfile::factory()->create([
            'profile_id' => $profile->id,
            'name' => $name,
            'community_type' => $type,
        ]);

        return $profile->fresh();
    }

    private function businessCreator(string $name = 'Honest Greens'): Profile
    {
        $profile = Profile::factory()->business()->create();
        BusinessProfile::factory()->create([
            'profile_id' => $profile->id,
            'name' => $name,
            'business_type' => 'restaurant',
        ]);

        return $profile->fresh();
    }

    /**
     * A Kolab shaped the way the API actually stores one: `needs` and
     * `offers_in_return` are lists of slugs (see CreateKolabRequest, which validates
     * `needs.*` as a string from the slug vocabulary). The factory still writes the
     * older associative-boolean shape, so these are set explicitly.
     */
    private function kolab(array $attributes = []): Kolab
    {
        $creator = $attributes['creator'] ?? $this->communityCreator();
        unset($attributes['creator']);

        return Kolab::factory()->create(array_merge([
            'creator_profile_id' => $creator->id,
            'intent_type' => IntentType::CommunitySeeking,
            'status' => KolabStatus::Published,
            'published_at' => now(),
            'title' => 'Sunday Run and Brunch',
            'description' => 'We run from Barceloneta every Sunday morning and want a brunch partner.',
            'preferred_city' => 'Barcelona',
            'needs' => ['food_drink'],
            'offers_in_return' => ['social_media'],
            'availability_start' => now()->addWeek(),
            'availability_end' => now()->addMonth(),
        ], $attributes));
    }

    private function seedOfferLabels(): void
    {
        OfferOption::query()->create([
            'kind' => OfferOption::KIND_NEED, 'slug' => 'food_drink',
            'name' => 'Food & Drink', 'sort_order' => 1, 'is_active' => true,
        ]);
        OfferOption::query()->create([
            'kind' => OfferOption::KIND_DELIVERABLE, 'slug' => 'social_media',
            'name' => 'Social Media', 'sort_order' => 1, 'is_active' => true,
        ]);

        OfferOptionLabels::flush();
    }

    public function test_listing_shows_a_published_in_date_kolab(): void
    {
        $this->kolab();

        $this->get('/kolabs')
            ->assertOk()
            ->assertSee('Sunday Run and Brunch')
            ->assertSee('1 Kolab open');
    }

    public function test_listing_hides_a_draft_kolab(): void
    {
        $this->kolab(['status' => KolabStatus::Draft, 'published_at' => null, 'title' => 'Not Ready Yet']);

        $this->get('/kolabs')
            ->assertOk()
            ->assertDontSee('Not Ready Yet');
    }

    public function test_listing_hides_a_closed_kolab(): void
    {
        $this->kolab(['status' => KolabStatus::Closed, 'title' => 'Already Filled']);

        $this->get('/kolabs')
            ->assertOk()
            ->assertDontSee('Already Filled');
    }

    /** ROLES §3.3: a Kolab whose dates have all passed is hidden, so nobody lands on an empty date picker. */
    public function test_listing_hides_a_date_exhausted_kolab(): void
    {
        $this->kolab([
            'title' => 'Last Summer Only',
            'availability_start' => now()->subMonths(3),
            'availability_end' => now()->subDay(),
        ]);

        $this->get('/kolabs')
            ->assertOk()
            ->assertDontSee('Last Summer Only');
    }

    public function test_listing_keeps_an_open_ended_kolab(): void
    {
        $this->kolab(['title' => 'Always Open', 'availability_end' => null]);

        $this->get('/kolabs')
            ->assertOk()
            ->assertSee('Always Open');
    }

    /**
     * The presentability floor. Not a junk filter — see
     * PublicKolabFeedService::publishable() for what it deliberately cannot do.
     */
    public function test_listing_hides_a_kolab_with_no_real_description(): void
    {
        $this->kolab(['title' => 'Barely Filled In', 'description' => 'testhj']);

        $this->get('/kolabs')
            ->assertOk()
            ->assertDontSee('Barely Filled In');
    }

    public function test_a_community_that_posted_is_described_but_never_named(): void
    {
        $this->kolab(['creator' => $this->communityCreator('Barcelona Runners', 'run_club')]);

        $response = $this->get('/kolabs')->assertOk();

        // The blur in §2.5 is worth nothing if logging out reveals the pairing.
        $response->assertDontSee('Barcelona Runners');
        $response->assertSee('A run club in Barcelona');
    }

    public function test_a_business_that_posted_is_named(): void
    {
        $this->kolab([
            'creator' => $this->businessCreator('Honest Greens'),
            'intent_type' => IntentType::VenuePromotion,
            'offering' => ['venue'],
            'needs' => null,
            'offers_in_return' => null,
        ]);

        // §3.3: "The business name (never blurred; communities have full access)".
        $this->get('/kolabs')->assertOk()->assertSee('Honest Greens');
    }

    public function test_offer_labels_come_from_the_lookup_table(): void
    {
        $this->seedOfferLabels();
        $this->kolab();

        $this->get('/kolabs')
            ->assertOk()
            // §2.3 / §3.3: concrete, never the abstract word "match" — and never a raw slug.
            ->assertSee('Food &amp; Drink', false)
            ->assertSee('Social Media')
            ->assertDontSee('food_drink');
    }

    public function test_detail_page_resolves_by_slug(): void
    {
        $kolab = $this->kolab();

        $this->get(PublicKolabLink::urlFor($kolab))
            ->assertOk()
            ->assertSee('Sunday Run and Brunch')
            ->assertSee('We run from Barceloneta');
    }

    public function test_detail_page_resolves_by_full_uuid(): void
    {
        $kolab = $this->kolab();

        $this->get('/kolabs/'.$kolab->id)->assertOk()->assertSee('Sunday Run and Brunch');
    }

    public function test_detail_page_404s_for_a_draft_even_with_the_exact_url(): void
    {
        $kolab = $this->kolab(['status' => KolabStatus::Draft, 'published_at' => null]);

        $this->get(PublicKolabLink::urlFor($kolab))->assertNotFound();
        $this->get('/kolabs/'.$kolab->id)->assertNotFound();
    }

    public function test_detail_page_404s_for_a_date_exhausted_kolab(): void
    {
        $kolab = $this->kolab([
            'availability_start' => now()->subMonths(3),
            'availability_end' => now()->subDay(),
        ]);

        $this->get(PublicKolabLink::urlFor($kolab))->assertNotFound();
    }

    public function test_detail_page_sends_applicants_to_the_panel_with_the_intent_attached(): void
    {
        $kolab = $this->kolab();

        $appUrl = rtrim((string) config('webapp.url'), '/');

        $this->get(PublicKolabLink::urlFor($kolab))
            ->assertOk()
            ->assertSee($appUrl.'/kolabs/'.$kolab->id.'?apply=1', false);
    }

    public function test_city_filter_offers_only_cities_that_have_a_listing(): void
    {
        City::query()->create(['name' => 'Barcelona', 'country' => 'ES', 'is_active' => true]);
        City::query()->create(['name' => 'Bilbao', 'country' => 'ES', 'is_active' => true]);

        $this->kolab(['preferred_city' => 'Barcelona']);

        $response = $this->get('/kolabs')->assertOk();

        $response->assertSee('Barcelona');
        // A chip leading to an empty page is worse than no chip.
        $response->assertDontSee('Bilbao');
    }

    public function test_an_unknown_intent_filter_falls_back_to_everything(): void
    {
        $this->kolab();

        $this->get('/kolabs?intent=not-a-real-intent')
            ->assertOk()
            ->assertSee('Sunday Run and Brunch');
    }

    public function test_pages_are_not_indexed_until_the_data_is_curated(): void
    {
        $kolab = $this->kolab();

        config()->set('kolabing.public_kolabs.indexable', false);

        $this->get('/kolabs')->assertOk()->assertSee('noindex,follow', false);
        $this->get(PublicKolabLink::urlFor($kolab))->assertOk()->assertSee('noindex,follow', false);
    }

    public function test_flipping_one_config_value_opens_the_pages_to_crawlers(): void
    {
        $kolab = $this->kolab();

        config()->set('kolabing.public_kolabs.indexable', true);

        $this->get('/kolabs')->assertOk()->assertDontSee('noindex', false);
        $this->get(PublicKolabLink::urlFor($kolab))->assertOk()->assertDontSee('noindex', false);
    }

    public function test_sitemap_stays_silent_about_kolabs_while_they_are_not_indexable(): void
    {
        $kolab = $this->kolab();

        config()->set('kolabing.public_kolabs.indexable', false);

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertDontSee(PublicKolabLink::urlFor($kolab), false)
            ->assertDontSee(route('public-kolabs'), false);
    }

    public function test_sitemap_lists_kolabs_once_they_are_indexable(): void
    {
        $kolab = $this->kolab();

        config()->set('kolabing.public_kolabs.indexable', true);

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertSee(PublicKolabLink::urlFor($kolab), false);
    }

    public function test_sitemap_never_advertises_a_kolab_the_page_would_404(): void
    {
        $hidden = $this->kolab([
            'availability_start' => now()->subMonths(3),
            'availability_end' => now()->subDay(),
        ]);

        config()->set('kolabing.public_kolabs.indexable', true);

        $this->get('/sitemap.xml')->assertOk()->assertDontSee(PublicKolabLink::urlFor($hidden), false);
    }

    public function test_homepage_links_to_both_public_feeds(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee(route('public-events'), false)
            ->assertSee(route('public-kolabs'), false);
    }

    public function test_homepage_shows_live_kolab_cards_that_link_to_the_detail_page(): void
    {
        $kolab = $this->kolab();

        $this->get('/')
            ->assertOk()
            ->assertSee('OPEN KOLABS')
            ->assertSee('Sunday Run and Brunch')
            ->assertSee(PublicKolabLink::urlFor($kolab), false);
    }

    /** A homepage section announcing "nothing open" is worse than no section. */
    public function test_homepage_omits_the_section_when_nothing_is_open(): void
    {
        $this->get('/')->assertOk()->assertDontSee('OPEN KOLABS');
    }

    public function test_homepage_never_names_the_community_behind_a_card(): void
    {
        $this->kolab(['creator' => $this->communityCreator('Barcelona Runners', 'run_club')]);

        $this->get('/')->assertOk()->assertDontSee('Barcelona Runners');
    }
}
