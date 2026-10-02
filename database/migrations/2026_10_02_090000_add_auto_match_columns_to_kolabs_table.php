<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Auto-kolabs (Daniel 2026-10-02): a maintainer sets up a Kolab between a
     * business and a community that is already matched, so sales can promise
     * "your first Kolab" and deliver it. The flag and the admin id exist for
     * analytics and audit only; they grant and gate nothing in the app.
     */
    public function up(): void
    {
        Schema::table('kolabs', function (Blueprint $table): void {
            $table->boolean('is_auto_matched')->default(false)->after('is_auto_listing');
            $table->foreignId('created_by_admin_id')
                ->nullable()
                ->after('is_auto_matched')
                ->constrained('users')
                ->nullOnDelete();
            $table->index('is_auto_matched');
        });
    }

    public function down(): void
    {
        Schema::table('kolabs', function (Blueprint $table): void {
            $table->dropIndex(['is_auto_matched']);
            $table->dropConstrainedForeignId('created_by_admin_id');
            $table->dropColumn('is_auto_matched');
        });
    }
};
