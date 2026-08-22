<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Scope a check-in token in time, and give it a typable twin.
 *
 * `events.checkin_token` was a 64-character string with no expiry, so anyone who
 * ever saw the QR could check in months later from anywhere — which is exactly the
 * claim ("this person was in the room") the platform wants to sell. An expiry ties
 * the token to the event window.
 *
 * `checkin_code` is the same permission in a form a person can read out and type:
 * it keeps the door working when a camera will not focus, when a screen glares, or
 * when someone's phone cannot decode a QR at all.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->string('checkin_code', 8)->nullable()->unique()->after('checkin_token');
            $table->timestamp('checkin_token_expires_at')->nullable()->after('checkin_code');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->dropUnique(['checkin_code']);
            $table->dropColumn(['checkin_code', 'checkin_token_expires_at']);
        });
    }
};
