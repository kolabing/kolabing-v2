<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Give business & community types a personalised icon, picked from the admin icon
 * library (the same `icons` table offer_options use). `icon_url` stores the public
 * URL of the chosen SVG; the app's CategoryIcon widget renders it with top priority,
 * falling back to the Lucide `icon` name. Mirrors offer_options.icon_url.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_types', function (Blueprint $table): void {
            if (! Schema::hasColumn('business_types', 'icon_url')) {
                $table->string('icon_url', 2048)->nullable()->after('icon');
            }
        });

        Schema::table('community_types', function (Blueprint $table): void {
            if (! Schema::hasColumn('community_types', 'icon_url')) {
                $table->string('icon_url', 2048)->nullable()->after('icon');
            }
        });
    }

    public function down(): void
    {
        Schema::table('business_types', function (Blueprint $table): void {
            $table->dropColumn('icon_url');
        });

        Schema::table('community_types', function (Blueprint $table): void {
            $table->dropColumn('icon_url');
        });
    }
};
