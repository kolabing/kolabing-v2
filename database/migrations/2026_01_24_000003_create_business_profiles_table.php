<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_profiles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('profile_id')
                ->unique()
                ->constrained('profiles')
                ->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->text('about')->nullable();
            $table->string('business_type', 100)->nullable();
            $table->foreignUuid('city_id')
                ->nullable()
                ->constrained('cities')
                ->nullOnDelete();
            $table->string('instagram')->nullable();
            $table->string('website')->nullable();
            $table->text('profile_photo')->nullable();
            $table->timestamps();

            $table->index('profile_id');
            $table->index('city_id');
            $table->index('business_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_profiles');
    }
};
