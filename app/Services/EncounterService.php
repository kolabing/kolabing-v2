<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\ChallengeRuleException;
use App\Models\Challenge;
use App\Models\ChallengeCompletion;
use App\Models\Encounter;
use App\Models\Event;
use App\Models\EventCheckin;
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

    // -------------------------------------------------------------------------
    // Ghost invites (#246)
    // -------------------------------------------------------------------------

    /**
     * How long a code stays redeemable. Long enough that "I'll download it
     * later" survives the walk home and the following weekend; short enough
     * that a code is not a permanent liability.
     */
    public const CLAIM_WINDOW_DAYS = 30;

    /**
     * How many unclaimed ghosts one attendee may hold at one event.
     *
     * Three is enough for a genuinely social night and low enough that
     * inventing imaginary friends is not worth anyone's evening.
     */
    public const MAX_UNCLAIMED_PER_EVENT = 3;

    /**
     * Record a challenge with someone who does not have the app, and hand back
     * something the inviter can actually send.
     *
     * **Nothing is paid here.** The points are frozen onto the row as
     * `pending_points` and named on the inviter's screen — "Ana gets 15 XP for
     * both of you when she joins" — and released only on a claim. Paying up
     * front invites imaginary friends; paying nothing means nobody bothers to
     * send the invite. A visible, named, pending reward is the honest middle,
     * and loss aversion does the rest.
     *
     * @throws ChallengeRuleException `not_checked_in` | `ghost_limit_reached`
     */
    public function createGhostInvite(
        Profile $inviter,
        Event $event,
        Challenge $challenge,
        string $ghostName,
        ?string $ghostContact = null,
    ): Encounter {
        // The invite only means anything if the inviter is actually at the
        // event. Without this the whole mechanism is a points faucet you can
        // turn on from your sofa.
        $checkedIn = EventCheckin::query()
            ->where('event_id', $event->id)
            ->where('profile_id', $inviter->id)
            ->exists();

        if (! $checkedIn) {
            throw new ChallengeRuleException(
                'not_checked_in',
                'You have to be checked in to this event to invite someone.'
            );
        }

        $unclaimed = Encounter::query()
            ->where('profile_id', $inviter->id)
            ->where('event_id', $event->id)
            ->whereNull('other_profile_id')
            ->whereNull('claimed_at')
            ->where(function ($q): void {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->count();

        if ($unclaimed >= self::MAX_UNCLAIMED_PER_EVENT) {
            throw new ChallengeRuleException(
                'ghost_limit_reached',
                'You already have '.self::MAX_UNCLAIMED_PER_EVENT.' invites waiting from this event.'
            );
        }

        return Encounter::create([
            'profile_id' => $inviter->id,
            'other_profile_id' => null,
            'ghost_name' => trim($ghostName),
            'ghost_claim_token' => $this->generateClaimCode(),
            'ghost_contact' => $ghostContact !== null ? trim($ghostContact) : null,
            'challenge_id' => $challenge->id,
            'community_id' => $event->community_id,
            'event_id' => $event->id,
            'met_at' => now(),
            'times_met' => 1,
            'pending_points' => $challenge->points,
            'expires_at' => now()->addDays(self::CLAIM_WINDOW_DAYS),
        ]);
    }

    /**
     * Redeem a claim code: fill in the ghost, write the reverse row, and pay
     * both sides what was promised.
     *
     * Reached from two doors for one code — the deep-link handler when the app
     * was already installed, and the onboarding field when it was not. A
     * Universal Link cannot carry a token through the App Store, and the whole
     * point of this feature is a person who has to go through the App Store.
     *
     * **No `ChallengeCompletion` is fabricated.** Nobody verified anything and
     * the two were never checked in together; a fake completion would put
     * something that did not happen into challenge stats and mission progress.
     * The meeting is real, so the encounter is real, and the points are paid
     * directly.
     *
     * Returns the CLAIMER's own row, not the inviter's: the person who just
     * typed the code wants to read "you met Ana", and on the inviter's row they
     * are the `other`, which reads backwards.
     *
     * @throws ChallengeRuleException `invalid_claim_code` | `claim_expired` |
     *                                `claim_requires_new_account` | `claim_self`
     */
    public function claim(Profile $claimer, string $code): Encounter
    {
        $normalized = strtoupper(trim($code));

        /** @var Encounter|null $ghost */
        $ghost = Encounter::query()
            ->where('ghost_claim_token', $normalized)
            ->first();

        if ($ghost === null || ! $ghost->isGhost() || $ghost->claimed_at !== null) {
            throw new ChallengeRuleException(
                'invalid_claim_code',
                'That invite code is not valid.'
            );
        }

        if ($ghost->expires_at !== null && $ghost->expires_at->isPast()) {
            throw new ChallengeRuleException(
                'claim_expired',
                'That invite has expired.'
            );
        }

        if ($ghost->profile_id === $claimer->id) {
            throw new ChallengeRuleException(
                'claim_self',
                'You cannot claim your own invite.'
            );
        }

        // The ghost path means "this person isn't on Kolabing". An account that
        // already existed when the invite was written is, by definition, not
        // that person — so it has no business claiming, and this is what stops
        // two existing users farming each other.
        //
        // Strictly BEFORE, not before-or-equal. Timestamps here have second
        // resolution, and the honest reading of "created in the same second as
        // the invite" is a brand new account, not a pre-existing one. An
        // account that really predates the invite is minutes or days older, so
        // the rule still catches every case it is there to catch.
        if ($claimer->created_at !== null && $claimer->created_at->lt($ghost->created_at)) {
            throw new ChallengeRuleException(
                'claim_requires_new_account',
                'Invites can only be claimed by a new Kolabing account.'
            );
        }

        $points = $ghost->pending_points;
        $mine = null;

        DB::transaction(function () use ($ghost, $claimer, $points, &$mine): void {
            $ghost->update([
                'other_profile_id' => $claimer->id,
                'claimed_at' => now(),
                'pending_points' => 0,
            ]);

            // The reverse direction, so the claimer's own "who have I met" has
            // the inviter in it from the first second.
            $mine = Encounter::create([
                'profile_id' => $claimer->id,
                'other_profile_id' => $ghost->profile_id,
                'challenge_id' => $ghost->challenge_id,
                'community_id' => $ghost->community_id,
                'event_id' => $ghost->event_id,
                'met_at' => $ghost->met_at,
                'times_met' => 1,
                'proof_photo_url' => $ghost->proof_photo_url,
                'claimed_at' => now(),
            ]);

            if ($points > 0) {
                foreach ([$ghost->profile, $claimer] as $participant) {
                    if ($participant === null) {
                        continue;
                    }
                    if ($participant->isAttendee() && $participant->attendeeProfile) {
                        $participant->attendeeProfile->increment('total_points', $points);
                    }
                }
            }
        });

        Log::info('Ghost invite claimed', [
            'encounter_id' => $ghost->id,
            'claimer_profile_id' => $claimer->id,
            'points_released' => $points,
        ]);

        return $mine ?? $ghost->refresh();
    }

    /**
     * A short code a human types off a screen.
     *
     * The alphabet leaves out `0/O` and `1/I/L` deliberately: someone is
     * reading this aloud in a noisy bar or copying it from a phone held at
     * arm's length, and a code that is ambiguous to read is a code that fails
     * for reasons the user cannot see.
     */
    public function generateClaimCode(): string
    {
        $alphabet = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';

        do {
            $code = '';
            for ($i = 0; $i < 6; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while (Encounter::query()->where('ghost_claim_token', $code)->exists());

        return $code;
    }

    /**
     * The URL that goes in the invite.
     *
     * On a phone with the app this opens it (Universal Links / App Links); on
     * one without, it opens the landing page that shows the same code in a size
     * someone can read.
     *
     * Built from the APP host, not `app.url`. The association files
     * (`.well-known/apple-app-site-association`, `assetlinks.json`) are served
     * from `webapp.host` and only paths on that host are handed to the app — an
     * invite pointing at the marketing domain would open a browser on every
     * phone, including the ones that have Kolabing installed.
     */
    public function inviteUrl(Encounter $ghost): string
    {
        return rtrim((string) config('webapp.url'), '/').'/i/'.$ghost->ghost_claim_token;
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
