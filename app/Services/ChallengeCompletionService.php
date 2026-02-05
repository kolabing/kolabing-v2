<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ChallengeCompletionStatus;
use App\Models\Challenge;
use App\Models\ChallengeCompletion;
use App\Models\Event;
use App\Models\EventCheckin;
use App\Models\Profile;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ChallengeCompletionService
{
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

        // Validate: challenger is checked in to the event
        $challengerCheckedIn = EventCheckin::query()
            ->where('event_id', $event->id)
            ->where('profile_id', $challenger->id)
            ->exists();

        if (! $challengerCheckedIn) {
            throw new \InvalidArgumentException('You must be checked in to the event to initiate a challenge.');
        }

        // Validate: verifier is checked in to the event
        $verifierCheckedIn = EventCheckin::query()
            ->where('event_id', $event->id)
            ->where('profile_id', $verifierProfileId)
            ->exists();

        if (! $verifierCheckedIn) {
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
            throw new \LogicException('This challenge has already been initiated between these two attendees.');
        }

        // Validate: challenger hasn't exceeded event's max_challenges_per_attendee
        $completedCount = ChallengeCompletion::query()
            ->where('event_id', $event->id)
            ->where('challenger_profile_id', $challenger->id)
            ->count();

        if ($completedCount >= $event->max_challenges_per_attendee) {
            throw new \LogicException('You have reached the maximum number of challenges for this event.');
        }

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
            throw new \InvalidArgumentException('You are not the designated verifier for this challenge.');
        }

        if (! $completion->isPending()) {
            throw new \LogicException('This challenge completion has already been processed.');
        }

        return DB::transaction(function () use ($completion): ChallengeCompletion {
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
