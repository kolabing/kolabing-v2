<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Guarded: prerequisites must exist and the table must not already.
        if (! Schema::hasTable('communities') || ! Schema::hasTable('profiles')) {
            return;
        }

        if (Schema::hasTable('community_join_requests')) {
            return;
        }

        Schema::create('community_join_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('community_id')->constrained('communities')->cascadeOnDelete();
            $table->foreignUuid('profile_id')->constrained('profiles')->cascadeOnDelete();
            $table->string('status', 20)->default('pending'); // pending | approved | declined
            $table->foreignUuid('decided_by')->nullable()->constrained('profiles')->nullOnDelete();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index('community_id');
            $table->index('profile_id');
            $table->index('status');
        });

        // At most one PENDING request per (community, profile). A partial unique
        // index keeps history rows (approved/declined) while blocking duplicate
        // pendings. Postgres (prod) and SQLite (tests) both support partial
        // indexes; MySQL does not, so it falls back to app-level idempotency.
        $driver = DB::connection()->getDriverName();

        if (in_array($driver, ['pgsql', 'sqlite'], true)) {
            DB::statement(
                "CREATE UNIQUE INDEX community_join_requests_pending_unique
                 ON community_join_requests (community_id, profile_id)
                 WHERE status = 'pending'"
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('community_join_requests');
    }
};
