<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('collab_opportunity_id')
                ->constrained('collab_opportunities')
                ->cascadeOnDelete();
            $table->foreignUuid('applicant_profile_id')
                ->constrained('profiles')
                ->cascadeOnDelete();
            $table->string('applicant_profile_type', 20);
            $table->text('message')->nullable();
            $table->text('availability')->nullable();
            $table->string('status', 20)->default('pending');
            $table->timestamps();

            // Prevent duplicate applications
            $table->unique(['collab_opportunity_id', 'applicant_profile_id']);

            $table->index('collab_opportunity_id');
            $table->index('applicant_profile_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applications');
    }
};
