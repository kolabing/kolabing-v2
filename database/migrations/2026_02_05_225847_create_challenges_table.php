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
        Schema::create('challenges', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->string('difficulty', 10);
            $table->unsignedInteger('points');
            $table->boolean('is_system')->default(false);
            $table->foreignUuid('event_id')->nullable()->constrained('events')->cascadeOnDelete();
            $table->timestamps();

            $table->index('is_system');
            $table->index('event_id');
            $table->index('difficulty');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('challenges');
    }
};
