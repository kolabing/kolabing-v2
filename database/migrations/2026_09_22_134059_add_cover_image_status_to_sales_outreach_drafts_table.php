<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cover generation becomes asynchronous (BE-FX-61).
 *
 * Generating the cover inside the web request produced a Cloudflare 504 in
 * production: image generation alone measured ~25s locally, and the upload to R2
 * comes after it. Cloudflare gives up at 100s and Laravel Cloud has its own ceiling,
 * so the shape was wrong regardless of where exactly the line sits — a request that
 * waits on a third-party image model is a request that will eventually exceed
 * somebody's timeout.
 *
 * Making it a queued job means the draft needs somewhere to say what happened,
 * because the maintainer is no longer watching the response that does the work.
 * Hence a status and, when it fails, the reason — a silent `cover_image_url` that
 * stays null tells nobody whether the job is still running or died twenty minutes ago.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_outreach_drafts', function (Blueprint $table): void {
            // idle | pending | ready | failed
            $table->string('cover_image_status', 16)->default('idle')->after('cover_image_url');
            $table->text('cover_image_error')->nullable()->after('cover_image_status');
        });

        // Existing drafts that already have a cover are 'ready'; the default
        // 'idle' would make them offer "Generate" as though nothing had happened.
        Schema::table('sales_outreach_drafts', function (): void {
            \Illuminate\Support\Facades\DB::table('sales_outreach_drafts')
                ->whereNotNull('cover_image_url')
                ->update(['cover_image_status' => 'ready']);
        });
    }

    public function down(): void
    {
        Schema::table('sales_outreach_drafts', function (Blueprint $table): void {
            $table->dropColumn(['cover_image_status', 'cover_image_error']);
        });
    }
};
