<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class MarketingSeoTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_homepage_renders_the_preview_v5_landing_page(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('kolab ideas', false);
        $response->assertSee('BUILD ANY KIND OF KOLAB', false);
        $response->assertSee('One community. One business goal.', false);
        $response->assertSee('Community', false);
        $response->assertSee('Business goal', false);
        $response->assertSee('Kolab', false);
        $response->assertSee('what&rsquo;s your business goal?', false);
        $response->assertSee('href="#how-it-works"', false);
        $response->assertSee('href="#faq"', false);
        $response->assertSee('href="#cta"', false);
        $response->assertSee('/brand/kolabing-logo.png', false);
        $response->assertSee('uploads/Screenshot 2026-05-16 at 22.47.19.png', false);
        $response->assertSee('uploads/Gemini_Generated_Image_j3ohygj3ohygj3oh.png', false);
        $response->assertSee('uploads/Gemini_Generated_Image_rfno1grfno1grfno.png', false);
        $response->assertSee('uploads/feedback-skincare.png', false);
        $response->assertSee('uploads/content-dog-walk.png', false);
        $response->assertSee('uploads/loyalty-wine-book.png', false);
    }

    public function test_secure_requests_receive_security_headers(): void
    {
        $response = $this
            ->withServerVariables([
                'HTTPS' => 'on',
                'HTTP_HOST' => 'kolabing.com',
                'HTTP_X_FORWARDED_PROTO' => 'https',
                'SERVER_PORT' => 443,
            ])
            ->get('/');

        $response->assertOk();
        $response->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        $response->assertHeader('Content-Security-Policy');
        $response->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_supporting_marketing_pages_are_crawlable(): void
    {
        foreach ([
            '/for-businesses',
            '/for-communities',
            '/support',
            '/careers',
            '/privacy',
            '/terms',
        ] as $uri) {
            $this->get($uri)->assertOk();
        }
    }

    public function test_sitemap_lists_key_marketing_urls(): void
    {
        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $response->assertSee(url('/'), false);
        $response->assertSee(url('/for-businesses'), false);
        $response->assertSee(url('/privacy'), false);
    }

    public function test_llms_txt_surfaces_core_site_guidance(): void
    {
        $response = $this->get('/llms.txt');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
        $response->assertSee('Kolabing', false);
        $response->assertSee(url('/support'), false);
        $response->assertSee(url('/terms'), false);
    }

    public function test_public_machine_readable_files_exist_with_expected_content(): void
    {
        $robots = base_path('public/robots.txt');
        $llms = base_path('public/llms.txt');
        $security = base_path('public/.well-known/security.txt');

        $this->assertTrue(File::exists($robots));
        $this->assertTrue(File::exists($llms));
        $this->assertTrue(File::exists($security));

        $this->assertStringContainsString('Sitemap: https://kolabing.com/sitemap.xml', File::get($robots));
        $this->assertStringContainsString('Kolabing is a collaboration platform', File::get($llms));
        $this->assertStringContainsString('Contact: mailto:support@kolabing.com', File::get($security));
    }
}
