<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ChatThreadType;
use App\Enums\CommunityMemberStatus;
use App\Events\NewChatMessage;
use App\Models\Application;
use App\Models\ChatMessage;
use App\Models\ChatThread;
use App\Models\ChatThreadRead;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\CommunityTier;
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
    public function getUnreadCountByApplication(Profile $profile): array
    {
        $applicationIds = $this->getParticipatingApplicationIds($profile);

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
        // Load the opportunity with creator
        $application->loadMissing('collabOpportunity');

        // Applicant can always participate
        if ($application->applicant_profile_id === $profile->id) {
            return true;
        }

        // Opportunity creator can participate
        if ($application->collabOpportunity->creator_profile_id === $profile->id) {
            return true;
        }

        return false;
    }

    /**
     * Get the other participant in the chat.
     */
    public function getOtherParticipant(Profile $profile, Application $application): ?Profile
    {
        $application->loadMissing(['collabOpportunity.creatorProfile', 'applicantProfile']);

        if ($application->applicant_profile_id === $profile->id) {
            return $application->collabOpportunity->creatorProfile;
        }

        if ($application->collabOpportunity->creator_profile_id === $profile->id) {
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
                'application.collabOpportunity.creatorProfile.businessProfile',
                'application.collabOpportunity.creatorProfile.communityProfile',
            ]);

        if ($profile->isBusiness()) {
            $query->whereNotNull('last_message_at');
        }

        $threads = $query->orderByDesc('last_message_at')->get();

        $unreadByApplication = $this->getUnreadCountByApplication($profile);

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

        return ChatThread::query()->create([
            'type' => ChatThreadType::CommunityCustom->value,
            'community_id' => $community->id,
            'slug' => $this->uniqueChatSlug($community, $name),
            'name' => $name,
            'created_by' => $creator->id,
        ]);
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

        if ($thread->community_id === null) {
            return false;
        }

        if ($thread->type === ChatThreadType::CommunityMain) {
            return $this->isCommunityMemberOrOwner($profile, $thread->community_id);
        }

        if ($thread->type === ChatThreadType::CommunityCustom) {
            return $this->canAccessCustomChat($profile, $thread);
        }

        return false; // event chats arrive in Phase 3
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

        $visible = $threads->filter(function (ChatThread $thread) use ($ownedIds, $memberByCommunity): bool {
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

            return in_array($thread->slug, $this->tierChatChannels($member->tier), true);
        });

        foreach ($visible as $thread) {
            $thread->unread_count = $this->unreadForThread($profile, $thread);
        }

        return $visible->values();
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

        return in_array($thread->slug, $this->tierChatChannels($member->tier), true);
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
            ->whereHas('collabOpportunity', function ($q) use ($profile) {
                $q->where('creator_profile_id', $profile->id);
            })
            ->pluck('id');

        return $asApplicant->merge($asCreator)->unique();
    }
}
