<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('profile_id');
            $table->string('type');
            $table->string('title');
            $table->text('body');
            $table->uuid('actor_profile_id')->nullable();
            $table->uuid('target_id')->nullable();
            $table->string('target_type')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->foreign('profile_id')
                ->references('id')
                ->on('profiles')
                ->onDelete('cascade');

            $table->foreign('actor_profile_id')
                ->references('id')
                ->on('profiles')
                ->onDelete('set null');

            $table->index('profile_id');
            $table->index(['profile_id', 'read_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
