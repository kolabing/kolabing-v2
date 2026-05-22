<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kolabs', function (Blueprint $table): void {
            $table->string('offer_headline', 50)->nullable()->after('description');
            $table->text('base_offer')->nullable()->after('offer_headline');
            $table->json('negotiation_triggers')->nullable()->after('base_offer');
        });

        DB::table('kolabs')
            ->select(['id', 'intent_type', 'description', 'offer_headline', 'base_offer'])
            ->orderBy('created_at')
            ->get()
            ->each(function (object $kolab): void {
                if (! is_string($kolab->description) || $kolab->description === '') {
                    return;
                }

                if ($kolab->intent_type === 'community_seeking') {
                    return;
                }

                $updates = [];

                if (! is_string($kolab->offer_headline) || $kolab->offer_headline === '') {
                    $updates['offer_headline'] = Str::limit($kolab->description, 50, '');
                }

                if (! is_string($kolab->base_offer) || $kolab->base_offer === '') {
                    $updates['base_offer'] = $kolab->description;
                }

                if ($updates !== []) {
                    DB::table('kolabs')
                        ->where('id', $kolab->id)
                        ->update($updates);
                }
            });
    }

    public function down(): void
    {
        Schema::table('kolabs', function (Blueprint $table): void {
            $table->dropColumn([
                'offer_headline',
                'base_offer',
                'negotiation_triggers',
            ]);
        });
    }
};
