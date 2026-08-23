<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\BusinessVisibilityBoostSetting;
use App\Models\User;
use App\Services\Admin\BusinessVisibilityBoostService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class BusinessVisibilityBoostAdminTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::forget(BusinessVisibilityBoostService::CACHE_KEY);
    }

    private function maintainer(): User
    {
        return User::factory()->create(['is_maintainer' => true]);
    }

    public function test_edit_page_renders_for_maintainer_with_config_defaults_when_no_row_exists(): void
    {
        $this->actingAs($this->maintainer(), 'admin')
            ->get(route('admin.gamification.business-visibility-boost.edit'))
            ->assertOk()
            ->assertSee('Business visibility boost')
            ->assertSee('Trusted Partner boost')
            ->assertSee('Community Favourite boost');
    }

    public function test_edit_page_forbidden_for_non_maintainer(): void
    {
        $user = User::factory()->create(['is_maintainer' => false]);

        $this->actingAs($user, 'admin')
            ->get(route('admin.gamification.business-visibility-boost.edit'))
            ->assertForbidden();
    }

    public function test_update_changes_boost_points(): void
    {
        $this->actingAs($this->maintainer(), 'admin')
            ->put(route('admin.gamification.business-visibility-boost.update'), [
                'trusted_partner_points' => 8,
                'community_favourite_points' => 15,
            ])
            ->assertRedirect(route('admin.gamification.business-visibility-boost.edit'));

        $row = BusinessVisibilityBoostSetting::query()->firstOrFail();
        $this->assertSame(8, $row->trusted_partner_points);
        $this->assertSame(15, $row->community_favourite_points);
    }

    public function test_update_rejects_favourite_boost_smaller_than_trusted_boost(): void
    {
        $this->actingAs($this->maintainer(), 'admin')
            ->put(route('admin.gamification.business-visibility-boost.update'), [
                'trusted_partner_points' => 20,
                'community_favourite_points' => 5,
            ])
            ->assertSessionHasErrors('community_favourite_points');
    }

    public function test_update_rejects_out_of_range_points(): void
    {
        $this->actingAs($this->maintainer(), 'admin')
            ->put(route('admin.gamification.business-visibility-boost.update'), [
                'trusted_partner_points' => 999,
                'community_favourite_points' => 999,
            ])
            ->assertSessionHasErrors('trusted_partner_points');
    }

    public function test_admin_edit_busts_the_cache(): void
    {
        $service = app(BusinessVisibilityBoostService::class);

        $this->assertSame(5, $service->current()->trusted_partner_points);

        $this->actingAs($this->maintainer(), 'admin')
            ->put(route('admin.gamification.business-visibility-boost.update'), [
                'trusted_partner_points' => 12,
                'community_favourite_points' => 20,
            ]);

        $this->assertSame(12, $service->current()->trusted_partner_points);
    }

    public function test_points_for_tier_reads_the_persisted_row(): void
    {
        BusinessVisibilityBoostSetting::factory()->create([
            'trusted_partner_points' => 7,
            'community_favourite_points' => 14,
        ]);

        $service = app(BusinessVisibilityBoostService::class);

        $this->assertSame(7, $service->pointsForTier('trusted_partner'));
        $this->assertSame(14, $service->pointsForTier('community_favourite'));
        $this->assertSame(0, $service->pointsForTier('new_partner'));
    }
}
