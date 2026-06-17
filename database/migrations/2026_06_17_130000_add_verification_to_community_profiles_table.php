<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Community verification: communities submit proof "channels", an admin
     * manually verifies, businesses see a Verified tick.
     *
     * verified_by holds the admin id (users table, bigint) as a string so the
     * column tolerates either an admin user id or a profile uuid without a hard
     * FK that would break across the two id schemes.
     */
    public function up(): void
    {
        Schema::table('community_profiles', function (Blueprint $table): void {
            $table->json('verification_channels')->nullable()->after('website');
            $table->string('verification_status', 20)->default('unverified')->index()->after('verification_channels');
            $table->timestamp('verified_at')->nullable()->after('verification_status');
            $table->string('verified_by')->nullable()->after('verified_at');
            $table->text('rejection_reason')->nullable()->after('verified_by');
            $table->timestamp('verification_flagged_at')->nullable()->after('rejection_reason');
        });
    }

    public function down(): void
    {
        Schema::table('community_profiles', function (Blueprint $table): void {
            $table->dropColumn([
                'verification_channels',
                'verification_status',
                'verified_at',
                'verified_by',
                'rejection_reason',
                'verification_flagged_at',
            ]);
        });
    }
};
