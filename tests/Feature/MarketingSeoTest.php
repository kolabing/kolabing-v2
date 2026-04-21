<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class MarketingSeoTest extends TestCase
{
    public function test_homepage_exposes_core_seo_metadata(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('rel="canonical"', false);
        $response->assertSee('property="og:title"', false);
        $response->assertSee('name="twitter:card"', false);
        $response->assertSee('application/ld+json', false);
        $response->assertSee('/for-businesses', false);
        $response->assertSee('/for-communities', false);
        $response->assertSee('/privacy', false);
        $response->assertSee('/terms', false);
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
