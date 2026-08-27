<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ChallengeCompletion;
use App\Models\Encounter;
use App\Models\Profile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Writes the People Layer (#244).
 *
 * The challenge loop already pays two people for something they did together.
 * What it has never done is remember that they **met**. This service is the one
 * place that fact gets written, and the one place the pair ladder is read.
 *
 * ## What "a meeting" means
 *
 * An EVENT, not a challenge. Two people who do ten challenges in one night met
 * once. `encounters` enforces that with a unique index on
 * `(profile_id, other_profile_id, event_id)`, so this service does not have to
 * count anything defensively — it inserts, and a duplicate simply does not
 * happen.
 *
 * ## What this service must never do
 *
 * **Break a verification.** The points are the contract between two people
 * standing in a room; this ledger is bookkeeping that happens afterwards. So
 * every entry point here is called *outside* the settlement transaction and
 * every failure is caught and logged, exactly as community points already are
 * in `ChallengeCompletionService::verify`. A bug in here must never cost anyone
 * what they earned.
 *
 * **Create a friendship.** An encounter is a fact; a friendship is a choice.
 * Nothing here touches `friendships` — the app offers that separately, and the
 * person decides.
 */
class EncounterService
{
    /**
     * Record that the two people on a verified completion met, in both
     * directions, and return the challenger's new rung if they crossed one.
     *
     * Returns null when there was nothing to record (a missing participant, a
     * non-attendee, or a meeting already on file for this event).
     *
     * @return array{times_met:int,key:string,next_at:int|null,just_levelled_up:bool,bonus_awarded:int}|null
     */
    public function recordChallengeMeeting(ChallengeCompletion $completion): ?array
    {
        $challenger = $completion->challenger;
        $verifier = $completion->verifier;

        if ($challenger === null || $verifier === null) {
            return null;
        }

        // The same profile on both ends is not a meeting. It should be
        // impossible upstream; it is cheap to refuse here rather than write a
        // row that means nothing.
        if ($challenger->id === $verifier->id) {
            return null;
        }

        $communityId = $completion->event?->community_id;
        $photo = $completion->proof_photo_url;
        $metAt = $completion->completed_at ?? now();

        $forChallenger = $this->write(
            $challenger,
            $verifier,
            $completion->event_id,
            $communityId,
            $photo,
            $metAt
        );

        $this->write(
            $verifier,
            $challenger,
            $completion->event_id,
            $communityId,
            $photo,
            $metAt
        );

        if ($forChallenger === null) {
            return null;
        }

        return $this->rungFor($forChallenger->times_met);
    }

    /**
     * One direction. Returns the row written, or null if this pair already had
     * this event on file — which is exactly the ten-challenges-in-one-night
     * case, and is not an error.
     */
    private function write(
        Profile $viewer,
        Profile $other,
        string $eventId,
        ?string $communityId,
        ?string $photoUrl,
        \DateTimeInterface $metAt
    ): ?Encounter {
        $existing = Encounter::query()
            ->where('profile_id', $viewer->id)
            ->where('other_profile_id', $other->id)
            ->where('event_id', $eventId)
            ->first();

        if ($existing !== null) {
            // A second challenge with the same person at the same event. The
            // meeting is already recorded; the only thing worth carrying over
            // is a photo, if this is the first challenge of the night that
            // produced one.
            if ($photoUrl !== null && $existing->proof_photo_url === null) {
                $existing->update(['proof_photo_url' => $photoUrl]);
            }

            return null;
        }

        // How many events this pair had already shared. The new row is the next
        // one, and its number is frozen at write time — see the migration.
        $previous = Encounter::query()
            ->where('profile_id', $viewer->id)
            ->where('other_profile_id', $other->id)
            ->count();

        return Encounter::create([
            'profile_id' => $viewer->id,
            'other_profile_id' => $other->id,
            'community_id' => $communityId,
            'event_id' => $eventId,
            'met_at' => $metAt,
            'times_met' => $previous + 1,
            'proof_photo_url' => $photoUrl,
        ]);
    }

    /**
     * Where `$timesMet` lands on the ladder, and what crossing it just paid.
     *
     * The ladder is config (`gamification.pair_ladder`), never constants: what
     * a repeat meeting is worth is a product decision that should be tunable
     * without a deploy, and certainly without a mobile release.
     *
     * @return array{times_met:int,key:string,next_at:int|null,just_levelled_up:bool,bonus_awarded:int}
     */
    public function rungFor(int $timesMet): array
    {
        /** @var list<array{at:int,key:string,bonus:int}> $ladder */
        $ladder = config('gamification.pair_ladder', []);
        usort($ladder, static fn (array $a, array $b): int => $a['at'] <=> $b['at']);

        $current = null;
        $next = null;
        foreach ($ladder as $rung) {
            if ($timesMet >= $rung['at']) {
                $current = $rung;
            } elseif ($next === null) {
                $next = $rung;
            }
        }

        // A count below the first rung still reads as the first rung: you have
        // met them, whatever the config says the bottom of the ladder is.
        $current ??= $ladder[0] ?? ['at' => 1, 'key' => 'met', 'bonus' => 0];

        // Crossed only when the count lands EXACTLY on a rung. Landing past one
        // is impossible (times_met moves by one) but saying `===` rather than
        // `>=` means a config change that removes a rung cannot retroactively
        // pay a bonus for a threshold nobody crossed.
        $justCrossed = $timesMet === $current['at'];

        return [
            'times_met' => $timesMet,
            'key' => $current['key'],
            'next_at' => $next['at'] ?? null,
            'just_levelled_up' => $justCrossed && $current['bonus'] > 0,
            'bonus_awarded' => $justCrossed ? $current['bonus'] : 0,
        ];
    }

    /**
     * Pay the one-time bonus for a crossed rung to both participants.
     *
     * Separate from [recordChallengeMeeting] because points and bookkeeping
     * fail differently: a ledger row that does not get written costs a memory,
     * a bonus that does not get paid costs trust. This one gets its own
     * transaction and its own log line.
     */
    public function awardPairBonus(ChallengeCompletion $completion, int $bonus): void
    {
        if ($bonus <= 0) {
            return;
        }

        DB::transaction(function () use ($completion, $bonus): void {
            foreach ([$completion->challenger, $completion->verifier] as $participant) {
                if ($participant === null) {
                    continue;
                }
                if ($participant->isAttendee() && $participant->attendeeProfile) {
                    $participant->attendeeProfile->increment('total_points', $bonus);
                }
            }
        });

        Log::info('Pair ladder bonus awarded', [
            'completion_id' => $completion->id,
            'bonus' => $bonus,
        ]);
    }
}
