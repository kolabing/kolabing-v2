<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\TierAssignmentRule;
use App\Models\Community;
use App\Models\CommunityTier;
use App\Services\CommunityTierService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CommunityTierCrudTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function service(): CommunityTierService
    {
        return app(CommunityTierService::class);
    }

    private function communityWithDefault(): Community
    {
        $community = Community::factory()->create();
        CommunityTier::factory()->defaultTier()->forCommunity($community)->create();

        return $community;
    }

    public function test_create_xp_tier(): void
    {
        $community = $this->communityWithDefault();

        $tier = $this->service()->create($community, [
            'name' => 'Exec',
            'rank' => 3,
            'color' => '#FFD861',
            'assignment_rule' => TierAssignmentRule::XpThreshold->value,
            'threshold' => 500,
        ]);

        $this->assertSame(TierAssignmentRule::XpThreshold, $tier->assignment_rule);
        $this->assertSame(500, $tier->threshold);
        $this->assertFalse($tier->is_default);
    }

    public function test_setting_a_new_default_unsets_the_previous_default(): void
    {
        $community = $this->communityWithDefault();
        $oldDefault = $community->tiers()->where('is_default', true)->first();

        $newDefault = $this->service()->create($community, [
            'name' => 'Pledge',
            'rank' => 2,
            'assignment_rule' => TierAssignmentRule::Manual->value,
            'is_default' => true,
        ]);

        $this->assertSame(
            1,
            $community->tiers()->where('is_default', true)->count()
        );
        $this->assertTrue($newDefault->fresh()->is_default);
        $this->assertFalse($oldDefault->fresh()->is_default);
    }

    public function test_cannot_delete_the_default_tier(): void
    {
        $community = $this->communityWithDefault();
        $default = $community->tiers()->where('is_default', true)->first();

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('cannot_delete_default_tier');

        $this->service()->delete($default);
    }

    public function test_can_delete_a_non_default_tier(): void
    {
        $community = $this->communityWithDefault();
        $tier = CommunityTier::factory()->forCommunity($community)->create(['rank' => 5]);

        $this->service()->delete($tier);

        $this->assertDatabaseMissing('community_tiers', ['id' => $tier->id]);
    }

    public function test_non_manual_rule_requires_threshold(): void
    {
        $community = $this->communityWithDefault();

        $this->expectException(\InvalidArgumentException::class);

        $this->service()->create($community, [
            'name' => 'Active',
            'rank' => 2,
            'assignment_rule' => TierAssignmentRule::Tenure->value,
            'threshold' => null,
        ]);
    }

    public function test_update_can_promote_then_delete_old_default(): void
    {
        $community = $this->communityWithDefault();
        $oldDefault = $community->tiers()->where('is_default', true)->first();
        $other = CommunityTier::factory()->forCommunity($community)->create(['rank' => 2]);

        $this->service()->update($other, ['is_default' => true]);

        $this->assertFalse($oldDefault->fresh()->is_default);
        $this->assertTrue($other->fresh()->is_default);

        // Now the old default is deletable.
        $this->service()->delete($oldDefault);
        $this->assertDatabaseMissing('community_tiers', ['id' => $oldDefault->id]);
    }
}
