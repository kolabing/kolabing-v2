<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('multi_kolab_event_status_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('multi_kolab_event_id')
                ->constrained('multi_kolab_events')
                ->cascadeOnDelete();

            $table->string('status', 20);

            // Null actor_profile_id = system/maintainer action, matching the
            // existing "null = maintainer" convention on collaborations
            // (ROLES-BACKEND-DB-MAP.md §10).
            $table->foreignUuid('actor_profile_id')
                ->nullable()
                ->constrained('profiles')
                ->nullOnDelete();

            $table->text('reason')->nullable();

            $table->timestamps();

            $table->index(['multi_kolab_event_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('multi_kolab_event_status_events');
    }
};
