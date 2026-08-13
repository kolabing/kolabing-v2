<?php

declare(strict_types=1);

namespace App\Exceptions;

use InvalidArgumentException;

/**
 * Thrown when a Multi-Kolab Event fails the strict publish-time validation
 * (as opposed to the lenient draft validation). Carries a field => messages
 * map so the controller (Task 7) can return the frozen contract's §5 error
 * shape (`errors: {field: [...]}`) instead of a single message.
 */
class MultiKolabEventPublishValidationException extends InvalidArgumentException
{
    /**
     * @param  array<string, array<int, string>>  $errors
     */
    public function __construct(private readonly array $errors)
    {
        parent::__construct('This event cannot be published yet.');
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
