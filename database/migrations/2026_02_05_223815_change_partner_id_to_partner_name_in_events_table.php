<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropForeign(['partner_id']);
            $table->dropIndex(['partner_id']);
            $table->dropColumn('partner_id');
            $table->string('partner_name', 100)->after('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('partner_name');
            $table->foreignUuid('partner_id')->after('name')->constrained('profiles')->cascadeOnDelete();
            $table->index('partner_id');
        });
    }
};
