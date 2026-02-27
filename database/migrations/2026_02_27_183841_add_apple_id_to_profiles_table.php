<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table): void {
            $table->string('apple_id')->nullable()->unique()->after('google_id');
            $table->index('apple_id');
        });
    }

    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table): void {
            $table->dropIndex(['apple_id']);
            $table->dropUnique(['apple_id']);
            $table->dropColumn('apple_id');
        });
    }
};
