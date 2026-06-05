<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ChatThreadType;
use App\Enums\CommunityMemberStatus;
use App\Models\ChatThread;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Event;
use App\Models\Profile;
use App\Models\ProfileGalleryPhoto;
use Illuminate\Support\Str;

/**
 * Aggregates the rich community public-profile payload from the existing
 * community / tier / event / leaderboard / chat services.
 *
 * This surface is community/attendee facing and MUST NEVER hit the business
 * paywall (no Profile::hasActiveSubscription()). Non-members get a public
 * subset (no chats, no viewer tier/rank); members get the full payload.
 */
class CommunityProfileService
{
    public function __construct(
        private readonly EventService $events,
        private readonly EventSignupService $signups,
        private readonly LeaderboardService $leaderboard,
        private readonly ChatService $chat,
    ) {}

    /**
     * Build the aggregate community-profile payload for one viewer.
     *
     * @return array{
     *     community: Community,
     *     tiers: \Illuminate\Database\Eloquent\Collection<int, \App\Models\CommunityTier>,
     *     upcoming_events: array<int, array<string, mixed>>,
     *     members_preview: array<int, array<string, mixed>>,
     *     gallery: array<int, string>,
     *     chats: array<int, array<string, mixed>>|null,
     *     viewer: array<string, mixed>
     * }
     */
    public function build(Community $community, Profile $viewer): array
    {
        $community->loadMissing(['tiers', 'owner']);

        $membership = $this->membershipFor($community, $viewer);
        $isOwner = $viewer->id === $community->owner_profile_id;
        $isMember = $membership !== null;
        $canManage = $viewer->can('manage', $community);

        return [
            'community' => $community,
            'tiers' => $community->tiers, // ordered rank desc by the relation
            'upcoming_events' => $this->upcomingEvents($community),
            'members_preview' => $this->membersPreview($community),
            'gallery' => $this->gallery($community),
            'chats' => ($isMember || $isOwner) ? $this->chats($community, $viewer) : null,
            'viewer' => $this->viewer($community, $viewer, $membership, $isOwner, $isMember, $canManage),
        ];
    }

    private function membershipFor(Community $community, Profile $viewer): ?CommunityMember
    {
        return $community->members()
            ->where('profile_id', $viewer->id)
            ->where('status', CommunityMemberStatus::Active->value)
            ->with('tier')
            ->first();
    }

    /**
     * Up to five upcoming events linked to this community.
     *
     * @return array<int, array<string, mixed>>
     */
    private function upcomingEvents(Community $community): array
    {
        $events = $this->events
            ->list(['community_id' => $community->id, 'time' => 'upcoming'], 5)
            ->getCollection();

        return $events->map(fn (Event $event): array => [
            'id' => $event->id,
            'name' => $event->name,
            'date' => $event->event_date->toDateString(),
            'visibility' => empty($event->tier_gate) ? 'public' : 'tier_gated',
            'going_count' => $this->signups->goingCount($event),
        ])->all();
    }

    /**
     * Up to twelve active members with their tier name.
     *
     * @return array<int, array<string, mixed>>
     */
    private function membersPreview(Community $community): array
    {
        $members = $community->members()
            ->where('status', CommunityMemberStatus::Active->value)
            ->with(['tier', 'profile'])
            ->orderByDesc('joined_at')
            ->limit(12)
            ->get();

        return $members->map(fn (CommunityMember $member): array => [
            'profile_id' => $member->profile_id,
            'name' => $this->memberName($member),
            'avatar_url' => $this->memberAvatar($member),
            'tier_name' => $member->tier?->name,
        ])->all();
    }

    private function memberName(CommunityMember $member): ?string
    {
        $extended = $member->profile?->getExtendedProfile();

        if ($extended && ! empty($extended->name)) {
            return $extended->name;
        }

        return $member->profile ? Str::before($member->profile->email, '@') : null;
    }

    private function memberAvatar(CommunityMember $member): ?string
    {
        $extended = $member->profile?->getExtendedProfile();

        return $member->profile?->avatar_url
            ?? ($extended->profile_photo ?? null);
    }

    /**
     * The community gallery: the owner profile's gallery photos. Empty when none.
     *
     * @return array<int, string>
     */
    private function gallery(Community $community): array
    {
        return ProfileGalleryPhoto::query()
            ->where('profile_id', $community->owner_profile_id)
            ->orderBy('created_at')
            ->pluck('url')
            ->all();
    }

    /**
     * Member-visible chat threads for this community.
     *
     * @return array<int, array<string, mixed>>
     */
    private function chats(Community $community, Profile $viewer): array
    {
        return $this->chat->communityThreadsFor($community, $viewer)
            ->map(fn (ChatThread $thread): array => [
                'id' => $thread->id,
                'type' => $thread->type->value,
                'name' => $thread->name,
                'slug' => $thread->slug,
                'is_open' => $thread->type === ChatThreadType::CommunityMain,
            ])
            ->values()
            ->all();
    }

    /**
     * The viewer block: membership, role flags, tier and chapter rank.
     *
     * @return array<string, mixed>
     */
    private function viewer(
        Community $community,
        Profile $viewer,
        ?CommunityMember $membership,
        bool $isOwner,
        bool $isMember,
        bool $canManage,
    ): array {
        $chapterRank = $isMember
            ? ($this->leaderboard->getMyCommunityRank($community, $viewer)['rank'] ?? null)
            : null;

        return [
            'is_member' => $isMember,
            'is_owner' => $isOwner,
            'can_manage' => $canManage,
            'tier_name' => $membership?->tier?->name,
            'chapter_rank' => $chapterRank,
        ];
    }
}
