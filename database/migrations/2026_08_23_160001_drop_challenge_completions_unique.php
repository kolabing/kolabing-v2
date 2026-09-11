<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Whether a pair may repeat a challenge stops being a schema fact
 * (kolabing-app#150).
 *
 * `challenge_completions_unique` on (challenge, event, challenger, verifier) made
 * "a pair can never repeat" true for everyone. The product model says it is a
 * **community choice** (§6), so the rule moves into
 * ChallengeCompletionService::initiate(), which is the only place that knows
 * which community's rules apply.
 *
 * What is given up: a database-level guarantee against a concurrent double
 * write. Replaced by a `lockForUpdate()` on the event row inside the transaction,
 * which serialises initiates per event. Initiates are rare and per-event
 * contention is not a problem.
 *
 * `down()` recreates the index and **will fail if repeats have happened by
 * then** — which is correct. At that point the old constraint is genuinely false,
 * and silently deleting completions to satisfy it would be much worse than a
 * migration that refuses.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('challenge_completions', function (Blueprint $table): void {
            $table->dropUnique('challenge_completions_unique');
        });
    }

    public function down(): void
    {
        Schema::table('challenge_completions', function (Blueprint $table): void {
            $table->unique(
                ['challenge_id', 'event_id', 'challenger_profile_id', 'verifier_profile_id'],
                'challenge_completions_unique'
            );
        });
    }
};
