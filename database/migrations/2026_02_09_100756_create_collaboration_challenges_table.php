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
        // Add event_id and qr_code_url to collaborations table
        Schema::table('collaborations', function (Blueprint $table) {
            $table->foreignUuid('event_id')->nullable()->after('contact_methods')->constrained('events')->nullOnDelete();
            $table->text('qr_code_url')->nullable()->after('event_id');
        });

        // Create collaboration_challenges pivot table
        Schema::create('collaboration_challenges', function (Blueprint $table) {
            $table->foreignUuid('collaboration_id')->constrained('collaborations')->cascadeOnDelete();
            $table->foreignUuid('challenge_id')->constrained('challenges')->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['collaboration_id', 'challenge_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('collaboration_challenges');

        Schema::table('collaborations', function (Blueprint $table) {
            $table->dropForeign(['event_id']);
            $table->dropColumn(['event_id', 'qr_code_url']);
        });
    }
};
