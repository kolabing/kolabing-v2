<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\BusinessProfile;
use App\Models\CommunityProfile;
use App\Models\Profile;
use App\Models\SalesOutreachDraft;
use App\Services\OpenAi\OpenAiClient;
use App\Services\SalesOutreach\CoverImageBrief;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The cover brief (BE-NF-66).
 *
 * The first covers were generic stock-looking rooms because the brief was one
 * invented sentence and nothing else — the model was never told the venue type, the
 * capacity, the city, or who was coming. This pins the two halves of the fix: the
 * brief is built from stored data, and the pair's own photographs are sent as
 * references so the generated room resembles the business being pitched.
 */
class SalesCoverImageBriefTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.openai.key', 'sk-test-key');
    }

    /**
     * @param  array<string, mixed>  $businessAttributes
     * @param  array<string, mixed>  $communityAttributes
     */
    private function draft(array $businessAttributes = [], array $communityAttributes = []): SalesOutreachDraft
    {
        $business = Profile::factory()->business()->create();
        BusinessProfile::factory()->create([
            'profile_id' => $business->id,
            'name' => 'Eixample 46',
            'city_name' => 'Barcelona',
            'categories' => ['cafe'],
            'primary_venue' => ['venue_type' => 'cafe', 'capacity' => 45, 'photos' => []],
            // Pinned, not left to the factory: it sets a photo 70% of the time,
            // which would make every reference assertion below intermittently wrong.
            'profile_photo' => null,
            'offer_photos' => null,
            ...$businessAttributes,
        ]);

        $community = Profile::factory()->community()->create();
        CommunityProfile::factory()->create([
            'profile_id' => $community->id,
            'name' => 'Barcelona Run Club',
            'community_type' => 'running',
            'profile_photo' => null,
            ...$communityAttributes,
        ]);

        return SalesOutreachDraft::factory()->create([
            'business_profile_id' => $business->id,
            'community_profile_id' => $community->id,
            'expected_attendees' => 30,
            'kolab_ideas' => [[
                'title' => 'Sunday recovery brunch',
                'format' => 'Sunday late morning',
                'business_provides' => 'The terrace and a set brunch menu',
                'community_delivers' => '30 runners straight after the long run',
                'why_it_works' => 'Fills the quietest table turn of the week',
                'cover_image_prompt' => 'Runners arriving at a sunlit terrace',
            ]],
        ]);
    }

    // ── The brief is built from real data ───────────────────────────────

    public function test_the_brief_carries_the_pairs_actual_facts(): void
    {
        $prompt = app(CoverImageBrief::class)->for($this->draft())['prompt'];

        foreach ([
            'cafe',                                    // venue type
            '45',                                      // capacity
            'Barcelona',                               // city
            'running',                                 // community type
            '30',                                      // expected attendees
            'The terrace and a set brunch menu',       // what the business brings
            '30 runners straight after the long run',  // what the community brings
            'Sunday late morning',                     // the event's own format
        ] as $fact) {
            $this->assertStringContainsString($fact, $prompt, "The brief must state: {$fact}");
        }
    }

    /** The model's own line survives as the opening direction, not as the whole brief. */
    public function test_the_creative_direction_is_kept(): void
    {
        $prompt = app(CoverImageBrief::class)->for($this->draft())['prompt'];

        $this->assertStringContainsString('Runners arriving at a sunlit terrace', $prompt);
    }

    /** The picture is shown to both sides, so the brief has to address both. */
    public function test_the_brief_asks_for_something_both_sides_want(): void
    {
        $prompt = app(CoverImageBrief::class)->for($this->draft())['prompt'];

        $this->assertStringContainsString('The business owner must see', $prompt);
        $this->assertStringContainsString('The community organiser must see', $prompt);
    }

    /**
     * Generative models render lettering as convincing gibberish, and a mangled
     * version of a business's own logo in a cold sales email is worse than none.
     */
    public function test_the_brief_forbids_text_and_logos(): void
    {
        $prompt = app(CoverImageBrief::class)->for($this->draft())['prompt'];

        $this->assertStringContainsString('NO text', $prompt);
        $this->assertStringContainsString('NO logos', $prompt);
    }

    // ── References ──────────────────────────────────────────────────────

    public function test_the_pairs_own_photographs_are_used_as_references(): void
    {
        $brief = app(CoverImageBrief::class)->for($this->draft(
            [
                'profile_photo' => 'https://cdn.test/business-logo.png',
                'primary_venue' => ['venue_type' => 'cafe', 'capacity' => 45, 'photos' => ['https://cdn.test/venue.jpg']],
            ],
            ['profile_photo' => 'https://cdn.test/community-logo.png'],
        ));

        // The venue photo leads: it is what decides whether the room looks like theirs.
        $this->assertSame('https://cdn.test/venue.jpg', $brief['references'][0]);
        $this->assertContains('https://cdn.test/business-logo.png', $brief['references']);
        $this->assertContains('https://cdn.test/community-logo.png', $brief['references']);
    }

    /** Each reference costs upload time on an already slow call. */
    public function test_references_are_capped_at_three(): void
    {
        $brief = app(CoverImageBrief::class)->for($this->draft(
            [
                'profile_photo' => 'https://cdn.test/a.png',
                'offer_photos' => ['https://cdn.test/b.png'],
                'primary_venue' => ['venue_type' => 'cafe', 'capacity' => 45, 'photos' => ['https://cdn.test/c.png']],
            ],
            ['profile_photo' => 'https://cdn.test/d.png'],
        ));

        $this->assertCount(3, $brief['references']);
    }

    /** A Places photo resource name is not a URL and cannot be fetched. */
    public function test_non_urls_are_not_offered_as_references(): void
    {
        $brief = app(CoverImageBrief::class)->for($this->draft([
            'primary_venue' => ['venue_type' => 'cafe', 'capacity' => 45, 'photos' => ['places/ChIJx/photos/abc']],
            'profile_photo' => 'https://cdn.test/real.png',
        ]));

        $this->assertSame(['https://cdn.test/real.png'], $brief['references']);
    }

    public function test_a_pair_with_no_photos_still_produces_a_brief(): void
    {
        $brief = app(CoverImageBrief::class)->for($this->draft());

        $this->assertSame([], $brief['references']);
        $this->assertNotSame('', $brief['prompt']);
    }

    // ── Which endpoint gets called ──────────────────────────────────────

    /** With references the call must go to /images/edits, which accepts them. */
    public function test_references_are_sent_to_the_edits_endpoint(): void
    {
        Http::fake([
            '*' => Http::response(['data' => [['b64_json' => base64_encode('png')]]]),
        ]);

        app(OpenAiClient::class)->imageWithReferences('a brief', ['https://cdn.test/ref.png']);

        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/images/edits'));
    }

    public function test_without_references_it_falls_back_to_plain_generation(): void
    {
        Http::fake(['*' => Http::response(['data' => [['b64_json' => base64_encode('png')]]])]);

        app(OpenAiClient::class)->imageWithReferences('a brief', []);

        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/images/generations'));
    }

    /**
     * One slow CDN must not be what makes a pitch fail — a cover grounded in fewer
     * photos is still a cover.
     */
    public function test_an_unreachable_reference_is_skipped_not_fatal(): void
    {
        Http::fake([
            'cdn.test/*' => Http::response('', 404),
            '*' => Http::response(['data' => [['b64_json' => base64_encode('png')]]]),
        ]);

        $result = app(OpenAiClient::class)->imageWithReferences('a brief', ['https://cdn.test/gone.png']);

        $this->assertStringStartsWith('data:image/png;base64,', $result);
        Http::assertSent(fn ($request): bool => str_contains($request->url(), '/images/generations'));
    }
}
