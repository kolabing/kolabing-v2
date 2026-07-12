<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class LegalPagesTest extends TestCase
{
    public function test_all_legal_pages_render(): void
    {
        foreach (['terms', 'privacy', 'terms.es', 'privacy.es'] as $routeName) {
            $this->get(route($routeName))->assertOk();
        }
    }

    public function test_terms_page_declares_language_and_alternates(): void
    {
        $response = $this->get(route('terms'));

        $response->assertOk()
            ->assertSee('<html lang="en">', false)
            ->assertSee('hreflang="es"', false)
            ->assertSee(route('terms.es'), false);
    }

    public function test_spanish_privacy_page_declares_spanish_locale(): void
    {
        $this->get(route('privacy.es'))
            ->assertOk()
            ->assertSee('<html lang="es">', false);
    }
}
