<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * profiles.avatar_url was never populated from business_profiles.profile_photo
     * or community_profiles.profile_photo before the model-level sync was added
     * (see BusinessProfile::booted / CommunityProfile::booted). Surfaces such as
     * the discovery feed's cover-photo fallback read profiles.avatar_url, so
     * existing rows with a photo set on the extended profile but a null/stale
     * profiles.avatar_url must be backfilled once. Done row-by-row (not a join
     * update) to stay portable across the sqlite test driver and Postgres.
     */
    public function up(): void
    {
        DB::table('profiles')
            ->join('business_profiles', 'business_profiles.profile_id', '=', 'profiles.id')
            ->whereNotNull('business_profiles.profile_photo')
            ->where('business_profiles.profile_photo', '!=', '')
            ->select(['profiles.id', 'business_profiles.profile_photo'])
            ->get()
            ->each(function (object $row): void {
                DB::table('profiles')->where('id', $row->id)->update([
                    'avatar_url' => $row->profile_photo,
                ]);
            });

        DB::table('profiles')
            ->join('community_profiles', 'community_profiles.profile_id', '=', 'profiles.id')
            ->whereNotNull('community_profiles.profile_photo')
            ->where('community_profiles.profile_photo', '!=', '')
            ->select(['profiles.id', 'community_profiles.profile_photo'])
            ->get()
            ->each(function (object $row): void {
                DB::table('profiles')->where('id', $row->id)->update([
                    'avatar_url' => $row->profile_photo,
                ]);
            });
    }

    public function down(): void
    {
        // No-op. This migration corrects stale denormalized data; reverting
        // would mean discarding correct avatar_url values with no way to
        // recover the prior (broken) state.
    }
};
