<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('company_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('legal_name')->nullable();
            $table->text('registered_address')->nullable();
            $table->string('registration_number')->nullable();
            $table->text('refund_policy')->nullable();
            $table->string('privacy_email')->nullable();
            $table->string('support_email')->nullable();
            $table->string('terms_version')->nullable();
            $table->date('terms_effective_date')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('company_settings');
    }
};
