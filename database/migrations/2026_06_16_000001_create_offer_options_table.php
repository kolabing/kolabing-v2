<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Single table for the three kolab "offer taxonomy" lists, discriminated by
 * `kind` (offering | deliverable | need). Mirrors the business_types / community_types
 * schema (uuid, name, slug, icon, sort_order, is_active). One table (not three)
 * because a single controller/model/Blade/lookup discriminates on `kind` exactly
 * like TypeController does on business/community — three tables would be identical
 * boilerplate. Slug is unique PER kind (e.g. "venue" is both an offering and a need).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offer_options', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('kind', 20); // offering | deliverable | need
            $table->string('name', 100);
            $table->string('slug', 100);
            $table->string('icon', 50)->nullable();
            $table->string('icon_url')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['kind', 'slug']);
            $table->index(['kind', 'is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offer_options');
    }
};
