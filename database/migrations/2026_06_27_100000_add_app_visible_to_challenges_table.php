<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Maria's v1 product review (kolabing-v2#49): the full mission catalogue stays
 * in the backend/admin, but only a small, activation-focused set should be
 * visible in the app at launch. `app_visible` is that gate — `GET /me/missions`
 * filters on it; admin screens (defaults matrix, challenge list) do not.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('challenges', function (Blueprint $table): void {
            $table->boolean('app_visible')->default(false)->after('is_system');
        });
    }

    public function down(): void
    {
        Schema::table('challenges', function (Blueprint $table): void {
            $table->dropColumn('app_visible');
        });
    }
};
