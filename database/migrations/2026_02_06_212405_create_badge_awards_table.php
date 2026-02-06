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
        Schema::create('badge_awards', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('badge_id')->constrained('badges')->cascadeOnDelete();
            $table->foreignUuid('profile_id')->constrained('profiles')->cascadeOnDelete();
            $table->timestamp('awarded_at');
            $table->timestamps();

            $table->unique(['badge_id', 'profile_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('badge_awards');
    }
};
