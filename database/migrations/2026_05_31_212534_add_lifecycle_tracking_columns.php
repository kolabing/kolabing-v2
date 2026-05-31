<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table): void {
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->timestamp('withdrawn_at')->nullable();
        });

        Schema::table('collaborations', function (Blueprint $table): void {
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancellation_reason', 500)->nullable();
            $table->foreignUuid('cancelled_by_profile_id')->nullable()
                ->constrained('profiles')->nullOnDelete();
        });

        Schema::table('profiles', function (Blueprint $table): void {
            $table->timestamp('last_active_at')->nullable();
            $table->index('last_active_at');
        });
    }

    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table): void {
            $table->dropIndex(['last_active_at']);
            $table->dropColumn('last_active_at');
        });

        Schema::table('collaborations', function (Blueprint $table): void {
            $table->dropForeign(['cancelled_by_profile_id']);
            $table->dropColumn(['activated_at', 'cancelled_at', 'cancellation_reason', 'cancelled_by_profile_id']);
        });

        Schema::table('applications', function (Blueprint $table): void {
            $table->dropColumn(['accepted_at', 'declined_at', 'withdrawn_at']);
        });
    }
};
