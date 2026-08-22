<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('multi_kolab_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('creator_profile_id')
                ->constrained('profiles')
                ->cascadeOnDelete();

            $table->string('status', 20)->default('draft');

            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->text('value_summary')->nullable();

            $table->boolean('venue_needed')->default(false);

            $table->string('date_mode', 10)->nullable();
            $table->date('event_date')->nullable();
            $table->date('date_range_start')->nullable();
            $table->date('date_range_end')->nullable();

            $table->string('city', 100)->nullable();
            $table->string('category', 100)->nullable();
            $table->string('rsvp_url', 2048)->nullable();

            $table->string('eligible_account_type', 20)->default('either');

            $table->timestamp('published_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();

            $table->timestamps();

            $table->index(['status', 'city']);
            $table->index('creator_profile_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('multi_kolab_events');
    }
};
