<?php

declare(strict_types=1);

namespace App\Enums;

enum ChallengeCompletionStatus: string
{
    case Pending = 'pending';
    case Verified = 'verified';
    case Rejected = 'rejected';

    /**
     * The challenger withdrew it before it was confirmed (kolabing-app#154).
     *
     * Its own state rather than deleting the row: the request happened, and
     * since the flow reversed (choose then scan) a mis-scan is the likeliest
     * reason someone cancels — which is worth being able to see.
     */
    case Cancelled = 'cancelled';

    /**
     * Nobody answered before the event's challenge window closed.
     *
     * A pending request that outlives its event is not waiting for anything, and
     * leaving it pending means it can still be confirmed days later for XP
     * neither person earned in the room.
     */
    case Expired = 'expired';

    /**
     * Whether this state can still change.
     */
    public function isOpen(): bool
    {
        return $this === self::Pending;
    }

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
