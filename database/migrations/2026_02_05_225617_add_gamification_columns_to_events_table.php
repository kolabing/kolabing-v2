<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->decimal('location_lat', 10, 7)->nullable()->after('attendee_count');
            $table->decimal('location_lng', 10, 7)->nullable()->after('location_lat');
            $table->string('address', 255)->nullable()->after('location_lng');
            $table->unsignedInteger('max_challenges_per_attendee')->default(10)->after('address');
            $table->boolean('is_active')->default(false)->after('max_challenges_per_attendee');
            $table->string('checkin_token', 64)->nullable()->unique()->after('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropUnique(['checkin_token']);
            $table->dropColumn([
                'location_lat', 'location_lng', 'address',
                'max_challenges_per_attendee', 'is_active', 'checkin_token',
            ]);
        });
    }
};
