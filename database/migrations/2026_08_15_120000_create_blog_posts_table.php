<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Marketing / SEO blog posts, served server-side (Blade) at /blog and
     * /blog/{slug} on the public marketing site and authored by maintainers via
     * /admin/blog. `published_at` gates visibility (null or future = draft).
     * `body` is trusted maintainer-authored HTML rendered in a prose container.
     * Not linked to `profiles` — `author_name` / `author_title` are the free-text
     * byline used for E-E-A-T authority in the Article schema.
     */
    public function up(): void
    {
        Schema::create('blog_posts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('slug')->unique();
            $table->string('title');
            $table->string('description', 500);
            $table->longText('body');
            $table->string('author_name')->default('Kolabing Team');
            $table->string('author_title')->nullable();
            $table->string('cover_image_url')->nullable();
            $table->string('locale', 8)->default('en');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['locale', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_posts');
    }
};
