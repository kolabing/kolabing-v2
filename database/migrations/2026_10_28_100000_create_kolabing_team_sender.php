<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kolabing speaking as itself (SERR-658, agreed on SERR-656 by Daniel).
     *
     * - chat_threads: one `kolabing_team` thread per profile (`team_profile_id`),
     *   plus `admin_last_read_at` so the admin inbox can show what is unanswered.
     * - chat_messages: a message can come from the team. `sender_profile_id`
     *   becomes nullable (a maintainer is a `users` row with no profile; a fake
     *   Kolabing profile would leak into Explore, the directory and the stats),
     *   `sender_type` says which kind, `sender_user_id` records the maintainer.
     * - kolabing_team_offers: one row per offer card, the unit of "one approved
     *   row = one send". `dedupe_key` is the "one row per recipient per offer"
     *   rule; (recipient_profile_id, sent_at) serves the weekly cap.
     */
    public function up(): void
    {
        Schema::table('chat_threads', function (Blueprint $table): void {
            $table->foreignUuid('team_profile_id')->nullable()->after('event_id')
                ->constrained('profiles')->cascadeOnDelete();
            $table->timestamp('admin_last_read_at')->nullable()->after('last_message_at');
            $table->unique('team_profile_id');
        });

        Schema::create('kolabing_team_offers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            // community_apply: a community is offered an open business Kolab to apply to.
            // business_propose: a business is offered a direct proposal to a named community.
            $table->string('kind', 32);
            // draft -> sent -> accepted | declined ; draft -> cancelled ; draft -> failed
            $table->string('status', 16)->default('draft');
            $table->foreignUuid('recipient_profile_id')->constrained('profiles')->cascadeOnDelete();
            $table->foreignUuid('kolab_id')->nullable()->constrained('kolabs')->nullOnDelete();
            $table->foreignUuid('community_profile_id')->nullable()->constrained('profiles')->nullOnDelete();
            $table->string('title');
            $table->text('message')->nullable();
            $table->string('what_business_gets', 500)->nullable();
            $table->string('what_community_gets', 500)->nullable();
            $table->date('proposed_date')->nullable();
            // thread (in-app) or the fallback for accounts that never logged in.
            // A test send may only go to a test account, and only then (see KolabingTeamService).
            $table->boolean('is_test_send')->default(false);
            $table->string('channel', 16)->default('thread');
            $table->string('dedupe_key')->unique();
            $table->foreignId('created_by_admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('sent_by_admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('chat_message_id')->nullable();
            $table->uuid('notification_id')->nullable();
            $table->foreignUuid('result_kolab_id')->nullable()->constrained('kolabs')->nullOnDelete();
            $table->foreignUuid('result_application_id')->nullable()->constrained('applications')->nullOnDelete();
            $table->text('failure_reason')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->index(['recipient_profile_id', 'sent_at']);
            $table->index('status');
        });

        Schema::table('chat_messages', function (Blueprint $table): void {
            $table->uuid('sender_profile_id')->nullable()->change();
            $table->string('sender_type', 16)->default('profile')->after('sender_profile_id');
            $table->foreignId('sender_user_id')->nullable()->after('sender_type')
                ->constrained('users')->nullOnDelete();
            $table->foreignUuid('team_offer_id')->nullable()->after('sender_user_id')
                ->constrained('kolabing_team_offers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('team_offer_id');
            $table->dropConstrainedForeignId('sender_user_id');
            $table->dropColumn('sender_type');
        });

        Schema::dropIfExists('kolabing_team_offers');

        Schema::table('chat_threads', function (Blueprint $table): void {
            $table->dropUnique(['team_profile_id']);
            $table->dropConstrainedForeignId('team_profile_id');
            $table->dropColumn('admin_last_read_at');
        });
    }
};
