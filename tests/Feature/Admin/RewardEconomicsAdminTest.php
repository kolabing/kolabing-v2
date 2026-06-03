<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Profile;
use App\Models\RewardEconomics;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Admin\RewardEconomicsService;
use Database\Seeders\RewardEconomicsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class RewardEconomicsAdminTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::forget(RewardEconomicsService::CACHE_KEY);
        $this->seed(RewardEconomicsSeeder::class);
    }

    private function maintainer(): User
    {
        return User::factory()->create(['is_maintainer' => true]);
    }

    public function test_edit_page_renders_for_maintainer(): void
    {
        $this->actingAs($this->maintainer(), 'admin')
            ->get(route('admin.gamification.economics.edit'))
            ->assertOk()
            ->assertSee('Reward economics')
            ->assertSee('Per-point rate')
            ->assertSee('Cost-impact preview');
    }

    public function test_edit_page_forbidden_for_non_maintainer(): void
    {
        $user = User::factory()->create(['is_maintainer' => false]);

        $this->actingAs($user, 'admin')
            ->get(route('admin.gamification.economics.edit'))
            ->assertForbidden();
    }

    public function test_update_changes_per_point_rate(): void
    {
        $this->actingAs($this->maintainer(), 'admin')
            ->put(route('admin.gamification.economics.update'), [
                'referral_goal' => 3,
                'referral_cash_reward_cents' => 7500,
                'euro_cents_per_point' => 25,
                'withdrawal_threshold_cents' => 7500,
                'currency' => 'EUR',
            ])
            ->assertRedirect(route('admin.gamification.economics.edit'));

        $this->assertSame(25, RewardEconomics::query()->firstOrFail()->euro_cents_per_point);
    }

    public function test_update_rejects_invalid_rate(): void
    {
        $this->actingAs($this->maintainer(), 'admin')
            ->put(route('admin.gamification.economics.update'), [
                'referral_goal' => 3,
                'referral_cash_reward_cents' => 7500,
                'euro_cents_per_point' => 0,
                'withdrawal_threshold_cents' => 7500,
                'currency' => 'EUR',
            ])
            ->assertSessionHasErrors('euro_cents_per_point');
    }

    public function test_withdrawal_flow_uses_economics_threshold_and_rate(): void
    {
        // Set rate to €0.25/point + threshold €100 → 400 points required.
        RewardEconomics::query()->first()->update([
            'euro_cents_per_point' => 25,
            'withdrawal_threshold_cents' => 10000,
        ]);
        Cache::forget(RewardEconomicsService::CACHE_KEY);

        $profile = Profile::factory()->community()->create();
        Wallet::factory()->create([
            'profile_id' => $profile->id,
            'points' => 500,
            'redeemed_points' => 0,
            'pending_withdrawal' => false,
        ]);

        $response = $this->actingAs($profile)
            ->postJson(route('api.v1.gamification.withdrawal'), [
                'iban' => 'ES7921000813610123456789',
                'account_holder' => 'Test User',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.points', 400)
            ->assertJsonPath('data.eur_amount', 100);
    }

    public function test_withdrawal_blocked_below_threshold(): void
    {
        $profile = Profile::factory()->community()->create();
        Wallet::factory()->create([
            'profile_id' => $profile->id,
            'points' => 374,
            'redeemed_points' => 0,
            'pending_withdrawal' => false,
        ]);

        $response = $this->actingAs($profile)
            ->postJson(route('api.v1.gamification.withdrawal'), [
                'iban' => 'ES7921000813610123456789',
                'account_holder' => 'Test User',
            ]);

        $response->assertStatus(400)
            ->assertJsonPath('message', 'Insufficient points. Need 375, have 374.');
    }

    public function test_admin_edit_busts_economics_cache(): void
    {
        $service = app(RewardEconomicsService::class);

        $this->assertSame(20, $service->current()->euro_cents_per_point);

        $this->actingAs($this->maintainer(), 'admin')
            ->put(route('admin.gamification.economics.update'), [
                'referral_goal' => 3,
                'referral_cash_reward_cents' => 7500,
                'euro_cents_per_point' => 30,
                'withdrawal_threshold_cents' => 7500,
                'currency' => 'EUR',
            ]);

        $this->assertSame(30, $service->current()->euro_cents_per_point);
    }

    public function test_cost_impact_counts_eligible_wallets(): void
    {
        Wallet::factory()->count(3)->create([
            'points' => 400,
            'redeemed_points' => 0,
        ]);
        Wallet::factory()->count(2)->create([
            'points' => 100,
            'redeemed_points' => 0,
        ]);

        $impact = app(RewardEconomicsService::class)->costImpact(20, 7500);

        $this->assertSame(375, $impact['threshold_points']);
        $this->assertSame(3, $impact['eligible_wallets']);
        // 3 wallets × 375 points × €0.20 = €225.
        $this->assertSame(225.0, $impact['total_payout_euros']);
    }
}
