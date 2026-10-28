<?php

declare(strict_types=1);

namespace App\Enums;

enum ChatThreadType: string
{
    case Collaboration = 'collaboration';
    case CommunityMain = 'community_main';
    case CommunityCustom = 'community_custom';
    case Event = 'event';
    /** One thread per profile: Kolabing speaking as itself (SERR-658). */
    case KolabingTeam = 'kolabing_team';
}
