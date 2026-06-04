<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_tiers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('community_id')->constrained('communities')->cascadeOnDelete();
            $table->string('name');
            $table->integer('rank');
            $table->string('color', 9)->nullable();
            $table->string('assignment_rule', 20)->default('manual');
            $table->integer('threshold')->nullable();
            $table->json('permissions')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index('community_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_tiers');
    }
};
