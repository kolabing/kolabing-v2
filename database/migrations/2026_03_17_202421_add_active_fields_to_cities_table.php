<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cities', function (Blueprint $table): void {
            $table->boolean('is_active')->default(false)->after('country');
            $table->unsignedSmallInteger('sort_order')->default(0)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('cities', function (Blueprint $table): void {
            $table->dropColumn(['is_active', 'sort_order']);
        });
    }
};
