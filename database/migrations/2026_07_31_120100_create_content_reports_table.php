<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per objectionable-content report. The reporter flags a target
     * (a profile, kolab, review or chat message); the developer is emailed on
     * insert. `status` starts 'open' (triage is out of scope / email-only for
     * now). Part of UGC moderation (App Review Guideline 1.2).
     */
    public function up(): void
    {
        Schema::create('content_reports', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('reporter_profile_id')
                ->constrained('profiles')
                ->cascadeOnDelete();
            $table->string('target_type');
            $table->string('target_id');
            $table->foreignUuid('reported_profile_id')
                ->nullable()
                ->constrained('profiles')
                ->nullOnDelete();
            $table->string('reason');
            $table->text('note')->nullable();
            $table->string('status')->default('open');
            $table->timestamps();

            $table->index(['target_type', 'target_id']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_reports');
    }
};
