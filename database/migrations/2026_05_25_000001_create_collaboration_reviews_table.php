<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collaboration_reviews', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('collaboration_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('reviewer_profile_id')->constrained('profiles')->cascadeOnDelete();
            $table->foreignUuid('reviewed_profile_id')->constrained('profiles')->cascadeOnDelete();
            $table->unsignedTinyInteger('rating'); // 1–5
            $table->text('body')->nullable();
            $table->boolean('would_collaborate_again')->nullable();
            $table->timestamps();

            // One review per reviewer per collaboration
            $table->unique(['collaboration_id', 'reviewer_profile_id']);

            $table->index('reviewed_profile_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collaboration_reviews');
    }
};
