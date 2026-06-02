<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gamification_badge_overrides', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('badge_slug', 50)->unique();
            $table->string('name', 120)->nullable();
            $table->string('description', 500)->nullable();
            $table->string('icon', 120)->nullable();
            $table->json('audiences')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gamification_badge_overrides');
    }
};
