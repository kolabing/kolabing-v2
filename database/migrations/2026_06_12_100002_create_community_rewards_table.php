<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-community redeemable rewards. `cost_points` is spent from the member's
 * per-community points balance; `stock` null = unlimited. Guarded.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('community_rewards')) {
            return;
        }

        Schema::create('community_rewards', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('community_id')->constrained('communities')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedInteger('cost_points');
            $table->unsignedInteger('stock')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('community_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_rewards');
    }
};
