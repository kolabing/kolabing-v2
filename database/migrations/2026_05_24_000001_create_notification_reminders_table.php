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
        Schema::create('notification_reminders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('profile_id');
            $table->string('type');
            $table->uuid('entity_id');
            $table->string('entity_type');
            $table->timestamp('anchor_at')->nullable();
            $table->unsignedTinyInteger('next_sequence')->default(0);
            $table->unsignedTinyInteger('last_sent_sequence')->nullable();
            $table->timestamp('scheduled_for')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->foreign('profile_id')
                ->references('id')
                ->on('profiles')
                ->onDelete('cascade');

            $table->unique(['profile_id', 'type', 'entity_id', 'entity_type'], 'notification_reminders_unique_chain');
            $table->index(['scheduled_for', 'cancelled_at'], 'notification_reminders_schedule_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_reminders');
    }
};
