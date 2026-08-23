<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ChallengeCompletionStatus;
use App\Enums\MissionTrigger;
use App\Models\Challenge;
use App\Models\ChallengeCompletion;
use App\Models\Event;
use App\Models\EventCheckin;
use App\Models\Profile;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ChallengeCompletionService
{
    public function __construct(
        private readonly BadgeService $badgeService,
        private readonly NotificationService $notificationService,
        private readonly CommunityPointsService $communityPointsService,
        private readonly MissionService $missionService,
    ) {}

    /**
     * Initiate a peer-to-peer challenge between two checked-in attendees.
     *
     * @param  array{challenge_id: string, event_id: string, verifier_profile_id: string}  $data
     */
    public function initiate(Profile $challenger, array $data): ChallengeCompletion
    {
        $challenge = Challenge::query()->findOrFail($data['challenge_id']);
        $event = Event::query()->findOrFail($data['event_id']);
        $verifierProfileId = $data['verifier_profile_id'];

        // Structured audit context for every guard below, so a failed completion
        // attempt is diagnosable from the logs (which precondition blocked it).
        $ctx = [
            'challenger_profile_id' => $challenger->id,
            'verifier_profile_id' => $verifierProfileId,
            'event_id' => $event->id,
            'challenge_id' => $challenge->id,
            'event_is_active' => $event->is_active,
        ];

        // Validate: challenger is checked in to the event
        $challengerCheckedIn = EventCheckin::query()
            ->where('event_id', $event->id)
            ->where('profile_id', $challenger->id)
            ->exists();

        if (! $challengerCheckedIn) {
            Log::warning('Challenge initiate blocked: challenger not checked in', $ctx);
            throw new \InvalidArgumentException('You must be checked in to the event to initiate a challenge.');
        }

        // Validate: verifier is checked in to the event
        $verifierCheckedIn = EventCheckin::query()
            ->where('event_id', $event->id)
            ->where('profile_id', $verifierProfileId)
            ->exists();

        if (! $verifierCheckedIn) {
            Log::warning('Challenge initiate blocked: verifier not checked in', $ctx);
            throw new \InvalidArgumentException('The verifier must be checked in to the event.');
        }

        // Validate: same challenge between same pair not already done
        $existing = ChallengeCompletion::query()
            ->where('challenge_id', $challenge->id)
            ->where('event_id', $event->id)
            ->where('challenger_profile_id', $challenger->id)
            ->where('verifier_profile_id', $verifierProfileId)
            ->exists();

        if ($existing) {
            Log::warning('Challenge initiate blocked: duplicate pair', $ctx);
            throw new \LogicException('This challenge has already been initiated between these two attendees.');
        }

        // Validate: challenger hasn't exceeded event's max_challenges_per_attendee.
        // Guard the null case: a raw-created event with no value must not coerce
        // `0 >= null` to true and block the very first challenge.
        $maxPerAttendee = $event->max_challenges_per_attendee ?? 10;
        $completedCount = ChallengeCompletion::query()
            ->where('event_id', $event->id)
            ->where('challenger_profile_id', $challenger->id)
            ->count();

        if ($completedCount >= $maxPerAttendee) {
            Log::warning('Challenge initiate blocked: max challenges reached', $ctx + [
                'completed_count' => $completedCount,
                'max_per_attendee' => $maxPerAttendee,
            ]);
            throw new \LogicException('You have reached the maximum number of challenges for this event.');
        }

        Log::info('Challenge initiated', $ctx);

        $completion = ChallengeCompletion::query()->create([
            'challenge_id' => $challenge->id,
            'event_id' => $event->id,
            'challenger_profile_id' => $challenger->id,
            'verifier_profile_id' => $verifierProfileId,
            'status' => ChallengeCompletionStatus::Pending,
            'points_earned' => 0,
        ]);

        return $completion->load(['challenge', 'event', 'challenger', 'verifier']);
    }

    /**
     * Verify a pending challenge completion and award points.
     */
    public function verify(Profile $verifier, ChallengeCompletion $completion): ChallengeCompletion
    {
        if ($completion->verifier_profile_id !== $verifier->id) {
            Log::warning('Challenge verify blocked: wrong verifier', [
                'completion_id' => $completion->id,
                'acting_profile_id' => $verifier->id,
                'designated_verifier_id' => $completion->verifier_profile_id,
            ]);
            throw new \InvalidArgumentException('You are not the designated verifier for this challenge.');
        }

        if (! $completion->isPending()) {
            Log::warning('Challenge verify blocked: not pending', [
                'completion_id' => $completion->id,
                'status' => $completion->status->value ?? (string) $completion->status,
            ]);
            throw new \LogicException('This challenge completion has already been processed.');
        }

        $result = DB::transaction(function () use ($completion): ChallengeCompletion {
            $points = $completion->challenge->points;

            $completion->update([
                'status' => ChallengeCompletionStatus::Verified,
                'completed_at' => now(),
                'points_earned' => $points,
            ]);

            // BOTH participants earn (kolabing-app#140).
            //
            // Previously only the challenger was credited, which made
            // confirming unpaid labour: the second time you asked someone to
            // verify you, you were asking a favour, and the natural equilibrium
            // was people avoiding the verifier role. A challenge is something
            // two people did together, so it pays both. `points_earned` on the
            // completion is therefore what EACH side earned, not a total.
            foreach ([$completion->challenger, $completion->verifier] as $participant) {
                if ($participant === null) {
                    continue;
                }
                if ($participant->isAttendee() && $participant->attendeeProfile) {
                    $participant->attendeeProfile->increment('total_points', $points);
                    $participant->attendeeProfile->increment('total_challenges_completed');
                }
            }

            return $completion->load(['challenge', 'event', 'challenger', 'verifier']);
        });

        // Send challenge verified notification (after transaction)
        $this->notificationService->notifyChallengeVerified($result);

        // Badge milestones for both, since both totals moved.
        foreach ([$result->challenger, $result->verifier] as $participant) {
            if ($participant === null) {
                continue;
            }
            $participant->attendeeProfile?->refresh();
            $this->badgeService->checkAndAwardBadges($participant);
        }

        // Per-community POINTS earn (+ mirrored global XP) when the challenge's
        // event is community-linked and the challenger is an active member.
        try {
            $this->communityPointsService->awardChallengeVerified($result);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Community points award on challenge verify failed', [
                'completion_id' => $result->id,
                'error' => $e->getMessage(),
            ]);
        }

        // Progress the attendee challenge missions (e.g. "complete N event
        // challenges") for both — they both completed a challenge.
        foreach ([$result->challenger, $result->verifier] as $participant) {
            if ($participant !== null) {
                $this->missionService->recordSafely(
                    $participant,
                    MissionTrigger::ChallengeCompleted
                );
            }
        }

        return $result;
    }

    /**
     * Reject a pending challenge completion.
     */
    public function reject(Profile $verifier, ChallengeCompletion $completion): ChallengeCompletion
    {
        if ($completion->verifier_profile_id !== $verifier->id) {
            throw new \InvalidArgumentException('You are not the designated verifier for this challenge.');
        }

        if (! $completion->isPending()) {
            throw new \LogicException('This challenge completion has already been processed.');
        }

        $completion->update([
            'status' => ChallengeCompletionStatus::Rejected,
        ]);

        return $completion->load(['challenge', 'event', 'challenger', 'verifier']);
    }

    /**
     * Get challenge completions where the profile is either challenger or verifier.
     */
    public function getMyCompletions(Profile $profile, int $perPage = 10): LengthAwarePaginator
    {
        return ChallengeCompletion::query()
            ->where('challenger_profile_id', $profile->id)
            ->orWhere('verifier_profile_id', $profile->id)
            ->with(['challenge', 'event', 'challenger', 'verifier'])
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }
}
