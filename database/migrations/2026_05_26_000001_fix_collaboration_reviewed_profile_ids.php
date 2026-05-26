<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('collaboration_reviews')
            ->join('collaborations', 'collaboration_reviews.collaboration_id', '=', 'collaborations.id')
            ->select([
                'collaboration_reviews.id',
                'collaboration_reviews.reviewer_profile_id',
                'collaborations.creator_profile_id',
                'collaborations.applicant_profile_id',
                'collaboration_reviews.reviewed_profile_id',
            ])
            ->orderBy('collaboration_reviews.id')
            ->get()
            ->each(function (object $review): void {
                $reviewedProfileId = $review->reviewer_profile_id === $review->creator_profile_id
                    ? $review->applicant_profile_id
                    : $review->creator_profile_id;

                if ($review->reviewed_profile_id === $reviewedProfileId) {
                    return;
                }

                DB::table('collaboration_reviews')
                    ->where('id', $review->id)
                    ->update(['reviewed_profile_id' => $reviewedProfileId]);
            });
    }

    public function down(): void
    {
        // No-op. This migration corrects bad denormalized data.
    }
};
