<?php

declare(strict_types=1);

namespace App\Enums;

enum SuggestionAudience: string
{
    case Business = 'business';
    case Community = 'community';
}
