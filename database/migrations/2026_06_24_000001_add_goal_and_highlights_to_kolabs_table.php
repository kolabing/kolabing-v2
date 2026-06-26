<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kolabs', function (Blueprint $table): void {
            $table->string('goal', 50)->nullable()->after('description');
            $table->json('highlights')->nullable()->after('past_events');
        });
    }

    public function down(): void
    {
        Schema::table('kolabs', function (Blueprint $table): void {
            $table->dropColumn(['goal', 'highlights']);
        });
    }
};
