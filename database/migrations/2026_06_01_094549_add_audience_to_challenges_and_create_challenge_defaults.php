<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Audience tag on each challenge — which side may add it to a
        //    collaboration. Backfilled to 'both' so legacy challenges keep
        //    appearing for everyone until a maintainer narrows them.
        Schema::table('challenges', function (Blueprint $table): void {
            $table->string('audience', 20)->default('both')->after('points');
            $table->index('audience');
        });

        // 2. The role-based defaults matrix: which challenges should
        //    auto-seed a new collaboration based on the participants'
        //    business / community type. See ChallengeDefaultsService.
        Schema::create('challenge_defaults', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('challenge_id')->constrained('challenges')->cascadeOnDelete();
            $table->string('applies_to', 20); // 'business_type' | 'community_type'
            $table->string('type_value', 100); // underscored slug
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['challenge_id', 'applies_to', 'type_value'], 'challenge_defaults_unique_idx');
            $table->index(['applies_to', 'type_value']);
        });

        // 3. Slug-format cleanup: community_types was seeded with hyphenated
        //    slugs (run-club) while community_profiles.community_type stores
        //    underscored values (run_club). Reconcile to underscored so the
        //    defaults matrix actually matches live data.
        $hyphenToUnderscore = [
            'run-club' => 'run_club',
            'fitness-community' => 'fitness_community',
            'wellness-community' => 'wellness_community',
            'art-creative-community' => 'art_creative_community',
            'photography-community' => 'photography_community',
            'music-community' => 'music_community',
            'dance-community' => 'dance_community',
            'tech-startup-community' => 'tech_startup_community',
            'book-club' => 'book_club',
            'sustainability-community' => 'sustainability_community',
            'food-community' => 'food_community',
            'travel-community' => 'travel_community',
            'student-community' => 'student_community',
            'professional-networking-community' => 'professional_networking_community',
            'business-coworking' => 'business_coworking',
            'hobby-community' => 'hobby_community',
        ];

        foreach ($hyphenToUnderscore as $from => $to) {
            DB::table('community_types')
                ->where('slug', $from)
                ->update(['slug' => $to]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('challenge_defaults');

        Schema::table('challenges', function (Blueprint $table): void {
            $table->dropIndex(['audience']);
            $table->dropColumn('audience');
        });

        // Note: slug-format change is NOT reversed automatically. If you must
        // roll back the slugs to hyphens, do it manually.
    }
};
