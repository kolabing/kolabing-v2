<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('collaboration_reviews', function (Blueprint $table): void {
            if (! Schema::hasColumn('collaboration_reviews', 'reviewed_profile_id')) {
                $table->foreignUuid('reviewed_profile_id')
                    ->nullable()
                    ->after('reviewer_profile_id')
                    ->constrained('profiles')
                    ->nullOnDelete();
                $table->index('reviewed_profile_id');
            }

            if (! Schema::hasColumn('collaboration_reviews', 'body')) {
                $table->text('body')->nullable()->after('note');
            }

            if (! Schema::hasColumn('collaboration_reviews', 'would_collaborate_again')) {
                $table->boolean('would_collaborate_again')->nullable()->after('body');
            }
        });

        DB::table('collaboration_reviews')
            ->join('collaborations', 'collaboration_reviews.collaboration_id', '=', 'collaborations.id')
            ->select([
                'collaboration_reviews.id',
                'collaboration_reviews.reviewer_profile_id',
                'collaboration_reviews.note',
                'collaboration_reviews.body',
                'collaborations.creator_profile_id',
                'collaborations.applicant_profile_id',
            ])
            ->whereNull('collaboration_reviews.reviewed_profile_id')
            ->orderBy('collaboration_reviews.id')
            ->get()
            ->each(function (object $review): void {
                $reviewedProfileId = $review->reviewer_profile_id === $review->creator_profile_id
                    ? $review->applicant_profile_id
                    : $review->creator_profile_id;

                DB::table('collaboration_reviews')
                    ->where('id', $review->id)
                    ->update([
                        'reviewed_profile_id' => $reviewedProfileId,
                        'body' => $review->body ?? $review->note,
                    ]);
            });
    }

    public function down(): void
    {
        Schema::table('collaboration_reviews', function (Blueprint $table): void {
            if (Schema::hasColumn('collaboration_reviews', 'would_collaborate_again')) {
                $table->dropColumn('would_collaborate_again');
            }

            if (Schema::hasColumn('collaboration_reviews', 'body')) {
                $table->dropColumn('body');
            }

            if (Schema::hasColumn('collaboration_reviews', 'reviewed_profile_id')) {
                $table->dropConstrainedForeignId('reviewed_profile_id');
            }
        });
    }
};
