<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kolabs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('creator_profile_id')
                ->constrained('profiles')
                ->cascadeOnDelete();

            $table->string('intent_type', 30);
            $table->string('status', 20)->default('draft');

            $table->string('title', 255);
            $table->text('description');
            $table->string('preferred_city', 100);
            $table->string('area', 100)->nullable();
            $table->json('media')->nullable();

            $table->string('availability_mode', 20)->nullable();
            $table->date('availability_start')->nullable();
            $table->date('availability_end')->nullable();
            $table->time('selected_time')->nullable();
            $table->json('recurring_days')->nullable();

            $table->json('needs')->nullable();
            $table->json('community_types')->nullable();
            $table->integer('community_size')->nullable();
            $table->integer('typical_attendance')->nullable();
            $table->json('offers_in_return')->nullable();
            $table->string('venue_preference', 30)->nullable();

            $table->string('venue_name', 255)->nullable();
            $table->string('venue_type', 50)->nullable();
            $table->integer('capacity')->nullable();
            $table->text('venue_address')->nullable();

            $table->string('product_name', 255)->nullable();
            $table->string('product_type', 50)->nullable();

            $table->json('offering')->nullable();
            $table->json('seeking_communities')->nullable();
            $table->integer('min_community_size')->nullable();
            $table->json('expects')->nullable();

            $table->json('past_events')->nullable();

            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['intent_type', 'status']);
            $table->index('preferred_city');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kolabs');
    }
};
