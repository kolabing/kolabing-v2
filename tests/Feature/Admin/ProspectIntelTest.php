<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\BusinessProfile;
use App\Models\Profile;
use App\Models\SalesOutreachDraft;
use App\Services\SalesOutreach\ProspectIntel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Prospect research (BE-NF-67).
 *
 * The pitch used to know only what a maintainer typed, while `primary_venue` had
 * been carrying the Google rating, review count, opening hours and price level since
 * import — unused. That stored data is the largest part of this and costs nothing.
 *
 * The other two sources leave our network, so they are tested for how they *fail*:
 * an unreachable site and a dead weather API must both degrade to a thinner brief,
 * never to a failed pitch. And the website fetch is the one place where a stranger's
 * input decides what our server connects to, which is the textbook shape of an SSRF
 * — half this file is about refusing those addresses.
 */
class ProspectIntelTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Any request this file does not explicitly fake is a bug, not a pass. The
        // first version of the website test used a pattern that did not match, so it
        // silently fetched the real example.com and asserted against Fastly's copy.
        Http::preventStrayRequests();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function business(array $attributes = []): Profile
    {
        $profile = Profile::factory()->business()->create();

        BusinessProfile::factory()->create([
            'profile_id' => $profile->id,
            'name' => 'Eixample 46',
            'website' => null,
            'primary_venue' => [
                'venue_type' => 'cafe',
                'capacity' => 45,
                'rating' => 4.2,
                'user_ratings_total' => 418,
                'price_level' => 'PRICE_LEVEL_MODERATE',
                'opening_hours' => ['Monday: 9:00 – 18:00', 'Tuesday: 9:00 – 18:00'],
                'latitude' => 41.39,
                'longitude' => 2.16,
            ],
            ...$attributes,
        ]);

        return $profile->fresh();
    }

    // ── What we already hold ────────────────────────────────────────────

    public function test_the_google_data_we_already_stored_is_used(): void
    {
        config()->set('sales_outreach.intel.website', false);
        config()->set('sales_outreach.intel.weather', false);

        $places = app(ProspectIntel::class)->gather($this->business())['places'];

        $this->assertSame(4.2, $places['google_rating']);
        $this->assertSame(418, $places['google_review_count']);
        $this->assertCount(2, $places['opening_hours']);
        $this->assertSame('PRICE_LEVEL_MODERATE', $places['price_level']);
    }

    /** A business with no venue is not an error, just a thinner brief. */
    public function test_a_business_with_no_venue_still_returns_a_shape(): void
    {
        config()->set('sales_outreach.intel.website', false);
        config()->set('sales_outreach.intel.weather', false);

        $intel = app(ProspectIntel::class)->gather($this->business(['primary_venue' => null]));

        $this->assertSame([], $intel['places']);
    }

    // ── Their website ───────────────────────────────────────────────────

    public function test_the_businesss_own_website_is_read(): void
    {
        config()->set('sales_outreach.intel.weather', false);

        Http::fake(['https://example.com/site' => Http::response(
            '<html><head><title>Eixample 46</title>'
            .'<meta name="description" content="Speciality coffee in Barcelona">'
            .'</head><body><script>ignore()</script><p>We host private events on Sundays.</p></body></html>'
        )]);

        $intel = app(ProspectIntel::class)->gather($this->business(['website' => 'https://example.com/site']));

        $this->assertSame('Eixample 46', $intel['website']['title']);
        $this->assertSame('Speciality coffee in Barcelona', $intel['website']['description']);
        $this->assertStringContainsString('We host private events on Sundays.', $intel['website']['text_excerpt']);
        // Script bodies are not prose and would waste the excerpt budget.
        $this->assertStringNotContainsString('ignore()', $intel['website']['text_excerpt']);
    }

    public function test_an_unreachable_website_is_simply_absent(): void
    {
        config()->set('sales_outreach.intel.weather', false);
        Http::fake(['*' => Http::response('', 500)]);

        $intel = app(ProspectIntel::class)->gather($this->business(['website' => 'https://example.com/site']));

        $this->assertArrayNotHasKey('website', $intel);
        $this->assertArrayHasKey('places', $intel, 'The stored data must survive a failed fetch.');
    }

    // ── The SSRF guard ──────────────────────────────────────────────────

    /**
     * `website` is supplied by whoever filled the form and we are about to make our
     * own server fetch it. The `url` validation rule checks the shape, not the
     * destination — it happily accepts localhost and cloud metadata addresses.
     *
     * @dataProvider blockedAddresses
     */
    public function test_private_and_reserved_addresses_are_never_fetched(string $url): void
    {
        config()->set('sales_outreach.intel.weather', false);
        Http::fake();

        $intel = app(ProspectIntel::class)->gather($this->business(['website' => $url]));

        $this->assertArrayNotHasKey('website', $intel);
        Http::assertNothingSent();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function blockedAddresses(): array
    {
        return [
            'loopback' => ['http://127.0.0.1/'],
            'loopback by name' => ['http://localhost/'],
            'private 10.x' => ['http://10.0.0.5/'],
            'private 192.168.x' => ['http://192.168.1.1/'],
            'link-local / cloud metadata' => ['http://169.254.169.254/latest/meta-data/'],
            'not http' => ['file:///etc/passwd'],
            'gopher' => ['gopher://example.com/'],
        ];
    }

    // ── Weather ─────────────────────────────────────────────────────────

    public function test_the_forecast_at_the_venue_is_collected(): void
    {
        config()->set('sales_outreach.intel.website', false);

        Http::fake(['api.open-meteo.com/*' => Http::response([
            'daily' => [
                'time' => ['2026-09-22', '2026-09-23'],
                'temperature_2m_max' => [24.1, 19.8],
                'precipitation_probability_max' => [5, 80],
            ],
        ])]);

        $days = app(ProspectIntel::class)->gather($this->business())['weather']['next_7_days'];

        $this->assertCount(2, $days);
        $this->assertSame(80, $days[1]['rain_probability_pct']);
    }

    public function test_a_venue_without_coordinates_skips_the_forecast(): void
    {
        config()->set('sales_outreach.intel.website', false);
        Http::fake();

        $intel = app(ProspectIntel::class)->gather($this->business([
            'primary_venue' => ['venue_type' => 'cafe', 'capacity' => 45],
        ]));

        $this->assertArrayNotHasKey('weather', $intel);
    }

    /** Each source is switchable, because each fails differently. */
    public function test_sources_can_be_switched_off(): void
    {
        config()->set('sales_outreach.intel.website', false);
        config()->set('sales_outreach.intel.weather', false);
        Http::fake();

        $intel = app(ProspectIntel::class)->gather($this->business(['website' => 'https://example.com']));

        $this->assertSame(['places'], array_keys($intel));
        Http::assertNothingSent();
    }

    // ── It reaches the draft ────────────────────────────────────────────

    /**
     * Stored, not recomputed: a pitch claiming "Tuesday morning is your quietest
     * shift" has to stay explainable next month, when their hours have changed.
     */
    public function test_the_draft_keeps_what_was_found(): void
    {
        $draft = SalesOutreachDraft::factory()->create([
            'intel' => ['places' => ['google_rating' => 4.2]],
            'angle' => 'quiet_hours',
        ]);

        $this->assertSame(4.2, $draft->refresh()->intel['places']['google_rating']);
        $this->assertSame('quiet_hours', $draft->angle);
    }
}
