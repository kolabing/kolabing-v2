<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Links an event to the community it belongs to (spec NF-6 §0.6).
     * Nullable: legacy events and standalone organiser events have no community.
     * The events_attended tier rule and chapter analytics scope by this column.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->foreignUuid('community_id')->nullable()->after('profile_id')
                ->constrained('communities')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('community_id');
        });
    }
};
