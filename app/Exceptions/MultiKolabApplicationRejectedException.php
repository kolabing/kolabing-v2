<?php

declare(strict_types=1);

namespace App\Exceptions;

use InvalidArgumentException;

/**
 * A role-application rejection that carries a stable, machine-readable
 * `code` (and the `errors` field it belongs under) so the client never has
 * to match on the localized/human-readable message. Controllers map this to
 * HTTP 422 with `errors: {field: [code]}`, per the frozen API contract §10.
 */
class MultiKolabApplicationRejectedException extends InvalidArgumentException
{
    public function __construct(
        string $message,
        private readonly string $errorCode,
        private readonly string $field = 'application',
    ) {
        parent::__construct($message);
    }

    public function code(): string
    {
        return $this->errorCode;
    }

    public function field(): string
    {
        return $this->field;
    }
}
