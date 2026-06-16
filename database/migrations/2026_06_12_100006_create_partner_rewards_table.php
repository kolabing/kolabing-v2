<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * GLOBAL partner rewards, redeemable with global XP ("Redeem your XP").
 * Admin-managed in a later phase; read-only endpoint for now. Guarded.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('partner_rewards')) {
            return;
        }

        Schema::create('partner_rewards', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->string('partner');
            $table->text('description')->nullable();
            $table->string('image_url')->nullable();
            $table->unsignedInteger('cost_xp');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_rewards');
    }
};
