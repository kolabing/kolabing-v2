<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\AdminWelcomeEmailTemplate;
use App\Services\WelcomeEmailTemplateRenderer;
use Tests\TestCase;

class WelcomeEmailTemplateRendererTest extends TestCase
{
    public function test_substitutes_known_tokens(): void
    {
        $template = new AdminWelcomeEmailTemplate([
            'subject' => 'Hello {{name}}',
            'intro_markdown' => 'Welcome {{name}}, {{who}} can find you.',
            'next_steps_markdown' => 'Next up, {{name}}.',
            'footer_markdown' => 'Thanks!',
        ]);

        $rendered = (new WelcomeEmailTemplateRenderer)->render($template, [
            'name' => 'Exploradores de Café',
            'who' => 'communities',
        ]);

        $this->assertSame('Hello Exploradores de Café', $rendered['subject']);
        $this->assertSame('Welcome Exploradores de Café, communities can find you.', $rendered['intro']);
        $this->assertSame('Next up, Exploradores de Café.', $rendered['next_steps']);
        $this->assertSame('Thanks!', $rendered['footer']);
    }

    public function test_leaves_an_unknown_token_untouched_rather_than_dropping_it(): void
    {
        $template = new AdminWelcomeEmailTemplate([
            'subject' => 'Hello {{name}}, {{typo_field}}',
            'intro_markdown' => '',
            'next_steps_markdown' => '',
            'footer_markdown' => '',
        ]);

        $rendered = (new WelcomeEmailTemplateRenderer)->render($template, ['name' => 'Test']);

        $this->assertSame('Hello Test, {{typo_field}}', $rendered['subject']);
    }
}
