<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('collab_opportunities', function (Blueprint $table): void {
            $table->time('selected_time')->nullable()->after('availability_end');
            $table->json('recurring_days')->nullable()->after('selected_time');
        });
    }

    public function down(): void
    {
        Schema::table('collab_opportunities', function (Blueprint $table): void {
            $table->dropColumn(['selected_time', 'recurring_days']);
        });
    }
};
