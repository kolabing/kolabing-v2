<?php

declare(strict_types=1);

namespace App\Data\Notifications;

readonly class NotificationTargetData
{
    public function __construct(
        public ?string $id,
        public ?string $type,
        public string $deeplink,
    ) {}
}
