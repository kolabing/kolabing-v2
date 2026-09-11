<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A community's cover photograph, stored apart from its logo.
 *
 * The app's community page has painted a cover band since IF-31, but there was
 * no column behind it: the band fell back to a blurred copy of `profile_photo`,
 * so every community's "background" was its own logo, and there was no way to
 * set one. This is that missing field — deliberately separate from
 * `profile_photo`, because they are two different pictures doing two different
 * jobs.
 *
 * `text`, matching `profile_photo`: these hold URLs, which outgrow `varchar(255)`
 * on some storage drivers.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('community_profiles', function (Blueprint $table): void {
            $table->text('cover_photo')->nullable()->after('profile_photo');
        });
    }

    public function down(): void
    {
        Schema::table('community_profiles', function (Blueprint $table): void {
            $table->dropColumn('cover_photo');
        });
    }
};
