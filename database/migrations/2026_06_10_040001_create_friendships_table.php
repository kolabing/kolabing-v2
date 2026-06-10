<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('friendships', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            // The sender of the friend request.
            $table->foreignUuid('requester_profile_id')->constrained('profiles')->cascadeOnDelete();
            // The recipient of the friend request.
            $table->foreignUuid('addressee_profile_id')->constrained('profiles')->cascadeOnDelete();
            // pending | accepted | blocked
            $table->string('status', 16)->default('pending');
            $table->timestamps();

            // One row per ordered pair. The reverse pair is guarded at the
            // application layer (FriendshipService) so a single relationship is
            // never represented twice.
            $table->unique(['requester_profile_id', 'addressee_profile_id'], 'friendships_pair_unique');
            $table->index('requester_profile_id');
            $table->index('addressee_profile_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('friendships');
    }
};
