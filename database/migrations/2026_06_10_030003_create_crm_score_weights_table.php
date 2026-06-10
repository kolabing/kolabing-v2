<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin-adjustable scoring weights. Business: Y/N fit factors → /100. Ambassador:
 * contribution points (referrals/conversions/kolabs/feedback/suggestions).
 * Seeded with the defaults from the CRM sheets; editable in admin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_score_weights', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('applies_to', 20);   // business | ambassador
            $table->string('key', 60);          // e.g. active_ig, businesses_referred
            $table->string('label');
            $table->integer('points');
            $table->timestamps();

            $table->unique(['applies_to', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_score_weights');
    }
};
