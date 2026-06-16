<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tag each business type with the onboarding path(s) it belongs to so the
     * app can filter the category pills by goal:
     * - venue:   physical-venue businesses (restaurant, bar, cafe, ...)
     * - product: product/service businesses with no venue (ecommerce, ...)
     * - both:    types valid on either path (retail, other, ...)
     *
     * The `icon` column already exists on this table (string, nullable); it is
     * left untouched here. The admin icon-gallery UI is a separate later task.
     */
    public function up(): void
    {
        Schema::table('business_types', function (Blueprint $table): void {
            $table->string('applies_to', 16)->default('both')->after('icon');
        });
    }

    public function down(): void
    {
        Schema::table('business_types', function (Blueprint $table): void {
            $table->dropColumn('applies_to');
        });
    }
};
