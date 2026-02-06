<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Profile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class GamificationStatsService
{
    /**
     * Get gamification stats for a profile.
     *
     * @return array{total_points: int, total_challenges_completed: int, total_events_attended: int, global_rank: int|null, badges_count: int, rewards_count: int}
     */
    public function getStats(Profile $profile): array
    {
        $ap = $profile->attendeeProfile;

        if (! $ap) {
            return [
                'total_points' => 0,
                'total_challenges_completed' => 0,
                'total_events_attended' => 0,
                'global_rank' => null,
                'badges_count' => 0,
                'rewards_count' => 0,
            ];
        }

        return [
            'total_points' => $ap->total_points,
            'total_challenges_completed' => $ap->total_challenges_completed,
            'total_events_attended' => $ap->total_events_attended,
            'global_rank' => $ap->global_rank,
            'badges_count' => $this->getBadgesCount($profile),
            'rewards_count' => $profile->rewardClaims()->count(),
        ];
    }

    /**
     * Get the game card view for a profile (public view).
     *
     * @return array{profile: array<string, mixed>, stats: array<string, mixed>, recent_badges: Collection<int, mixed>}
     */
    public function getGameCard(Profile $profile): array
    {
        $stats = $this->getStats($profile);

        $recentBadges = $this->getRecentBadges($profile);

        return [
            'profile' => [
                'id' => $profile->id,
                'email' => $profile->email,
                'avatar_url' => $profile->avatar_url,
                'user_type' => $profile->user_type->value,
            ],
            'stats' => $stats,
            'recent_badges' => $recentBadges,
        ];
    }

    /**
     * Get badge count for a profile, handling the case where BadgeAward table may not exist yet.
     */
    private function getBadgesCount(Profile $profile): int
    {
        if (! Schema::hasTable('badge_awards')) {
            return 0;
        }

        return \App\Models\BadgeAward::query()
            ->where('profile_id', $profile->id)
            ->count();
    }

    /**
     * Get recent badges for a profile, handling the case where BadgeAward table may not exist yet.
     *
     * @return Collection<int, mixed>
     */
    private function getRecentBadges(Profile $profile): Collection
    {
        if (! Schema::hasTable('badge_awards')) {
            return collect();
        }

        return \App\Models\BadgeAward::query()
            ->where('profile_id', $profile->id)
            ->with('badge')
            ->orderByDesc('awarded_at')
            ->limit(5)
            ->get();
    }
}
