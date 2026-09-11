<?php

declare(strict_types=1);

namespace Tests\Feature\WebApp;

use Tests\TestCase;

/**
 * The web app loads @tailwindcss/forms from the Tailwind CDN. That plugin paints
 * an unchecked checkbox a hard-coded `#fff` and a checked one `currentColor`
 * behind a tick that is hard-coded `white` — both written for a light page. In
 * dark theme `--kb-ink` inverts to near-white, so a checkbox carrying `text-ink`
 * became a white tick on a near-white fill: ticking it changed nothing on screen
 * and users read the control as impossible to select. `.kb-checkbox` in
 * layout.blade.php pins both states to theme tokens instead.
 */
class WebappCheckboxContrastTest extends TestCase
{
    private function host(): string
    {
        return config('webapp.host');
    }

    public function test_register_consent_checkbox_is_themed_and_not_tinted_by_currentcolor(): void
    {
        $response = $this->get('http://'.$this->host().'/register')->assertOk();

        // The consent box opts into the themed treatment…
        $response->assertSee('class="kb-checkbox mt-0.5 w-5 h-5 rounded-md focus:ring-0"', false);

        // …and the layout ships the rule that makes the ticked state visible.
        $response->assertSee('input[type="checkbox"].kb-checkbox:checked', false);
        $response->assertSee('background-color: rgb(var(--kb-primary));', false);
    }

    /**
     * A `text-*` utility on a checkbox is the exact shape of the bug above: the
     * plugin reuses `currentColor` as the checked fill under a white tick. Any
     * checkbox that needs a colour must go through `.kb-checkbox`.
     */
    public function test_no_webapp_checkbox_paints_its_checked_fill_with_a_text_colour(): void
    {
        $offenders = [];

        $dir = resource_path('views/webapp');
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
        foreach ($iterator as $file) {
            if ($file->isDir() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $contents = file_get_contents($file->getPathname());

            // Every <input …> tag that declares type="checkbox", however the
            // attributes are ordered.
            if (! preg_match_all('/<input\b[^>]*>/i', $contents, $tags)) {
                continue;
            }

            foreach ($tags[0] as $tag) {
                if (! preg_match('/type="checkbox"/i', $tag)) {
                    continue;
                }
                if (! preg_match('/\bclass="([^"]*)"/i', $tag, $class)) {
                    continue;
                }
                if (str_contains($class[1], 'kb-checkbox')) {
                    continue;
                }
                if (preg_match('/\btext-[a-z]/i', $class[1])) {
                    $offenders[] = $file->getFilename().': '.trim($class[1]);
                }
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "A checkbox's `text-*` colour becomes its checked fill, behind a white tick — "
            ."use `kb-checkbox` instead:\n".implode("\n", $offenders),
        );
    }
}
