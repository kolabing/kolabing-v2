<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kolabs', function (Blueprint $table): void {
            $table->foreignUuid('recipient_community_id')
                ->nullable()
                ->after('creator_profile_id')
                ->constrained('profiles')
                ->nullOnDelete();
        });

        Schema::table('collab_opportunities', function (Blueprint $table): void {
            $table->foreignUuid('recipient_community_id')
                ->nullable()
                ->after('creator_profile_id')
                ->constrained('profiles')
                ->nullOnDelete();
            $table->string('offer_headline', 50)->nullable()->after('description');
            $table->text('base_offer')->nullable()->after('offer_headline');
            $table->json('negotiation_triggers')->nullable()->after('base_offer');
        });

        DB::table('collab_opportunities')
            ->select('id')
            ->orderBy('id')
            ->get()
            ->each(function (object $opportunity): void {
                $kolab = DB::table('kolabs')
                    ->select([
                        'recipient_community_id',
                        'offer_headline',
                        'base_offer',
                        'negotiation_triggers',
                    ])
                    ->where('id', $opportunity->id)
                    ->first();

                if ($kolab === null) {
                    return;
                }

                DB::table('collab_opportunities')
                    ->where('id', $opportunity->id)
                    ->update([
                        'recipient_community_id' => $kolab->recipient_community_id,
                        'offer_headline' => $kolab->offer_headline,
                        'base_offer' => $kolab->base_offer,
                        'negotiation_triggers' => $kolab->negotiation_triggers,
                    ]);
            });
    }

    public function down(): void
    {
        Schema::table('collab_opportunities', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('recipient_community_id');
            $table->dropColumn([
                'offer_headline',
                'base_offer',
                'negotiation_triggers',
            ]);
        });

        Schema::table('kolabs', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('recipient_community_id');
        });
    }
};
