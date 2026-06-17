<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Stable community size captured during community onboarding (approximate
     * member count). Nullable, so existing communities are unaffected.
     */
    public function up(): void
    {
        Schema::table('community_profiles', function (Blueprint $table): void {
            $table->unsignedInteger('community_size')->nullable()->after('community_type');
        });
    }

    public function down(): void
    {
        Schema::table('community_profiles', function (Blueprint $table): void {
            $table->dropColumn('community_size');
        });
    }
};
