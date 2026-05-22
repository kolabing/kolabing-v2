<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The collaboration_feedback table captures the rich, role-specific
     * completion feedback (decision ROLES-AND-PERMISSIONS.md §4). It is distinct
     * from collaboration_reviews, which only stores the public star rating + note.
     *
     * Business reviewers fill: rating, stories_posted, posts_reels, revenue,
     * expectation_match, would_recommend.
     * Community reviewers fill: rating, benefits, posts_reels, expectation_match,
     * would_recommend.
     *
     * Columns not applicable to a reviewer's role are left null.
     */
    public function up(): void
    {
        Schema::create('collaboration_feedback', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('collaboration_id')
                ->constrained('collaborations')
                ->cascadeOnDelete();
            $table->foreignUuid('reviewer_profile_id')
                ->constrained('profiles')
                ->cascadeOnDelete();

            // Which side submitted this feedback row.
            // 'business' or 'community' (the reviewer's user_type).
            $table->string('reviewer_type', 20);
            // Which participant slot the reviewer occupies: 'creator' | 'applicant'.
            $table->string('reviewer_role', 20);

            // Shared field (both roles).
            $table->unsignedTinyInteger('rating'); // 1..5
            $table->unsignedSmallInteger('posts_reels')->nullable();
            $table->boolean('expectation_match');
            $table->boolean('would_recommend');

            // Business-only fields.
            $table->unsignedSmallInteger('stories_posted')->nullable();
            $table->decimal('revenue', 12, 2)->nullable();

            // Community-only fields.
            $table->text('benefits')->nullable();

            $table->timestamps();

            $table->unique(['collaboration_id', 'reviewer_profile_id']);
            $table->index('collaboration_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collaboration_feedback');
    }
};
