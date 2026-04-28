<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('business_profiles', function (Blueprint $table): void {
            $table->json('categories')->nullable()->after('business_type');
        });

        DB::table('business_profiles')
            ->whereNotNull('business_type')
            ->whereNull('categories')
            ->select(['id', 'business_type'])
            ->orderBy('id')
            ->get()
            ->each(function (object $businessProfile): void {
                DB::table('business_profiles')
                    ->where('id', $businessProfile->id)
                    ->update([
                        'categories' => json_encode([$businessProfile->business_type]),
                    ]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('business_profiles', function (Blueprint $table): void {
            $table->dropColumn('categories');
        });
    }
};
