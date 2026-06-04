<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_members', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('community_id')->constrained('communities')->cascadeOnDelete();
            $table->foreignUuid('profile_id')->constrained('profiles')->cascadeOnDelete();
            $table->foreignUuid('tier_id')->nullable()->constrained('community_tiers')->nullOnDelete();
            $table->boolean('can_manage')->default(false);
            $table->string('status', 20)->default('active');
            $table->timestamp('joined_at');
            $table->timestamp('tier_assigned_at')->nullable();
            $table->timestamps();

            $table->unique(['community_id', 'profile_id']);
            $table->index('profile_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_members');
    }
};
