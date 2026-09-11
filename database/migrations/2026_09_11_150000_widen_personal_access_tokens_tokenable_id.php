<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * personal_access_tokens.tokenable_id was created as `uuid` (uuidMorphs()) to match the
 * mobile-app Profile model's UUID primary key — the only HasApiTokens model at the time.
 * Adding HasApiTokens to User (bigint auto-increment id, PR #265) broke token issuance for
 * maintainers: Postgres rejects an integer id like "2" as invalid uuid syntax. A single
 * polymorphic column has to hold both id shapes, so it needs a general string type, not uuid.
 *
 * Safe on live data: uuid -> text is a lossless, standard Postgres cast (canonical string
 * representation), so every existing Profile token keeps validating identically after this.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE personal_access_tokens ALTER COLUMN tokenable_id TYPE varchar(36) USING tokenable_id::text');
    }

    public function down(): void
    {
        // Reversible only if every stored tokenable_id is still uuid-shaped at rollback time —
        // true for Profile tokens, NOT true for any User (bigint id) token issued while this
        // migration was up. Rolling back after issuing a maintainer token will fail loudly
        // (invalid uuid syntax) rather than silently truncating data — intentional: revoke any
        // User-issued tokens first (`personal_access_tokens` where tokenable_type = User::class)
        // if a real rollback is ever needed.
        DB::statement('ALTER TABLE personal_access_tokens ALTER COLUMN tokenable_id TYPE uuid USING tokenable_id::uuid');
    }
};
