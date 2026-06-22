<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Which side may add a challenge to a collaboration. Stored on
 * `challenges.audience`. The app's Gamification Setup card filters the
 * selectable pool by the viewer's role using this value.
 */
enum ChallengeAudience: string
{
    case Community = 'community';
    case Business = 'business';
    case Both = 'both';
    case Attendee = 'attendee';

    /**
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::Community => 'Community',
            self::Business => 'Business',
            self::Both => 'Both',
            self::Attendee => 'Attendee',
        };
    }
}
