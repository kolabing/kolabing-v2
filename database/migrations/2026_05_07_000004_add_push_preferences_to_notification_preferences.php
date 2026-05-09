<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_preferences', function (Blueprint $table): void {
            $table->boolean('messages_enabled')->default(true)->after('marketing_tips');
            $table->boolean('applications_enabled')->default(true)->after('messages_enabled');
            $table->boolean('collaborations_enabled')->default(true)->after('applications_enabled');
            $table->boolean('rewards_enabled')->default(true)->after('collaborations_enabled');
            $table->boolean('marketing_enabled')->default(false)->after('rewards_enabled');
            $table->time('quiet_hours_start')->nullable()->after('marketing_enabled');
            $table->time('quiet_hours_end')->nullable()->after('quiet_hours_start');
            $table->string('timezone', 64)->nullable()->after('quiet_hours_end');
        });
    }

    public function down(): void
    {
        Schema::table('notification_preferences', function (Blueprint $table): void {
            $table->dropColumn([
                'messages_enabled',
                'applications_enabled',
                'collaborations_enabled',
                'rewards_enabled',
                'marketing_enabled',
                'quiet_hours_start',
                'quiet_hours_end',
                'timezone',
            ]);
        });
    }
};
