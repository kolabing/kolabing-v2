<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ChallengeCompletionStatus;
use App\Enums\FileUploadType;
use App\Enums\MissionTrigger;
use App\Exceptions\ChallengeRuleException;
use App\Models\Challenge;
use App\Models\ChallengeCompletion;
use App\Models\CommunityChallenge;
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
        private readonly EncounterService $encounterService,
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
            throw new ChallengeRuleException(
                'already_pending',
                'You have already asked them to confirm this challenge.'
            );
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
                throw new ChallengeRuleException(
                    'already_completed',
                    'This challenge has already been initiated between these two attendees.'
                );
            }
        }

        // "Meet someone new" means someone you have not played with before —
        // either direction, any event, because "we already met" is symmetric and
        // the point is meeting someone new rather than meeting them tonight (§7).
        if ($rules?->requires_new_person === true
            && $this->pairHasPlayedBefore($challenger->id, $verifierProfileId)) {
            Log::warning('Challenge initiate blocked: not a new person', $ctx);
            throw new ChallengeRuleException(
                'needs_new_person',
                'This challenge is for meeting someone you have not played with before.'
            );
        }

        // A cap only if this event actually has one (kolabing-app#156).
        //
        // **Null means unlimited**, and that is now the default: the column used
        // to default to 10, so every event carried a limit nobody chose and
        // nothing in the product ever mentioned. §8 of the product model says no
        // cap for MVP — see what people do before restricting it. The mechanism
        // stays for the organizer who eventually wants one.
        $maxPerAttendee = $event->max_challenges_per_attendee;
        $completedCount = $maxPerAttendee === null ? 0 : ChallengeCompletion::query()
            ->where('event_id', $event->id)
            ->where('challenger_profile_id', $challenger->id)
            ->count();

        if ($maxPerAttendee !== null && $completedCount >= $maxPerAttendee) {
            Log::warning('Challenge initiate blocked: max challenges reached', $ctx + [
                'completed_count' => $completedCount,
                'max_per_attendee' => $maxPerAttendee,
            ]);
            throw new ChallengeRuleException(
                'event_limit_reached',
                'You have reached the maximum number of challenges for this event.'
            );
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
     * The challenger withdraws a request before it is answered
     * (kolabing-app#154).
     *
     * Only the challenger, because it is their ask. The verifier already has a
     * way out — Reject — and the two should stay distinct: "I changed my mind"
     * and "no, we did not do that" are different facts, and the second is the
     * one that says something about the pair.
     *
     * This became necessary with the flow reversal (kolabing-app#152): choosing
     * a challenge and then scanning means a mis-scan sends a real request to the
     * wrong person, and until now there was no way to take it back.
     *
     * @throws \InvalidArgumentException not the challenger
     * @throws \LogicException already answered
     */
    public function cancel(Profile $challenger, ChallengeCompletion $completion): ChallengeCompletion
    {
        if ($completion->challenger_profile_id !== $challenger->id) {
            throw new \InvalidArgumentException('Only the person who asked can cancel this.');
        }

        if (! $completion->isPending()) {
            throw new \LogicException('This challenge has already been answered.');
        }

        $completion->update(['status' => ChallengeCompletionStatus::Cancelled->value]);

        Log::info('Challenge cancelled by challenger', [
            'completion_id' => $completion->id,
            'challenger_profile_id' => $challenger->id,
        ]);

        return $completion->fresh(['challenge', 'event', 'challenger', 'verifier']);
    }

    /**
     * How long a request stays answerable after it is made, regardless of what
     * the event's dates say.
     *
     * 12 hours, matching the app's active check-in session
     * (`kActiveEventSessionTtl`), because the model's rule is "the end of the
     * event **or** the check-in session" and that session is what the two people
     * are actually inside.
     */
    private const REQUEST_TTL_HOURS = 12;

    /**
     * Whether a pending request has run out (kolabing-app#154).
     *
     * The event's window **or** REQUEST_TTL_HOURS from when it was asked,
     * whichever is LATER.
     *
     * "Later" is load-bearing, and it is the event's dates that make it
     * necessary. An event's recorded schedule is not always the truth: events
     * exist with only a date and no times, with dates in the past (retroactive
     * showcases are a first-class thing here), and with schedules that were
     * edited after the fact. Keying expiry on the window alone would mean a
     * request made ten seconds ago is already dead because someone typed
     * yesterday's date — so the session gives every request its own floor, and
     * the window is what closes a request that outlives a long event.
     */
    public function hasExpired(ChallengeCompletion $completion): bool
    {
        $askedAt = $completion->created_at;

        if ($askedAt === null) {
            return false;
        }

        $sessionEndsAt = $askedAt->copy()->addHours(self::REQUEST_TTL_HOURS);
        $windowEndsAt = $completion->event?->challengesCloseAt();

        $expiresAt = $windowEndsAt !== null && $windowEndsAt->isAfter($sessionEndsAt)
            ? $windowEndsAt
            : $sessionEndsAt;

        return $expiresAt->isPast();
    }

    /**
     * Mark every pending request whose event has closed (kolabing-app#154).
     *
     * Read paths refuse an expired request anyway, so this is about the data
     * telling the truth rather than about correctness — a table slowly filling
     * with rows that say "pending" about last month is how a status stops
     * meaning anything.
     *
     * @return int how many were expired
     */
    public function expireStale(): int
    {
        $expired = 0;

        ChallengeCompletion::query()
            ->where('status', ChallengeCompletionStatus::Pending->value)
            ->with('event')
            ->chunkById(200, function ($completions) use (&$expired): void {
                foreach ($completions as $completion) {
                    if (! $this->hasExpired($completion)) {
                        continue;
                    }

                    $completion->update(['status' => ChallengeCompletionStatus::Expired->value]);
                    $expired++;
                }
            });

        return $expired;
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

        // A request that outlived its event is not waiting for anything
        // (kolabing-app#154). Without this it could be confirmed days later, for
        // XP neither person earned in the room — and the sweep command runs
        // daily, so there is always a window where the row still says pending.
        if ($completion->isPending() && $this->hasExpired($completion)) {
            $completion->update(['status' => ChallengeCompletionStatus::Expired->value]);

            Log::warning('Challenge verify blocked: expired', [
                'completion_id' => $completion->id,
            ]);
            throw new \LogicException('This challenge expired when the event ended.');
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

        // Remember that these two MET (#244), and pay the pair ladder if this
        // event took them over a rung.
        //
        // Outside the settlement transaction and inside a catch, for the same
        // reason community points are: the points are the contract between two
        // people standing in a room, and this ledger is bookkeeping that
        // happens afterwards. A bug in here must never cost anyone what they
        // just earned.
        try {
            $rung = $this->encounterService->recordChallengeMeeting($result);
            if ($rung !== null && $rung['bonus_awarded'] > 0) {
                $this->encounterService->awardPairBonus($result, $rung['bonus_awarded']);
            }
            // Carried on the model rather than persisted: the reveal wants to
            // say "3rd time" once, and nothing else ever reads it back.
            $result->pairLevel = $rung;
        } catch (\Throwable $e) {
            Log::warning('Encounter write on challenge verify failed', [
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
            // Grouped, not chained: adding the status filter below to a bare
            // `where()->orWhere()` would read as
            // "(challenger = me) OR (verifier = me AND ...)" and quietly stop
            // filtering half the rows.
            ->where(function ($mine) use ($profile): void {
                $mine->where('challenger_profile_id', $profile->id)
                    ->orWhere('verifier_profile_id', $profile->id);
            })
            // Cancelled and expired requests are excluded, and this is a
            // COMPATIBILITY decision as much as a product one: shipped app
            // builds parse an unknown status by falling back to `pending`, so
            // any status they do not know about would surface on their poller as
            // a live request that can never be answered. Excluding them here is
            // what keeps those builds honest (kolabing-app#154).
            ->whereNotIn('status', [
                ChallengeCompletionStatus::Cancelled->value,
                ChallengeCompletionStatus::Expired->value,
            ])
            ->with(['challenge', 'event', 'challenger', 'verifier'])
            ->orderByDesc('created_at')
            ->paginate($perPage);
    }
}
