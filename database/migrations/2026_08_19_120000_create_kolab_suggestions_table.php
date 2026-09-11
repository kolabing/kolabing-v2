<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Generated collaboration suggestions: one row per (viewer, counterpart)
     * pair, addressed to one side (`audience`) and refreshed in place by the
     * nightly pass. Carries the score, the per-signal reasons behind it, a
     * proposed event format, the evidence that produced it, and the
     * shown/clicked/dismissed/converted funnel.
     *
     * `signals`, `suggested_format` and `evidence` are documents rather than
     * queryable fields, so Postgres should store them as `jsonb`; on SQLite
     * (the test suite) the same declaration degrades to `text`, which is why
     * nothing may filter or aggregate them in SQL. That divergence is the
     * lesson from the `GET /chats` outage: an aggregate written as
     * `max(uuid)` passed the SQLite suite and then 500'd in production,
     * because Postgres has no `max()` over `uuid`. Read these columns in PHP
     * through the model's array casts only.
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
            $table->date('batch_key'); // The date this pair was last scored.
            $table->timestamp('expires_at');
            $table->timestamp('shown_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->timestamp('dismissed_at')->nullable();
            $table->foreignUuid('converted_kolab_id')->nullable()
                ->constrained('kolabs')
                ->nullOnDelete();
            $table->timestamps();

            // Deliberately NOT keyed on batch_key: one row per pair, refreshed in place by
            // the nightly pass. Including batch_key would write a new row every night while
            // the previous 13 were still inside their 14-day expiry — up to 14 near-identical
            // cards per counterpart.
            $table->unique(
                ['viewer_profile_id', 'counterpart_profile_id'],
                'kolab_suggestions_pair_unique'
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
