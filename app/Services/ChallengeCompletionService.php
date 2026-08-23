<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ChallengeCompletionStatus;
use App\Enums\FileUploadType;
use App\Enums\MissionTrigger;
use App\Models\Challenge;
use App\Models\ChallengeCompletion;
use App\Models\Event;
use App\Models\EventCheckin;
use App\Models\Profile;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ChallengeCompletionService
{
    public function __construct(
        private readonly BadgeService $badgeService,
        private readonly NotificationService $notificationService,
        private readonly CommunityPointsService $communityPointsService,
        private readonly MissionService $missionService,
        private readonly FileUploadService $fileUploadService,
    ) {}

    /**
     * Attach (or replace) the photo the pair took (kolabing-v2#216).
     *
     * Either participant may do it. Two people are in that picture, and which of
     * them happened to press the button first is not a permission model.
     *
     * Allowed while `pending` and after `verified`, refused once `rejected`:
     * before, because the camera opens as soon as the pair agrees and the photo
     * exists before the confirmation does; after, because people remember to
     * keep it later. Rejected means the thing did not happen, so there is
     * nothing to illustrate.
     *
     * Replacing deletes the old file rather than orphaning it — one completion,
     * one photo, and the disk should say the same.
     *
     * @throws \InvalidArgumentException not a participant
     * @throws \LogicException the completion was rejected
     */
    public function attachProofPhoto(
        Profile $actor,
        ChallengeCompletion $completion,
        UploadedFile $photo
    ): ChallengeCompletion {
        $this->assertParticipant($actor, $completion);

        if ($completion->status === ChallengeCompletionStatus::Rejected) {
            throw new \LogicException('This challenge was not confirmed, so there is nothing to add a photo to.');
        }

        $previous = $completion->proof_photo_url;

        // Returns an absolute URL; delete() takes either that or a raw path.
        $url = $this->fileUploadService->uploadFromFile(
            $photo,
            FileUploadType::ChallengeProof,
            $completion->id
        );

        $completion->update(['proof_photo_url' => $url]);

        if ($previous !== null && $previous !== $url) {
            // A failed delete must not fail the upload the user just waited for.
            try {
                $this->fileUploadService->delete($previous);
            } catch (\Throwable $e) {
                Log::warning('Challenge proof photo replace: old file not deleted', [
                    'completion_id' => $completion->id,
                    'path' => $previous,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $completion->fresh(['challenge', 'challenger', 'verifier']);
    }

    /**
     * Remove the photo. Either participant, for the same reason as attaching:
     * both of them are in it, so both can take it down.
     *
     * @throws \InvalidArgumentException not a participant
     */
    public function removeProofPhoto(Profile $actor, ChallengeCompletion $completion): ChallengeCompletion
    {
        $this->assertParticipant($actor, $completion);

        $url = $completion->proof_photo_url;
        $completion->update(['proof_photo_url' => null]);

        if ($url !== null) {
            try {
                $this->fileUploadService->delete($url);
            } catch (\Throwable $e) {
                // The row is already clear, which is what the caller asked for.
                Log::warning('Challenge proof photo delete: file not removed', [
                    'completion_id' => $completion->id,
                    'url' => $url,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $completion->fresh(['challenge', 'challenger', 'verifier']);
    }

    /**
     * @throws \InvalidArgumentException
     */
    private function assertParticipant(Profile $actor, ChallengeCompletion $completion): void
    {
        $isParticipant = $actor->id === $completion->challenger_profile_id
            || $actor->id === $completion->verifier_profile_id;

        if (! $isParticipant) {
            throw new \InvalidArgumentException('Only the two people in this challenge can change its photo.');
        }
    }

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

            // Increment attendee profile stats
            $challengerProfile = $completion->challenger;
            if ($challengerProfile->isAttendee() && $challengerProfile->attendeeProfile) {
                $challengerProfile->attendeeProfile->increment('total_points', $points);
                $challengerProfile->attendeeProfile->increment('total_challenges_completed');
            }

            return $completion->load(['challenge', 'event', 'challenger', 'verifier']);
        });

        // Send challenge verified notification (after transaction)
        $this->notificationService->notifyChallengeVerified($result);

        // Check for badge milestones (after transaction)
        $result->challenger->attendeeProfile?->refresh();
        $this->badgeService->checkAndAwardBadges($result->challenger);

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
        // challenges"). The challenger is the earner; verification is the
        // source action for the `challenge_completed` mission trigger.
        $this->missionService->recordSafely($result->challenger, MissionTrigger::ChallengeCompleted);

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
