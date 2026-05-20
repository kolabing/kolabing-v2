<?php

declare(strict_types=1);

namespace App\Exceptions;

use InvalidArgumentException;

/**
 * Thrown when an action requires an active subscription that the profile lacks.
 *
 * Controllers map this to an HTTP 402 (Payment Required) response so the mobile
 * client can present its paywall. It extends InvalidArgumentException to remain
 * backward compatible with existing catch blocks and substring-based mapping.
 */
class SubscriptionRequiredException extends InvalidArgumentException {}
