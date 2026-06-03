<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Models\XpEarnRule;
use App\Models\XpLevel;
use Database\Seeders\XpEarnRuleSeeder;
use Database\Seeders\XpLevelSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class GamificationAdminTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(XpEarnRuleSeeder::class);
        $this->seed(XpLevelSeeder::class);
    }

    private function maintainer(): User
    {
        return User::factory()->create(['is_maintainer' => true]);
    }

    public function test_earn_rules_index_renders_for_maintainer(): void
    {
        $this->actingAs($this->maintainer(), 'admin')
            ->get(route('admin.gamification.earn-rules.index'))
            ->assertOk()
            ->assertSee('XP earn rules')
            ->assertSee('collaboration_complete');
    }

    public function test_earn_rules_index_forbidden_for_non_maintainer(): void
    {
        $user = User::factory()->create(['is_maintainer' => false]);

        $this->actingAs($user, 'admin')
            ->get(route('admin.gamification.earn-rules.index'))
            ->assertForbidden();
    }

    public function test_earn_rule_update_changes_points(): void
    {
        $rule = XpEarnRule::query()->where('event_type', 'review_posted')->firstOrFail();

        $this->actingAs($this->maintainer(), 'admin')
            ->put(route('admin.gamification.earn-rules.update', $rule), [
                'points' => 25,
                'label' => 'Post a thoughtful review',
                'is_active' => true,
            ])
            ->assertRedirect(route('admin.gamification.earn-rules.index'));

        $rule->refresh();
        $this->assertSame(25, $rule->points);
        $this->assertSame('Post a thoughtful review', $rule->label);
    }

    public function test_levels_index_renders(): void
    {
        $this->actingAs($this->maintainer(), 'admin')
            ->get(route('admin.gamification.levels.index'))
            ->assertOk()
            ->assertSee('XP levels')
            ->assertSee('New Community');
    }

    public function test_levels_update_changes_title(): void
    {
        $level = XpLevel::query()->where('number', 1)->firstOrFail();

        $this->actingAs($this->maintainer(), 'admin')
            ->put(route('admin.gamification.levels.update', $level), [
                'title' => 'Newcomer',
                'min_xp' => 0,
                'max_xp' => 99,
                'color' => '#AABBCC',
            ])
            ->assertRedirect(route('admin.gamification.levels.index'));

        $this->assertSame('Newcomer', $level->fresh()->title);
    }

    public function test_levels_update_rejects_non_contiguous_band(): void
    {
        $level = XpLevel::query()->where('number', 1)->firstOrFail();

        // 0..50 leaves a gap up to 100 where the next level starts.
        $this->actingAs($this->maintainer(), 'admin')
            ->put(route('admin.gamification.levels.update', $level), [
                'title' => 'Newcomer',
                'min_xp' => 0,
                'max_xp' => 50,
                'color' => '#AABBCC',
            ])
            ->assertRedirect(route('admin.gamification.levels.edit', $level))
            ->assertSessionHasErrors('ladder');
    }

    public function test_levels_update_rejects_invalid_color(): void
    {
        $level = XpLevel::query()->where('number', 1)->firstOrFail();

        $this->actingAs($this->maintainer(), 'admin')
            ->put(route('admin.gamification.levels.update', $level), [
                'title' => 'Newcomer',
                'min_xp' => 0,
                'max_xp' => 99,
                'color' => 'not-a-hex',
            ])
            ->assertSessionHasErrors('color');
    }
}
