<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1 of the mission system: extend `challenges` so a system challenge can
 * be a self-tracked mission (trigger + target + repeat + campaign window), and
 * give every challenge a stable slug so seeding is idempotent by slug.
 *
 * All columns are nullable / defaulted so existing peer-verified event
 * challenges keep working untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('challenges', function (Blueprint $table): void {
            $table->string('trigger_action', 60)->nullable()->after('audience');
            $table->unsignedInteger('target_value')->default(1)->after('trigger_action');
            $table->string('repeat_interval', 20)->default('once')->after('target_value');
            $table->timestamp('starts_at')->nullable()->after('repeat_interval');
            $table->timestamp('ends_at')->nullable()->after('starts_at');
            $table->string('slug', 120)->nullable()->unique()->after('id');

            $table->index(['audience', 'is_system']);
            $table->index('trigger_action');
        });
    }

    public function down(): void
    {
        Schema::table('challenges', function (Blueprint $table): void {
            $table->dropIndex(['audience', 'is_system']);
            $table->dropIndex(['trigger_action']);
            $table->dropUnique(['slug']);
            $table->dropColumn([
                'trigger_action',
                'target_value',
                'repeat_interval',
                'starts_at',
                'ends_at',
                'slug',
            ]);
        });
    }
};
