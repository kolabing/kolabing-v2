<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collaboration_challenge_bonuses', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('collaboration_id')->constrained('collaborations')->cascadeOnDelete();
            $table->foreignUuid('challenge_id')->constrained('challenges')->cascadeOnDelete();
            $table->string('bonus_type', 30);
            $table->string('bonus_value', 120);
            $table->string('bonus_description', 240)->nullable();
            $table->foreignUuid('set_by_profile_id')->constrained('profiles')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['collaboration_id', 'challenge_id'], 'collab_challenge_bonus_unique_idx');
            $table->index(['collaboration_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collaboration_challenge_bonuses');
    }
};
