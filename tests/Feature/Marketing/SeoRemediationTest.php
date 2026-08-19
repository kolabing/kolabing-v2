<?php

declare(strict_types=1);

namespace Tests\Feature\Marketing;

use App\Http\Middleware\CanonicalUrl;
use App\Models\BlogPost;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Guards for the SEO audit fixes.
 *
 * Each case pins one finding that was invisible from inside the app: the site
 * rendered perfectly while serving itself twice, linking nowhere, and shipping
 * 24 MB of images. These assertions are what make a regression loud.
 */
class SeoRemediationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_www_folds_onto_the_apex_host(): void
    {
        // www.kolabing.com used to answer 200 with its own self-referencing
        // canonical, so the two hosts competed as separate documents.
        $this->get('http://www.kolabing.com/')
            ->assertStatus(301)
            ->assertRedirect('http://kolabing.com/');

        $this->get('http://www.kolabing.com/pricing')
            ->assertStatus(301)
            ->assertRedirect('http://kolabing.com/pricing');
    }

    public function test_a_query_string_survives_the_canonical_redirect(): void
    {
        // Campaign parameters must not be dropped on the way to the apex host.
        $this->get('http://www.kolabing.com/pricing?utm_source=newsletter')
            ->assertStatus(301)
            ->assertRedirect('http://kolabing.com/pricing?utm_source=newsletter');
    }

    public function test_trailing_slashes_redirect_instead_of_answering(): void
    {
        /*
         * Driven through the middleware rather than $this->get(): Laravel's test
         * client runs the URL through prepareUrlForRequest(), which trims the
         * trailing slash, so the HTTP helper physically cannot express this
         * request. Production can and did — /pricing/ answered 200.
         */
        $middleware = new CanonicalUrl;
        $next = fn (): Response => new Response('reached the controller');

        $redirect = $middleware->handle(Request::create('http://kolabing.com/pricing/'), $next);

        $this->assertSame(301, $redirect->getStatusCode());
        $this->assertSame('http://kolabing.com/pricing', $redirect->headers->get('Location'));

        // The root keeps its slash — the one path where it is canonical.
        $root = $middleware->handle(Request::create('http://kolabing.com/'), $next);
        $this->assertSame(200, $root->getStatusCode());

        // And a clean path is passed straight through.
        $clean = $middleware->handle(Request::create('http://kolabing.com/pricing'), $next);
        $this->assertSame('reached the controller', $clean->getContent());
    }

    public function test_a_post_is_never_redirected_for_canonicalisation(): void
    {
        // Redirecting a POST would drop the body; the form would silently fail.
        $middleware = new CanonicalUrl;
        $response = $middleware->handle(
            Request::create('http://www.kolabing.com/newsletter/', 'POST'),
            fn (): Response => new Response('handled')
        );

        $this->assertSame('handled', $response->getContent());
    }

    public function test_marketing_pages_are_cacheable_by_the_cdn(): void
    {
        // Cloudflare answered every marketing request with BYPASS because Laravel
        // sends `no-cache, private` by default.
        foreach (['/', '/pricing', '/for-businesses', '/sitemap.xml'] as $path) {
            $cacheControl = $this->get('http://kolabing.com'.$path)
                ->assertOk()
                ->headers->get('Cache-Control');

            $this->assertStringContainsString('s-maxage=300', (string) $cacheControl, $path.' is not shared-cacheable');
            $this->assertStringContainsString('stale-while-revalidate', (string) $cacheControl, $path);
        }
    }

    public function test_the_admin_login_is_never_shared_cached(): void
    {
        // A shared cache in front of a login screen would hand one visitor's
        // response to the next.
        $cacheControl = (string) $this->get('http://kolabing.com/admin/login')->headers->get('Cache-Control');

        $this->assertStringNotContainsString('s-maxage', $cacheControl);
    }

    public function test_the_homepage_links_to_the_pages_that_convert(): void
    {
        // The site's strongest URL used to link only to terms, privacy, support
        // and careers — none of the pages that sell anything.
        $response = $this->get('http://kolabing.com/');

        $response->assertOk()
            ->assertSee('href="'.route('for-businesses').'"', false)
            ->assertSee('href="'.route('for-communities').'"', false)
            ->assertSee('href="'.route('pricing').'"', false)
            ->assertSee('href="'.route('blog.index').'"', false)
            ->assertSee('href="'.route('directory.index').'"', false);
    }

    public function test_the_homepage_ships_compressed_images_with_reserved_space(): void
    {
        $response = $this->get('http://kolabing.com/');

        $response->assertOk()
            // 24.58 MB of PNGs became 860 KB of WebP.
            ->assertDontSee('Gemini_Generated_Image', false)
            ->assertDontSee('Screenshot 2026-05-16', false)
            ->assertSee('uploads/kolab-run-club-cafe.webp', false)
            ->assertSee('/brand/kolabing-logo.webp', false);

        // Every image declares its intrinsic size, or the layout shifts as each
        // one arrives.
        preg_match_all('/<img\b[^>]*>/', $response->getContent(), $matches);

        $this->assertNotEmpty($matches[0]);
        foreach ($matches[0] as $tag) {
            $this->assertMatchesRegularExpression('/\bwidth="\d+"/', $tag, 'image without width: '.$tag);
            $this->assertMatchesRegularExpression('/\bheight="\d+"/', $tag, 'image without height: '.$tag);
        }
    }

    public function test_the_compressed_images_exist_and_stay_small(): void
    {
        $budget = 300 * 1024;

        foreach (glob(public_path('uploads/*.webp')) as $path) {
            $this->assertLessThan(
                $budget,
                filesize($path),
                basename($path).' is over the 300 KB budget — re-encode before committing'
            );
        }

        $this->assertFileExists(public_path('brand/kolabing-logo.webp'));
        // Nothing may reintroduce the multi-megabyte originals.
        $this->assertSame([], glob(public_path('uploads/*.png')));
    }

    public function test_an_empty_blog_keeps_itself_out_of_the_index(): void
    {
        // The engine shipped without content: /blog was an indexable 77-word page
        // sitting in the sitemap.
        $this->assertSame(0, BlogPost::query()->count());

        $this->get('http://kolabing.com/blog')
            ->assertOk()
            ->assertSee('noindex,follow', false);

        $this->get('http://kolabing.com/sitemap.xml')
            ->assertOk()
            ->assertDontSee('<loc>'.route('blog.index').'</loc>', false);
    }

    public function test_a_published_post_flips_the_blog_back_on(): void
    {
        BlogPost::factory()->create([
            'title' => 'How a cafe filled a Tuesday',
            'published_at' => now()->subDay(),
        ]);

        $this->get('http://kolabing.com/blog')
            ->assertOk()
            ->assertDontSee('noindex', false);

        $this->get('http://kolabing.com/sitemap.xml')
            ->assertOk()
            ->assertSee('<loc>'.route('blog.index').'</loc>', false);
    }

    public function test_the_directory_hub_waits_for_a_published_city(): void
    {
        // Same rule as the blog: a hub with nothing in it is a thin page.
        $this->get('http://kolabing.com/communities')
            ->assertOk()
            ->assertSee('noindex,follow', false);

        $this->get('http://kolabing.com/sitemap.xml')
            ->assertOk()
            ->assertDontSee('<loc>'.route('directory.index').'</loc>', false);
    }

    public function test_titles_are_descriptive_rather_than_single_words(): void
    {
        $expected = [
            '/blog' => 'Local partnership playbooks',
            '/support' => 'Help with your Kolabing account',
            '/careers' => 'Work at Kolabing',
            '/pricing' => 'Pricing for businesses',
        ];

        foreach ($expected as $path => $title) {
            $this->get('http://kolabing.com'.$path)
                ->assertOk()
                ->assertSee('<title>'.$title.' | Kolabing</title>', false);
        }
    }
}
