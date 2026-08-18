<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Community rankings directory (public city×topic lead-magnet pages).
 *
 * The ranked list is a LIVE projection of crm_accounts (type=community) ordered by
 * the admin-managed score, so re-rank/edit/add/remove all happen in /admin/crm.
 * This migration adds only the thin editorial + control layer:
 *  - ranking_pages: the per-page marketing copy (curated without touching leads).
 *  - crm_accounts.listed: the public-visibility flag on a community lead.
 *  - listing_claims: the one-field "claim your listing" lead capture.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ranking_pages', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('city', 60);
            $table->string('topic', 60)->nullable();      // null = the city hub page
            $table->string('slug')->unique();
            $table->string('title');
            $table->string('meta_description', 320)->nullable();
            $table->text('intro')->nullable();
            $table->text('how_ranked')->nullable();
            $table->json('verticals')->nullable();          // CRM vertical keywords this page pulls (null = all, for a hub)
            $table->json('faq')->nullable();               // [{q,a}, ...]
            $table->string('editor_name', 80)->nullable(); // named editor byline (E-E-A-T)
            $table->boolean('published')->default(false);  // progressive-rollout guard
            $table->integer('sort')->default(0);
            $table->timestamps();

            $table->index(['city', 'published']);
            $table->index('topic');
        });

        Schema::table('crm_accounts', function (Blueprint $table): void {
            // Public directory visibility. Community leads are private in the CRM until
            // a maintainer lists them (or Maria's per-city review passes).
            $table->boolean('listed')->default(false)->after('score');
            $table->index(['type', 'listed']);
        });

        Schema::create('listing_claims', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('community_name');
            $table->string('handle', 120)->nullable();
            $table->string('email');
            $table->string('city', 60)->nullable();
            $table->string('source', 60)->nullable();      // which ranking page the claim came from
            $table->foreignUuid('crm_account_id')->nullable()
                ->constrained('crm_accounts')->nullOnDelete();
            $table->timestamps();

            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listing_claims');
        Schema::table('crm_accounts', function (Blueprint $table): void {
            $table->dropIndex(['type', 'listed']);
            $table->dropColumn('listed');
        });
        Schema::dropIfExists('ranking_pages');
    }
};
