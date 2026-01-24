<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_subscriptions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('profile_id')
                ->unique()
                ->constrained('profiles')
                ->cascadeOnDelete();
            $table->string('stripe_customer_id')->nullable();
            $table->string('stripe_subscription_id')->nullable();
            $table->string('status', 20)->default('inactive');
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->boolean('cancel_at_period_end')->default(false);
            $table->timestamps();

            $table->index('profile_id');
            $table->index('stripe_customer_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_subscriptions');
    }
};
