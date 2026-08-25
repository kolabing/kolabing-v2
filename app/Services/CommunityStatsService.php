<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CommunityInvitationStatus;
use App\Enums\CommunityMemberStatus;
use App\Enums\JoinRequestStatus;
use App\Models\Community;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The aggregate figures behind GET /communities/{community}/stats — the
 * Community Hub's health strip.
 *
 * Deliberately a fixed set of aggregates, not a general analytics engine:
 * every figure is one grouped query and none of them iterate members.
 */
class CommunityStatsService
{
    private const DORMANT_DAYS = 30;

    /**
     * @return array<string, mixed>
     */
    public function forCommunity(Community $community): array
    {
        $window = now()->subDays(self::DORMANT_DAYS);

        return [
            'members' => $this->members($community, $window),
            'pending' => $this->pending($community),
            'tiers' => $this->tiers($community),
            'engagement' => $this->engagement($community, $window),
            'top_members' => $this->topMembers($community),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function members(Community $community, DateTimeInterface $window): array
    {
        $byStatus = DB::table('community_members')
            ->where('community_id', $community->id)
            ->groupBy('status')
            ->selectRaw('status, COUNT(*) as aggregate')
            ->pluck('aggregate', 'status');

        $active = (int) ($byStatus[CommunityMemberStatus::Active->value] ?? 0);
        $inactive = (int) ($byStatus[CommunityMemberStatus::Inactive->value] ?? 0);
        $removed = (int) ($byStatus[CommunityMemberStatus::Removed->value] ?? 0);

        $newThisMonth = DB::table('community_members')
            ->where('community_id', $community->id)
            ->where('joined_at', '>=', now()->startOfMonth())
            ->count();

        // Dormant: active members with no community_point_ledger row in the
        // window. The ledger is written on check-in, goal completion, challenge
        // verification and redemption, so it is the activity spine.
        $dormant = DB::table('community_members')
            ->where('community_members.community_id', $community->id)
            ->where('community_members.status', CommunityMemberStatus::Active->value)
            ->whereNotExists(function ($sub) use ($community, $window): void {
                $sub->select(DB::raw(1))
                    ->from('community_point_ledger')
                    ->whereColumn('community_point_ledger.profile_id', 'community_members.profile_id')
                    ->where('community_point_ledger.community_id', $community->id)
                    ->where('community_point_ledger.created_at', '>=', $window);
            })
            ->count();

        return [
            'total' => $active + $inactive + $removed,
            'active' => $active,
            'inactive' => $inactive,
            'removed' => $removed,
            'new_this_month' => $newThisMonth,
            'dormant_30d' => $dormant,
        ];
    }

    /**
     * @return array<string, int>
     */
    private function pending(Community $community): array
    {
        return [
            'join_requests' => DB::table('community_join_requests')
                ->where('community_id', $community->id)
                ->where('status', JoinRequestStatus::Pending->value)
                ->count(),
            'invitations' => DB::table('community_invitations')
                ->where('community_id', $community->id)
                ->where('status', CommunityInvitationStatus::Pending->value)
                ->where('expires_at', '>', now())
                ->count(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function tiers(Community $community): array
    {
        $counts = DB::table('community_members')
            ->where('community_id', $community->id)
            ->where('status', CommunityMemberStatus::Active->value)
            ->whereNotNull('tier_id')
            ->groupBy('tier_id')
            ->selectRaw('tier_id, COUNT(*) as aggregate')
            ->pluck('aggregate', 'tier_id');

        return $community->tiers()
            ->orderByDesc('rank')
            ->get()
            ->map(fn ($tier): array => [
                'tier_id' => $tier->id,
                'name' => $tier->name,
                'color' => $tier->color,
                'rank' => $tier->rank,
                'member_count' => (int) ($counts[$tier->id] ?? 0),
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function engagement(Community $community, DateTimeInterface $window): array
    {
        $pointsIssued = (int) DB::table('community_point_ledger')
            ->where('community_id', $community->id)
            ->where('created_at', '>=', $window)
            ->where('points', '>', 0)
            ->sum('points');

        // events.event_date is a DATE column — compare against a date string so
        // the driver does not do a lexical comparison against a datetime.
        $eventIds = DB::table('events')
            ->where('community_id', $community->id)
            ->where('event_date', '>=', $window->format('Y-m-d'))
            ->pluck('id');

        $checkins = $eventIds->isEmpty() ? 0 : DB::table('event_checkins')
            ->whereIn('event_id', $eventIds)
            ->count();

        $distinctAttendees = $eventIds->isEmpty() ? 0 : DB::table('event_checkins')
            ->whereIn('event_id', $eventIds)
            ->distinct()
            ->count('profile_id');

        $activeMembers = DB::table('community_members')
            ->where('community_id', $community->id)
            ->where('status', CommunityMemberStatus::Active->value)
            ->count();

        // No events in the window, or no members: report 0. Never divide by
        // zero, and never imply full attendance at nothing.
        $rate = ($eventIds->isEmpty() || $activeMembers === 0)
            ? 0.0
            : round($distinctAttendees / $activeMembers, 2);

        return [
            'points_issued_30d' => $pointsIssued,
            'checkins_30d' => $checkins,
            'events_30d' => $eventIds->count(),
            'attendance_rate_30d' => $rate,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function topMembers(Community $community): array
    {
        return DB::table('community_points')
            ->join('profiles', 'profiles.id', '=', 'community_points.profile_id')
            ->join('community_members', function ($join) use ($community): void {
                $join->on('community_members.profile_id', '=', 'community_points.profile_id')
                    ->where('community_members.community_id', '=', $community->id);
            })
            ->where('community_points.community_id', $community->id)
            ->where('community_members.status', CommunityMemberStatus::Active->value)
            ->orderByDesc('community_points.points')
            ->limit(5)
            ->get([
                'profiles.id as profile_id',
                'profiles.name as name',
                'profiles.email as email',
                'profiles.avatar_url as avatar_url',
                'community_points.points as points',
            ])
            ->map(fn ($row): array => [
                'profile_id' => $row->profile_id,
                'name' => $row->name ?: Str::before((string) $row->email, '@'),
                'avatar_url' => $row->avatar_url,
                'points' => (int) $row->points,
            ])
            ->all();
    }
}
