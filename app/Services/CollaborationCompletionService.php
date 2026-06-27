<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CollaborationCompletionStatus;
use App\Enums\PointEventType;
use App\Exceptions\CollaborationException;
use App\Models\Collaboration;
use App\Models\CollaborationCompletion;
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

        $callerRole = $collaboration->roleFor($caller);

        if ($callerRole === null) {
            throw CollaborationException::notAParticipant();
        }

        // Completion gates purely on real completion confirmations — there is
        // no feedback-based fallback. Each party must answer 'yes' via
        // POST /completion; rich /feedback is optional impact data only.
        $own = $this->ownRow($collaboration, $caller);

        if ($own === null) {
            throw CollaborationException::awaitingOwnCompletionConfirmation();
        }

        if ($own->status !== CollaborationCompletionStatus::Yes) {
            $partner = $this->partnerRow($collaboration, $caller);
            throw CollaborationException::completionNotConfirmed($own->status->value, $partner?->status->value);
        }

        $partner = $this->partnerRow($collaboration, $caller);

        if ($partner === null) {
            throw CollaborationException::awaitingPartnerCompletionConfirmation(
                $this->pendingConfirmationFrom($collaboration),
            );
        }

        if ($partner->status !== CollaborationCompletionStatus::Yes) {
            throw CollaborationException::completionNotConfirmed($own->status->value, $partner->status->value);
        }
    }
}
