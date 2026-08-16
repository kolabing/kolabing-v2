<?php

declare(strict_types=1);

namespace App\Services\Concerns;

/**
 * Runs a single non-critical side effect (notification delivery, analytics
 * capture) with best-effort semantics: a failure is reported and swallowed,
 * never allowed to invalidate already-committed domain state or turn a
 * successful operation into an uncaught 500. Mirrors the resilience pattern
 * already established in {@see \App\Services\ApplicationService::accept()}.
 *
 * Each call is independent — call this once per side effect, not once
 * wrapping several, so one failure never prevents the others from being
 * attempted.
 *
 * This is deliberately best-effort, not a durable outbox/retry queue — a
 * dropped notification is not re-attempted. That is consistent with the
 * existing application architecture (the same trade-off `ApplicationService`
 * already makes) and is an explicit, accepted scope boundary for this MVP
 * hardening pass, not an oversight.
 */
trait RunsSideEffects
{
    private function runSideEffect(\Closure $effect): void
    {
        try {
            $effect();
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
