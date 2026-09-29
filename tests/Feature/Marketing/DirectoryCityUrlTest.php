<?php

declare(strict_types=1);

namespace Tests\Feature\Marketing;

use App\Models\RankingPage;
use Database\Seeders\RankingPageSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * BE-FX-80: the hub page is served per city. The sitemap listed hubs by slug
 * (a 404), and a lowercase city URL 404'd because Postgres compares the city
 * case-sensitively.
 */
class DirectoryCityUrlTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RankingPageSeeder::class);
    }

    public function test_the_sitemap_lists_hubs_by_city_and_every_listed_directory_url_answers(): void
    {
        $xml = $this->get('/sitemap.xml')->assertOk()->getContent();

        $this->assertStringContainsString(route('directory.city', 'Barcelona'), $xml);
        $this->assertStringNotContainsString('/communities/best-community-groups-in-barcelona<', $xml);

        preg_match_all('#<loc>([^<]*/communities/[^<]+)</loc>#', (string) $xml, $m);
        $this->assertNotEmpty($m[1]);
        foreach ($m[1] as $url) {
            $this->get(html_entity_decode($url))->assertOk();
        }
    }

    public function test_a_lowercase_city_url_redirects_to_the_canonical_one(): void
    {
        $this->get('/communities/barcelona')
            ->assertStatus(301)
            ->assertRedirect(route('directory.city', 'Barcelona'));

        $topic = RankingPage::query()->published()->where('city', 'Barcelona')->whereNotNull('topic')->firstOrFail();

        $this->get('/communities/barcelona/'.$topic->slug)
            ->assertStatus(301)
            ->assertRedirect(route('directory.topic', ['Barcelona', $topic->slug]));
    }

    public function test_the_canonical_city_url_answers_and_an_unknown_city_is_404(): void
    {
        $this->get('/communities/Barcelona')->assertOk();
        $this->get('/communities/atlantis')->assertNotFound();
    }
}
