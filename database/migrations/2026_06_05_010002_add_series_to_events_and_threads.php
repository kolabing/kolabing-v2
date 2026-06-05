<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Link occurrences (events) and the optional shared chat to their series.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->foreignUuid('series_id')->nullable()->after('community_id')
                ->constrained('event_series')->nullOnDelete();
            $table->unsignedInteger('occurrence_index')->nullable()->after('series_id');
            $table->index('series_id');
        });

        // chat_mode = 'series' attaches one thread to the whole series.
        Schema::table('chat_threads', function (Blueprint $table): void {
            $table->foreignUuid('series_id')->nullable()->after('event_id')
                ->constrained('event_series')->cascadeOnDelete();
            $table->index('series_id');
        });
    }

    public function down(): void
    {
        Schema::table('chat_threads', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('series_id');
        });

        Schema::table('events', function (Blueprint $table): void {
            $table->dropIndex(['series_id']);
            $table->dropConstrainedForeignId('series_id');
            $table->dropColumn('occurrence_index');
        });
    }
};
