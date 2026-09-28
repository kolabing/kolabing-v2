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
            'subscriptions.business.stripe.pro_monthly.price' => 299,
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
            ->assertSee('hreflang="es"', false)
            ->assertSee('hreflang="ca"', false);
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

    /**
     * The model since 25 Sep 2026: listing is free, receiving/accepting applications
     * and running kolabs are free (accepting stopped being paywalled in PR #343), the
     * free listing ends at 3 completed kolabs or 90 days, and paying keeps a business
     * listed. The old page sold "Receive and accept applications" as a paid feature.
     */
    public function test_pricing_page_describes_the_free_listing_model(): void
    {
        $appUrl = rtrim(config('webapp.url'), '/');

        $this->get('http://kolabing.com/pricing')
            ->assertOk()
            ->assertSee('List free. Pay only to stay listed.')
            ->assertSee('€0')
            ->assertSee('Free listing')
            ->assertSee('3 kolabs or for 90 days, whichever comes first')
            ->assertSee('Receive and accept applications from communities')
            ->assertSee('quiet nights')
            ->assertSee('Stay listed in Explore')
            ->assertSee($appUrl.'/register?type=business"', false)
            ->assertDontSee('Publish unlimited Kolabs')
            ->assertDontSee('One plan. Unlimited local partnerships.')
            ->assertDontSee('Community Commerce');
    }

    public function test_pricing_page_offers_venue_pro_for_hotels_on_the_web(): void
    {
        $appUrl = rtrim(config('webapp.url'), '/');

        $this->get('http://kolabing.com/pricing')
            ->assertOk()
            ->assertSee('Venue Pro')
            ->assertSee('€299')
            ->assertSee('For hotels')
            ->assertSee('Billed monthly · web only')
            ->assertSee('Revenue, expected and real attendance for every event')
            ->assertSee($appUrl.'/register?type=business&amp;plan=pro_monthly', false)
            ->assertSee('"price":"299"', false)
            ->assertSee('"price":"0"', false);
    }

    public function test_spanish_pricing_page_describes_the_free_listing_model(): void
    {
        $this->get('http://kolabing.com/es/pricing')
            ->assertOk()
            ->assertSee('Precios para negocios')
            ->assertSee('Listado gratis')
            ->assertSee('horas valle')
            ->assertSee('3 kolabs o 90 días, lo que ocurra primero')
            ->assertSee('Recibe y acepta solicitudes de comunidades')
            ->assertSee('Venue Pro')
            ->assertSee('€299')
            ->assertSee('Ingresos y asistencia prevista y real de cada evento')
            ->assertSee('hreflang="ca"', false)
            ->assertDontSee('horas flojas')
            ->assertDontSee('Publica Kolabs ilimitados');
    }

    public function test_catalan_pricing_page_renders_with_hreflang_alternates(): void
    {
        $this->get('http://kolabing.com/ca/pricing')
            ->assertOk()
            ->assertSee('lang="ca"', false)
            ->assertSee('rel="canonical" href="http://kolabing.com/ca/pricing"', false)
            ->assertSee('hreflang="en" href="http://kolabing.com/pricing"', false)
            ->assertSee('hreflang="es" href="http://kolabing.com/es/pricing"', false)
            ->assertSee('hreflang="ca" href="http://kolabing.com/ca/pricing"', false)
            ->assertSee('hreflang="x-default" href="http://kolabing.com/pricing"', false)
            ->assertSee('Preus per a negocis')
            ->assertSee('Llistat gratuït')
            ->assertSee('hores vall')
            ->assertSee('Les comunitats no paguen mai')
            ->assertSee('€49')
            ->assertSee('€129 cobrats cada 3 mesos')
            ->assertSee('€299')
            ->assertSee('Tot el que inclou Kolabing Business')
            ->assertSee('"inLanguage":"ca"', false)
            ->assertSee('"@type":"FAQPage"', false);
    }

    public function test_pricing_is_discoverable_in_the_sitemap_and_llms_txt(): void
    {
        $this->get('http://kolabing.com/sitemap.xml')
            ->assertOk()
            ->assertSee('http://kolabing.com/pricing')
            ->assertSee('http://kolabing.com/es/pricing')
            ->assertSee('http://kolabing.com/ca/pricing');

        $this->get('http://kolabing.com/llms.txt')
            ->assertOk()
            ->assertSee('http://kolabing.com/pricing');
    }
}
