<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Honest social-proof layer for the community-rankings directory.
 *
 *  - listing_vouches: one-directional public endorsements ("I vouch for this community").
 *    Spam-resistant + PII-free: a UNIQUE dedupe_hash = sha256(cookie + /24 subnet + listing
 *    + rotating salt), never a raw IP. Counts are always REAL taps; nothing is seeded.
 *  - listing_testimonials: member quotes, moderation-queued (status=pending) — never live on
 *    submit, URL-banned, length-bounded. email_hash (not raw email) for erasure + verified match.
 *  - rank_snapshots: weekly rank captures so "up N this week" movement is real, never invented.
 *  - listing_claims gains double-opt-in verification (verify_token + verified_at) so the
 *    "Verified by N members" proof and the verified badge are email-backed, not gameable.
 *
 * Votes NEVER reorder the public ranking (that stays the admin-score projection); this layer
 * is displayed proof only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('listing_vouches', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('listing_id')->nullable()
                ->constrained('crm_accounts')->nullOnDelete();
            $table->string('ranking_city', 60)->nullable();
            $table->char('dedupe_hash', 64)->unique();  // sha256(cookie + ip/24 + listing + salt)
            $table->boolean('verified')->default(false); // true only when tied to a verified claim
            $table->string('reason', 24)->nullable();    // member | attended | know_organizer
            $table->timestamps();

            $table->index(['listing_id', 'verified']);
        });

        Schema::create('listing_testimonials', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('listing_id')->nullable()
                ->constrained('crm_accounts')->nullOnDelete();
            $table->string('body', 280);
            $table->string('author_label', 60)->nullable(); // 'member' | first name; never full PII
            $table->char('email_hash', 64)->nullable();      // erasure + verified-member match
            $table->boolean('verified_member')->default(false);
            $table->string('status', 12)->default('pending'); // pending | approved | rejected
            $table->timestamp('reviewed_at')->nullable();
            $table->string('reviewed_by', 120)->nullable();
            $table->timestamps();

            $table->index(['listing_id', 'status']);
        });

        Schema::create('rank_snapshots', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('listing_id')->nullable()
                ->constrained('crm_accounts')->nullOnDelete();
            $table->string('city', 60);
            $table->integer('rank');
            $table->date('captured_on');

            $table->unique(['listing_id', 'captured_on']);
            $table->index(['city', 'captured_on']);
        });

        Schema::table('listing_claims', function (Blueprint $table): void {
            $table->string('verify_token', 40)->nullable()->after('source');
            $table->timestamp('verified_at')->nullable()->after('verify_token');
        });
    }

    public function down(): void
    {
        Schema::table('listing_claims', function (Blueprint $table): void {
            $table->dropColumn(['verify_token', 'verified_at']);
        });
        Schema::dropIfExists('rank_snapshots');
        Schema::dropIfExists('listing_testimonials');
        Schema::dropIfExists('listing_vouches');
    }
};
