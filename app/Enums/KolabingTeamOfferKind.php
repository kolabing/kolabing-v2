<?php

declare(strict_types=1);

namespace App\Enums;

enum KolabingTeamOfferKind: string
{
    /** A community is offered an open business Kolab; Accept runs the apply flow. */
    case CommunityApply = 'community_apply';

    /** A business is offered a direct proposal to a named community; Accept creates it. */
    case BusinessPropose = 'business_propose';
}
