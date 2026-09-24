<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Venue Pro (BE-NF-68): which plan a business subscription is on. Every existing
 * row is the €49 Kolabing Business plan, which is exactly the `standard` default,
 * so no backfill is needed. The value is written from the Stripe Price id on the
 * subscription, never from client input.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_subscriptions', function (Blueprint $table): void {
            $table->string('plan', 20)->default('standard')->after('source');
        });
    }

    public function down(): void
    {
        Schema::table('business_subscriptions', function (Blueprint $table): void {
            $table->dropColumn('plan');
        });
    }
};
