<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Somewhere to keep the photo two people took (kolabing-v2#216).
 *
 * `challenge_completions` recorded that a challenge happened and what it paid,
 * and nothing of the thing itself. The pair took a selfie and the app had
 * nowhere to put it, so the only trace of the evening was a number.
 *
 * A path on the completion rather than a photos table: there is exactly one
 * photo per completion by design. Two people, one moment, one picture — a
 * gallery would invite a different feature.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('challenge_completions', function (Blueprint $table): void {
            // A URL, not a path: FileUploadService::uploadFromFile() returns an
            // absolute URL and every other photo in this codebase stores that,
            // so this one does too rather than inventing a second convention.
            $table->string('proof_photo_url')->nullable()->after('points_earned');
        });
    }

    public function down(): void
    {
        Schema::table('challenge_completions', function (Blueprint $table): void {
            $table->dropColumn('proof_photo_url');
        });
    }
};
