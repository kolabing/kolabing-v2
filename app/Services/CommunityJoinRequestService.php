<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CommunityMemberStatus;
use App\Enums\JoinPolicy;
use App\Enums\JoinRequestStatus;
use App\Enums\NotificationType;
use App\Models\Community;
use App\Models\CommunityJoinAnswer;
use App\Models\CommunityJoinQuestion;
use App\Models\CommunityJoinRequest;
use App\Models\Profile;
use DomainException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class CommunityJoinRequestService
{
    public function __construct(
        private readonly CommunityMemberService $memberService,
        private readonly NotificationService $notifications,
    ) {}

    /**
     * An attendee requests to join an invite_only community.
     *
     * Idempotent: if a pending request already exists it is returned as-is.
     * Open communities have no request flow (the caller should self-join);
     * we signal that with a 'community_is_open' DomainException.
     *
     * @throws DomainException 'community_is_open'
     */
    /**
     * Apply to join.
     *
     * @param  array<int, array{question_id: string, answer: string}>  $answers
     *
     * @throws DomainException `community_is_open` when an open community asks
     *                         nothing (the caller should use /join), or
     *                         `missing_required_answers`
     */
    public function request(
        Community $community,
        Profile $profile,
        array $answers = []
    ): CommunityJoinRequest {
        $questions = CommunityJoinQuestion::query()
            ->where('community_id', $community->id)
            ->activeOrdered()
            ->get();

        // An open community that asks nothing has no application to make — the
        // client should call /join, which is what it has always done. An open
        // community that DOES ask something now has a real application, so the
        // request path opens up and self-approves once answered.
        //
        // No community has questions until a leader creates one, so on the day
        // this deploys every existing community keeps behaving exactly as it
        // does today.
        $isOpen = $community->join_policy === JoinPolicy::Open;

        if ($isOpen && $questions->isEmpty()) {
            throw new DomainException('community_is_open');
        }

        // Existing pending request → return it (idempotent re-request).
        $pending = $community->joinRequests()
            ->where('profile_id', $profile->id)
            ->where('status', JoinRequestStatus::Pending->value)
            ->first();

        if ($pending !== null) {
            return $pending;
        }

        $this->assertRequiredAnswered($questions, $answers);

        return DB::transaction(function () use ($community, $profile, $questions, $answers, $isOpen): CommunityJoinRequest {
            $joinRequest = $community->joinRequests()->create([
                'profile_id' => $profile->id,
                'status' => JoinRequestStatus::Pending->value,
                'requested_at' => now(),
            ]);

            $this->storeAnswers($joinRequest, $questions, $answers);

            if ($isOpen) {
                // Open + questions: the answers are collected for the record,
                // and membership is granted immediately. Neither notification
                // fires — nobody is waiting on a decision, and telling the
                // applicant their own action was approved is noise.
                $this->memberService->addMember($community, $profile->id);

                $joinRequest->update([
                    'status' => JoinRequestStatus::Approved->value,
                    'decided_at' => now(),
                ]);

                return $joinRequest->refresh();
            }

            $this->notifyManagersOfNewRequest($community, $profile);

            return $joinRequest;
        });
    }

    /**
     * Every question marked required must have a non-empty answer.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, CommunityJoinQuestion>  $questions
     * @param  array<int, array{question_id: string, answer: string}>  $answers
     *
     * @throws DomainException
     */
    private function assertRequiredAnswered($questions, array $answers): void
    {
        $given = [];
        foreach ($answers as $answer) {
            $id = $answer['question_id'] ?? null;
            $text = trim((string) ($answer['answer'] ?? ''));
            if ($id !== null && $text !== '') {
                $given[$id] = $text;
            }
        }

        foreach ($questions as $question) {
            if ($question->required && ! array_key_exists($question->id, $given)) {
                throw new DomainException('missing_required_answers');
            }
        }
    }

    /**
     * Persists the answers, ignoring anything that does not belong to this
     * community's active set — a client cannot smuggle in an answer to another
     * community's question.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, CommunityJoinQuestion>  $questions
     * @param  array<int, array{question_id: string, answer: string}>  $answers
     */
    private function storeAnswers(
        CommunityJoinRequest $joinRequest,
        $questions,
        array $answers
    ): void {
        $allowed = $questions->pluck('id')->all();

        foreach ($answers as $answer) {
            $questionId = $answer['question_id'] ?? null;
            $text = trim((string) ($answer['answer'] ?? ''));

            if ($questionId === null || $text === '' || ! in_array($questionId, $allowed, true)) {
                continue;
            }

            CommunityJoinAnswer::query()->updateOrCreate(
                ['join_request_id' => $joinRequest->id, 'question_id' => $questionId],
                ['answer' => $text],
            );
        }
    }

    /**
     * Pending requests for a community, requester profile eager-loaded.
     *
     * @return Collection<int, CommunityJoinRequest>
     */
    public function pending(Community $community): Collection
    {
        return $community->joinRequests()
            ->where('status', JoinRequestStatus::Pending->value)
            ->with([
                'profile.attendeeProfile', 'profile.communityProfile', 'profile.businessProfile',
                // The answers are the point of the queue; loading the question
                // too keeps a retired one's prompt readable.
                'answers.question',
            ])
            ->orderBy('requested_at')
            ->get();
    }

    /**
     * Approve a pending request: create the member (default tier, active),
     * mark approved, notify the requester. Guards against double-approve.
     *
     * @throws DomainException 'already_decided'
     */
    public function approve(CommunityJoinRequest $joinRequest, Profile $decider): CommunityJoinRequest
    {
        if ($joinRequest->status !== JoinRequestStatus::Pending) {
            throw new DomainException('already_decided');
        }

        return DB::transaction(function () use ($joinRequest, $decider): CommunityJoinRequest {
            $community = $joinRequest->community;

            // Create the membership exactly like a normal join (default tier, active).
            $this->memberService->addMember($community, $joinRequest->profile_id);

            $joinRequest->update([
                'status' => JoinRequestStatus::Approved->value,
                'decided_by' => $decider->id,
                'decided_at' => now(),
            ]);

            $this->notifyRequester(
                $joinRequest,
                NotificationType::CommunityJoinApproved,
                'notifications.community.join_approved.title',
                'notifications.community.join_approved.body',
                ['community' => $community->name],
            );

            return $joinRequest->refresh();
        });
    }

    /**
     * Decline a pending request: mark declined, notify the requester.
     *
     * @throws DomainException 'already_decided'
     */
    public function decline(CommunityJoinRequest $joinRequest, Profile $decider): CommunityJoinRequest
    {
        if ($joinRequest->status !== JoinRequestStatus::Pending) {
            throw new DomainException('already_decided');
        }

        $community = $joinRequest->community;

        $joinRequest->update([
            'status' => JoinRequestStatus::Declined->value,
            'decided_by' => $decider->id,
            'decided_at' => now(),
        ]);

        $this->notifyRequester(
            $joinRequest,
            NotificationType::CommunityJoinDeclined,
            'notifications.community.join_declined.title',
            'notifications.community.join_declined.body',
            ['community' => $community->name],
        );

        return $joinRequest->refresh();
    }

    /**
     * The viewer's current request status for a community, or null.
     */
    public function statusFor(Community $community, Profile $profile): ?JoinRequestStatus
    {
        $request = $community->joinRequests()
            ->where('profile_id', $profile->id)
            ->orderByDesc('created_at')
            ->first();

        return $request?->status;
    }

    /**
     * Notify the owner + active can_manage members that a new request landed.
     */
    private function notifyManagersOfNewRequest(Community $community, Profile $requester): void
    {
        $managerIds = $community->members()
            ->where('can_manage', true)
            ->where('status', CommunityMemberStatus::Active->value)
            ->pluck('profile_id')
            ->all();

        $managerIds[] = $community->owner_profile_id;
        $managerIds = array_values(array_unique(array_filter($managerIds)));

        $requesterName = $requester->getExtendedProfile()?->name ?? $requester->name ?? __('Someone');

        foreach (Profile::query()->whereIn('id', $managerIds)->get() as $manager) {
            $this->notifications->createLocalizedNotification(
                recipient: $manager,
                type: NotificationType::CommunityJoinRequested,
                titleKey: 'notifications.community.join_requested.title',
                bodyKey: 'notifications.community.join_requested.body',
                replace: ['name' => $requesterName, 'community' => $community->name],
                actor: $requester,
                targetId: $community->id,
                targetType: 'community',
            );
        }
    }

    /**
     * @param  array<string, string|int>  $replace
     */
    private function notifyRequester(
        CommunityJoinRequest $joinRequest,
        NotificationType $type,
        string $titleKey,
        string $bodyKey,
        array $replace = [],
    ): void {
        $requester = $joinRequest->profile;

        if ($requester === null) {
            return;
        }

        $this->notifications->createLocalizedNotification(
            recipient: $requester,
            type: $type,
            titleKey: $titleKey,
            bodyKey: $bodyKey,
            replace: $replace,
            targetId: $joinRequest->community_id,
            targetType: 'community',
        );
    }
}
