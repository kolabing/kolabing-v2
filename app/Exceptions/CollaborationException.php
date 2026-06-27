<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class CollaborationException extends Exception
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        string $message,
        private readonly int $statusCode = Response::HTTP_BAD_REQUEST,
        private readonly array $context = [],
    ) {
        parent::__construct($message);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'errors' => $this->context,
        ], $this->statusCode);
    }

    public static function cannotActivate(string $currentStatus): self
    {
        return new self(
            __('collaboration.cannot_activate'),
            Response::HTTP_UNPROCESSABLE_ENTITY,
            ['status' => $currentStatus],
        );
    }

    public static function cannotComplete(string $currentStatus): self
    {
        return new self(
            __('collaboration.cannot_complete'),
            Response::HTTP_UNPROCESSABLE_ENTITY,
            ['status' => $currentStatus],
        );
    }

    public static function cannotCancel(string $currentStatus): self
    {
        return new self(
            __('collaboration.cannot_cancel'),
            Response::HTTP_UNPROCESSABLE_ENTITY,
            ['status' => $currentStatus],
        );
    }

    public static function alreadyInTerminalState(string $currentStatus): self
    {
        return new self(
            __('collaboration.already_in_terminal_state'),
            Response::HTTP_UNPROCESSABLE_ENTITY,
            ['status' => $currentStatus],
        );
    }

    public static function applicationNotAccepted(): self
    {
        return new self(
            __('collaboration.application_not_accepted'),
            Response::HTTP_UNPROCESSABLE_ENTITY,
        );
    }

    public static function collaborationAlreadyExists(): self
    {
        return new self(
            __('collaboration.already_exists'),
            Response::HTTP_CONFLICT,
        );
    }

    public static function notAParticipant(): self
    {
        return new self(
            __('You are not a participant in this collaboration.'),
            Response::HTTP_FORBIDDEN,
            ['error_code' => 'not_a_participant'],
        );
    }

    public static function feedbackAlreadySubmitted(): self
    {
        return new self(
            __('Feedback already submitted for this collaboration.'),
            Response::HTTP_CONFLICT,
            ['error_code' => 'feedback_already_submitted'],
        );
    }

    public static function feedbackNotYetSubmitted(): self
    {
        return new self(
            __('No feedback row exists to update for this collaboration.'),
            Response::HTTP_NOT_FOUND,
            ['error_code' => 'feedback_not_yet_submitted'],
        );
    }

    public static function feedbackLocked(): self
    {
        return new self(
            __('Feedback can no longer be edited because the partner has submitted theirs.'),
            Response::HTTP_LOCKED,
            ['error_code' => 'feedback_locked'],
        );
    }

    public static function awaitingOwnCompletionConfirmation(): self
    {
        return new self(
            __('Confirm whether the Kolab happened before completing it.'),
            Response::HTTP_UNPROCESSABLE_ENTITY,
            ['error_code' => 'awaiting_own_completion_confirmation'],
        );
    }

    /**
     * @param  array<int, string>  $pendingRoles
     */
    public static function awaitingPartnerCompletionConfirmation(array $pendingRoles): self
    {
        return new self(
            __('Waiting on the other party to confirm completion.'),
            Response::HTTP_UNPROCESSABLE_ENTITY,
            ['error_code' => 'awaiting_partner_completion_confirmation', 'pending_completion_from' => $pendingRoles],
        );
    }

    /**
     * The caller's own answer is 'no'/'not_yet' (regardless of the
     * partner's), OR both answered but at least one said 'no'/'not_yet'.
     * Distinct from the "hasn't responded at all" cases above — this is
     * checked FIRST against the caller's own status (2026-06-27 QA fix), so
     * a caller who hasn't confirmed 'yes' themselves never sees
     * `awaiting_partner_completion_confirmation`. `partner_status` is null
     * when the partner hasn't answered at all yet (own answer alone is
     * already blocking).
     */
    public static function completionNotConfirmed(string $ownStatus, ?string $partnerStatus): self
    {
        return new self(
            __('Both parties must confirm the Kolab happened before it can be marked complete.'),
            Response::HTTP_UNPROCESSABLE_ENTITY,
            [
                'error_code' => 'completion_not_confirmed',
                'own_status' => $ownStatus,
                'partner_status' => $partnerStatus,
            ],
        );
    }
}
