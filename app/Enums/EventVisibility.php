<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Who may SEE an event.
 *
 * - Public  — anyone; surfaces in city discover.
 * - Members — host community members (the historical default).
 * - Tier    — restricted to the tiers listed in `events.tier_gate`.
 */
enum EventVisibility: string
{
    case Public = 'public';
    case Members = 'members';
    case Tier = 'tier';
}
