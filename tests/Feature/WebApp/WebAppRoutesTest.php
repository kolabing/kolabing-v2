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

    public function test_login_page_renders_on_the_app_host(): void
    {
        $this->get('http://'.$this->host().'/login')
            ->assertOk()
            ->assertSee('Log in')
            ->assertSee('noindex', false);
    }

    public function test_register_page_renders_on_the_app_host(): void
    {
        $this->get('http://'.$this->host().'/register')
            ->assertOk()
            ->assertSee('Create your account');
    }

    public function test_subscription_page_renders_on_the_app_host(): void
    {
        // Public shell; auth + data are enforced client-side against /api/v1.
        $this->get('http://'.$this->host().'/subscription')
            ->assertOk()
            ->assertSee('Subscription');
    }

    public function test_app_root_and_welcome_render(): void
    {
        $this->get('http://'.$this->host().'/')->assertOk();
        $this->get('http://'.$this->host().'/welcome')->assertOk()->assertSee('Continue in the Kolabing app');
    }

    public function test_feed_and_my_kolabs_pages_render(): void
    {
        $host = $this->host();
        $this->get('http://'.$host.'/feed')->assertOk()->assertSee('Discover Kolabs');
        $this->get('http://'.$host.'/kolabs')->assertOk()->assertSee('Your Kolabs');
    }

    public function test_kolab_create_and_detail_pages_render(): void
    {
        $host = $this->host();
        // "create" must resolve to the form, not be captured by the {kolab} route.
        $this->get('http://'.$host.'/kolabs/create')->assertOk()->assertSee('Create a Kolab');
        $this->get('http://'.$host.'/kolabs/some-uuid')->assertOk();
        $this->get('http://'.$host.'/kolabs/some-uuid/edit')->assertOk();
    }

    public function test_account_and_applications_pages_render(): void
    {
        $host = $this->host();
        $this->get('http://'.$host.'/account')->assertOk()->assertSee('Your profile');
        $this->get('http://'.$host.'/applications')->assertOk()->assertSee('Applications');
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
        $this->get('http://'.$host.'/es/kolabs/create')->assertOk();

        // Deep authed pages localise too (server-rendered copy).
        $this->get('http://'.$host.'/es/feed')->assertOk()->assertSee('Descubre Kolabs');
        $this->get('http://'.$host.'/ca/account')->assertOk()->assertSee('El teu perfil');

        // An unsupported locale is not a valid prefix.
        $this->get('http://'.$host.'/de/login')->assertNotFound();
    }

    public function test_web_app_routes_do_not_leak_onto_the_marketing_host(): void
    {
        // /login only exists on the app host — the marketing domain must not expose it.
        $this->get('http://kolabing.com/login')->assertNotFound();
    }
}
