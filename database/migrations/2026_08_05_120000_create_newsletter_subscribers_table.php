<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Marketing mailing-list captures from the public landing page pop-up.
     * One row per email (unique, so a repeat submit is idempotent). `audience`
     * records whether the person self-identified as a community or a business
     * so the list can be segmented; null when they did not pick. Not linked to
     * `profiles` — these are pre-signup leads, not authenticated users.
     */
    public function up(): void
    {
        Schema::create('newsletter_subscribers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('email')->unique();
            $table->string('audience')->nullable();
            $table->string('source')->default('landing_popup');
            $table->string('locale', 8)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index('audience');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_subscribers');
    }
};
