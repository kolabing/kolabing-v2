<?php

declare(strict_types=1);

namespace Tests\Feature\WebApp;

use Tests\TestCase;

class WebAppRoutesTest extends TestCase
{
    private function host(): string
    {
        return config('webapp.host');
    }

    /** The locale path prefix for the default locale (empty). */
    private function base(): string
    {
        return '';
    }

    public function test_login_is_an_overlay_over_the_hero_not_a_standalone_page(): void
    {
        $this->get('http://'.$this->host().'/login')
            ->assertOk()
            ->assertSee('Log in')
            ->assertSee('Welcome back')
            // The sheet, and the hero it sits on top of, are both rendered.
            ->assertSee('kbLoginModal', false)
            ->assertSee('Where businesses &amp; communities grow together', false);
    }

    public function test_the_app_host_has_no_landing_page_of_its_own(): void
    {
        // kolabing.com is where the product is pitched. Anyone arriving on the app
        // host goes straight to signing in; signing up is its own page.
        $host = $this->host();

        $this->get('http://'.$host.'/')->assertRedirect('http://'.$host.'/login');
        $this->get('http://'.$host.'/es')->assertRedirect('http://'.$host.'/es/login');
        $this->get('http://'.$host.'/ca')->assertRedirect('http://'.$host.'/ca/login');

        $this->get('http://'.$host.'/login')
            ->assertOk()
            ->assertSee('kbLoginModal', false)
            ->assertSee('openLogin()', false)
            // Sign-up stays a full page — it is a multi-step flow.
            ->assertSee($this->base().'/register', false);
    }

    public function test_register_page_renders_the_role_picker_and_the_account_step(): void
    {
        $this->get('http://'.$this->host().'/register')
            ->assertOk()
            ->assertSee('Get started')
            ->assertSee("I'm a community")
            ->assertSee('Create your account');
    }

    public function test_subscription_page_renders_on_the_app_host(): void
    {
        // Public shell; auth + data are enforced client-side against /api/v1.
        $this->get('http://'.$this->host().'/subscription')
            ->assertOk()
            ->assertSee('Your plan')
            ->assertSee('KOLABING BUSINESS')
            ->assertSee('noindex', false);
    }

    public function test_subscription_page_shows_both_plans_priced_from_config(): void
    {
        config([
            'subscriptions.business.stripe.monthly.price' => 49,
            'subscriptions.business.stripe.three_months.price' => 129,
        ]);

        $this->get('http://'.$this->host().'/subscription')
            ->assertOk()
            ->assertSee('Monthly')
            ->assertSee('3 months')
            ->assertSee('€49')
            // €129 / 3 = €43 a month, 12% under the monthly plan — both derived.
            ->assertSee('€43')
            ->assertSee('Save 12%')
            ->assertSee('€129 billed every 3 months');
    }

    public function test_checkout_returns_to_the_confirmation_page_not_a_static_thanks(): void
    {
        // The success URL must carry Stripe's session-id placeholder, which is what
        // lets the return page activate the plan without waiting on the webhook.
        $this->get('http://'.$this->host().'/subscription')
            ->assertOk()
            ->assertSee('/subscription/success?session_id={CHECKOUT_SESSION_ID}', false);

        $this->get('http://'.$this->host().'/subscription/success')
            ->assertOk()
            ->assertSee('Confirming your payment')
            ->assertSee('/me/subscription/checkout/confirm', false);

        $this->get('http://'.$this->host().'/es/subscription/success')
            ->assertOk()
            ->assertSee('Confirmando tu pago');
        $this->get('http://'.$this->host().'/ca/subscription/success')->assertOk();
    }

    public function test_paywalled_actions_send_the_reason_to_the_plan_page(): void
    {
        $host = $this->host();

        $this->get('http://'.$host.'/kolabs')
            ->assertOk()
            ->assertSee('/subscription?reason=publish', false)
            ->assertSee('/subscription?reason=accept', false);

        $this->get('http://'.$host.'/kolabs/create')
            ->assertOk()
            ->assertSee('/subscription?reason=publish', false);

        // …and the plan page has copy for each of them.
        $this->get('http://'.$host.'/subscription')
            ->assertOk()
            ->assertSee('Publishing a Kolab needs an active plan', false);
    }

    public function test_every_authed_page_carries_the_failed_payment_alert(): void
    {
        // A declined card silently removes access; the shell must say so and offer
        // the fix. The banner ships with the sidebar, so it cannot be forgotten on
        // a new page.
        foreach (['/dashboard', '/feed', '/kolabs', '/chats', '/subscription', '/account'] as $path) {
            $this->get('http://'.$this->host().$path)
                ->assertOk()
                ->assertSee('We could not charge your card')
                ->assertSee('Update payment method')
                ->assertSee('pastDue', false);
        }
    }

    public function test_post_purchase_welcome_renders(): void
    {
        $this->get('http://'.$this->host().'/welcome')
            ->assertOk()
            ->assertSee('Continue in the Kolabing app');
    }

