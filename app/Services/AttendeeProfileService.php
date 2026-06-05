<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CommunityMemberStatus;
use App\Models\EventCheckin;
use App\Models\Profile;
use App\Models\XpLevel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only aggregator for the attendee public-profile surface (Batch 4).
 *
 * NEVER gates on the business paywall: attendees are free (see
 * docs/ROLES-AND-PERMISSIONS.md §7). This service only reads the existing
 * gamification + membership data.
 */
class AttendeeProfileService
{
    /**
     * The default page size for the events-attended history list.
     */
    private const HISTORY_PER_PAGE = 15;

    /**
     * The number of most-recent events embedded in the aggregate profile.
     */
    private const RECENT_EVENTS = 5;

    /**
     * Build the public attendee-profile aggregate.
     *
     * @return array{
     *     identity: array{id: string, name: string|null, avatar_url: string|null},
     *     gamification: array{points: int, level: array{number: int, title: string, min_xp: int, max_xp: int|null, color: string}|null, badges: array{count: int, items: array<int, array{slug: string, earned_at: string|null}>}},
     *     communities: array<int, array{id: string, name: string, slug: string, avatar_url: string|null, tier_name: string|null, can_manage: bool}>,
     *     events_attended: array{total: int, recent: array<int, array{event_id: string, event_name: string, event_date: string|null, checked_in_at: string|null, community: array{id: string, name: string}|null}>},
     *     friends_count: int|null
     * }
     */
    public function buildPublicProfile(Profile $profile): array
    {
        $attendeeProfile = $profile->attendeeProfile;
        $points = $attendeeProfile?->total_points ?? 0;

        return [
            'identity' => [
                'id' => $profile->id,
                'name' => $this->resolveName($profile),
                'avatar_url' => $profile->avatar_url,
            ],
            'gamification' => [
                'points' => $points,
                'level' => $this->resolveLevel($points),
                'badges' => $this->resolveBadges($profile),
            ],
            'communities' => $this->resolveCommunities($profile),
            'events_attended' => [
                'total' => $attendeeProfile?->total_events_attended
                    ?? $this->checkinQuery($profile)->count(),
                'recent' => $this->resolveRecentEvents($profile),
            ],
            'friends_count' => $this->resolveFriendsCount($profile),
        ];
    }

    /**
     * Paginated history of the events an attendee has attended (check-ins).
     *
     * @return LengthAwarePaginator<int, EventCheckin>
     */
    public function paginateEventsAttended(Profile $profile, int $perPage = self::HISTORY_PER_PAGE): LengthAwarePaginator
    {
        return $this->checkinQuery($profile)
            ->with(['event:id,name,event_date,community_id', 'event.community:id,name'])
            ->orderByDesc('checked_in_at')
            ->paginate($perPage);
    }

    /**
     * Resolve the highest XP level the given point total satisfies.
     *
     * Returns null when no `xp_levels` rows exist (level rule not configured) so
     * the caller can fall back to points-only.
     *
     * @return array{number: int, title: string, min_xp: int, max_xp: int|null, color: string}|null
     */
    public function resolveLevel(int $points): ?array
    {
        if (! Schema::hasTable('xp_levels')) {
            return null;
        }

        $level = XpLevel::query()
            ->where('min_xp', '<=', $points)
            ->orderByDesc('min_xp')
            ->orderByDesc('number')
            ->first();

        if ($level === null) {
            return null;
        }

        return [
            'number' => $level->number,
            'title' => $level->title,
            'min_xp' => $level->min_xp,
            'max_xp' => $level->max_xp,
            'color' => $level->color,
        ];
    }

    /**
     * @return array{count: int, items: array<int, array{slug: string, earned_at: string|null}>}
     */
    private function resolveBadges(Profile $profile): array
    {
        if (! Schema::hasTable('earned_badges')) {
            return ['count' => 0, 'items' => []];
        }

        $badges = $profile->earnedBadges()
            ->orderByDesc('earned_at')
            ->get();

        return [
            'count' => $badges->count(),
            'items' => $badges->map(fn ($badge): array => [
                'slug' => $badge->badge_slug->value,
                'earned_at' => $badge->earned_at?->toIso8601String(),
            ])->all(),
        ];
    }

    /**
     * @return array<int, array{id: string, name: string, slug: string, avatar_url: string|null, tier_name: string|null, can_manage: bool}>
     */
    private function resolveCommunities(Profile $profile): array
    {
        return $profile->communityMemberships()
            ->where('status', CommunityMemberStatus::Active->value)
            ->with(['community:id,name,slug,avatar_url', 'tier:id,name'])
            ->get()
            ->filter(fn ($member): bool => $member->community !== null)
            ->map(fn ($member): array => [
                'id' => $member->community->id,
                'name' => $member->community->name,
                'slug' => $member->community->slug,
                'avatar_url' => $member->community->avatar_url,
                'tier_name' => $member->tier?->name,
                'can_manage' => $member->can_manage,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{event_id: string, event_name: string, event_date: string|null, checked_in_at: string|null, community: array{id: string, name: string}|null}>
     */
    private function resolveRecentEvents(Profile $profile): array
    {
        return $this->checkinQuery($profile)
            ->with(['event:id,name,event_date,community_id', 'event.community:id,name'])
            ->orderByDesc('checked_in_at')
            ->limit(self::RECENT_EVENTS)
            ->get()
            ->filter(fn (EventCheckin $checkin): bool => $checkin->event !== null)
            ->map(fn (EventCheckin $checkin): array => $this->formatCheckin($checkin))
            ->values()
            ->all();
    }

    /**
     * @return array{event_id: string, event_name: string, event_date: string|null, checked_in_at: string|null, community: array{id: string, name: string}|null}
     */
    public function formatCheckin(EventCheckin $checkin): array
    {
        $event = $checkin->event;

        return [
            'event_id' => $event->id,
            'event_name' => $event->name,
            'event_date' => $event->event_date?->toDateString(),
            'checked_in_at' => $checkin->checked_in_at?->toIso8601String(),
            'community' => $event->community !== null
                ? ['id' => $event->community->id, 'name' => $event->community->name]
                : null,
        ];
    }

    /**
     * Accepted-friendship count. Omitted (null) when the friendships table does
     * not exist yet — the FRIENDS lane adds it in parallel, so this lane stays
     * independent (see Batch 4 brief).
     */
    private function resolveFriendsCount(Profile $profile): ?int
    {
        if (! Schema::hasTable('friendships')) {
            return null;
        }

        return \Illuminate\Support\Facades\DB::table('friendships')
            ->where('status', 'accepted')
            ->where(fn ($query) => $query
                ->where('requester_profile_id', $profile->id)
                ->orWhere('addressee_profile_id', $profile->id)
            )
            ->count();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<EventCheckin>
     */
    private function checkinQuery(Profile $profile): \Illuminate\Database\Eloquent\Builder
    {
        return EventCheckin::query()->where('profile_id', $profile->id);
    }

    private function resolveName(Profile $profile): ?string
    {
        return $profile->getExtendedProfile()?->name
            ?? $profile->email;
    }
}
