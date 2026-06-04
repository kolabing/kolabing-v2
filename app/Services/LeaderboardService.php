<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ChallengeCompletionStatus;
use App\Enums\CommunityMemberStatus;
use App\Models\AttendeeProfile;
use App\Models\ChallengeCompletion;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Event;
use App\Models\Profile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LeaderboardService
{
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
                'display_name' => $profile?->email ?? 'Unknown',
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
                'display_name' => $ap->profile?->email ?? 'Unknown',
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
                'display_name' => $ap->profile?->email ?? 'Unknown',
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
     * @return array<int, string>
     */
    private function activeMemberIds(Community $community): array
    {
        return CommunityMember::query()
            ->where('community_id', $community->id)
            ->where('status', CommunityMemberStatus::Active->value)
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
