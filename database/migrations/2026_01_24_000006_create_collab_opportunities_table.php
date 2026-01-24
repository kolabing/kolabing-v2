<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collab_opportunities', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('creator_profile_id')
                ->constrained('profiles')
                ->cascadeOnDelete();
            $table->string('creator_profile_type', 20);
            $table->string('title');
            $table->text('description');
            $table->string('status', 20)->default('draft');

            // What the business offers (JSON)
            $table->json('business_offer')->nullable();

            // What the community delivers (JSON)
            $table->json('community_deliverables')->nullable();

            // Category tags (JSON array)
            $table->json('categories')->nullable();

            // Availability settings
            $table->string('availability_mode', 50)->nullable();
            $table->date('availability_start')->nullable();
            $table->date('availability_end')->nullable();

            // Venue settings
            $table->string('venue_mode', 50)->nullable();
            $table->text('address')->nullable();
            $table->string('preferred_city', 100)->nullable();

            // Media
            $table->text('offer_photo')->nullable();

            // Timestamps
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index('creator_profile_id');
            $table->index('status');
            $table->index('creator_profile_type');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collab_opportunities');
    }
};
