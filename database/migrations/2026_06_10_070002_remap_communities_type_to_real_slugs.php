<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Unify communities.type onto the REAL 17-slug community-type vocabulary
 * (CommunityOnboardingRequest::COMMUNITY_TYPES) — the same list communities pick
 * at sign-up and GET /lookup/community-types serves. The legacy 5-value
 * App\Enums\CommunityType {greek,fitness,running,business,other} was a
 * placeholder and is retired as the source of truth for this column.
 *
 * Guarded + idempotent:
 *  - widen the column to 100 chars (the longest slug,
 *    professional_networking_community, is 33 chars; 20 was too small) on
 *    drivers that enforce length (Postgres). SQLite ignores VARCHAR length.
 *  - remap any rows still holding a legacy 5-value to its 17-slug equivalent.
 *    Rows already on a 17-slug (or any unknown value) are normalised to a valid
 *    slug, defaulting to 'other'. Running twice is a no-op.
 */
return new class extends Migration
{
    /**
     * Legacy 5-value placeholder => real 17-slug mapping.
     *
     * @var array<string, string>
     */
    private const MAP = [
        'fitness' => 'fitness_community',
        'running' => 'run_club',
        'business' => 'business_coworking',
        'greek' => 'student_community',
        'other' => 'other',
    ];

    /**
     * The real 17-slug vocabulary (kept in sync with
     * CommunityOnboardingRequest::COMMUNITY_TYPES). Any value not in this list
     * is collapsed to 'other'.
     *
     * @var array<int, string>
     */
    private const SLUGS = [
        'run_club',
        'fitness_community',
        'wellness_community',
        'art_creative_community',
        'photography_community',
        'music_community',
        'dance_community',
        'tech_startup_community',
        'book_club',
        'sustainability_community',
        'food_community',
        'travel_community',
        'student_community',
        'professional_networking_community',
        'business_coworking',
        'hobby_community',
        'other',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('communities') || ! Schema::hasColumn('communities', 'type')) {
            return;
        }

        // Widen the column on length-enforcing drivers so 17-slug values fit.
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE communities ALTER COLUMN type TYPE VARCHAR(100)');
        }

        // Remap legacy 5-values to their 17-slug equivalents.
        foreach (self::MAP as $legacy => $slug) {
            DB::table('communities')->where('type', $legacy)->update(['type' => $slug]);
        }

        // Normalise anything still outside the real vocabulary to 'other'.
        DB::table('communities')
            ->whereNotIn('type', self::SLUGS)
            ->update(['type' => 'other']);
    }

    public function down(): void
    {
        // One-way data normalisation; no destructive rollback. The column stays
        // widened (harmless) and slugs are not reverted to the retired 5-values.
    }
};
