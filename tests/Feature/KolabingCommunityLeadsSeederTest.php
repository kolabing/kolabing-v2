<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CrmAccount;
use Database\Seeders\KolabingCommunityLeadsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KolabingCommunityLeadsSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_140_communities_with_mapped_fields(): void
    {
        $this->seed(KolabingCommunityLeadsSeeder::class);

        $this->assertSame(
            140,
            CrmAccount::query()->where('type', 'community')
                ->where('metrics->source', 'neil-2026-08-18')->count(),
            '140 community leads should be seeded',
        );

        $volta = CrmAccount::query()->where('type', 'community')
            ->where('name', 'Volta Run Club')->firstOrFail();
        $this->assertSame('@voltarunclub', $volta->instagram_handle);
        $this->assertSame('Madrid', $volta->metrics['city']);
        $this->assertSame('Target', $volta->status);
        $this->assertSame('Neil', $volta->owner);
        $this->assertSame(7176, $volta->metrics['ig_followers']);
        $this->assertStringContainsString('Electrolit', $volta->metrics['collab_businesses']);
    }

    public function test_it_creates_distinct_collab_businesses(): void
    {
        $this->seed(KolabingCommunityLeadsSeeder::class);

        $businesses = CrmAccount::query()->where('type', 'business')
            ->where('metrics->source', 'neil-2026-08-18');
        $this->assertGreaterThan(30, $businesses->count(), 'collab businesses should be extracted');

        // A known multi-collab evidence row should have produced a real business.
        $this->assertTrue(
            CrmAccount::query()->where('type', 'business')
                ->where('name', 'like', 'Nike%')->exists(),
            'Nike should be captured as a collab business',
        );
    }

    public function test_reseeding_is_idempotent(): void
    {
        $this->seed(KolabingCommunityLeadsSeeder::class);
        $firstCommunities = CrmAccount::query()->where('type', 'community')->count();
        $firstBusinesses = CrmAccount::query()->where('type', 'business')->count();

        $this->seed(KolabingCommunityLeadsSeeder::class);

        $this->assertSame($firstCommunities, CrmAccount::query()->where('type', 'community')->count());
        $this->assertSame($firstBusinesses, CrmAccount::query()->where('type', 'business')->count());
    }
}
