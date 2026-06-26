<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Would you kolab again?" becomes a first-class completion-feedback
     * question. The feedback row is now the source that mirrors into the public
     * collaboration_reviews row (which already has its own
     * would_collaborate_again column), so the two stop asking the user twice.
     *
     * Nullable in the schema for portability + so historical / mirrored rows
     * stay valid; the FormRequest enforces it as required on new submissions.
     */
    public function up(): void
    {
        Schema::table('collaboration_feedback', function (Blueprint $table): void {
            if (! Schema::hasColumn('collaboration_feedback', 'would_collaborate_again')) {
                $table->boolean('would_collaborate_again')->nullable()->after('would_recommend');
            }
        });
    }

    public function down(): void
    {
        Schema::table('collaboration_feedback', function (Blueprint $table): void {
            if (Schema::hasColumn('collaboration_feedback', 'would_collaborate_again')) {
                $table->dropColumn('would_collaborate_again');
            }
        });
    }
};
