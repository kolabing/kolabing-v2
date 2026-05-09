<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_tokens', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('profile_id')
                ->constrained('profiles')
                ->cascadeOnDelete();
            $table->string('token')->unique();
            $table->string('platform', 20);
            $table->string('app_version', 50)->nullable();
            $table->string('locale', 16)->nullable();
            $table->string('timezone', 64)->nullable();
            $table->decimal('last_location_lat', 10, 7)->nullable();
            $table->decimal('last_location_lng', 10, 7)->nullable();
            $table->timestamp('location_permission_granted_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('last_delivered_at')->nullable();
            $table->timestamp('invalidated_at')->nullable();
            $table->string('invalid_reason')->nullable();
            $table->timestamps();

            $table->index(['profile_id', 'is_active']);
            $table->index('last_seen_at');
            $table->index('location_permission_granted_at');
        });

        $now = now();

        DB::table('profiles')
            ->whereNotNull('device_token')
            ->whereNotNull('device_platform')
            ->orderBy('updated_at')
            ->select(['id', 'device_token', 'device_platform', 'updated_at', 'created_at'])
            ->get()
            ->each(function (object $profile) use ($now): void {
                DB::table('device_tokens')->insertOrIgnore([
                    'id' => (string) \Illuminate\Support\Str::uuid(),
                    'profile_id' => $profile->id,
                    'token' => $profile->device_token,
                    'platform' => $profile->device_platform,
                    'is_active' => true,
                    'last_seen_at' => $profile->updated_at ?? $now,
                    'created_at' => $profile->created_at ?? $now,
                    'updated_at' => $profile->updated_at ?? $now,
                ]);
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_tokens');
    }
};
