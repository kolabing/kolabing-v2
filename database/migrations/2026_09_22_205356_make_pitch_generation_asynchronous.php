<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Writing the pitch moves to a worker too (BE-FX-63).
 *
 * The cover already did (BE-FX-61); the copy did not, and it is the slower half:
 * two `gpt-5.2` calls plus the research. Measured from a laptop that is ~23s, which
 * looked safe — but production runs these calls three to four times slower, which is
 * how it reached Cloudflare's 100s edge timeout.
 *
 * The giveaway was in the data: two drafts a minute apart, both complete, both with
 * copy. The maintainer saw a 504, clicked again, and the server had finished the
 * first one all along. Exactly the shape of the image bug — the work succeeds and
 * only the response is lost — except here it silently duplicates records.
 *
 * A draft therefore has to be able to exist before it has any content, so the three
 * columns that only the model can fill become nullable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_outreach_drafts', function (Blueprint $table): void {
            // pending | ready | failed
            $table->string('generation_status', 16)->default('ready')->after('status');
            $table->text('generation_error')->nullable()->after('generation_status');
        });

        /*
         * Nullable now: a queued draft exists before the model has written anything.
         *
         * Through the schema builder rather than raw SQL — `ALTER COLUMN … DROP NOT
         * NULL` is Postgres-only and SQLite (the test database) rejects it outright.
         * Each column restates its full original definition, because a change() that
         * omits an attribute drops it.
         */
        Schema::table('sales_outreach_drafts', function (Blueprint $table): void {
            $table->jsonb('kolab_ideas')->nullable()->change();
            $table->string('subject', 255)->nullable()->change();
            $table->text('body_markdown')->nullable()->change();
        });

        // Everything that already exists was written synchronously and is complete.
        DB::table('sales_outreach_drafts')->update(['generation_status' => 'ready']);
    }

    public function down(): void
    {
        // Drop the rows that could not exist before this migration, or restoring
        // NOT NULL fails on them.
        DB::table('sales_outreach_drafts')->whereNull('subject')->orWhereNull('kolab_ideas')->delete();

        Schema::table('sales_outreach_drafts', function (Blueprint $table): void {
            $table->jsonb('kolab_ideas')->nullable(false)->change();
            $table->string('subject', 255)->nullable(false)->change();
            $table->text('body_markdown')->nullable(false)->change();
        });

        Schema::table('sales_outreach_drafts', function (Blueprint $table): void {
            $table->dropColumn(['generation_status', 'generation_error']);
        });
    }
};
