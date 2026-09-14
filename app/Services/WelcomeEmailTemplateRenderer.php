<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AdminWelcomeEmailTemplate;

/**
 * Substitutes {{token}} placeholders into an admin-edited welcome-email template.
 * Deliberately pure string substitution, not Blade::render() on stored content — a
 * maintainer-editable database field is not a place to compile arbitrary Blade/PHP,
 * even though only maintainers can write it. Unknown tokens are left as-is rather
 * than silently dropped, so a typo in an edited template is visible, not swallowed.
 */
class WelcomeEmailTemplateRenderer
{
    /**
     * @param  array<string, string>  $vars
     * @return array{subject: string, intro: string, next_steps: string, footer: string}
     */
    public function render(AdminWelcomeEmailTemplate $template, array $vars): array
    {
        return [
            'subject' => $this->substitute($template->subject, $vars),
            'intro' => $this->substitute($template->intro_markdown, $vars),
            'next_steps' => $this->substitute($template->next_steps_markdown, $vars),
            'footer' => $this->substitute($template->footer_markdown, $vars),
        ];
    }

    /**
     * @param  array<string, string>  $vars
     */
    private function substitute(string $text, array $vars): string
    {
        return preg_replace_callback(
            '/\{\{\s*(\w+)\s*\}\}/',
            fn (array $match): string => $vars[$match[1]] ?? $match[0],
            $text
        );
    }
}
