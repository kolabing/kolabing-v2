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
            $table->string('city_name')->nullable()->after('city_id');
            $table->string('city_country')->nullable()->after('city_name');
            $table->json('primary_venue')->nullable()->after('profile_photo');
        });
    }

    public function down(): void
    {
        Schema::table('business_profiles', function (Blueprint $table): void {
            $table->dropColumn([
                'city_name',
                'city_country',
                'primary_venue',
            ]);
        });
    }
};
