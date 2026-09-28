<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Instagram Connect (BE-NF-75): one connected Instagram professional account per
 * Kolabing profile, plus the Meta data-deletion request log, plus the source
 * markers + video columns on the gallery so imported media can be recognised,
 * shown as video, and deleted on a Meta data-deletion request.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instagram_accounts', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('profile_id')->unique()->constrained('profiles')->cascadeOnDelete();
            // Instagram professional account id (`user_id` on /me) and the
            // app-scoped id (`id` on /me); Meta callbacks may carry either.
            $table->string('ig_user_id', 64)->index();
            $table->string('ig_app_scoped_id', 64)->nullable()->index();
            $table->string('username', 100);
            $table->string('name', 255)->nullable();
            $table->string('account_type', 32)->nullable();
            $table->text('profile_picture_url')->nullable();
            // Long-lived token, encrypted at rest (Eloquent `encrypted` cast).
            // NULL = disconnected (the row stays so a later Meta data-deletion
            // request can still find the media this account imported).
            $table->text('access_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->timestamp('token_refreshed_at')->nullable();
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('disconnected_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('instagram_data_deletion_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('confirmation_code', 64)->unique();
            $table->string('ig_user_id', 64)->index();
            $table->string('status', 20)->default('completed');
            $table->unsignedInteger('accounts_deleted')->default(0);
            $table->unsignedInteger('media_deleted')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::table('profile_gallery_photos', function (Blueprint $table): void {
            // `url` stays an image for every item (for a video it is the poster
            // frame), so every existing surface that renders `url` as a picture
            // keeps working; the video file itself lives in `video_url`.
            $table->string('media_type', 10)->default('image');
            $table->text('video_url')->nullable();
            // The Instagram media id stored (a carousel child's own id) and the id
            // the user picked (the carousel's id), for the `imported` flag.
            $table->string('instagram_media_id', 64)->nullable();
            $table->string('instagram_source_id', 64)->nullable();
            $table->index(['profile_id', 'instagram_source_id']);
        });
    }

    public function down(): void
    {
        Schema::table('profile_gallery_photos', function (Blueprint $table): void {
            $table->dropIndex(['profile_id', 'instagram_source_id']);
            $table->dropColumn(['media_type', 'video_url', 'instagram_media_id', 'instagram_source_id']);
        });

        Schema::dropIfExists('instagram_data_deletion_requests');
        Schema::dropIfExists('instagram_accounts');
    }
};
