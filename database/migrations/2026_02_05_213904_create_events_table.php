<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('profile_id')->constrained('profiles')->cascadeOnDelete();
            $table->string('name', 100);
            $table->foreignUuid('partner_id')->constrained('profiles')->cascadeOnDelete();
            $table->string('partner_type', 20);
            $table->date('event_date');
            $table->unsignedInteger('attendee_count')->default(0);
            $table->timestamps();

            $table->index('profile_id');
            $table->index('partner_id');
            $table->index('event_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
