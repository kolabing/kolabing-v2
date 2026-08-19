<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * CRM activity log — the per-account audit trail behind the pipeline: stage
 * moves, logged notes, first-touch sends, contact events. Rendered as the
 * timeline on the account detail view; every pipeline action writes one row so
 * the lead's history is reconstructable. Distinct from crm_tasks (open work
 * items); an activity is an immutable event that already happened.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_activities', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('crm_account_id')->constrained('crm_accounts')->cascadeOnDelete();
            $table->string('type', 20);          // note | stage_change | first_touch | contact
            $table->string('actor', 60)->nullable();   // admin who performed it
            $table->text('body');                // human-readable line
            $table->json('meta')->nullable();    // e.g. {from: 'Target', to: 'Contacted'}
            $table->timestamps();

            $table->index(['crm_account_id', 'created_at']);
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_activities');
    }
};
