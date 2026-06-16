<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin-managed personalised-icon library. Each row points at an SVG file stored in
 * the public disk under `category-icons/`; the public URL is derived at read time
 * from `filename` (no URL column to keep stale). `source` distinguishes the bundled
 * app icons (seeded from the mobile app assets) from admin uploads. Offer options
 * and any other taxonomy pick a library icon and persist its public URL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('icons', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('label');
            $table->string('slug')->unique();
            $table->string('filename');
            $table->string('source', 20)->default('uploaded'); // bundled | uploaded
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('icons');
    }
};
