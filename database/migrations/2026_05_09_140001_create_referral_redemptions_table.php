<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referral_redemptions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('referral_code_id')
                ->constrained('referral_codes')
                ->cascadeOnDelete();
            $table->foreignUuid('referred_profile_id')
                ->unique()
                ->constrained('profiles')
                ->cascadeOnDelete();
            $table->foreignUuid('business_subscription_id')
                ->constrained('business_subscriptions')
                ->cascadeOnDelete();
            $table->timestamp('rewarded_at');
            $table->timestamps();

            $table->index('referral_code_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_redemptions');
    }
};
