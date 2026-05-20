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
            $table->foreignUuid('collaboration_id')
                ->constrained('collaborations')
                ->cascadeOnDelete();
            $table->foreignUuid('reviewer_profile_id')
                ->constrained('profiles')
                ->cascadeOnDelete();
            $table->string('reviewer_role', 20);
            $table->unsignedTinyInteger('rating')->nullable();
            $table->string('note', 200)->nullable();
            $table->timestamps();

            $table->unique(['collaboration_id', 'reviewer_profile_id']);
            $table->index('collaboration_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collaboration_reviews');
    }
};
