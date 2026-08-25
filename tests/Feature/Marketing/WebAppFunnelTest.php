<?php

declare(strict_types=1);

namespace Tests\Feature\Marketing;

use App\Models\BlogPost;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * The marketing site (kolabing.com) and the web app (app.kolabing.com) are
 * different hosts, so every CTA is an absolute URL built from config('webapp.url').
 * These tests pin the funnel: no marketing surface should be a dead end, and the
 * role-specific surfaces must carry the ?type= prefill that skips the register
 * form's role-picker step.
 */
class WebAppFunnelTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function app_url(): string
    {
        return rtrim(config('webapp.url'), '/');
    }

    public function test_homepage_nav_links_to_the_web_app_instead_of_a_download_anchor(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('href="'.$this->app_url().'/login"', false);
        $response->assertSee('href="'.$this->app_url().'/register"', false);
        $response->assertSee('get started free', false);
        // The old nav CTA scrolled to #cta and the hamburger opened nothing.
        $response->assertDontSee('href="#cta"', false);
        $response->assertDontSee('class="menu-icon"', false);
    }

    public function test_homepage_hero_replaces_the_dead_store_badges_with_a_web_cta(): void
    {
        $response = $this->get('/');

        $response->assertSee('class="hero-cta-btn"', false);
        $response->assertSee('start free', false);
        $response->assertSee('already on kolabing?', false);
        // Neither app is published, so the placeholder store buttons are gone.
        $response->assertDontSee('Download on the', false);
        $response->assertDontSee('Get it on', false);
        $response->assertDontSee('class="dl-btn"', false);
        $response->assertDontSee('iOS + Android', false);
    }

    public function test_homepage_journey_section_shows_both_the_web_panel_and_the_app(): void
    {
        $response = $this->get('/');

        // The section used to show a phone alone, which sold the mobile app the
        // site cannot yet link to. It now leads with the web panel.
        $response->assertSee('class="browser-frame"', false);
        $response->assertSee('class="browser-url">app.kolabing.com<', false);
        $response->assertSee('class="phone-mini"', false);
        $response->assertSee('<strong>web panel</strong>', false);
        $response->assertSee('<strong>mobile app</strong>', false);
    }

    public function test_homepage_final_cta_deep_links_each_role_to_a_prefilled_register(): void
    {
        $response = $this->get('/');

        $response->assertSee('href="'.$this->app_url().'/register?type=business"', false);
        $response->assertSee('href="'.$this->app_url().'/register?type=community"', false);
    }

    public function test_homepage_carries_the_sticky_mobile_cta_and_a_popup_signup_link(): void
    {
        $response = $this->get('/');

        $response->assertSee('id="kbSticky"', false);
        $response->assertSee('class="kb-sticky__cta"', false);
        $response->assertSee('Create your free account', false);
    }

    public function test_shared_marketing_header_funnels_every_subpage_into_the_web_app(): void
    {
        foreach (['/support', '/careers', '/privacy', '/terms', '/blog'] as $path) {
            $response = $this->get($path);

            $response->assertOk();
            $response->assertSee('href="'.$this->app_url().'/register"', false);
            $response->assertSee('href="'.$this->app_url().'/login"', false);
            $response->assertSee('Get started', false);
        }
    }

    public function test_audience_pages_carry_a_role_prefilled_cta(): void
    {
        $this->get('/for-businesses')
            ->assertOk()
            ->assertSee('href="'.$this->app_url().'/register?type=business"', false)
            ->assertSee('Create your business account', false);

        $this->get('/for-communities')
            ->assertOk()
            ->assertSee('href="'.$this->app_url().'/register?type=community"', false)
            ->assertSee('Create your community account', false);
    }

    public function test_blog_post_ends_with_a_signup_cta(): void
    {
        $post = BlogPost::factory()->create([
            'published_at' => now()->subDay(),
        ]);

        $this->get(route('blog.show', $post))
            ->assertOk()
            ->assertSee('Get started free', false)
            ->assertSee('href="'.$this->app_url().'/register"', false);
    }

    public function test_web_app_url_defaults_to_the_production_app_host(): void
    {
        $this->assertSame('https://app.kolabing.com', config('webapp.url'));
    }
}
