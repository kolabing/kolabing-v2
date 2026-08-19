<?php

declare(strict_types=1);

namespace App\Exceptions;

use InvalidArgumentException;

/**
 * Thrown when accepting a {@see \App\Models\MultiKolabRoleApplication} would
 * exceed the role's `positions_needed`. The controller (Task 7) maps this to
 * HTTP 409 per the frozen API contract §8. Raised only after the role row is
 * locked (`lockForUpdate()`) inside {@see \App\Services\MultiKolabRoleApplicationService::accept()},
 * so under real concurrent acceptance attempts exactly one succeeds and every
 * other concurrent caller for a one-position role receives this exception.
 */
class RoleCapacityExceededException extends InvalidArgumentException {}
