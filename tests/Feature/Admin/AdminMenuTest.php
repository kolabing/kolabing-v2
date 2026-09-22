<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The admin sidebar (`config/adminlte.php` → `menu`).
 *
 * Two surfaces shipped without a menu entry and were only reachable by typing the
 * URL or by finding a button on another page — the Full Onboarding form (BE-NF-64)
 * and Sales Mailing (BE-NF-65). Building a page and not linking it is an easy thing
 * to miss in review, because every test can still reach it by route name.
 *
 * So this file checks the menu itself: the entries exist, and every route a menu
 * entry names actually resolves. The second half catches the opposite failure — a
 * renamed or deleted route leaving a sidebar link that 500s on click.
 */
class AdminMenuTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * @return list<array<string, mixed>>
     */
    private function menu(): array
    {
        return array_values(array_filter(
            (array) config('adminlte.menu'),
            static fn (mixed $entry): bool => is_array($entry) && isset($entry['route']),
        ));
    }

    /**
     * @return list<string>
     */
    private function menuRouteNames(): array
    {
        return array_map(static fn (array $entry): string => (string) $entry['route'], $this->menu());
    }

    public function test_full_onboarding_is_in_the_menu(): void
    {
        $this->assertContains(
            'admin.users.onboard',
            $this->menuRouteNames(),
            'The Full Onboarding form has no sidebar entry — it is reachable only by URL.'
        );
    }

    public function test_sales_mailing_is_in_the_menu(): void
    {
        $this->assertContains(
            'admin.sales-mailing.index',
            $this->menuRouteNames(),
            'Sales Mailing has no sidebar entry — it is reachable only by URL.'
        );
    }

    /** A sidebar link to a route that no longer exists is a 500 on click. */
    public function test_every_menu_route_resolves(): void
    {
        $missing = array_values(array_filter(
            $this->menuRouteNames(),
            static fn (string $name): bool => Route::has($name) === false,
        ));

        $this->assertSame([], $missing, 'Menu entries naming routes that do not exist: '.implode(', ', $missing));
    }

    /** Every entry a maintainer can see must actually open. */
    public function test_the_two_new_entries_are_reachable_by_a_maintainer(): void
    {
        $admin = User::factory()->create(['is_maintainer' => true]);

        foreach (['admin.users.onboard', 'admin.sales-mailing.index'] as $name) {
            $this->actingAs($admin, 'admin')->get(route($name))->assertOk();
        }
    }

    /**
     * The config is the source, but the sidebar is what a maintainer actually sees —
     * so assert against the rendered HTML too. A config entry that fails to render
     * (a bad icon class, a route the menu filter hides) would satisfy every check
     * above and still leave the page unreachable, which is the bug this file exists
     * for.
     */
    public function test_the_sidebar_renders_both_labels(): void
    {
        $admin = User::factory()->create(['is_maintainer' => true]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Full Onboarding', false)
            ->assertSee('Sales Mailing', false);
    }

    /** And neither is reachable without the maintainer guard. */
    public function test_the_two_new_entries_are_still_guarded(): void
    {
        foreach (['admin.users.onboard', 'admin.sales-mailing.index'] as $name) {
            $this->get(route($name))->assertRedirect();
        }
    }
}
