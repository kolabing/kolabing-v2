<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * BE-FX-79: production's ranking_pages predates two columns that were added to
 * an already-run create migration. The follow-up migration must add them there
 * and do nothing on a database that already has them.
 */
class RankingPagesMissingColumnsMigrationTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function migration(): object
    {
        return require database_path('migrations/2026_09_29_090000_add_missing_columns_to_ranking_pages.php');
    }

    public function test_it_adds_the_columns_production_is_missing(): void
    {
        Schema::table('ranking_pages', function ($table): void {
            $table->dropColumn(['host_venues', 'spotlight_top']);
        });
        $this->assertFalse(Schema::hasColumn('ranking_pages', 'host_venues'));

        $this->migration()->up();

        $this->assertTrue(Schema::hasColumn('ranking_pages', 'host_venues'));
        $this->assertTrue(Schema::hasColumn('ranking_pages', 'spotlight_top'));
    }

    public function test_it_is_a_no_op_when_the_columns_exist(): void
    {
        $this->assertTrue(Schema::hasColumn('ranking_pages', 'host_venues'));

        $this->migration()->up();

        $this->assertTrue(Schema::hasColumn('ranking_pages', 'host_venues'));
        $this->assertTrue(Schema::hasColumn('ranking_pages', 'spotlight_top'));
    }

    public function test_the_ranking_seeder_runs_after_it(): void
    {
        $this->seed(\Database\Seeders\RankingPageSeeder::class);

        $this->assertGreaterThan(0, \App\Models\RankingPage::query()->published()->count());
    }
}
