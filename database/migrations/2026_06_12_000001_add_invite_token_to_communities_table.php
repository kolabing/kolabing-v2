<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('communities', function (Blueprint $table): void {
            // Pre-authorizing token for invite_only communities. Open communities
            // never need it (their slug link is enough). Nullable + unique so it can
            // be lazily minted on the first GET /communities/{id}/invite call.
            $table->string('invite_token', 64)->nullable()->unique()->after('join_policy');
        });
    }

    public function down(): void
    {
        Schema::table('communities', function (Blueprint $table): void {
            $table->dropUnique(['invite_token']);
            $table->dropColumn('invite_token');
        });
    }
};
