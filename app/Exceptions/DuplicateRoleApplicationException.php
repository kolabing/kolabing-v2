<?php

declare(strict_types=1);

namespace App\Exceptions;

use InvalidArgumentException;

/**
 * Thrown when a profile applies to a {@see \App\Models\MultiKolabRole} it has
 * already applied to. The DB unique constraint on
 * `(multi_kolab_role_id, applicant_profile_id)` is the ultimate backstop for
 * a race between two concurrent requests; this exception is the deterministic
 * pre-check that gives the controller (Task 7) a clean HTTP 409 per the
 * frozen API contract §7, instead of surfacing a raw QueryException.
 */
class DuplicateRoleApplicationException extends InvalidArgumentException {}
