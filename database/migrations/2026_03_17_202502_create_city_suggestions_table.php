<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('city_suggestions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('suggested_by')
                ->constrained('profiles')
                ->cascadeOnDelete();
            $table->string('city_name', 200);
            $table->timestamps();

            $table->index('suggested_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('city_suggestions');
    }
};
