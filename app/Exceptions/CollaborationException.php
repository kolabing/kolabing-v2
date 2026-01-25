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
}