    public function test_explore_my_kolabs_and_notifications_pages_render(): void
    {
        $host = $this->host();
        $this->get('http://'.$host.'/feed')->assertOk()->assertSee('Explore');
        $this->get('http://'.$host.'/kolabs')->assertOk()->assertSee('My Kolabs');
        $this->get('http://'.$host.'/notifications')->assertOk()->assertSee('Notifications');
    }

    public function test_kolab_create_and_detail_pages_render(): void
    {
        $host = $this->host();
        // "create" must resolve to the wizard, not be captured by the {kolab} route.
        $this->get('http://'.$host.'/kolabs/create')->assertOk()->assertSee('Create a Kolab');
        $this->get('http://'.$host.'/kolabs/some-uuid')->assertOk();
        $this->get('http://'.$host.'/kolabs/some-uuid/edit')->assertOk();
    }

    public function test_account_and_applications_pages_render(): void
    {
        $host = $this->host();
        $this->get('http://'.$host.'/account')->assertOk()->assertSee('Profile');
        // /applications reuses the My Kolabs view, opened on the Requests tab.
        // /applications reuses the My Kolabs view — assert it actually boots on the
        // Requests tab, not merely that the word appears somewhere on the page.
        $this->get('http://'.$host.'/applications')
            ->assertOk()
            ->assertSee("myKolabsPage('requests')", false)
            ->assertSee('<title>Applications | Kolabing</title>', false);

        $this->get('http://'.$host.'/kolabs')
            ->assertOk()
            ->assertSee("myKolabsPage('offers')", false);
    }

    public function test_localized_public_pages_render_with_lang_and_hreflang(): void
    {
        $host = $this->host();

        $this->get('http://'.$host.'/es/login')
            ->assertOk()
            ->assertSee('lang="es"', false)
            ->assertSee('Iniciar sesión')
            ->assertSee('hreflang="ca"', false)
            ->assertSee('hreflang="x-default"', false);

        $this->get('http://'.$host.'/ca/login')
            ->assertOk()
            ->assertSee('lang="ca"', false)
            ->assertSee('Inicia la sessió');

        // The default (en) is served at the root.
        $this->get('http://'.$host.'/login')->assertOk()->assertSee('lang="en"', false)->assertSee('Log in');

        // "create" still resolves under a locale prefix (not swallowed by {kolab}).
        $this->get('http://'.$host.'/es/kolabs/create')->assertOk()->assertSee('Crear un Kolab');

        // Deep authed pages localise too (server-rendered copy).
        $this->get('http://'.$host.'/es/feed')->assertOk()->assertSee('Explorar');
        $this->get('http://'.$host.'/ca/account')->assertOk()->assertSee('Perfil');
        $this->get('http://'.$host.'/es/notifications')->assertOk()->assertSee('Notificaciones');

        // An unsupported locale is not a valid prefix.
        $this->get('http://'.$host.'/de/login')->assertNotFound();
    }

    public function test_login_and_register_always_offer_google_sign_in(): void
    {
        // The option must never silently vanish when GOOGLE_CLIENT_ID_WEB is unset —
        // it degrades to a visible, explained state instead.
        config(['services.google.client_id_web' => null]);

        $this->get('http://'.$this->host().'/login')
            ->assertOk()
            ->assertSee('Continue with Google')
            ->assertSee('Google sign-in is not configured yet', false)
            ->assertSee('googleBtn', false);

        $this->get('http://'.$this->host().'/register')
            ->assertOk()
            ->assertSee('Continue with Google');

        // With a client id configured the live widget mounts instead.
        config(['services.google.client_id_web' => 'test-web-client-id.apps.googleusercontent.com']);

        $this->get('http://'.$this->host().'/login')
            ->assertOk()
            ->assertSee('test-web-client-id.apps.googleusercontent.com', false);
    }

    public function test_web_app_host_csp_allows_alpine_and_google_sign_in(): void
    {
        // Alpine compiles x-* expressions with new Function(); without 'unsafe-eval'
        // every component silently fails to initialise and the app is inert.
        $csp = $this->get('http://'.$this->host().'/login')
            ->assertOk()
            ->headers->get('Content-Security-Policy');

        $this->assertStringContainsString("'unsafe-eval'", $csp);
        $this->assertStringContainsString('https://accounts.google.com', $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
    }

    public function test_marketing_host_keeps_the_strict_csp(): void
    {
        // Only the web app needs the eval relaxation — nothing else uses Alpine.
        $csp = $this->get('http://kolabing.com/')
            ->assertOk()
            ->headers->get('Content-Security-Policy');

        $this->assertStringNotContainsString("'unsafe-eval'", $csp);
        $this->assertStringNotContainsString('accounts.google.com', $csp);
    }

    public function test_web_app_routes_do_not_leak_onto_the_marketing_host(): void
    {
        // /login only exists on the app host — the marketing domain must not expose it.
        $this->get('http://kolabing.com/login')->assertNotFound();
        $this->get('http://kolabing.com/notifications')->assertNotFound();
    }
}
