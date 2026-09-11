<?php

declare(strict_types=1);

use App\Enums\ChallengeProofType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How a challenge is played (kolabing-v2#216).
 *
 * `challenges` said what a challenge was worth and how hard it was, and nothing
 * about how you do it — so the app had one surface for all of them and "Take a
 * selfie together" opened a screen of text.
 *
 * Additive with a default, so every challenge that already exists keeps working
 * and reports itself as text-played, which is what it has always been.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('challenges', function (Blueprint $table): void {
            $table->string('proof_type', 10)
                ->default(ChallengeProofType::Text->value)
                ->after('difficulty');
        });
    }

    public function down(): void
    {
        Schema::table('challenges', function (Blueprint $table): void {
            $table->dropColumn('proof_type');
        });
    }
};
