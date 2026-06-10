<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Attendee interests — a JSON array of community-type slugs (the CommunityType
 * enum values). Set at onboarding, editable later, and used to rank community
 * discovery interest-first. Nullable; cast to array on the Profile model.
 * Guarded so it is a no-op where the column already exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('profiles', 'interests')) {
            Schema::table('profiles', function (Blueprint $table): void {
                $table->json('interests')->nullable()->after('handle');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('profiles', 'interests')) {
            Schema::table('profiles', function (Blueprint $table): void {
                $table->dropColumn('interests');
            });
        }
    }
};
