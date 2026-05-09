<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_deliveries', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('notification_id')
                ->constrained('notifications')
                ->cascadeOnDelete();
            $table->foreignUuid('device_token_id')
                ->constrained('device_tokens')
                ->cascadeOnDelete();
            $table->string('provider', 20)->default('fcm');
            $table->string('provider_message_id')->nullable();
            $table->string('status', 30)->default('queued');
            $table->unsignedInteger('attempt_count')->default(0);
            $table->string('last_error_code')->nullable();
            $table->text('last_error_message')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->unique(['notification_id', 'device_token_id']);
            $table->index(['status', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_deliveries');
    }
};
