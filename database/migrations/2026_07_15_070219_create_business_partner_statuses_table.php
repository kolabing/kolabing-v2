<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_partner_statuses', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('profile_id')
                ->unique()
                ->constrained('profiles')
                ->cascadeOnDelete();
            $table->string('status')->default('new_partner');
            $table->unsignedInteger('completed_kolabs_count')->default(0);
            $table->unsignedInteger('review_count')->default(0);
            $table->unsignedInteger('repeat_partner_count')->default(0);
            $table->decimal('average_rating', 3, 2)->nullable();
            $table->timestamp('recalculated_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_partner_statuses');
    }
};
