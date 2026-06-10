<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fix: admin_column_prefs.admin_id was created as uuid, but the admin (User)
 * has a bigint id — on Postgres `where admin_id = <int>` throws
 * "invalid input syntax for type uuid", 500ing /admin/crm. The table is empty
 * (saving a pref would have errored too), so drop + recreate with the right
 * type. uuid→bigint isn't a clean ALTER on Postgres, hence drop/recreate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('admin_column_prefs');

        Schema::create('admin_column_prefs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('admin_id');
            $table->string('table_key', 60);
            $table->json('visible_columns');
            $table->timestamps();

            $table->unique(['admin_id', 'table_key']);
        });
    }

    public function down(): void
    {
        // No-op (the table is recreated by this migration's up()).
    }
};
