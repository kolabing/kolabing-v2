<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizer_entitlements', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('profile_id')
                ->constrained('profiles')
                ->cascadeOnDelete();

            $table->string('capability', 30)->default('event_creator');

            // Mirrors business_subscriptions.source (stripe|apple_iap|maintainer):
            // MVP only ever writes 'maintainer', but the column keeps the audit
            // pattern consistent and leaves room for a future paid grant path
            // (out of scope here, see Global Constraints).
            $table->string('source', 20)->nullable();

            $table->timestamp('granted_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();

            $table->timestamps();

            $table->index(['profile_id', 'capability']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organizer_entitlements');
    }
};
