<?php

declare(strict_types=1);

namespace App\Exceptions;

use InvalidArgumentException;

/**
 * Thrown when a non-organizer actor attempts an organizer-only Multi-Kolab
 * action (shortlist/decline/accept). Replaces classifying a generic
 * {@see InvalidArgumentException} via English substring matching
 * ("organizer may") in {@see \App\Http\Controllers\Api\V1\Concerns\MapsMultiKolabExceptions}
 * — mapped directly to HTTP 403 with a stable `owner` → `not_owner` code.
 */
class MultiKolabNotOrganizerException extends InvalidArgumentException {}
