<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\CrmScoreService;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class CommunityFitScoreTest extends TestCase
{
    private function svc(): CrmScoreService
    {
        return new CrmScoreService;
    }

    public function test_a_strong_local_community_scores_well(): void
    {
        $r = $this->svc()->communityFit([
            'audience_count' => 10000, 'confidence' => 'High', 'collab_businesses' => 'Nike, Oakley',
            'last_active_date' => CarbonImmutable::now()->toDateString(), 'locality_confirmed' => true,
        ]);
        $this->assertGreaterThan(50, $r['score']);
        $this->assertArrayHasKey('audience', $r['breakdown']);
    }

    public function test_a_global_brand_is_capped_regardless_of_reach(): void
    {
        $r = $this->svc()->communityFit([
            'audience_count' => 900000, 'confidence' => 'High', 'collab_businesses' => 'adidas',
            'last_active_date' => CarbonImmutable::now()->toDateString(), 'is_global_brand' => true,
        ]);
        $this->assertLessThanOrEqual(20, $r['score'], 'a global brand can never rank top-decile');
    }

    public function test_low_confidence_discounts_the_score(): void
    {
        $base = ['audience_count' => 8000, 'collab_businesses' => 'X', 'last_active_date' => CarbonImmutable::now()->toDateString(), 'locality_confirmed' => true];
        $high = $this->svc()->communityFit($base + ['confidence' => 'High'])['score'];
        $low = $this->svc()->communityFit($base + ['confidence' => 'Low'])['score'];
        $this->assertGreaterThan($low, $high);
    }

    public function test_stale_community_loses_recency_points(): void
    {
        $base = ['audience_count' => 8000, 'collab_businesses' => 'X', 'confidence' => 'High', 'locality_confirmed' => true];
        $fresh = $this->svc()->communityFit($base + ['last_active_date' => CarbonImmutable::now()->toDateString()])['score'];
        $stale = $this->svc()->communityFit($base + ['last_active_date' => CarbonImmutable::now()->subDays(200)->toDateString()])['score'];
        $this->assertGreaterThan($stale, $fresh);
    }
}
