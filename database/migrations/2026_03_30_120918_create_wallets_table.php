<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('wallets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('profile_id')->unique();
            $table->foreign('profile_id')->references('id')->on('profiles')->cascadeOnDelete();
            $table->integer('points')->default(0);
            $table->integer('redeemed_points')->default(0);
            $table->boolean('pending_withdrawal')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};
