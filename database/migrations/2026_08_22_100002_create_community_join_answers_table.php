<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What an applicant answered, attached to their join request
 * (kolabing-app#138).
 *
 * Hangs off `community_join_requests`, which already exists and already carries
 * the pending/approved/declined lifecycle — this adds the substance a leader
 * decides on, without touching that table's shape.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_join_answers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('join_request_id')
                ->constrained('community_join_requests')
                ->cascadeOnDelete();
            $table->foreignUuid('question_id')
                ->constrained('community_join_questions')
                ->cascadeOnDelete();
            $table->text('answer');
            $table->timestamps();

            // One answer per question per application.
            $table->unique(['join_request_id', 'question_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_join_answers');
    }
};
