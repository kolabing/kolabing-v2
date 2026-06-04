<?php

declare(strict_types=1);

namespace App\Exceptions;

use DomainException;

/**
 * Thrown when a Community Leader tries to create more communities than the
 * free cap (config('communities.max_free_communities')) allows. Reserved for
 * the NF-7 Community Premium upsell. NOT the business paywall.
 */
class CommunityLimitReachedException extends DomainException
{
    public function __construct(string $message = 'community_limit_reached')
    {
        parent::__construct($message);
    }
}
