<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_profiles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('profile_id')
                ->unique()
                ->constrained('profiles')
                ->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->text('about')->nullable();
            $table->string('community_type', 100)->nullable();
            $table->foreignUuid('city_id')
                ->nullable()
                ->constrained('cities')
                ->nullOnDelete();
            $table->string('instagram')->nullable();
            $table->string('tiktok')->nullable();
            $table->string('website')->nullable();
            $table->text('profile_photo')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->timestamps();

            $table->index('profile_id');
            $table->index('city_id');
            $table->index('community_type');
        });

        // Create partial index for featured profiles
        if (config('database.default') === 'pgsql') {
            \Illuminate\Support\Facades\DB::statement(
                'CREATE INDEX idx_community_profiles_is_featured ON community_profiles(is_featured) WHERE is_featured = true'
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('community_profiles');
    }
};
