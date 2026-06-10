<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Discovery ranking: featured communities surface first on the public
 * `GET /communities/discover` feed. Guarded so it is a no-op where the column
 * already exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('communities', 'is_featured')) {
            Schema::table('communities', function (Blueprint $table): void {
                $table->boolean('is_featured')->default(false)->after('is_primary');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('communities', 'is_featured')) {
            Schema::table('communities', function (Blueprint $table): void {
                $table->dropColumn('is_featured');
            });
        }
    }
};
