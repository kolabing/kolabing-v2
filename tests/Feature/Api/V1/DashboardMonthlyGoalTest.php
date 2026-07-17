<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\CollaborationStatus;
use App\Models\Collaboration;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class DashboardMonthlyGoalTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_monthly_goal_reflects_this_months_completions(): void
    {
        $business = Profile::factory()->business()->create();

        Collaboration::factory()
            ->forCreator($business)
            ->create([
                'status' => CollaborationStatus::Completed,
                'completed_at' => now()->startOfMonth()->addDays(2),
            ]);

        $response = $this->actingAs($business)->getJson('/api/v1/me/dashboard');

        $response->assertOk()
            ->assertJsonPath('data.monthly_goal.completed', 1)
            ->assertJsonPath('data.monthly_goal.goal', 1)
            ->assertJsonPath('data.monthly_goal.met', true);
    }

    public function test_monthly_goal_excludes_last_months_completions_and_is_not_a_broken_streak(): void
    {
        $business = Profile::factory()->business()->create();

        Collaboration::factory()
            ->forCreator($business)
            ->create([
                'status' => CollaborationStatus::Completed,
                'completed_at' => now()->subMonth(),
            ]);

        $response = $this->actingAs($business)->getJson('/api/v1/me/dashboard');

        $response->assertOk()
            ->assertJsonPath('data.monthly_goal.completed', 0)
            ->assertJsonPath('data.monthly_goal.met', false);
    }
}
