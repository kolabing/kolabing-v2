<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Upcoming-event fields (NF-CHAT Phase 3). `event_date` stays for the existing
 * past-showcase; `starts_at`/`ends_at` drive the upcoming/past lifecycle.
 * `community_id` already exists (2026_06_03_000004).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->timestamp('starts_at')->nullable()->after('event_date');
            $table->timestamp('ends_at')->nullable()->after('starts_at');
            $table->string('location')->nullable()->after('address');
            $table->unsignedInteger('capacity')->nullable()->after('location'); // null = unlimited
            $table->json('tier_gate')->nullable()->after('capacity'); // null = all members may sign up
            $table->foreignUuid('collaboration_id')->nullable()->after('community_id')
                ->constrained('collaborations')->nullOnDelete();

            $table->index('starts_at');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('collaboration_id');
            $table->dropIndex(['starts_at']);
            $table->dropColumn(['starts_at', 'ends_at', 'location', 'capacity', 'tier_gate']);
        });
    }
};
