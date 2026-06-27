<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lightweight, required completion-confirmation step — decoupled from the
     * rich collaboration_feedback table. Each participant answers a single
     * yes/no/not_yet question; /complete gates on this table instead of
     * feedback rows (PR 1 of the 2026-06-26 completion-flow simplification).
     */
    public function up(): void
    {
        Schema::create('collaboration_completions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('collaboration_id')
                ->constrained('collaborations')
                ->cascadeOnDelete();
            $table->foreignUuid('profile_id')
                ->constrained('profiles')
                ->cascadeOnDelete();

            // Which participant slot the confirmer occupies: 'creator' | 'applicant'.
            $table->string('role', 20);

            // 'yes' | 'no' | 'not_yet'.
            $table->string('status', 20);

            $table->string('note', 500)->nullable();

            $table->timestamps();

            // The composite unique already serves collaboration_id-prefix
            // lookups, so no separate index('collaboration_id') is needed.
            $table->unique(['collaboration_id', 'profile_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collaboration_completions');
    }
};
