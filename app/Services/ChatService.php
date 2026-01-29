<?php

declare(strict_types=1);

namespace App\Services;

use App\Events\NewChatMessage;
use App\Models\Application;
use App\Models\ChatMessage;
use App\Models\Profile;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class ChatService
{
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

        $message = ChatMessage::query()->create([
            'application_id' => $application->id,
            'sender_profile_id' => $sender->id,
            'content' => $data['content'],
        ]);

        $message->load('senderProfile.businessProfile', 'senderProfile.communityProfile');

        // Broadcast the new message event
        broadcast(new NewChatMessage($message))->toOthers();

        return $message;
    }

    /**
     * Mark messages as read for a user in an application.
     */
    public function markMessagesAsRead(Profile $reader, Application $application): int
    {
        return ChatMessage::query()
            ->where('application_id', $application->id)
            ->where('sender_profile_id', '!=', $reader->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
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
