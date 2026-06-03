<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('communities', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('owner_profile_id')->constrained('profiles')->cascadeOnDelete();
            $table->foreignUuid('community_profile_id')->nullable()->constrained('community_profiles')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('type', 20)->default('other');
            $table->text('description')->nullable();
            $table->string('avatar_url')->nullable();
            $table->boolean('is_primary')->default(true);
            $table->string('join_policy', 20)->default('open');
            $table->timestamps();
            $table->softDeletes();

            $table->index('owner_profile_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communities');
    }
};
