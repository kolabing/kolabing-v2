<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ChallengeCompletionStatus;
use App\Enums\MissionTrigger;
use App\Models\Challenge;
use App\Models\ChallengeCompletion;
use App\Models\CommunityChallenge;
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
     * Whether these two have ever completed a challenge together, in either
     * direction, at any event.
     *
     * The model asks Kolabing to "remember internally that A and B completed
     * something together so we can use it for challenge rules" (§17) — this is
     * that memory being used, and it needs no friends table to exist.
     */
    private function pairHasPlayedBefore(string $a, string $b): bool
    {
        return ChallengeCompletion::query()
            ->where('status', ChallengeCompletionStatus::Verified->value)
            ->where(function ($q) use ($a, $b): void {
                $q->where(function ($forward) use ($a, $b): void {
                    $forward->where('challenger_profile_id', $a)
                        ->where('verifier_profile_id', $b);
                })->orWhere(function ($back) use ($a, $b): void {
                    $back->where('challenger_profile_id', $b)
                        ->where('verifier_profile_id', $a);
                });
            })
            ->exists();
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

        // Everything below runs inside one transaction with the event row locked.
        //
        // Dropping `challenge_completions_unique` (kolabing-app#150) was the cost
        // of making "can a pair repeat" a community choice, and it took the
        // database-level guard against a concurrent double write with it. This
        // lock replaces it by serialising initiates per event, so the duplicate
        // checks below cannot both pass for two requests racing. Initiates are
        // rare and per-event contention is not a problem.
        //
        // No-op on SQLite, so the suite exercises the checks rather than the
        // locking. It is for production Postgres.
        return DB::transaction(function () use ($challenge, $event, $challenger, $verifierProfileId): ChallengeCompletion {
            Event::query()->whereKey($event->id)->lockForUpdate()->first();

            return $this->createCompletion($challenge, $event, $challenger, $verifierProfileId);
        });
    }

    /**
     * The guards and the write, run under initiate()'s lock.
     *
     * @throws \InvalidArgumentException a precondition of being in the room
     * @throws \LogicException a rule about repeating or about who
     */
    private function createCompletion(
        Challenge $challenge,
        Event $event,
        Profile $challenger,
        string $verifierProfileId
    ): ChallengeCompletion {

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

        // The community's rules for this challenge (kolabing-app#150). No row
        // means the community has not curated, which means the old behaviour:
        // no repeats, no new-person requirement.
        $rules = $event->community_id === null ? null : CommunityChallenge::query()
            ->where('community_id', $event->community_id)
            ->where('challenge_id', $challenge->id)
            ->first();

        $allowRepeat = $rules?->allow_repeat_with_same_person ?? false;

        // ALWAYS: never two live requests for the same thing between the same two
        // people. Whatever a community allows about repeating, two pending
        // confirmations for one challenge is never what anyone meant (§19).
        $pending = ChallengeCompletion::query()
            ->where('challenge_id', $challenge->id)
            ->where('event_id', $event->id)
            ->where('challenger_profile_id', $challenger->id)
            ->where('verifier_profile_id', $verifierProfileId)
            ->where('status', ChallengeCompletionStatus::Pending->value)
            ->exists();

        if ($pending) {
            Log::warning('Challenge initiate blocked: already pending for this pair', $ctx);
            throw new \LogicException('You have already asked them to confirm this challenge.');
        }

        // Repeating a finished one is the COMMUNITY's choice (§6). Off by
        // default, which is exactly the rule the dropped unique index enforced.
        if (! $allowRepeat) {
            $done = ChallengeCompletion::query()
                ->where('challenge_id', $challenge->id)
                ->where('event_id', $event->id)
                ->where('challenger_profile_id', $challenger->id)
                ->where('verifier_profile_id', $verifierProfileId)
                ->where('status', ChallengeCompletionStatus::Verified->value)
                ->exists();

            if ($done) {
                Log::warning('Challenge initiate blocked: duplicate pair', $ctx);
                throw new \LogicException('This challenge has already been initiated between these two attendees.');
            }
        }

        // "Meet someone new" means someone you have not played with before —
        // either direction, any event, because "we already met" is symmetric and
        // the point is meeting someone new rather than meeting them tonight (§7).
        if ($rules?->requires_new_person === true
            && $this->pairHasPlayedBefore($challenger->id, $verifierProfileId)) {
            Log::warning('Challenge initiate blocked: not a new person', $ctx);
            throw new \LogicException('This challenge is for meeting someone you have not played with before.');
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
