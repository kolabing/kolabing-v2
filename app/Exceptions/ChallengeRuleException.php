<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * A challenge was refused by a rule, with a machine-readable reason
 * (kolabing-app#150).
 *
 * The three 409s a pair can hit are genuinely different things and want
 * different words on screen: you already asked them, you two have already done
 * this one, and this challenge is for meeting someone new. They arrived at the
 * app as one `conflict` with English prose, so it could only show a generic
 * message — which is how "nothing happened" gets reported.
 *
 * Extends LogicException on purpose: every existing `catch (\LogicException)`
 * keeps working and keeps returning 409, and only the callers that care about
 * the reason have to know this class exists.
 */
class ChallengeRuleException extends \LogicException
{
    public function __construct(
        public readonly string $reason,
        string $message,
    ) {
        parent::__construct($message);
    }
}
