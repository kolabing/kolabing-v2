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
        Schema::table('applications', function (Blueprint $table): void {
            if (Schema::hasColumn('applications', 'collab_opportunity_id')) {
                $table->uuid('collab_opportunity_id')->nullable()->change();
            }

            if (! Schema::hasColumn('applications', 'kolab_id')) {
                $table->foreignUuid('kolab_id')
                    ->nullable()
                    ->after('collab_opportunity_id')
                    ->constrained('kolabs')
                    ->nullOnDelete();
            }
        });

        Schema::table('collaborations', function (Blueprint $table): void {
            if (Schema::hasColumn('collaborations', 'collab_opportunity_id')) {
                $table->uuid('collab_opportunity_id')->nullable()->change();
            }

            if (! Schema::hasColumn('collaborations', 'kolab_id')) {
                $table->foreignUuid('kolab_id')
                    ->nullable()
                    ->after('collab_opportunity_id')
                    ->constrained('kolabs')
                    ->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('collaborations', function (Blueprint $table): void {
            if (Schema::hasColumn('collaborations', 'kolab_id')) {
                $table->dropForeign(['kolab_id']);
                $table->dropColumn('kolab_id');
            }

            if (Schema::hasColumn('collaborations', 'collab_opportunity_id')) {
                $table->uuid('collab_opportunity_id')->nullable(false)->change();
            }
        });

        Schema::table('applications', function (Blueprint $table): void {
            if (Schema::hasColumn('applications', 'kolab_id')) {
                $table->dropForeign(['kolab_id']);
                $table->dropColumn('kolab_id');
            }

            if (Schema::hasColumn('applications', 'collab_opportunity_id')) {
                $table->uuid('collab_opportunity_id')->nullable(false)->change();
            }
        });
    }
};
