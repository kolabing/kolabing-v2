<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profiles', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('email')->unique();
            $table->string('phone_number', 20)->nullable();
            $table->string('user_type', 20);
            $table->string('google_id')->unique()->nullable();
            $table->text('avatar_url')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamps();

            $table->index('email');
            $table->index('google_id');
            $table->index('user_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profiles');
    }
};
