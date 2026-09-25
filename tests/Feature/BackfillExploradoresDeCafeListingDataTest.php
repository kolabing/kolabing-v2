<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\BusinessProfile;
use App\Models\Profile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Verifies the one-off backfill migration
 * (2026_09_15_060500_backfill_exploradores_de_cafe_listing_data.php) does exactly
 * what it claims: fills name/about/business_type/opening_hours ONLY when about and
 * business_type are both still genuinely empty, and never touches a row that
 * already has real content -- the migration runs automatically on every deploy via
 * `php artisan migrate --force`, so this guard is what keeps it safe to leave in
 * the migration history rather than something that only had to be true once.
 *
 * The test suite's own migration run (LazilyRefreshDatabase) already executed this
 * migration once against an empty database (a correct no-op, nothing to update) --
 * this test re-runs the SAME migration class directly, after seeding rows that
 * match the two real scenarios, to verify its actual UPDATE logic rather than just
 * its syntax.
 */
class BackfillExploradoresDeCafeListingDataTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const PROFILE_ID = '01a0a313-293a-737c-ae51-a8302dce8f78';

    private function runMigration(): void
    {
        $migration = include base_path('database/migrations/2026_09_15_060500_backfill_exploradores_de_cafe_listing_data.php');
        $migration->up();
    }

    public function test_it_backfills_a_row_that_is_still_genuinely_empty(): void
    {
        $profile = Profile::factory()->business()->create([
            'id' => self::PROFILE_ID,
            'email' => 'antonio@grupoexploradores.com',
        ]);
        BusinessProfile::factory()->create([
            'profile_id' => $profile->id,
            'name' => 'Exploradores Club de Cafe',
            'about' => null,
            'business_type' => null,
            'opening_hours' => null,
        ]);

        $this->runMigration();

        $business = BusinessProfile::where('profile_id', self::PROFILE_ID)->first();
        $this->assertSame('Exploradores de Café', $business->name);
        $this->assertSame('cafe', $business->business_type);
        $this->assertStringStartsWith('Exploradores de Café nace de la pasión', $business->about);
        $this->assertCount(7, $business->opening_hours);
    }

    public function test_it_does_not_touch_a_row_that_already_has_real_content(): void
    {
        $profile = Profile::factory()->business()->create([
            'id' => self::PROFILE_ID,
            'email' => 'antonio@grupoexploradores.com',
        ]);
        BusinessProfile::factory()->create([
            'profile_id' => $profile->id,
            'name' => 'A Maintainer Already Fixed This By Hand',
            'about' => 'A maintainer already wrote real content here.',
            'business_type' => 'cafe',
            'opening_hours' => null,
        ]);

        $this->runMigration();

        $business = BusinessProfile::where('profile_id', self::PROFILE_ID)->first();
        $this->assertSame('A Maintainer Already Fixed This By Hand', $business->name);
        $this->assertSame('A maintainer already wrote real content here.', $business->about);
    }

    public function test_it_does_not_touch_an_empty_about_if_business_type_is_already_set(): void
    {
        $profile = Profile::factory()->business()->create([
            'id' => self::PROFILE_ID,
            'email' => 'antonio@grupoexploradores.com',
        ]);
        BusinessProfile::factory()->create([
            'profile_id' => $profile->id,
            'name' => 'Exploradores de Café',
            'about' => '',
            'business_type' => 'cafe',
            'opening_hours' => null,
        ]);

        $this->runMigration();

        $business = BusinessProfile::where('profile_id', self::PROFILE_ID)->first();
        // about stays empty rather than being silently overwritten -- once
        // business_type is real, a maintainer is actively working on this row.
        $this->assertSame('', $business->about);
    }

    public function test_it_does_nothing_when_the_profile_does_not_exist(): void
    {
        // Every other environment (local dev, CI, staging) has no row with this
        // specific UUID -- the migration must be a safe no-op there, not an error.
        $this->runMigration();

        $this->assertNull(BusinessProfile::where('profile_id', self::PROFILE_ID)->first());
    }
}
