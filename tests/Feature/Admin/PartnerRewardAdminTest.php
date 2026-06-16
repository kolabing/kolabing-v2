<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\PartnerReward;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class PartnerRewardAdminTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function maintainer(): User
    {
        return User::factory()->create(['is_maintainer' => true]);
    }

    public function test_index_renders_for_maintainer(): void
    {
        PartnerReward::factory()->create(['title' => 'Free coffee voucher']);

        $this->actingAs($this->maintainer(), 'admin')
            ->get(route('admin.gamification.partner-rewards.index'))
            ->assertOk()
            ->assertSee('Free coffee voucher');
    }

    public function test_index_forbidden_for_non_maintainer(): void
    {
        $this->actingAs(User::factory()->create(['is_maintainer' => false]), 'admin')
            ->get(route('admin.gamification.partner-rewards.index'))
            ->assertForbidden();
    }

    public function test_index_redirects_guest_to_login(): void
    {
        $this->get(route('admin.gamification.partner-rewards.index'))
            ->assertRedirect(route('login'));
    }

    public function test_store_creates_a_partner_reward(): void
    {
        $this->actingAs($this->maintainer(), 'admin')
            ->post(route('admin.gamification.partner-rewards.store'), [
                'title' => 'Gym day pass',
                'partner' => 'FitClub',
                'description' => 'One free day at any FitClub location.',
                'image_url' => 'https://example.com/pass.png',
                'cost_xp' => 1200,
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.gamification.partner-rewards.index'));

        $this->assertDatabaseHas('partner_rewards', [
            'title' => 'Gym day pass',
            'partner' => 'FitClub',
            'cost_xp' => 1200,
            'is_active' => true,
        ]);
    }

    public function test_store_validation_fails_without_required_fields(): void
    {
        $this->actingAs($this->maintainer(), 'admin')
            ->post(route('admin.gamification.partner-rewards.store'), [
                'title' => '',
                'partner' => '',
            ])
            ->assertSessionHasErrors(['title', 'partner', 'cost_xp']);
    }

    public function test_store_forbidden_for_non_maintainer(): void
    {
        $this->actingAs(User::factory()->create(['is_maintainer' => false]), 'admin')
            ->post(route('admin.gamification.partner-rewards.store'), [
                'title' => 'Hidden',
                'partner' => 'Nope',
                'cost_xp' => 100,
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('partner_rewards', ['title' => 'Hidden']);
    }

    public function test_update_changes_fields(): void
    {
        $reward = PartnerReward::factory()->create([
            'title' => 'Old title',
            'cost_xp' => 500,
            'is_active' => true,
        ]);

        $this->actingAs($this->maintainer(), 'admin')
            ->put(route('admin.gamification.partner-rewards.update', $reward), [
                'title' => 'New title',
                'partner' => $reward->partner,
                'description' => $reward->description,
                'cost_xp' => 750,
                'is_active' => null,
            ])
            ->assertRedirect(route('admin.gamification.partner-rewards.index'));

        $reward->refresh();
        $this->assertSame('New title', $reward->title);
        $this->assertSame(750, $reward->cost_xp);
        $this->assertFalse($reward->is_active);
    }

    public function test_destroy_deletes_the_reward(): void
    {
        $reward = PartnerReward::factory()->create();

        $this->actingAs($this->maintainer(), 'admin')
            ->delete(route('admin.gamification.partner-rewards.destroy', $reward))
            ->assertRedirect(route('admin.gamification.partner-rewards.index'));

        $this->assertDatabaseMissing('partner_rewards', ['id' => $reward->id]);
    }
}
