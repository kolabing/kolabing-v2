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
        $role = $this->resolveRole($collaboration, $confirmer);

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
     * yet submitted ANY completion confirmation (regardless of status).
     *
     * @return array<int, string>
     */
    public function pendingConfirmationFrom(Collaboration $collaboration): array
    {
        $collaboration->loadMissing(['creatorProfile', 'applicantProfile']);

        $participantTypes = collect([
            $collaboration->creatorProfile?->user_type?->value,
            $collaboration->applicantProfile?->user_type?->value,
        ])->filter()->unique()->values()->all();

        $submittedTypes = CollaborationCompletion::query()
            ->where('collaboration_id', $collaboration->id)
            ->get()
            ->map(function (CollaborationCompletion $row) use ($collaboration): ?string {
                return $row->role === 'creator'
                    ? $collaboration->creatorProfile?->user_type?->value
                    : $collaboration->applicantProfile?->user_type?->value;
            })
            ->filter()
            ->all();

        return array_values(array_diff($participantTypes, $submittedTypes));
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
        if ($caller === null) {
            $pending = $this->pendingConfirmationFrom($collaboration);
            if ($pending !== []) {
                throw CollaborationException::awaitingPartnerCompletionConfirmation($pending);
            }

            return;
        }

        $collaboration->loadMissing(['creatorProfile', 'applicantProfile']);
        $callerRole = $this->resolveRole($collaboration, $caller);

        if ($callerRole === null) {
            throw CollaborationException::notAParticipant();
        }

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
            $pending = $this->pendingConfirmationFrom($collaboration);
            throw CollaborationException::awaitingPartnerCompletionConfirmation($pending);
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

    private function resolveRole(Collaboration $collaboration, Profile $confirmer): ?string
    {
        return match (true) {
            $confirmer->id === $collaboration->creator_profile_id => 'creator',
            $confirmer->id === $collaboration->applicant_profile_id => 'applicant',
            default => null,
        };
    }
}
