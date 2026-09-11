<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Following a community — the lightweight half of the follower/member split
 * (kolabing-app#138).
 *
 * A deliberately separate table rather than a `kind` column on
 * `community_members`. The security-critical direction is that a follower must
 * never gain member access, and membership gates seven surfaces (chat,
 * member/tier events, community points, badges, leaderboard, tier assignment,
 * roster). Keeping followers out of `community_members` means every one of
 * those existing queries keeps its exact meaning and cannot start matching a
 * follower by accident. A shared table with a discriminator fails the other
 * way: an unfiltered query would include followers, so missing a single call
 * site would leak privilege silently.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_followers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('community_id')->constrained('communities')->cascadeOnDelete();
            $table->foreignUuid('profile_id')->constrained('profiles')->cascadeOnDelete();
            $table->timestamp('followed_at');
            $table->timestamps();

            // One follow per person per community; the service relies on this to
            // make following idempotent.
            $table->unique(['community_id', 'profile_id']);
            // "Which communities do I follow" is the common read.
            $table->index('profile_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_followers');
    }
};
