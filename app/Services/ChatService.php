<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ChatThreadType;
use App\Enums\CommunityMemberStatus;
use App\Enums\EventSignupStatus;
use App\Events\NewChatMessage;
use App\Jobs\FanOutThreadMessageNotifications;
use App\Models\Application;
use App\Models\ChatMessage;
use App\Models\ChatThread;
use App\Models\ChatThreadBan;
use App\Models\ChatThreadRead;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\CommunityTier;
use App\Models\Event;
use App\Models\EventSeries;
use App\Models\EventSignup;
use App\Models\Profile;
use DomainException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ChatService
{
    public function __construct(
        private readonly NotificationService $notificationService,
        private readonly NotificationReminderService $notificationReminderService,
    ) {}

    /**
     * Get chat messages for an application.
     *
     * @return LengthAwarePaginator<ChatMessage>
     */
    public function getMessages(Application $application, int $perPage = 50): LengthAwarePaginator
    {
        return ChatMessage::query()
            ->where('application_id', $application->id)
            ->with('senderProfile.businessProfile', 'senderProfile.communityProfile')
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }

    /**
     * Send a new chat message.
     *
     * @param  array{content: string}  $data
     *
     * @throws InvalidArgumentException
     */
    public function sendMessage(Profile $sender, Application $application, array $data): ChatMessage
    {
        if (! $this->canParticipate($sender, $application)) {
            throw new InvalidArgumentException('You are not authorized to send messages in this chat.');
        }

        $thread = $this->threadForApplication($application);

        $message = ChatMessage::query()->create([
            'application_id' => $application->id,
            'thread_id' => $thread->id,
            'sender_profile_id' => $sender->id,
            'content' => $data['content'],
        ]);

        // Drives the business "active chats" filter + sort.
        $thread->forceFill(['last_message_at' => now()])->save();

        $message->load('senderProfile.businessProfile', 'senderProfile.communityProfile');

        // Broadcast the new message event
        broadcast(new NewChatMessage($message))->toOthers();

        // Create notification for the recipient
        $this->notificationService->notifyNewMessage($message, $application);

        $recipient = $this->getOtherParticipant($sender, $application);
        if ($recipient !== null) {
            $this->notificationReminderService->syncUnreadMessageReminder($application, $recipient);
        }

        $this->notificationReminderService->syncUnreadMessageReminder($application, $sender);

        return $message;
    }

    /**
     * Mark messages as read for a user in an application.
     */
    public function markMessagesAsRead(Profile $reader, Application $application): int
    {
        $updated = ChatMessage::query()
            ->where('application_id', $application->id)
            ->where('sender_profile_id', '!=', $reader->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        $this->notificationReminderService->syncUnreadMessageReminder($application, $reader);

        return $updated;
    }

    /**
     * Get unread message count for a user.
     */
    public function getUnreadCount(Profile $profile): int
    {
        // Get applications where user is either the applicant or the opportunity creator
        $applicationIds = $this->getParticipatingApplicationIds($profile);

        return ChatMessage::query()
            ->whereIn('application_id', $applicationIds)
            ->where('sender_profile_id', '!=', $profile->id)
            ->whereNull('read_at')
            ->count();
    }

    /**
     * Get unread count per application for a user.
     *
     * @return array<string, int>
     */
    public function getUnreadCountByApplication(Profile $profile, ?Collection $applicationIds = null): array
    {
        $applicationIds ??= $this->getParticipatingApplicationIds($profile);

        return ChatMessage::query()
            ->whereIn('application_id', $applicationIds)
            ->where('sender_profile_id', '!=', $profile->id)
            ->whereNull('read_at')
            ->selectRaw('application_id, COUNT(*) as count')
            ->groupBy('application_id')
            ->pluck('count', 'application_id')
            ->toArray();
    }

    /**
     * Check if a profile can participate in the application chat.
     */
    public function canParticipate(Profile $profile, Application $application): bool
    {
        // Chat is read-only once an application is no longer pending/accepted.
        if ($application->isDeclined() || $application->isWithdrawn()) {
            return false;
        }

        $application->loadMissing('kolab');
        $opportunity = $application->kolab;

        // Applicant can always participate
        if ($application->applicant_profile_id === $profile->id) {
            return true;
        }

        // Opportunity creator can participate
        if ($opportunity !== null && $opportunity->creator_profile_id === $profile->id) {
            return true;
        }

        return false;
    }

    /**
     * Get the other participant in the chat.
     */
    public function getOtherParticipant(Profile $profile, Application $application): ?Profile
    {
        $application->loadMissing(['kolab.creatorProfile', 'applicantProfile']);
        $opportunity = $application->kolab;

        if ($opportunity === null) {
            return null;
        }

        if ($application->applicant_profile_id === $profile->id) {
            return $opportunity->creatorProfile;
        }

        if ($opportunity->creator_profile_id === $profile->id) {
            return $application->applicantProfile;
        }

        return null;
    }

    /**
     * Resolve (or lazily create) the collaboration thread for an application.
     */
    public function threadForApplication(Application $application): ChatThread
    {
        return ChatThread::query()->firstOrCreate(
            ['application_id' => $application->id],
            ['type' => ChatThreadType::Collaboration->value],
        );
    }

    /**
     * Threads visible to a profile, for the inbox: collaboration threads plus
     * the community threads (main + tier-granted custom) the viewer can access.
     * Each thread carries a transient `unread_count`. Sorted newest-activity first.
     *
     * @return Collection<int, ChatThread>
     */
    public function visibleThreads(Profile $profile): Collection
    {
        return $this->visibleCollaborationThreads($profile)
            ->merge($this->visibleCommunityThreads($profile))
            ->merge($this->visibleEventThreads($profile))
            ->sortByDesc(fn (ChatThread $thread): int => $thread->last_message_at?->getTimestamp() ?? 0)
            ->values();
    }

    /**
     * Collaboration threads visible to a profile. Business viewers only see
     * threads with at least one message (last_message_at != null).
     *
     * @return Collection<int, ChatThread>
     */
    private function visibleCollaborationThreads(Profile $profile): Collection
    {
        $applicationIds = $this->getParticipatingApplicationIds($profile);

        $query = ChatThread::query()
            ->where('type', ChatThreadType::Collaboration->value)
            ->whereIn('application_id', $applicationIds)
            ->with([
                'application.collaboration',
                'application.applicantProfile.businessProfile',
                'application.applicantProfile.communityProfile',
                'application.kolab.creatorProfile.businessProfile',
                'application.kolab.creatorProfile.communityProfile',
            ]);

        if ($profile->isBusiness()) {
            $query->whereNotNull('last_message_at');
        }

        $threads = $query->orderByDesc('last_message_at')->get();

        $unreadByApplication = $this->getUnreadCountByApplication($profile, $applicationIds);

        foreach ($threads as $thread) {
            $thread->unread_count = $unreadByApplication[$thread->application_id] ?? 0;
        }

        return $threads;
    }

    /**
     * Resolve (or lazily create) the single main chat for a community.
     */
    public function mainThreadFor(Community $community): ChatThread
    {
        return ChatThread::query()->firstOrCreate(
            ['type' => ChatThreadType::CommunityMain->value, 'community_id' => $community->id],
            ['name' => $community->name],
        );
    }

    /**
     * Create a custom community chat (owner / can_manage). Enforces the ≤5 cap.
     *
     * @throws DomainException 'chat_limit_reached'
     */
    public function createCustomChat(Community $community, Profile $creator, string $name): ChatThread
    {
        $count = ChatThread::query()
            ->where('type', ChatThreadType::CommunityCustom->value)
            ->where('community_id', $community->id)
            ->count();

        if ($count >= 5) {
            throw new DomainException('chat_limit_reached');
        }

        $thread = ChatThread::query()->create([
            'type' => ChatThreadType::CommunityCustom->value,
            'community_id' => $community->id,
            'slug' => $this->uniqueChatSlug($community, $name),
            'name' => $name,
            'created_by' => $creator->id,
        ]);

        // Default-open: a brand-new chat is ungated, so grant its slug to every
        // tier and make it visible to all members immediately. Without this a new
        // chat is invisible to non-managers (their tier's chat_channels never lists
        // it) until the leader manually grants access — which read as "not all
        // chats appear available" (see chat-visibility-tier-gating diagnosis). The
        // leader can still RESTRICT it afterwards via the per-chat Access picker.
        $this->grantChatToAllTiers($community, $thread->slug);

        return $thread;
    }

    /**
     * Append a chat slug to every tier's permissions.chat_channels (idempotent),
     * so a newly created, ungated chat is visible to all members by default.
     */
    private function grantChatToAllTiers(Community $community, string $slug): void
    {
        $tiers = CommunityTier::query()->where('community_id', $community->id)->get();

        foreach ($tiers as $tier) {
            $permissions = $tier->permissions ?? [];
            $channels = $permissions['chat_channels'] ?? [];
            if (! is_array($channels)) {
                $channels = [];
            }
            if (! in_array($slug, $channels, true)) {
                $channels[] = $slug;
                $permissions['chat_channels'] = $channels;
                $tier->update(['permissions' => $permissions]);
            }
        }
    }

    /**
     * Rename a custom chat. The slug is deliberately NOT changed — it's the
     * gating key in tiers' permissions.chat_channels, so renaming must not
     * silently revoke access.
     */
    public function renameCustomChat(ChatThread $thread, string $name): ChatThread
    {
        $thread->update(['name' => $name]);

        return $thread->fresh();
    }

    /**
     * Delete a custom chat (messages + read pointers cascade via FK). Tiers that
     * listed its slug keep a harmless dangling entry.
     */
    public function deleteCustomChat(ChatThread $thread): void
    {
        $thread->delete();
    }

    /**
     * Block an individual from a thread (overrides their tier access). No-op for
     * a community owner / manager — admins can't be blocked. Idempotent.
     */
    public function banFromChat(ChatThread $thread, Profile $target, Profile $by): void
    {
        if ($thread->community_id !== null
            && $this->canManageCommunity($target, $thread->community_id)) {
            return; // can't block an owner/manager
        }

        ChatThreadBan::query()->firstOrCreate(
            ['thread_id' => $thread->id, 'profile_id' => $target->id],
            ['created_by' => $by->id],
        );
    }

    /**
     * Lift a block. Idempotent.
     */
    public function unbanFromChat(ChatThread $thread, string $profileId): void
    {
        ChatThreadBan::query()
            ->where('thread_id', $thread->id)
            ->where('profile_id', $profileId)
            ->delete();
    }

    /**
     * Profile ids currently blocked from a thread.
     *
     * @return array<int, string>
     */
    public function bannedProfileIds(ChatThread $thread): array
    {
        return ChatThreadBan::query()
            ->where('thread_id', $thread->id)
            ->pluck('profile_id')
            ->all();
    }

    /**
     * Whether a profile may read/post in a thread (derived access, §5 of the spec).
     */
    public function canAccessThread(Profile $profile, ChatThread $thread): bool
    {
        if ($thread->type === ChatThreadType::Collaboration) {
            $thread->loadMissing('application');

            return $thread->application !== null && $this->canParticipate($profile, $thread->application);
        }

        // Event chats: a `going` sign-up, or the community leader/can_manage.
        if ($thread->type === ChatThreadType::Event) {
            return $this->canAccessEventThread($profile, $thread);
        }

        if ($thread->community_id === null) {
            return false;
        }

        if ($thread->type === ChatThreadType::CommunityMain) {
            return $this->isCommunityMemberOrOwner($profile, $thread->community_id);
        }

        if ($thread->type === ChatThreadType::CommunityCustom) {
            return $this->canAccessCustomChat($profile, $thread);
        }

        return false;
    }

    /**
     * Messages for a (non-application) thread, oldest-first so the newest is last.
     *
     * @return LengthAwarePaginator<ChatMessage>
     */
    public function getThreadMessages(ChatThread $thread, int $perPage = 50): LengthAwarePaginator
    {
        return ChatMessage::query()
            ->where('thread_id', $thread->id)
            ->with('senderProfile.businessProfile', 'senderProfile.communityProfile')
            ->orderBy('created_at')
            ->paginate($perPage);
    }

    /**
     * Send a message into any thread (community/event). Sets last_message_at and
     * broadcasts. Access must be checked by the caller.
     *
     * @param  array{content: string}  $data
     */
    public function sendThreadMessage(Profile $sender, ChatThread $thread, array $data): ChatMessage
    {
        $message = ChatMessage::query()->create([
            'application_id' => $thread->application_id,
            'thread_id' => $thread->id,
            'sender_profile_id' => $sender->id,
            'content' => $data['content'],
        ]);

        $thread->forceFill(['last_message_at' => now()])->save();

        $message->load('senderProfile.businessProfile', 'senderProfile.communityProfile');

        broadcast(new NewChatMessage($message))->toOthers();

        // Fan out push + in-app notifications to the thread's other members
        // (community/event). Queued so a large audience never blocks the send.
        FanOutThreadMessageNotifications::dispatch($message->id, $thread->id, $sender->id);

        return $message;
    }

    /**
     * Move a viewer's read pointer for a thread. Collaboration threads keep the
     * existing per-message read model; community/event threads use chat_thread_reads.
     */
    public function markThreadRead(Profile $reader, ChatThread $thread): void
    {
        if ($thread->type === ChatThreadType::Collaboration && $thread->application_id !== null) {
            $thread->loadMissing('application');

            if ($thread->application !== null) {
                $this->markMessagesAsRead($reader, $thread->application);
            }

            return;
        }

        ChatThreadRead::query()->updateOrCreate(
            ['thread_id' => $thread->id, 'profile_id' => $reader->id],
            ['last_read_at' => now()],
        );
    }

    /**
     * Community threads (main + tier-granted custom) visible to a profile, with
     * a transient unread_count computed from chat_thread_reads.
     *
     * @return Collection<int, ChatThread>
     */
    private function visibleCommunityThreads(Profile $profile): Collection
    {
        $ownedIds = Community::query()->where('owner_profile_id', $profile->id)->pluck('id');

        $memberships = CommunityMember::query()
            ->with('tier')
            ->where('profile_id', $profile->id)
            ->where('status', CommunityMemberStatus::Active->value)
            ->get();

        $memberByCommunity = $memberships->keyBy('community_id');
        $communityIds = $ownedIds->merge($memberships->pluck('community_id'))->unique();

        if ($communityIds->isEmpty()) {
            return collect();
        }

        $threads = ChatThread::query()
            ->whereIn('community_id', $communityIds)
            ->whereIn('type', [
                ChatThreadType::CommunityMain->value,
                ChatThreadType::CommunityCustom->value,
            ])
            ->get();

        $bannedThreadIds = $this->bannedThreadIds($profile, $threads);

        $visible = $threads->filter(function (ChatThread $thread) use ($ownedIds, $memberByCommunity, $bannedThreadIds): bool {
            if ($thread->type === ChatThreadType::CommunityMain) {
                return true; // already scoped to owned/joined communities
            }

            if ($ownedIds->contains($thread->community_id)) {
                return true;
            }

            $member = $memberByCommunity[$thread->community_id] ?? null;
            if ($member === null) {
                return false;
            }
            if ($member->can_manage) {
                return true;
            }
            if ($bannedThreadIds->contains($thread->id)) {
                return false;
            }

            return in_array($thread->slug, $this->tierChatChannels($member->tier), true);
        });

        $this->attachUnreadCounts($profile, $visible);

        return $visible->values();
    }

    /**
     * Resolve (or lazily create) the single event chat thread for an event.
     */
    public function eventThreadFor(Event $event, ?Profile $creator = null): ChatThread
    {
        // Shared series chat: one thread for the whole series (keyed by
        // series_id), so every occurrence's "Open chat" lands in the same room.
        if ($event->series_id !== null) {
            $series = EventSeries::query()->find($event->series_id);
            if ($series !== null && $series->chat_mode === 'series') {
                return ChatThread::query()->firstOrCreate(
                    ['type' => ChatThreadType::Event->value, 'series_id' => $series->id],
                    [
                        'community_id' => $event->community_id,
                        'name' => $series->name,
                        'created_by' => $creator?->id,
                    ],
                );
            }
        }

        return ChatThread::query()->firstOrCreate(
            ['type' => ChatThreadType::Event->value, 'event_id' => $event->id],
            [
                'community_id' => $event->community_id,
                'name' => $event->name,
                'created_by' => $creator?->id,
            ],
        );
    }

    /**
     * Profile ids of all occurrences in a series (for series-chat access/audience).
     *
     * @return \Illuminate\Support\Collection<int, string>
     */
    private function seriesEventIds(string $seriesId): \Illuminate\Support\Collection
    {
        return Event::query()->where('series_id', $seriesId)->pluck('id');
    }

    /**
     * Event threads visible to a profile: the events they are `going` to, plus
     * (for leaders) the event chats of communities they manage.
     *
     * @return Collection<int, ChatThread>
     */
    private function visibleEventThreads(Profile $profile): Collection
    {
        $goingEventIds = EventSignup::query()
            ->where('profile_id', $profile->id)
            ->where('status', EventSignupStatus::Going->value)
            ->pluck('event_id');

        // Series the profile is going to (any occurrence) → its shared series chat.
        $goingSeriesIds = $goingEventIds->isEmpty()
            ? collect()
            : Event::query()->whereIn('id', $goingEventIds)
                ->whereNotNull('series_id')->pluck('series_id')->unique();

        $managedCommunityIds = $this->managedCommunityIds($profile);

        if ($goingEventIds->isEmpty() && $managedCommunityIds->isEmpty()) {
            return collect();
        }

        $threads = ChatThread::query()
            ->where('type', ChatThreadType::Event->value)
            ->where(function ($query) use ($goingEventIds, $goingSeriesIds, $managedCommunityIds): void {
                $query->whereIn('event_id', $goingEventIds);
                if ($goingSeriesIds->isNotEmpty()) {
                    $query->orWhereIn('series_id', $goingSeriesIds);
                }
                if ($managedCommunityIds->isNotEmpty()) {
                    $query->orWhereIn('community_id', $managedCommunityIds);
                }
            })
            ->get();

        $bannedThreadIds = $this->bannedThreadIds($profile, $threads);

        // Per-member block hides the thread from the blocked viewer (managers
        // are never banned, so this only affects going-but-blocked members).
        $threads = $threads->reject(fn (ChatThread $thread): bool => $bannedThreadIds->contains($thread->id));

        $this->attachUnreadCounts($profile, $threads);

        return $threads->values();
    }

    private function canAccessEventThread(Profile $profile, ChatThread $thread): bool
    {
        // Shared series thread: manager, or going to ANY occurrence of the series.
        if ($thread->event_id === null && $thread->series_id !== null) {
            if ($thread->community_id !== null
                && $this->canManageCommunity($profile, $thread->community_id)) {
                return true;
            }
            if ($this->isBanned($thread, $profile)) {
                return false;
            }

            return EventSignup::query()
                ->whereIn('event_id', $this->seriesEventIds($thread->series_id))
                ->where('profile_id', $profile->id)
                ->where('status', EventSignupStatus::Going->value)
                ->exists();
        }

        if ($thread->event_id === null) {
            return false;
        }

        $event = Event::query()->find($thread->event_id);
        if ($event === null) {
            return false;
        }

        // Community leader / can_manage always has access to manage the chat.
        if ($event->community_id !== null && $this->canManageCommunity($profile, $event->community_id)) {
            return true;
        }
        if ($this->isBanned($thread, $profile)) {
            return false;
        }

        // Going sign-ups have access; waitlisted do not.
        return EventSignup::query()
            ->where('event_id', $event->id)
            ->where('profile_id', $profile->id)
            ->where('status', EventSignupStatus::Going->value)
            ->exists();
    }

    /**
     * Owner, or active member with can_manage, of a community.
     */
    public function canManageCommunity(Profile $profile, string $communityId): bool
    {
        if (Community::query()->whereKey($communityId)->where('owner_profile_id', $profile->id)->exists()) {
            return true;
        }

        return CommunityMember::query()
            ->where('community_id', $communityId)
            ->where('profile_id', $profile->id)
            ->where('can_manage', true)
            ->where('status', CommunityMemberStatus::Active->value)
            ->exists();
    }

    /**
     * Community ids the profile manages (owns or can_manage).
     *
     * @return Collection<int, string>
     */
    private function managedCommunityIds(Profile $profile): Collection
    {
        $owned = Community::query()->where('owner_profile_id', $profile->id)->pluck('id');

        $managed = CommunityMember::query()
            ->where('profile_id', $profile->id)
            ->where('can_manage', true)
            ->where('status', CommunityMemberStatus::Active->value)
            ->pluck('community_id');

        return $owned->merge($managed)->unique()->values();
    }

    /**
     * Profile ids that should be notified of a new message in a thread, minus the
     * sender. Mirrors `canAccessThread` as a SET. Collaboration threads return []
     * (those notify via NotificationService::notifyNewMessage).
     *
     * @return array<int, string>
     */
    public function threadRecipientIds(ChatThread $thread, string $senderProfileId): array
    {
        $ids = match ($thread->type) {
            ChatThreadType::CommunityMain => $this->communityAudienceIds($thread->community_id),
            ChatThreadType::CommunityCustom => $this->customChatAudienceIds($thread),
            ChatThreadType::Event => $this->eventChatAudienceIds($thread),
            default => collect(),
        };

        return $ids
            ->filter(fn ($id): bool => $id !== null && $id !== $senderProfileId)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, string>
     */
    private function communityAudienceIds(?string $communityId): Collection
    {
        if ($communityId === null) {
            return collect();
        }

        $members = CommunityMember::query()
            ->where('community_id', $communityId)
            ->where('status', CommunityMemberStatus::Active->value)
            ->pluck('profile_id');

        $owner = Community::query()->whereKey($communityId)->value('owner_profile_id');

        return $members->push($owner);
    }

    /**
     * @return Collection<int, string>
     */
    private function customChatAudienceIds(ChatThread $thread): Collection
    {
        if ($thread->community_id === null) {
            return collect();
        }

        $owner = Community::query()->whereKey($thread->community_id)->value('owner_profile_id');

        $ids = CommunityMember::query()
            ->with('tier')
            ->where('community_id', $thread->community_id)
            ->where('status', CommunityMemberStatus::Active->value)
            ->get()
            ->filter(fn (CommunityMember $m): bool => $m->can_manage
                || in_array($thread->slug, $this->tierChatChannels($m->tier), true))
            ->pluck('profile_id');

        return $ids->push($owner);
    }

    /**
     * @return Collection<int, string>
     */
    private function eventChatAudienceIds(ChatThread $thread): Collection
    {
        if ($thread->event_id === null && $thread->series_id === null) {
            return collect();
        }

        // Series thread → going across all occurrences; else the single event.
        $going = $thread->series_id !== null
            ? EventSignup::query()
                ->whereIn('event_id', $this->seriesEventIds($thread->series_id))
                ->where('status', EventSignupStatus::Going->value)
                ->pluck('profile_id')
            : EventSignup::query()
                ->where('event_id', $thread->event_id)
                ->where('status', EventSignupStatus::Going->value)
                ->pluck('profile_id');

        if ($thread->community_id === null) {
            return $going;
        }

        $managers = CommunityMember::query()
            ->where('community_id', $thread->community_id)
            ->where('can_manage', true)
            ->where('status', CommunityMemberStatus::Active->value)
            ->pluck('profile_id');

        $owner = Community::query()->whereKey($thread->community_id)->value('owner_profile_id');

        return $going->merge($managers)->push($owner);
    }

    private function isCommunityMemberOrOwner(Profile $profile, string $communityId): bool
    {
        if (Community::query()->whereKey($communityId)->where('owner_profile_id', $profile->id)->exists()) {
            return true;
        }

        return CommunityMember::query()
            ->where('community_id', $communityId)
            ->where('profile_id', $profile->id)
            ->where('status', CommunityMemberStatus::Active->value)
            ->exists();
    }

    private function canAccessCustomChat(Profile $profile, ChatThread $thread): bool
    {
        if (Community::query()->whereKey($thread->community_id)->where('owner_profile_id', $profile->id)->exists()) {
            return true;
        }

        $member = CommunityMember::query()
            ->with('tier')
            ->where('community_id', $thread->community_id)
            ->where('profile_id', $profile->id)
            ->where('status', CommunityMemberStatus::Active->value)
            ->first();

        if ($member === null) {
            return false;
        }
        if ($member->can_manage) {
            return true;
        }
        // Per-member block overrides tier access.
        if ($this->isBanned($thread, $profile)) {
            return false;
        }

        return in_array($thread->slug, $this->tierChatChannels($member->tier), true);
    }

    /**
     * Whether a profile is individually blocked from a thread (overrides tier).
     */
    private function isBanned(ChatThread $thread, Profile $profile): bool
    {
        return ChatThreadBan::query()
            ->where('thread_id', $thread->id)
            ->where('profile_id', $profile->id)
            ->exists();
    }

    /**
     * Unread count in a thread for a viewer, based on their chat_thread_reads pointer.
     */
    private function unreadForThread(Profile $profile, ChatThread $thread): int
    {
        $read = ChatThreadRead::query()
            ->where('thread_id', $thread->id)
            ->where('profile_id', $profile->id)
            ->first();

        $query = ChatMessage::query()
            ->where('thread_id', $thread->id)
            ->where('sender_profile_id', '!=', $profile->id);

        if ($read?->last_read_at !== null) {
            $query->where('created_at', '>', $read->last_read_at);
        }

        return $query->count();
    }

    /**
     * @param  Collection<int, ChatThread>  $threads
     */
    private function attachUnreadCounts(Profile $profile, Collection $threads): void
    {
        $threadIds = $threads->pluck('id')->filter()->values();
        if ($threadIds->isEmpty()) {
            return;
        }

        $counts = ChatMessage::query()
            ->leftJoin('chat_thread_reads', function ($join) use ($profile): void {
                $join->on('chat_messages.thread_id', '=', 'chat_thread_reads.thread_id')
                    ->where('chat_thread_reads.profile_id', '=', $profile->id);
            })
            ->whereIn('chat_messages.thread_id', $threadIds)
            ->where('chat_messages.sender_profile_id', '!=', $profile->id)
            ->where(function ($query): void {
                $query->whereNull('chat_thread_reads.last_read_at')
                    ->orWhereColumn('chat_messages.created_at', '>', 'chat_thread_reads.last_read_at');
            })
            ->selectRaw('chat_messages.thread_id, COUNT(*) as aggregate')
            ->groupBy('chat_messages.thread_id')
            ->pluck('aggregate', 'chat_messages.thread_id');

        foreach ($threads as $thread) {
            $thread->unread_count = (int) ($counts[$thread->id] ?? 0);
        }
    }

    /**
     * @param  Collection<int, ChatThread>  $threads
     * @return Collection<int, string>
     */
    private function bannedThreadIds(Profile $profile, Collection $threads): Collection
    {
        $threadIds = $threads->pluck('id')->filter()->values();
        if ($threadIds->isEmpty()) {
            return collect();
        }

        return ChatThreadBan::query()
            ->whereIn('thread_id', $threadIds)
            ->where('profile_id', $profile->id)
            ->pluck('thread_id');
    }

    /**
     * The chat-channel slugs a tier grants (permissions.chat_channels).
     *
     * @return array<int, string>
     */
    private function tierChatChannels(?CommunityTier $tier): array
    {
        $permissions = $tier?->permissions ?? [];
        $channels = $permissions['chat_channels'] ?? [];

        return is_array($channels) ? $channels : [];
    }

    private function uniqueChatSlug(Community $community, string $name): string
    {
        $base = Str::slug($name);
        if ($base === '') {
            $base = 'chat';
        }

        $slug = $base;
        $suffix = 2;
        while (ChatThread::query()
            ->where('community_id', $community->id)
            ->where('slug', $slug)
            ->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    /**
     * Get application IDs where the profile is a participant.
     *
     * @return Collection<int, mixed>
     */
    private function getParticipatingApplicationIds(Profile $profile): Collection
    {
        // Applications where user is the applicant
        $asApplicant = Application::query()
            ->where('applicant_profile_id', $profile->id)
            ->pluck('id');

        // Applications where user is the opportunity creator
        $asCreator = Application::query()
            ->whereHas('kolab', function ($q) use ($profile): void {
                $q->where('creator_profile_id', $profile->id);
            })
            ->pluck('id');

        return $asApplicant->merge($asCreator)->unique();
    }
}
