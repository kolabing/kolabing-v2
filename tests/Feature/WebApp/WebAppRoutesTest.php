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

    public function test_web_app_routes_do_not_leak_onto_the_marketing_host(): void
    {
        // /login only exists on the app host — the marketing domain must not expose it.
        $this->get('http://kolabing.com/login')->assertNotFound();
    }
}
