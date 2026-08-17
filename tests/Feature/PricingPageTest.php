<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * The public pricing page: the link sales sends, and the only place a prospect can
 * see the price without registering first.
 */
class PricingPageTest extends TestCase
{
    // The sitemap enumerates published blog posts.
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'subscriptions.business.stripe.monthly.price' => 49,
            'subscriptions.business.stripe.three_months.price' => 129,
        ]);
    }

    public function test_pricing_page_shows_both_plans_priced_from_config(): void
    {
        $this->get('http://kolabing.com/pricing')
            ->assertOk()
            ->assertSee('€49')
            ->assertSee('€43')          // €129 / 3 months
            ->assertSee('Save 12%')
            ->assertSee('€129 billed every 3 months');
    }

    public function test_pricing_page_is_indexable_and_declares_its_offers(): void
    {
        $this->get('http://kolabing.com/pricing')
            ->assertOk()
            ->assertSee('index,follow', false)
            ->assertSee('rel="canonical" href="http://kolabing.com/pricing"', false)
            ->assertSee('"@type":"Product"', false)
            ->assertSee('"@type":"Offer"', false)
            ->assertSee('"@type":"FAQPage"', false)
            ->assertSee('hreflang="es"', false);
    }

    public function test_the_cta_hands_off_to_the_app_with_the_chosen_plan(): void
    {
        $appUrl = rtrim(config('webapp.url'), '/');

        $this->get('http://kolabing.com/pricing')
            ->assertOk()
            ->assertSee($appUrl.'/register?type=business&amp;plan=monthly', false)
            ->assertSee($appUrl.'/register?type=business&amp;plan=three_months', false)
            // Communities must never be pushed at a paid plan.
            ->assertSee($appUrl.'/register?type=community', false)
            ->assertSee('Communities never pay');
    }

    public function test_spanish_pricing_page_renders_and_cross_links(): void
    {
        $this->get('http://kolabing.com/es/pricing')
            ->assertOk()
            ->assertSee('lang="es"', false)
            ->assertSee('Precios')
            ->assertSee('Las comunidades nunca pagan')
            ->assertSee('€49')
            ->assertSee('hreflang="en"', false);
    }

    public function test_pricing_is_discoverable_in_the_sitemap_and_llms_txt(): void
    {
        $this->get('http://kolabing.com/sitemap.xml')
            ->assertOk()
            ->assertSee('http://kolabing.com/pricing')
            ->assertSee('http://kolabing.com/es/pricing');

        $this->get('http://kolabing.com/llms.txt')
            ->assertOk()
            ->assertSee('http://kolabing.com/pricing');
    }
}
