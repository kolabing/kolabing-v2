<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('referral_codes', function (Blueprint $table): void {
            $table->timestamp('expires_at')->nullable()->after('code');
        });

        Schema::table('business_subscriptions', function (Blueprint $table): void {
            $table->unique('apple_original_transaction_id');
            $table->unique('apple_transaction_id');
        });

        DB::table('business_subscriptions')
            ->whereNull('stripe_customer_id')
            ->whereNull('stripe_subscription_id')
            ->whereNull('apple_original_transaction_id')
            ->update(['source' => 'apple_iap']);
    }

    public function down(): void
    {
        Schema::table('business_subscriptions', function (Blueprint $table): void {
            $table->dropUnique(['apple_original_transaction_id']);
            $table->dropUnique(['apple_transaction_id']);
        });

        Schema::table('referral_codes', function (Blueprint $table): void {
            $table->dropColumn('expires_at');
        });
    }
};
