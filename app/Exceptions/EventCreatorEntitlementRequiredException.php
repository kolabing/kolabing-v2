<?php

declare(strict_types=1);

namespace App\Exceptions;

use InvalidArgumentException;

/**
 * Thrown when publishing a Multi-Kolab Event requires the maintainer-granted
 * Event Creator capability the profile does not hold.
 *
 * Mirrors {@see SubscriptionRequiredException} but is a distinct exception —
 * this is never the business paywall (`hasActiveSubscription()`), and must
 * never be caught/mapped as if it were one. Controllers (Task 7) map this to
 * HTTP 403 per the frozen API contract §5.
 */
class EventCreatorEntitlementRequiredException extends InvalidArgumentException {}
