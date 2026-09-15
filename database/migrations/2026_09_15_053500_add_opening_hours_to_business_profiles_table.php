<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_profiles', function (Blueprint $table): void {
            $table->json('opening_hours')->nullable()->after('primary_venue');
        });
    }

    public function down(): void
    {
        Schema::table('business_profiles', function (Blueprint $table): void {
            $table->dropColumn('opening_hours');
        });
    }
};
