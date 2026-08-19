<?php

declare(strict_types=1);

namespace Tests\Feature\Marketing;

use App\Models\CommunityProfile;
use App\Models\Profile;
use App\Support\PublicProfileLink;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Every `application/ld+json` block the marketing site emits, checked as JSON.
 *
 * This exists because of a bug that shipped silently: Laravel 12 has an `@context`
 * Blade directive, and Blade compiles directives inside `{!! !!}` expressions. Every
 * page that built its schema inline — the shared layout, the homepage, both pricing
 * pages — emitted compiled PHP where the `@context` key should have been, so Google
 * could not read any of it. Nothing looked broken: the pages rendered, the JSON still
 * parsed, and the only symptom was structured data that silently did not count.
 *
 * The lesson is the assertion below: parse each block and require the vocabulary,
 * rather than trusting that a template "has JSON-LD in it".
 */
class StructuredDataTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Every marketing URL that emits structured data.
     *
     * @return array<string, array{0: string}>
     */
    public static function marketingPages(): array
    {
        return [
            'homepage' => ['/'],
            'pricing' => ['/pricing'],
            'pricing (es)' => ['/es/pricing'],
            'for businesses' => ['/for-businesses'],
            'for communities' => ['/for-communities'],
            'support' => ['/support'],
            'careers' => ['/careers'],
            'privacy' => ['/privacy'],
            'terms' => ['/terms'],
            'blog index' => ['/blog'],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function structuredData(TestResponse $response): array
    {
        preg_match_all(
            '#<script type="application/ld\+json">(.*?)</script>#s',
            $response->getContent(),
            $matches
        );

        return array_map(function (string $raw): array {
            $decoded = json_decode(trim($raw), true);

            $this->assertIsArray($decoded, 'A JSON-LD block did not decode to an object: '.mb_substr(trim($raw), 0, 160));

            return $decoded;
        }, $matches[1]);
    }

    /**
     * @param  array<string, mixed>  $block
     */
    private function assertUsesSchemaVocabulary(array $block, string $where): void
    {
        // The exact failure the @context directive caused: the key becomes compiled
        // PHP, so `@context` is missing while the document still parses.
        $this->assertArrayHasKey('@context', $block, "{$where}: JSON-LD is missing @context (is it built inline in a {!! !!} echo?)");
        $this->assertSame('https://schema.org', $block['@context'], "{$where}: unexpected @context");

        foreach (array_keys($block) as $key) {
            $this->assertStringNotContainsString('<?php', (string) $key, "{$where}: a JSON-LD key contains raw PHP");
        }

        // Either a plain typed node or an @graph of them.
        $this->assertTrue(
            isset($block['@type']) || isset($block['@graph']),
            "{$where}: JSON-LD has neither @type nor @graph"
        );
    }

    #[DataProvider('marketingPages')]
    public function test_marketing_pages_emit_valid_schema_org_json_ld(string $path): void
    {
        $response = $this->get('http://kolabing.com'.$path);
        $response->assertOk();

        $blocks = $this->structuredData($response);

        $this->assertNotEmpty($blocks, "{$path}: no JSON-LD at all — the shared layout should always emit the Organization block");

        foreach ($blocks as $i => $block) {
            $this->assertUsesSchemaVocabulary($block, $path.' block '.$i);
        }
    }

    public function test_the_shared_layout_emits_the_organization_block(): void
    {
        $blocks = $this->structuredData($this->get('http://kolabing.com/support')->assertOk());

        $organization = collect($blocks)->firstWhere('@type', 'Organization');

        $this->assertNotNull($organization, 'The layout no longer emits an Organization block');
        $this->assertSame('Kolabing', $organization['name']);
        $this->assertSame('https://schema.org', $organization['@context']);
    }

    public function test_the_homepage_graph_names_the_organization_and_the_site(): void
    {
        $blocks = $this->structuredData($this->get('http://kolabing.com/')->assertOk());

        $graph = collect($blocks)->first(fn (array $block): bool => isset($block['@graph']));

        $this->assertNotNull($graph, 'The homepage no longer emits an @graph');
        $this->assertSame('https://schema.org', $graph['@context']);

        $types = collect($graph['@graph'])->pluck('@type')->all();
        $this->assertContains('Organization', $types);
        $this->assertContains('WebSite', $types);
    }

    public function test_the_pricing_pages_describe_the_product_and_the_faq(): void
    {
        foreach (['/pricing', '/es/pricing'] as $path) {
            $types = collect($this->structuredData($this->get('http://kolabing.com'.$path)->assertOk()))
                ->pluck('@type')
                ->all();

            $this->assertContains('Product', $types, "{$path} lost its Product schema");
            $this->assertContains('FAQPage', $types, "{$path} lost its FAQPage schema");
        }
    }

    public function test_a_public_profile_page_emits_valid_json_ld_too(): void
    {
        $profile = Profile::factory()->community()->create();
        CommunityProfile::factory()->create(['profile_id' => $profile->id, 'name' => 'Barcelona Runners']);

        $blocks = $this->structuredData(
            $this->get('http://kolabing.com/p/'.PublicProfileLink::slugFor($profile->fresh()))->assertOk()
        );

        foreach ($blocks as $i => $block) {
            $this->assertUsesSchemaVocabulary($block, 'public profile block '.$i);
        }

        $this->assertNotNull(
            collect($blocks)->firstWhere('name', 'Barcelona Runners'),
            'The profile page no longer describes the profile itself'
        );
    }
}
