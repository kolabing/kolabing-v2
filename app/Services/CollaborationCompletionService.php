<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CollaborationCompletionStatus;
use App\Enums\PointEventType;
use App\Exceptions\CollaborationException;
use App\Models\Collaboration;
use App\Models\CollaborationCompletion;
use App\Models\CollaborationFeedback;
use App\Models\Profile;
use Illuminate\Support\Facades\DB;

/**
 * Lightweight, required completion-confirmation step (PR 1 of the
 * 2026-06-26 completion-flow simplification). Each participant answers
 * yes/no/not_yet once; /complete gates on this table instead of the rich
 * collaboration_feedback table. XP is awarded once, on first submission,
 * via a dedicated event type so it never collides with feedback XP.
 */
class CollaborationCompletionService
{
    public function __construct(
        private readonly GamificationWalletService $walletService,
    ) {}

    /**
     * Submit (or update) the caller's completion confirmation. XP is only
     * awarded the first time a row is created for this caller — resubmitting
     * to change e.g. not_yet -> yes never re-awards.
     *
     * @throws CollaborationException
     */
    public function submit(Collaboration $collaboration, Profile $confirmer, string $status, ?string $note = null): CollaborationCompletion
    {
        // A completed/cancelled Kolab is settled — never accept a new
        // confirmation (and never award its XP) against a terminal collaboration.
        if ($collaboration->isInTerminalState()) {
            throw CollaborationException::alreadyInTerminalState($collaboration->status->value);
        }

        $role = $collaboration->roleFor($confirmer);

        if ($role === null) {
            throw CollaborationException::notAParticipant();
        }

        return DB::transaction(function () use ($collaboration, $confirmer, $role, $status, $note): CollaborationCompletion {
            $existing = $this->ownRow($collaboration, $confirmer);

            if ($existing !== null) {
                $existing->fill(['status' => $status, 'note' => $note]);
                $existing->save();

                return $existing->fresh();
            }

            $completion = CollaborationCompletion::create([
                'collaboration_id' => $collaboration->id,
                'profile_id' => $confirmer->id,
                'role' => $role,
                'status' => $status,
                'note' => $note,
            ]);

            $this->walletService->awardPoints(
                $confirmer->id,
                PointEventType::CollaborationCompletionConfirmed,
                $collaboration->id,
                'Confirmed Kolab completion',
            );

            return $completion;
        });
    }

    public function ownRow(Collaboration $collaboration, Profile $confirmer): ?CollaborationCompletion
    {
        return CollaborationCompletion::query()
            ->where('collaboration_id', $collaboration->id)
            ->where('profile_id', $confirmer->id)
            ->first();
    }

    public function partnerRow(Collaboration $collaboration, Profile $confirmer): ?CollaborationCompletion
    {
        return CollaborationCompletion::query()
            ->where('collaboration_id', $collaboration->id)
            ->where('profile_id', '!=', $confirmer->id)
            ->first();
    }

