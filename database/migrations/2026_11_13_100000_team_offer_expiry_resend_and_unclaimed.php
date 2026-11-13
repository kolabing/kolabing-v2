<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kolabing team sender, second pass (SERR-1344, on top of SERR-658).
     *
     * - kolabing_team_offers.expires_at: an offer expires 7 days after `sent_at`
     *   (a resend restarts the clock). Expiry is read from this column, never
     *   written by a job, so there is nothing to forget to schedule.
     * - resent_at / resend_count: an admin may resend a row once; the resend
     *   counts toward the weekly cap.
     * - claim_token_hash / claim_token_enc / contact_email: an offer to an
     *   unclaimed profile carries a claim link. The hash is the lookup, the
     *   encrypted copy lets the admin see the same link again.
     * - profiles.is_unclaimed / claimed_at: a profile the team created before
     *   its owner joined. It is also `is_active = false`, which is what keeps it
     *   out of Explore, the directory and the stats (ActiveProfileScope and the
     *   `active()` scope); `is_unclaimed` says WHY it is off, so a plain
     *   "activate" cannot publish it by accident.
     */
    public function up(): void
    {
        Schema::table('kolabing_team_offers', function (Blueprint $table): void {
            $table->timestamp('expires_at')->nullable()->after('responded_at');
            $table->timestamp('resent_at')->nullable()->after('expires_at');
            $table->unsignedSmallInteger('resend_count')->default(0)->after('resent_at');
            $table->string('claim_token_hash', 64)->nullable()->unique()->after('resend_count');
            $table->text('claim_token_enc')->nullable()->after('claim_token_hash');
            $table->string('contact_email')->nullable()->after('claim_token_enc');
        });

        Schema::table('profiles', function (Blueprint $table): void {
            $table->boolean('is_unclaimed')->default(false)->after('is_active');
            $table->timestamp('claimed_at')->nullable()->after('is_unclaimed');
        });

        // Rows sent before this migration get the same 7-day window.
        DB::table('kolabing_team_offers')->whereNotNull('sent_at')->orderBy('id')->each(function ($row): void {
            DB::table('kolabing_team_offers')->where('id', $row->id)->update([
                'expires_at' => \Illuminate\Support\Carbon::parse($row->sent_at)->addDays(7),
            ]);
        }, 100, 'id');
    }

    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table): void {
            $table->dropColumn(['is_unclaimed', 'claimed_at']);
        });

        Schema::table('kolabing_team_offers', function (Blueprint $table): void {
            $table->dropUnique(['claim_token_hash']);
            $table->dropColumn(['expires_at', 'resent_at', 'resend_count', 'claim_token_hash', 'claim_token_enc', 'contact_email']);
        });
    }
};
