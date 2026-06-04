<?php

declare(strict_types=1);

namespace App\Enums;

enum EventSignupStatus: string
{
    case Going = 'going';
    case Waitlisted = 'waitlisted';
    case Cancelled = 'cancelled';
}
