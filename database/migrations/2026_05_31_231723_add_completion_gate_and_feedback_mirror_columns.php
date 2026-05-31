<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('collaborations', function (Blueprint $table): void {
            $table->string('completion_reason', 500)->nullable();
            $table->foreignUuid('completed_by_profile_id')->nullable()
                ->constrained('profiles')->nullOnDelete();
            $table->timestamp('auto_completed_at')->nullable();
        });

        // Mirror flag on existing collaboration_feedback table (created earlier;
        // still in prod even though the controller was rolled back).
        Schema::table('collaboration_feedback', function (Blueprint $table): void {
            $table->boolean('mirrored_from_review')->default(false)->after('benefits');
        });

        // Relax the two boolean fields so mirrored rows can sit alongside rich
        // ones without forcing a value the legacy client doesn't know to send.
        // SQLite/PostgreSQL portable: drop NOT NULL by re-issuing the column with
        // nullable(). For sqlite we use doctrine-free raw because the bundled
        // schema builder cannot ALTER COLUMN; for everything else use change().
        if (DB::connection()->getDriverName() === 'sqlite') {
            // SQLite via Laravel: create a shadow column, copy, drop, rename.
            Schema::table('collaboration_feedback', function (Blueprint $table): void {
                $table->boolean('expectation_match_new')->nullable()->after('expectation_match');
                $table->boolean('would_recommend_new')->nullable()->after('would_recommend');
            });

            DB::statement('UPDATE collaboration_feedback SET expectation_match_new = expectation_match, would_recommend_new = would_recommend');

            Schema::table('collaboration_feedback', function (Blueprint $table): void {
                $table->dropColumn('expectation_match');
                $table->dropColumn('would_recommend');
            });

            Schema::table('collaboration_feedback', function (Blueprint $table): void {
                $table->renameColumn('expectation_match_new', 'expectation_match');
                $table->renameColumn('would_recommend_new', 'would_recommend');
            });
        } else {
            Schema::table('collaboration_feedback', function (Blueprint $table): void {
                $table->boolean('expectation_match')->nullable()->change();
                $table->boolean('would_recommend')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        Schema::table('collaboration_feedback', function (Blueprint $table): void {
            $table->dropColumn('mirrored_from_review');
        });

        if (DB::connection()->getDriverName() !== 'sqlite') {
            Schema::table('collaboration_feedback', function (Blueprint $table): void {
                $table->boolean('expectation_match')->nullable(false)->change();
                $table->boolean('would_recommend')->nullable(false)->change();
            });
        }

        Schema::table('collaborations', function (Blueprint $table): void {
            $table->dropForeign(['completed_by_profile_id']);
            $table->dropColumn(['completion_reason', 'completed_by_profile_id', 'auto_completed_at']);
        });
    }
};
