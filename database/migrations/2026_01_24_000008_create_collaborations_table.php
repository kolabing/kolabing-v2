<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collaborations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('application_id')
                ->unique()
                ->constrained('applications')
                ->cascadeOnDelete();
            $table->foreignUuid('collab_opportunity_id')
                ->constrained('collab_opportunities')
                ->cascadeOnDelete();
            $table->foreignUuid('creator_profile_id')
                ->constrained('profiles')
                ->cascadeOnDelete();
            $table->foreignUuid('applicant_profile_id')
                ->constrained('profiles')
                ->cascadeOnDelete();
            $table->foreignUuid('business_profile_id')
                ->nullable()
                ->constrained('business_profiles')
                ->nullOnDelete();
            $table->foreignUuid('community_profile_id')
                ->nullable()
                ->constrained('community_profiles')
                ->nullOnDelete();
            $table->string('status', 20)->default('scheduled');
            $table->date('scheduled_date')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('contact_methods')->nullable();
            $table->timestamps();

            $table->index('application_id');
            $table->index('collab_opportunity_id');
            $table->index('creator_profile_id');
            $table->index('applicant_profile_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collaborations');
    }
};
