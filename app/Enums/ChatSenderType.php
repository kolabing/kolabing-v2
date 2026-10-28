<?php

declare(strict_types=1);

namespace App\Enums;

enum ChatSenderType: string
{
    case Profile = 'profile';
    case Team = 'team';
}
