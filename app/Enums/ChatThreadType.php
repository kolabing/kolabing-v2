<?php

declare(strict_types=1);

namespace App\Enums;

enum ChatThreadType: string
{
    case Collaboration = 'collaboration';
    case CommunityMain = 'community_main';
    case CommunityCustom = 'community_custom';
    case Event = 'event';
}