    /**
     * Reviewer types ('business' | 'community') of participants who have NOT
     * confirmed 'yes' — i.e. they have no row at all, or answered 'no'/'not_yet'.
     * This matches what the gate actually requires (both parties 'yes'), so the
     * API resource and the gate never disagree about who is still blocking.
     *
     * @return array<int, string>
     */
    public function pendingConfirmationFrom(Collaboration $collaboration): array
    {
        $collaboration->loadMissing(['creatorProfile', 'applicantProfile']);

        $confirmedProfileIds = $this->completionRowsFor($collaboration)
            ->filter(fn (CollaborationCompletion $row): bool => $row->status === CollaborationCompletionStatus::Yes)
            ->pluck('profile_id')
            ->all();

        return collect([
            $collaboration->creatorProfile,
            $collaboration->applicantProfile,
        ])
            ->filter()
            ->reject(fn (Profile $profile): bool => in_array($profile->id, $confirmedProfileIds, true))
            ->map(fn (Profile $profile): ?string => $profile->user_type?->value)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Completion rows for a collaboration, served from the eager-loaded
     * `completions` relation when present (avoids an N+1 in the API resource)
     * and falling back to a single query otherwise.
     *
     * @return \Illuminate\Support\Collection<int, CollaborationCompletion>
     */
    private function completionRowsFor(Collaboration $collaboration): \Illuminate\Support\Collection
    {
        if ($collaboration->relationLoaded('completions')) {
            return $collaboration->completions;
        }

        return CollaborationCompletion::query()
            ->where('collaboration_id', $collaboration->id)
            ->get();
    }

    /**
     * Error precedence (2026-06-27 QA fix): the caller's OWN answer is
     * checked before the partner's. A caller who answered 'no'/'not_yet'
     * themselves is told `completion_not_confirmed`, never
     * `awaiting_partner_completion_confirmation` — that error is reserved
     * for the case where the caller has genuinely confirmed 'yes' and is
     * only blocked on the partner.
     *
     * @throws CollaborationException
     */
    public function enforceGate(Collaboration $collaboration, ?Profile $caller): void
    {
        // System path (non-interactive caller): require EVERY participant to
        // have answered 'yes'. pendingConfirmationFrom now reports anyone who
        // has not — including explicit 'no'/'not_yet' — so this path no longer
        // lets a refusal slip through.
        if ($caller === null) {
            $pending = $this->pendingConfirmationFrom($collaboration);
            if ($pending !== []) {
                throw CollaborationException::awaitingPartnerCompletionConfirmation($pending);
            }

            return;
        }

        $collaboration->loadMissing(['creatorProfile', 'applicantProfile']);
        $callerRole = $collaboration->roleFor($caller);

        if ($callerRole === null) {
            throw CollaborationException::notAParticipant();
        }

        // Own answer is checked first (error precedence). The feedback fallback
        // (own and partner) keeps legacy /feedback-only and /review-only clients
        // un-stranded by treating an existing feedback row as an implicit 'yes'.
        // It is safe against the new flow: a client that has touched the
        // /completion endpoint always has a real row, so partnerRow() returns it
        // and the fallback never overrides an explicit no/not_yet — the fallback
        // only ever applies to genuine pre-PR-1 clients with no completion row.
        $own = $this->ownRow($collaboration, $caller)
            ?? $this->fallbackFromFeedback($collaboration, $caller, $callerRole);

        if ($own === null) {
            throw CollaborationException::awaitingOwnCompletionConfirmation();
        }

        if ($own->status !== CollaborationCompletionStatus::Yes) {
            $partner = $this->partnerRow($collaboration, $caller);
            throw CollaborationException::completionNotConfirmed($own->status->value, $partner?->status->value);
        }

        $partnerProfile = $callerRole === 'creator'
            ? $collaboration->applicantProfile
            : $collaboration->creatorProfile;
        $partnerRole = $callerRole === 'creator' ? 'applicant' : 'creator';

        $partner = $this->partnerRow($collaboration, $caller)
            ?? ($partnerProfile !== null ? $this->fallbackFromFeedback($collaboration, $partnerProfile, $partnerRole) : null);

        if ($partner === null) {
            throw CollaborationException::awaitingPartnerCompletionConfirmation(
                $this->pendingConfirmationFrom($collaboration),
            );
        }

        if ($partner->status !== CollaborationCompletionStatus::Yes) {
            throw CollaborationException::completionNotConfirmed($own->status->value, $partner->status->value);
        }
    }

    /**
     * Backward-compatibility fallback (PR 1 follow-up, 2026-06-27).
     *
     * Old app builds POST `/feedback` then `/complete`, expecting feedback
     * alone to satisfy completion — there is no client code path to call the
     * new `/completion` endpoint. If a participant has no completion row but
     * already has a `collaboration_feedback` row, treat that submission as
     * an implicit 'yes' confirmation so those clients are never stranded.
     * The row is persisted (not just computed) so subsequent calls — and the
     * API resource — see a real, stable confirmation.
     *
     * No XP is awarded here: the old `/feedback` flow already paid
     * `CollaborationComplete` XP for this submission, and awarding
     * `CollaborationCompletionConfirmed` on top would double-pay.
     *
     * TODO: remove once old (pre-PR-1) app builds are no longer supported —
     * at that point every client will call `/completion` directly and this
     * fallback becomes dead code. See also the one-time backfill migration
     * `2026_06_27_*_backfill_collaboration_completions_from_feedback.php`,
     * which covers existing rows so this runtime path is only a safety net.
     */
    private function fallbackFromFeedback(Collaboration $collaboration, Profile $participant, string $role): ?CollaborationCompletion
    {
        $hasFeedback = CollaborationFeedback::query()
            ->where('collaboration_id', $collaboration->id)
            ->where('reviewer_profile_id', $participant->id)
            ->exists();

        if (! $hasFeedback) {
            return null;
        }

        return CollaborationCompletion::query()->firstOrCreate(
            [
                'collaboration_id' => $collaboration->id,
                'profile_id' => $participant->id,
            ],
            [
                'role' => $role,
                'status' => CollaborationCompletionStatus::Yes->value,
                'note' => null,
            ],
        );
    }
}
