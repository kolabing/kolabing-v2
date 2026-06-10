<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-admin column visibility for CRM tables (your layout follows you across
 * devices). admin_id is the authenticated admin's id (no FK so it's robust to
 * the admin model); table_key e.g. crm.business / crm.community / crm.ambassador.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_column_prefs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('admin_id');
            $table->string('table_key', 60);
            $table->json('visible_columns'); // ordered list of visible column keys
            $table->timestamps();

            $table->unique(['admin_id', 'table_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_column_prefs');
    }
};
