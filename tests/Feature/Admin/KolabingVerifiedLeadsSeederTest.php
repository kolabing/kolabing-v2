<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\CrmAccount;
use Database\Seeders\KolabingVerifiedLeadsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KolabingVerifiedLeadsSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_loads_the_verified_communities(): void
    {
        $this->seed(KolabingVerifiedLeadsSeeder::class);

        $count = CrmAccount::query()->where('type', 'community')
            ->where('metrics->source', 'neil-2026-08-18-verified')->count();
        $this->assertGreaterThanOrEqual(140, $count, 'the verified community set should load');

        // Every verified row is classified and carries an audience + confidence in metrics.
        $sample = CrmAccount::query()->where('type', 'community')
            ->where('metrics->source', 'neil-2026-08-18-verified')->first();
        $this->assertSame('local-community', $sample->metrics['classification']);
        $this->assertSame('Target', $sample->status);
        $this->assertArrayHasKey('confidence', $sample->metrics);
    }

    public function test_it_retires_the_first_pass_rows(): void
    {
        // A first-pass (flawed) row that should be removed by the verified seeder.
        CrmAccount::query()->create([
            'type' => 'community',
            'name' => 'adidas Runners Berlin',
            'metrics' => ['source' => 'neil-2026-08-18'],
        ]);

        $this->seed(KolabingVerifiedLeadsSeeder::class);

        $this->assertFalse(
            CrmAccount::query()->where('type', 'community')
                ->where('metrics->source', 'neil-2026-08-18')->exists(),
            'first-pass rows should be retired',
        );
    }

    public function test_reseeding_is_idempotent(): void
    {
        $this->seed(KolabingVerifiedLeadsSeeder::class);
        $first = CrmAccount::query()->where('type', 'community')->count();
        $this->seed(KolabingVerifiedLeadsSeeder::class);
        $this->assertSame($first, CrmAccount::query()->where('type', 'community')->count());
    }
}
