<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Attribute mission-completed ledger rows to the challenge that paid them.
 * `reference_id` holds the heterogeneous source entity (checkin/collaboration/
 * application id) and is ambiguous when one source action completes several
 * missions; `challenge_id` makes each MissionCompleted credit traceable to its
 * mission. Nullable: existing rows and non-mission events leave it null.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('point_ledger', function (Blueprint $table): void {
            $table->uuid('challenge_id')->nullable()->after('reference_id');
            $table->foreign('challenge_id')->references('id')->on('challenges')->nullOnDelete();
            $table->index('challenge_id');
        });
    }

    public function down(): void
    {
        Schema::table('point_ledger', function (Blueprint $table): void {
            $table->dropForeign(['challenge_id']);
            $table->dropIndex(['challenge_id']);
            $table->dropColumn('challenge_id');
        });
    }
};
