<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Authoritative per-action XP awards. Keys exactly match the values of
        // App\Enums\PointEventType (one row per case). The award path in
        // GamificationWalletService reads from here so the displayed "+N XP"
        // can never disagree with the actual ledger write.
        Schema::create('xp_earn_rules', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('event_type', 60)->unique();
            $table->unsignedSmallInteger('points');
            $table->string('label', 120);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'position']);
        });

        // The XP ladder rendered by the app. Contiguous, non-overlapping bands.
        // Exactly one row has max_xp = null (the top, open-ended level).
        Schema::create('xp_levels', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->unsignedTinyInteger('number')->unique();
            $table->string('title', 120);
            $table->unsignedInteger('min_xp');
            $table->unsignedInteger('max_xp')->nullable();
            $table->string('color', 9);
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index('position');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('xp_levels');
        Schema::dropIfExists('xp_earn_rules');
    }
};
