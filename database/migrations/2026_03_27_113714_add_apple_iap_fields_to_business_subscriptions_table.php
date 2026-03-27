<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_subscriptions', function (Blueprint $table): void {
            $table->string('source', 20)->default('stripe')->after('cancel_at_period_end');
            $table->string('apple_original_transaction_id')->nullable()->after('source');
            $table->string('apple_transaction_id')->nullable()->after('apple_original_transaction_id');
            $table->string('apple_product_id')->nullable()->after('apple_transaction_id');

            $table->index('apple_original_transaction_id');
        });
    }

    public function down(): void
    {
        Schema::table('business_subscriptions', function (Blueprint $table): void {
            $table->dropIndex(['apple_original_transaction_id']);
            $table->dropColumn(['source', 'apple_original_transaction_id', 'apple_transaction_id', 'apple_product_id']);
        });
    }
};
