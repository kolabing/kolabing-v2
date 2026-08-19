<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('multi_kolab_role_applications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('multi_kolab_role_id')
                ->constrained('multi_kolab_roles')
                ->cascadeOnDelete();
            $table->foreignUuid('applicant_profile_id')
                ->constrained('profiles')
                ->cascadeOnDelete();
            $table->string('applicant_profile_type', 20);

            $table->string('status', 20)->default('pending');

            $table->text('pitch')->nullable();
            $table->text('availability')->nullable();
            $table->text('withdrawal_reason')->nullable();

            // Restrictive: a child Kolab created on acceptance must not be
            // deletable while this application still references it (protects
            // the canonical acceptance record — see Task 6).
            $table->foreignUuid('kolab_id')
                ->nullable()
                ->constrained('kolabs')
                ->restrictOnDelete();

            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->timestamp('withdrawn_at')->nullable();

            $table->timestamps();

            // Prevent duplicate applications from the same profile to the
            // same role (§5 of the plan).
            $table->unique(['multi_kolab_role_id', 'applicant_profile_id']);

            $table->index('applicant_profile_id');
            $table->index('status');
            $table->index('kolab_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('multi_kolab_role_applications');
    }
};
