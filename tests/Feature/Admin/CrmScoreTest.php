<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\CrmAccount;
use App\Models\CrmScoreWeight;
use App\Services\CrmScoreService;
use Database\Seeders\CrmSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CrmScoreTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_seed_computes_business_fit_scores(): void
    {
        (new CrmSeeder)->run();

        // Zaatar: active+events+community+fit+responsive (no multi-loc) = 90.
        $this->assertSame(90, CrmAccount::query()->where('name', 'Zaatar')->value('score'));
        // Datirs: active+community+fit+responsive = 70.
        $this->assertSame(70, CrmAccount::query()->where('name', 'Datirs')->value('score'));
        // Confirmed event-history prospect = hosts_events only = 20.
        $this->assertSame(20, CrmAccount::query()->where('name', 'Kubik Barcelona')->value('score'));
        // Low potential = 0.
        $this->assertSame(0, CrmAccount::query()->where('name', 'T44')->value('score'));
        // Mojibake cleaned on the way in.
        $this->assertSame('Gràcia', CrmAccount::query()->where('name', 'Kubik Barcelona')->value('metrics')['neighborhood']);

        // Communities — Health Score factors (20/20/15/20/25).
        $this->assertSame(100, CrmAccount::query()->where('name', 'All Barcelona')->value('score'));
        $this->assertSame(65, CrmAccount::query()->where('name', 'BCN Book Club')->value('score')); // attendance+founder+vibes
        $this->assertSame(80, CrmAccount::query()->where('name', 'Cycling Barcelona')->value('score')); // all but engaged_founder
    }

    public function test_ambassador_contribution_score(): void
    {
        $amb = CrmAccount::query()->create([
            'type' => 'ambassador', 'name' => 'Laura A.', 'status' => 'Active',
            'metrics' => [
                'businesses_referred' => 2, 'communities_referred' => 1,
                'kolabs_generated' => 3, 'product_feedback' => 1, 'feature_suggestions' => 2,
            ],
        ]);

        // 2×20 + 1×15 + 3×30 + 1×10 + 2×5 = 165 (uncapped contribution).
        $this->assertSame(165, app(CrmScoreService::class)->recalculate($amb)->score);
    }

    public function test_weights_are_adjustable(): void
    {
        (new CrmSeeder)->run();
        $zaatar = CrmAccount::query()->where('name', 'Zaatar')->first();
        $this->assertSame(90, $zaatar->score);

        // Bump "hosts_events" 20 → 50; Zaatar recomputes (capped at 100).
        CrmScoreWeight::query()->where(['applies_to' => 'business', 'key' => 'hosts_events'])->update(['points' => 50]);
        $this->assertSame(100, app(CrmScoreService::class)->recalculate($zaatar)->score);
    }

    public function test_follow_up_and_trial_candidate_flags(): void
    {
        (new CrmSeeder)->run();
        $datirs = CrmAccount::query()->where('name', 'Datirs')->first();
        $datirs->update(['last_activity_at' => now()->subDays(20)]);

        $this->assertTrue($datirs->fresh()->needsFollowUp());          // 20d ≥ 14
        $this->assertTrue(CrmAccount::query()->where('name', 'Zaatar')->first()->isTrialCandidate()); // Active & 90
    }
}
