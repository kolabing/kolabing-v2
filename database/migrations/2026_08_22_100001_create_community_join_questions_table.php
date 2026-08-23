<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The questions a leader asks before letting someone become a member
 * (kolabing-app#138).
 *
 * Retired rather than deleted: `is_active = false` takes a question out of the
 * form while its past answers stay readable, so a leader reviewing an old
 * application can still see what was actually asked. Deleting would cascade the
 * answers away and leave the application meaningless.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_join_questions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('community_id')->constrained('communities')->cascadeOnDelete();
            // Display order, 1..5. The 5-active cap is enforced in the service.
            $table->unsignedSmallInteger('position')->default(1);
            $table->string('prompt', 280);
            $table->boolean('required')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['community_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_join_questions');
    }
};
