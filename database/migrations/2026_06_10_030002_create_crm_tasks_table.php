<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin tasks, classified on two axes: area (department) × subarea (segment).
 * area = sales | marketing | dev ; subarea = business | communities |
 * ambassadors | community_members (ambassadors only under sales/marketing —
 * enforced in the request, not the DB). Optionally linked to a crm_account
 * (a "Next action" on an account materialises here).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_tasks', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->foreignUuid('crm_account_id')->nullable()
                ->constrained('crm_accounts')->nullOnDelete();
            $table->string('assignee', 60)->nullable();
            $table->string('area', 20);        // sales | marketing | dev
            $table->string('subarea', 30);     // business | communities | ambassadors | community_members
            $table->date('due_on')->nullable();
            $table->string('status', 20)->default('open'); // open | doing | done
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('area');
            $table->index('subarea');
            $table->index('assignee');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_tasks');
    }
};
