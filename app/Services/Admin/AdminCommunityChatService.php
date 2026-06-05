<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Enums\ChatParticipantState;
use App\Enums\ChatThreadType;
use App\Enums\CommunityMemberStatus;
use App\Models\ChatMessage;
use App\Models\ChatThread;
use App\Models\Community;
use App\Models\Event;
use App\Models\Profile;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Read-side queries for the maintainer operator panel (communities, businesses,
 * and the chat threads inside them). Keeps the admin controllers thin and the
 * eager-loading correct so the index/detail screens never N+1.
 */
class AdminCommunityChatService
{
    /**
     * Paginated index of every community with owner, counts, and tier/chat tallies.
     *
     * @return LengthAwarePaginator<Community>
     */
    public function communitiesIndex(?string $search, int $perPage = 20): LengthAwarePaginator
    {
        return Community::query()
            ->with(['owner', 'communityProfile'])
            ->withCount([
                'tiers',
                'members as active_members_count' => fn ($q) => $q->where('status', CommunityMemberStatus::Active->value),
            ])
            ->withCount([
                'chatThreads as chat_threads_count',
            ])
            ->when($search !== null && $search !== '', function ($query) use ($search): void {
                $term = '%'.$search.'%';
                $query->where(function ($inner) use ($term): void {
                    $inner->where('name', 'like', $term)
                        ->orWhere('slug', 'like', $term)
                        ->orWhereHas('owner', fn ($q) => $q->where('email', 'like', $term));
                });
            })
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Hydrate a single community for the detail view: profile + tiers + upcoming
     * events + every chat thread within it (with message + participant counts).
     *
     * @return array{
     *     community: Community,
     *     tiers: Collection<int, \App\Models\CommunityTier>,
     *     upcomingEvents: Collection<int, Event>,
     *     threads: Collection<int, ChatThread>
     * }
     */
    public function communityDetail(Community $community): array
    {
        $community->loadMissing(['owner', 'communityProfile', 'tiers']);

        $upcomingEvents = Event::query()
            ->where('community_id', $community->id)
            ->whereDate('event_date', '>=', now()->toDateString())
            ->orderBy('event_date')
            ->get();

        $threads = $this->threadsForCommunity($community);

        return [
            'community' => $community,
            'tiers' => $community->tiers,
            'upcomingEvents' => $upcomingEvents,
            'threads' => $threads,
        ];
    }

    /**
     * All chat threads belonging to a community (main/custom/event), each carrying
     * transient `messages_count` and `participants_count`. Soft-deleted threads are
     * included so an operator can see what was removed.
     *
     * @return Collection<int, ChatThread>
     */
    public function threadsForCommunity(Community $community): Collection
    {
        return ChatThread::query()
            ->withTrashed()
            ->where('community_id', $community->id)
            ->withCount(['messages', 'participants'])
            ->orderByDesc('last_message_at')
            ->get();
    }

    /**
     * Paginated index of every business profile with its active collaboration-chat tally.
     *
     * @return LengthAwarePaginator<Profile>
     */
    public function businessesIndex(?string $search, int $perPage = 20): LengthAwarePaginator
    {
        return Profile::query()
            ->where('user_type', \App\Enums\UserType::Business->value)
            ->with(['businessProfile', 'subscription'])
            ->when($search !== null && $search !== '', function ($query) use ($search): void {
                $term = '%'.$search.'%';
                $query->where(function ($inner) use ($term): void {
                    $inner->where('email', 'like', $term)
                        ->orWhereHas('businessProfile', fn ($q) => $q->where('name', 'like', $term));
                });
            })
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Active collaboration chats for a business, newest activity first. "Active"
     * here = a collaboration thread with at least one message (last_message_at set).
     *
     * @return array{business: Profile, threads: Collection<int, ChatThread>}
     */
    public function businessDetail(Profile $business): array
    {
        $business->loadMissing(['businessProfile', 'subscription']);

        $threads = ChatThread::query()
            ->where('type', ChatThreadType::Collaboration->value)
            ->whereNotNull('last_message_at')
            ->whereHas('application', function ($query) use ($business): void {
                $query->where('applicant_profile_id', $business->id)
                    ->orWhereHas('collabOpportunity', fn ($q) => $q->where('creator_profile_id', $business->id));
            })
            ->with([
                'application.applicantProfile.businessProfile',
                'application.applicantProfile.communityProfile',
                'application.collabOpportunity.creatorProfile.businessProfile',
                'application.collabOpportunity.creatorProfile.communityProfile',
            ])
            ->withCount('messages')
            ->orderByDesc('last_message_at')
            ->get();

        return [
            'business' => $business,
            'threads' => $threads,
        ];
    }

    /**
     * The full transcript for a thread (operator view), oldest first.
     *
     * @return LengthAwarePaginator<ChatMessage>
     */
    public function transcript(ChatThread $thread, int $perPage = 100): LengthAwarePaginator
    {
        return ChatMessage::query()
            ->where('thread_id', $thread->id)
            ->with(['senderProfile.businessProfile', 'senderProfile.communityProfile'])
            ->orderBy('created_at')
            ->paginate($perPage);
    }

    /**
     * Distinct participants of a thread for the operator's ban UI: anyone who has
     * sent a message, plus everyone on the participants table. De-duplicated by
     * profile id and annotated with their ban state.
     *
     * @return Collection<int, array{profile: Profile, banned: bool}>
     */
    public function threadParticipants(ChatThread $thread): Collection
    {
        $senderIds = ChatMessage::query()
            ->where('thread_id', $thread->id)
            ->pluck('sender_profile_id')
            ->filter()
            ->unique();

        $participantIds = $thread->participants()->pluck('profile_id');

        $bannedIds = $thread->participants()
            ->where('state', ChatParticipantState::Banned->value)
            ->pluck('profile_id')
            ->all();

        $allIds = $senderIds->merge($participantIds)->unique()->values();

        if ($allIds->isEmpty()) {
            return collect();
        }

        return Profile::query()
            ->whereIn('id', $allIds)
            ->with(['businessProfile', 'communityProfile'])
            ->get()
            ->map(fn (Profile $profile): array => [
                'profile' => $profile,
                'banned' => in_array($profile->id, $bannedIds, true),
            ])
            ->values();
    }
}
