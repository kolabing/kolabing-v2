<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which challenges a community's events play (kolabing-app#150).
 *
 * There was no community layer at all: every community's events showed the same
 * global list, so a run club that wants people meeting each other and a
 * community that only cares about attendance got identical challenges.
 *
 * The library itself needs no table — it is `challenges where is_system and
 * trigger_action is null`, exactly the set ChallengeService::listForEvent()
 * already returns. Only the CHOICE needs storing, and this is it.
 *
 * **Presence means enabled.** No `is_enabled` flag: the row means "this
 * community plays this challenge", and a flag would add a state every read has
 * to filter which says nothing the absence of a row does not.
 *
 * **A community with no rows gets the whole library.** That is today's
 * behaviour, and it is the point: the alternative blanks every existing
 * community's events the day this deploys, since nobody has curated anything
 * yet — until now there was nothing to curate. Curating is the act that changes
 * behaviour.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_challenges', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('community_id')->constrained('communities')->cascadeOnDelete();
            $table->foreignUuid('challenge_id')->constrained('challenges')->cascadeOnDelete();

            // §6 — some communities encourage meeting different people, others
            // do not care. Off by default, which preserves the old hard rule.
            $table->boolean('allow_repeat_with_same_person')->default(false);

            // §7 — "Meet someone new" means someone you have not played with
            // before, in either direction, at any event.
            $table->boolean('requires_new_person')->default(false);

            $table->timestamps();

            $table->unique(['community_id', 'challenge_id']);
            $table->index('community_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_challenges');
    }
};
