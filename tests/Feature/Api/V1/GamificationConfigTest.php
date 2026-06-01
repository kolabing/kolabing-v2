<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\PointEventType;
use App\Models\Profile;
use App\Models\XpEarnRule;
use App\Services\Admin\XpEarnRuleService;
use App\Services\GamificationWalletService;
use Database\Seeders\XpEarnRuleSeeder;
use Database\Seeders\XpLevelSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class GamificationConfigTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::forget(XpEarnRuleService::CACHE_KEY);
        $this->seed(XpEarnRuleSeeder::class);
        $this->seed(XpLevelSeeder::class);
    }

    public function test_config_endpoint_returns_levels_and_earn_rules(): void
    {
        $profile = Profile::factory()->community()->create();

        $response = $this->actingAs($profile)
            ->getJson(route('api.v1.gamification.config'));

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'xp_levels' => [
                        ['number', 'title', 'min_xp', 'max_xp', 'color'],
                    ],
                    'earn_rules' => [
                        ['event_type', 'points', 'label'],
                    ],
                ],
            ]);

        // First level seeded as the 0–99 starter.
        $response->assertJsonPath('data.xp_levels.0.number', 1)
            ->assertJsonPath('data.xp_levels.0.min_xp', 0)
            ->assertJsonPath('data.xp_levels.0.max_xp', 99);
    }

    public function test_config_endpoint_requires_authentication(): void
    {
        $this->getJson(route('api.v1.gamification.config'))
            ->assertUnauthorized();
    }

    public function test_award_path_reads_points_from_earn_rules_table(): void
    {
        // Set CollaborationComplete to a non-default value via the table.
        XpEarnRule::query()
            ->where('event_type', PointEventType::CollaborationComplete->value)
            ->update(['points' => 25]);

        $profile = Profile::factory()->community()->create();

        app(GamificationWalletService::class)->awardPoints(
            $profile->id,
            PointEventType::CollaborationComplete,
            'collab-1',
            'Test',
        );

        // Awarded 25 from rule + 50 default first-kolab bonus = 75.
        $this->assertDatabaseHas('point_ledger', [
            'profile_id' => $profile->id,
            'event_type' => 'collaboration_complete',
            'points' => 25,
        ]);
    }

    public function test_inactive_rule_falls_back_to_enum_default_points(): void
    {
        // Deactivate the row so pointsFor() must use the enum default (10).
        XpEarnRule::query()
            ->where('event_type', PointEventType::ReviewPosted->value)
            ->update(['is_active' => false, 'points' => 999]);

        $profile = Profile::factory()->community()->create();

        app(GamificationWalletService::class)->awardPoints(
            $profile->id,
            PointEventType::ReviewPosted,
            'review-1',
            'Test',
        );

        $this->assertDatabaseHas('point_ledger', [
            'profile_id' => $profile->id,
            'event_type' => 'review_posted',
            'points' => 10,
        ]);
    }

    public function test_admin_edit_busts_config_cache(): void
    {
        $maintainer = \App\Models\User::factory()->create(['is_maintainer' => true]);
        $profile = Profile::factory()->community()->create();

        // Warm the cache via the public endpoint.
        $first = $this->actingAs($profile)
            ->getJson(route('api.v1.gamification.config'))
            ->json('data.earn_rules');

        $reviewPoints = collect($first)->firstWhere('event_type', 'review_posted')['points'];
        $this->assertSame(10, $reviewPoints);

        // Maintainer edits the points in the admin panel.
        $rule = XpEarnRule::query()->where('event_type', 'review_posted')->firstOrFail();
        $this->actingAs($maintainer, 'admin')
            ->put(route('admin.gamification.earn-rules.update', $rule), [
                'points' => 42,
                'label' => $rule->label,
                'is_active' => true,
            ])
            ->assertRedirect(route('admin.gamification.earn-rules.index'));

        // Next config request should see the new value (cache busted).
        $second = $this->actingAs($profile)
            ->getJson(route('api.v1.gamification.config'))
            ->json('data.earn_rules');

        $reviewPointsAfter = collect($second)->firstWhere('event_type', 'review_posted')['points'];
        $this->assertSame(42, $reviewPointsAfter);
    }
}
