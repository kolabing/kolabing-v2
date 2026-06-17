<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Goal-based onboarding: a business is either a venue ("fill my venue",
     * has_venue = true, the existing primary_venue path) or a product/service
     * business ("promote a product/service", has_venue = false) that has no
     * physical venue but reaches multiple cities and describes an offering.
     */
    public function up(): void
    {
        Schema::table('business_profiles', function (Blueprint $table): void {
            $table->boolean('has_venue')->default(true)->after('business_type');
            $table->json('target_city_ids')->nullable()->after('city_country');
            $table->text('offering')->nullable()->after('about');
            $table->json('offer_photos')->nullable()->after('primary_venue');
        });
    }

    public function down(): void
    {
        Schema::table('business_profiles', function (Blueprint $table): void {
            $table->dropColumn([
                'has_venue',
                'target_city_ids',
                'offering',
                'offer_photos',
            ]);
        });
    }
};
