<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ChallengeCompletionStatus;
use App\Enums\CommunityMemberStatus;
use App\Models\AttendeeProfile;
use App\Models\ChallengeCompletion;
use App\Models\Community;
use App\Models\CommunityBadgeAward;
use App\Models\CommunityMember;
use App\Models\CommunityPoints;
use App\Models\Event;
use App\Models\Profile;
use App\Support\PublicDisplayName;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LeaderboardService
{
    /**
     * Per-community POINTS leaderboard (the canonical community ranking). Ranks
     * active members by their community_points balance and enriches each row
     * with the member's tier, badge_count, and points. Rows with 0 points are
     * included so a new chapter still renders its roster.
     *
     * @return Collection<int, array{profile_id: string, display_name: string, profile_photo: string|null, points: int, tier: array{id: string, name: string, color: string|null}|null, badge_count: int, rank: int}>
     */
    public function getCommunityPointsLeaderboard(Community $community, int $limit = 50): Collection
    {
        $members = CommunityMember::query()
            ->where('community_id', $community->id)
            ->where('status', CommunityMemberStatus::Active->value)
            // A switched-off or deleted account is invisible everywhere (#258).
            ->whereHas('profile', fn ($profile) => $profile->active())
            ->with(['profile', 'tier'])
            ->get();

        if ($members->isEmpty()) {
            return collect();
        }

        $profileIds = $members->pluck('profile_id')->all();

        $points = CommunityPoints::query()
            ->where('community_id', $community->id)
            ->whereIn('profile_id', $profileIds)
            ->pluck('points', 'profile_id');

        $badgeCounts = CommunityBadgeAward::query()
            ->where('community_id', $community->id)
            ->whereIn('profile_id', $profileIds)
            ->selectRaw('profile_id, COUNT(*) as c')
            ->groupBy('profile_id')
            ->pluck('c', 'profile_id');

        $sorted = $members
            ->sortByDesc(fn (CommunityMember $m): int => (int) ($points[$m->profile_id] ?? 0))
            ->values()
            ->take($limit);

        $rank = 0;
        $previousPoints = null;

        return $sorted->map(function (CommunityMember $member) use ($points, $badgeCounts, &$rank, &$previousPoints): array {
            $memberPoints = (int) ($points[$member->profile_id] ?? 0);

            if ($memberPoints !== $previousPoints) {
                $rank++;
                $previousPoints = $memberPoints;
            }

            return [
                'profile_id' => $member->profile_id,
                'display_name' => PublicDisplayName::for($member->profile),
                'profile_photo' => $member->profile?->avatar_url,
                'points' => $memberPoints,
                'tier' => $member->tier !== null ? [
                    'id' => $member->tier->id,
                    'name' => $member->tier->name,
                    'color' => $member->tier->color,
                ] : null,
                'badge_count' => (int) ($badgeCounts[$member->profile_id] ?? 0),
                'rank' => $rank,
            ];
        });
    }

    /**
     * The authenticated member's row on the community POINTS leaderboard, or
     * null when they are not an active member.
     *
     * @return array{profile_id: string, points: int, rank: int}|null
     */
    public function getMyCommunityPointsRank(Community $community, Profile $profile): ?array
    {
        $isMember = $community->members()
            ->where('profile_id', $profile->id)
            ->where('status', CommunityMemberStatus::Active->value)
            ->exists();

        if (! $isMember) {
            return null;
        }

        $myPoints = (int) (CommunityPoints::query()
            ->where('community_id', $community->id)
            ->where('profile_id', $profile->id)
            ->value('points') ?? 0);

        $memberIds = $this->activeMemberIds($community);

        $rank = CommunityPoints::query()
            ->where('community_id', $community->id)
            ->whereIn('profile_id', $memberIds)
            ->where('points', '>', $myPoints)
            ->count() + 1;

        return [
            'profile_id' => $profile->id,
            'points' => $myPoints,
            'rank' => $rank,
        ];
    }

    /**
     * Get the event leaderboard by aggregating verified challenge completion points.
     *
     * @return Collection<int, array{profile_id: string, display_name: string, profile_photo: string|null, total_points: int, rank: int}>
     */
    public function getEventLeaderboard(Event $event, int $limit = 50): Collection
    {
        $rows = ChallengeCompletion::query()
            ->select([
                'challenger_profile_id as profile_id',
                DB::raw('SUM(points_earned) as total_points'),
            ])
            ->where('event_id', $event->id)
            ->where('status', ChallengeCompletionStatus::Verified->value)
            ->groupBy('challenger_profile_id')
            ->orderByDesc('total_points')
            ->limit($limit)
            ->get();

        $profileIds = $rows->pluck('profile_id')->all();
        $profiles = Profile::query()
            ->whereIn('id', $profileIds)
            ->get()
            ->keyBy('id');

        $rank = 0;
        $previousPoints = null;

        return $rows->map(function ($row) use ($profiles, &$rank, &$previousPoints): array {
            $profile = $profiles->get($row->profile_id);

            if ((int) $row->total_points !== $previousPoints) {
                $rank++;
                $previousPoints = (int) $row->total_points;
            }

            return [
                'profile_id' => $row->profile_id,
                'display_name' => PublicDisplayName::for($profile),
                'profile_photo' => $profile?->avatar_url,
                'total_points' => (int) $row->total_points,
                'rank' => $rank,
            ];
        });
    }

    /**
     * Get the global leaderboard from attendee profiles with total_points > 0.
     *
     * @return Collection<int, array{profile_id: string, display_name: string, profile_photo: string|null, total_points: int, rank: int}>
     */
    public function getGlobalLeaderboard(int $limit = 50): Collection
    {
        $attendeeProfiles = AttendeeProfile::query()
            ->where('total_points', '>', 0)
            ->whereHas('profile', fn ($q) => $q->active())
            ->orderByDesc('total_points')
            ->limit($limit)
            ->with('profile')
            ->get();

        $rank = 0;
        $previousPoints = null;

        return $attendeeProfiles->map(function (AttendeeProfile $ap) use (&$rank, &$previousPoints): array {
            if ($ap->total_points !== $previousPoints) {
                $rank++;
                $previousPoints = $ap->total_points;
            }

            return [
                'profile_id' => $ap->profile_id,
                'display_name' => PublicDisplayName::for($ap->profile),
                'profile_photo' => $ap->profile?->avatar_url,
                'total_points' => $ap->total_points,
                'rank' => $rank,
            ];
        });
    }

    /**
     * Chapter-scoped leaderboard: the global leaderboard filtered to the
     * active members of one community (NF-6 member surface).
     *
     * @return Collection<int, array{profile_id: string, display_name: string, profile_photo: string|null, total_points: int, rank: int}>
     */
    public function getCommunityLeaderboard(Community $community, int $limit = 50): Collection
    {
        $memberIds = $this->activeMemberIds($community);

        if ($memberIds === []) {
            return collect();
        }

        $attendeeProfiles = AttendeeProfile::query()
            ->whereIn('profile_id', $memberIds)
            ->where('total_points', '>', 0)
            ->whereHas('profile', fn ($q) => $q->active())
            ->orderByDesc('total_points')
            ->limit($limit)
            ->with('profile')
            ->get();

        $rank = 0;
        $previousPoints = null;

        return $attendeeProfiles->map(function (AttendeeProfile $ap) use (&$rank, &$previousPoints): array {
            if ($ap->total_points !== $previousPoints) {
                $rank++;
                $previousPoints = $ap->total_points;
            }

            return [
                'profile_id' => $ap->profile_id,
                'display_name' => PublicDisplayName::for($ap->profile),
                'profile_photo' => $ap->profile?->avatar_url,
                'total_points' => $ap->total_points,
                'rank' => $rank,
            ];
        });
    }

    /**
     * The authenticated user's rank within one community. Null if they are not
     * an active member or have no points.
     *
     * @return array{profile_id: string, total_points: int, rank: int}|null
     */
    public function getMyCommunityRank(Community $community, Profile $profile): ?array
    {
        $isMember = $community->members()
            ->where('profile_id', $profile->id)
            ->where('status', CommunityMemberStatus::Active->value)
            ->exists();

        $attendeeProfile = $profile->attendeeProfile;

        if (! $isMember || ! $attendeeProfile || $attendeeProfile->total_points === 0) {
            return null;
        }

        $rank = AttendeeProfile::query()
            ->whereIn('profile_id', $this->activeMemberIds($community))
            ->where('total_points', '>', $attendeeProfile->total_points)
            ->count() + 1;

        return [
            'profile_id' => $profile->id,
            'total_points' => $attendeeProfile->total_points,
            'rank' => $rank,
        ];
    }

    /**
     * Weekly check-ins leaderboard (NF-7): ranks a community's active members
     * by verified event check-ins within the current calendar week
     * (Europe/Madrid, Monday to Sunday) — distinct check-ins per event, so one
     * event cannot be farmed for extra rank. Rows with 0 check-ins this week
     * are omitted; a quiet week renders an empty board, not a zeroed roster.
     *
     * @return Collection<int, array{profile_id: string, display_name: string, profile_photo: string|null, checkins: int, rank: int}>
     */
    public function getCommunityWeeklyLeaderboard(Community $community, int $limit = 50): Collection
    {
        $memberIds = $this->activeMemberIds($community);

        if ($memberIds === []) {
            return collect();
        }

        [$start, $end] = $this->weekBounds();

        $rows = DB::table('event_checkins')
            ->join('events', 'events.id', '=', 'event_checkins.event_id')
            ->where('events.community_id', $community->id)
            ->whereIn('event_checkins.profile_id', $memberIds)
            ->whereBetween('event_checkins.checked_in_at', [$start, $end])
            ->selectRaw('event_checkins.profile_id, COUNT(DISTINCT event_checkins.event_id) as checkins')
            ->groupBy('event_checkins.profile_id')
            ->orderByDesc('checkins')
            ->limit($limit)
            ->get();

        if ($rows->isEmpty()) {
            return collect();
        }

        $profiles = Profile::query()
            ->whereIn('id', $rows->pluck('profile_id'))
            ->get()
            ->keyBy('id');

        $rank = 0;
        $previousCheckins = null;

        return $rows->map(function ($row) use ($profiles, &$rank, &$previousCheckins): array {
            $profile = $profiles->get($row->profile_id);
            $checkins = (int) $row->checkins;

            if ($checkins !== $previousCheckins) {
                $rank++;
                $previousCheckins = $checkins;
            }

            return [
                'profile_id' => $row->profile_id,
                'display_name' => PublicDisplayName::for($profile),
                'profile_photo' => $profile?->avatar_url,
                'checkins' => $checkins,
                'rank' => $rank,
            ];
        });
    }

    /**
     * The authenticated member's row on the weekly check-ins leaderboard, or
     * null when they are not an active member or have no check-ins this week.
     *
     * @return array{profile_id: string, checkins: int, rank: int}|null
     */
    public function getMyCommunityWeeklyRank(Community $community, Profile $profile): ?array
    {
        $isMember = $community->members()
            ->where('profile_id', $profile->id)
            ->where('status', CommunityMemberStatus::Active->value)
            ->exists();

        if (! $isMember) {
            return null;
        }

        [$start, $end] = $this->weekBounds();

        $myCheckins = (int) DB::table('event_checkins')
            ->join('events', 'events.id', '=', 'event_checkins.event_id')
            ->where('events.community_id', $community->id)
            ->where('event_checkins.profile_id', $profile->id)
            ->whereBetween('event_checkins.checked_in_at', [$start, $end])
            ->distinct('event_checkins.event_id')
            ->count('event_checkins.event_id');

        if ($myCheckins === 0) {
            return null;
        }

        $memberIds = $this->activeMemberIds($community);

        $ahead = DB::table('event_checkins')
            ->join('events', 'events.id', '=', 'event_checkins.event_id')
            ->where('events.community_id', $community->id)
            ->whereIn('event_checkins.profile_id', $memberIds)
            ->whereBetween('event_checkins.checked_in_at', [$start, $end])
            ->selectRaw('event_checkins.profile_id, COUNT(DISTINCT event_checkins.event_id) as checkins')
            ->groupBy('event_checkins.profile_id')
            ->havingRaw('COUNT(DISTINCT event_checkins.event_id) > ?', [$myCheckins])
            ->get()
            ->count();

        return [
            'profile_id' => $profile->id,
            'checkins' => $myCheckins,
            'rank' => $ahead + 1,
        ];
    }

    /**
     * Current calendar week bounds, Monday 00:00 to Sunday 23:59:59.999,
     * Europe/Madrid — matching the house convention for incentives windows
     * (see Incentives\CityLeagueService::monthBounds).
     *
     * @return array{0: \Illuminate\Support\Carbon, 1: \Illuminate\Support\Carbon}
     */
    private function weekBounds(): array
    {
        $now = now()->setTimezone('Europe/Madrid');

        return [
            $now->copy()->startOfWeek()->utc(),
            $now->copy()->endOfWeek()->utc(),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function activeMemberIds(Community $community): array
    {
        return CommunityMember::query()
            ->where('community_id', $community->id)
            ->where('status', CommunityMemberStatus::Active->value)
            ->whereHas('profile', fn ($profile) => $profile->active())
            ->pluck('profile_id')
            ->all();
    }

    /**
     * Get the authenticated user's rank within a specific event.
     *
     * @return array{profile_id: string, total_points: int, rank: int}|null
     */
    public function getMyEventRank(Event $event, Profile $profile): ?array
    {
        $myPoints = (int) ChallengeCompletion::query()
            ->where('event_id', $event->id)
            ->where('challenger_profile_id', $profile->id)
            ->where('status', ChallengeCompletionStatus::Verified->value)
            ->sum('points_earned');

        if ($myPoints === 0) {
            return null;
        }

        $rank = ChallengeCompletion::query()
            ->select('challenger_profile_id')
            ->where('event_id', $event->id)
            ->where('status', ChallengeCompletionStatus::Verified->value)
            ->groupBy('challenger_profile_id')
            ->havingRaw('SUM(points_earned) > ?', [$myPoints])
            ->get()
            ->count() + 1;

        return [
            'profile_id' => $profile->id,
            'total_points' => $myPoints,
            'rank' => $rank,
        ];
    }

    /**
     * Get the authenticated user's global rank from attendee_profiles.
     *
     * @return array{profile_id: string, total_points: int, rank: int}|null
     */
    public function getMyGlobalRank(Profile $profile): ?array
    {
        $attendeeProfile = $profile->attendeeProfile;

        if (! $attendeeProfile || $attendeeProfile->total_points === 0) {
            return null;
        }

        $rank = AttendeeProfile::query()
            ->where('total_points', '>', $attendeeProfile->total_points)
            ->count() + 1;

        return [
            'profile_id' => $profile->id,
            'total_points' => $attendeeProfile->total_points,
            'rank' => $rank,
        ];
    }
}
