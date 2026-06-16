<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The product/service business path (has_venue = false) carries a product_type
     * the app picks from the admin-managed product_type taxonomy (offer_options
     * kind=product_type, /lookup/product-types). Persisted so the free auto-offer
     * (OnboardingService::provisionBusinessAutoOffer) uses the submitted value
     * instead of a hardcoded "other". Unconstrained string (slug is validated at
     * the request layer against the active DB slugs).
     */
    public function up(): void
    {
        Schema::table('business_profiles', function (Blueprint $table): void {
            $table->string('product_type')->nullable()->after('offering');
        });
    }

    public function down(): void
    {
        Schema::table('business_profiles', function (Blueprint $table): void {
            $table->dropColumn('product_type');
        });
    }
};
