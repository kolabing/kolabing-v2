<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per community Kolabing invited, by in-app + push notification,
     * to apply to a Kolab (KolabInviteService). The unique pair is the
     * "at most one invite per Kolab" rule; (profile_id, created_at) serves the
     * weekly cap. Nothing in the app reads it to grant or gate anything.
     */
    public function up(): void
    {
        Schema::create('kolab_invites', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('kolab_id')->constrained('kolabs')->cascadeOnDelete();
            $table->foreignUuid('profile_id')->constrained('profiles')->cascadeOnDelete();
            $table->foreignId('sent_by_admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('notification_id')->nullable();
            $table->timestamps();

            $table->unique(['kolab_id', 'profile_id']);
            $table->index(['profile_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kolab_invites');
    }
};
