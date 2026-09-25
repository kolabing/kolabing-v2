<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Structured FAQ pairs for a post, mirrored from the article's visible
     * "FAQ" section and emitted as FAQPage JSON-LD on /blog/{slug} (answer
     * engines read the schema; readers read the prose — the two must match,
     * which is why this is stored data, not view-time HTML parsing).
     * Shape: [{"question": "...", "answer": "..."}, ...]. Nullable — posts
     * without a FAQ section simply emit no FAQPage schema.
     */
    public function up(): void
    {
        Schema::table('blog_posts', function (Blueprint $table): void {
            $table->json('faq')->nullable()->after('body');
        });
    }

    public function down(): void
    {
        Schema::table('blog_posts', function (Blueprint $table): void {
            $table->dropColumn('faq');
        });
    }
};
