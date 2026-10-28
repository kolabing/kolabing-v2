<?php

declare(strict_types=1);

namespace App\Enums;

enum KolabingTeamOfferStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Cancelled = 'cancelled';
    case Failed = 'failed';
}
