<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin CRM accounts — sales/partnership records for businesses, communities
 * and ambassadors. Mostly prospects (not yet app users), with an optional link
 * to a real profile once they onboard. Shared, sortable/filterable columns are
 * first-class; type-specific fields (business fit factors, ambassador
 * contribution counts, community metrics) live in `metrics` JSON. The numeric
 * `score` is computed by CrmScoreService from `metrics` + crm_score_weights.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_accounts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type', 20);                 // business | community | ambassador
            $table->string('name');
            $table->string('status', 30)->nullable();   // Target/Contacted/Interested/Active/Lost (or ambassador states)
            $table->string('owner', 60)->nullable();    // sales owner
            $table->string('email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('instagram_handle', 80)->nullable();
            $table->string('whatsapp', 40)->nullable();
            $table->string('next_action')->nullable();
            $table->text('notes')->nullable();
            $table->integer('score')->default(0);       // computed
            $table->json('metrics')->nullable();        // type-specific fields
            $table->foreignUuid('linked_profile_id')->nullable()
                ->constrained('profiles')->nullOnDelete();
            $table->date('last_activity_at')->nullable();
            $table->timestamps();

            $table->index('type');
            $table->index('status');
            $table->index('owner');
            $table->index(['type', 'score']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_accounts');
    }
};
