<?php

declare(strict_types=1);

namespace Tests\Feature\WebApp;

use Tests\TestCase;

class WebappAlpineBindingsTest extends TestCase
{
    /**
     * Every Alpine `:class` binding must be a SINGLE JS expression. A common
     * copy-paste mistake is prefixing it with a bare CSS class, e.g.
     * `:class="kb-on-yellow foo === bar ? '…' : '…'"`, which is invalid JS —
     * Alpine throws an "Expression Error" and the binding silently does nothing
     * (breaking selected-state styling on plan cards, role pills, the create
     * wizard, etc.). Guard against it across the whole web-app view tree.
     */
    public function test_no_alpine_class_binding_starts_with_a_bare_css_class(): void
    {
        $offenders = [];

        $dir = resource_path('views/webapp');
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
        foreach ($iterator as $file) {
            if ($file->isDir() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }
            $contents = file_get_contents($file->getPathname());

            // :class="<hyphenated-css-class> <rest>"  — a leading token that looks
            // like a CSS class (contains a hyphen) followed by a space then more
            // expression is never a valid single JS expression.
            if (preg_match_all('/:class="([a-z][a-z0-9]*-[a-z0-9-]*)\s+\S/i', $contents, $m)) {
                foreach ($m[1] as $token) {
                    $offenders[] = $file->getFilename().": :class starts with the bare class '{$token}'";
                }
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "Alpine `:class` must be a single JS expression, not a leading CSS class:\n".implode("\n", $offenders),
        );
    }
}
