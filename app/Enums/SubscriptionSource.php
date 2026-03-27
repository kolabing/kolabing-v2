<?php

declare(strict_types=1);

namespace App\Enums;

enum SubscriptionSource: string
{
    case Stripe = 'stripe';
    case AppleIap = 'apple_iap';
}
