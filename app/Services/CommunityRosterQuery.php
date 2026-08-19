<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CommunityMemberStatus;
use App\Models\Community;
use App\Models\CommunityMember;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The community roster query — search, filters, sort, and each member's
 * engagement metrics.
 *
 * Metrics are resolved with LEFT-JOINed aggregate subqueries so one page costs
 * a fixed number of queries regardless of member count (BACKLOG BE-NF-15 flags
 * the O(N)-per-row pattern this class exists to avoid). Grouped aggregates are
 * the documented exception to the "prefer Model::query() over DB::" rule —
 * Eloquent cannot express them without a correlated subquery per row.
 */
class CommunityRosterQuery
{
    /** Accepted sort keys → the column they order by. */
    private const SORTS = [
        'joined_at' => 'community_members.joined_at',
        'name' => 'display_name_value',
        'points' => 'points_value',
        'events_attended' => 'events_attended_value',
        'last_active_at' => 'last_active_value',
        'tier' => 'tier_rank_value',
    ];

    /** Metric sorts read best highest-first. */
    private const DESC_BY_DEFAULT = ['points', 'events_attended', 'last_active_at', 'tier'];

    /**
     * @param  array{search?: string|null, status?: string|null, tier_id?: string|null, can_manage?: bool|null, sort?: string|null, direction?: string|null}  $filters
     */
    public function paginate(Community $community, array $filters = [], int $perPage = 25): LengthAwarePaginator
    {
        $query = $this->base($community);

        $this->applyStatus($query, $filters['status'] ?? null);
        $this->applySearch($query, $filters['search'] ?? null);
        $this->applyTier($query, $filters['tier_id'] ?? null);
        $this->applyCanManage($query, $filters['can_manage'] ?? null);
        $this->applySort($query, $filters['sort'] ?? null, $filters['direction'] ?? null);

        return $query->paginate($perPage);
    }

    /**
     * Base query: the membership rows plus every column the filters, the sorts
     * and the resource need, joined once.
     */
    public function base(Community $community): Builder
    {
        $points = DB::table('community_points')
            ->select('profile_id', 'points')
            ->where('community_id', $community->id);

        $checkins = DB::table('event_checkins')
            ->join('events', 'events.id', '=', 'event_checkins.event_id')
            ->where('events.community_id', $community->id)
            ->groupBy('event_checkins.profile_id')
            ->select('event_checkins.profile_id', DB::raw('COUNT(*) as events_attended'));

        $activity = DB::table('community_point_ledger')
            ->where('community_id', $community->id)
            ->groupBy('profile_id')
            ->select('profile_id', DB::raw('MAX(created_at) as last_ledger_at'));

        // Built from the model rather than $community->members(), because a
        // HasMany proxies these calls back to itself and would not satisfy the
        // Builder return type.
        return CommunityMember::query()
            ->where('community_members.community_id', $community->id)
            ->join('profiles', 'profiles.id', '=', 'community_members.profile_id')
            ->leftJoin('business_profiles', 'business_profiles.profile_id', '=', 'profiles.id')
            ->leftJoin('community_profiles', 'community_profiles.profile_id', '=', 'profiles.id')
            ->leftJoin('community_tiers', 'community_tiers.id', '=', 'community_members.tier_id')
            ->leftJoinSub($points, 'cp', 'cp.profile_id', '=', 'community_members.profile_id')
            ->leftJoinSub($checkins, 'ec', 'ec.profile_id', '=', 'community_members.profile_id')
            ->leftJoinSub($activity, 'al', 'al.profile_id', '=', 'community_members.profile_id')
            ->with([
                'tier',
                'profile.attendeeProfile',
                'profile.businessProfile',
                'profile.communityProfile',
            ])
            ->select('community_members.*')
            ->selectRaw('COALESCE(cp.points, 0) as points_value')
            ->selectRaw('COALESCE(ec.events_attended, 0) as events_attended_value')
            ->selectRaw('COALESCE(al.last_ledger_at, community_members.joined_at) as last_active_value')
            ->selectRaw('COALESCE(community_tiers.rank, 0) as tier_rank_value')
            ->selectRaw(
                "COALESCE(NULLIF(profiles.name, ''), NULLIF(business_profiles.name, ''), "
                ."NULLIF(community_profiles.name, ''), profiles.email) as display_name_value"
            );
    }

    /**
     * A soft-removed member is not a member. The default set is
     * active + inactive; ?status=all restores the pre-fix behaviour.
     */
    private function applyStatus(Builder $query, ?string $status): void
    {
        if ($status === 'all') {
            return;
        }

        if ($status !== null && in_array($status, CommunityMemberStatus::values(), true)) {
            $query->where('community_members.status', $status);

            return;
        }

        $query->whereIn('community_members.status', [
            CommunityMemberStatus::Active->value,
            CommunityMemberStatus::Inactive->value,
        ]);
    }

    private function applySearch(Builder $query, ?string $search): void
    {
        $search = is_string($search) ? trim($search) : '';

        if ($search === '') {
            return;
        }

        // A pasted @handle searches the handle without its marker.
        $needle = '%'.mb_strtolower(ltrim($search, '@')).'%';

        $query->where(function (Builder $inner) use ($needle): void {
            $inner->whereRaw('LOWER(profiles.email) LIKE ?', [$needle])
                ->orWhereRaw("LOWER(COALESCE(profiles.handle, '')) LIKE ?", [$needle])
                ->orWhereRaw("LOWER(COALESCE(profiles.name, '')) LIKE ?", [$needle])
                ->orWhereRaw("LOWER(COALESCE(business_profiles.name, '')) LIKE ?", [$needle])
                ->orWhereRaw("LOWER(COALESCE(community_profiles.name, '')) LIKE ?", [$needle]);
        });
    }

    private function applyTier(Builder $query, ?string $tierId): void
    {
        if ($tierId === null || $tierId === '') {
            return;
        }

        if ($tierId === 'none') {
            $query->whereNull('community_members.tier_id');

            return;
        }

        $query->where('community_members.tier_id', $tierId);
    }

    private function applyCanManage(Builder $query, ?bool $canManage): void
    {
        if ($canManage === null) {
            return;
        }

        $query->where('community_members.can_manage', $canManage);
    }

    /**
     * An unknown sort key falls back to joined_at rather than reaching the
     * database — the key is never interpolated, only looked up in self::SORTS.
     */
    private function applySort(Builder $query, ?string $sort, ?string $direction): void
    {
        $key = array_key_exists((string) $sort, self::SORTS) ? (string) $sort : 'joined_at';

        $direction = in_array($direction, ['asc', 'desc'], true)
            ? $direction
            : (in_array($key, self::DESC_BY_DEFAULT, true) ? 'desc' : 'asc');

        $query->orderBy(self::SORTS[$key], $direction)
            // Stable pagination: ties must not reshuffle between pages.
            ->orderBy('community_members.id');
    }
}
