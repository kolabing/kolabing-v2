<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pending email invitations to a community (NF-6 / BE-NF-29).
 *
 * POST /communities/{id}/members only works for people who already hold a
 * Kolabing account — it 404s otherwise. A leader's real roster lives in a
 * spreadsheet, so this table lets them invite an email address: the row waits
 * as `pending` until that person registers (anywhere), at which point the
 * signup hook claims it and creates the membership.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_invitations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('community_id')->constrained('communities')->cascadeOnDelete();
            $table->string('email');
            $table->foreignUuid('tier_id')->nullable()->constrained('community_tiers')->nullOnDelete();
            $table->string('token', 64)->unique();
            $table->foreignUuid('invited_by_profile_id')->nullable()->constrained('profiles')->nullOnDelete();
            // pending | accepted | revoked | expired
            $table->string('status', 20)->default('pending');
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->foreignUuid('accepted_profile_id')->nullable()->constrained('profiles')->nullOnDelete();
            $table->timestamps();

            // A partial unique index on (community_id, email) WHERE pending is
            // not portable to SQLite (the test driver), so uniqueness is
            // enforced in the service (upsert on the pending row) and these
            // indexes serve the reads.
            $table->index(['community_id', 'status']);
            $table->index(['email', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_invitations');
    }
};
