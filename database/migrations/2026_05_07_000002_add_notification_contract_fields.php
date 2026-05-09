<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table): void {
            $table->string('deeplink')->nullable()->after('target_type');
            $table->string('image_url')->nullable()->after('deeplink');
            $table->json('data')->nullable()->after('image_url');
            $table->string('priority', 20)->default('normal')->after('data');
            $table->boolean('is_in_app')->default(true)->after('priority');
            $table->boolean('is_push')->default(true)->after('is_in_app');
            $table->string('dedupe_key')->nullable()->after('is_push');
            $table->timestamp('queued_at')->nullable()->after('dedupe_key');

            $table->unique(['profile_id', 'dedupe_key'], 'notifications_profile_dedupe_unique');
            $table->index(['type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table): void {
            $table->dropUnique('notifications_profile_dedupe_unique');
            $table->dropIndex(['type', 'created_at']);
            $table->dropColumn([
                'deeplink',
                'image_url',
                'data',
                'priority',
                'is_in_app',
                'is_push',
                'dedupe_key',
                'queued_at',
            ]);
        });
    }
};
