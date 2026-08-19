<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Generated collaboration suggestions: one row per (viewer, counterpart)
     * pair per batch, addressed to one side (`audience`). Carries the score,
     * the per-signal reasons behind it, a proposed event format, the evidence
     * that produced it, and the shown/clicked/dismissed/converted funnel.
     *
     * `signals` and `evidence` are write-once, read-only jsonb — never queried
     * or aggregated in SQL (BE-FX-12: the suite runs on SQLite, prod is
     * Postgres, so Postgres-only SQL cannot be caught by CI).
     */
    public function up(): void
    {
        Schema::create('kolab_suggestions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('audience');
            $table->foreignUuid('viewer_profile_id')
                ->constrained('profiles')
                ->cascadeOnDelete();
            $table->foreignUuid('counterpart_profile_id')
                ->constrained('profiles')
                ->cascadeOnDelete();
            $table->foreignUuid('city_id')->nullable()
                ->constrained('cities')
                ->nullOnDelete();
            $table->unsignedSmallInteger('score');
            $table->string('confidence');
            $table->jsonb('signals');
            $table->jsonb('suggested_format');
            $table->jsonb('evidence');
            $table->date('batch_key');
            $table->timestamp('expires_at');
            $table->timestamp('shown_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->timestamp('dismissed_at')->nullable();
            $table->foreignUuid('converted_kolab_id')->nullable()
                ->constrained('kolabs')
                ->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['viewer_profile_id', 'counterpart_profile_id', 'batch_key'],
                'kolab_suggestions_pair_batch_unique'
            );
            $table->index(['viewer_profile_id', 'score'], 'kolab_suggestions_viewer_score_index');
            $table->index(['audience', 'batch_key'], 'kolab_suggestions_audience_batch_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kolab_suggestions');
    }
};
