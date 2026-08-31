<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Http\Middleware\EnsureProfileActive;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Thrown when an account an admin switched off tries to get a token (#254).
 *
 * Exists so login, Google, Apple and password reset all answer identically
 * without four controllers each assembling the payload — and, more importantly,
 * so none of them falls back to "Invalid credentials", which sends someone to
 * reset a password that was never the problem.
 */
class AccountDeactivatedException extends RuntimeException
{
    public function render(Request $request): JsonResponse
    {
        return EnsureProfileActive::deactivated();
    }
}
